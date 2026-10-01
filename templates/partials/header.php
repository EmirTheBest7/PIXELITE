<?php
/** @var array $meta @var string $locale */
$nav = [
    ['', 'nav.home'],
    ['#services', 'nav.services'],
    ['contact', 'nav.contact'],
];
?>
<header class="header">
  <div class="container header__container">
    <a class="header__logo" href="<?= e(url('')) ?>" aria-label="<?= e(t('a11y.home')) ?>"><img class="header__img" src="<?= e(asset('img/logo.png')) ?>" alt="" width="24" height="28"> <span class="header__title">PIXELITE<span class="header__light">.cz</span></span></a>
    <button type="button" class="navbar-toggle collapsed" data-nav-toggle aria-expanded="false" aria-controls="navbar">
      <span class="sr-only"><?= e(t('a11y.toggle')) ?></span>
      <span class="icon-bar"></span>
      <span class="icon-bar"></span>
      <span class="icon-bar"></span>
    </button>
    <div class="lang" role="group" aria-label="<?= e(t('a11y.language')) ?>">
<?php foreach (\Pixelite\I18n::LOCALES as $loc): ?>
      <a class="lang__link<?= $loc === $locale ? ' is-active' : '' ?>" href="<?= e(url($meta['path'], $loc)) ?>" hreflang="<?= $loc ?>" lang="<?= $loc ?>" data-lang="<?= $loc ?>"<?= $loc === $locale ? ' aria-current="true"' : '' ?>><?= strtoupper($loc) ?></a>
<?php endforeach ?>
    </div>
    <div class="header__menu">
      <nav id="navbar" class="header__nav collapse" aria-label="<?= e(t('a11y.nav_main')) ?>">
        <ul class="header__elenco">
<?php foreach ($nav as [$href, $label]): ?>
          <li class="header__el"><a href="<?= e(str_starts_with($href, '#') ? url('') . $href : url($href)) ?>" class="header__link"><?= e(t($label)) ?></a></li>
<?php endforeach ?>
          <li class="header__el header__el--blue"><a href="<?= e(url('order')) ?>" class="btn btn--white"><?= e(t('nav.start')) ?></a></li>
        </ul>
      </nav>
    </div>
  </div>
</header>
