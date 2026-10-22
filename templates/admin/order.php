<p><a href="/admin">← All orders</a></p>
<?php if (!empty($flash)): ?><div class="form__alert form__alert--ok" role="status"><?= e($flash) ?></div><?php endif ?>
<div class="admin__card">
  <h1 class="admin__title">Order #<?= (int) $o['id'] ?></h1>
  <dl class="admin__dl">
<?php foreach (['name' => 'Name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone', 'project_type' => 'Project type', 'package' => 'Package', 'budget' => 'Budget', 'timeframe' => 'Timeframe', 'locale' => 'Language', 'created_at' => 'Created (UTC)', 'consent_at' => 'Privacy notice acknowledged (UTC)'] as $k => $label): ?>
<?php if ($k === 'package'): $pk = \Pixelite\PricingSnapshot::describe($o); ?>
    <dt>Package</dt><dd><?= $pk === null ? '–' : '<code>' . e($pk['id']) . '</code>' ?></dd>
<?php if ($pk !== null && $pk['recorded']): ?>
    <dt>Package name shown</dt><dd><?= e($pk['name']) ?></dd>
    <dt>Starting price shown</dt><dd><?= e($pk['price']) ?> <small>(a starting price, not the agreed price)</small></dd>
    <dt>VAT display at the time</dt><dd><?= e($pk['vat'] ?: '–') ?></dd>
<?php elseif ($pk !== null): ?>
    <dt>Package price shown</dt><dd>– <small>(not recorded: order placed before pricing snapshots existed)</small></dd>
<?php endif ?>
<?php elseif ($k === 'budget' && ($o['template'] ?? '') !== ''): ?>
    <dt>Chosen design</dt><dd><a href="/admin/templates/<?= e($o['template']) ?>"><?= e($o['template_name'] ?: $o['template']) ?></a> <small>(<code><?= e($o['template']) ?></code>)</small></dd>
    <dt><?= $label ?></dt><dd><?= e($o[$k]) ?: '–' ?></dd>
<?php else: ?>
    <dt><?= $label ?></dt><dd><?= e($o[$k]) ?: '–' ?></dd>
<?php endif ?>
<?php endforeach ?>
    <dt>Description</dt><dd class="admin__pre"><?= e($o['description']) ?></dd>
    <dt>Telegram</dt><dd><span class="admin__status admin__status--<?= e($o['notification_status']) ?>"><?= e($o['notification_status']) ?></span>
      <?= $o['notification_error'] !== '' ? '<small>' . e($o['notification_error']) . '</small>' : '' ?></dd>
  </dl>
  <h2 class="admin__h2">Customer account</h2>
<?php $linked = !empty($o['customer_id']) ? \Pixelite\Customers::find((int) $o['customer_id']) : null; ?>
  <p><?= $linked ? 'Linked to <a href="/admin/customers/' . (int) $linked['id'] . '">' . e($linked['email']) . '</a>.' : 'Not linked to a customer account. Orders are never linked automatically from the email address.' ?></p>
  <form method="post" action="/admin/orders/<?= (int) $o['id'] ?>/link">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label for="lk">Link to the customer with this email (empty = unlink). Check that it is really the same person first.</label>
    <input class="form__field form__text" id="lk" type="email" name="customer_email" value="<?= e($linked['email'] ?? '') ?>">
    <button class="btn btn--ghost btn--sm btn--plain" type="submit">Save link</button>
  </form>
  <form method="post" action="/admin/orders/<?= (int) $o['id'] ?>/retry">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="btn btn--accent btn--sm btn--plain" type="submit"><?= $o['notification_status'] === 'sent' ? 'Send Telegram notification again' : 'Retry Telegram notification' ?></button>
  </form>
</div>
