<?php /** Shared by the public and admin layouts: theme bootstrap, browser chrome colour, favicons, stylesheets. */ ?>
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#3a9fff" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0a0f1a" media="(prefers-color-scheme: dark)">
  <script src="<?= e(asset('theme-init.js')) ?>"></script>
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
