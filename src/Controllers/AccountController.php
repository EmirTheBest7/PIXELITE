<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Csrf;
use Pixelite\CustomerAuth;
use Pixelite\Customers;
use Pixelite\I18n;
use Pixelite\Logger;
use Pixelite\Preview;
use Pixelite\Projects;
use Pixelite\RateLimiter;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\Session;

/** Customer sign-in, registration and the personal account page. Every account route answers only about the signed-in customer's own records. */
final class AccountController extends BaseController
{
    private const HONEYPOT = 'hp_site';
    private const MIN_FILL_SECONDS = 2;

    public function loginForm(Request $r): Response
    {
        Session::start($r);
        if (CustomerAuth::current() !== null) {
            return Response::redirect(url('account'));
        }
        $_SESSION['form_ts'] = time();
        return $this->login_page('', [], '', 200, $this->flash());
    }

    public function login(Request $r): Response
    {
        Session::start($r);
        $email = mb_substr(strtolower(trim($r->post('email'))), 0, Customers::MAX['email']);
        $ipBucket = 'cl-ip:' . RateLimiter::clientKey($r);
        $mailBucket = 'cl-em:' . hash('sha256', $email);
        // Two independent limits (per connection, per account) so neither one address nor one network can hammer the form. Every attempt consumes a slot.
        if (!RateLimiter::attempt($ipBucket, 10, 900) || !RateLimiter::attempt($mailBucket, 8, 900)) {
            $resp = $this->login_page($email, [], 'rate_limited', 429);
            $resp->headers['Retry-After'] = '900';
            return $resp;
        }
        if (!Csrf::valid($r->post('_csrf'))) {
            return $this->login_page($email, [], 'csrf', 403);
        }
        $c = CustomerAuth::attempt($email, $r->post('password'));
        if ($c === null) {
            Logger::info('customer login failed');   // no e-mail, no password in the log
            return $this->login_page($email, [], 'invalid', 401);
        }
        RateLimiter::clear($mailBucket);
        CustomerAuth::login($c);
        return Response::redirect($this->safeNext($r->query('next')), 303);
    }

    public function registerForm(Request $r): Response
    {
        Session::start($r);
        if (CustomerAuth::current() !== null) {
            return Response::redirect(url('account'));
        }
        $_SESSION['form_ts'] = time();
        return $this->register_page([], [], '');
    }

    public function register(Request $r): Response
    {
        Session::start($r);
        $old = [];
        foreach (['name', 'email', 'company', 'phone'] as $f) {
            $old[$f] = $r->post($f);
        }
        if (!RateLimiter::attempt('reg:' . RateLimiter::clientKey($r), 6, 3600)) {
            $resp = $this->register_page($old, [], 'rate_limited', 429);
            $resp->headers['Retry-After'] = '3600';
            return $resp;
        }
        if (!Csrf::valid($r->post('_csrf'))) {
            return $this->register_page($old, [], 'csrf', 403);
        }
        $ts = $_SESSION['form_ts'] ?? null;
        if ($r->post(self::HONEYPOT) !== '' || !is_int($ts) || time() - $ts < self::MIN_FILL_SECONDS) {
            Logger::info('registration rejected as spam');
            return $this->registered();   // pretend success, do nothing
        }
        [$clean, $errors] = Customers::validateRegistration($old + ['password' => $r->post('password'), 'password2' => $r->post('password2')], $r->post('consent') === '1');
        if ($errors) {
            return $this->register_page($old, $errors, '', 422);
        }
        try {
            if (Customers::create($clean, I18n::locale()) === 0) {
                password_hash($clean['password'], PASSWORD_DEFAULT);   // same work as a real registration: no timing difference
            }
        } catch (\Throwable $e) {
            Logger::error('registration failed', ['error' => get_class($e)]);
            return $this->register_page($old, [], 'server', 500);
        }
        // Identical outcome whether or not the address was already registered: the page never reveals which e-mail addresses have accounts.
        return $this->registered();
    }

    public function logout(Request $r): Response
    {
        Session::start($r);
        if (Csrf::valid($r->post('_csrf'))) {
            CustomerAuth::logout();
            $_SESSION['account_flash'] = 'logged_out';
        }
        return Response::redirect(url('login'), 303);
    }

    public function account(Request $r): Response
    {
        Session::start($r);
        $c = CustomerAuth::current();
        if ($c === null) {
            return Response::redirect(url('login'));
        }
        return $this->account_page($c, [], '', 200);
    }

    public function updateProfile(Request $r): Response
    {
        Session::start($r);
        $c = CustomerAuth::current();
        if ($c === null) {
            return Response::redirect(url('login'), 303);
        }
        if (!Csrf::valid($r->post('_csrf'))) {
            return $this->account_page($c, [], 'csrf', 403);
        }
        [$clean, $errors] = Customers::validateProfile(['name' => $r->post('name'), 'company' => $r->post('company'), 'phone' => $r->post('phone')]);
        if ($errors) {
            return $this->account_page($clean + $c, $errors, '', 422);
        }
        Customers::updateProfile((int) $c['id'], $clean);   // only the signed-in customer's own row, only these three fields
        $_SESSION['account_flash'] = 'saved';
        return Response::redirect(url('account'), 303);
    }

    private function registered(): Response
    {
        unset($_SESSION['form_ts'], $_SESSION['_csrf']);
        $_SESSION['account_flash'] = 'registered';
        return Response::redirect(url('login'), 303);
    }

    private function flash(): string
    {
        $f = (string) ($_SESSION['account_flash'] ?? '');
        unset($_SESSION['account_flash']);
        return in_array($f, ['registered', 'logged_out', 'saved'], true) ? $f : '';
    }

    /** Only a plain path to the account page of a supported language is accepted – no external URLs, no protocol-relative or encoded tricks. */
    private function safeNext(string $next): string
    {
        return preg_match('#^/(?:' . implode('|', I18n::LOCALES) . ')/account\z#D', $next) === 1 ? $next : url('account');
    }

    private function login_page(string $email, array $errors, string $formError, int $status = 200, string $flash = ''): Response
    {
        return $this->page('login', 'login', ['email' => $email, 'errors' => $errors, 'formError' => $formError, 'csrf' => Csrf::token(), 'flash' => $flash], $status, ['robots' => 'noindex,nofollow']);
    }

    private function register_page(array $old, array $errors, string $formError, int $status = 200): Response
    {
        return $this->page('register', 'register', ['old' => $old, 'errors' => $errors, 'formError' => $formError, 'csrf' => Csrf::token()], $status, ['robots' => 'noindex,nofollow']);
    }

    private function account_page(array $c, array $errors, string $formError, int $status): Response
    {
        $id = (int) $c['id'];
        $projects = array_map(static fn(array $p): array => ['id' => $p['id'], 'name' => $p['name'], 'status' => $p['status'], 'preview' => $p['access_enabled'] && $p['file_count'] > 0 ? Preview::url($p) : ''], Projects::forCustomer($id));
        return $this->page('account', 'account', [
            'c' => ['id' => $id, 'email' => $c['email'], 'name' => $c['name'], 'company' => $c['company'], 'phone' => $c['phone']],
            'orders' => Customers::orders($id), 'projects' => $projects, 'errors' => $errors, 'formError' => $formError, 'csrf' => Csrf::token(), 'flash' => $this->flash(),
        ], $status, ['robots' => 'noindex,nofollow']);
    }
}
