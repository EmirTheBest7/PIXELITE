<?php
/** Compact header control: cycles System -> Light -> Dark. Hidden on narrow screens (the menu and footer carry the 3-way switch). */
$labels = ['theme' => t('theme.label') . ': {mode}', 'next' => (string) t('theme.switch_to'), 'light' => t('theme.light'), 'dark' => t('theme.dark'), 'system' => t('theme.system')];
$initial = str_replace('{mode}', $labels['system'], $labels['theme']) . '. ' . str_replace('{mode}', $labels['light'], $labels['next']);
?>
<button type="button" class="theme-cycle" data-theme-cycle data-mode="system" data-labels="<?= e(json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>" aria-label="<?= e($initial) ?>" title="<?= e($initial) ?>">
  <svg class="theme-cycle__icon theme-cycle__icon--light" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9L17 7M7 17l-2.1 2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
  <svg class="theme-cycle__icon theme-cycle__icon--dark" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
  <svg class="theme-cycle__icon theme-cycle__icon--system" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 20h8M12 16v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
</button>
