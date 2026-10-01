<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\I18n;
use Pixelite\Response;
use Pixelite\View;

abstract class BaseController
{
    /**
     * @param string $page   template under templates/pages and key under meta.* in lang files
     * @param string $path   locale-less path, used for canonical + hreflang ('' = home)
     * @param array  $opts   robots => 'noindex,follow' to hide a page from search engines
     */
    protected function page(string $page, string $path, array $data = [], int $status = 200, array $opts = []): Response
    {
        $meta = [
            'page' => $page,
            'path' => $path,
            'title' => (string) t("meta.$page.title"),
            'description' => (string) t("meta.$page.description"),
            'robots' => $opts['robots'] ?? 'index,follow',
        ];
        $html = View::render("pages/$page", $data + ['meta' => $meta, 'locale' => I18n::locale()], null);
        return Response::html(View::render('layout', $data + ['meta' => $meta, 'locale' => I18n::locale(), 'content' => $html]), $status);
    }
}
