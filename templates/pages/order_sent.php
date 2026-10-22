<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<section class="phero">
  <div class="wrap">
    <div class="sent">
      <span class="sent__mark" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 30 30" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 15.5l5.5 5.5L23 10"/></svg></span>
      <h1 class="phero__title"><?= e(t('order.sent_title')) ?></h1>
      <p class="phero__sub"><?= e(t('order.sent_text')) ?></p>
      <?= ui_link(url(''), (string) t('order.sent_back'), 'accent') ?>
    </div>
  </div>
</section>
