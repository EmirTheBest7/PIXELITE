<?php $vat = vat_mode(); ?>
<section id="price" class="sect sect--padding-top sect--padding-bottom" aria-labelledby="bots-title">
  <div class="container">
    <div class="row row--center">
      <h1 class="row__title" id="bots-title"><?= e(t('bots.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('bots.subtitle')) ?></h2>
    </div>
    <p class="page-intro"><?= e(t('bots.intro')) ?></p>
    <p class="page-intro"><?= e(t('bots.price_intro')) ?> <?php if ($vat !== ''): ?><?= e(legal_text('{vat}')) ?><?php else: ?><span class="placeholder"><?= e(t('vat.unset')) ?></span><?php endif ?></p>
    <div class="scope">
<?php foreach (t('bots.price_cards') as $c): ?>
      <div class="scope__card" data-bot-price="<?= e($c['id']) ?>">
        <h3 class="why__name"><?= e($c['name']) ?></h3>
<?php if (isset($c['amount'])): ?>
        <p class="price-box__discount"><?php if (isset($c['from'])): ?><span class="price-box__from"><?= e($c['from']) ?></span> <?php endif ?><span class="price-box__amount"><?= e($c['amount']) ?></span> <span class="price-box__discount--light"><?= e($c['currency']) ?> / <?= e($c['per']) ?></span></p>
<?php endif ?>
<?php if (isset($c['billing'])): ?>
        <p class="why__text"><strong><?= e($c['billing']) ?></strong></p>
<?php endif ?>
        <p class="why__text"><?= e($c['text']) ?></p>
<?php if (!empty($c['list'])): ?>
        <ul class="scope__list scope__list--yes">
<?php foreach ($c['list'] as $f): ?>
          <li><?= e($f) ?></li>
<?php endforeach ?>
        </ul>
<?php endif ?>
      </div>
<?php endforeach ?>
    </div>
    <p class="consult__cta"><a class="btn btn--strong btn--auto" href="<?= e(url('order') . '?type=bot') ?>"><?= e(t('bots.cta_button')) ?></a></p>
  </div>
</section>

<section id="features" class="sect sect--no-padding sect--padding-bottom" aria-labelledby="what-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="what-title"><?= e(t('bots.what_title')) ?></h2>
      <h3 class="row__sub"><?= e(t('bots.what_sub')) ?></h3>
    </div>
    <div class="scope">
<?php foreach (t('bots.tabs') as $g): ?>
      <div class="scope__card">
        <h3 class="why__name"><?= e($g['title']) ?></h3>
        <ul class="scope__list scope__list--yes">
<?php foreach ($g['items'] as $f): ?>
          <li><?= e($f) ?></li>
<?php endforeach ?>
        </ul>
      </div>
<?php endforeach ?>
    </div>
  </div>
</section>

<section class="sect sect--no-padding sect--padding-bottom" aria-labelledby="fit-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="fit-title"><?= e(t('bots.fit_title')) ?></h2>
      <h3 class="row__sub"><?= e(t('bots.fit_text')) ?></h3>
    </div>
    <h3 class="why__name"><?= e(t('bots.next_title')) ?></h3>
    <p class="page-intro"><?= e(t('bots.next_sub')) ?></p>
    <ul class="factors">
<?php foreach (t('bots.next') as $n): ?>
      <li><?= e($n) ?></li>
<?php endforeach ?>
    </ul>
  </div>
</section>

<section class="sect sect--no-padding sect--padding-bottom" aria-labelledby="steps-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="steps-title"><?= e(t('bots.steps_title')) ?></h2>
    </div>
    <div class="why">
<?php foreach (t('bots.steps') as $i => $s): ?>
      <div class="why__item">
        <h3 class="why__name"><?= $i + 1 ?>. <?= e($s['title']) ?></h3>
        <p class="why__text"><?= e($s['text']) ?></p>
      </div>
<?php endforeach ?>
    </div>
  </div>
</section>

<section id="faq" class="sect sect--no-padding sect--padding-bottom" aria-labelledby="faq-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="faq-title"><?= e(t('bots.faq_title')) ?></h2>
    </div>
    <div class="faq">
<?php foreach (t('bots.faq') as $item): ?>
      <details class="faq__item">
        <summary class="faq__q"><?= e($item['q']) ?></summary>
        <p class="faq__a"><?= e($item['a']) ?></p>
      </details>
<?php endforeach ?>
    </div>
  </div>
</section>

<section class="sect sect--violet">
  <img src="<?= e(asset('img/band.png')) ?>" class="career-img" alt="">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h2 class="career_title"><?= e(t('bots.cta_title')) ?></h2>
        <h3 class="career_sub"><?= e(t('bots.cta_text')) ?></h3>
        <a href="<?= e(url('order') . '?type=bot') ?>" class="btn btn--white btn--auto"><?= e(t('bots.cta_button')) ?></a>
        <a href="<?= e(url('pricing')) ?>" class="btn btn--white btn--auto"><?= e(t('bots.cta_secondary')) ?></a>
      </div>
    </div>
  </div>
</section>
