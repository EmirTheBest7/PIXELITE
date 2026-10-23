<?php $c = company(); $brand = '<span>pixelite<i>.cz</i></span>'; ?>
<footer class="footer">
  <div class="wrap">
    <div class="footer__top">
      <div>
        <a class="footer__brand" href="<?= e(url('')) ?>" aria-label="<?= e(t('a11y.home')) ?>"><?= brand_mark(34) ?><?= $brand ?></a>
        <address class="footer__company">
          <span class="footer__co-name"><?= company_field('name') ?></span><br>
          <span class="footer__co-label"><?= e(t('company.ico')) ?></span> <?= company_field('ico') ?>
<?php if ($c['dic'] !== ''): ?>
          <span aria-hidden="true">·</span> <span class="footer__co-label"><?= e(t('company.dic')) ?></span> <?= e($c['dic']) ?>
<?php endif ?><br>
          <span class="footer__co-label"><?= e(t('company.address')) ?></span> <?= company_field('address') ?><br>
<?php if ($c['register'] !== ''): ?>
          <?= e($c['register']) ?><br>
<?php endif ?>
          <span class="footer__co-label"><?= e(t('company.email')) ?></span> <?php if ($c['email'] !== ''): ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php else: ?><?= company_field('email') ?><?php endif ?>
<?php if ($c['phone'] !== ''): ?><br>
          <span class="footer__co-label"><?= e(t('company.phone')) ?></span> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>"><?= e($c['phone']) ?></a>
<?php endif ?>
        </address>
      </div>
      <nav class="footer__nav" aria-label="<?= e(t('a11y.nav_footer')) ?>">
        <a class="footer__link" href="<?= e(url('order')) ?>"><?= e(t('footer.order')) ?></a>
        <a class="footer__link" href="<?= e(url('templates')) ?>"><?= e(t('nav.templates')) ?></a>
        <a class="footer__link" href="<?= e(url('pricing')) ?>"><?= e(t('footer.pricing')) ?></a>
        <a class="footer__link" href="<?= e(url('process')) ?>"><?= e(t('footer.process')) ?></a>
        <a class="footer__link" href="<?= e(url('contact')) ?>"><?= e(t('footer.contact')) ?></a>
        <a class="footer__link" href="<?= e(url('team')) ?>"><?= e(t('footer.team')) ?></a>
        <a class="footer__link" href="<?= e(url('careers')) ?>"><?= e(t('footer.careers')) ?></a>
        <a class="footer__link" href="<?= e(url('login')) ?>"><?= e(t('footer.login')) ?></a>
        <a class="footer__link" href="<?= e(url('privacy')) ?>"><?= e(t('footer.privacy')) ?></a>
        <a class="footer__link" href="<?= e(url('cookies')) ?>"><?= e(t('footer.cookies')) ?></a>
        <a class="footer__link" href="<?= e(url('terms')) ?>"><?= e(t('footer.terms')) ?></a>
        <a class="footer__link" href="<?= e(url('cookies')) ?>#settings" data-cookie-settings><?= e(t('footer.cookie_settings')) ?></a>
        <a class="footer__link footer__link--version" href="<?= e(url_other_design($meta['path'])) ?>" rel="nofollow" data-design-switch><?= e(t('nav.legacy')) ?></a>
      </nav>
    </div>
    <div class="footer__bottom">
      <p><?= e(t('footer.rights', ['year' => date('Y')])) ?></p>
      <?php require __DIR__ . '/theme-switch.php' ?>
    </div>
  </div>
  <div class="footer__giant" aria-hidden="true">pixelite</div>
</footer>
