<?php /** Inner-page hero. @var array{label?:string,title:string,sub?:string} $ph */ require_once __DIR__ . '/ui.php'; ?>
<header class="phero">
  <div class="wrap">
<?php if (!empty($ph['label'])): ?>
    <div class="eyebrow"><span class="eyebrow__label"><?= e($ph['label']) ?></span></div>
<?php endif ?>
    <h1 class="phero__title"><?= e($ph['title']) ?></h1>
<?php if (!empty($ph['sub'])): ?>
    <p class="phero__sub"><?= e($ph['sub']) ?></p>
<?php endif ?>
  </div>
</header>
