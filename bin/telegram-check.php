<?php
// Verifies the REAL Telegram credentials in .env by sending one clearly-labelled test message.
// Usage: docker compose exec app php bin/telegram-check.php
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../src/bootstrap.php';

$n = new Pixelite\TelegramNotifier();
if (!$n->isConfigured()) {
    fwrite(STDERR, "TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID are not set in .env\n");
    exit(2);
}
[$status, $error] = $n->send([
    'id' => 0, 'locale' => 'en', 'created_at' => gmdate('c'),
    'name' => 'Telegram check (not a real order)', 'company' => '', 'email' => 'check@example.invalid', 'phone' => '',
    'project_type' => 'other', 'budget' => 'unsure', 'timeframe' => 'flexible',
    'description' => 'If you can read this in your chat, the Pixelite bot is configured correctly.',
]);
echo $status === 'sent' ? "OK – message delivered\n" : "FAILED: $error\n";
exit($status === 'sent' ? 0 : 1);
