<?php /** Shared by the public and admin layouts: theme bootstrap, browser chrome colour, favicons, the site stylesheet (no bootstrap, no web fonts). */ ?>
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#efefef" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0c0d10" media="(prefers-color-scheme: dark)">
  <script src="<?= e(asset('theme-init.js')) ?>"></script>
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
  <meta name="apple-mobile-web-app-title" content="Pixelite">
  <link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
  <link rel="preload" href="/assets/fonts/onest/onest-latin.woff2" as="font" type="font/woff2" crossorigin>
<?php if (in_array(\Pixelite\I18n::locale(), ['ru', 'uk'], true)): ?>
  <link rel="preload" href="/assets/fonts/onest/onest-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
<?php endif ?>
  <link rel="stylesheet" href="<?= e(asset('site.css')) ?>">
