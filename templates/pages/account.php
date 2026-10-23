<?php /** @var array $c @var list<array> $orders @var list<array> $projects @var array $errors @var string $formError @var string $csrf @var string $flash */
require_once __DIR__ . '/../partials/ui.php';
$err = fn(string $k): string => isset($errors[$k]) ? (string) t('auth.errors.' . $errors[$k]) : '';
$ph = ['label' => (string) t('footer.account'), 'title' => (string) t('account.title'), 'sub' => (string) t('account.hello', ['email' => $c['email']])];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-label="<?= e(t('account.title')) ?>">
  <div class="wrap"><div class="acct">
<?php if ($flash !== ''): ?><div class="form__alert form__alert--ok" role="status"><?= e(t('auth.flash.' . $flash)) ?></div><?php endif ?>
<?php if ($formError || $errors): ?><div class="form__alert" role="alert" tabindex="-1" data-form-alert><?= e(t('auth.form_errors.' . ($formError ?: 'fix'))) ?></div><?php endif ?>
    <section class="card" aria-labelledby="acct-profile">
      <h2 class="acct__title" id="acct-profile"><?= e(t('account.profile')) ?></h2>
      <form class="form" method="post" action="<?= e(url('account')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php foreach ([['name', 'auth.name', 'text', 'name', true], ['company', 'auth.company', 'text', 'organization', false], ['phone', 'auth.phone', 'tel', 'tel', false]] as [$k, $lab, $type, $auto, $req]): ?>
        <div class="form__cell">
          <label class="form__label" for="p-<?= $k ?>"><?= e(t($lab)) ?></label>
          <input class="form__field form__text<?= $err($k) !== '' ? ' is-invalid' : '' ?>" type="<?= $type ?>" id="p-<?= $k ?>" name="<?= $k ?>" value="<?= e($c[$k] ?? '') ?>" autocomplete="<?= $auto ?>"<?= $req ? ' required' : '' ?><?= $err($k) !== '' ? ' aria-invalid="true" aria-describedby="e-' . $k . '"' : '' ?>>
<?php if ($err($k) !== ''): ?><p class="form__error" id="e-<?= $k ?>"><?= e($err($k)) ?></p><?php endif ?>
        </div>
<?php endforeach ?>
        <div class="form__cell"><label class="form__label" for="p-email"><?= e(t('auth.email')) ?></label><input class="form__field" type="email" id="p-email" value="<?= e($c['email']) ?>" disabled aria-describedby="h-email"><p class="auth-note" id="h-email"><?= e(t('account.email_note')) ?></p></div>
        <div class="form__actions"><?= ui_button((string) t('account.save'), 'accent', ['type' => 'submit']) ?></div>
      </form>
    </section>
    <section class="card" aria-labelledby="acct-orders">
      <h2 class="acct__title" id="acct-orders"><?= e(t('account.orders')) ?></h2>
<?php if (!$orders): ?><p class="acct__empty"><?= e(t('account.orders_none')) ?></p><?php else: ?>
      <ul class="acct-list">
<?php foreach ($orders as $o): ?>
        <li><strong><?= e(t('account.order_no', ['id' => $o['id']])) ?></strong>
          <span class="acct-list__meta"><?= e(t('account.placed')) ?>: <?= e(substr((string) $o['created_at'], 0, 10)) ?> · <?= e(t('order.options.project_type.' . $o['project_type'])) ?></span>
<?php if ($o['package'] !== ''): ?><span class="acct-list__meta"><?= e(t('account.package')) ?>: <?= e($o['package_name'] !== '' ? $o['package_name'] : $o['package']) ?><?= $o['package_price'] !== '' ? ' (' . e($o['package_price']) . ')' : '' ?></span><?php endif ?>
<?php if ($o['template'] !== ''): ?><span class="acct-list__meta"><?= e(t('account.design')) ?>: <?= e($o['template_name'] !== '' ? $o['template_name'] : $o['template']) ?></span><?php endif ?>
        </li>
<?php endforeach ?>
      </ul>
<?php endif ?>
    </section>
    <section class="card" aria-labelledby="acct-projects">
      <h2 class="acct__title" id="acct-projects"><?= e(t('account.projects')) ?></h2>
<?php if (!$projects): ?><p class="acct__empty"><?= e(t('account.projects_none')) ?></p><?php else: ?>
      <ul class="acct-list">
<?php foreach ($projects as $p): ?>
        <li><strong><?= e($p['name']) ?></strong><span class="acct-list__meta"><?= e(t('account.status.' . $p['status'])) ?></span>
<?php if (\Pixelite\Editor\Editor::enabled() && $p['status'] !== 'archived'): /* EXPERIMENTAL visual editor: only while EDITOR_ENABLED=true; English-only on purpose */ ?><?= ui_link('/editor/projects/' . (int) $p['id'], 'Edit visually (experimental)', 'sm ghost', [], (string) $p['name']) ?><?= ui_link('/editor/projects/' . (int) $p['id'] . '/builder', 'Site builder (experimental)', 'sm ghost', [], (string) $p['name']) ?><?php endif ?>
<?php if ($p['preview'] !== ''): ?><?= ui_link($p['preview'], (string) t('account.preview_open'), 'sm ghost', ['target' => '_blank', 'rel' => 'noopener noreferrer'], $p['name']) ?><?php else: ?><span class="acct-list__meta"><?= e(t('account.preview_off')) ?></span><?php endif ?>
        </li>
<?php endforeach ?>
      </ul>
      <p class="acct__hint"><?= e(t('account.preview_note')) ?></p>
<?php endif ?>
    </section>
    <form method="post" action="<?= e(url('logout')) ?>"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><?= ui_button((string) t('account.logout'), 'ghost plain', ['type' => 'submit']) ?></form>
  </div></div>
</section>
