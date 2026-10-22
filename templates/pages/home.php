<?php require_once __DIR__ . '/../partials/ui.php';
$featured = array_slice(\Pixelite\TemplateCatalog::search([])['items'] ?? [], 0, 3); ?>
<section class="hero" id="top" aria-labelledby="hero-title">
  <canvas id="hero-canvas" class="hero__canvas" aria-hidden="true"></canvas>
  <div class="wrap">
    <div class="hero__grid">
      <div class="hero__copy">
        <h1 class="hero__title" id="hero-title"><?= e(t('hero.title')) ?></h1>
        <p class="hero__sub"><?= e(t('hero.subtitle')) ?></p>
        <div class="hero__cta">
          <?= ui_link(url('order'), (string) t('hero.cta'), 'accent') ?>
          <?= ui_link('#services', (string) t('hero.secondary'), 'ghost') ?>
        </div>
      </div>
      <div class="hero__stage">
        <div class="hero-laptop" aria-hidden="true">
          <div class="laptop"><div class="lp-tilt">
            <div class="lp-shadow"></div>
            <div class="lp-lid"><div class="lp-front"><span class="lp-cam"></span>
              <div class="lp-screen"><div class="lp-page">
                <div class="lp-nav"><b class="lp-logo"></b><span></span><span></span><span></span><em class="lp-btn"></em></div>
                <div class="lp-copy"><span class="lp-h"></span><span class="lp-h"></span><span class="lp-p"></span>
                  <span class="lp-btns"><em class="lp-pill"></em><em class="lp-pill lp-pill--ghost"></em></span></div>
                <div class="lp-cards">
                  <div class="lp-card"><i></i><span></span><span></span></div>
                  <div class="lp-card"><i></i><span></span><span></span></div>
                  <div class="lp-card"><i></i><span></span><span></span></div>
                </div>
              </div></div>
            </div></div>
            <div class="lp-base"></div>
          </div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="services" class="section" aria-labelledby="services-title">
  <div class="wrap">
    <div class="sec-head">
      <?= ui_eyebrow('01', (string) t('nav.services')) ?>
      <h2 class="h-lg" id="services-title"><?= e(t('services.title')) ?></h2>
      <p class="lede"><?= e(t('services.subtitle')) ?></p>
    </div>
    <?php require __DIR__ . '/../partials/package-cards.php' ?>
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
