<?php /** Shared by the public and admin layouts: theme bootstrap, browser chrome colour, favicons, the site stylesheet (no bootstrap, no web fonts). */ ?>
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#efefef" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0c0d10" media="(prefers-color-scheme: dark)">
  <script src="<?= e(asset('theme-init.js')) ?>"></script>
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('site.css')) ?>">
