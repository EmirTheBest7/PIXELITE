<?php require_once __DIR__ . '/../partials/ui.php'; $adminPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH); ?>
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
  <div class="wrap">
    <a class="admin__brand" href="/admin"><?= brand_mark(28) ?><span>pixelite<i>.cz admin</i></span></a>
<?php if (!empty($_SESSION['admin_at'])): ?>
    <nav class="admin__nav" aria-label="Admin">
      <a href="/admin"<?= $adminPath === '/admin' || str_starts_with($adminPath, '/admin/orders') ? ' aria-current="page"' : '' ?>>Orders</a>
      <a href="/admin/customers"<?= str_starts_with($adminPath, '/admin/customers') ? ' aria-current="page"' : '' ?>>Customers</a>
      <a href="/admin/projects"<?= str_starts_with($adminPath, '/admin/projects') ? ' aria-current="page"' : '' ?>>Projects</a>
      <a href="/admin/careers"<?= str_starts_with($adminPath, '/admin/careers') ? ' aria-current="page"' : '' ?>>Careers</a>
      <a href="/admin/templates"<?= str_starts_with($adminPath, '/admin/templates') ? ' aria-current="page"' : '' ?>>Design prompts</a>
    </nav>
    <form method="post" action="/admin/logout" class="admin__logout"><input type="hidden" name="_csrf" value="<?= e(\Pixelite\Csrf::token()) ?>"><?= ui_button('Sign out', 'sm ghost plain', ['type' => 'submit']) ?></form>
<?php endif ?>
  </div>
</header>
<main class="wrap admin__main">
<?= $content ?>
</main>
<script src="<?= e(asset('site-theme.js')) ?>" defer></script>
<script src="<?= e(asset('admin.js')) ?>" defer></script>
<script src="<?= e(asset('endora-banner.js')) ?>" defer></script>
</body>
</html>
