<?php /** Placeholder only: Dreamers sign-in is NOT implemented – the button is disabled, has no handler and no target. */ ?>
<div class="auth-alt">
  <p class="auth-alt__or"><span><?= e(t('auth.or')) ?></span></p>
  <button type="button" class="btn btn--ghost btn--plain auth-alt__btn" disabled aria-disabled="true" aria-describedby="dreamers-note"><?= e(t('auth.dreamers')) ?></button>
  <p class="auth-note" id="dreamers-note"><?= e(t('auth.dreamers_note')) ?></p>
</div>
