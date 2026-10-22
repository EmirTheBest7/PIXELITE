<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Files of client projects. Layout: storage/projects/<uid>/site/<relative path>. <uid> is a random internal id (never a token, order number
 * or user input); storage/ is outside the web root. Nothing stored here is ever executed or included by PHP – the preview host only
 * streams whitelisted, static file types with a fixed Content-Type.
 */
final class ProjectStorage
{
    public const MAX_FILE_BYTES = 10 * 1024 * 1024;
    public const MAX_TOTAL_BYTES = 50 * 1024 * 1024;
    public const MAX_FILES = 1000;
    public const MAX_DEPTH = 12;

    /** Whitelist: extension => Content-Type. Anything else (php, phtml, phar, sh, cgi, htaccess, exe, …) is refused at upload and never served. */
    public const TYPES = [
        'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8', 'json' => 'application/json; charset=utf-8',
        'map' => 'application/json; charset=utf-8', 'webmanifest' => 'application/manifest+json; charset=utf-8', 'txt' => 'text/plain; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'ttf' => 'font/ttf', 'otf' => 'font/otf', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg',
        'wav' => 'audio/wav', 'pdf' => 'application/pdf',
    ];

    /** storage/projects by default; PROJECTS_DIR can point elsewhere (the tests use a temporary folder). Always outside the web root. */
    public static function baseDir(): string
    {
        $d = Env::get('PROJECTS_DIR');
        return $d !== '' ? rtrim($d, '/') : Paths::storage('projects');
    }

    /** @throws \InvalidArgumentException unless $uid is a 16-hex internal id */
    public static function dir(string $uid): string
    {
        if (!preg_match('/^[a-f0-9]{16}$/', $uid)) {
            throw new \InvalidArgumentException('bad project id');
        }
        return self::baseDir() . '/' . $uid;
    }

    public static function siteDir(string $uid): string
    {
        return self::dir($uid) . '/site';
    }

    /** Normalised relative path, or null when it is not acceptable (traversal, absolute, hidden, odd characters, unknown type, too deep …). */
    public static function cleanPath(string $p): ?string
    {
        $p = str_replace("\u{00A0}", ' ', $p);
        if ($p === '' || strlen($p) > 240 || !mb_check_encoding($p, 'UTF-8') || preg_match('/[\x00-\x1F\x7F\\\\]/', $p) || $p[0] === '/' || preg_match('/^[A-Za-z]:/', $p)) {
            return null;
        }
        $parts = explode('/', $p);
        if (count($parts) > self::MAX_DEPTH) {
            return null;
        }
        foreach ($parts as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..' || mb_strlen($seg) > 100 || !preg_match('/^[\p{L}\p{N}_][\p{L}\p{N} ._@()+~\-]*$/u', $seg) || str_ends_with($seg, '.') || str_ends_with($seg, ' ')) {
                return null;
            }
        }
        return self::mime($p) === null ? null : implode('/', $parts);
    }

    public static function mime(string $rel): ?string
    {
        $dot = strrpos($rel, '.');
        return $dot === false ? null : (self::TYPES[strtolower(substr($rel, $dot + 1))] ?? null);
    }

    /**
     * Write $files (relative path => bytes) into the project. All-or-nothing validation first; any error => nothing is written.
     * @param array<string,string> $files already cleaned paths
     * @return list<string> errors (empty on success)
     */
    public static function store(string $uid, array $files, bool $replace): array
    {
        $errors = [];
        if (!$files) {
            return ['No files were provided.'];
        }
        if (count($files) > self::MAX_FILES) {
            return ['Too many files (the limit is ' . self::MAX_FILES . ').'];
        }
        $lower = [];
        $total = 0;
        foreach ($files as $path => $data) {
            if (self::cleanPath((string) $path) !== (string) $path) {
                $errors[] = 'Refused path: ' . self::shown((string) $path);
                continue;
            }
            if (isset($lower[strtolower((string) $path)])) {
                $errors[] = 'Two files differ only by letter case: ' . self::shown((string) $path);
            }
            $lower[strtolower((string) $path)] = true;
            if (strlen($data) > self::MAX_FILE_BYTES) {
                $errors[] = 'File too large (limit ' . intdiv(self::MAX_FILE_BYTES, 1048576) . ' MB): ' . self::shown((string) $path);
            }
            $total += strlen($data);
        }
        $site = self::siteDir($uid);
        $existing = $replace ? [] : self::inventory($uid);
        foreach ($existing as $f) {
            if (!isset($files[$f['path']])) { $total += $f['size']; }
        }
        if ($total > self::MAX_TOTAL_BYTES) {
            $errors[] = 'The project would exceed ' . intdiv(self::MAX_TOTAL_BYTES, 1048576) . ' MB in total.';
        }
        if (!$replace) {
            foreach (array_keys($files) as $path) {
                $segs = explode('/', (string) $path);
                for ($i = 1; $i < count($segs); $i++) {
                    if (is_file($site . '/' . implode('/', array_slice($segs, 0, $i)))) { $errors[] = 'A file already exists where a folder is needed: ' . self::shown((string) $path); break; }
                }
                if (is_dir($site . '/' . $path)) { $errors[] = 'A folder already exists where a file is needed: ' . self::shown((string) $path); }
            }
        }
        if ($errors) {
            return array_slice(array_unique($errors), 0, 8);
        }

        $base = self::dir($uid);
        if (!is_dir($base) && !@mkdir($base, 0775, true) && !is_dir($base)) {
            return ['The project folder could not be created.'];
        }
        $target = $replace ? $base . '/.stage-' . bin2hex(random_bytes(6)) : $site;
        try {
            foreach ($files as $path => $data) {
                self::write($target, (string) $path, $data);
            }
            if ($replace) {
                $old = $base . '/.old-' . bin2hex(random_bytes(6));
                if (is_dir($site) && !@rename($site, $old)) {
                    throw new \RuntimeException('swap failed');
                }
                if (!@rename($target, $site)) {
                    if (is_dir($old)) { @rename($old, $site); }
                    throw new \RuntimeException('swap failed');
                }
                if (is_dir($old)) { self::removeTree($old); }
            }
        } catch (\Throwable $e) {
            if ($replace && is_dir($target)) { self::removeTree($target); }
            Logger::error('project upload failed', ['uid' => $uid, 'error' => $e->getMessage()]);
            return ['The files could not be stored.'];
        }
        return [];
    }

    /** @return list<array{path:string,size:int}> regular files only, hidden/temporary names skipped */
    public static function inventory(string $uid): array
    {
        $root = self::siteDir($uid);
        $out = [];
        if (!is_dir($root) || is_link($root)) {
            return $out;
        }
        $walk = function (string $dir, string $rel) use (&$walk, &$out): void {
            foreach (scandir($dir) ?: [] as $n) {
                if ($n === '.' || $n === '..' || $n[0] === '.') { continue; }
                $full = $dir . '/' . $n;
                if (is_link($full)) { continue; }
                $r = $rel === '' ? $n : $rel . '/' . $n;
                if (is_dir($full)) { $walk($full, $r); } elseif (is_file($full) && count($out) < self::MAX_FILES + 50) { $out[] = ['path' => $r, 'size' => (int) filesize($full)]; }
            }
        };
        $walk($root, '');
        usort($out, static fn($a, $b) => strcmp($a['path'], $b['path']));
        return $out;
    }

    /** Resolve a requested path to a real file inside the project's site directory, or null. Used by the preview host only. */
    public static function resolve(string $uid, string $rel): ?string
    {
        $clean = self::cleanPath($rel);
        if ($clean === null) {
            return null;
        }
        $root = realpath(self::siteDir($uid));
        $full = $root === false ? false : realpath($root . '/' . $clean);
        if ($root === false || $full === false || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full) || is_link($root . '/' . $clean)) {
            return null;
        }
        return $full;
    }

    public static function remove(string $uid): void
    {
        $dir = self::dir($uid);
        if (is_dir($dir)) {
            self::removeTree($dir);
        }
    }

    /** Pick the entry document: index.html at the root, else the first HTML file. */
    public static function guessEntry(string $uid): string
    {
        $paths = array_column(self::inventory($uid), 'path');
        foreach (['index.html', 'index.htm'] as $c) { if (in_array($c, $paths, true)) { return $c; } }
        foreach ($paths as $p) { if (preg_match('/\.html?$/i', $p)) { return $p; } }
        return 'index.html';
    }

    /**
     * Turn an upload ($_FILES-shaped) into cleaned [path => bytes]. ZIP files are read with ZipReader and never extracted to disk.
     * @param array<string,mixed> $upload e.g. $request->file('files') / ->file('zip')
     * @return array{0: array<string,string>, 1: list<string>} [files, errors]
     */
    public static function collect(?array $files, ?array $zip, bool $verifyUploaded = true): array
    {
        $out = [];
        $errors = [];
        $budget = self::MAX_TOTAL_BYTES;
        foreach (self::normalize($files) as $f) {
            if ($f['error'] === UPLOAD_ERR_NO_FILE) { continue; }
            if ($f['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Upload failed (' . self::uploadError($f['error']) . '): ' . self::shown($f['name']); continue; }
            if ($verifyUploaded && !is_uploaded_file($f['tmp_name'])) { $errors[] = 'Rejected an invalid upload.'; continue; }
            if ($f['size'] > self::MAX_FILE_BYTES) { $errors[] = 'File too large: ' . self::shown($f['name']); continue; }
            $rel = self::cleanPath($f['path']);
            if ($rel === null) { $errors[] = 'Refused file name or type: ' . self::shown($f['path']); continue; }
            $budget -= $f['size'];
            if ($budget < 0) { $errors[] = 'The upload is larger than ' . intdiv(self::MAX_TOTAL_BYTES, 1048576) . ' MB.'; break; }
            $out[$rel] = (string) file_get_contents($f['tmp_name']);
        }
        foreach (self::normalize($zip) as $z) {
            if ($z['error'] === UPLOAD_ERR_NO_FILE) { continue; }
            if ($z['error'] !== UPLOAD_ERR_OK) { $errors[] = 'ZIP upload failed (' . self::uploadError($z['error']) . ').'; continue; }
            if ($verifyUploaded && !is_uploaded_file($z['tmp_name'])) { $errors[] = 'Rejected an invalid upload.'; continue; }
            $zipOut = [];
            try {
                $entries = ZipReader::entries($z['tmp_name']);
                foreach ($entries as $e) {   // hostile names are refused BEFORE any prefix is stripped, so "../x" or "/etc/x" can never be normalised into something acceptable
                    $n = $e['name'];
                    if ($n === '' || $n[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $n) || str_contains($n, '\\') || preg_match('/^[A-Za-z]:/', $n) || preg_match('/[\x00-\x1F\x7F]/', $n)) {
                        throw new \RuntimeException('Refused file name in the archive: ' . self::shown($n));
                    }
                }
                $entries = array_values(array_filter($entries, static fn($e) => !$e['dir']));
                $entries = array_values(array_filter($entries, static fn($e) => !preg_match('#(^|/)(__MACOSX/|\.DS_Store$|Thumbs\.db$)#', $e['name'])));
                // a single wrapping folder ("mysite/index.html") is removed
                $tops = array_unique(array_map(static fn($e) => explode('/', $e['name'])[0], $entries));
                $strip = count($tops) === 1 && !in_array($entries[0]['name'] ?? '', $tops, true) ? $tops[0] . '/' : '';
                $sum = 0;
                foreach ($entries as $e) {
                    $sum += $e['usize'];
                    if ($sum > self::MAX_TOTAL_BYTES) { throw new \RuntimeException('The archive expands to more than ' . intdiv(self::MAX_TOTAL_BYTES, 1048576) . ' MB.'); }
                    $name = $strip !== '' && str_starts_with($e['name'], $strip) ? substr($e['name'], strlen($strip)) : $e['name'];
                    $rel = self::cleanPath($name);
                    if ($rel === null) { throw new \RuntimeException('Refused file name or type in the archive: ' . self::shown($e['name'])); }
                    $zipOut[$rel] = ZipReader::read($z['tmp_name'], $e, self::MAX_FILE_BYTES);
                }
                $out += $zipOut;   // an archive is accepted whole or not at all
            } catch (\RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
        return [$out, array_slice($errors, 0, 8)];
    }

    /** @return list<array{name:string,path:string,tmp_name:string,size:int,error:int}> */
    private static function normalize(?array $f): array
    {
        if ($f === null || !isset($f['name'])) { return []; }
        $one = !is_array($f['name']);
        $names = $one ? [$f['name']] : $f['name'];
        $out = [];
        foreach ($names as $i => $n) {
            $pick = static fn(string $k, mixed $d) => $one ? ($f[$k] ?? $d) : ($f[$k][$i] ?? $d);
            $full = $pick('full_path', null);
            $out[] = [
                'name' => (string) $n,
                'path' => is_string($full) && $full !== '' ? $full : (string) $n,   // full_path carries the folder for directory uploads (PHP ≥ 8.1)
                'tmp_name' => (string) $pick('tmp_name', ''),
                'size' => (int) $pick('size', 0),
                'error' => (int) $pick('error', UPLOAD_ERR_NO_FILE),
            ];
        }
        return array_slice($out, 0, self::MAX_FILES + 50);
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'larger than the server upload limit (upload_max_filesize ' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_PARTIAL => 'partial upload',
            default => 'error ' . $code,
        };
    }

    /** User-supplied names are shown only after stripping everything but plain characters (they are escaped again on output). */
    private static function shown(string $s): string
    {
        return mb_substr(preg_replace('/[^\p{L}\p{N} ._@()+~\/\-]/u', '?', $s) ?? '', 0, 80);
    }

    private static function write(string $root, string $rel, string $data): void
    {
        $full = $root . '/' . $rel;
        $dir = dirname($full);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('mkdir failed');
        }
        $realRoot = realpath($root) ?: $root;
        $realDir = realpath($dir);
        if ($realDir === false || ($realDir !== $realRoot && !str_starts_with($realDir, $realRoot . DIRECTORY_SEPARATOR)) || is_link($full)) {
            throw new \RuntimeException('path escaped the project folder');
        }
        $tmp = $dir . '/.up-' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $data) !== strlen($data)) {
            @unlink($tmp);
            throw new \RuntimeException('write failed');
        }
        @chmod($tmp, 0644);   // never executable
        if (!@rename($tmp, $full)) {
            @unlink($tmp);
            throw new \RuntimeException('rename failed');
        }
    }

    private static function removeTree(string $dir): void
    {
        if (is_link($dir)) { @unlink($dir); return; }
        foreach (scandir($dir) ?: [] as $n) {
            if ($n === '.' || $n === '..') { continue; }
            $p = $dir . '/' . $n;
            (is_dir($p) && !is_link($p)) ? self::removeTree($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
