<?php
declare(strict_types=1);

namespace Pixelite\Editor;

use Pixelite\Env;

/** EXPERIMENTAL visual editor – feature switch. Off unless EDITOR_ENABLED=true in .env: while off, no editor route exists at all (404) and no link is shown. */
final class Editor
{
    public static function enabled(): bool
    {
        return Env::bool('EDITOR_ENABLED');
    }
}
