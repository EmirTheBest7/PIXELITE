<?php /** @var array $filters @var array $result */
$cats = \Pixelite\TemplateCatalog::categories(); $styles = \Pixelite\TemplateCatalog::styles();
$qs = fn(array $over): string => http_build_query(array_filter(array_merge($filters, $over, ['page' => $over['page'] ?? null]), static fn($v, $k) => $v !== '' && $v !== null && !($k === 'sort' && $v === 'featured') && !($k === 'page' && (int) $v <= 1), ARRAY_FILTER_USE_BOTH));
$pageUrl = fn(int $p): string => url('templates') . (($s = $qs(['page' => $p])) !== '' ? '?' . $s : '');
?>
<div class="sect sect--padding-top">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('templates.hero_title')) ?></h1>
      <h2 class="row__sub"><?= e(t('templates.hero_sub')) ?></h2>
    </div>
    <ol class="tsteps" aria-label="<?= e(t('nav.process')) ?>">
<?php foreach (t('templates.how') as $i => $step): ?>
      <li><span class="tsteps__n" aria-hidden="true"><?= $i + 1 ?></span> <?= e($step) ?></li>
<?php endforeach ?>
    </ol>
    <form class="tfilters" method="get" action="<?= e(url('templates')) ?>" role="search" aria-label="<?= e(t('templates.search_label')) ?>">
      <div class="tfilters__search">
        <label class="sr-only" for="t-q"><?= e(t('templates.search_label')) ?></label>
        <input class="form__field form__text" id="t-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('templates.search_ph')) ?>" maxlength="100" autocomplete="off">
      </div>
<?php if (count($cats) > 1): ?>
      <div class="tfilters__field">
        <label for="t-cat"><?= e(t('templates.category')) ?></label>
        <select class="form__field form__select" id="t-cat" name="category">
          <option value=""><?= e(t('templates.all')) ?></option>
<?php foreach ($cats as $c): ?>
          <option value="<?= e($c) ?>"<?= $filters['category'] === $c ? ' selected' : '' ?>><?= e(t("templates.categories.$c.name")) ?></option>
<?php endforeach ?>
        </select>
      </div>
<?php endif ?>
<?php if (count($styles) > 1): ?>
      <div class="tfilters__field">
        <label for="t-style"><?= e(t('templates.style')) ?></label>
        <select class="form__field form__select" id="t-style" name="style">
          <option value=""><?= e(t('templates.all')) ?></option>
<?php foreach ($styles as $st): ?>
          <option value="<?= e($st) ?>"<?= $filters['style'] === $st ? ' selected' : '' ?>><?= e(t("templates.styles.$st")) ?></option>
<?php endforeach ?>
        </select>
      </div>
<?php endif ?>
<?php if (count(\Pixelite\TemplateCatalog::all()) > 1): ?>
      <div class="tfilters__field">
        <label for="t-sort"><?= e(t('templates.sort')) ?></label>
        <select class="form__field form__select" id="t-sort" name="sort">
          <option value="featured"<?= $filters['sort'] === 'featured' ? ' selected' : '' ?>><?= e(t('templates.sort_featured')) ?></option>
          <option value="az"<?= $filters['sort'] === 'az' ? ' selected' : '' ?>><?= e(t('templates.sort_az')) ?></option>
        </select>
      </div>
<?php endif ?>
      <div class="tfilters__actions">
        <button class="btn btn--auto" type="submit"><?= e(t('templates.filters_apply')) ?></button>
<?php if ($result['filtered']): ?>
        <a class="tfilters__reset" href="<?= e(url('templates')) ?>"><?= e(t('templates.reset')) ?></a>
<?php endif ?>
      </div>
    </form>
    <p class="tresults" role="status" aria-live="polite"><?= e($result['total'] === 1 ? t('templates.results_one') : t('templates.results_many', ['n' => $result['total']])) ?></p>
<?php if (!$result['items']): ?>
    <div class="tempty">
      <h3 class="tempty__title"><?= e(t('templates.empty_title')) ?></h3>
      <p><?= e(t('templates.empty_text')) ?></p>
      <a class="btn btn--auto" href="<?= e(url('templates')) ?>"><?= e(t('templates.reset')) ?></a>
    </div>
<?php else: ?>
    <div class="tgrid">
<?php foreach ($result['items'] as $t): require __DIR__ . '/../partials/template-card.php'; endforeach ?>
    </div>
<?php if ($result['pages'] > 1): ?>
    <nav class="tpager" aria-label="<?= e(t('templates.pagination')) ?>">
<?php if ($result['page'] > 1): ?><a class="btn btn--revert btn--auto" rel="prev" href="<?= e($pageUrl($result['page'] - 1)) ?>"><?= e(t('templates.prev')) ?></a><?php endif ?>
      <span><?= e(t('templates.page_of', ['p' => $result['page'], 'n' => $result['pages']])) ?></span>
<?php if ($result['page'] < $result['pages']): ?><a class="btn btn--revert btn--auto" rel="next" href="<?= e($pageUrl($result['page'] + 1)) ?>"><?= e(t('templates.next')) ?></a><?php endif ?>
    </nav>
<?php endif ?>
<?php endif ?>
  </div>
</div>
<div class="sect sect--violet">
  <div class="container">
    <div class="row">
      <h2 class="career_title"><?= e(t('templates.gallery_cta_title')) ?></h2>
      <h3 class="career_sub"><?= e(t('templates.gallery_cta_text')) ?></h3>
      <a class="btn btn--white btn--auto" href="<?= e(url('order')) ?>"><?= e(t('templates.gallery_cta_btn')) ?></a>
    </div>
  </div>
</div>
