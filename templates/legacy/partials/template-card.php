<?php /** @var array $t localised catalog entry */
$m = $t['media']; $detail = url('templates/' . $t['slug']); ?>
<article class="tcard" data-template-card>
  <div class="tcard__media" data-preview-media>
    <img class="tcard__poster" src="<?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $m['poster']['640'])) ?>" srcset="<?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $m['poster']['640'])) ?> 640w, <?= e(\Pixelite\TemplateCatalog::mediaUrl($t, $m['poster']['1280'])) ?> 1280w" sizes="(min-width: 1200px) 360px, (min-width: 768px) 45vw, 92vw" width="640" height="400" loading="lazy" decoding="async" alt="">
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
      <a class="btn btn--revert btn--auto" href="<?= e($detail) ?>"><?= e(t('templates.view')) ?><span class="sr-only">: <?= e($t['title']) ?></span></a>
      <a class="btn btn--auto" href="<?= e(url('order') . '?template=' . $t['slug']) ?>"><?= e(t('templates.choose')) ?><span class="sr-only">: <?= e($t['title']) ?></span></a>
    </div>
  </div>
</article>
