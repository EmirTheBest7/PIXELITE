<?php /** Dreamers Ad Credits strip (shared by the landing page and /pricing). */ ?>
<div class="credits">
  <svg class="credits__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
  <div class="credits__body">
    <p class="credits__badge"><?= e(t('credits.badge')) ?></p>
    <p class="credits__text"><?= e(t('credits.text')) ?></p>
    <p class="credits__note"><?= e(t('credits.note')) ?> <a class="link" href="<?= e(url('terms') . '#dreamers') ?>"><?= e(t('credits.link')) ?></a></p>
  </div>
</div>
