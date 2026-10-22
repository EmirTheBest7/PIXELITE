<?php /** @var list<array> $rows @var string $flash */ ?>
<div class="row"><h1 class="row__title">Customers</h1></div>
<?php if ($flash !== ''): ?><div class="form__alert form__alert--ok" role="status"><?= e($flash) ?></div><?php endif ?>
<div class="admin__card row--margin">
<?php if (!$rows): ?>
  <p class="admin__empty">No customer accounts yet.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table admin__table">
      <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Lang</th><th>Status</th><th>Registered (UTC)</th><th>Last sign-in (UTC)</th></tr></thead>
      <tbody>
<?php foreach ($rows as $c): ?>
        <tr>
          <td><a href="/admin/customers/<?= (int) $c['id'] ?>"><?= (int) $c['id'] ?></a></td>
          <td><?= e($c['name']) ?><?= $c['company'] !== '' ? '<br><small>' . e($c['company']) . '</small>' : '' ?></td>
          <td><?= e($c['email']) ?></td>
          <td><?= e($c['locale']) ?></td>
          <td><span class="admin__status admin__status--<?= $c['status'] === 'active' ? 'sent' : 'failed' ?>"><?= e($c['status']) ?></span></td>
          <td><?= e(substr($c['created_at'], 0, 16)) ?></td>
          <td><?= $c['last_login_at'] ? e(substr($c['last_login_at'], 0, 16)) : '–' ?></td>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
</div>
