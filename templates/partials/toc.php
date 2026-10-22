<?php /** Legal-page table of contents. @var list<array{0:string,1:string}> $tocItems [id, label]. Strings are concept-local: lang/ is shared with the untouched original. */
$tocLabel = \Pixelite\I18n::locale() === 'cs' ? 'Obsah stránky' : 'On this page'; ?>
<nav class="toc" aria-label="<?= e($tocLabel) ?>" data-toc>
  <p class="toc__title"><?= e($tocLabel) ?></p>
  <ol>
<?php foreach ($tocItems as [$id, $label]): ?>
    <li><a href="#<?= e($id) ?>"><?= e($label) ?></a></li>
<?php endforeach ?>
  </ol>
</nav>
