<?php /** @var list<array> $rows @var string $checkedAt @var string $flash */ $badge = ['up' => 'sent', 'degraded' => 'failed', 'down' => 'failed', 'not_configured' => 'skipped']; $label = ['up' => 'up', 'degraded' => 'degraded', 'down' => 'down', 'not_configured' => 'not configured']; ?>
<div class="row"><h1 class="row__title">Telegram bots</h1></div>
<p>Bots live in their own repositories and deploy themselves; Pixelite only reads each bot's <code>/health</code> (see docs/bots/README.md). Checked <?= e($checkedAt) ?> UTC · <a href="/admin/bots">Check again</a></p>
<div class="admin__card row--margin">
<?php if (!$rows): ?>
  <p class="admin__empty">No bots registered in config/bots.php.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table admin__table">
      <thead><tr><th>Bot</th><th>Status</th><th>Live version</th><th>Deployed</th><th>Database</th><th>Host</th><th>Response</th></tr></thead>
      <tbody>
<?php foreach ($rows as $b): ?>
        <tr>
          <td><?= e($b['name']) ?><br><small><?= e($b['slug']) ?><?= $b['repo'] !== '' ? ' · ' . e($b['repo']) : '' ?></small></td>
          <td><span class="admin__status admin__status--<?= $badge[$b['state']] ?>"><?= e($label[$b['state']]) ?></span><?= $b['detail'] !== '' ? '<br><small>' . e($b['detail']) . '</small>' : '' ?></td>
          <td><?php if ($b['version'] !== ''): ?><?php if ($b['repo'] !== ''): ?><a href="https://github.com/<?= e($b['repo']) ?>/commit/<?= e($b['version']) ?>" rel="noopener noreferrer"><code><?= e(substr($b['version'], 0, 12)) ?></code></a><?php else: ?><code><?= e(substr($b['version'], 0, 12)) ?></code><?php endif ?><?php else: ?>–<?php endif ?></td>
          <td><?= $b['deployed_at'] !== '' ? e($b['deployed_at']) : '–' ?></td>
          <td><?= $b['db'] === null ? '–' : ($b['db'] ? 'ok' : 'error') ?></td>
          <td><?= $b['host'] !== '' ? e($b['host']) . '/…' : '<code>' . e($b['env']) . '</code>' ?></td>
          <td><?= $b['host'] !== '' ? (int) $b['ms'] . ' ms' : '–' ?></td>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
</div>
