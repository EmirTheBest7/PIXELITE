<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('terms.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('terms.subtitle')) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-8 col-md-offset-2">
        <div class="prose">
<?php foreach (t('terms.sections') as $s): ?>
          <h3><?= e($s['h']) ?></h3>
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
  </div>
</div>
