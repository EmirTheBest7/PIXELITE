<?php $vat = vat_mode(); $pkgs = t('services.items'); $none = t('pricing.compare_none'); ?>
<section id="packages" class="sect sect--padding-top sect--padding-bottom" aria-labelledby="pricing-title">
  <div class="container">
    <div class="row row--center">
      <h1 class="row__title" id="pricing-title"><?= e(t('pricing.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('pricing.subtitle')) ?></h2>
    </div>
    <p class="page-intro"><?= e(t('pricing.intro')) ?> <?php if ($vat !== ''): ?><?= e(legal_text('{vat}')) ?><?php else: ?><span class="placeholder"><?= e(t('vat.unset')) ?></span><?php endif ?></p>
    <?php require __DIR__ . '/../partials/package-cards.php' ?>

    <?php require __DIR__ . '/../partials/credits.php' ?>
  </div>
</section>

<section class="sect sect--no-padding sect--padding-bottom" aria-labelledby="why-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="why-title"><?= e(t('pricing.why_title')) ?></h2>
    </div>
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

<section id="compare" class="sect sect--no-padding sect--padding-bottom" aria-labelledby="compare-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="compare-title"><?= e(t('pricing.compare_title')) ?></h2>
      <h3 class="row__sub"><?= e(t('pricing.compare_note')) ?></h3>
    </div>
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
</section>

<section id="scope" class="sect sect--no-padding sect--padding-bottom" aria-labelledby="scope-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="scope-title"><?= e(t('pricing.scope_title')) ?></h2>
      <h3 class="row__sub"><?= e(t('pricing.scope_intro')) ?></h3>
    </div>
    <div class="scope">
<?php $skin = ['purple', 'violet', 'blue']; foreach ($pkgs as $i => $p): ?>
      <div class="scope__card scope__card--<?= $skin[$i] ?>" data-package="<?= e($p['id']) ?>">
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

<section class="sect sect--no-padding sect--padding-bottom" aria-labelledby="factors-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="factors-title"><?= e(t('pricing.factors_title')) ?></h2>
    </div>
    <ul class="factors">
<?php foreach (t('pricing.factors') as $f): ?>
      <li><?= e($f) ?></li>
<?php endforeach ?>
    </ul>
  </div>
</section>

<section id="faq" class="sect sect--no-padding sect--padding-bottom" aria-labelledby="faq-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="faq-title"><?= e(t('faq.title')) ?></h2>
    </div>
    <?php require __DIR__ . '/../partials/faq.php' ?>
  </div>
</section>

<section class="sect sect--violet">
  <img src="<?= e(asset('img/band.png')) ?>" class="career-img" alt="">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h2 class="career_title"><?= e(t('pricing.cta_title')) ?></h2>
        <h3 class="career_sub"><?= e(t('pricing.cta_text')) ?></h3>
        <a href="<?= e(url('order')) ?>" class="btn btn--white btn--auto"><?= e(t('pricing.cta_button')) ?></a>
        <a href="<?= e(url('process')) ?>" class="btn btn--white btn--auto"><?= e(t('pricing.cta_secondary')) ?></a>
      </div>
    </div>
  </div>
</section>
