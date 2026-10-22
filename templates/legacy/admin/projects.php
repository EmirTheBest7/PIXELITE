<?php /** @var list<array> $rows @var bool $previewOn @var string $flash */ ?>
<div class="row"><h1 class="row__title">Projects</h1></div>
<?php if ($flash !== ''): ?><div class="form__alert form__alert--ok" role="status"><?= e($flash) ?></div><?php endif ?>
<?php if (!$previewOn): ?>
<div class="admin__warn"><strong>Browser previews are switched off.</strong> They need a separate preview origin (set <code>PREVIEW_ORIGIN</code> to a host that is different from the main site, see docs/deployment/endora.md). Projects and uploads still work; the links stay hidden until it is configured.</div>
<?php endif ?>
<?php if (\Pixelite\Preview::sharesSite()): ?>
<div class="admin__warn"><strong>Weaker isolation.</strong> The preview host shares its registrable domain with the main site. Origins are still separate (no shared storage, scripts, or cookies sent), but code on a preview could set cookies for the parent domain. The session cookie is protected with the <code>__Host-</code> prefix over HTTPS; for full isolation use a separate domain (for example <code>pixelite-preview.cz</code>).</div>
<?php endif ?>
<p><a class="btn btn--auto" href="/admin/projects/new">New project</a></p>
<div class="admin__card row--margin">
<?php if (!$rows): ?>
  <p class="admin__empty">No projects yet.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table admin__table">
      <thead><tr><th>#</th><th>Name</th><th>Status</th><th>Preview link</th><th>Files</th><th>Order</th><th>Customer</th><th>Updated (UTC)</th></tr></thead>
      <tbody>
<?php foreach ($rows as $p): ?>
        <tr>
          <td><a href="/admin/projects/<?= (int) $p['id'] ?>"><?= (int) $p['id'] ?></a></td>
          <td><a href="/admin/projects/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a></td>
          <td><?= e($p['status']) ?></td>
          <td><span class="admin__status admin__status--<?= $p['access_enabled'] ? 'sent' : 'skipped' ?>"><?= $p['access_enabled'] ? 'on' : 'off' ?></span></td>
          <td><?= (int) $p['file_count'] ?> · <?= e(number_format($p['total_bytes'] / 1024, 0, '.', ' ')) ?> KB</td>
          <td><?= $p['order_id'] ? '<a href="/admin/orders/' . (int) $p['order_id'] . '">#' . (int) $p['order_id'] . '</a>' : '–' ?></td>
          <td><?= $p['customer_id'] ? '<a href="/admin/customers/' . (int) $p['customer_id'] . '">#' . (int) $p['customer_id'] . '</a>' : '–' ?></td>
          <td><?= e(substr($p['updated_at'], 0, 16)) ?></td>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
</div>
