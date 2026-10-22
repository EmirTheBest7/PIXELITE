<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Careers;
use Pixelite\Request;
use Pixelite\Response;

/** Vacancies: create, edit, publish/unpublish, delete (with a confirmation page). Administrators only; CSRF on every change. */
final class AdminCareersController extends AdminBase
{
    public function index(Request $r): Response
    {
        return $this->guard($r) ?? $this->view('admin/careers', ['rows' => Careers::all()]);
    }

    public function create(Request $r): Response
    {
        return $this->guard($r) ?? $this->view('admin/career', ['row' => null, 'titles' => [], 'descriptions' => [], 'errors' => []]);
    }

    public function store(Request $r): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        [$clean, $errors] = Careers::validate($this->post($r));
        if ($errors) {
            return $this->view('admin/career', ['row' => null] + $clean + ['errors' => $errors], 422);
        }
        $id = Careers::create($clean);
        return $this->redirect('/admin/careers/' . $id, 'Vacancy saved as a draft. Publish it when it is ready.');
    }

    public function edit(Request $r, array $p): Response
    {
        if ($g = $this->guard($r)) { return $g; }
        $row = Careers::find((int) $p['id']);
        return $row === null ? $this->notFound() : $this->view('admin/career', ['row' => $row, 'titles' => $row['titles'], 'descriptions' => $row['descriptions'], 'errors' => []]);
    }

    public function update(Request $r, array $p): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        $row = Careers::find((int) $p['id']);
        if ($row === null) { return $this->notFound(); }
        [$clean, $errors] = Careers::validate($this->post($r));
        if ($errors) {
            return $this->view('admin/career', ['row' => $row] + $clean + ['errors' => $errors], 422);
        }
        Careers::update((int) $row['id'], $clean);
        return $this->redirect('/admin/careers/' . $row['id'], 'Saved.');
    }

    public function publish(Request $r, array $p): Response
    {
        return $this->toggle($r, (int) $p['id'], true);
    }

    public function unpublish(Request $r, array $p): Response
    {
        return $this->toggle($r, (int) $p['id'], false);
    }

    public function confirmDelete(Request $r, array $p): Response
    {
        if ($g = $this->guard($r)) { return $g; }
        $row = Careers::find((int) $p['id']);
        return $row === null ? $this->notFound() : $this->view('admin/career-delete', ['row' => $row]);
    }

    public function delete(Request $r, array $p): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        if (Careers::find((int) $p['id']) === null) { return $this->notFound(); }
        Careers::delete((int) $p['id']);
        return $this->redirect('/admin/careers', 'Vacancy deleted.');
    }

    private function toggle(Request $r, int $id, bool $publish): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        if (Careers::find($id) === null) { return $this->notFound(); }
        Careers::setPublished($id, $publish);
        return $this->redirect('/admin/careers', $publish ? 'Published. It is now visible on the Careers page.' : 'Unpublished. It is no longer public.');
    }

    /** @return array<string,mixed> */
    private function post(Request $r): array
    {
        $out = ['titles' => [], 'descriptions' => []];
        foreach (\Pixelite\I18n::LOCALES as $loc) {
            $out['titles'][$loc] = $r->post('title_' . $loc);
            $out['descriptions'][$loc] = $r->post('description_' . $loc);
        }
        return $out;
    }
}
