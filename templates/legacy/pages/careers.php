<?php /** @var list<array> $vacancies published only */
$mail = contact()['email'];
$mailto = fn(string $subject = ''): string => 'mailto:' . $mail . ($subject !== '' ? '?subject=' . rawurlencode($subject) : '');
?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('careers.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('careers.subtitle')) ?></h2>
    </div>
<?php if (!$vacancies): ?>
    <div class="row row--margin">
      <div class="col-md-8 col-md-offset-2">
        <div class="cta-card careers-none">
          <h3 class="cta-card__title"><?= e(t('careers.none_title')) ?></h3>
          <p class="cta-card__text"><?= e(t('careers.none_text')) ?></p>
<?php if ($mail !== ''): ?>
          <a class="btn btn--auto" href="<?= e($mailto()) ?>"><?= e(t('careers.email_cta')) ?></a>
          <p class="careers-none__mail"><a href="<?= e($mailto()) ?>"><?= e($mail) ?></a></p>
<?php else: ?>
          <p class="careers-none__mail"><span class="placeholder"><?= e(t('careers.email_unset')) ?></span></p>
<?php endif ?>
        </div>
      </div>
    </div>
<?php else: ?>
    <div class="row row--margin">
      <div class="col-md-8 col-md-offset-2">
        <h3 class="careers-open__title"><?= e(t('careers.open_title')) ?></h3>
        <p class="careers-open__text"><?= e(t('careers.open_text')) ?></p>
<?php foreach ($vacancies as $v): $x = \Pixelite\Careers::text($v); ?>
        <article class="vacancy" aria-labelledby="v-<?= (int) $v['id'] ?>">
          <h3 class="vacancy__title" id="v-<?= (int) $v['id'] ?>"><?= e($x['title']) ?></h3>
<?php if (!empty($v['published_at'])): ?>
          <p class="vacancy__meta"><?= e(t('careers.published', ['date' => substr((string) $v['published_at'], 0, 10)])) ?></p>
<?php endif ?>
<?php foreach (preg_split('/\n{2,}/', $x['description']) ?: [] as $para): ?>
          <p class="vacancy__text"><?= nl2br(e($para)) ?></p>
<?php endforeach ?>
<?php if ($mail !== ''): ?>
          <a class="btn btn--auto" href="<?= e($mailto((string) t('careers.subject', ['title' => $x['title']]))) ?>"><?= e(t('careers.apply')) ?></a>
<?php else: ?>
          <p><span class="placeholder"><?= e(t('careers.email_unset')) ?></span></p>
<?php endif ?>
        </article>
<?php endforeach ?>
        <p class="vacancy__more"><?= e(t('careers.spontaneous')) ?><?php if ($mail !== ''): ?> <a href="<?= e($mailto()) ?>"><?= e($mail) ?></a><?php endif ?></p>
      </div>
    </div>
<?php endif ?>
  </div>
</div>
