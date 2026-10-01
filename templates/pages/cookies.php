<?php
$cfg = consent_config();
$byGroup = ['necessary' => [], 'preferences' => []];
foreach ($cfg['storage'] as $item) { $byGroup[$item['group']][] = $item; }
?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('cookies.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('cookies.subtitle')) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-10 col-md-offset-1">
        <div class="prose">
          <p><?= e(t('cookies.intro')) ?></p>
<?php foreach (['necessary', 'preferences'] as $group): ?>
          <h3><?= e(t("cookies.$group")) ?></h3>
          <p><?= e(t("cookies.{$group}_note")) ?></p>
          <table class="cookie-table">
            <thead><tr>
              <th scope="col"><?= e(t('cookies.col_name')) ?></th><th scope="col"><?= e(t('cookies.col_purpose')) ?></th>
              <th scope="col"><?= e(t('cookies.col_duration')) ?></th><th scope="col"><?= e(t('cookies.col_type')) ?></th>
            </tr></thead>
            <tbody>
<?php foreach ($byGroup[$group] as $item): ?>
              <tr>
                <th scope="row" data-label="<?= e(t('cookies.col_name')) ?>"><code><?= e($item['name']) ?></code></th>
                <td data-label="<?= e(t('cookies.col_purpose')) ?>"><?= e(t("cookies.items.{$item['id']}.purpose")) ?></td>
                <td data-label="<?= e(t('cookies.col_duration')) ?>"><?= e(t("cookies.items.{$item['id']}.duration")) ?></td>
                <td data-label="<?= e(t('cookies.col_type')) ?>"><?= e(t($item['type'] === 'cookie' ? 'cookies.type_cookie' : 'cookies.type_storage')) ?></td>
              </tr>
<?php endforeach ?>
            </tbody>
          </table>
<?php endforeach ?>
          <h3><?= e(t('cookies.optional')) ?></h3>
          <p><?= e(t('cookies.optional_none')) ?></p>
          <h3 id="settings"><?= e(t('consent.dialog_title')) ?></h3>
          <p><?= e(t('cookies.manage')) ?></p>
          <p><button type="button" class="btn btn--strong btn--auto" data-cookie-settings><?= e(t('cookies.settings_button')) ?></button></p>
        </div>
      </div>
    </div>
  </div>
</div>
