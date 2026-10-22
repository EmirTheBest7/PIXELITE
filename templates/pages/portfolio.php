<?php /** @var array $projects @var string $locale */
require_once __DIR__ . '/../partials/ui.php';
$ph = ['label' => (string) t('portfolio.title'), 'title' => (string) t('portfolio.title'), 'sub' => (string) t('portfolio.subtitle')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-label="<?= e(t('portfolio.title')) ?>">
  <div class="wrap">
<?php if (!$projects): ?>
    <p class="lede"><?= e(t('portfolio.empty')) ?></p>
<?php else: ?>
    <div class="pf">
<?php foreach ($projects as $p): $tag = empty($p['url']) ? 'div' : 'a'; ?>
      <<?= $tag ?> class="article-pre"<?= $tag === 'a' ? ' href="' . e($p['url']) . '" rel="noopener"' : '' ?>>
        <div class="article-pre__img"></div>
        <p class="article-pre__info">
          <span class="article-pre__cat"><?= e(localized($p['category'], $locale)) ?></span>
<?php if (!empty($p['placeholder'])): ?>
          <span class="portfolio__badge"><?= e(t('portfolio.placeholder')) ?></span>
<?php endif ?>
        </p>
        <h2 class="article-pre__title"><?= e(localized($p['title'], $locale)) ?></h2>
        <p class="portfolio__summary"><?= e(localized($p['summary'], $locale)) ?></p>
<?php if (!empty($p['placeholder'])): ?>
        <p class="portfolio__note"><?= e(t('portfolio.placeholder_note')) ?></p>
<?php endif ?>
      </<?= $tag ?>>
<?php endforeach ?>
    </div>
<?php endif ?>
  </div>
</section>
