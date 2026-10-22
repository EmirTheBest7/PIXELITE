<section class="sect sect--padding-top sect--padding-bottom" aria-labelledby="process-title">
  <div class="container">
    <div class="row row--center">
      <h1 class="row__title" id="process-title"><?= e(t('process.title')) ?></h1>
      <h2 class="row__sub"><?= e(t('process.subtitle')) ?></h2>
    </div>
    <div class="steps-wrap">
      <?php require __DIR__ . '/../partials/steps.php' ?>
    </div>
  </div>
</section>

<section class="sect sect--no-padding sect--padding-bottom" aria-labelledby="notes-title">
  <div class="container">
    <div class="row row--center">
      <h2 class="row__title" id="notes-title"><?= e(t('process.notes_title')) ?></h2>
    </div>
    <ul class="factors">
<?php foreach (t('process.notes') as $n): ?>
      <li><?= e($n) ?></li>
<?php endforeach ?>
    </ul>
    <p class="page-intro"><span class="placeholder"><?= e(t('process.placeholder')) ?></span></p>
    <p class="consult__cta"><a class="btn btn--revert btn--auto" href="<?= e(url('pricing')) ?>"><?= e(t('process.cta_secondary')) ?></a></p>
  </div>
</section>

<section class="sect sect--violet">
  <img src="<?= e(asset('img/band.png')) ?>" class="career-img" alt="">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h2 class="career_title"><?= e(t('process.cta_title')) ?></h2>
        <h3 class="career_sub"><?= e(t('process.cta_text')) ?></h3>
        <a href="<?= e(url('order')) ?>" class="btn btn--white btn--auto"><?= e(t('process.cta_button')) ?></a>
      </div>
    </div>
  </div>
</section>
