<?php /** @var array $c @var list<array> $orders @var list<array> $projects @var string $flash @var string $csrf */ ?>
<a class="admin__back" href="/admin/customers">← All customers</a>
<?php if ($flash !== ''): ?><div class="form__alert form__alert--ok" role="status"><?= e($flash) ?></div><?php endif ?>
<div class="admin__card">
  <h1 class="row__title admin__title"><?= e($c['name']) ?></h1>
  <dl class="admin__dl">
    <dt>Email</dt><dd><?= e($c['email']) ?></dd>
    <dt>Company</dt><dd><?= e($c['company']) ?: '–' ?></dd>
    <dt>Phone</dt><dd><?= e($c['phone']) ?: '–' ?></dd>
    <dt>Language</dt><dd><?= e($c['locale']) ?></dd>
    <dt>Status</dt><dd><span class="admin__status admin__status--<?= $c['status'] === 'active' ? 'sent' : 'failed' ?>"><?= e($c['status']) ?></span></dd>
    <dt>Registered (UTC)</dt><dd><?= e($c['created_at']) ?></dd>
    <dt>Last sign-in (UTC)</dt><dd><?= e($c['last_login_at'] ?? '–') ?></dd>
    <dt>Linked orders</dt><dd><?php foreach ($orders as $o): ?><a href="/admin/orders/<?= (int) $o['id'] ?>">#<?= (int) $o['id'] ?></a> <?php endforeach ?><?= $orders ? '' : '–' ?></dd>
    <dt>Projects</dt><dd><?php foreach ($projects as $p): ?><a href="/admin/projects/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a> <?php endforeach ?><?= $projects ? '' : '–' ?></dd>
  </dl>
  <form method="post" action="/admin/customers/<?= (int) $c['id'] ?>/status" class="admin__actions">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="status" value="<?= $c['status'] === 'active' ? 'disabled' : 'active' ?>">
    <button class="btn btn--revert btn--auto" type="submit"><?= $c['status'] === 'active' ? 'Disable account' : 'Enable account' ?></button>
  </form>
</div>
