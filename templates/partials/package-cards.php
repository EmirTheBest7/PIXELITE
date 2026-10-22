<?php /** The three package cards (shared by the landing page and /pricing). Data: lang services.items – no price text lives in templates. */
require_once __DIR__ . '/ui.php'; ?>
<div class="pkgs">
<?php foreach (t('services.items') as $i => $s): ?>
  <article class="pkg" data-package="<?= e($s['id']) ?>">
    <p class="pkg__n" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></p>
    <h3 class="pkg__title"><?= e($s['name']) ?></h3>
    <p class="pkg__people"><?= e($s['tagline']) ?></p>
    <p class="pkg__desc"><?= e($s['desc']) ?></p>
    <p class="pkg__price"><span class="pkg__from"><?= e($s['from']) ?></span> <span class="pkg__amount"><?= e($s['amount']) ?></span> <span class="pkg__cur"><?= e($s['currency']) ?></span></p>
    <p class="pkg__vat"><?php if (vat_mode() !== ''): ?><?= e(t('vat.card_' . vat_mode())) ?><?php else: ?><span class="placeholder"><?= e(t('vat.unset')) ?></span><?php endif ?></p>
    <p class="pkg__note"><?= e($s['note']) ?></p>
    <p class="pkg__feat"><?= e(t('services.included')) ?></p>
    <ul class="pkg__list">
<?php foreach ($s['features'] as $f): ?>
      <li><?= e($f) ?></li>
<?php endforeach ?>
    </ul>
    <?= ui_link(url('order') . '?package=' . $s['id'], (string) t('services.cta'), 'block') ?>
  </article>
<?php endforeach ?>
</div>
