<p><a href="/admin">← All orders</a></p>
<div class="admin__card">
  <h1 class="row__title admin__title">Order #<?= (int) $o['id'] ?></h1>
  <dl class="admin__dl">
<?php foreach (['name' => 'Name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone', 'project_type' => 'Project type', 'budget' => 'Budget', 'timeframe' => 'Timeframe', 'locale' => 'Language', 'created_at' => 'Created (UTC)', 'consent_at' => 'Privacy notice acknowledged (UTC)'] as $k => $label): ?>
    <dt><?= $label ?></dt><dd><?= e($o[$k]) ?: '–' ?></dd>
<?php endforeach ?>
    <dt>Description</dt><dd class="admin__pre"><?= e($o['description']) ?></dd>
    <dt>Telegram</dt><dd><span class="admin__status admin__status--<?= e($o['notification_status']) ?>"><?= e($o['notification_status']) ?></span>
      <?= $o['notification_error'] !== '' ? '<small>' . e($o['notification_error']) . '</small>' : '' ?></dd>
  </dl>
  <form method="post" action="/admin/orders/<?= (int) $o['id'] ?>/retry">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="btn btn--auto" type="submit"><?= $o['notification_status'] === 'sent' ? 'Send Telegram notification again' : 'Retry Telegram notification' ?></button>
  </form>
</div>
