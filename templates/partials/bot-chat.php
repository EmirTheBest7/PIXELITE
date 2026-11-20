<?php /** Telegram-style phone with a scripted chat (decorative sample). @var array $chat rows [who 'bot'|'me', text, ?buttons[]] @var string $chatName @var bool $chatSmall @var bool $chatBare only the bubbles (no phone frame, for the feature cards) */
$chatSmall ??= false; $chatBare ??= false; $chatName ??= ''; ?>
<?php if ($chatBare): ?>
    <div class="chat chat--bare" aria-hidden="true">
<?php foreach ($chat as $row): ?>
      <div class="chat__row chat__row--<?= $row[0] === 'me' ? 'me' : 'bot' ?>">
        <div class="chat__b"><?= e($row[1]) ?></div>
<?php if (!empty($row[2])): ?>
        <div class="chat__btns"><?php foreach ($row[2] as $b): ?><span class="chat__btn"><?= e($b) ?></span><?php endforeach ?></div>
<?php endif ?>
      </div>
<?php endforeach ?>
    </div>
<?php else: ?>
<div class="phone<?= $chatSmall ? ' phone--sm' : '' ?>" role="img" aria-label="<?= e(implode(' ', array_map(static fn(array $r): string => $r[1], $chat))) ?>">
  <div class="phone__screen" aria-hidden="true">
    <div class="phone__bar">
      <span class="phone__ava"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M21.4 3.6 2.7 10.8c-1 .4-1 1.2-.2 1.4l4.8 1.5 1.8 5.6c.2.6.4.8.8.8.4 0 .6-.2.9-.5l2.2-2.1 4.5 3.3c.8.5 1.4.2 1.6-.8l3-14.2c.3-1.2-.4-1.7-1.2-1.3zM8.5 13.2l9.5-6c.4-.3.8-.1.5.2l-7.8 7-.3 3.2-1.9-4.4z"/></svg></span>
      <span><span class="phone__name"><?= e($chatName) ?></span><br><span class="phone__sub">bot</span></span>
    </div>
    <div class="chat">
<?php foreach ($chat as $row): ?>
      <div class="chat__row chat__row--<?= $row[0] === 'me' ? 'me' : 'bot' ?>">
        <div class="chat__b"><?= e($row[1]) ?></div>
<?php if (!empty($row[2])): ?>
        <div class="chat__btns"><?php foreach ($row[2] as $b): ?><span class="chat__btn"><?= e($b) ?></span><?php endforeach ?></div>
<?php endif ?>
      </div>
<?php endforeach ?>
    </div>
  </div>
</div>
<?php endif ?>
