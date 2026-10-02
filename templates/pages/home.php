<div class="sect sect--padding-top">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <div class="site">
          <h1 class="site__title"><?= e(t('hero.title')) ?></h1>
          <h2 class="site__subtitle"><?= e(t('hero.subtitle')) ?></h2>
          <div class="site__box-link">
            <a class="btn btn--auto" href="<?= e(url('order')) ?>"><?= e(t('hero.cta')) ?></a>
            <a class="btn btn--revert btn--auto" href="#services"><?= e(t('hero.secondary')) ?></a>
          </div>
          <div class="hero-laptop" aria-hidden="true">
            <div class="laptop">
              <div class="lp-lid">
                <div class="lp-front">
                  <div class="lp-screen">
                    <div class="lp-bar"><i></i><i></i><i></i><span class="lp-url"></span></div>
                    <div class="lp-page">
                      <div class="lp-nav"><b class="lp-logo"></b><span></span><span></span><span></span><em class="lp-pill"></em></div>
                      <div class="lp-copy"><span class="lp-h1"></span><span class="lp-h2"></span><span class="lp-btns"><em class="lp-pill lp-pill--lg"></em><em class="lp-pill lp-pill--ghost"></em></span></div>
                      <div class="lp-cards">
                        <div class="lp-card lp-card--purple"><i></i><span></span><span></span></div>
                        <div class="lp-card lp-card--violet"><i></i><span></span><span></span></div>
                        <div class="lp-card lp-card--blue"><i></i><span></span><span></span></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="lp-base"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<section id="services" class="sect sect--padding-bottom" aria-labelledby="services-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="services-title"><?= e(t('services.title')) ?></h2>
      <h3 class="row__sub"><?= e(t('services.subtitle')) ?></h3>
    </div>
    <?php require __DIR__ . '/../partials/package-cards.php' ?>

    <?php require __DIR__ . '/../partials/credits.php' ?>

    <p class="more-link"><a class="btn btn--revert btn--auto" href="<?= e(url('pricing')) ?>"><?= e(t('home_more.compare')) ?></a></p>
  </div>
</section>

<section id="consultation" class="sect sect--no-padding consult" aria-labelledby="consult-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="consult-title"><?= e(t('consultation.title')) ?></h2>
    </div>
    <p class="consult__text"><?= e(t('consultation.text')) ?></p>
    <p class="consult__cta"><a class="btn btn--strong btn--auto" href="<?= e(url('order')) ?>"><?= e(t('consultation.cta')) ?></a></p>
  </div>
</section>

<section id="how" class="sect sect--no-padding sect--padding-bottom" aria-labelledby="how-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="how-title"><?= e(t('home_more.process_title')) ?></h2>
      <h3 class="row__sub"><?= e(t('home_more.process_sub')) ?></h3>
    </div>
    <div class="steps-wrap">
      <?php $stepsCompact = true; require __DIR__ . '/../partials/steps.php' ?>
    </div>
    <p class="more-link"><a class="btn btn--revert btn--auto" href="<?= e(url('process')) ?>"><?= e(t('home_more.process_cta')) ?></a></p>
  </div>
</section>

<section id="faq" class="sect sect--no-padding sect--padding-bottom" aria-labelledby="faq-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="faq-title"><?= e(t('faq.home_title')) ?></h2>
    </div>
    <?php $faqIds = ['price', 'binding', 'help']; require __DIR__ . '/../partials/faq.php' ?>
    <p class="more-link"><a class="btn btn--revert btn--auto" href="<?= e(url('pricing')) ?>#faq"><?= e(t('faq.more')) ?></a></p>
  </div>
</section>

<section class="sect sect--padding-bottom" aria-labelledby="contact-title">
  <div class="container">
    <div class="row">
      <h2 class="row__title" id="contact-title"><?= e(t('contact.title')) ?></h2>
      <h3 class="row__sub"><?= e(t('contact.subtitle')) ?></h3>
    </div>
    <div class="row row--margin">
      <div class="col-md-1"></div>
      <div class="col-md-4"><?php require __DIR__ . '/../partials/contact-details.php' ?></div>
      <div class="col-md-6">
        <div class="cta-card">
          <h3 class="cta-card__title"><?= e(t('contact.cta_title')) ?></h3>
          <p class="cta-card__text"><?= e(t('contact.cta_text')) ?></p>
          <a class="btn btn--auto" href="<?= e(url('order')) ?>"><?= e(t('contact.cta_button')) ?></a>
        </div>
      </div>
      <div class="col-md-1"></div>
    </div>
  </div>
</section>

<section class="sect sect--violet">
  <img src="<?= e(asset('img/band.png')) ?>" class="career-img" alt="">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h2 class="career_title"><?= e(t('band.title')) ?></h2>
        <h3 class="career_sub"><?= e(t('band.subtitle')) ?></h3>
        <a href="<?= e(url('order')) ?>" class="btn btn--white btn--auto"><?= e(t('band.button')) ?></a>
      </div>
    </div>
  </div>
</section>
