<?php
/** @var array $meta @var string $locale */
$cur = $meta['page'] === 'template' ? 'templates' : $meta['page'];
$nav = [   // [href, label key, page id for aria-current]
    ['', 'nav.home', 'home'],
    ['#services', 'nav.services', null],
    ['templates', 'nav.templates', 'templates'],
    ['pricing', 'nav.pricing', 'pricing'],
    ['process', 'nav.process', 'process'],
    ['contact', 'nav.contact', 'contact'],
];
$href = fn(string $h): string => str_starts_with($h, '#') ? url('') . $h : url($h);
$brand = '<span class="pill__brand">PIXELITE<i>.cz</i></span>';
$langs = function (string $cls) use ($meta, $locale): void { ?>
<div class="lang <?= $cls ?>" role="group" aria-label="<?= e(t('a11y.language')) ?>">
<?php foreach (\Pixelite\I18n::LOCALES as $loc): ?>
  <a class="lang__link<?= $loc === $locale ? ' is-active' : '' ?>" href="<?= e(url($meta['path'], $loc) . current_query()) ?>" hreflang="<?= $loc ?>" lang="<?= $loc ?>" data-lang="<?= $loc ?>"<?= $loc === $locale ? ' aria-current="true"' : '' ?>><?= strtoupper($loc) ?></a>
<?php endforeach ?>
</div>
<?php };
?>
<header class="site-header">
  <div class="wrap">
    <nav class="pill" aria-label="<?= e(t('a11y.nav_main')) ?>">
      <div class="pill__left">
        <a class="pill__logo" href="<?= e(url('')) ?>" aria-label="<?= e(t('a11y.home')) ?>"><img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="36" height="36"><?= $brand ?></a>
        <ul class="pill__links">
<?php foreach ($nav as [$h, $label, $id]): ?>
          <li><a class="pill__link header__link" href="<?= e($href($h)) ?>"<?= $id !== null && $id === $cur ? ' aria-current="page"' : '' ?>><?= e(t($label)) ?></a></li>
<?php endforeach ?>
          <li><a class="pill__link pill__link--version" href="<?= e(url_other_design($meta['path'])) ?>" rel="nofollow" data-design-switch><?= e(t('nav.legacy')) ?></a></li>
        </ul>
      </div>
      <div class="pill__right">
        <?php $langs('') ?>
        <?php require __DIR__ . '/theme-cycle.php' ?>
        <?= ui_link(url('order'), (string) t('nav.start'), 'sm accent .pill__cta') ?>
        <button type="button" class="pill__menu navbar-toggle collapsed" data-nav-toggle aria-expanded="false" aria-controls="navbar">
          <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M2 4.5h12M2 8h12M2 11.5h12"/></svg>
          <span class="sr-only"><?= e(t('a11y.toggle')) ?></span><span aria-hidden="true"><?= e(t('nav.menu')) ?></span>
        </button>
      </div>
    </nav>
  </div>
</header>
<div id="navbar" class="sheet" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?= e(t('a11y.nav_main')) ?>">
  <div class="sheet__backdrop" data-nav-close></div>
  <div class="sheet__panel">
    <div class="sheet__head">
      <a class="pill__logo" href="<?= e(url('')) ?>"><img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="32" height="32"><?= $brand ?></a>
      <button type="button" class="sheet__close" data-nav-close aria-label="<?= e(t('consent.close')) ?>"><svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l12 12M15 3L3 15"/></svg></button>
    </div>
    <ul class="sheet__nav">
<?php foreach ($nav as [$h, $label, $id]): ?>
      <li><a class="sheet__link header__link" href="<?= e($href($h)) ?>"<?= $id !== null && $id === $cur ? ' aria-current="page"' : '' ?>><?= e(t($label)) ?></a></li>
<?php endforeach ?>
    </ul>
    <p class="sheet__version"><a href="<?= e(url_other_design($meta['path'])) ?>" rel="nofollow" data-design-switch><?= e(t('nav.legacy')) ?></a></p>
    <div class="sheet__foot">
      <?= ui_link(url('order'), (string) t('nav.start'), 'accent block') ?>
      <div class="sheet__tools">
        <?php $langs('') ?>
        <?php require __DIR__ . '/theme-switch.php' ?>
      </div>
    </div>
  </div>
</div>
