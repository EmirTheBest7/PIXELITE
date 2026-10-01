<?php
/** @var array $meta @var string $locale @var string $content */
$canonical = absolute_url(url($meta['path']));
$isHome = $meta['page'] === 'home';
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($meta['title']) ?></title>
  <?php if ($meta['description'] !== ''): ?><meta name="description" content="<?= e($meta['description']) ?>"><?php endif ?>

  <meta name="robots" content="<?= e($meta['robots']) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">
<?php if (!in_array($meta['page'], ['404', '500'], true)): foreach (\Pixelite\I18n::LOCALES as $loc): ?>
  <link rel="alternate" hreflang="<?= $loc ?>" href="<?= e(absolute_url(url($meta['path'], $loc))) ?>">
<?php endforeach ?>
  <link rel="alternate" hreflang="x-default" href="<?= e(absolute_url(url($meta['path'], \Pixelite\I18n::default()))) ?>">
<?php endif ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Pixelite.cz">
  <meta property="og:title" content="<?= e($meta['title']) ?>">
  <meta property="og:description" content="<?= e($meta['description']) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:locale" content="<?= $locale === 'cs' ? 'cs_CZ' : 'en_US' ?>">
  <meta property="og:image" content="<?= e(absolute_url('/assets/img/hero.png')) ?>">
  <meta name="twitter:card" content="summary_large_image">
<?php require __DIR__ . '/partials/head-assets.php' ?>
<?php if ($isHome): $c = contact(); ?>
  <script type="application/ld+json"><?= json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Organization',
      'name' => 'Pixelite.cz',
      'url' => absolute_url('/'),
      'logo' => absolute_url('/assets/icons/apple-touch-icon.png'),
      'description' => $meta['description'],
      'email' => $c['email'] ?: null,
      'telephone' => $c['phone'] ?: null,
  ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif ?>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('a11y.skip')) ?></a>
<?php require __DIR__ . '/partials/consent.php' ?>
<?php require __DIR__ . '/partials/header.php' ?>
<main id="main" tabindex="-1">
<?= $content ?>
</main>
<?php require __DIR__ . '/partials/footer.php' ?>
<script src="<?= e(asset('script.js')) ?>" defer></script>
<script src="<?= e(asset('theme.js')) ?>" defer></script>
<script src="<?= e(asset('consent.js')) ?>" defer></script>
</body>
</html>
