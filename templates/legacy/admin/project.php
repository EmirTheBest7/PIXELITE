<?php /** @var array $p @var list<array> $files @var bool $previewOn @var string $previewUrl @var list<string> $errors @var list<string> $statuses @var array $limits @var string $flash @var string $csrf */ ?>
<a class="admin__back" href="/admin/projects">← All projects</a>
<?php if ($flash !== ''): ?><div class="form__alert form__alert--ok" role="status"><?= e($flash) ?></div><?php endif ?>
<?php foreach ($errors as $er): ?><div class="form__alert" role="alert"><?= e($er) ?></div><?php endforeach ?>
<div class="admin__card">
  <h1 class="row__title admin__title"><?= e($p['name']) ?></h1>
  <p>Internal id <code><?= e($p['uid']) ?></code> · <?= (int) $p['file_count'] ?> file(s) · <?= e(number_format($p['total_bytes'] / 1024, 0, '.', ' ')) ?> KB · entry <code><?= e($p['entry']) ?></code> · updated <?= e(substr($p['updated_at'], 0, 16)) ?> UTC</p>

  <h2 class="admin__h2">Preview link</h2>
  <div class="admin__warn"><strong>Treat the link like a password.</strong> Anyone who has it can open this project, without signing in, until you switch it off or generate a new link. The page runs on a separate preview origin and cannot reach the admin area, the accounts or the order data.</div>
<?php if (!$previewOn): ?>
  <p><strong>Not available:</strong> no isolated preview origin is configured (<code>PREVIEW_ORIGIN</code>), so no link can be shown. See docs/deployment/endora.md.</p>
<?php elseif (!$p['access_enabled']): ?>
  <p>The link is <span class="admin__status admin__status--skipped">off</span>. Switch it on to share it.</p>
<?php else: ?>
  <p>The link is <span class="admin__status admin__status--sent">on</span> · token created <?= e(substr($p['token_rotated_at'], 0, 16)) ?> UTC</p>
  <code class="admin__url" id="preview-url"><?= e($previewUrl) ?></code>
  <p><button class="btn btn--auto" type="button" data-copy-target="preview-url" hidden>Copy link</button> <a class="btn btn--revert btn--auto" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener noreferrer">Open in a new tab</a> <span class="admin__copied" data-copy-status role="status"></span></p>
<?php endif ?>
  <div class="admin__actions">
    <form method="post" action="/admin/projects/<?= (int) $p['id'] ?>/access"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="access" value="<?= $p['access_enabled'] ? 'off' : 'on' ?>"><button class="btn btn--revert btn--auto" type="submit"><?= $p['access_enabled'] ? 'Switch the link off' : 'Switch the link on' ?></button></form>
    <form method="post" action="/admin/projects/<?= (int) $p['id'] ?>/token"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn--revert btn--auto" type="submit">Generate a new link (the old one stops working)</button></form>
  </div>
</div>

<div class="admin__card">
  <h2 class="admin__h2">Website files</h2>
  <p>Upload a ZIP of the finished static website (HTML, CSS, JavaScript, images, fonts, video) or select files. Only these types are accepted: <?= e(implode(', ', array_keys(\Pixelite\ProjectStorage::TYPES))) ?>. Server-side code (PHP …) is refused and nothing is ever executed. Use <strong>relative</strong> links inside the site; the preview blocks requests to other websites.
    Limits: <?= (int) $limits['file'] ?> MB per file, <?= (int) $limits['total'] ?> MB per project. This server currently accepts uploads up to <?= e($limits['upload']) ?> per file and <?= e($limits['post']) ?> per request (<?= e($limits['count']) ?> files).</p>
  <form method="post" action="/admin/projects/<?= (int) $p['id'] ?>/upload" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label for="u-zip">ZIP archive</label>
    <input id="u-zip" type="file" name="zip" accept=".zip,application/zip">
    <label for="u-files">…or files / a folder</label>
    <input id="u-files" type="file" name="files[]" multiple webkitdirectory>
    <label for="u-mode">What to do with the existing files</label>
    <select class="form__field form__select" id="u-mode" name="mode"><option value="replace">Replace everything with this upload</option><option value="add">Add to / overwrite single files</option></select>
    <div class="admin__actions"><button class="btn btn--auto" type="submit">Upload</button></div>
  </form>
<?php if ($files): ?>
  <details><summary><?= count($files) ?> stored file(s)</summary>
    <ul class="admin__files"><?php foreach ($files as $f): ?><li><code><?= e($f['path']) ?></code> <small><?= e(number_format($f['size'] / 1024, 1, '.', ' ')) ?> KB</small></li><?php endforeach ?></ul>
  </details>
<?php endif ?>
</div>

<div class="admin__card">
  <h2 class="admin__h2">Details</h2>
  <form method="post" action="/admin/projects/<?= (int) $p['id'] ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label for="pn">Project name</label>
    <input class="form__field form__text" id="pn" name="name" required maxlength="<?= \Pixelite\Projects::MAX_NAME ?>" value="<?= e($p['name']) ?>">
    <label for="ps">Status</label>
    <select class="form__field form__select" id="ps" name="status"><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"<?= $s === $p['status'] ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach ?></select>
    <label for="po">Related order number (optional)</label>
    <input class="form__field form__text" id="po" name="order_id" inputmode="numeric" pattern="[0-9]*" value="<?= $p['order_id'] ? (int) $p['order_id'] : '' ?>">
    <label for="pc">Customer account email (optional)</label>
    <p class="auth-field__hint">Accounts are not e-mail verified. Anyone can register any address, so check that the account really belongs to your client (see its registration date on the Customers page) before linking an order or project: the customer gets the preview link and order summary.</p>
    <input class="form__field form__text" id="pc" name="customer_email" type="email" value="<?= e($p['customer_id'] ? (\Pixelite\Customers::find((int) $p['customer_id'])['email'] ?? '') : '') ?>">
    <label for="pt">Internal notes (never shown to the customer)</label>
    <textarea class="form__field form__textarea" id="pt" name="notes" maxlength="<?= \Pixelite\Projects::MAX_NOTES ?>"><?= e($p['notes']) ?></textarea>
    <div class="admin__actions"><button class="btn btn--auto" type="submit">Save</button> <a class="btn btn--revert btn--auto" href="/admin/projects/<?= (int) $p['id'] ?>/delete">Delete project…</a></div>
  </form>
</div>
