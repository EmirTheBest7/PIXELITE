<?php
declare(strict_types=1);

namespace Pixelite\Editor;

use Pixelite\Csrf;
use Pixelite\CustomerAuth;
use Pixelite\I18n;
use Pixelite\Paths;
use Pixelite\Projects;
use Pixelite\RateLimiter;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\Session;

/**
 * EXPERIMENTAL visual editor – what every editor controller shares: who may touch which project (session customer + ownership, otherwise an
 * identical 404), the CSRF / X-Requested-With / rate-limit guard for mutations, and the response helpers. One implementation, so the Vantage
 * editor and the site builder cannot drift apart.
 */
abstract class EditorBase
{
    /** @param bool $verifyUploaded false only in tests, which cannot produce a real HTTP upload */
    public function __construct(protected bool $verifyUploaded = true) {}

    /**
     * The one place that decides who may touch which project.
     * @return array{project: array<string,mixed>, customer: array<string,mixed>}|Response
     */
    protected function context(Request $r, array $p, bool $redirectToLogin): array|Response
    {
        I18n::set(I18n::default());
        Session::start($r);
        $c = CustomerAuth::current();
        if ($c === null) {
            return $redirectToLogin ? Response::redirect(url('login')) : $this->json(['ok' => false, 'error' => 'Please sign in again.'], 401);
        }
        $project = Projects::find((int) ($p['id'] ?? 0));
        if ($project === null || (int) $project['customer_id'] !== (int) $c['id'] || $project['status'] === 'archived') {
            return $this->notFound();
        }
        return ['project' => $project, 'customer' => $c];
    }

    protected function mutationGuard(Request $r, string $bucket, int $limit, int $window): ?Response
    {
        if (!RateLimiter::attempt($bucket, $limit, $window)) {
            $resp = $this->json(['ok' => false, 'error' => 'Too many requests. Please wait a moment.'], 429);
            $resp->headers['Retry-After'] = (string) $window;
            return $resp;
        }
        if (!Csrf::valid($r->post('_csrf')) || $r->header('X-Requested-With') !== 'pixelite-editor') {
            return $this->json(['ok' => false, 'error' => 'Your session expired. Reload the editor.'], 403);
        }
        return null;
    }

    /** Own tiny renderer: the editor views do not depend on the public design switch (templates/legacy/…). */
    protected function render(string $view, array $data): string
    {
        if (!preg_match('#^(?:editor|demos)/[a-z0-9-]+\z#', $view)) {
            throw new \InvalidArgumentException('bad view');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require Paths::root("templates/$view.php");
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    protected function html(string $body, string $csp, string $frame): Response
    {
        $resp = Response::html($body);
        $resp->headers += ['Content-Security-Policy' => $csp, 'X-Frame-Options' => $frame, 'X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store'];
        return $resp;
    }

    protected function json(array $data, int $status = 200): Response
    {
        return new Response($status, (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), [
            'Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    protected function notFound(): Response
    {
        return new Response(404, 'Not found', ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
