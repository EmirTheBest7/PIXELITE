<?php
require_once __DIR__ . '/../partials/ui.php';
$cfg = consent_config();
$byGroup = ['necessary' => [], 'preferences' => []];
foreach ($cfg['storage'] as $item) { $byGroup[$item['group']][] = $item; }
$ph = ['label' => (string) t('footer.cookies'), 'title' => (string) t('cookies.title'), 'sub' => (string) t('cookies.subtitle')];
require __DIR__ . '/../partials/page-hero.php';
$tocItems = [['necessary', (string) t('cookies.necessary')], ['preferences', (string) t('cookies.preferences')], ['optional', (string) t('cookies.optional')], ['settings', (string) t('consent.dialog_title')]];
?>
<section class="section" aria-label="<?= e(t('cookies.title')) ?>">
  <div class="wrap">
    <div class="legal">
      <?php require __DIR__ . '/../partials/toc.php' ?>
      <div class="prose">
        <p><?= e(t('cookies.intro')) ?></p>
<?php foreach (['necessary', 'preferences'] as $group): ?>
        <h3 id="<?= $group ?>"><?= e(t("cookies.$group")) ?></h3>
        <p><?= e(t("cookies.{$group}_note")) ?></p>
        <table class="cookie-table">
          <thead><tr>
            <th scope="col"><?= e(t('cookies.col_name')) ?></th><th scope="col"><?= e(t('cookies.col_purpose')) ?></th>
            <th scope="col"><?= e(t('cookies.col_duration')) ?></th><th scope="col"><?= e(t('cookies.col_type')) ?></th>
          </tr></thead>
          <tbody>
<?php foreach ($byGroup[$group] as $item): ?>
            <tr>
              <th scope="row" data-label="<?= e(t('cookies.col_name')) ?>"><code><?= e($item['id'] === 'session' ? ($sessionCookie ?? $item['name']) : ($item['id'] === 'builder' ? ($builderCookie ?? $item['name']) : $item['name'])) ?></code></th>
              <td data-label="<?= e(t('cookies.col_purpose')) ?>"><?= e(t("cookies.items.{$item['id']}.purpose")) ?></td>
              <td data-label="<?= e(t('cookies.col_duration')) ?>"><?= e(t("cookies.items.{$item['id']}.duration")) ?></td>
              <td data-label="<?= e(t('cookies.col_type')) ?>"><?= e(t($item['type'] === 'cookie' ? 'cookies.type_cookie' : 'cookies.type_storage')) ?></td>
            </tr>
<?php endforeach ?>
          </tbody>
        </table>
<?php endforeach ?>
        <h3 id="optional"><?= e(t('cookies.optional')) ?></h3>
        <p><?= e(t('cookies.optional_none')) ?></p>
        <h3 id="settings"><?= e(t('consent.dialog_title')) ?></h3>
        <p><?= e(t('cookies.manage')) ?></p>
        <p><?= ui_button((string) t('cookies.settings_button'), 'accent', ['data-cookie-settings' => true]) ?></p>
      </div>
    </div>
  </div>
</section>
