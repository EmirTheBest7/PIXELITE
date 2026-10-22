<?php require_once __DIR__ . '/../partials/ui.php';
$ph = ['label' => (string) t('footer.privacy'), 'title' => (string) t('privacy.title'), 'sub' => (string) t('privacy.subtitle')];
require __DIR__ . '/../partials/page-hero.php';
$tocItems = []; foreach (t('privacy.sections') as $i => $s) { $tocItems[] = ['p' . ($i + 1), $s['h']]; } ?>
<section class="section" aria-label="<?= e(t('privacy.title')) ?>">
  <div class="wrap">
    <div class="legal">
      <?php require __DIR__ . '/../partials/toc.php' ?>
      <div class="prose">
<?php foreach (t('privacy.sections') as $i => $s): ?>
        <h3 id="p<?= $i + 1 ?>"><?= e($s['h']) ?></h3>
<?php foreach ($s['p'] as $p): ?>
        <p><?= e(legal_text($p)) ?></p>
<?php endforeach ?>
<?php endforeach ?>
        <p class="prose__meta"><?= e(t('privacy.updated')) ?></p>
      </div>
    </div>
  </div>
</section>
