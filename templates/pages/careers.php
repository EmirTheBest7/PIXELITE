<?php /** @var list<array> $vacancies */
require_once __DIR__ . '/../partials/ui.php';
$mail = contact()['email'];
$mailto = fn(string $s = ''): string => 'mailto:' . $mail . ($s !== '' ? '?subject=' . rawurlencode($s) : '');
$ph = ['label' => (string) t('footer.careers'), 'title' => (string) t('careers.title'), 'sub' => (string) t('careers.subtitle')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-label="<?= e(t('careers.title')) ?>">
  <div class="wrap">
<?php if (!$vacancies): ?>
    <div class="cta-card careers-none">
      <h2 class="h-md"><?= e(t('careers.none_title')) ?></h2>
      <p class="cta-card__text"><?= e(t('careers.none_text')) ?></p>
<?php if ($mail !== ''): ?><?= ui_link($mailto(), (string) t('careers.email_cta'), 'accent') ?><p class="auth-note"><a class="link" href="<?= e($mailto()) ?>"><?= e($mail) ?></a></p>
<?php else: ?><p><span class="placeholder"><?= e(t('careers.email_unset')) ?></span></p><?php endif ?>
    </div>
<?php else: ?>
    <div class="vacancies">
      <h2 class="h-md"><?= e(t('careers.open_title')) ?></h2><p class="lede"><?= e(t('careers.open_text')) ?></p>
<?php foreach ($vacancies as $v): $x = \Pixelite\Careers::text($v); ?>
      <article class="vacancy" aria-labelledby="v-<?= (int) $v['id'] ?>">
        <h3 class="vacancy__title" id="v-<?= (int) $v['id'] ?>"<?= $x['fallback'] ? ' lang="en"' : '' ?>><?= e($x['title']) ?></h3>
<?php if (!empty($v['published_at'])): ?><p class="vacancy__meta"><?= e(t('careers.published', ['date' => substr((string) $v['published_at'], 0, 10)])) ?></p><?php endif ?>
<?php if ($x['fallback']): ?><p class="vacancy__meta"><?= e(t('careers.english_only')) ?></p><?php endif ?>
<?php foreach (preg_split('/\n{2,}/', $x['description']) ?: [] as $para): ?><p class="vacancy__text"<?= $x['fallback'] ? ' lang="en"' : '' ?>><?= nl2br(e($para)) ?></p><?php endforeach ?>
<?php if ($mail !== ''): ?><?= ui_link($mailto((string) t('careers.subject', ['title' => $x['title']])), (string) t('careers.apply'), 'accent') ?><?php else: ?><p><span class="placeholder"><?= e(t('careers.email_unset')) ?></span></p><?php endif ?>
      </article>
<?php endforeach ?>
      <p class="acct__hint"><?= e(t('careers.spontaneous')) ?><?php if ($mail !== ''): ?> <a class="link" href="<?= e($mailto()) ?>"><?= e($mail) ?></a><?php endif ?></p>
    </div>
<?php endif ?>
  </div>
</section>
