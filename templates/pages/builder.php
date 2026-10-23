<?php
/** Public site builder landing page. @var bool $available @var bool $hasWorkspace @var string $startToken @var string $error @var int $ttlHours @var array $limits */
require_once __DIR__ . '/../partials/ui.php';
$repl = ['{hours}' => (string) $ttlHours, '{pages}' => (string) $limits['pages'], '{images}' => (string) $limits['media_files']];
$fill = static fn(string $s): string => strtr($s, $repl);
$ph = ['label' => (string) t('nav.builder'), 'title' => (string) t('builder.title'), 'sub' => (string) t('builder.subtitle')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-labelledby="start-title">
  <div class="wrap">
    <div class="card">
      <h2 class="acct__title" id="start-title"><?= e(t('builder.start_title')) ?></h2>
<?php if (!$available): ?>
      <div class="form__alert" role="status"><?= e(t('builder.errors.unavailable')) ?></div>
<?php else: ?>
      <p class="page-intro"><?= e($fill((string) t('builder.start_text'))) ?></p>
<?php if ($error !== ''): ?>
      <div class="form__alert" role="alert" tabindex="-1" data-form-alert><?= e(t('builder.errors.' . $error)) ?></div>
<?php endif ?>
      <form method="post" action="<?= e(url('builder')) ?>" class="form">
        <input type="hidden" name="_start" value="<?= e($startToken) ?>">
        <div class="form__actions">
<?php if ($hasWorkspace): ?>
          <?= ui_link('/builder/w', (string) t('builder.continue'), 'accent', ['rel' => 'nofollow']) ?>
          <?= ui_button((string) t('builder.restart'), 'ghost plain', ['type' => 'submit', 'name' => 'action', 'value' => 'new']) ?>
<?php else: ?>
          <?= ui_button((string) t('builder.start'), 'accent', ['type' => 'submit', 'name' => 'action', 'value' => 'start']) ?>
<?php endif ?>
        </div>
      </form>
      <p class="acct__hint"><?= e($fill((string) t('builder.cookie_note'))) ?> <a href="<?= e(url('cookies')) ?>"><?= e(t('builder.cookies_link')) ?></a></p>
<?php endif ?>
    </div>
  </div>
</section>

<section class="section section--soft" aria-labelledby="how-title">
  <div class="wrap">
    <div class="sec-head"><h2 class="h-lg" id="how-title"><?= e(t('builder.how_title')) ?></h2></div>
    <ul class="factors">
<?php foreach ((array) t('builder.how') as $line): ?>
      <li><?= e($fill((string) $line)) ?></li>
<?php endforeach ?>
    </ul>
  </div>
</section>

<section class="section" aria-labelledby="limits-title">
  <div class="wrap">
    <div class="sec-head"><h2 class="h-lg" id="limits-title"><?= e(t('builder.limits_title')) ?></h2></div>
    <ul class="factors">
<?php foreach ((array) t('builder.limits') as $line): ?>
      <li><?= e($fill((string) $line)) ?></li>
<?php endforeach ?>
    </ul>
  </div>
</section>

<section class="section" aria-labelledby="cta-title">
  <div class="wrap">
    <div class="band">
      <div>
        <h2 class="band__title" id="cta-title"><?= e(t('builder.cta_title')) ?></h2>
        <p class="band__sub"><?= e(t('builder.cta_text')) ?></p>
      </div>
      <div class="band__actions"><?= ui_link(url('order'), (string) t('builder.cta_button'), 'light') ?></div>
    </div>
  </div>
</section>
