<?php /** @var array $c @var list<array> $orders @var list<array> $projects @var array $errors @var string $formError @var string $csrf @var string $flash */
$err = fn(string $k): string => isset($errors[$k]) ? (string) t('auth.errors.' . $errors[$k]) : '';
$inv = fn(string $k): string => $err($k) !== '' ? ' is-invalid" aria-invalid="true" aria-describedby="e-' . $k : '';
?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('account.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('account.hello', ['email' => $c['email']])) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-8 col-md-offset-2">
<?php if ($flash !== ''): ?>
        <div class="form__alert form__alert--ok" role="status"><?= e(t('auth.flash.' . $flash)) ?></div>
<?php endif ?>
<?php if ($formError || $errors): ?>
        <div class="form__alert" role="alert" tabindex="-1" data-form-alert><?= e(t('auth.form_errors.' . ($formError ?: 'fix'))) ?></div>
<?php endif ?>
        <section class="acct-card" aria-labelledby="acct-profile">
          <h3 id="acct-profile" class="acct-card__title"><?= e(t('account.profile')) ?></h3>
          <form class="form" method="post" action="<?= e(url('account')) ?>" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php foreach ([['name', 'auth.name', 'text', 'name', true], ['company', 'auth.company', 'text', 'organization', false], ['phone', 'auth.phone', 'tel', 'tel', false]] as [$k, $lab, $type, $auto, $req]): ?>
            <div class="form-group auth-field">
              <label class="auth-field__label" for="p-<?= $k ?>"><?= e(t($lab)) ?></label>
              <input class="form__field form__text<?= $inv($k) ?>" type="<?= $type ?>" id="p-<?= $k ?>" name="<?= $k ?>" value="<?= e($c[$k] ?? '') ?>" autocomplete="<?= $auto ?>"<?= $req ? ' required' : '' ?>>
<?php if ($err($k) !== ''): ?>              <p class="form__error" id="e-<?= $k ?>"><?= e($err($k)) ?></p><?php endif ?>
            </div>
<?php endforeach ?>
            <div class="form-group auth-field">
              <label class="auth-field__label" for="p-email"><?= e(t('auth.email')) ?></label>
              <input class="form__field form__text" type="email" id="p-email" value="<?= e($c['email']) ?>" disabled aria-describedby="h-email">
              <p class="auth-field__hint" id="h-email"><?= e(t('account.email_note')) ?></p>
            </div>
            <div class="form__actions"><button class="btn btn--auto" type="submit"><?= e(t('account.save')) ?></button></div>
          </form>
        </section>

        <section class="acct-card" aria-labelledby="acct-orders">
          <h3 id="acct-orders" class="acct-card__title"><?= e(t('account.orders')) ?></h3>
<?php if (!$orders): ?>
          <p class="acct-card__empty"><?= e(t('account.orders_none')) ?></p>
<?php else: ?>
          <ul class="acct-list">
<?php foreach ($orders as $o): ?>
            <li>
              <strong><?= e(t('account.order_no', ['id' => $o['id']])) ?></strong>
              <span class="acct-list__meta"><?= e(t('account.placed')) ?>: <?= e(substr((string) $o['created_at'], 0, 10)) ?> · <?= e(t('order.options.project_type.' . $o['project_type'])) ?></span>
<?php if ($o['package'] !== ''): ?>
              <span class="acct-list__meta"><?= e(t('account.package')) ?>: <?= e($o['package_name'] !== '' ? $o['package_name'] : $o['package']) ?><?= $o['package_price'] !== '' ? ' (' . e($o['package_price']) . ')' : '' ?></span>
<?php endif ?>
<?php if ($o['template'] !== ''): ?>
              <span class="acct-list__meta"><?= e(t('account.design')) ?>: <?= e($o['template_name'] !== '' ? $o['template_name'] : $o['template']) ?></span>
<?php endif ?>
            </li>
<?php endforeach ?>
          </ul>
<?php endif ?>
        </section>

        <section class="acct-card" aria-labelledby="acct-projects">
          <h3 id="acct-projects" class="acct-card__title"><?= e(t('account.projects')) ?></h3>
<?php if (!$projects): ?>
          <p class="acct-card__empty"><?= e(t('account.projects_none')) ?></p>
<?php else: ?>
          <ul class="acct-list">
<?php foreach ($projects as $p): ?>
            <li>
              <strong><?= e($p['name']) ?></strong>
              <span class="acct-list__meta"><?= e(t('account.status.' . $p['status'])) ?></span>
<?php if ($p['preview'] !== ''): ?>
              <a class="btn btn--revert btn--auto" href="<?= e($p['preview']) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('account.preview_open')) ?><span class="sr-only"> – <?= e($p['name']) ?></span></a>
<?php else: ?>
              <span class="acct-list__meta"><?= e(t('account.preview_off')) ?></span>
<?php endif ?>
            </li>
<?php endforeach ?>
          </ul>
          <p class="auth-field__hint"><?= e(t('account.preview_note')) ?></p>
<?php endif ?>
        </section>

        <form method="post" action="<?= e(url('logout')) ?>" class="acct-logout">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="btn btn--revert btn--auto" type="submit"><?= e(t('account.logout')) ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
