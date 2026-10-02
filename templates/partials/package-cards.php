<?php /** The three package cards (shared by the landing page and /pricing). Data: lang services.items. */ ?>
<div class="row row--center row--margin pricing-row">
<?php $skin = ['purple', 'violet', 'blue']; foreach (t('services.items') as $i => $s): $c = $skin[$i]; ?>
  <div class="col-md-4 col-sm-4 price-box price-box--<?= $c ?>" data-package="<?= e($s['id']) ?>">
    <div class="price-box__wrap">
      <div class="price-box__img"></div>
      <h3 class="price-box__title"><?= e($s['name']) ?></h3>
      <p class="price-box__people"><?= e($s['tagline']) ?></p>
      <p class="price-box__desc"><?= e($s['desc']) ?></p>
      <h4 class="price-box__discount"><span class="price-box__from"><?= e($s['from']) ?></span> <span class="price-box__amount"><?= e($s['amount']) ?></span> <span class="price-box__discount--light"><?= e($s['currency']) ?></span></h4>
      <p class="price-box__vat"><?php if (vat_mode() !== ''): ?><?= e(t('vat.card_' . vat_mode())) ?><?php else: ?><span class="placeholder"><?= e(t('vat.unset')) ?></span><?php endif ?></p>
      <p class="price-box__note"><?= e($s['note']) ?></p>
      <p class="price-box__feat"><?= e(t('services.included')) ?></p>
      <ul class="price-box__list">
<?php foreach ($s['features'] as $f): ?>
        <li class="price-box__list-el"><?= e($f) ?></li>
<?php endforeach ?>
      </ul>
      <div class="price-box__btn">
        <a class="btn btn--<?= $c ?> btn--width" href="<?= e(url('order') . '?package=' . $s['id']) ?>"><?= e(t('services.cta')) ?></a>
      </div>
    </div>
  </div>
<?php endforeach ?>
</div>
