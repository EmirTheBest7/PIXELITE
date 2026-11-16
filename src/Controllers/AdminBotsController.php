<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\BotMonitor;
use Pixelite\Request;
use Pixelite\Response;

/** Read-only status of the self-deployed Telegram bots (docs/bots/README.md). Every load checks each bot's /health. */
final class AdminBotsController extends AdminBase
{
    public function index(Request $r): Response
    {
        return $this->guard($r) ?? $this->view('admin/bots', ['rows' => BotMonitor::check(), 'checkedAt' => gmdate('Y-m-d H:i:s')]);
    }
}
