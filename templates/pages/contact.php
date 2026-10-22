<?php require_once __DIR__ . '/../partials/ui.php';
$ph = ['label' => (string) t('nav.contact'), 'title' => (string) t('contact.title'), 'sub' => (string) t('contact.page_intro')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-label="<?= e(t('nav.contact')) ?>">
  <div class="wrap">
    <div class="contact-grid">
      <?php require __DIR__ . '/../partials/contact-details.php' ?>
      <div class="cta-card">
        <h2 class="h-md"><?= e(t('contact.cta_title')) ?></h2>
<?php if (isset($design) && $design !== null): ?><p class="contact-design"><strong><?= e(t('contact.chosen_design')) ?>:</strong> <?= e($design['title']) ?></p><?php endif ?>
        <p class="cta-card__text"><?= e(t('contact.cta_text')) ?></p>
        <?= ui_link(url('order') . (isset($design) && $design !== null ? '?template=' . $design['slug'] : ''), (string) t('contact.cta_button'), 'accent') ?>
      </div>
    </div>
  </div>
</section>
