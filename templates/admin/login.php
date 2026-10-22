<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<div class="admin-login__card">
  <h1 class="admin__title">Sign in</h1>
<?php if (!$enabled): ?>
  <div class="form__alert" role="alert">Admin login is not configured. Set ADMIN_EMAIL and ADMIN_PASSWORD_HASH in .env (see CLAUDE.md).</div>
<?php elseif ($error): ?>
  <div class="form__alert" role="alert"><?= e($error) ?></div>
<?php endif ?>
  <form method="post" action="/admin/login" class="form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="form__cell"><label class="form__label" for="a-email">Email</label><input class="form__field form__text" type="email" id="a-email" name="email" value="<?= e($email) ?>" required autocomplete="username"></div>
    <div class="form__cell"><label class="form__label" for="a-pass">Password</label><input class="form__field form__text" type="password" id="a-pass" name="password" required autocomplete="current-password"></div>
    <div class="form__actions"><?= ui_button('Sign in', 'accent block', ['type' => 'submit']) ?></div>
  </form>
</div>
