<?php
/** @var array $old @var array $errors @var string $formError @var string $csrf */
$field = fn(string $k): string => e($old[$k] ?? '');
$err = fn(string $k): string => isset($errors[$k]) ? (string) t('order.errors.' . $errors[$k]) : '';
$select = function (string $name) use ($old, $errors, $err): void {
    $err_msg = $err($name); $cur = $old[$name] ?? '';
    echo '<div class="form__field--half-wrap">';
    echo '<label class="sr-only" for="f-' . $name . '">' . e(t("order.fields.$name")) . '</label>';
    echo '<select class="form__field form__select' . ($err_msg ? ' is-invalid' : '') . '" id="f-' . $name . '" name="' . $name . '" required'
        . ($err_msg ? ' aria-invalid="true" aria-describedby="e-' . $name . '"' : '') . '>';
    echo '<option value=""' . ($cur === '' ? ' selected' : '') . ' disabled>' . e(t("order.fields.$name")) . '</option>';
    foreach (t("order.options.$name") as $val => $label) {
        echo '<option value="' . e($val) . '"' . ($cur === (string) $val ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select>';
    if ($err_msg) echo '<p class="form__error" id="e-' . $name . '">' . e($err_msg) . '</p>';
    echo '</div>';
};
$text = function (string $name, string $type = 'text', bool $required = false, string $autocomplete = '') use ($old, $err): void {
    $err_msg = $err($name);
    echo '<div class="form__field--half-wrap">';
    echo '<label class="sr-only" for="f-' . $name . '">' . e(t("order.fields.$name")) . '</label>';
    echo '<input class="form__field form__text' . ($err_msg ? ' is-invalid' : '') . '" type="' . $type . '" id="f-' . $name . '" name="' . $name . '"'
        . ' placeholder="' . e(t("order.fields.$name")) . '" value="' . e($old[$name] ?? '') . '" maxlength="' . \Pixelite\OrderValidator::MAX[$name] . '"'
        . ($required ? ' required' : '') . ($autocomplete ? ' autocomplete="' . $autocomplete . '"' : '')
        . ($err_msg ? ' aria-invalid="true" aria-describedby="e-' . $name . '"' : '') . '>';
    if ($err_msg) echo '<p class="form__error" id="e-' . $name . '">' . e($err_msg) . '</p>';
    echo '</div>';
};
?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('order.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('order.subtitle')) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-8 col-md-offset-2">
<?php if ($formError || $errors): ?>
        <div class="form__alert" role="alert" tabindex="-1" data-form-alert><?= e(t('order.form_errors.' . ($formError ?: 'fix'))) ?></div>
<?php endif ?>
        <form id="order" class="form" method="post" action="<?= e(url('order')) ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <div class="form__trap" aria-hidden="true">
            <label for="f-hp">Leave this empty</label>
            <input type="text" id="f-hp" name="hp_site" value="" tabindex="-1" autocomplete="off">
          </div>
          <div class="form-group">
            <?php $text('name', 'text', true, 'name'); $text('company', 'text', false, 'organization'); ?>
          </div>
          <div class="form-group">
            <?php $text('email', 'email', true, 'email'); $text('phone', 'tel', false, 'tel'); ?>
          </div>
          <div class="form-group">
            <?php $select('project_type'); $select('budget'); ?>
          </div>
          <div class="form-group">
            <?php $select('timeframe'); ?>
          </div>
          <div class="form-group">
            <?php $e = $err('description'); ?>
            <label class="sr-only" for="f-description"><?= e(t('order.fields.description')) ?></label>
            <textarea class="form__field form__textarea<?= $e ? ' is-invalid' : '' ?>" id="f-description" name="description" required maxlength="<?= \Pixelite\OrderValidator::MAX['description'] ?>" placeholder="<?= e(t('order.fields.description')) ?>"<?= $e ? ' aria-invalid="true" aria-describedby="e-description"' : '' ?>><?= $field('description') ?></textarea>
            <?php if ($e): ?><p class="form__error" id="e-description"><?= e($e) ?></p><?php endif ?>
          </div>
          <div class="form__consent">
            <input type="checkbox" id="f-consent" name="consent" value="1"<?= isset($_POST['consent']) && $_POST['consent'] === '1' ? ' checked' : '' ?><?= isset($errors['consent']) ? ' aria-invalid="true" aria-describedby="e-consent"' : '' ?>>
            <label for="f-consent"><?= e(t('order.consent')) ?> <a href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener"><?= e(t('order.consent_link')) ?></a></label>
            <?php if (isset($errors['consent'])): ?><p class="form__error" id="e-consent"><?= e($err('consent')) ?></p><?php endif ?>
          </div>
          <div class="form__actions">
            <button class="btn btn--auto" type="submit"><?= e(t('order.submit')) ?></button>
            <span class="form__note"><?= e(t('order.required_note')) ?></span>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
