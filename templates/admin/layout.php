<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Pixelite admin</title>
  <link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
</head>
<body class="admin">
<header class="admin__bar">
  <div class="container">
    <a class="header__logo" href="/admin"><img class="header__img" src="<?= e(asset('img/logo.png')) ?>" alt="" width="24" height="28"> <span class="header__title">PIXELITE<span class="header__light">.cz</span> <span class="header__light">admin</span></span></a>
<?php if (!empty($_SESSION['admin_at'])): ?>
    <form method="post" action="/admin/logout" class="admin__logout"><input type="hidden" name="_csrf" value="<?= e(\Pixelite\Csrf::token()) ?>"><button class="btn btn--revert btn--auto" type="submit">Sign out</button></form>
<?php endif ?>
  </div>
</header>
<main class="container admin__main">
<?= $content ?>
</main>
</body>
</html>
