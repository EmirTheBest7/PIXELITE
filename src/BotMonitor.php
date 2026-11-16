<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Health of the self-deployed Telegram bots (config/bots.php, docs/bots/README.md). Server-side GET of <base>/health:
 * no redirects, short timeouts, capped body, only the documented fields are kept. The base URL (with the bot's random
 * path) comes from .env, else from storage/bot-urls.ini (written by .github/workflows/provision-bots.yml), and is never
 * shown in full; errors are stripped of it.
 */
final class BotMonitor
{
    private const MAX_BODY = 8192;

    /** @return list<array{slug:string,name:string,repo:string,env:string,host:string,state:string,version:string,deployed_at:string,db:?bool,detail:string,ms:int}> */
    public static function check(?array $bots = null, ?string $urlsFile = null): array
    {
        $bots ??= require dirname(__DIR__) . '/config/bots.php';
        $urlsFile ??= dirname(__DIR__) . '/storage/bot-urls.ini';
        $urls = is_file($urlsFile) ? (parse_ini_file($urlsFile, false, INI_SCANNER_RAW) ?: []) : [];
        $out = [];
        foreach ($bots as $slug => $b) {
            $row = ['slug' => (string) $slug, 'name' => (string) $b['name'], 'repo' => (string) ($b['repo'] ?? ''), 'env' => (string) $b['env'],
                'host' => '', 'state' => 'not_configured', 'version' => '', 'deployed_at' => '', 'db' => null, 'detail' => '', 'ms' => 0];
            $base = rtrim(Env::get($row['env']) ?: (string) ($urls[$row['env']] ?? ''), '/');
            $u = $base === '' ? false : parse_url($base);
            if (!$u || !in_array(strtolower($u['scheme'] ?? ''), ['http', 'https'], true) || empty($u['host'])) {
                $row['detail'] = $base === '' ? 'Set ' . $row['env'] . ' in .env.' : $row['env'] . ' is not an http(s) URL.';
                $out[] = $row;
                continue;
            }
            $row['host'] = strtolower($u['host']);
            $out[] = self::probe($row, $base);
        }
        return $out;
    }

    private static function probe(array $row, string $base): array
    {
        $body = '';
        $ch = curl_init($base . '/health');
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                $body .= $chunk;
                return strlen($body) > self::MAX_BODY ? 0 : strlen($chunk);
            },
        ]);
        $t = microtime(true);
        $ok = curl_exec($ch);
        $row['ms'] = (int) round((microtime(true) - $t) * 1000);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $row['state'] = 'down';
        if ($ok === false) {
            $row['detail'] = strlen($body) > self::MAX_BODY ? 'Response larger than ' . self::MAX_BODY . ' bytes.' : mb_substr(str_replace($base, '[bot URL]', $err), 0, 160);
            return $row;
        }
        $j = json_decode($body, true);
        if (!is_array($j) || !array_key_exists('ok', $j)) {
            $row['detail'] = "HTTP $status, not the /health JSON.";
            return $row;
        }
        $v = (string) ($j['version'] ?? '');
        $row['version'] = preg_match('/^[0-9a-f]{7,40}$/', $v) ? $v : '';
        $row['deployed_at'] = mb_substr(preg_replace('/[^0-9A-Za-z:+\-. ]/', '', (string) ($j['deployed_at'] ?? '')), 0, 40);
        $row['db'] = isset($j['db']) ? (bool) $j['db'] : null;
        $row['state'] = $status === 200 && $j['ok'] === true ? 'up' : 'degraded';
        $row['detail'] = $row['state'] === 'up' ? '' : "HTTP $status, ok: " . json_encode($j['ok']) . '.';
        return $row;
    }
}
