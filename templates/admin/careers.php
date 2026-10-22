<?php /** @var list<array> $rows @var string $flash @var string $csrf */ ?>
<h1 class="admin__title">Careers</h1>
<?php if ($flash !== ''): ?><div class="form__alert form__alert--ok" role="status"><?= e($flash) ?></div><?php endif ?>
<p><a class="btn btn--accent btn--sm btn--plain" href="/admin/careers/new">New vacancy</a></p>
<div class="admin__card">
<?php if (!$rows): ?>
  <p class="admin__empty">No vacancies yet. The public Careers page shows the “not hiring” message and the email link.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="admin__table">
      <thead><tr><th>Title (EN)</th><th>Status</th><th>Created (UTC)</th><th>Updated (UTC)</th><th>Published (UTC)</th><th></th></tr></thead>
      <tbody>
<?php foreach ($rows as $v): ?>
        <tr>
          <td><a href="/admin/careers/<?= (int) $v['id'] ?>"><?= e($v['titles']['en'] ?? '–') ?></a></td>
          <td><span class="admin__status admin__status--<?= $v['status'] === 'published' ? 'sent' : 'skipped' ?>"><?= e($v['status']) ?></span></td>
          <td><?= e(substr($v['created_at'], 0, 16)) ?></td>
          <td><?= e(substr($v['updated_at'], 0, 16)) ?></td>
          <td><?= $v['published_at'] ? e(substr($v['published_at'], 0, 16)) : '–' ?></td>
          <td class="admin__actions">
            <form method="post" action="/admin/careers/<?= (int) $v['id'] ?>/<?= $v['status'] === 'published' ? 'unpublish' : 'publish' ?>"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn--ghost btn--sm btn--plain" type="submit"><?= $v['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form>
            <a class="btn btn--ghost btn--sm btn--plain" href="/admin/careers/<?= (int) $v['id'] ?>/delete">Delete…</a>
          </td>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
</div>
