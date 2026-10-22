<?php /** @var string $f @var string $label @var string $type @var string $value @var string $auto @var ?string $hint @var bool $req */
$msg = isset($errors[$f]) ? (string) t('auth.errors.' . $errors[$f]) : ''; ?>
<div class="form__cell">
  <label class="form__label" for="a-<?= e($f) ?>"><?= e($label) ?></label>
  <input class="form__field form__text<?= $msg ? ' is-invalid' : '' ?>" type="<?= e($type) ?>" id="a-<?= e($f) ?>" name="<?= e($f) ?>" value="<?= e($value) ?>"<?= $req ? ' required' : '' ?><?= $auto !== '' ? ' autocomplete="' . e($auto) . '"' : '' ?><?= $type === 'password' ? ' minlength="10" maxlength="72"' : '' ?><?= $msg ? ' aria-invalid="true" aria-describedby="e-' . e($f) . '"' : ($hint ? ' aria-describedby="h-' . e($f) . '"' : '') ?>>
<?php if ($hint && !$msg): ?>  <p class="auth-note" id="h-<?= e($f) ?>"><?= e($hint) ?></p><?php endif ?>
<?php if ($msg): ?>  <p class="form__error" id="e-<?= e($f) ?>"><?= e($msg) ?></p><?php endif ?>
</div>
