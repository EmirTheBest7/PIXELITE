<div class="row">
  <div class="col-sm-6 col-sm-offset-3 col-md-4 col-md-offset-4">
    <h1 class="row__title">Sign in</h1>
<?php if (!$enabled): ?>
    <div class="form__alert" role="alert">Admin login is not configured. Set ADMIN_EMAIL and ADMIN_PASSWORD_HASH in .env (see CLAUDE.md).</div>
<?php elseif ($error): ?>
    <div class="form__alert" role="alert"><?= e($error) ?></div>
<?php endif ?>
    <form method="post" action="/admin/login" class="form row--margin">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="form-group"><div class="form__field--half-wrap"><label class="sr-only" for="a-email">Email</label><input class="form__field form__text" type="email" id="a-email" name="email" placeholder="Email" value="<?= e($email) ?>" required autocomplete="username"></div></div>
      <div class="form-group"><div class="form__field--half-wrap"><label class="sr-only" for="a-pass">Password</label><input class="form__field form__text" type="password" id="a-pass" name="password" placeholder="Password" required autocomplete="current-password"></div></div>
      <div class="form__actions"><button class="btn btn--auto" type="submit">Sign in</button></div>
    </form>
  </div>
</div>
