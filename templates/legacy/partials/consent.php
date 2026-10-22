<?php
/** Cookie banner (non-modal, bottom) and settings dialog. Hidden until consent.js decides they are needed. */
$cfg = consent_config();
$categories = array_keys(array_filter($cfg['optional'], fn($services) => $services !== []));   // only categories that really have a service
?>
<section id="cookie-banner" class="consent-banner" role="region" aria-labelledby="cb-title" hidden
         data-version="<?= (int) $cfg['version'] ?>" data-max-age-days="<?= (int) $cfg['max_age_days'] ?>" data-categories="<?= e(implode(',', $categories)) ?>">
  <div class="consent-banner__inner">
    <div class="consent-banner__text">
      <p class="consent-banner__title" id="cb-title"><?= e(t('consent.banner_title')) ?></p>
      <p><?= e(t('consent.banner_text')) ?> <a href="<?= e(url('cookies')) ?>"><?= e(t('consent.policy')) ?></a></p>
    </div>
    <div class="consent-banner__actions">
      <button type="button" class="btn btn--strong btn--auto" data-consent-accept><?= e(t('consent.accept')) ?></button>
      <button type="button" class="btn btn--strong btn--auto" data-consent-reject><?= e(t('consent.reject')) ?></button>
      <button type="button" class="btn btn--revert btn--auto" data-consent-settings data-cookie-settings><?= e(t('consent.settings')) ?></button>
    </div>
  </div>
</section>

<dialog id="cookie-dialog" class="consent-dialog" aria-labelledby="cd-title">
  <div class="consent-dialog__head">
    <h2 id="cd-title" tabindex="-1" autofocus><?= e(t('consent.dialog_title')) ?></h2>
    <button type="button" class="consent-dialog__x" data-consent-close aria-label="<?= e(t('consent.close')) ?>"><span aria-hidden="true">×</span></button>
  </div>
  <p><?= e(t('consent.dialog_intro')) ?></p>
  <div class="consent-group">
    <h3><?= e(t('consent.necessary_title')) ?></h3>
    <p><?= e(t('consent.necessary_text')) ?></p>
  </div>
  <div class="consent-group">
    <h3><?= e(t('consent.preferences_title')) ?></h3>
    <p><?= e(t('consent.preferences_text')) ?></p>
  </div>
  <div class="consent-group">
    <h3><?= e(t('consent.optional_title')) ?></h3>
<?php if (!$categories): ?>
    <p><?= e(t('consent.optional_none')) ?></p>
<?php else: foreach ($categories as $cat): ?>
    <label class="consent-switch">
      <input type="checkbox" role="switch" data-consent-category="<?= e($cat) ?>">
      <span><strong><?= e(t("consent.{$cat}_title")) ?></strong> – <?= e(t("consent.{$cat}_text")) ?></span>
    </label>
<?php endforeach; endif ?>
  </div>
  <p class="consent-dialog__more"><a href="<?= e(url('cookies')) ?>"><?= e(t('consent.policy')) ?></a></p>
  <div class="consent-dialog__actions">
    <button type="button" class="btn btn--strong btn--auto" data-consent-accept><?= e(t('consent.accept')) ?></button>
    <button type="button" class="btn btn--strong btn--auto" data-consent-reject><?= e(t('consent.reject')) ?></button>
<?php if ($categories): ?>
    <button type="button" class="btn btn--revert btn--auto" data-consent-save><?= e(t('consent.save')) ?></button>
<?php else: ?>
    <button type="button" class="btn btn--revert btn--auto" data-consent-close><?= e(t('consent.close')) ?></button>
<?php endif ?>
  </div>
</dialog>
