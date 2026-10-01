<?php
/** Three-way theme control (Light / Dark / System). JS in theme.js keeps every instance in sync. */
$icons = [
    'light' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9L17 7M7 17l-2.1 2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    'dark' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
    'system' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 20h8M12 16v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
];
?>
<div class="theme" role="group" aria-label="<?= e(t('theme.label')) ?>">
<?php foreach (['light', 'dark', 'system'] as $mode): ?>
  <button type="button" class="theme__btn" data-theme-set="<?= $mode ?>" aria-pressed="false" aria-label="<?= e(t('a11y.theme_' . $mode)) ?>" title="<?= e(t('theme.' . $mode)) ?>"><?= $icons[$mode] ?><span class="theme__text"><?= e(t('theme.' . $mode)) ?></span></button>
<?php endforeach ?>
</div>
