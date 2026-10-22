<?php require_once __DIR__ . '/../partials/ui.php';
$ph = ['label' => (string) t('nav.process'), 'title' => (string) t('process.title'), 'sub' => (string) t('process.subtitle')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-label="<?= e(t('nav.process')) ?>">
  <div class="wrap">
    <?php require __DIR__ . '/../partials/steps.php' ?>
  </div>
</section>

<section class="section section--soft" aria-labelledby="notes-title">
  <div class="wrap">
    <div class="sec-head"><h2 class="h-lg" id="notes-title"><?= e(t('process.notes_title')) ?></h2></div>
    <ul class="factors">
<?php foreach (t('process.notes') as $n): ?>
      <li><?= e($n) ?></li>
<?php endforeach ?>
    </ul>
    <p class="page-intro"><span class="placeholder"><?= e(t('process.placeholder')) ?></span></p>
    <p class="more"><?= ui_link(url('pricing'), (string) t('process.cta_secondary'), 'ghost') ?></p>
  </div>
</section>

<section class="section" aria-labelledby="cta-title">
  <div class="wrap">
    <div class="band">
      <div>
        <h2 class="band__title" id="cta-title"><?= e(t('process.cta_title')) ?></h2>
        <p class="band__sub"><?= e(t('process.cta_text')) ?></p>
      </div>
      <div class="band__actions"><?= ui_link(url('order'), (string) t('process.cta_button'), 'light') ?></div>
    </div>
  </div>
</section>
