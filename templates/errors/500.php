<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<section class="phero">
  <div class="wrap">
    <div class="sent">
      <p class="eyebrow__label">500</p>
      <h1 class="phero__title"><?= e(t('errors.500_title')) ?></h1>
      <p class="phero__sub"><?= e(t('errors.500_text')) ?></p>
      <?= ui_link(url(''), (string) t('errors.home'), 'accent') ?>
    </div>
  </div>
</section>
