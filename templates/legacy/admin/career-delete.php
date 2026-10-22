<?php /** @var array $row @var string $csrf */ ?>
<a class="admin__back" href="/admin/careers/<?= (int) $row['id'] ?>">← Back</a>
<div class="admin__card">
  <h1 class="row__title admin__title">Delete this vacancy?</h1>
  <p><strong><?= e($row['titles']['en'] ?? '') ?></strong> (<?= e($row['status']) ?>) will be removed permanently<?= $row['status'] === 'published' ? ' and disappear from the public Careers page' : '' ?>. This cannot be undone.</p>
  <form method="post" action="/admin/careers/<?= (int) $row['id'] ?>/delete" class="admin__actions">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="btn btn--auto" type="submit">Yes, delete it</button>
    <a class="btn btn--revert btn--auto" href="/admin/careers/<?= (int) $row['id'] ?>">Cancel</a>
  </form>
</div>
