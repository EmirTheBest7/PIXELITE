<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('privacy.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('privacy.subtitle')) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-8 col-md-offset-2">
        <div class="prose">
<?php foreach (t('privacy.sections') as $s): ?>
          <h3><?= e($s['h']) ?></h3>
<?php foreach ($s['p'] as $p): ?>
          <p><?= e($p) ?></p>
<?php endforeach ?>
<?php endforeach ?>
        </div>
      </div>
    </div>
  </div>
</div>
