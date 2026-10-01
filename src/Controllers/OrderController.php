<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Csrf;
use Pixelite\Env;
use Pixelite\I18n;
use Pixelite\Logger;
use Pixelite\OrderOptions;
use Pixelite\OrderService;
use Pixelite\OrderValidator;
use Pixelite\RateLimiter;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\Session;

final class OrderController extends BaseController
{
    private const MIN_FILL_SECONDS = 2;
    public const HONEYPOT = 'hp_site';

    public function __construct(private OrderService $service = new OrderService()) {}

    public function show(Request $r): Response
    {
        Session::start($r);
        $_SESSION['form_ts'] = time();
        $old = [];
        $type = $r->query('type');
        if (in_array($type, OrderOptions::PROJECT_TYPES, true)) {
            $old['project_type'] = $type;
        }
        return $this->form($old, [], '');
    }

    public function submit(Request $r): Response
    {
        Session::start($r);
        $bucket = 'order:' . RateLimiter::clientKey($r);
        $limit = Env::int('CONTACT_RATE_LIMIT', 5);
        $window = Env::int('CONTACT_RATE_WINDOW', 900);

        $old = [];
        foreach (['name', 'company', 'email', 'phone', 'project_type', 'budget', 'timeframe', 'description'] as $f) {
            $old[$f] = $r->post($f);
        }

        if (RateLimiter::blocked($bucket, $limit, $window)) {
            $resp = $this->form($old, [], 'rate_limited', 429);
            $resp->headers['Retry-After'] = (string) $window;
            return $resp;
        }
        if (!Csrf::valid($r->post('_csrf'))) {
            return $this->form($old, [], 'csrf', 403);
        }

        // Honeypot (hidden "website" field) or an implausibly fast submit => bot. Pretend success, do nothing.
        // Fail closed: a real visitor always loaded the form first (which sets form_ts).
        $ts = $_SESSION['form_ts'] ?? null;
        $tooFast = !is_int($ts) || time() - $ts < self::MIN_FILL_SECONDS;
        if ($r->post(self::HONEYPOT) !== '' || $tooFast) {
            if ($r->post(self::HONEYPOT) !== '') {
                RateLimiter::record($bucket); // a filled honeypot is a bot; a merely fast human is not penalised
            }
            Logger::info('order rejected as spam', ['honeypot' => $r->post(self::HONEYPOT) !== '', 'fast' => $tooFast]);
            return $this->sentRedirect();
        }

        [$clean, $errors] = OrderValidator::validate($old, $r->post('consent') === '1');
        if ($errors) {
            return $this->form($old, $errors, '', 422);
        }

        try {
            if (!RateLimiter::attempt($bucket, $limit, $window)) { // atomic, so parallel requests can't slip past
                $resp = $this->form($old, [], 'rate_limited', 429);
                $resp->headers['Retry-After'] = (string) $window;
                return $resp;
            }
            $id = $this->service->submit($clean, I18n::locale());
        } catch (\Throwable $ex) {
            Logger::error('order persistence failed', ['error' => get_class($ex) . ': ' . $ex->getMessage()]);
            return $this->form($old, [], 'server', 500);
        }
        Logger::info($id ? 'order accepted' : 'duplicate order ignored', ['order' => $id]);
        return $this->sentRedirect();
    }

    public function sent(Request $r): Response
    {
        Session::start($r);
        if (empty($_SESSION['order_sent'])) {
            return Response::redirect(url('order'));
        }
        unset($_SESSION['order_sent']);
        return $this->page('order_sent', 'order/sent', [], 200, ['robots' => 'noindex,nofollow']);
    }

    private function sentRedirect(): Response
    {
        $_SESSION['order_sent'] = true;
        unset($_SESSION['form_ts']);
        unset($_SESSION['_csrf']); // token is single-use per accepted order
        return Response::redirect(url('order/sent'), 303);
    }

    /** @param array<string,string> $old @param array<string,string> $errors */
    private function form(array $old, array $errors, string $formError, int $status = 200): Response
    {
        return $this->page('order', 'order', [
            'old' => $old,
            'errors' => $errors,
            'formError' => $formError,
            'csrf' => Csrf::token(),
        ], $status);
    }
}
