<?php /** @var ?array $row @var array $titles @var array $descriptions @var list<string> $errors @var string $csrf */
$new = $row === null; ?>
<a class="admin__back" href="/admin/careers">← All vacancies</a>
<div class="admin__card">
  <h1 class="row__title admin__title"><?= $new ? 'New vacancy' : 'Edit vacancy' ?></h1>
<?php foreach ($errors as $er): ?><div class="form__alert" role="alert"><?= e($er) ?></div><?php endforeach ?>
<?php if (!$new): ?>
  <p>Status: <span class="admin__status admin__status--<?= $row['status'] === 'published' ? 'sent' : 'skipped' ?>"><?= e($row['status']) ?></span>
    · created <?= e(substr($row['created_at'], 0, 16)) ?> · updated <?= e(substr($row['updated_at'], 0, 16)) ?> · published <?= $row['published_at'] ? e(substr($row['published_at'], 0, 16)) : '–' ?> (UTC)</p>
<?php endif ?>
  <form method="post" action="<?= $new ? '/admin/careers' : '/admin/careers/' . (int) $row['id'] ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php foreach (\Pixelite\I18n::LOCALES as $loc): ?>
    <div class="admin__lang">
      <h3><?= e(\Pixelite\I18n::NAMES[$loc]) ?> (<?= $loc ?>)<?= $loc === 'en' ? ' – required, used as fallback' : ' – optional' ?></h3>
      <label for="t-<?= $loc ?>">Title</label>
      <input class="form__field form__text" id="t-<?= $loc ?>" name="title_<?= $loc ?>" maxlength="<?= \Pixelite\Careers::MAX_TITLE ?>" value="<?= e($titles[$loc] ?? '') ?>"<?= $loc === 'en' ? ' required' : '' ?> lang="<?= $loc ?>">
      <label for="d-<?= $loc ?>">Description (plain text; a blank line starts a new paragraph)</label>
      <textarea class="form__field form__textarea" id="d-<?= $loc ?>" name="description_<?= $loc ?>" maxlength="<?= \Pixelite\Careers::MAX_DESCRIPTION ?>"<?= $loc === 'en' ? ' required' : '' ?> lang="<?= $loc ?>"><?= e($descriptions[$loc] ?? '') ?></textarea>
    </div>
<?php endforeach ?>
    <div class="admin__actions"><button class="btn btn--auto" type="submit"><?= $new ? 'Save as draft' : 'Save' ?></button></div>
  </form>
<?php if (!$new): ?>
  <div class="admin__actions">
    <form method="post" action="/admin/careers/<?= (int) $row['id'] ?>/<?= $row['status'] === 'published' ? 'unpublish' : 'publish' ?>"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn--revert btn--auto" type="submit"><?= $row['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form>
    <a class="btn btn--revert btn--auto" href="/admin/careers/<?= (int) $row['id'] ?>/delete">Delete…</a>
  </div>
<?php endif ?>
</div>
