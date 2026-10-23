<?php require_once __DIR__ . '/../partials/ui.php';
$featured = array_slice(\Pixelite\TemplateCatalog::search([])['items'] ?? [], 0, 3); ?>
<?php /* centred hero with an auto-rotating fan of the real designs */
$fan = \Pixelite\TemplateCatalog::search([])['items'] ?? []; $n = count($fan); $first = (t('services.items'))[0] ?? null; ?>
<section class="hero hero--center" id="top" aria-labelledby="hero-title">
  <canvas id="hero-canvas" class="hero__canvas" aria-hidden="true"></canvas>
  <div class="wrap">
    <div class="hero__copy">
<?php if ($first): ?>
      <a class="hero__pill" href="#services"><span class="hero__pill-dot" aria-hidden="true"></span><span><?= e($first['name']) ?></span><b><?= e($first['from']) ?> <?= e($first['amount']) ?> <?= e($first['currency']) ?></b></a>
<?php endif ?>
      <h1 class="hero__title" id="hero-title"><?= e(t('hero.title')) ?></h1>
      <p class="hero__sub"><?= e(t('hero.subtitle')) ?></p>
      <div class="hero__cta">
        <?= ui_link(url('order'), (string) t('hero.cta'), 'accent') ?>
        <?= ui_link(url('templates'), (string) t('hero.view_designs'), 'ghost') ?>
      </div>
    </div>
<?php if ($n > 0): ?>
    <p class="fan__label"><span class="fan__label-dot" aria-hidden="true"></span><?= e(t('hero.fan_label')) ?></p>
    <div class="fan" data-fan role="group" aria-roledescription="carousel" aria-label="<?= e(t('hero.fan_aria')) ?>">
      <ul class="fan__track">
<?php foreach ($fan as $i => $t): $pos = (($i + intdiv($n, 2)) % $n) - intdiv($n, 2); ?>
        <li class="fan__card" data-pos="<?= $pos ?>">
          <a href="<?= e(url('templates/' . $t['slug'])) ?>" aria-label="<?= e($t['title'] . ' – ' . t('templates.view')) ?>">
            <img src="<?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $t['media']['poster']['1280'])) ?>" srcset="<?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $t['media']['poster']['640'])) ?> 640w, <?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $t['media']['poster']['1280'])) ?> 1280w" sizes="(min-width:900px) 640px, 80vw" width="1280" height="800" alt="" <?= $pos === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            <span class="fan__name"><?= e($t['title']) ?></span>
          </a>
        </li>
<?php endforeach ?>
      </ul>
    </div>
<?php endif ?>
  </div>
</section>

<section id="services" class="section" aria-labelledby="services-title">
  <div class="wrap">
    <div class="sec-head">
      <?= ui_eyebrow('01', (string) t('nav.services')) ?>
      <h2 class="h-lg" id="services-title"><?= e(t('services.title')) ?></h2>
      <p class="lede"><?= e(t('services.subtitle')) ?></p>
    </div>
    <?php require __DIR__ . '/../partials/package-list.php' ?>
    <?php require __DIR__ . '/../partials/credits.php' ?>
    <p class="more"><?= ui_link(url('pricing'), (string) t('home_more.compare'), 'ghost') ?></p>
  </div>
</section>
<?php if ($featured): ?>

<section id="designs" class="section section--soft" aria-labelledby="designs-title">
  <div class="wrap">
    <div class="sec-head">
      <?= ui_eyebrow('02', (string) t('nav.templates')) ?>
      <h2 class="h-lg" id="designs-title"><?= e(t('templates.hero_title')) ?></h2>
      <p class="lede"><?= e(t('templates.hero_sub')) ?></p>
    </div>
    <div class="tgrid">
<?php foreach ($featured as $t): require __DIR__ . '/../partials/template-card.php'; endforeach ?>
    </div>
    <p class="more"><?= ui_link(url('templates'), (string) t('nav.templates'), 'ghost') ?></p>
  </div>
</section>
<?php endif ?>

<section id="consultation" class="section" aria-labelledby="consult-title">
  <div class="wrap">
    <div class="consult">
      <div>
        <h2 class="h-lg" id="consult-title"><?= e(t('consultation.title')) ?></h2>
        <p class="lede"><?= e(t('consultation.text')) ?></p>
      </div>
      <?= ui_link(url('order'), (string) t('consultation.cta'), 'accent') ?>
    </div>
  </div>
</section>

<section id="how" class="section section--soft" aria-labelledby="how-title">
  <div class="wrap">
    <div class="sec-head">
      <?= ui_eyebrow('03', (string) t('nav.process')) ?>
      <h2 class="h-lg" id="how-title"><?= e(t('home_more.process_title')) ?></h2>
      <p class="lede"><?= e(t('home_more.process_sub')) ?></p>
    </div>
    <?php $stepsCompact = true; require __DIR__ . '/../partials/steps.php' ?>
    <p class="more"><?= ui_link(url('process'), (string) t('home_more.process_cta'), 'ghost') ?></p>
  </div>
</section>

<section id="faq" class="section" aria-labelledby="faq-title">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="h-lg" id="faq-title"><?= e(t('faq.home_title')) ?></h2>
    </div>
    <?php $faqIds = ['price', 'binding', 'help']; require __DIR__ . '/../partials/faq.php' ?>
    <p class="more"><?= ui_link(url('pricing') . '#faq', (string) t('faq.more'), 'ghost') ?></p>
  </div>
</section>

<section class="section section--soft" aria-labelledby="contact-title">
  <div class="wrap">
    <div class="sec-head">
      <?= ui_eyebrow('04', (string) t('nav.contact')) ?>
      <h2 class="h-lg" id="contact-title"><?= e(t('contact.title')) ?></h2>
      <p class="lede"><?= e(t('contact.subtitle')) ?></p>
    </div>
    <div class="contact-grid">
      <?php require __DIR__ . '/../partials/contact-details.php' ?>
      <div class="cta-card">
        <h3 class="h-md"><?= e(t('contact.cta_title')) ?></h3>
        <p class="cta-card__text"><?= e(t('contact.cta_text')) ?></p>
        <?= ui_link(url('order'), (string) t('contact.cta_button'), 'accent') ?>
      </div>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="band-title">
  <div class="wrap">
    <div class="band">
      <div>
        <h2 class="band__title" id="band-title"><?= e(t('band.title')) ?></h2>
        <p class="band__sub"><?= e(t('band.subtitle')) ?></p>
      </div>
      <div class="band__actions"><?= ui_link(url('order'), (string) t('band.button'), 'light') ?></div>
    </div>
  </div>
</section>
