<?php
declare(strict_types=1);

namespace Pixelite\Editor\Builder\Anon;

use Pixelite\Editor\Builder\BuilderController;
use Pixelite\Editor\Builder\Workspace;
use Pixelite\I18n;
use Pixelite\RateLimiter;
use Pixelite\Request;
use Pixelite\Response;

/**
 * Public site builder: the same builder, working on the visitor's TEMPORARY workspace instead of a customer project. No account, no login, no PHP session.
 *
 * Trust model
 *  - The workspace is found ONLY through the httpOnly cookie (AnonWorkspaces::open). No URL, parameter or header names a workspace, project or customer, so an
 *    anonymous request can never address anything else: customer projects, other visitors' workspaces and the authenticated APIs are simply not reachable from here.
 *  - Mutations need the workspace-bound CSRF token (HMAC of the cookie secret, unreadable cross-site) AND X-Requested-With, and pass per-workspace and per-IP rate limits.
 *  - Quotas (pages, content, images, global storage) come from AnonWorkspaces::limits(); the same sanitisers, CSP and sandbox as for customers apply.
 */
final class PublicBuilderController extends BuilderController
{
    /** @var array{ws:Workspace,key:string,secret:string,csrf:string}|null */
    private ?array $active = null;

    /** kind => [per-workspace limit, per-IP limit, window seconds] */
    private const LIMITS = ['save' => [120, 400, 600], 'page' => [20, 40, 3600], 'media' => [30, 60, 3600], 'upload' => [12, 30, 3600]];

    protected function workspace(Request $r, array $p, bool $page): Workspace|Response
    {
        I18n::set(I18n::default());
        $a = AnonWorkspaces::available() ? AnonWorkspaces::open($r) : null;
        if ($a === null) {
            if ($page) {
                return Response::redirect(url('builder', $this->locale($r)), 302);   // the landing page: start a (new) workspace there
            }
            return $this->json(['ok' => false, 'error' => 'Your temporary workspace is not available (it may have expired). Start a new one.', 'expired' => true], 401);
        }
        $this->active = $a;
        return $a['ws'];
    }

    protected function guard(Request $r, Workspace $w, string $kind): ?Response
    {
        [$perWs, $perIp, $window] = self::LIMITS[$kind];
        if (!RateLimiter::attempt("bw-$kind:" . $w->owner, $perWs, $window) || !RateLimiter::attempt("bw-ip-$kind:" . RateLimiter::clientKey($r), $perIp, $window)) {
            $resp = $this->json(['ok' => false, 'error' => 'Too many requests. Please wait a moment.'], 429);
            $resp->headers['Retry-After'] = (string) $window;
            return $resp;
        }
        if ($this->active === null || !hash_equals($this->active['csrf'], $r->post('_csrf')) || $r->header('X-Requested-With') !== 'pixelite-editor') {
            return $this->json(['ok' => false, 'error' => 'Your session expired. Reload the builder.'], 403);
        }
        return null;
    }

    protected function shellData(Workspace $w, array $site): array
    {
        return [
            'project' => ['id' => 0, 'name' => 'Temporary workspace'], 'csrf' => (string) $this->active['csrf'], 'api' => $w->api, 'upload' => $w->api . '/media',
            'version' => $site['version'], 'imageUploads' => \Pixelite\Editor\Media::available(), 'sanitizer' => true, 'anonymous' => true, 'home' => url('builder', I18n::locale()),
            'ttlHours' => intdiv(AnonWorkspaces::TTL, 3600),
        ];
    }

    protected function storageFull(Workspace $w): ?Response
    {
        return AnonWorkspaces::globalBytes() >= AnonWorkspaces::maxBytes()
            ? $this->json(['ok' => false, 'error' => 'The builder is out of temporary storage right now. Please try again later.'], 503)
            : null;
    }

    public function shell(Request $r, array $p): Response
    {
        $w = $this->workspace($r, $p, true);
        if (!$w instanceof Workspace) {
            return $w;
        }
        I18n::set($this->locale($r));
        return parent::shell($r, $p);
    }

    /** Responses that belong to a live workspace refresh its cookie (24 h from the last use) and are never cached. */
    protected function json(array $data, int $status = 200): Response
    {
        return $this->stamp(parent::json($data, $status));
    }

    protected function html(string $body, string $csp, string $frame): Response
    {
        return $this->stamp(parent::html($body, $csp, $frame));
    }

    private function stamp(Response $resp): Response
    {
        if ($this->active !== null && ($req = Request::$current) !== null) {
            $resp->headers['Set-Cookie'] = AnonWorkspaces::cookieHeader($req, $this->active['key'], $this->active['secret']);
        }
        $resp->headers['Cache-Control'] = 'no-store';
        return $resp;
    }

    private function locale(Request $r): string
    {
        $pick = $_COOKIE['pixelite_lang'] ?? '';
        return is_string($pick) && in_array($pick, I18n::LOCALES, true) ? $pick : \Pixelite\bestLocale($r->header('Accept-Language'));
    }
}
