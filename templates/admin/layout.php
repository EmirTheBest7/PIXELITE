<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Pixelite admin</title>
<?php require __DIR__ . '/../partials/head-assets.php' ?>
</head>
<body class="admin">
<header class="admin__bar">
  <div class="container">
    <a class="header__logo" href="/admin"><?= logo_mark('header__img') ?> <span class="header__title">PIXELITE<span class="header__light">.cz</span> <span class="header__light">admin</span></span></a>
<?php if (!empty($_SESSION['admin_at'])): ?>
    <form method="post" action="/admin/logout" class="admin__logout"><input type="hidden" name="_csrf" value="<?= e(\Pixelite\Csrf::token()) ?>"><button class="btn btn--revert btn--auto" type="submit">Sign out</button></form>
<?php endif ?>
  </div>
</header>
<main class="container admin__main">
<?= $content ?>
</main>
<script src="<?= e(asset('theme.js')) ?>" defer></script>
</body>
</html>
