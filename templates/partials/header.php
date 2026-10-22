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
$flags = ['en' => "\u{1F1EC}\u{1F1E7}", 'cs' => "\u{1F1E8}\u{1F1FF}", 'ru' => "\u{1F1F7}\u{1F1FA}", 'uk' => "\u{1F1FA}\u{1F1E6}"];   // emoji flags: decorative, the language name is always in the text
$langLink = function (string $loc, bool $named) use ($meta, $locale, $flags): void { ?>
<a class="lang__link<?= $loc === $locale ? ' is-active' : '' ?>" href="<?= e(url($meta['path'], $loc) . current_query()) ?>" hreflang="<?= $loc ?>" lang="<?= $loc ?>" data-lang="<?= $loc ?>"<?= $loc === $locale ? ' aria-current="true"' : '' ?>><span class="lang__flag" aria-hidden="true"><?= $flags[$loc] ?></span><span><?= $named ? e(\Pixelite\I18n::NAMES[$loc]) : strtoupper($loc) ?></span></a>
<?php };
/** $dropdown: compact picker (desktop pill); otherwise an inline row (mobile menu). */
$langs = function (bool $dropdown) use ($locale, $flags, $langLink): void {
    if ($dropdown): ?>
<details class="lang-dd" data-lang-dd>
  <summary class="lang-dd__btn" aria-label="<?= e(t('a11y.language')) ?>: <?= e(\Pixelite\I18n::NAMES[$locale]) ?>"><span class="lang__flag" aria-hidden="true"><?= $flags[$locale] ?></span><span><?= strtoupper($locale) ?></span><svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3.5 5 6.5 8 3.5"/></svg></summary>
  <div class="lang-dd__menu" role="group" aria-label="<?= e(t('a11y.language')) ?>">
<?php foreach (\Pixelite\I18n::LOCALES as $loc) { $langLink($loc, true); } ?>
  </div>
</details>
<?php else: ?>
<div class="lang" role="group" aria-label="<?= e(t('a11y.language')) ?>">
<?php foreach (\Pixelite\I18n::LOCALES as $loc) { $langLink($loc, false); } ?>
</div>
<?php endif;
};
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
        </ul>
      </div>
      <div class="pill__right">
        <?php $langs(true) ?>
        <?php require __DIR__ . '/theme-cycle.php' ?>
        <?= ui_link(url('login'), (string) t('nav.login'), 'sm ghost plain .pill__login', ['rel' => 'nofollow']) ?>
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
    <ul class="sheet__nav sheet__nav--account">
      <li><a class="sheet__link" href="<?= e(url('login')) ?>" rel="nofollow"><?= e(t('nav.login')) ?></a></li>
      <li><a class="sheet__link" href="<?= e(url('register')) ?>" rel="nofollow"><?= e(t('auth.register_title')) ?></a></li>
    </ul>
    <div class="sheet__foot">
      <?= ui_link(url('order'), (string) t('nav.start'), 'accent block') ?>
      <div class="sheet__tools">
        <?php $langs(false) ?>
        <?php require __DIR__ . '/theme-switch.php' ?>
      </div>
    </div>
  </div>
</div>
