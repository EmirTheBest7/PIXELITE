<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Auth;
use Pixelite\Csrf;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\Session;
use Pixelite\View;

/** Shared guard for the staff area: every action starts with guard()/guardPost(); English only, never indexed, never cached. */
abstract class AdminBase
{
    protected function guard(Request $r): ?Response
    {
        Session::start($r);
        return Auth::check() ? null : Response::redirect('/admin/login');
    }

    /** Authenticated administrator AND a valid CSRF token – the gate for every state-changing request. */
    protected function guardPost(Request $r): ?Response
    {
        if ($g = $this->guard($r)) {
            return $g;
        }
        if ($r->method !== 'POST' || !Csrf::valid($r->post('_csrf'))) {
            return $this->view('admin/notfound', ['message' => 'Your session expired or the form was invalid. Go back, reload the page and try again.'], 403);
        }
        return null;
    }

    protected function view(string $tpl, array $data, int $status = 200): Response
    {
        return Response::html(View::render($tpl, $data + ['csrf' => Csrf::token(), 'flash' => $this->flash()], 'admin/layout'), $status);
    }

    protected function notFound(): Response
    {
        return $this->view('admin/notfound', [], 404);
    }

    protected function redirect(string $to, string $flash = ''): Response
    {
        if ($flash !== '') {
            $_SESSION['admin_flash'] = $flash;
        }
        return Response::redirect($to, 303);
    }

    private function flash(): string
    {
        $m = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);
        return $m;
    }
}
