<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Editor\Builder\Anon\AnonWorkspaces;
use Pixelite\Request;
use Pixelite\Response;

/** Public entry point of the site builder: /{lang}/builder (landing + "start") – localized, no account. The builder itself is /builder/w (PublicBuilderController). */
final class BuilderPageController extends BaseController
{
    public function landing(Request $r, string $error = '', int $status = 200): Response
    {
        $available = AnonWorkspaces::available();
        $mine = $available ? AnonWorkspaces::open($r) : null;
        $resp = $this->page('builder', 'builder', [
            'available' => $available, 'hasWorkspace' => $mine !== null, 'startToken' => $available ? AnonWorkspaces::startToken($r) : '', 'error' => $error,
            'ttlHours' => intdiv(AnonWorkspaces::TTL, 3600), 'limits' => AnonWorkspaces::limits(),
        ], $status);
        $resp->headers['Cache-Control'] = 'no-store';   // carries the start token
        return $resp;
    }

    /**
     * "Start building" (explicit user action): creates a temporary workspace and sets its cookie. Needs the start token (a foreign site cannot read the landing page, so it
     * cannot forge this request) and refuses cross-site fetches; creation is rate-limited per IP and capped globally.
     */
    public function start(Request $r): Response
    {
        if (!AnonWorkspaces::available()) {
            return $this->landing($r, 'unavailable', 503);
        }
        if (!$this->sameOrigin($r) || !AnonWorkspaces::startTokenValid($r, $r->post('_start'))) {
            return $this->landing($r, 'token', 403);
        }
        $mine = AnonWorkspaces::open($r);
        if ($mine !== null && $r->post('action') !== 'new') {
            return Response::redirect('/builder/w', 303);   // already has one: continue it
        }
        $new = AnonWorkspaces::create($r);
        if (!$new['ok']) {
            $status = $new['reason'] === 'rate' ? 429 : 503;
            $resp = $this->landing($r, $new['reason'], $status);
            if ($new['reason'] === 'rate') {
                $resp->headers['Retry-After'] = '3600';
            }
            return $resp;
        }
        if ($mine !== null) {
            AnonWorkspaces::destroy($mine['key']);   // "start over": only the workspace this very browser owns
        }
        return new Response(303, '', ['Location' => '/builder/w', 'Set-Cookie' => AnonWorkspaces::cookieHeader($r, $new['key'], $new['secret']), 'Cache-Control' => 'no-store']);
    }

    /** Browser-sent provenance: a request that says it comes from another site (or another origin) is refused; clients that send neither header are judged by the token only. */
    private function sameOrigin(Request $r): bool
    {
        $site = $r->header('Sec-Fetch-Site');
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }
        $origin = $r->header('Origin');
        if ($origin === '') {
            return true;
        }
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        return $host !== '' && ($host === $r->host() || $host === strtolower((string) parse_url(\Pixelite\Env::get('APP_URL'), PHP_URL_HOST)));
    }
}
