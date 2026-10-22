<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<section class="phero">
  <div class="wrap">
    <div class="sent">
      <p class="eyebrow__label">404</p>
      <h1 class="phero__title"><?= e(t('errors.404_title')) ?></h1>
      <p class="phero__sub"><?= e(t('errors.404_text')) ?></p>
      <?= ui_link(url(''), (string) t('errors.home'), 'accent') ?>
    </div>
  </div>
</section>
