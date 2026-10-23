<?php /** The three packages as an expanding numbered list (landing page). Same data as package-cards.php (lang services.items); /pricing keeps the cards. No price text lives in templates. */
require_once __DIR__ . '/ui.php'; ?>
<div class="plist">
<?php foreach (t('services.items') as $i => $s): ?>
  <details class="plist__row" name="packages" data-package="<?= e($s['id']) ?>"<?= $i === 0 ? ' open' : '' ?>>
    <summary class="plist__sum">
      <span class="plist__n" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
      <span class="plist__name"><span class="plist__title"><?= e($s['name']) ?></span><span class="plist__tag"><?= e($s['tagline']) ?></span></span>
      <span class="plist__price"><span class="pkg__from"><?= e($s['from']) ?></span> <span class="plist__amount"><?= e($s['amount']) ?></span> <span class="pkg__cur"><?= e($s['currency']) ?></span></span>
      <svg class="plist__chev" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8l5 5 5-5"/></svg>
    </summary>
    <div class="plist__body">
      <div class="plist__text">
        <p class="plist__desc"><?= e($s['desc']) ?></p>
        <p class="pkg__vat"><?php if (vat_mode() !== ''): ?><?= e(t('vat.card_' . vat_mode())) ?><?php else: ?><span class="placeholder"><?= e(t('vat.unset')) ?></span><?php endif ?></p>
        <p class="pkg__note plist__note"><?= e($s['note']) ?></p>
        <?= ui_link(url('order') . '?package=' . $s['id'], (string) t('services.cta'), 'accent') ?>
      </div>
      <div class="plist__feats">
        <p class="pkg__feat plist__feat"><?= e(t('services.included')) ?></p>
        <ul class="pkg__list">
<?php foreach ($s['features'] as $f): ?>
          <li><?= e($f) ?></li>
<?php endforeach ?>
        </ul>
      </div>
    </div>
  </details>
<?php endforeach ?>
</div>
