<?php require_once __DIR__ . '/../partials/ui.php'; $vat = vat_mode(); $demo = t('bots.demo'); ?>
<header class="phero">
  <div class="wrap bhero">
    <div>
      <div class="eyebrow"><span class="eyebrow__label"><?= e(t('nav.bots')) ?></span></div>
      <h1 class="phero__title"><?= e(t('bots.title')) ?></h1>
      <p class="phero__sub"><?= e(t('bots.subtitle')) ?></p>
      <div class="bhero__actions">
        <?= ui_link(url('order') . '?type=bot', (string) t('bots.cta_button'), 'accent') ?>
        <?= ui_link('#price', (string) t('bots.price_title'), 'ghost') ?>
      </div>
    </div>
    <?php $chat = $demo['chat']; $chatName = $demo['name']; $chatSmall = false; require __DIR__ . '/../partials/bot-chat.php' ?>
  </div>
</header>

<section class="section" aria-label="<?= e(t('bots.intro')) ?>">
  <div class="wrap">
    <p class="page-intro"><?= e(t('bots.intro')) ?></p>
    <div class="bfacts">
<?php foreach (t('bots.facts') as $f): ?>
      <div class="bfact"><p class="bfact__n"><?= e($f['n']) ?></p><p class="bfact__t"><?= e($f['t']) ?></p></div>
<?php endforeach ?>
    </div>
  </div>
</section>

<section id="features" class="section section--soft" aria-labelledby="what-title">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="h-lg" id="what-title"><?= e(t('bots.what_title')) ?></h2>
      <p class="lede"><?= e(t('bots.what_sub')) ?></p>
    </div>
    <div class="bento">
<?php foreach (t('bots.tabs') as $i => $g): $top = array_slice($g['items'], 0, 3); $rest = array_slice($g['items'], 3); ?>
      <article class="bento__card bento__card--<?= $i + 1 ?>">
        <p class="bento__top"><span><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?>/</span><span><?= e($g['label']) ?></span></p>
        <?php $chat = $g['chat']; $chatBare = true; require __DIR__ . '/../partials/bot-chat.php'; $chatBare = false ?>
        <h3 class="bento__title"><?= e($g['title']) ?></h3>
        <ul class="bento__list">
<?php foreach ($top as $f): ?>
          <li><?= e($f) ?></li>
<?php endforeach ?>
        </ul>
<?php if ($rest): ?>
        <details class="bento__more">
          <summary><?= e(t('bots.more')) ?> (<?= count($g['items']) ?>)</summary>
          <ul class="bento__list">
<?php foreach ($rest as $f): ?>
            <li><?= e($f) ?></li>
<?php endforeach ?>
          </ul>
        </details>
<?php endif ?>
      </article>
<?php endforeach ?>
    </div>
  </div>
</section>

<section id="price" class="section" aria-labelledby="price-title">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="h-lg" id="price-title"><?= e(t('bots.price_title')) ?></h2>
      <p class="lede"><?= e(t('bots.price_intro')) ?> <?php if ($vat !== ''): ?><?= e(legal_text('{vat}')) ?><?php else: ?><span class="placeholder"><?= e(t('vat.unset')) ?></span><?php endif ?></p>
    </div>
    <div class="scope">
<?php foreach (t('bots.price_cards') as $c): ?>
      <div class="scope__card<?= $c['id'] === 'dev' ? ' is-main' : '' ?>" data-bot-price="<?= e($c['id']) ?>">
        <h3 class="scope__name"><?= e($c['name']) ?></h3>
<?php if (isset($c['amount'])): ?>
        <p class="pkg__price"><?php if (isset($c['from'])): ?><span class="pkg__from"><?= e($c['from']) ?></span> <?php endif ?><span class="pkg__amount"><?= e($c['amount']) ?></span> <span class="pkg__cur"><?= e($c['currency']) ?></span> <span class="pkg__from">/ <?= e($c['per']) ?></span></p>
<?php endif ?>
<?php if (isset($c['billing'])): ?>
        <p class="bill"><?= e($c['billing']) ?></p>
<?php endif ?>
        <p class="pkg__note"><?= e($c['text']) ?></p>
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
  </div>
</section>

<section class="section section--soft" aria-labelledby="steps-title">
  <div class="wrap">
    <div class="sec-head"><h2 class="h-lg" id="steps-title"><?= e(t('bots.steps_title')) ?></h2></div>
    <div class="bsteps">
<?php foreach (t('bots.steps') as $i => $s): ?>
      <div class="bstep">
        <span class="bstep__n" aria-hidden="true"><?= $i + 1 ?></span>
        <h3 class="bstep__t"><?= e($s['title']) ?></h3>
        <p class="bstep__x"><?= e($s['text']) ?></p>
      </div>
<?php endforeach ?>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="fit-title">
  <div class="wrap bsplit">
    <div class="scope__card">
      <h2 class="scope__name" id="fit-title"><?= e(t('bots.fit_title')) ?></h2>
      <p class="pkg__note"><?= e(t('bots.fit_text')) ?></p>
    </div>
    <div class="scope__card">
      <h2 class="scope__name"><?= e(t('bots.next_title')) ?></h2>
      <p class="pkg__note"><?= e(t('bots.next_sub')) ?></p>
      <ul class="scope__list scope__list--next">
<?php foreach (t('bots.next') as $n): ?>
        <li><?= e($n) ?></li>
<?php endforeach ?>
      </ul>
    </div>
  </div>
</section>

<section id="faq" class="section section--soft" aria-labelledby="faq-title">
  <div class="wrap">
    <div class="sec-head"><h2 class="h-lg" id="faq-title"><?= e(t('bots.faq_title')) ?></h2></div>
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

<section class="section" aria-labelledby="cta-title">
  <div class="wrap">
    <div class="band">
      <div>
        <h2 class="band__title" id="cta-title"><?= e(t('bots.cta_title')) ?></h2>
        <p class="band__sub"><?= e(t('bots.cta_text')) ?></p>
      </div>
      <div class="band__actions">
        <?= ui_link(url('order') . '?type=bot', (string) t('bots.cta_button'), 'light') ?>
        <?= ui_link(url('pricing'), (string) t('bots.cta_secondary'), 'ghost .band__ghost') ?>
      </div>
    </div>
  </div>
</section>
