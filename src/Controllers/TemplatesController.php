<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\I18n;
use Pixelite\Paths;
use Pixelite\Request;
use Pixelite\Response;
use Pixelite\TemplateCatalog;

/** Public website-design gallery. Never reads or renders prompt text. */
final class TemplatesController extends BaseController
{
    public function index(Request $r): Response
    {
        $cat = $r->query('category');
        $style = $r->query('style');
        $filters = [
            'q' => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $r->query('q')) ?? ''), 0, 100),
            'category' => in_array($cat, TemplateCatalog::categories(), true) ? $cat : '',
            'style' => in_array($style, TemplateCatalog::styles(), true) ? $style : '',
            'sort' => in_array($r->query('sort'), TemplateCatalog::SORTS, true) ? $r->query('sort') : 'featured',
            'page' => (int) $r->query('page'),
        ];
        $res = TemplateCatalog::search($filters, I18n::locale());
        $filters['page'] = $res['page'];
        return $this->page('templates', 'templates', ['filters' => $filters, 'result' => $res], 200,
            // filtered / sorted / paged views are alternative views of one page: keep them out of the index, canonical stays /templates
            ['robots' => $res['filtered'] ? 'noindex,follow' : 'index,follow', 'crumbs' => [[(string) t('nav.templates'), 'templates']]]);
    }

    /**
     * The design itself, running: a self-contained HTML document from templates/demos/ whose CSS/JS/fonts/images are
     * same-origin assets. It gets its own, still strict CSP (no inline script or style blocks, no third-party hosts; only
     * inline style *attributes* are allowed because the templates use them for animation delays) and may only be framed
     * by Pixelite itself. Never indexed.
     */
    public function demo(Request $r, array $p): Response
    {
        $entry = TemplateCatalog::find($p['slug']);
        $name = (string) ($entry['demo'] ?? '');
        $file = $name !== '' && preg_match('/^[a-z0-9-]+$/', $name) ? Paths::root("templates/demos/$name.html") : '';
        if ($file === '' || !is_file($file)) {
            return \Pixelite\notFound($r);
        }
        $resp = Response::html((string) file_get_contents($file));
        $resp->headers += [
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' data:; "
                . "font-src 'self'; connect-src 'self'; form-action 'none'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
        return $resp;
    }

    public function show(Request $r, array $p): Response
    {
        $entry = TemplateCatalog::find($p['slug']);
        if ($entry === null) {
            return \Pixelite\notFound($r);
        }
        $e = TemplateCatalog::localized($entry);
        $related = array_values(array_filter(
            TemplateCatalog::search(['category' => $e['category']])['items'],
            static fn(array $x): bool => $x['slug'] !== $e['slug']
        ));
        return $this->page('template', 'templates/' . $e['slug'], ['t' => $e, 'related' => array_slice($related, 0, 3)], 200, [
            'title' => (string) t('templates.detail_title', ['name' => $e['title']]),
            'description' => $e['desc'],
            'image' => '/assets/' . $e['media']['dir'] . '/' . $e['media']['poster']['1280'],
            'crumbs' => [[(string) t('nav.templates'), 'templates'], [$e['title'], 'templates/' . $e['slug']]],
        ]);
    }
}
