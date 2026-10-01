<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Auth;
use Pixelite\Csrf;
use Pixelite\OrderRepository;
use Pixelite\OrderService;
use Pixelite\RateLimiter;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\Session;
use Pixelite\View;

/** Staff-only area. English only, never indexed. */
final class AdminController
{
    public function __construct(private OrderRepository $repo = new OrderRepository()) {}

    public function loginForm(Request $r): Response
    {
        Session::start($r);
        if (Auth::check()) {
            return Response::redirect('/admin');
        }
        return $this->view('admin/login', ['csrf' => Csrf::token(), 'error' => '', 'enabled' => Auth::enabled(), 'email' => '']);
    }

    public function login(Request $r): Response
    {
        Session::start($r);
        $bucket = 'login:' . RateLimiter::clientKey($r);
        $email = $r->post('email');
        $error = '';
        $status = 200;
        if (!RateLimiter::attempt($bucket, 10, 900)) { // every attempt consumes a slot, atomically
            $error = 'Too many attempts. Try again later.';
            $status = 429;
        } elseif (!Csrf::valid($r->post('_csrf'))) {
            $error = 'Your session expired. Please try again.';
            $status = 403;
        } elseif (Auth::attempt($email, $r->post('password'))) {
            RateLimiter::clear($bucket);
            Auth::login();
            return Response::redirect('/admin', 303);
        } else {
            $error = 'Invalid email or password.';
            $status = 401;
        }
        return $this->view('admin/login', ['csrf' => Csrf::token(), 'error' => $error, 'enabled' => Auth::enabled(), 'email' => $email], $status);
    }

    public function logout(Request $r): Response
    {
        Session::start($r);
        if (Csrf::valid($r->post('_csrf'))) {
            Auth::logout();
        }
        return Response::redirect('/admin/login', 303);
    }

    public function index(Request $r): Response
    {
        if ($guard = $this->guard($r)) {
            return $guard;
        }
        $page = max(1, (int) $r->query('page'));
        $per = 50;
        $orders = $this->repo->latest($per + 1, ($page - 1) * $per);
        return $this->view('admin/orders', [
            'orders' => array_slice($orders, 0, $per), 'page' => $page, 'hasMore' => count($orders) > $per, 'csrf' => Csrf::token(),
        ]);
    }

    public function show(Request $r, array $p): Response
    {
        if ($guard = $this->guard($r)) {
            return $guard;
        }
        $order = $this->repo->find((int) $p['id']);
        if ($order === null) {
            return Response::html(View::render('admin/notfound', [], 'admin/layout'), 404);
        }
        return $this->view('admin/order', ['o' => $order, 'csrf' => Csrf::token()]);
    }

    public function retry(Request $r, array $p): Response
    {
        if ($guard = $this->guard($r)) {
            return $guard;
        }
        if (Csrf::valid($r->post('_csrf'))) {
            (new OrderService())->notify((int) $p['id']);
        }
        return Response::redirect('/admin/orders/' . (int) $p['id'], 303);
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
