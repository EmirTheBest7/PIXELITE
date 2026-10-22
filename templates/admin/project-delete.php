<?php /** @var array $p @var string $csrf */ ?>
<a class="admin__back" href="/admin/projects/<?= (int) $p['id'] ?>">← Back</a>
<div class="admin__card">
  <h1 class="admin__title">Delete this project?</h1>
  <p><strong><?= e($p['name']) ?></strong>, its <?= (int) $p['file_count'] ?> uploaded file(s) and its preview link will be removed permanently. This cannot be undone.</p>
  <form method="post" action="/admin/projects/<?= (int) $p['id'] ?>/delete" class="admin__actions">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="btn btn--accent btn--sm btn--plain" type="submit">Yes, delete everything</button>
    <a class="btn btn--ghost btn--sm btn--plain" href="/admin/projects/<?= (int) $p['id'] ?>">Cancel</a>
  </form>
</div>
