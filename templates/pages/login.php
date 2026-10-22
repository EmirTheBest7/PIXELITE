<?php /** @var string $email @var array $errors @var string $formError @var string $csrf @var string $flash */
require_once __DIR__ . '/../partials/ui.php';
$ph = ['label' => (string) t('nav.login'), 'title' => (string) t('auth.login_title'), 'sub' => (string) t('auth.login_subtitle')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-label="<?= e(t('auth.login_title')) ?>">
  <div class="wrap"><div class="auth-wrap">
<?php if ($flash !== ''): ?><div class="form__alert form__alert--ok" role="status"><?= e(t('auth.flash.' . $flash)) ?></div><?php endif ?>
<?php if ($formError): ?><div class="form__alert" role="alert" tabindex="-1" data-form-alert><?= e(t('auth.form_errors.' . $formError)) ?></div><?php endif ?>
    <form class="form" method="post" action="<?= e(url('login')) ?>" novalidate>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <?php $errors = []; $hint = null; $req = true;
        $f = 'email'; $label = (string) t('auth.email'); $type = 'email'; $value = $email; $auto = 'username'; require __DIR__ . '/../partials/auth-field.php';
        $f = 'password'; $label = (string) t('auth.password'); $type = 'password'; $value = ''; $auto = 'current-password'; require __DIR__ . '/../partials/auth-field.php'; ?>
      <div class="form__actions"><?= ui_button((string) t('auth.submit_login'), 'accent', ['type' => 'submit']) ?></div>
      <p class="auth-note"><?= e(t('auth.reset_note')) ?></p>
    </form>
    <?php require __DIR__ . '/../partials/dreamers-button.php' ?>
    <p class="auth-switch"><?= e(t('auth.no_account')) ?> <a class="link" href="<?= e(url('register')) ?>"><?= e(t('auth.register_title')) ?></a></p>
  </div></div>
</section>
