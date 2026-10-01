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
          <img class="site__img" src="<?= e(asset('img/hero.png')) ?>" alt="" width="907" height="503">
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
    <div class="row row--center row--margin">
<?php $skin = ['purple', 'violet', 'blue']; foreach (t('services.items') as $i => $s): $c = $skin[$i]; ?>
      <div class="col-md-4 col-sm-4 price-box price-box--<?= $c ?>">
        <div class="price-box__wrap">
          <div class="price-box__img"></div>
          <h3 class="price-box__title"><?= e($s['name']) ?></h3>
          <p class="price-box__people"><?= e($s['tagline']) ?></p>
          <h4 class="price-box__discount"><span class="price-box__discount--light"><?= e(t('services.price')) ?></span></h4>
          <p class="price-box__feat"><?= e(t('services.included')) ?></p>
          <ul class="price-box__list">
<?php foreach ($s['features'] as $f): ?>
            <li class="price-box__list-el"><?= e($f) ?></li>
<?php endforeach ?>
          </ul>
          <div class="price-box__btn">
            <a class="btn btn--<?= $c ?> btn--width" href="<?= e(url('order') . '?type=' . $s['type']) ?>"><?= e(t('services.cta')) ?></a>
          </div>
        </div>
      </div>
<?php endforeach ?>
    </div>
    <p class="services__more"><?= e(t('services.more')) ?> <a href="<?= e(url('order')) ?>"><?= e(t('services.more_link')) ?> →</a></p>
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
