<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\I18n;
use Pixelite\Response;

final class SeoController
{
    /** Pages that belong in the sitemap. Portfolio and order/sent are intentionally excluded. */
    private const INDEXABLE = ['', 'order', 'contact', 'privacy'];

    public function robots(): Response
    {
        return Response::text("User-agent: *\nDisallow: /admin\n\nSitemap: " . absolute_url('/sitemap.xml') . "\n");
    }

    public function sitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach (self::INDEXABLE as $path) {
            foreach (I18n::LOCALES as $loc) {
                $xml .= '  <url><loc>' . e(absolute_url(url($path, $loc))) . '</loc>';
                foreach (I18n::LOCALES as $alt) {
                    $xml .= '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . e(absolute_url(url($path, $alt))) . '"/>';
                }
                $xml .= "</url>\n";
            }
        }
        return Response::text($xml . '</urlset>' . "\n", 'application/xml');
    }
}
