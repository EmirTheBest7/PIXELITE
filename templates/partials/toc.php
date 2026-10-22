<?php /** Legal-page table of contents. @var list<array{0:string,1:string}> $tocItems [id, label]. */
$tocLabel = (string) t('a11y.toc'); ?>
<nav class="toc" aria-label="<?= e($tocLabel) ?>" data-toc>
  <p class="toc__title"><?= e($tocLabel) ?></p>
  <ol>
<?php foreach ($tocItems as [$id, $label]): ?>
    <li><a href="#<?= e($id) ?>"><?= e($label) ?></a></li>
<?php endforeach ?>
  </ol>
</nav>
