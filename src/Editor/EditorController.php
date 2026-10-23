<?php
declare(strict_types=1);

namespace Pixelite\Editor;

use Pixelite\Csrf;
use Pixelite\CustomerAuth;
use Pixelite\I18n;
use Pixelite\Logger;
use Pixelite\Paths;
use Pixelite\Projects;
use Pixelite\RateLimiter;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\Session;

/**
 * EXPERIMENTAL visual editor (proof of concept). Customer-only, draft-only; nothing here publishes anything.
 *
 * Trust model
 *  - Every route resolves the project from the signed-in customer's session: a project that does not exist and a project that belongs to
 *    someone else both answer 404 with the same body. No route takes a file name, directory or customer id from the browser.
 *  - The browser only ever sends typed field VALUES (Schema); markup is never accepted. The documents the editor shows (canvas, preview) are
 *    rendered on the server from the template + those values with e(), so no customer-authored HTML or script runs on the Pixelite origin.
 *  - Mutations need the session CSRF token AND the X-Requested-With header (a custom header cannot be sent cross-site without a CORS preflight).
 */
final class EditorController extends EditorBase
{
    private const CSP_SHELL = "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; "
        . "connect-src 'self'; frame-src 'self'; form-action 'none'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'";
    private const CSP_DOCUMENT = "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' data:; media-src 'self'; font-src 'self'; "
        . "connect-src 'none'; form-action 'none'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'";
    private const THEME_VARS = ['color_title_1' => '--px-title-1', 'color_title_2' => '--px-title-2', 'color_copy' => '--px-copy', 'color_cta' => '--px-cta-bg'];

    // ---- pages -------------------------------------------------------------------------------------------------------------------

    public function shell(Request $r, array $p): Response
    {
        $ctx = $this->context($r, $p, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        ['project' => $project] = $ctx;
        $draft = DraftStore::load($project['uid']);
        $base = '/editor/projects/' . (int) $project['id'];
        $html = $this->render('editor/shell', [
            'project' => ['id' => (int) $project['id'], 'name' => (string) $project['name']],
            'template' => Schema::template()['title'],
            'csrf' => Csrf::token(),
            'base' => $base,
            'version' => $draft['version'],
            'imageUploads' => Media::available(),
        ]);
        return $this->html($html, self::CSP_SHELL, 'DENY');
    }

    public function canvas(Request $r, array $p): Response
    {
        return $this->document($r, $p, true);
    }

    public function preview(Request $r, array $p): Response
    {
        return $this->document($r, $p, false);
    }

    public function themeCss(Request $r, array $p): Response
    {
        $ctx = $this->context($r, $p, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $v = DraftStore::load($ctx['project']['uid'])['values'];
        $decl = [];
        foreach (self::THEME_VARS as $key => $var) {
            if (preg_match('/^#[0-9a-f]{6}\z/', (string) ($v[$key] ?? ''))) {
                $decl[] = "$var:{$v[$key]}";
            }
        }
        if (preg_match('/^#[0-9a-f]{6}\z/', (string) $v['color_cta'])) {
            $decl[] = '--px-cta-text:' . Schema::onColor($v['color_cta']);
        }
        $css = "/* generated from validated #rrggbb values only */\nbody{" . implode(';', $decl) . "}\n";
        return new Response(200, $css, ['Content-Type' => 'text/css; charset=utf-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    // ---- JSON API ----------------------------------------------------------------------------------------------------------------

    public function draft(Request $r, array $p): Response
    {
        $ctx = $this->context($r, $p, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $d = DraftStore::load($ctx['project']['uid']);
        return $this->json(['ok' => true, 'version' => $d['version'], 'updated_at' => $d['updated_at'], 'values' => $d['values']]);
    }

    public function saveDraft(Request $r, array $p): Response
    {
        $ctx = $this->context($r, $p, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        ['project' => $project, 'customer' => $c] = $ctx;
        if (($guard = $this->mutationGuard($r, 'ed-save:' . (int) $c['id'], 120, 600)) !== null) {
            return $guard;
        }
        $raw = $r->post('values');
        $values = strlen($raw) <= Schema::MAX_VALUES_BYTES ? json_decode($raw, true, 4) : null;
        $base = $r->post('base_version');
        if (!is_array($values) || ($values !== [] && array_is_list($values)) || !ctype_digit($base) || strlen($base) > 9) {
            return $this->json(['ok' => false, 'error' => 'Invalid request.'], 400);
        }
        $uid = $project['uid'];
        [$clean, $errors] = Schema::validate($values, static fn(string $id): bool => Media::exists($uid, $id));
        if ($errors) {
            return $this->json(['ok' => false, 'error' => 'Some values need a change.', 'fields' => $errors], 422);
        }
        $res = DraftStore::save($uid, (int) $base, $clean);
        if (!empty($res['conflict'])) {
            return $this->json(['ok' => false, 'error' => 'This draft was changed in another window. Reload to continue.', 'version' => $res['version']], 409);
        }
        if (!$res['ok']) {
            return $this->json(['ok' => false, 'error' => 'The draft could not be saved.'], 500);
        }
        return $this->json(['ok' => true, 'version' => $res['version'], 'updated_at' => $res['updated_at']]);
    }

    public function uploadMedia(Request $r, array $p): Response
    {
        $ctx = $this->context($r, $p, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        ['project' => $project, 'customer' => $c] = $ctx;
        if (($guard = $this->mutationGuard($r, 'ed-up:' . (int) $c['id'], 30, 3600)) !== null) {
            return $guard;
        }
        $f = $r->file('image');
        if ($f === null || !is_string($f['tmp_name'] ?? null) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || ($this->verifyUploaded && !is_uploaded_file($f['tmp_name']))) {
            return $this->json(['ok' => false, 'error' => 'Choose an image to upload (JPEG, PNG or WebP, up to 4 MB).'], 400);
        }
        if ((int) ($f['size'] ?? 0) > Media::MAX_UPLOAD_BYTES) {
            return $this->json(['ok' => false, 'error' => 'Images must be at most 4 MB.'], 413);
        }
        $res = Media::ingest($project['uid'], (string) file_get_contents($f['tmp_name'], false, null, 0, Media::MAX_UPLOAD_BYTES + 1));
        if (!$res['ok']) {
            return $this->json(['ok' => false, 'error' => $res['error']], 422);
        }
        return $this->json(['ok' => true, 'id' => $res['id'], 'url' => "/editor/projects/" . (int) $project['id'] . "/media/{$res['id']}"]);
    }

    public function media(Request $r, array $p): Response
    {
        $ctx = $this->context($r, $p, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $file = Media::path($ctx['project']['uid'], (string) ($p['slug'] ?? ''));
        if ($file === null) {
            return $this->notFound();
        }
        return new Response(200, '', [
            'Content-Type' => Media::mime($file), 'Content-Length' => (string) filesize($file), 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store', 'Cross-Origin-Resource-Policy' => 'same-origin',
        ], $file);
    }

    // ---- internals ---------------------------------------------------------------------------------------------------------------

    private function document(Request $r, array $p, bool $edit): Response
    {
        $ctx = $this->context($r, $p, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $project = $ctx['project'];
        $d = DraftStore::load($project['uid']);
        $base = '/editor/projects/' . (int) $project['id'];
        $html = $this->render(Schema::template()['view'], [
            'v' => $d['values'], 'edit' => $edit, 'themeUrl' => $base . '/theme.css?v=' . $d['version'] . '-' . bin2hex(random_bytes(3)),
            'mediaUrl' => static fn(string $id): string => $base . '/media/' . $id,
        ]);
        return $this->html($html, self::CSP_DOCUMENT, 'SAMEORIGIN');
    }
}
