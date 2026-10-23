<?php
declare(strict_types=1);

namespace Pixelite\Editor\Builder;

/**
 * EXPERIMENTAL site builder – turns one stored page into a complete document. The server owns the <head>: the browser never supplies one.
 * The stored body/CSS are sanitised AGAIN here (a tampered site.json can therefore not inject anything), and the response that carries the
 * document is sandboxed and script-free (see BuilderController::CSP_DOCUMENT).
 */
final class PageRenderer
{
    /** @param array{title:string,html:string,css:string} $page */
    public static function document(array $page, int|string $media, bool $edit): string
    {
        $san = new HtmlSanitizer($media);
        CssSanitizer::$notes = [];
        $body = $san->body($page['html'])['html'] ?? '';
        $css = CssSanitizer::stylesheet($page['css'], fn(string $u): bool => $san->imageUrlOk($u));
        $title = htmlspecialchars($page['title'] !== '' ? $page['title'] : 'Untitled page', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<meta name=\"robots\" content=\"noindex,nofollow\">\n"
            . "<title>$title</title>\n<link rel=\"stylesheet\" href=\"/assets/editor/vendor/bootstrap/bootstrap.min.css\">\n<link rel=\"stylesheet\" href=\"/assets/editor/builder-base.css\">\n"
            . '<style id="vvvebjs-styles">' . $css . "</style>\n"
            . ($edit ? "<link rel=\"stylesheet\" href=\"/assets/editor/builder-canvas.css\">\n" : '')
            . "</head>\n<body>\n" . $body . "\n</body>\n</html>\n";
    }
}
