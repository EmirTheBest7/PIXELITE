<?php /** Placeholder only: Dreamers sign-in is NOT implemented. The button is disabled, has no handler and no target; no credentials are requested or stored. */ ?>
<div class="auth-alt">
  <p class="auth-alt__or"><span><?= e(t('auth.or')) ?></span></p>
  <button type="button" class="btn btn--revert btn--auto auth-alt__btn" disabled aria-disabled="true" aria-describedby="dreamers-note"><?= e(t('auth.dreamers')) ?></button>
  <p class="auth-alt__note" id="dreamers-note"><?= e(t('auth.dreamers_note')) ?></p>
</div>
