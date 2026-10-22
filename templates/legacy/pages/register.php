<?php /** @var array $old @var array $errors @var string $formError @var string $csrf */
$o = fn(string $k): string => (string) ($old[$k] ?? ''); ?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('auth.register_title')) ?></h1>
      <h2 class="row__sub"><?= e(t('auth.register_subtitle')) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-6 col-md-offset-3 col-sm-8 col-sm-offset-2">
<?php if ($formError || $errors): ?>
        <div class="form__alert" role="alert" tabindex="-1" data-form-alert><?= e(t('auth.form_errors.' . ($formError ?: 'fix'))) ?></div>
<?php endif ?>
        <form class="form auth-form" method="post" action="<?= e(url('register')) ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <div class="form__trap" aria-hidden="true">
            <label for="f-hp"><?= e(t('a11y.honeypot')) ?></label>
            <input type="text" id="f-hp" name="hp_site" value="" tabindex="-1" autocomplete="off">
          </div>
          <?php $req = true; $hint = null;
            $f = 'name'; $label = (string) t('auth.name'); $type = 'text'; $value = $o('name'); $auto = 'name'; require __DIR__ . '/../partials/auth-field.php';
            $f = 'email'; $label = (string) t('auth.email'); $type = 'email'; $value = $o('email'); $auto = 'email'; require __DIR__ . '/../partials/auth-field.php';
            $req = false; $f = 'company'; $label = (string) t('auth.company'); $type = 'text'; $value = $o('company'); $auto = 'organization'; require __DIR__ . '/../partials/auth-field.php';
            $f = 'phone'; $label = (string) t('auth.phone'); $type = 'tel'; $value = $o('phone'); $auto = 'tel'; require __DIR__ . '/../partials/auth-field.php';
            $req = true; $f = 'password'; $label = (string) t('auth.password'); $type = 'password'; $value = ''; $auto = 'new-password'; $hint = (string) t('auth.password_hint'); require __DIR__ . '/../partials/auth-field.php';
            $hint = null; $f = 'password2'; $label = (string) t('auth.password2'); require __DIR__ . '/../partials/auth-field.php'; ?>
          <div class="form__consent">
            <input type="checkbox" id="a-consent" name="consent" value="1"<?= isset($_POST['consent']) && $_POST['consent'] === '1' ? ' checked' : '' ?><?= isset($errors['consent']) ? ' aria-invalid="true" aria-describedby="e-consent"' : '' ?>>
            <label for="a-consent"><?= e(t('order.consent')) ?> <a href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener"><?= e(t('order.consent_link')) ?></a></label>
<?php if (isset($errors['consent'])): ?><p class="form__error" id="e-consent"><?= e(t('auth.errors.' . $errors['consent'])) ?></p><?php endif ?>
          </div>
          <div class="form__actions">
            <button class="btn btn--auto" type="submit"><?= e(t('auth.submit_register')) ?></button>
          </div>
        </form>
        <?php require __DIR__ . '/../partials/dreamers-button.php' ?>
        <p class="auth-form__switch"><?= e(t('auth.have_account')) ?> <a href="<?= e(url('login')) ?>"><?= e(t('auth.login_title')) ?></a></p>
      </div>
    </div>
  </div>
</div>
