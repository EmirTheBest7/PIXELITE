<?php
/** Public site builder landing page (legacy design). @var bool $available @var bool $hasWorkspace @var string $startToken @var string $error @var int $ttlHours @var array $limits */
$repl = ['{hours}' => (string) $ttlHours, '{pages}' => (string) $limits['pages'], '{images}' => (string) $limits['media_files']];
$fill = static fn(string $s): string => strtr($s, $repl);
?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('builder.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('builder.subtitle')) ?></h2>
    </div>
    <div class="row row--margin">
      <div class="col-md-1"></div>
      <div class="col-md-10">
        <div class="cta-card">
          <h3 class="cta-card__title"><?= e(t('builder.start_title')) ?></h3>
<?php if (!$available): ?>
          <p class="cta-card__text"><?= e(t('builder.errors.unavailable')) ?></p>
<?php else: ?>
          <p class="cta-card__text"><?= e($fill((string) t('builder.start_text'))) ?></p>
<?php if ($error !== ''): ?>
          <p class="cta-card__text" role="alert"><strong><?= e(t('builder.errors.' . $error)) ?></strong></p>
<?php endif ?>
          <form method="post" action="<?= e(url('builder')) ?>">
            <input type="hidden" name="_start" value="<?= e($startToken) ?>">
<?php if ($hasWorkspace): ?>
            <a class="btn btn--auto" href="/builder/w" rel="nofollow"><?= e(t('builder.continue')) ?></a>
            <button class="btn btn--auto btn--revert" type="submit" name="action" value="new"><?= e(t('builder.restart')) ?></button>
<?php else: ?>
            <button class="btn btn--auto" type="submit" name="action" value="start"><?= e(t('builder.start')) ?></button>
<?php endif ?>
          </form>
          <p class="cta-card__text"><?= e($fill((string) t('builder.cookie_note'))) ?> <a href="<?= e(url('cookies')) ?>"><?= e(t('builder.cookies_link')) ?></a></p>
<?php endif ?>
        </div>
        <h3><?= e(t('builder.how_title')) ?></h3>
        <ul class="factors">
<?php foreach ((array) t('builder.how') as $line): ?>
          <li><?= e($fill((string) $line)) ?></li>
<?php endforeach ?>
        </ul>
        <h3><?= e(t('builder.limits_title')) ?></h3>
        <ul class="factors">
<?php foreach ((array) t('builder.limits') as $line): ?>
          <li><?= e($fill((string) $line)) ?></li>
<?php endforeach ?>
        </ul>
        <p><a class="btn btn--auto" href="<?= e(url('order')) ?>"><?= e(t('builder.cta_button')) ?></a></p>
      </div>
      <div class="col-md-1"></div>
    </div>
  </div>
</div>
