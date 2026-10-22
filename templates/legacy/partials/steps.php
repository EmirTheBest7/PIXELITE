<?php /** Numbered workflow steps. @var bool $stepsCompact short one-line version (landing page). Data: lang process.steps. */ $stepsCompact ??= false; ?>
<ol class="steps<?= $stepsCompact ? ' steps--compact' : '' ?>">
<?php foreach (t('process.steps') as $step): ?>
  <li class="steps__item">
    <h3 class="steps__title"><?= e($step['title']) ?></h3>
    <p class="steps__text"><?= e($stepsCompact ? $step['short'] : $step['text']) ?></p>
  </li>
<?php endforeach ?>
</ol>
