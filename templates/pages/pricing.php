<?php require_once __DIR__ . '/../partials/ui.php'; $vat = vat_mode(); $pkgs = t('services.items'); $none = t('pricing.compare_none');
$ph = ['label' => (string) t('nav.pricing'), 'title' => (string) t('pricing.title'), 'sub' => (string) t('pricing.subtitle')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section id="packages" class="section" aria-label="<?= e(t('nav.pricing')) ?>">
  <div class="wrap">
    <p class="page-intro"><?= e(t('pricing.intro')) ?> <?php if ($vat !== ''): ?><?= e(legal_text('{vat}')) ?><?php else: ?><span class="placeholder"><?= e(t('vat.unset')) ?></span><?php endif ?></p>
    <?php require __DIR__ . '/../partials/package-cards.php' ?>
    <?php require __DIR__ . '/../partials/credits.php' ?>
  </div>
</section>

<section class="section section--soft" aria-labelledby="why-title">
  <div class="wrap">
    <div class="sec-head"><h2 class="h-lg" id="why-title"><?= e(t('pricing.why_title')) ?></h2></div>
    <div class="why">
<?php foreach ($pkgs as $p): ?>
      <div class="why__item">
        <h3 class="why__name"><?= e($p['name']) ?></h3>
        <p class="why__text"><?= e(t('pricing.why.' . $p['id'])) ?></p>
      </div>
<?php endforeach ?>
    </div>
  </div>
</section>

<section id="compare" class="section" aria-labelledby="compare-title">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="h-lg" id="compare-title"><?= e(t('pricing.compare_title')) ?></h2>
      <p class="lede"><?= e(t('pricing.compare_note')) ?></p>
    </div>
    <div class="compare-wrap" tabindex="0" role="region" aria-label="<?= e(t('pricing.compare_title')) ?>">
    <table class="compare">
      <thead>
        <tr>
          <th scope="col"><span class="sr-only"><?= e(t('pricing.compare_feature')) ?></span></th>
<?php foreach ($pkgs as $p): ?>
          <th scope="col"><?= e($p['name']) ?><span class="compare__price"><?= e($p['from']) ?> <?= e($p['amount']) ?> <?= e($p['currency']) ?></span></th>
<?php endforeach ?>
        </tr>
      </thead>
      <tbody>
<?php foreach (t('pricing.compare_rows') as $row): ?>
        <tr>
          <th scope="row"><?= e($row['label']) ?></th>
<?php foreach ($pkgs as $i => $p): $cell = $row['cells'][$i]; ?>
          <td data-label="<?= e($p['name']) ?>"><?php if ($cell !== null): ?><?= e($cell) ?><?php else: ?><span class="compare__none"><?= e($none) ?></span><?php endif ?></td>
<?php endforeach ?>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
    </div>
  </div>
</section>

<section id="scope" class="section section--soft" aria-labelledby="scope-title">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="h-lg" id="scope-title"><?= e(t('pricing.scope_title')) ?></h2>
      <p class="lede"><?= e(t('pricing.scope_intro')) ?></p>
    </div>
    <div class="scope">
<?php foreach ($pkgs as $i => $p): ?>
      <div class="scope__card" data-package="<?= e($p['id']) ?>">
        <h3 class="scope__name"><?= e($p['name']) ?></h3>
        <h4 class="scope__head"><?= e(t('pricing.included')) ?></h4>
        <ul class="scope__list scope__list--yes">
<?php foreach ($p['features'] as $f): ?>
          <li><?= e($f) ?></li>
<?php endforeach ?>
        </ul>
        <h4 class="scope__head"><?= e(t('pricing.not_included')) ?></h4>
        <ul class="scope__list scope__list--no">
<?php foreach (t('pricing.not_included_items.' . $p['id']) as $f): ?>
          <li><?= e($f) ?></li>
<?php endforeach ?>
        </ul>
      </div>
<?php endforeach ?>
    </div>
    <p class="page-intro"><span class="placeholder"><?= e(t('pricing.exclusions_placeholder')) ?></span></p>
  </div>
</section>

<section class="section" aria-labelledby="factors-title">
  <div class="wrap">
    <div class="sec-head"><h2 class="h-lg" id="factors-title"><?= e(t('pricing.factors_title')) ?></h2></div>
    <ul class="factors">
<?php foreach (t('pricing.factors') as $f): ?>
      <li><?= e($f) ?></li>
<?php endforeach ?>
    </ul>
  </div>
</section>

<section id="faq" class="section section--soft" aria-labelledby="faq-title">
  <div class="wrap">
    <div class="sec-head"><h2 class="h-lg" id="faq-title"><?= e(t('faq.title')) ?></h2></div>
    <?php require __DIR__ . '/../partials/faq.php' ?>
  </div>
</section>

<section class="section" aria-labelledby="cta-title">
  <div class="wrap">
    <div class="band">
      <div>
        <h2 class="band__title" id="cta-title"><?= e(t('pricing.cta_title')) ?></h2>
        <p class="band__sub"><?= e(t('pricing.cta_text')) ?></p>
      </div>
      <div class="band__actions">
        <?= ui_link(url('order'), (string) t('pricing.cta_button'), 'light') ?>
        <?= ui_link(url('process'), (string) t('pricing.cta_secondary'), 'ghost .band__ghost') ?>
      </div>
    </div>
  </div>
</section>
