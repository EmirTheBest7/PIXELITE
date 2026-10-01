<?php
declare(strict_types=1);

namespace Pixelite;

/** Sends order notifications through the official Telegram Bot API (sendMessage). Plain text only. */
final class TelegramNotifier
{
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    /** @var ?\Closure(string,array,int):array{0:int,1:string} [http status, body] — injectable for tests */
    private ?\Closure $transport;

    public function __construct(?\Closure $transport = null)
    {
        $this->transport = $transport;
    }

    public function isConfigured(): bool
    {
        return Env::get('TELEGRAM_BOT_TOKEN') !== '' && Env::get('TELEGRAM_CHAT_ID') !== '';
    }

    /** @return array{0:string,1:string} [status, error] — never throws, never includes the token */
    public function send(array $order): array
    {
        if (!$this->isConfigured()) {
            return [self::SKIPPED, 'Telegram not configured'];
        }
        $url = rtrim(Env::get('TELEGRAM_API_BASE', 'https://api.telegram.org'), '/')
            . '/bot' . Env::get('TELEGRAM_BOT_TOKEN') . '/sendMessage';
        $payload = [
            'chat_id' => Env::get('TELEGRAM_CHAT_ID'),
            'text' => $this->format($order),
            'disable_web_page_preview' => true,
        ];
        try {
            [$status, $body] = ($this->transport ?? self::curl(...))($url, $payload, Env::int('TELEGRAM_TIMEOUT', 10));
        } catch (\Throwable $ex) {
            return [self::FAILED, 'transport error: ' . $this->redact($ex->getMessage())];
        }
        $json = json_decode($body, true);
        if ($status === 200 && is_array($json) && ($json['ok'] ?? false) === true) {
            return [self::SENT, ''];
        }
        $desc = is_array($json) ? (string) ($json['description'] ?? '') : '';
        return [self::FAILED, "HTTP $status " . $this->redact($desc)];
    }

    public function format(array $o): string
    {
        $type = $o['project_type'] ?? '';
        $lines = [
            '🚀 NEW PIXELITE ORDER',
            '',
            'Name: ' . $o['name'],
            'Company: ' . (($o['company'] ?? '') ?: '-'),
            'Email: ' . $o['email'],
            'Phone: ' . (($o['phone'] ?? '') ?: '-'),
            '',
            'Project: ' . I18n::get("order.options.project_type.$type", [], 'en'),
            ...self::packageLines($o),
            'Budget: ' . I18n::get('order.options.budget.' . ($o['budget'] ?? ''), [], 'en'),
            'Timeline: ' . I18n::get('order.options.timeframe.' . ($o['timeframe'] ?? ''), [], 'en'),
            '',
            'Description:',
            (string) $o['description'],
            '',
            'Source: ' . preg_replace('#^https?://#', '', absolute_url('/' . ($o['locale'] ?? 'en') . '/order')),
            'Language: ' . strtoupper($o['locale'] ?? 'en'),
            'Created: ' . ($o['created_at'] ?? gmdate('c')),
            'Order #' . ($o['id'] ?? '?'),
        ];
        $text = implode("\n", $lines);
        // Telegram's hard limit is 4096 chars.
        return mb_strlen($text) > 4000 ? mb_substr($text, 0, 3990) . "\n[truncated]" : $text;
    }

    /** @return list<string> "Package: …" (+ the price context the customer saw, if recorded) */
    private static function packageLines(array $o): array
    {
        $p = PricingSnapshot::describe($o);
        if ($p === null) {
            return ['Package: -'];
        }
        if (!$p['recorded']) {
            return ["Package: {$p['id']} (price context not recorded)"];
        }
        return ["Package: {$p['id']} ({$p['name']})", "Price shown: {$p['price']} · {$p['vat']} (starting price, not the agreed price)"];
    }

    private function redact(string $s): string
    {
        $token = Env::get('TELEGRAM_BOT_TOKEN');
        return mb_substr($token !== '' ? str_replace($token, '***', $s) : $s, 0, 200);
    }

    /** @return array{0:int,1:string} */
    private static function curl(string $url, array $payload, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, (string) $body];
    }
}
