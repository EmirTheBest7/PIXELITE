<?php
declare(strict_types=1);

/**
 * Builds the Endora shared-hosting artifact into build/endora/ (git-ignored).
 *
 *   build/endora/public_html/   -> FTP: ./public_html/   (the ONLY web-served directory on Endora)
 *   build/endora/app/           -> FTP: ./               (private: src, templates, lang, config, storage)
 *
 * The repository's public/ directory becomes public_html/, so `public_html/index.php` keeps finding
 * `../src/bootstrap.php` exactly like in development. Nothing else from the repository is shipped:
 * no tests, docs, bin, Docker files, .git*, .github, .env* or runtime data.
 *
 * Usage: php scripts/build-endora.php [outDir]
 */

$root = dirname(__DIR__);
$out = rtrim($argv[1] ?? $root . '/build/endora', '/');

/** @return list<string> relative file paths below $dir (files only, sorted) */
function listFiles(string $dir): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $files[] = substr($f->getPathname(), strlen($dir) + 1);
        }
    }
    sort($files);
    return $files;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

function copyTree(string $from, string $to, ?callable $accept = null): int
{
    $n = 0;
    foreach (listFiles($from) as $rel) {
        if ($accept && !$accept($rel)) {
            continue;
        }
        $dest = $to . '/' . $rel;
        is_dir(dirname($dest)) || mkdir(dirname($dest), 0755, true);
        copy($from . '/' . $rel, $dest);
        $n++;
    }
    return $n;
}

if (!is_file($root . '/public/index.php') || !is_file($root . '/src/bootstrap.php')) {
    fwrite(STDERR, "build: run from a full checkout (public/index.php and src/bootstrap.php must exist)\n");
    exit(1);
}
if (str_contains($out, '..') || $out === '' || $out === '/' || realpath($out) === $root) {
    fwrite(STDERR, "build: refusing unsafe output directory\n");
    exit(1);
}

rrmdir($out);
mkdir($out . '/public_html', 0755, true);
mkdir($out . '/app', 0755, true);

// Public document root: only what the browser may fetch.
$pub = copyTree($root . '/public', $out . '/public_html', fn(string $rel) => !str_ends_with($rel, '.DS_Store'));

// Private application code. Explicit allow-list: a new top-level directory is never shipped by accident.
$app = 0;
foreach (['src', 'templates', 'lang', 'config'] as $dir) {
    $app += copyTree($root . '/' . $dir, $out . '/app/' . $dir, fn(string $rel) => !str_ends_with($rel, '.DS_Store'));
}
// storage/ must exist and be writable by PHP at runtime (SQLite, logs, rate-limit salt); ship an empty marker only.
mkdir($out . '/app/storage', 0755, true);
file_put_contents($out . '/app/storage/.gitkeep', '');

printf("build: %d public files, %d private files, storage marker -> %s\n", $pub, $app, $out);
