<?php /** @var list<array{id:string,name:string,role:string,photo:?string,tags:list<string>,bio:?string}> $members */
$tags = array_values(array_unique(array_merge(...array_map(fn($m) => $m['tags'], $members) ?: [[]])));
$initials = function (string $n): string { $o = ''; foreach (array_slice(preg_split('/\s+/u', trim($n)) ?: [], 0, 2) as $w) { $o .= mb_strtoupper(mb_substr($w, 0, 1)); } return $o; };
?>
<div class="sect sect--padding-top sect--padding-bottom">
  <div class="container">
    <div class="row">
      <h1 class="row__title"><?= e(t('team.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('team.subtitle')) ?></h2>
    </div>
<?php if (count($tags) >= 2): ?>
    <div class="team-filter" role="group" aria-label="<?= e(t('team.filter_label')) ?>" data-team-filter hidden>
      <button type="button" class="team-filter__btn" data-tag="" aria-pressed="true"><?= e(t('team.filter_all')) ?></button>
<?php foreach ($tags as $tag): ?>
      <button type="button" class="team-filter__btn" data-tag="<?= e($tag) ?>" aria-pressed="false"><?= e(t('team.tags.' . $tag)) ?></button>
<?php endforeach ?>
    </div>
<?php endif ?>
    <ul class="team-grid" data-team-grid>
<?php foreach ($members as $m): $role = (string) t('team.roles.' . $m['role']); ?>
      <li class="team-card" data-tags="<?= e(implode(' ', $m['tags'])) ?>">
        <article tabindex="0" aria-label="<?= e($m['name'] . ', ' . $role) ?>">
<?php if (!empty($m['photo'])): ?>
          <img class="team-card__photo" src="<?= e(asset($m['photo'])) ?>" alt="<?= e($m['name']) ?>" width="320" height="320" loading="lazy">
<?php else: ?>
          <div class="team-card__photo team-card__photo--mono" role="img" aria-label="<?= e(t('team.monogram', ['name' => $m['name']])) ?>"><span aria-hidden="true"><?= e($initials($m['name'])) ?></span></div>
<?php endif ?>
          <div class="team-card__body">
            <h3 class="team-card__name"><?= e($m['name']) ?></h3>
            <p class="team-card__role"><?= e($role) ?></p>
<?php if (!empty($m['bio'])): ?>
            <p class="team-card__bio"><?= e(t('team.bios.' . $m['bio'])) ?></p>
<?php endif ?>
          </div>
        </article>
      </li>
<?php endforeach ?>
    </ul>
    <p class="team-more"><?= e(t('team.more')) ?> <a href="<?= e(url('contact')) ?>"><?= e(t('team.contact')) ?></a></p>
  </div>
</div>
