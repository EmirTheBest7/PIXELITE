<?php /** @var array $projects @var string $locale */ ?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('portfolio.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('portfolio.subtitle')) ?></h2>
    </div>
<?php if (!$projects): ?>
    <p class="services__more"><?= e(t('portfolio.empty')) ?></p>
<?php else: ?>
    <div class="row row--margin">
<?php foreach ($projects as $p): $tag = empty($p['url']) ? 'div' : 'a'; ?>
      <div class="col-md-6 article-pre__col">
        <<?= $tag ?> class="article-pre"<?= $tag === 'a' ? ' href="' . e($p['url']) . '" rel="noopener"' : '' ?>>
          <div class="article-pre__img article-pre__img--placeholder"></div>
          <p class="article-pre__info">
            <span class="article-pre__cat"><?= e($p['category'][$locale] ?? '') ?></span>
<?php if (!empty($p['placeholder'])): ?>
            <span class="article-pre__date portfolio__badge"><?= e(t('portfolio.placeholder')) ?></span>
<?php endif ?>
          </p>
          <h3 class="article-pre__title"><?= e($p['title'][$locale] ?? '') ?></h3>
          <p class="portfolio__summary"><?= e($p['summary'][$locale] ?? '') ?></p>
<?php if (!empty($p['placeholder'])): ?>
          <p class="portfolio__note"><?= e(t('portfolio.placeholder_note')) ?></p>
<?php endif ?>
        </<?= $tag ?>>
      </div>
<?php endforeach ?>
    </div>
<?php endif ?>
  </div>
</div>
