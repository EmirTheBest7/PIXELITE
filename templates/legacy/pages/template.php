<?php /** @var array $t localised catalog entry @var list<array> $related */
$m = $t['media']; $orderUrl = url('order') . '?template=' . $t['slug']; ?>
<div class="sect sect--padding-top">
  <div class="container">
    <nav class="tcrumbs" aria-label="<?= e(t('a11y.breadcrumb')) ?>">
      <a href="<?= e(url('templates')) ?>">← <?= e(t('templates.back')) ?></a>
    </nav>
    <div class="row">
      <h1 class="row__title tdetail__title"><?= e($t['title']) ?></h1>
      <h2 class="row__sub"><?= e($t['desc']) ?></h2>
    </div>
    <div class="tdetail">
      <div class="tdetail__preview" data-detail-preview>
<?php $demoUrl = url('templates/' . $t['slug'] . '/demo'); ?>
        <div class="tpreview__bar">
          <h2 class="tpreview__title"><?= e(t('templates.preview_heading')) ?></h2>
          <div class="tpreview__sizes" role="group" aria-label="<?= e(t('templates.preview_size')) ?>" data-size-toggle hidden>
            <button type="button" data-size="desktop" aria-pressed="true"><?= e(t('templates.preview_desktop')) ?></button>
            <button type="button" data-size="phone" aria-pressed="false"><?= e(t('templates.preview_phone')) ?></button>
          </div>
          <a class="tpreview__open" href="<?= e($demoUrl) ?>" target="_blank" rel="noopener"><?= e(t('templates.preview_open')) ?></a>
        </div>
        <div class="tpreview__stage" data-stage>
          <iframe class="tpreview__frame" src="<?= e($demoUrl) ?>" title="<?= e(t('templates.preview_frame_title', ['name' => $t['title']])) ?>" loading="lazy" sandbox="allow-scripts allow-same-origin allow-forms" width="1440" height="900"></iframe>
        </div>
        <p class="tdetail__render"><?= e(t('templates.preview_hint')) ?> <?= e(t('templates.render_note')) ?></p>
      </div>
      <aside class="tdetail__side">
        <ul class="tcard__tags">
          <li><a href="<?= e(url('templates') . '?category=' . $t['category']) ?>"><?= e(t('templates.categories.' . $t['category'] . '.name')) ?></a></li>
          <li><?= e(t('templates.styles.' . $t['style'])) ?></li>
<?php foreach ($t['tags'] as $tag): ?>
          <li><?= e(t("templates.tags.$tag")) ?></li>
<?php endforeach ?>
        </ul>
        <h2 class="tdetail__h"><?= e(t('templates.features')) ?></h2>
        <ul class="tdetail__list">
<?php foreach ($t['features'] as $f): ?>
          <li><?= e($f) ?></li>
<?php endforeach ?>
        </ul>
        <?php require __DIR__ . '/../partials/template-price.php' ?>
        <a class="btn btn--auto tdetail__cta" href="<?= e($orderUrl) ?>"><?= e(t('templates.cta')) ?></a>
        <p class="tdetail__ask"><a href="<?= e(url('contact') . '?template=' . $t['slug']) ?>"><?= e(t('templates.ask')) ?></a></p>
      </aside>
    </div>
    <div class="tsource">
      <h2 class="tdetail__h"><?= e(t('templates.source')) ?></h2>
      <p><?= e(t('templates.origin')) ?> <?= e(t('templates.built_with')) ?> <?= e(implode(', ', $t['stack'])) ?>.</p>
<?php if (!empty($t['origin']['third_party'])): ?>
      <p><?= e(t('templates.components', ['list' => implode(', ', array_map(static fn(array $c): string => $c['name'] . ' (' . $c['license'] . ')', $t['origin']['third_party']))])) ?>
<?php foreach ($t['origin']['third_party'] as $c): ?>
        <a href="<?= e($c['notice']) ?>" rel="noopener"><?= e($c['name']) ?> – <?= e(t('templates.notice_link')) ?></a>
<?php endforeach ?></p>
<?php endif ?>
    </div>
<?php if ($related): ?>
    <div class="row"><h2 class="row__title tdetail__related"><?= e(t('templates.related')) ?></h2></div>
    <div class="tgrid">
<?php foreach ($related as $t): require __DIR__ . '/../partials/template-card.php'; endforeach ?>
    </div>
<?php endif ?>
  </div>
</div>
