<?php /** @var array $t localised catalog entry */
require_once __DIR__ . '/ui.php';
$m = $t['media']; $detail = url('templates/' . $t['slug']); ?>
<article class="tcard" data-template-card>
  <div class="tcard__media" data-preview-media>
    <img class="tcard__poster" src="<?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $m['poster']['640'])) ?>" srcset="<?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $m['poster']['640'])) ?> 640w, <?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $m['poster']['1280'])) ?> 1280w" sizes="(min-width: 1100px) 30vw, (min-width: 700px) 45vw, 92vw" width="640" height="400" loading="lazy" decoding="async" alt="">
    <video class="tcard__video" muted loop playsinline preload="none" tabindex="-1" aria-hidden="true" data-src="<?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $m['video'])) ?>"></video>
    <button type="button" class="tcard__play" data-preview-toggle hidden aria-pressed="false" data-label-play="<?= e(t('templates.play')) ?>" data-label-pause="<?= e(t('templates.pause')) ?>" data-label-failed="<?= e(t('templates.preview_failed')) ?>"><span class="tcard__play-icon" aria-hidden="true"></span><span class="tcard__play-label"><?= e(t('templates.play')) ?></span></button>
  </div>
  <div class="tcard__body">
    <h3 class="tcard__title"><a href="<?= e($detail) ?>"><?= e($t['title']) ?></a></h3>
    <p class="tcard__desc"><?= e($t['desc']) ?></p>
    <ul class="tcard__tags">
      <li><?= e(t('templates.categories.' . $t['category'] . '.name')) ?></li>
      <li><?= e(t('templates.styles.' . $t['style'])) ?></li>
<?php foreach (array_slice($t['tags'], 0, 2) as $tag): ?>
      <li><?= e(t("templates.tags.$tag")) ?></li>
<?php endforeach ?>
    </ul>
    <div class="tcard__actions">
      <?= ui_link($detail, (string) t('templates.view'), 'sm ghost', [], $t['title']) ?>
      <?= ui_link(url('order') . '?template=' . $t['slug'], (string) t('templates.choose'), 'sm accent', [], $t['title']) ?>
    </div>
  </div>
</article>
