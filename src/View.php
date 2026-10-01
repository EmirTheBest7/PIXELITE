<?php
declare(strict_types=1);

namespace Pixelite;

final class View
{
    /** Render templates/$template.php inside the given layout (null = bare). */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, $data + ['content' => $content]);
    }

    private static function capture(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require Paths::root("templates/$template.php");
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
