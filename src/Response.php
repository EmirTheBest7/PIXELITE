<?php
declare(strict_types=1);

namespace Pixelite;

final class Response
{
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $body, string $type = 'text/plain', int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => $type . '; charset=utf-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self($status, '', ['Location' => $to]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        echo $this->body;
    }
}
