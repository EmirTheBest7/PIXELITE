<?php
/** @var array $meta @var string $locale */
$cur = $meta['page'] === 'template' ? 'templates' : $meta['page'];
$builderOn = \Pixelite\Editor\Builder\Anon\AnonWorkspaces::offered();
$tpls = array_values(\Pixelite\TemplateCatalog::all());
$poster = static fn(int $i): string => isset($tpls[$i]) ? \Pixelite\TemplateCatalog::mediaUrl($tpls[$i], $tpls[$i]['media']['poster']['640']) : '';
$botDemo = t('bots.demo');
/** Menu model shared by the desktop morphing dropdown and the mobile sheet. Rows: [href, title lang key, description lang key, preview kind, page id]. */
$menu = [
    'services' => ['label' => 'nav.services', 'pages' => ['bots', 'templates', 'builder'], 'rows' => [
        [url('') . '#services', 'menu.websites', 'menu.d_websites', 'img0', 'websites'],
        [url('telegram-bots'), 'footer.bots', 'menu.d_bots', 'bot', 'bots'],
        ...($builderOn ? [[url('builder'), 'nav.builder', 'menu.d_builder', 'brand', 'builder']] : []),
        [url('templates'), 'nav.templates', 'menu.d_designs', 'img1', 'templates'],
    ]],
    'pricing' => ['label' => 'nav.pricing', 'pages' => ['pricing', 'process'], 'rows' => [
        [url('pricing'), 'nav.pricing', 'menu.d_pricing', 'g1', 'pricing'],
        [url('process'), 'nav.process', 'menu.d_process', 'g2', 'process'],
    ]],
    'company' => ['label' => 'menu.company', 'pages' => ['team', 'careers', 'privacy', 'terms'], 'rows' => [
        [url('team'), 'footer.team', '', '', 'team'],
        [url('careers'), 'footer.careers', '', '', 'careers'],
        [url('privacy'), 'footer.privacy', '', '', 'privacy'],
        [url('terms'), 'footer.terms', '', '', 'terms'],
    ]],
];
$chev = '<svg class="chev" width="8" height="5" viewBox="0 0 8 5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M1 1l3 3 3-3"/></svg>';
$href = fn(string $h): string => str_starts_with($h, '#') ? url('') . $h : url($h);
$brand = '<span class="pill__brand">pixelite<i>.cz</i></span>';
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
        <a class="pill__logo" href="<?= e(url('')) ?>" aria-label="<?= e(t('a11y.home')) ?>"><?= brand_mark(30) ?><?= $brand ?></a>
        <ul class="pill__links">
<?php foreach ($menu as $id => $g): ?>
          <li><button type="button" class="pill__link pill__link--menu<?= in_array($cur, $g['pages'], true) ? ' is-current' : '' ?>" data-menu="<?= $id ?>" aria-expanded="false" aria-haspopup="true" aria-controls="dd"><?= e(t($g['label'])) ?><?= $chev ?></button></li>
<?php endforeach ?>
          <li><a class="pill__link header__link" id="contact-link" href="<?= e(url('contact')) ?>"<?= $cur === 'contact' ? ' aria-current="page"' : '' ?>><?= e(t('nav.contact')) ?></a></li>
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
      <div class="dd" id="dd" aria-label="<?= e(t('a11y.nav_main')) ?>">
        <div class="dd__panel dd__panel--services" id="p-services" data-panel="services">
          <ul class="dd__list">
<?php foreach ($menu['services']['rows'] as $i => $r): ?>
            <li class="dd__row"><a class="dd__item<?= $i === 0 ? ' is-active' : '' ?>" data-img="<?= $i ?>" href="<?= e($r[0]) ?>"><strong><?= e(t($r[1])) ?></strong><span><?= e(t($r[2])) ?></span></a></li>
<?php endforeach ?>
          </ul>
          <div class="dd__media" aria-hidden="true">
<?php foreach ($menu['services']['rows'] as $i => $r): $k = $r[3]; ?>
<?php if ($k === 'img0' || $k === 'img1'): $src = $poster($k === 'img0' ? 0 : 1); ?>
            <div class="dd__art<?= $i === 0 ? ' is-active' : '' ?>"><img alt="" decoding="async" data-src="<?= e($src) ?>"></div>
<?php elseif ($k === 'bot'): $chat = array_slice($botDemo['chat'], 0, 3); $chatBare = true; ?>
            <div class="dd__art dd__art--grad dd__art--bot<?= $i === 0 ? ' is-active' : '' ?>"><?php require __DIR__ . '/bot-chat.php'; $chatBare = false ?></div>
<?php else: ?>
            <div class="dd__art dd__art--grad dd__art--brand<?= $i === 0 ? ' is-active' : '' ?>"><?= brand_mark(84) ?></div>
<?php endif ?>
<?php endforeach ?>
          </div>
        </div>
        <div class="dd__panel dd__panel--pricing" id="p-pricing" data-panel="pricing">
<?php foreach ($menu['pricing']['rows'] as $i => $r): ?>
          <a class="dd__card dd__card--<?= $i + 1 ?>" href="<?= e($r[0]) ?>"><strong><?= e(t($r[1])) ?></strong><span><?= e(t($r[2])) ?></span></a>
<?php endforeach ?>
        </div>
        <div class="dd__panel dd__panel--company" id="p-company" data-panel="company">
<?php foreach ($menu['company']['rows'] as $r): ?>
          <a class="dd__link" href="<?= e($r[0]) ?>"><?= e(t($r[1])) ?></a>
<?php endforeach ?>
        </div>
      </div>
    </nav>
  </div>
</header>
<div id="navbar" class="sheet" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?= e(t('a11y.nav_main')) ?>">
  <div class="sheet__backdrop" data-nav-close></div>
  <div class="sheet__panel">
    <div class="sheet__head">
      <a class="pill__logo" href="<?= e(url('')) ?>"><?= brand_mark(28) ?><?= $brand ?></a>
      <button type="button" class="sheet__close" data-nav-close aria-label="<?= e(t('consent.close')) ?>"><svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l12 12M15 3L3 15"/></svg></button>
    </div>
    <div class="sheet__groups">
<?php foreach ($menu as $g): ?>
      <section class="sheet__group">
        <h3 class="sheet__h"><?= e(t($g['label'])) ?></h3>
        <ul class="sheet__rows">
<?php foreach ($g['rows'] as $r): ?>
          <li><a class="sheet__row header__link" href="<?= e($r[0]) ?>"<?= $r[4] === $cur ? ' aria-current="page"' : '' ?>><strong><?= e(t($r[1])) ?></strong><?php if ($r[2] !== ''): ?><span><?= e(t($r[2])) ?></span><?php endif ?></a></li>
<?php endforeach ?>
        </ul>
      </section>
<?php endforeach ?>
      <ul class="sheet__rows sheet__rows--solo"><li><a class="sheet__row header__link" href="<?= e(url('contact')) ?>"<?= $cur === 'contact' ? ' aria-current="page"' : '' ?>><strong><?= e(t('nav.contact')) ?></strong></a></li></ul>
    </div>
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
