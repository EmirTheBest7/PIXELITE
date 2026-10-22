<?php /** @var string $slug @var string $title @var array $e @var ?array $prompt @var int $orders @var string $csrf @var string $flash @var list<string> $errors */
?>
<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<a class="admin__back" href="/admin/templates">← All design prompts</a>
<?php if ($flash !== ''): ?><div class="form__alert" role="status"><?= e($flash) ?></div><?php endif ?>
<?php foreach ($errors as $err): ?><div class="form__alert" role="alert"><?= e($err) ?></div><?php endforeach ?>
<div class="admin__card">
  <h1 class="admin__title"><?= e($title) ?></h1>
  <dl class="admin__dl">
    <dt>Slug</dt><dd><code><?= e($slug) ?></code> · <a href="/en/templates/<?= e($slug) ?>">public page</a></dd>
    <dt>Category</dt><dd><?= e($e['category']) ?></dd>
    <dt>Origin</dt><dd><?= e($e['origin']['author']) ?> – original template. Preview: <a href="/en/templates/<?= e($slug) ?>/demo">live demo</a> (source: <code>templates/demos/<?= e($e['demo'] ?? '') ?>.html</code> + <code>public/assets/templates/<?= e($slug) ?>/</code>)</dd>
<?php foreach ($e['origin']['third_party'] ?? [] as $c): ?>
    <dt>Component</dt><dd><?= e($c['name']) ?> – <?= e($c['license']) ?> · <a href="<?= e($c['url']) ?>" rel="noopener noreferrer">project</a> · <a href="<?= e($c['notice']) ?>">notice shipped with the template</a><br><small>Keep this notice file in any customised copy delivered to a customer.</small></dd>
<?php endforeach ?>
    <dt>Stack</dt><dd><?= e(implode(', ', $e['stack'])) ?></dd>
    <dt>Orders choosing this design</dt><dd><?= (int) $orders ?></dd>
    <dt>Prompt</dt><dd><?= $prompt === null ? '<span class="admin__status admin__status--failed">missing</span>' : '<span class="admin__status admin__status--sent">version ' . (int) $prompt['version'] . '</span> <small>updated ' . e($prompt['updated_at']) . ' (UTC)</small>' ?></dd>
<?php if ($prompt !== null && ($prompt['origin'] ?? '') !== ''): ?>
    <dt>Prompt origin</dt><dd><?= e($prompt['origin']) ?></dd>
<?php endif ?>
<?php if ($prompt !== null && ($prompt['notes'] ?? '') !== ''): ?>
    <dt>Technical notes</dt><dd class="admin__pre"><?= e($prompt['notes']) ?></dd>
<?php endif ?>
  </dl>
<?php if ($prompt !== null && ($prompt['version'] ?? 0) > 0 && !$errors): ?>
  <h2 class="admin__h2">Prompt text</h2>
  <p><?= ui_button('Copy prompt', 'sm accent plain', ['data-copy-target' => 'prompt-text', 'hidden' => true]) ?> <span class="admin__copied" data-copy-status role="status"></span></p>
  <pre class="admin__prompt" id="prompt-text"><?= e($prompt['body']) ?></pre>
<?php endif ?>
  <h2 class="admin__h2"><?= $prompt === null ? 'Add the prompt' : 'Edit the prompt' ?></h2>
  <form method="post" action="/admin/templates/<?= e($slug) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label for="p-body">Prompt (plain text; saving a changed text creates a new version)</label>
    <textarea class="form__field form__textarea admin__textarea" id="p-body" name="body" required maxlength="<?= \Pixelite\TemplatePrompts::MAX_BODY ?>"><?= e($prompt['body'] ?? '') ?></textarea>
    <label for="p-origin">Origin</label>
    <input class="form__field form__text" id="p-origin" type="text" name="origin" maxlength="200" value="<?= e($prompt['origin'] ?? 'Authored by Pixelite') ?>">
    <label for="p-notes">Technical notes</label>
    <textarea class="form__field form__textarea" id="p-notes" name="notes" maxlength="<?= \Pixelite\TemplatePrompts::MAX_NOTES ?>"><?= e($prompt['notes'] ?? '') ?></textarea>
    <?= ui_button('Save prompt', 'accent', ['type' => 'submit']) ?>
  </form>
</div>
