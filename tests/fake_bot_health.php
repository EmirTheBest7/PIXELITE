<?php
// Minimal fake of a bot's /health for tests: php -S 127.0.0.1:8993 tests/fake_bot_health.php
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
match ($path) {
    '/secret-path/health' => print(json_encode(['ok' => true, 'bot' => 'lash', 'version' => 'abcdef0123456789abcdef0123456789abcdef01', 'deployed_at' => '2026-11-16T12:00:00Z', 'db' => true, 'token' => 'NEVERSHOWN'])),
    '/degraded/health' => (http_response_code(503) && print(json_encode(['ok' => false, 'version' => '<script>', 'db' => false]))),
    '/html/health' => (header('Content-Type: text/html') || true) && print('<html>Endora placeholder</html>'),
    '/big/health' => print(json_encode(['ok' => true, 'pad' => str_repeat('x', 20000)])),
    '/redirect/health' => (http_response_code(302) && header('Location: /secret-path/health')),
    default => http_response_code(404),
};
