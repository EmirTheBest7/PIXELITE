<?php
declare(strict_types=1);

// Front controller: every non-file request lands here (see public/.htaccess).
require __DIR__ . '/../src/bootstrap.php';

ini_set('display_errors', '0');

// Serve static files when running under `php -S` (Apache handles this itself).
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

Pixelite\handle(Pixelite\Request::fromGlobals())->send();
