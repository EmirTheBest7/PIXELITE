<?php
declare(strict_types=1);

namespace Pixelite;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $post = [],
        private array $query = [],
        private array $server = [],
    ) {}

    public static function fromGlobals(): self
    {
        $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $path = '/' . trim(preg_replace('#/+#', '/', $path), '/');
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_POST, $_GET, $_SERVER);
    }

    public function post(string $key): string
    {
        $v = $this->post[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    public function query(string $key): string
    {
        $v = $this->query[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    public function header(string $name): string
    {
        return (string) ($this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '');
    }

    public function isHttps(): bool
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }
        $hops = array_map('trim', explode(',', strtolower($this->header('X-Forwarded-Proto'))));
        return Env::bool('TRUST_PROXY') && end($hops) === 'https';
    }

    /** X-Forwarded-For is only honoured (assumes exactly one trusted proxy in front) when TRUST_PROXY=true, otherwise it could be spoofed to dodge limits. */
    public function ip(): string
    {
        if (Env::bool('TRUST_PROXY')) {
            // Last entry = what our own (single, trusted) proxy appended; earlier entries are client-controlled.
            $hops = array_map('trim', explode(',', $this->header('X-Forwarded-For')));
            $xff = end($hops);
            if (filter_var($xff, FILTER_VALIDATE_IP)) {
                return $xff;
            }
        }
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
