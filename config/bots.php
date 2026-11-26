<?php
declare(strict_types=1);

/*
 * Telegram bots that deploy themselves next to Pixelite (contract: docs/bots/README.md). Public metadata only: the bot's
 * base URL contains its random path, so it lives in .env under the key named by 'env' and never in this public repo.
 * Pixelite holds no bot code; /admin/bots only calls <base URL>/health.
 */
return [
    'lash' => [
        'name' => 'Lash studio booking bot',
        'repo' => 'EmirTheBest7/TG-PHPBot',
        'env' => 'BOT_LASH_URL',
    ],
    'stay' => [
        'name' => 'Domovo housing bot',
        'repo' => 'EmirTheBest7/TG-StayBot',
        'env' => 'BOT_STAY_URL',
    ],
];
