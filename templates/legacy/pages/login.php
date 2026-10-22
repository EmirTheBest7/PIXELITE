<?php /** @var string $email @var array $errors @var string $formError @var string $csrf @var string $flash */ ?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('auth.login_title')) ?></h1>
      <h2 class="row__sub"><?= e(t('auth.login_subtitle')) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-6 col-md-offset-3 col-sm-8 col-sm-offset-2">
<?php if ($flash !== ''): ?>
        <div class="form__alert form__alert--ok" role="status"><?= e(t('auth.flash.' . $flash)) ?></div>
<?php endif ?>
<?php if ($formError): ?>
        <div class="form__alert" role="alert" tabindex="-1" data-form-alert><?= e(t('auth.form_errors.' . $formError)) ?></div>
<?php endif ?>
        <form class="form auth-form" method="post" action="<?= e(url('login')) ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <?php $f = 'email'; $label = (string) t('auth.email'); $type = 'email'; $value = $email; $auto = 'username'; $hint = null; $req = true; $errors = []; require __DIR__ . '/../partials/auth-field.php' ?>
          <?php $f = 'password'; $label = (string) t('auth.password'); $type = 'password'; $value = ''; $auto = 'current-password'; require __DIR__ . '/../partials/auth-field.php' ?>
          <div class="form__actions">
            <button class="btn btn--auto" type="submit"><?= e(t('auth.submit_login')) ?></button>
          </div>
          <p class="auth-form__note"><?= e(t('auth.reset_note')) ?></p>
        </form>
        <?php require __DIR__ . '/../partials/dreamers-button.php' ?>
        <p class="auth-form__switch"><?= e(t('auth.no_account')) ?> <a href="<?= e(url('register')) ?>"><?= e(t('auth.register_title')) ?></a></p>
      </div>
    </div>
  </div>
</div>
