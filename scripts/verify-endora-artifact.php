<?php
declare(strict_types=1);

/**
 * Pre-upload gate for the Endora artifact (build/endora). Exits non-zero on ANY problem, so a wrong
 * document-root layout, a missing asset or a leaked private file can never reach the FTP step.
 *
 * Usage: php scripts/verify-endora-artifact.php [artifactDir] [--http]
 *   --http  additionally boots the built artifact with PHP's built-in server and fetches real URLs.
 */

$args = array_values(array_filter(array_slice($argv, 1), fn($a) => $a[0] !== '-'));
$http = in_array('--http', $argv, true);
$root = dirname(__DIR__);
$dir = rtrim($args[0] ?? $root . '/build/endora', '/');
$pass = 0;
$failures = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $failures;
    if ($ok) {
        $pass++;
        echo "  ok   $name\n";
    } else {
        $failures[] = $name . ($detail !== '' ? " — $detail" : '');
        echo "  FAIL $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function files(string $dir): array
{
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile() || $f->isLink()) {
            $out[] = substr($f->getPathname(), strlen($dir) + 1);
        }
    }
    sort($out);
    return $out;
}

$web = "$dir/public_html";
$app = "$dir/app";

echo "layout\n";
check('artifact has public_html/ and app/', is_dir($web) && is_dir($app));
check('public_html/index.php exists (the front controller; replaces the host\'s placeholder page)', is_file("$web/index.php"));
check('public_html/.htaccess exists', is_file("$web/.htaccess"));
$idx = (string) @file_get_contents("$web/index.php");
check("front controller loads ../src/bootstrap.php (resolves to app/src/bootstrap.php in the FTP-root layout)", str_contains($idx, "__DIR__ . '/../src/bootstrap.php'") && is_file("$app/src/bootstrap.php"));
check('private directories are siblings of public_html, not inside it', array_diff(['src', 'templates', 'lang', 'config', 'storage'], array_map('basename', glob("$app/*", GLOB_ONLYDIR) ?: [])) === [] && !array_filter(['src', 'templates', 'lang', 'config', 'storage', 'tests', 'docs', 'bin'], fn($d) => is_dir("$web/$d")));
$top = array_map('basename', glob("$web/{,.}*", GLOB_BRACE) ?: []);
$top = array_values(array_diff($top, ['.', '..']));
sort($top);
check('public_html top level is exactly index.php, .htaccess, favicon.ico, assets', $top === ['.htaccess', 'assets', 'favicon.ico', 'index.php'], implode(',', $top));
check('app top level is exactly config, lang, src, storage, templates', array_map('basename', glob("$app/*") ?: []) === ['config', 'lang', 'src', 'storage', 'templates'] && !glob("$app/.[!.]*"));

echo "private / secret file leakage\n";
$all = array_merge(array_map(fn($f) => "public_html/$f", files($web)), array_map(fn($f) => "app/$f", files($app)));
$bad = [];
foreach ($all as $f) {
    $b = basename($f);
    if ($f === 'app/storage/.gitkeep') {
        continue;   // the only marker file we ship, so PHP has a writable runtime directory
    }
    if (preg_match('#(^|/)(\.env[^/]*|\.git[^/]*|\.github|tests|docs|bin|build|node_modules|vendor-private)(/|$)#', $f)
        || preg_match('/\.(sqlite3?|db|log|bak|sql|md|sh|yml|yaml|lock|dist|orig|swp|DS_Store)$/i', $b)
        || in_array($b, ['Dockerfile', 'docker-compose.yml', 'docker-entrypoint.sh', '.dockerignore', 'CLAUDE.md', 'composer.json', 'package.json'], true)) {
        $bad[] = $f;
    }
}
check('no env files, git data, tests, docs, bin scripts, Docker files, databases, logs or notes in the artifact', $bad === [], implode(', ', array_slice($bad, 0, 8)));
$secretHits = [];
$sec = [];
foreach (['TELEGRAM_BOT_TOKEN', 'ADMIN_PASSWORD_HASH'] as $k) {
    $v = trim((string) getenv($k));
    if (strlen($v) > 8) {
        $sec[] = $v;
    }
}
$envFile = $root . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^(TELEGRAM_BOT_TOKEN|ADMIN_PASSWORD_HASH)=(.*)$/', trim($line), $m)) {
            $v = trim($m[2], " '\"");
            if (strlen($v) > 8) {
                $sec[] = $v;
            }
        }
    }
}
foreach ($all as $f) {
    $c = (string) file_get_contents($dir . '/' . $f);
    foreach ($sec as $s) {
        if (str_contains($c, $s)) {
            $secretHits[] = $f;
        }
    }
    if (preg_match('/\b\d{8,10}:[A-Za-z0-9_-]{30,}\b/', $c) || preg_match('/\$2[ayb]\$\d\d\$[.\/A-Za-z0-9]{50,}/', $c)) {
        $secretHits[] = $f . ' (token/hash-shaped string)';
    }
}
check('no Telegram token, bcrypt hash or configured secret value appears in any shipped file', $secretHits === [], implode(', ', array_unique($secretHits)));
check('no symlinks', !array_filter($all, fn($f) => is_link("$dir/$f")));
check('every PHP file lints', (function () use ($all, $dir) {
    foreach ($all as $f) {
        if (str_ends_with($f, '.php')) {
            exec('php -l ' . escapeshellarg("$dir/$f") . ' 2>&1', $o, $rc);
            if ($rc !== 0) {
                return false;
            }
        }
    }
    return true;
})());

$ht = (string) @file_get_contents("$web/.htaccess");
check('.htaccess: no directory listing, front-controller rewrite, dotfiles and data extensions denied', str_contains($ht, 'Options -Indexes') && str_contains($ht, 'RewriteRule ^ index.php [L]') && str_contains($ht, 'Require all denied') && str_contains($ht, 'well-known') && str_contains($ht, 'sqlite'));
echo "assets\n";
// Every /assets/… path referenced from templates, src and the CSS must exist in public_html.
$refs = [];
foreach (['templates', 'src'] as $base) {
    foreach (files("$app/$base") as $f) {
        $c = (string) file_get_contents("$app/$base/$f");
        if (preg_match_all("#asset\(\s*'([^']+)'#", $c, $m)) {
            $refs = array_merge($refs, $m[1]);
        }
        if (preg_match_all("#['\"]/assets/([A-Za-z0-9_./-]+)['\"]#", $c, $m)) {
            $refs = array_merge($refs, $m[1]);
        }
        if (str_contains($c, "'/favicon.ico'") || str_contains($c, '"/favicon.ico"')) {
            $refs[] = '../favicon.ico';
        }
    }
}
$missing = [];
foreach (array_unique($refs) as $r) {
    $p = str_starts_with($r, '../') ? "$web/" . substr($r, 3) : "$web/assets/$r";
    if (!is_file($p)) {
        $missing[] = $r;
    }
}
check('every asset() / /assets/ reference in templates and code exists in public_html (' . count(array_unique($refs)) . ' refs)', $missing === [] && count($refs) > 5, implode(', ', $missing));
$css = (string) @file_get_contents("$web/assets/style.css");
$cssMissing = [];
if (preg_match_all('#url\(\s*[\'"]?(?!data:|https?:|\#)([^)\'"?\#]+)#', $css, $m)) {
    foreach (array_unique($m[1]) as $u) {
        if (!is_file("$web/assets/" . ltrim($u, '/'))) {
            $cssMissing[] = $u;
        }
    }
}
check('every url() in style.css resolves inside public_html/assets', $cssMissing === [], implode(', ', $cssMissing));
check('self-hosted fonts present (no third-party font/CDN reference in CSS or templates)', count(glob("$web/assets/fonts/*.ttf") ?: []) >= 3 && !preg_match('#https?://(fonts\.|cdn|ajax)#i', $css));

if ($http) {
    echo "built artifact served over HTTP (php -S, document root = public_html)\n";
    $tmp = sys_get_temp_dir() . '/pixelite-artifact-' . bin2hex(random_bytes(4));
    mkdir($tmp);
    // Isolated copy so the smoke test can create storage/ data without touching the artifact.
    $run = "$tmp/run";
    mkdir($run);
    exec('cp -R ' . escapeshellarg($web) . ' ' . escapeshellarg("$run/public_html"));
    foreach (['src', 'templates', 'lang', 'config', 'storage'] as $d) {
        exec('cp -R ' . escapeshellarg("$app/$d") . ' ' . escapeshellarg("$run/$d"));
    }
    file_put_contents("$run/.env", "APP_URL=https://pixelite.cz\nTRUST_PROXY=true\nLEADS_DATABASE_PATH=$tmp/test.sqlite\nTELEGRAM_BOT_TOKEN=\nTELEGRAM_CHAT_ID=\n");
    $port = 18000 + random_int(0, 999);
    $proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', "$run/public_html", "$run/public_html/index.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', "$tmp/server.log", 'w']], $pipes);
    for ($i = 0; $i < 40; $i++) {
        if (@fsockopen('127.0.0.1', $port)) {
            break;
        }
        usleep(100000);
    }
    $get = function (string $path) use ($port): array {
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0, 'header' => "X-Forwarded-Proto: https\r\n"]]);
        $body = @file_get_contents("http://127.0.0.1:$port$path", false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        return [$status, (string) $body, $http_response_header ?? []];
    };
    [$s, $b] = $get('/en/');
    check('GET /en/ -> 200 Pixelite page (not a placeholder)', $s === 200 && str_contains($b, 'Pixelite') && !str_contains($b, 'Hello World'), (string) $s);
    [$s, $b] = $get('/cs/');
    check('GET /cs/ -> 200, lang="cs"', $s === 200 && str_contains($b, '<html lang="cs">'));
    foreach (['pricing', 'process', 'order', 'contact', 'privacy', 'cookies', 'terms'] as $pg) {
        [$s] = $get("/en/$pg");
        check("GET /en/$pg -> 200", $s === 200, (string) $s);
    }
    [$s, $b] = $get('/sitemap.xml');
    check('GET /sitemap.xml -> 200 with absolute https://pixelite.cz URLs', $s === 200 && str_contains($b, 'https://pixelite.cz/en/pricing'));
    [$s, $b] = $get('/robots.txt');
    check('GET /robots.txt -> 200 with Sitemap line and /admin disallowed', $s === 200 && str_contains($b, 'Sitemap: https://pixelite.cz/sitemap.xml') && str_contains($b, 'Disallow: /admin'));
    preg_match_all('#(?:href|src)="(/(?:assets/[^"?]+|favicon\.ico))(?:\?[^"]*)?"#', (string) $get('/en/')[1], $m);
    $bad = [];
    foreach (array_unique($m[1]) as $a) {
        [$s] = $get($a);
        if ($s !== 200) {
            $bad[] = "$a=$s";
        }
    }
    check('every asset URL the homepage actually emits answers 200 (' . count(array_unique($m[1])) . ' URLs)', $bad === [] && count($m[1]) > 5, implode(', ', $bad));
    [, , $h] = $get('/assets/style.css');
    check('asset cache-busting works in the public_html layout (?v= present)', (bool) preg_match('#/assets/style\.css\?v=\d+#', (string) $get('/en/')[1]));
    foreach (['/src/bootstrap.php', '/templates/layout.php', '/lang/en.php', '/config/consent.php', '/storage/leads.sqlite', '/storage/.salt', '/.env', '/.env.example', '/CLAUDE.md', '/tests/run.php', '/bin/hash-password.php', '/Dockerfile', '/../src/bootstrap.php', '/assets/../../src/bootstrap.php', '/public/index.php', '/.ftp-deploy-sync-state.json'] as $p) {
        [$s, $b] = $get($p);
        check("GET $p is not served (got $s)", in_array($s, [403, 404], true) && !str_contains($b, '<?php') && !str_contains($b, 'namespace Pixelite'));
    }
    [$s, , $h] = $get('/en/');
    $hh = strtolower(implode("\n", $h));
    check('security headers present: CSP, nosniff, X-Frame-Options, HSTS (behind TRUST_PROXY https)', str_contains($hh, 'content-security-policy:') && str_contains($hh, 'x-content-type-options: nosniff') && str_contains($hh, 'x-frame-options:') && str_contains($hh, 'strict-transport-security:'));
    check('canonical + hreflang use the configured APP_URL', (bool) preg_match('#<link rel="canonical" href="https://pixelite\.cz/en/">#', (string) $get('/en/')[1]) && str_contains((string) $get('/en/')[1], 'hreflang="cs" href="https://pixelite.cz/cs/"'));
    [$s] = $get('/en/about');
    check('/en/about is not built (404)', $s === 404, (string) $s);
    proc_terminate($proc);
    proc_close($proc);
    exec('rm -rf ' . escapeshellarg($tmp));
}

echo "\n$pass passed, " . count($failures) . " failed\n";
if ($failures) {
    fwrite(STDERR, "VERIFY FAILED:\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}
