<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('contact.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('contact.page_intro')) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-1"></div>
      <div class="col-md-4"><?php require __DIR__ . '/../partials/contact-details.php' ?></div>
      <div class="col-md-6">
        <div class="cta-card">
          <h3 class="cta-card__title"><?= e(t('contact.cta_title')) ?></h3>
<?php if ($design !== null): // only for a valid catalog slug; the name comes from the catalog, never from the request ?>
          <p class="contact-design"><strong><?= e(t('contact.chosen_design')) ?>:</strong> <?= e($design['title']) ?></p>
<?php endif ?>
          <p class="cta-card__text"><?= e(t('contact.cta_text')) ?></p>
          <?php $tq = $design !== null ? '?template=' . $design['slug'] : ''; ?>
          <a class="btn btn--auto" href="<?= e(url('order') . $tq) ?>"><?= e(t('contact.cta_button')) ?></a>
        </div>
      </div>
      <div class="col-md-1"></div>
    </div>
  </div>
</div>
