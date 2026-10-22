<?php require_once __DIR__ . '/../partials/ui.php';
$ph = ['label' => (string) t('footer.terms'), 'title' => (string) t('terms.title'), 'sub' => (string) t('terms.subtitle')];
require __DIR__ . '/../partials/page-hero.php';
$tocItems = []; foreach (t('terms.sections') as $i => $s) { $tocItems[] = [$s['id'] ?? 't' . ($i + 1), $s['h']]; } ?>
<section class="section" aria-label="<?= e(t('terms.title')) ?>">
  <div class="wrap">
    <div class="legal">
      <?php require __DIR__ . '/../partials/toc.php' ?>
      <div class="prose">
<?php foreach (t('terms.sections') as $i => $s): ?>
        <h3 id="<?= e($s['id'] ?? 't' . ($i + 1)) ?>"><?= e($s['h']) ?></h3>
<?php foreach ($s['p'] as $p): ?>
        <p><?= e(legal_text($p)) ?></p>
<?php endforeach ?>
<?php if (!empty($s['link'])): ?>
        <p><a href="<?= e($s['link']['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($s['link']['label']) ?></a></p>
<?php endif ?>
<?php endforeach ?>
        <p class="prose__meta"><?= e(t('terms.note')) ?></p>
      </div>
    </div>
  </div>
</section>
