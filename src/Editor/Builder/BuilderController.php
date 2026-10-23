<?php
declare(strict_types=1);

namespace Pixelite\Editor\Builder;

use Pixelite\Csrf;
use Pixelite\Editor\EditorBase;
use Pixelite\Editor\Media;
use Pixelite\Request;
use Pixelite\Response;

/**
 * Site builder (VvvebJs, free-form HTML + CSS). Works on a Workspace: a customer's project (this class: session + ownership, identical 404 otherwise) or a visitor's
 * temporary workspace (PublicBuilderController). Everything below is shared, so both get the same behaviour:
 *   - nothing the browser sends is stored as sent: HtmlSanitizer/CssSanitizer rebuild it from allowlists and the reply carries the sanitised result;
 *   - the documents that show customer markup (canvas, preview) are served sandboxed and with script-src 'none': even a sanitiser bug cannot execute script;
 *   - customer JavaScript does not exist in this design (no <script>, no event attributes, no embeds);
 *   - mutations need CSRF + X-Requested-With and are rate-limited; quotas come from the Workspace.
 */
class BuilderController extends EditorBase
{
    protected const CSP_SHELL = "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; "
        . "connect-src 'self'; frame-src 'self'; form-action 'none'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'";
    /** No script at all (even inline or via attributes), no forms, no navigation of the parent, unique-origin NOT used so the editor can reach the DOM. */
    public const CSP_DOCUMENT = "sandbox allow-same-origin; default-src 'none'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; "
        . "base-uri 'none'; form-action 'none'; frame-ancestors 'self'";

    /**
     * Who is working on what. Customers: a project they own. Returns a Response (redirect / 401 / 404) when there is nothing to work on.
     * The public builder overrides this with the visitor's temporary workspace.
     */
    protected function workspace(Request $r, array $p, bool $page): Workspace|Response
    {
        $ctx = $this->context($r, $p, $page);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $pr = $ctx['project'];
        $id = (int) $pr['id'];
        return new Workspace((string) $pr['uid'], "/editor/projects/$id/builder", "/editor/projects/$id/media", 'c:' . (int) $ctx['customer']['id'], (string) $pr['name'], false, Workspace::projectLimits());
    }

    /** Mutation guard: rate limit + CSRF + X-Requested-With. $kind: save | page | media | upload. */
    protected function guard(Request $r, Workspace $w, string $kind): ?Response
    {
        [$limit, $window] = ['save' => [240, 600], 'page' => [30, 3600], 'media' => [60, 3600], 'upload' => [20, 3600]][$kind];
        return $this->mutationGuard($r, "bd-$kind:" . $w->owner, $limit, $window);
    }

    /** Variables of the builder page (templates/editor/builder.php). */
    protected function shellData(Workspace $w, array $site): array
    {
        return [
            'project' => ['id' => 0, 'name' => $w->label], 'csrf' => Csrf::token(), 'api' => $w->api, 'upload' => preg_replace('#/builder$#', '', $w->api) . '/media',
            'version' => $site['version'], 'imageUploads' => Media::available(), 'sanitizer' => HtmlSanitizer::available(), 'anonymous' => false, 'home' => url('account'),
        ];
    }

    public function shell(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, true);
        if ($w instanceof Response) {
            return $w;
        }
        return $this->html($this->render('editor/builder', $this->shellData($w, SiteStore::load($w->uid))), static::CSP_SHELL, 'DENY');
    }

    public function canvas(Request $r, array $p): Response
    {
        return $this->document($r, $p, true);
    }

    public function preview(Request $r, array $p): Response
    {
        return $this->document($r, $p, false);
    }

    public function site(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        $s = SiteStore::load($w->uid);
        return $this->json(['ok' => true, 'version' => $s['version'], 'temporary' => $w->anonymous, 'pages' => array_map(static fn(string $slug) => ['slug' => $slug, 'title' => $s['pages'][$slug]['title']], $s['order'])]);
    }

    public function savePage(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        if (($guard = $this->guard($r, $w, 'save')) !== null) {
            return $guard;
        }
        $slug = (string) ($p['slug'] ?? '');
        $html = $r->post('html');
        $css = $r->post('css');
        $title = trim((string) preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}\s]+/u', ' ', $r->post('title')));
        $base = $r->post('base_version');
        if (!ctype_digit($base) || strlen($base) > 9 || mb_strlen($title) > 80 || !mb_check_encoding($html . $css . $title, 'UTF-8')) {
            return $this->json(['ok' => false, 'error' => 'Invalid request.'], 400);
        }
        if (!HtmlSanitizer::available()) {
            return $this->json(['ok' => false, 'error' => 'Saving pages is not available on this server.'], 503);
        }
        if (strlen($html) > HtmlSanitizer::MAX_BYTES || strlen($css) > CssSanitizer::MAX_BYTES) {
            return $this->json(['ok' => false, 'error' => 'This page is too large to save.'], 413);
        }
        $san = new HtmlSanitizer($w->mediaPrefix);
        $clean = $san->body($html);
        if ($clean === null) {
            return $this->json(['ok' => false, 'error' => 'This page is too large or too complex to be saved safely.'], 422);
        }
        $urlOk = fn(string $u): bool => $san->imageUrlOk($u);
        CssSanitizer::$notes = [];
        $cleanCss = CssSanitizer::stylesheet($css, $urlOk);
        if ($cleanCss !== CssSanitizer::stylesheet($cleanCss, $urlOk)) {   // fixpoint, like the HTML
            return $this->json(['ok' => false, 'error' => 'The CSS could not be saved safely.'], 422);
        }
        $notes = array_values(array_unique(array_merge($clean['notes'], CssSanitizer::$notes)));
        $contentLimit = (int) $w->limits['content'];
        $res = SiteStore::mutate($w->uid, (int) $base, static function (array $site) use ($slug, $clean, $cleanCss, $title, $contentLimit): array|string|null {
            if (!isset($site['pages'][$slug])) {
                return null;
            }
            $site['pages'][$slug] = ['title' => $title !== '' ? $title : $site['pages'][$slug]['title'], 'html' => $clean['html'], 'css' => $cleanCss];
            $total = 0;
            foreach ($site['pages'] as $pg) {
                $total += strlen($pg['html']) + strlen($pg['css']);
            }
            return $contentLimit > 0 && $total > $contentLimit
                ? 'This workspace is full: remove some content or an unused page. A temporary workspace holds up to ' . intdiv($contentLimit, 1000) . ' KB of page content.'
                : $site;
        });
        return $this->mutationResult($res, ['html' => $clean['html'], 'css' => $cleanCss, 'title' => $title, 'notes' => array_slice($notes, 0, 8)]);
    }

    public function createPage(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        if (($guard = $this->guard($r, $w, 'page')) !== null) {
            return $guard;
        }
        $slug = $r->post('slug');
        $title = trim((string) preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $r->post('title')));
        $base = $r->post('base_version');
        if (preg_match(SiteStore::SLUG, $slug) !== 1 || $title === '' || mb_strlen($title) > 80 || !ctype_digit($base) || strlen($base) > 9 || !mb_check_encoding($title, 'UTF-8')) {
            return $this->json(['ok' => false, 'error' => 'Use a short name with letters, digits and hyphens.'], 422);
        }
        $maxPages = (int) $w->limits['pages'];
        $res = SiteStore::mutate($w->uid, (int) $base, static function (array $site) use ($slug, $title, $maxPages): array|string|null {
            if (isset($site['pages'][$slug])) {
                return null;
            }
            if (count($site['pages']) >= $maxPages) {
                return "This workspace holds up to $maxPages pages.";
            }
            $site['pages'][$slug] = ['title' => $title, 'html' => '', 'css' => ''];
            $site['order'][] = $slug;
            return $site;
        });
        return $this->mutationResult($res, ['slug' => $slug]);
    }

    public function deletePage(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        if (($guard = $this->guard($r, $w, 'page')) !== null) {
            return $guard;
        }
        $slug = (string) ($p['slug'] ?? '');
        $base = $r->post('base_version');
        if ($slug === 'index' || !ctype_digit($base) || strlen($base) > 9) {
            return $this->json(['ok' => false, 'error' => 'The home page cannot be deleted.'], 422);
        }
        $res = SiteStore::mutate($w->uid, (int) $base, static function (array $site) use ($slug): ?array {
            if (!isset($site['pages'][$slug])) {
                return null;
            }
            unset($site['pages'][$slug]);
            $site['order'] = array_values(array_diff($site['order'], [$slug]));
            return $site;
        });
        return $this->mutationResult($res, []);
    }

    public function mediaList(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        return $this->json(['ok' => true, 'images' => array_map(
            static fn(array $m) => ['id' => $m['id'], 'url' => $w->mediaPrefix . '/' . $m['id'], 'bytes' => $m['bytes'], 'used' => SiteStore::usesMedia($w->uid, $m['id'])],
            Media::all($w->uid)
        )]);
    }

    public function mediaDelete(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        if (($guard = $this->guard($r, $w, 'media')) !== null) {
            return $guard;
        }
        $id = (string) ($p['slug'] ?? '');
        if (!Media::exists($w->uid, $id)) {
            return $this->json(['ok' => false, 'error' => 'Image not found.'], 404);
        }
        if (SiteStore::usesMedia($w->uid, $id)) {
            return $this->json(['ok' => false, 'error' => 'This image is used on a page. Remove it from the page first.'], 409);
        }
        return Media::delete($w->uid, $id) ? $this->json(['ok' => true]) : $this->json(['ok' => false, 'error' => 'The image could not be deleted.'], 500);
    }

    /** Upload an image into the workspace: real upload only, content-checked and re-encoded (Media::ingest), within the workspace's quota. */
    public function uploadMedia(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        if (($guard = $this->guard($r, $w, 'upload')) !== null) {
            return $guard;
        }
        $f = $r->file('image');
        if ($f === null || !is_string($f['tmp_name'] ?? null) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || ($this->verifyUploaded && !is_uploaded_file($f['tmp_name']))) {
            $tooBig = in_array((int) ($f['error'] ?? 0), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            return $this->json(['ok' => false, 'error' => $tooBig ? 'That file is larger than this server accepts.' : 'Choose an image to upload (JPEG, PNG or WebP, up to 4 MB).'], $tooBig ? 413 : 400);
        }
        if ((int) ($f['size'] ?? 0) > Media::MAX_UPLOAD_BYTES) {
            return $this->json(['ok' => false, 'error' => 'Images must be at most 4 MB.'], 413);
        }
        if (($full = $this->storageFull($w)) !== null) {
            return $full;
        }
        $res = Media::ingest($w->uid, (string) file_get_contents($f['tmp_name'], false, null, 0, Media::MAX_UPLOAD_BYTES + 1), ['files' => $w->limits['media_files'], 'bytes' => $w->limits['media_bytes']]);
        if (!$res['ok']) {
            return $this->json(['ok' => false, 'error' => $res['error']], 422);
        }
        return $this->json(['ok' => true, 'id' => $res['id'], 'url' => $w->mediaPrefix . '/' . $res['id']]);
    }

    /** Serve one uploaded image of the workspace (owner only; fixed image Content-Type, nosniff, sandboxed). */
    public function mediaFile(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        $file = Media::path($w->uid, (string) ($p['slug'] ?? ''));
        if ($file === null) {
            return $this->notFoundText();
        }
        return new Response(200, '', [
            'Content-Type' => Media::mime($file), 'Content-Length' => (string) filesize($file), 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store', 'Cross-Origin-Resource-Policy' => 'same-origin',
        ], $file);
    }

    /** Hook for the public builder: a global storage cap. Customers' projects have none here. */
    protected function storageFull(Workspace $w): ?Response
    {
        return null;
    }

    // ---- internals ---------------------------------------------------------------------------------------------------------------

    protected function document(Request $r, array $p, bool $edit): Response
    {
        $w = $this->workspace($r, $p, false);
        if ($w instanceof Response) {
            return $w;
        }
        $slug = (string) ($p['slug'] ?? '');
        $site = SiteStore::load($w->uid);
        if (!isset($site['pages'][$slug]) || !HtmlSanitizer::available()) {
            return $this->notFoundText();
        }
        return $this->html(PageRenderer::document($site['pages'][$slug], $w->mediaPrefix, $edit), self::CSP_DOCUMENT, 'SAMEORIGIN');
    }

    protected function notFoundText(): Response
    {
        return new Response(404, 'Not found', ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    /** @param array<string,mixed> $extra */
    protected function mutationResult(array $res, array $extra): Response
    {
        if (!empty($res['conflict'])) {
            return $this->json(['ok' => false, 'error' => 'This site was changed in another window. Reload to continue.', 'version' => $res['version']], 409);
        }
        if (!empty($res['refused'])) {
            return $this->json(['ok' => false, 'error' => $res['reason'] ?? 'That change is not possible (page missing or name taken).'], 422);
        }
        if (!$res['ok']) {
            return $this->json(['ok' => false, 'error' => 'The site could not be saved.'], 500);
        }
        return $this->json(['ok' => true, 'version' => $res['version'], 'updated_at' => $res['updated_at']] + $extra);
    }
}
