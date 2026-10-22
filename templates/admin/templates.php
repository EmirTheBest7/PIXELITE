<?php /** @var array $rows @var string $q @var string $status @var string $category @var string $csrf @var int $missing @var int $total @var string $flash */ ?>
<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<h1 class="admin__title">Design prompts</h1>
<?php if ($flash !== ''): ?><div class="form__alert" role="status"><?= e($flash) ?></div><?php endif ?>
<div class="admin__card">
  <p><strong><?= $total - $missing ?></strong> of <?= $total ?> designs have a prompt<?= $missing ? ' · <strong>' . $missing . ' missing</strong>' : '' ?>.
    Prompts are written by Pixelite and are visible only here.</p>
  <form method="get" action="/admin/templates" class="admin__filters">
    <label class="sr-only" for="a-q">Search</label>
    <input class="form__field form__text" id="a-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search slug, title, category, origin">
    <label class="sr-only" for="a-status">Prompt status</label>
    <select class="form__field form__select" id="a-status" name="status">
      <option value="">Any prompt status</option>
      <option value="has"<?= $status === 'has' ? ' selected' : '' ?>>Has prompt</option>
      <option value="missing"<?= $status === 'missing' ? ' selected' : '' ?>>Missing prompt</option>
    </select>
    <label class="sr-only" for="a-cat">Category</label>
    <select class="form__field form__select" id="a-cat" name="category">
      <option value="">All categories</option>
<?php foreach (\Pixelite\TemplateCatalog::categories() as $c): ?>
      <option value="<?= e($c) ?>"<?= $category === $c ? ' selected' : '' ?>><?= e($c) ?></option>
<?php endforeach ?>
    </select>
    <?= ui_button('Filter', 'sm accent plain', ['type' => 'submit']) ?>
  </form>
<?php if (!$rows): ?>
  <p class="admin__empty">No designs match.</p>
<?php else: ?>
  <div class="table-wrap" tabindex="0" role="region" aria-label="Design prompts">
    <table class="admin__table">
      <thead><tr><th>Design</th><th>Category</th><th>Prompt</th><th>Origin</th><th>Components</th></tr></thead>
      <tbody>
<?php foreach ($rows as $row): $e = $row['e']; $p = $row['p']; ?>
        <tr>
          <td><a href="/admin/templates/<?= e($row['slug']) ?>"><?= e($row['title']) ?></a><br><small><code><?= e($row['slug']) ?></code></small></td>
          <td><?= e($e['category']) ?></td>
          <td><?php if ($p === null): ?><span class="admin__status admin__status--failed">missing</span><?php else: ?><span class="admin__status admin__status--sent">v<?= (int) $p['version'] ?></span> <small><?= e(substr($p['updated_at'], 0, 10)) ?> · <?= (int) $p['chars'] ?> chars</small><?php endif ?></td>
          <td><?= e($e['origin']['author']) ?></td>
          <td><?= e(implode(', ', array_map(static fn(array $c): string => $c['name'] . ' (' . $c['license'] . ')', $e['origin']['third_party'] ?? [])) ?: '–') ?></td>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
</div>
<div class="admin__card">
  <h2 class="admin__h2">Import a prompt package</h2>
  <p>Upload the private JSON package (<code>{"prompts":[{"slug","body","origin","notes","version"}]}</code>) kept in the git-ignored <code>private/template-prompts/</code> folder. Everything is validated first; if any entry is invalid nothing is imported. Max 1 MB.</p>
  <form method="post" action="/admin/templates/import" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label for="a-file">JSON file</label>
    <input id="a-file" type="file" name="package" accept="application/json,.json" required>
    <?= ui_button('Import', 'sm accent plain', ['type' => 'submit']) ?>
  </form>
</div>
