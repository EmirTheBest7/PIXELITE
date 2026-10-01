<?php
declare(strict_types=1);

namespace Pixelite;

final class Router
{
    /** @var list<array{string,string,callable}> */
    private array $routes = [];

    /** Pattern tokens: {lang} (en|cs) and {id} (digits). */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = strtr(preg_quote($pattern, '#'), [
            '\{lang\}' => '(?<lang>' . implode('|', I18n::LOCALES) . ')',
            '\{id\}' => '(?<id>\d+)',
        ]);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    public function dispatch(Request $r): ?Response
    {
        $path = $r->path === '/' ? '/' : rtrim($r->path, '/');
        $pathMatched = false;
        foreach ($this->routes as [$method, $regex, $handler]) {
            if (!preg_match($regex, $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($method !== $r->method && !($method === 'GET' && $r->method === 'HEAD')) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            if (isset($params['lang'])) {
                I18n::set($params['lang']);
            }
            return $handler($r, $params);
        }
        return $pathMatched ? new Response(405, 'Method Not Allowed', ['Allow' => 'GET, POST']) : null;
    }
}
