<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Preview;
use Pixelite\ProjectStorage;
use Pixelite\Projects;
use Pixelite\Request;
use Pixelite\Response;

/** Client projects + preview links. Administrators only. The preview itself is served from a separate origin (see Preview) – never from this admin origin. */
final class AdminProjectsController extends AdminBase
{
    public function index(Request $r): Response
    {
        return $this->guard($r) ?? $this->view('admin/projects', ['rows' => Projects::all(), 'previewOn' => Preview::enabled()]);
    }

    public function create(Request $r): Response
    {
        return $this->guard($r) ?? $this->view('admin/project-new', ['old' => ['name' => '', 'notes' => '', 'order_id' => '', 'customer_email' => ''], 'errors' => []]);
    }

    public function store(Request $r): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        $old = ['name' => $r->post('name'), 'notes' => $r->post('notes'), 'order_id' => $r->post('order_id'), 'customer_email' => $r->post('customer_email'), 'status' => 'draft'];
        [$clean, $errors] = Projects::validate($old);
        if ($errors) {
            return $this->view('admin/project-new', ['old' => $old, 'errors' => $errors], 422);
        }
        $p = Projects::create($clean);
        return $this->redirect('/admin/projects/' . $p['id'], 'Project created. Upload the website files, then switch the preview link on.');
    }

    public function show(Request $r, array $p): Response
    {
        if ($g = $this->guard($r)) { return $g; }
        $proj = Projects::find((int) $p['id']);
        return $proj === null ? $this->notFound() : $this->detail($proj, [], 200);
    }

    public function update(Request $r, array $p): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        $proj = Projects::find((int) $p['id']);
        if ($proj === null) { return $this->notFound(); }
        [$clean, $errors] = Projects::validate(['name' => $r->post('name'), 'notes' => $r->post('notes'), 'status' => $r->post('status'), 'order_id' => $r->post('order_id'), 'customer_email' => $r->post('customer_email')]);
        if ($errors) {
            return $this->detail($proj, $errors, 422);
        }
        Projects::update((int) $proj['id'], $clean);
        return $this->redirect('/admin/projects/' . $proj['id'], 'Saved.');
    }

    public function upload(Request $r, array $p): Response
    {
        // A body larger than post_max_size arrives with empty $_POST/$_FILES: say so instead of a confusing "session expired".
        if ($r->method === 'POST' && $r->post('_csrf') === '' && (int) $r->header('Content-Length') > 0 && $this->guard($r) === null) {
            return $this->redirect('/admin/projects/' . (int) $p['id'], 'The upload was larger than the server allows (post_max_size ' . ini_get('post_max_size') . ').');
        }
        if ($g = $this->guardPost($r)) { return $g; }
        $proj = Projects::find((int) $p['id']);
        if ($proj === null) { return $this->notFound(); }
        [$files, $errors] = ProjectStorage::collect($r->file('files'), $r->file('zip'));
        if (!$errors) {
            $errors = ProjectStorage::store($proj['uid'], $files, $r->post('mode') !== 'add');
        }
        if ($errors) {
            return $this->detail($proj, $errors, 422);
        }
        Projects::refreshStats($proj);
        return $this->redirect('/admin/projects/' . $proj['id'], count($files) . ' file(s) stored.');
    }

    public function access(Request $r, array $p): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        $proj = Projects::find((int) $p['id']);
        if ($proj === null) { return $this->notFound(); }
        $on = $r->post('access') === 'on';
        Projects::setAccess((int) $proj['id'], $on);
        return $this->redirect('/admin/projects/' . $proj['id'], $on ? 'Preview link switched on. Anyone who has the link can open the project.' : 'Preview link switched off: the link no longer works.');
    }

    public function regenerate(Request $r, array $p): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        $proj = Projects::find((int) $p['id']);
        if ($proj === null) { return $this->notFound(); }
        Projects::regenerateToken((int) $proj['id']);
        return $this->redirect('/admin/projects/' . $proj['id'], 'New preview link generated. The previous link stopped working.');
    }

    public function confirmDelete(Request $r, array $p): Response
    {
        if ($g = $this->guard($r)) { return $g; }
        $proj = Projects::find((int) $p['id']);
        return $proj === null ? $this->notFound() : $this->view('admin/project-delete', ['p' => $proj]);
    }

    public function delete(Request $r, array $p): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        $proj = Projects::find((int) $p['id']);
        if ($proj === null) { return $this->notFound(); }
        Projects::delete($proj);
        return $this->redirect('/admin/projects', 'Project and all its files deleted.');
    }

    /** @param list<string> $errors */
    private function detail(array $proj, array $errors, int $status): Response
    {
        return $this->view('admin/project', [
            'p' => $proj, 'files' => ProjectStorage::inventory($proj['uid']), 'previewOn' => Preview::enabled(), 'previewUrl' => Preview::url($proj),
            'errors' => $errors, 'statuses' => Projects::STATUSES,
            'limits' => ['file' => intdiv(ProjectStorage::MAX_FILE_BYTES, 1048576), 'total' => intdiv(ProjectStorage::MAX_TOTAL_BYTES, 1048576), 'upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size'), 'count' => ini_get('max_file_uploads')],
        ], $status);
    }
}
