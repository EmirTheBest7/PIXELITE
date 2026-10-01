<?php
// Minimal fake of the Telegram Bot API for tests: php -S 127.0.0.1:8991 tests/fake_telegram.php
header('Content-Type: application/json');
$body = json_decode(file_get_contents('php://input'), true) ?: [];
file_put_contents(sys_get_temp_dir() . '/fake_telegram_last.json', json_encode(['uri' => $_SERVER['REQUEST_URI'], 'body' => $body]));
if (str_contains($_SERVER['REQUEST_URI'], 'botBAD')) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'description' => 'Unauthorized']);
    return;
}
echo json_encode(['ok' => true, 'result' => ['message_id' => 1]]);
