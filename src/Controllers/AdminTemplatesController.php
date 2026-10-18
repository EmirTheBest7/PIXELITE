<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Auth;
use Pixelite\Csrf;
use Pixelite\Database;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\Session;
use Pixelite\TemplateCatalog;
use Pixelite\TemplatePrompts;
use Pixelite\View;

/** Admin-only prompt library. Same session/CSRF guard as the orders area; English only, never indexed. */
final class AdminTemplatesController
{
    public function index(Request $r): Response
    {
        if ($guard = $this->guard($r)) {
            return $guard;
        }
        $q = mb_strtolower(mb_substr(trim($r->query('q')), 0, 100));
        $status = in_array($r->query('status'), ['has', 'missing'], true) ? $r->query('status') : '';
        $cat = in_array($r->query('category'), TemplateCatalog::categories(), true) ? $r->query('category') : '';
        $prompts = TemplatePrompts::summaries();
        $rows = [];
        foreach (TemplateCatalog::all() as $slug => $e) {
            $p = $prompts[$slug] ?? null;
            $title = TemplateCatalog::englishTitle($slug);
            if (($status === 'has' && $p === null) || ($status === 'missing' && $p !== null) || ($cat !== '' && $e['category'] !== $cat)
                || ($q !== '' && !str_contains(mb_strtolower("$slug $title {$e['category']} {$e['origin']['author']}"), $q))) {
                continue;
            }
            $rows[] = ['slug' => $slug, 'title' => $title, 'e' => $e, 'p' => $p];
        }
        return $this->view('admin/templates', [
            'rows' => $rows, 'q' => $q, 'status' => $status, 'category' => $cat, 'csrf' => Csrf::token(),
            'missing' => count(array_diff_key(TemplateCatalog::all(), $prompts)), 'total' => count(TemplateCatalog::all()),
            'flash' => $this->flash(),
        ]);
    }

    public function show(Request $r, array $p): Response
    {
        if ($guard = $this->guard($r)) {
            return $guard;
        }
        $e = TemplateCatalog::find($p['slug']);
        if ($e === null) {
            return Response::html(View::render('admin/notfound', [], 'admin/layout'), 404);
        }
        $orders = Database::pdo()->prepare('SELECT COUNT(*) FROM orders WHERE template = ?');
        $orders->execute([$p['slug']]);
        return $this->view('admin/template', [
            'slug' => $p['slug'], 'title' => TemplateCatalog::englishTitle($p['slug']), 'e' => $e, 'prompt' => TemplatePrompts::find($p['slug']),
            'orders' => (int) $orders->fetchColumn(), 'csrf' => Csrf::token(), 'flash' => $this->flash(), 'errors' => [],
        ]);
    }

    public function save(Request $r, array $p): Response
    {
        if ($guard = $this->guard($r)) {
            return $guard;
        }
        $e = TemplateCatalog::find($p['slug']);
        if ($e === null) {
            return Response::html(View::render('admin/notfound', [], 'admin/layout'), 404);
        }
        if (!Csrf::valid($r->post('_csrf'))) {
            return Response::html(View::render('admin/notfound', [], 'admin/layout'), 403);
        }
        $body = trim(str_replace("\r\n", "\n", $r->post('body')));
        $errors = [];
        if ($body === '' || mb_strlen($body) > TemplatePrompts::MAX_BODY || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $body)) {
            $errors[] = 'The prompt must be 1–' . TemplatePrompts::MAX_BODY . ' characters of plain text.';
        }
        if ($errors) {
            return $this->view('admin/template', [
                'slug' => $p['slug'], 'title' => TemplateCatalog::englishTitle($p['slug']), 'e' => $e, 'prompt' => ['body' => $body] + (TemplatePrompts::find($p['slug']) ?? ['version' => 0, 'origin' => '', 'notes' => '', 'updated_at' => '']),
                'orders' => 0, 'csrf' => Csrf::token(), 'flash' => '', 'errors' => $errors,
            ], 422);
        }
        $v = TemplatePrompts::save($p['slug'], $body, trim($r->post('origin')), trim($r->post('notes')));
        $_SESSION['tpl_flash'] = "Saved as version $v.";
        return Response::redirect('/admin/templates/' . $p['slug'], 303);
    }

    public function import(Request $r): Response
    {
        if ($guard = $this->guard($r)) {
            return $guard;
        }
        if (!Csrf::valid($r->post('_csrf'))) {
            return Response::html(View::render('admin/notfound', [], 'admin/layout'), 403);
        }
        $json = $r->post('package_json');
        $f = $_FILES['package'] ?? null;
        if ($json === '' && is_array($f) && ($f['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file((string) $f['tmp_name'])) {
            if (($f['size'] ?? 0) > TemplatePrompts::MAX_IMPORT_BYTES) {
                $_SESSION['tpl_flash'] = 'Import failed: the file is larger than 1 MB.';
                return Response::redirect('/admin/templates', 303);
            }
            $json = (string) file_get_contents($f['tmp_name']);   // content only; the client-supplied file name is never used
        }
        [$n, $errors] = TemplatePrompts::import($json);
        $_SESSION['tpl_flash'] = $errors ? 'Import rejected, nothing was changed: ' . implode(' ', array_slice($errors, 0, 5)) : "Imported $n prompts.";
        return Response::redirect('/admin/templates', 303);
    }

    private function flash(): string
    {
        $m = (string) ($_SESSION['tpl_flash'] ?? '');
        unset($_SESSION['tpl_flash']);
        return $m;
    }

    private function guard(Request $r): ?Response
    {
        Session::start($r);
        return Auth::check() ? null : Response::redirect('/admin/login');
    }

    private function view(string $tpl, array $data, int $status = 200): Response
    {
        return Response::html(View::render($tpl, $data, 'admin/layout'), $status);
    }
}
