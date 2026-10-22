<?php
/** @var array $old @var array $errors @var string $formError @var string $csrf   (contract unchanged from the original template) */
require_once __DIR__ . '/../partials/ui.php';
$field = fn(string $k): string => e($old[$k] ?? '');
$err = fn(string $k): string => isset($errors[$k]) ? (string) t('order.errors.' . $errors[$k]) : '';
$select = function (string $name, ?array $opts = null, bool $required = true) use ($old, $errors, $err): void {
    $err_msg = $err($name); $cur = $old[$name] ?? '';
    echo '<div class="form__cell">';
    echo '<label class="form__label" for="f-' . $name . '">' . e(t("order.fields.$name")) . '</label>';
    echo '<select class="form__field form__select' . ($err_msg ? ' is-invalid' : '') . '" id="f-' . $name . '" name="' . $name . '"' . ($required ? ' required' : '')
        . ($err_msg ? ' aria-invalid="true" aria-describedby="e-' . $name . '"' : '') . '>';
    echo '<option value=""' . ($cur === '' ? ' selected' : '') . ($required ? ' disabled' : '') . '>' . e(t("order.fields.$name")) . '</option>';
    foreach ($opts ?? t("order.options.$name") as $val => $label) {
        echo '<option value="' . e($val) . '"' . ($cur === (string) $val ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select>';
    if ($err_msg) echo '<p class="form__error" id="e-' . $name . '">' . e($err_msg) . '</p>';
    echo '</div>';
};
$text = function (string $name, string $type = 'text', bool $required = false, string $autocomplete = '') use ($old, $err): void {
    $err_msg = $err($name);
    echo '<div class="form__cell">';
    echo '<label class="form__label" for="f-' . $name . '">' . e(t("order.fields.$name")) . '</label>';
    echo '<input class="form__field form__text' . ($err_msg ? ' is-invalid' : '') . '" type="' . $type . '" id="f-' . $name . '" name="' . $name . '"'
        . ' value="' . e($old[$name] ?? '') . '" maxlength="' . \Pixelite\OrderValidator::MAX[$name] . '"'
        . ($required ? ' required' : '') . ($autocomplete ? ' autocomplete="' . $autocomplete . '"' : '')
        . ($err_msg ? ' aria-invalid="true" aria-describedby="e-' . $name . '"' : '') . '>';
    if ($err_msg) echo '<p class="form__error" id="e-' . $name . '">' . e($err_msg) . '</p>';
    echo '</div>';
};
$ph = ['label' => (string) t('nav.start'), 'title' => (string) t('order.title'), 'sub' => (string) t('order.subtitle')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-label="<?= e(t('order.title')) ?>">
  <div class="wrap">
    <div class="order-card">
<?php if ($formError || $errors): ?>
      <div class="form__alert" role="alert" tabindex="-1" data-form-alert><?= e(t('order.form_errors.' . ($formError ?: 'fix'))) ?></div>
<?php endif ?>
      <form id="order" class="form" method="post" action="<?= e(url('order')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="form__trap" aria-hidden="true">
          <label for="f-hp">Leave this empty</label>
          <input type="text" id="f-hp" name="hp_site" value="" tabindex="-1" autocomplete="off">
        </div>
        <div class="form__row"><?php $text('name', 'text', true, 'name'); $text('company', 'text', false, 'organization'); ?></div>
        <div class="form__row"><?php $text('email', 'email', true, 'email'); $text('phone', 'tel', false, 'tel'); ?></div>
        <div class="form__row">
          <?php
            $pkgOpts = [];   // "<name> – <from> <amount> <currency>", straight from the pricing data
            foreach (t('services.items') as $it) { $pkgOpts[$it['id']] = $it['name'] . ' – ' . $it['from'] . ' ' . $it['amount'] . ' ' . $it['currency']; }
            $select('project_type'); $select('package', $pkgOpts, false);
          ?>
        </div>
<?php if (($old['template'] ?? '') !== ''): // the field exists only for a valid design (or a tampered value that must be corrected) ?>
        <div class="tpick" data-template-pick>
          <label class="tpick__label" for="f-template"><?= e(t('templates.order_label')) ?></label>
          <div class="tpick__row">
<?php $tcur = $old['template'] ?? ''; $tcat = \Pixelite\TemplateCatalog::all(); ?>
            <img class="tpick__thumb" data-template-thumb src="<?= $tcur !== '' && isset($tcat[$tcur]) ? e(\Pixelite\TemplateCatalog::mediaUrl($tcat[$tcur], 'poster-640.jpg')) : '' ?>" width="160" height="100" alt="<?= e(t('templates.order_thumb_alt')) ?>"<?= $tcur === '' || !isset($tcat[$tcur]) ? ' hidden' : '' ?>>
            <div class="tpick__controls">
              <select class="form__field form__select<?= isset($errors['template']) ? ' is-invalid' : '' ?>" id="f-template" name="template"<?= isset($errors['template']) ? ' aria-invalid="true" aria-describedby="e-template"' : '' ?>>
                <option value=""><?= e(t('templates.order_none')) ?></option>
<?php foreach ($tcat as $slug => $te): ?>
                <option value="<?= e($slug) ?>" data-thumb="<?= e(\Pixelite\TemplateCatalog::mediaUrl($te, 'poster-640.jpg')) ?>"<?= $tcur === $slug ? ' selected' : '' ?>><?= e(t("templates.items.$slug.title")) ?></option>
<?php endforeach ?>
              </select>
              <p class="tpick__note"><?= e(t('templates.order_note')) ?> <a href="<?= e(url('templates')) ?>"><?= e(t('templates.order_change')) ?></a></p>
<?php if (isset($errors['template'])): ?><p class="form__error" id="e-template"><?= e($err('template')) ?></p><?php endif ?>
            </div>
          </div>
        </div>
<?php endif ?>
        <div class="form__row"><?php $select('budget'); $select('timeframe'); ?></div>
        <div class="form__cell">
          <?php $e = $err('description'); ?>
          <label class="form__label" for="f-description"><?= e(t('order.fields.description')) ?></label>
          <textarea class="form__field form__textarea<?= $e ? ' is-invalid' : '' ?>" id="f-description" name="description" required maxlength="<?= \Pixelite\OrderValidator::MAX['description'] ?>"<?= $e ? ' aria-invalid="true" aria-describedby="e-description"' : '' ?>><?= $field('description') ?></textarea>
          <?php if ($e): ?><p class="form__error" id="e-description"><?= e($e) ?></p><?php endif ?>
        </div>
        <div class="form__consent">
          <input type="checkbox" id="f-consent" name="consent" value="1"<?= isset($_POST['consent']) && $_POST['consent'] === '1' ? ' checked' : '' ?><?= isset($errors['consent']) ? ' aria-invalid="true" aria-describedby="e-consent"' : '' ?>>
          <label for="f-consent"><?= e(t('order.consent')) ?> <a href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener"><?= e(t('order.consent_link')) ?></a></label>
          <?php if (isset($errors['consent'])): ?><p class="form__error" id="e-consent"><?= e($err('consent')) ?></p><?php endif ?>
        </div>
        <div class="form__actions">
          <?= ui_button((string) t('order.submit'), 'accent', ['type' => 'submit']) ?>
          <span class="form__note"><?= e(t('order.required_note')) ?></span>
        </div>
      </form>
    </div>
  </div>
</section>
