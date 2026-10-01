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

    <div class="credits">
      <svg class="credits__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
      <div class="credits__body">
        <p class="credits__badge"><?= e(t('credits.badge')) ?></p>
        <p class="credits__text"><?= e(t('credits.text')) ?></p>
        <p class="credits__note"><?= e(t('credits.note')) ?> <a href="<?= e(url('terms') . '#dreamers') ?>"><?= e(t('credits.link')) ?></a></p>
      </div>
    </div>
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
