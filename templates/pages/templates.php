<?php /** @var array $filters @var array $result */
require_once __DIR__ . '/../partials/ui.php';
$cats = \Pixelite\TemplateCatalog::categories(); $styles = \Pixelite\TemplateCatalog::styles();
$qs = fn(array $over): string => http_build_query(array_filter(array_merge($filters, $over, ['page' => $over['page'] ?? null]), static fn($v, $k) => $v !== '' && $v !== null && !($k === 'sort' && $v === 'featured') && !($k === 'page' && (int) $v <= 1), ARRAY_FILTER_USE_BOTH));
$pageUrl = fn(int $p): string => url('templates') . (($s = $qs(['page' => $p])) !== '' ? '?' . $s : '');
?>
<?php $ph = ['label' => (string) t('nav.templates'), 'title' => (string) t('templates.hero_title'), 'sub' => (string) t('templates.hero_sub')];
require __DIR__ . '/../partials/page-hero.php' ?>
<section class="section" aria-label="<?= e(t('nav.templates')) ?>">
  <div class="wrap">
    <ol class="tsteps" aria-label="<?= e(t('nav.process')) ?>">
<?php foreach (t('templates.how') as $i => $step): ?>
      <li><span class="tsteps__n" aria-hidden="true"><?= $i + 1 ?></span> <span><?= e($step) ?></span></li>
<?php endforeach ?>
    </ol>
    <form class="tfilters" method="get" action="<?= e(url('templates')) ?>" role="search" aria-label="<?= e(t('templates.search_label')) ?>">
      <div class="tfilters__field">
        <label for="t-q"><?= e(t('templates.search_label')) ?></label>
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
        <?= ui_button((string) t('templates.filters_apply'), 'accent', ['type' => 'submit']) ?>
<?php if ($result['filtered']): ?>
        <a class="tfilters__reset" href="<?= e(url('templates')) ?>"><?= e(t('templates.reset')) ?></a>
<?php endif ?>
      </div>
    </form>
    <p class="tresults" role="status" aria-live="polite"><?= e($result['total'] === 1 ? t('templates.results_one') : t('templates.results_many', ['n' => $result['total']])) ?></p>
<?php if (!$result['items']): ?>
    <div class="tempty">
      <h2 class="h-md"><?= e(t('templates.empty_title')) ?></h2>
      <p><?= e(t('templates.empty_text')) ?></p>
      <?= ui_link(url('templates'), (string) t('templates.reset'), 'accent') ?>
    </div>
<?php else: ?>
    <div class="tgrid">
<?php foreach ($result['items'] as $t): require __DIR__ . '/../partials/template-card.php'; endforeach ?>
    </div>
<?php if ($result['pages'] > 1): ?>
    <nav class="tpager" aria-label="<?= e(t('templates.pagination')) ?>">
<?php if ($result['page'] > 1): ?><?= ui_link($pageUrl($result['page'] - 1), (string) t('templates.prev'), 'sm ghost', ['rel' => 'prev']) ?><?php endif ?>
      <span><?= e(t('templates.page_of', ['p' => $result['page'], 'n' => $result['pages']])) ?></span>
<?php if ($result['page'] < $result['pages']): ?><?= ui_link($pageUrl($result['page'] + 1), (string) t('templates.next'), 'sm ghost', ['rel' => 'next']) ?><?php endif ?>
    </nav>
<?php endif ?>
<?php endif ?>
  </div>
</section>

<section class="section section--tight-top" aria-labelledby="gcta-title">
  <div class="wrap">
    <div class="band">
      <div>
        <h2 class="band__title" id="gcta-title"><?= e(t('templates.gallery_cta_title')) ?></h2>
        <p class="band__sub"><?= e(t('templates.gallery_cta_text')) ?></p>
      </div>
      <div class="band__actions"><?= ui_link(url('order'), (string) t('templates.gallery_cta_btn'), 'light') ?></div>
    </div>
  </div>
</section>
