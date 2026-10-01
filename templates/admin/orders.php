<div class="row"><h1 class="row__title">Orders</h1></div>
<div class="admin__card row--margin">
<?php if (!$orders): ?>
  <p class="admin__empty">No orders yet.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table admin__table">
      <thead><tr><th>#</th><th>Created (UTC)</th><th>Name</th><th>Email</th><th>Project</th><th>Package</th><th>Budget</th><th>Lang</th><th>Telegram</th></tr></thead>
      <tbody>
<?php foreach ($orders as $o): ?>
        <tr>
          <td><a href="/admin/orders/<?= (int) $o['id'] ?>"><?= (int) $o['id'] ?></a></td>
          <td><?= e(substr($o['created_at'], 0, 16)) ?></td>
          <td><?= e($o['name']) ?><?= $o['company'] !== '' ? '<br><small>' . e($o['company']) . '</small>' : '' ?></td>
          <td><?= e($o['email']) ?></td>
          <td><?= e($o['project_type']) ?></td>
<?php $pk = \Pixelite\PricingSnapshot::describe($o); ?>
          <td><?php if ($pk === null): ?>–<?php elseif ($pk['recorded']): ?><?= e($pk['name']) ?><br><small><?= e($pk['price']) ?></small><?php else: ?><code><?= e($pk['id']) ?></code><br><small>price not recorded</small><?php endif ?></td>
          <td><?= e($o['budget']) ?></td>
          <td><?= e($o['locale']) ?></td>
          <td><span class="admin__status admin__status--<?= e($o['notification_status']) ?>"><?= e($o['notification_status']) ?></span></td>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
</div>
<?php if ($page > 1 || $hasMore): ?>
<p class="admin__pager"><?php if ($page > 1): ?><a href="/admin?page=<?= $page - 1 ?>">← Newer</a><?php endif ?> <?php if ($hasMore): ?><a href="/admin?page=<?= $page + 1 ?>">Older →</a><?php endif ?></p>
<?php endif ?>
