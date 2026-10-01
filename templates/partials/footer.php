<?php $c = company(); ?>
<footer class="footer">
  <div class="container">
    <div class="row">
      <div class="col-md-6 col-xs-12">
        <?= logo_mark('footer__img') ?> <span class="footer__title">PIXELITE<span class="footer__light">.cz</span></span>
        <address class="footer__company">
          <span class="footer__co-name"><?= company_field('name') ?></span><br>
          <span class="footer__co-label"><?= e(t('company.ico')) ?></span> <?= company_field('ico') ?>
<?php if ($c['dic'] !== ''): ?>
          <span class="footer__sep" aria-hidden="true">·</span> <span class="footer__co-label"><?= e(t('company.dic')) ?></span> <?= e($c['dic']) ?>
<?php endif ?><br>
          <span class="footer__co-label"><?= e(t('company.address')) ?></span> <?= company_field('address') ?><br>
<?php if ($c['register'] !== ''): ?>
          <?= e($c['register']) ?><br>
<?php endif ?>
          <span class="footer__co-label"><?= e(t('company.email')) ?></span> <?php if ($c['email'] !== ''): ?><a class="footer__link footer__link--inline" href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php else: ?><?= company_field('email') ?><?php endif ?>
<?php if ($c['phone'] !== ''): ?><br>
          <span class="footer__co-label"><?= e(t('company.phone')) ?></span> <a class="footer__link footer__link--inline" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>"><?= e($c['phone']) ?></a>
<?php endif ?>
        </address>
        <p class="footer__copy"><?= e(t('footer.rights', ['year' => date('Y')])) ?></p>
      </div>
      <div class="col-md-6 col-xs-12">
        <nav class="footer__links" aria-label="<?= e(t('a11y.nav_footer')) ?>">
          <a class="footer__link" href="<?= e(url('order')) ?>"><?= e(t('footer.order')) ?></a>
          <a class="footer__link" href="<?= e(url('contact')) ?>"><?= e(t('footer.contact')) ?></a>
          <a class="footer__link" href="<?= e(url('privacy')) ?>"><?= e(t('footer.privacy')) ?></a>
          <a class="footer__link" href="<?= e(url('cookies')) ?>"><?= e(t('footer.cookies')) ?></a>
          <a class="footer__link" href="<?= e(url('terms')) ?>"><?= e(t('footer.terms')) ?></a>
          <a class="footer__link" href="<?= e(url('cookies')) ?>#settings" data-cookie-settings><?= e(t('footer.cookie_settings')) ?></a>
        </nav>
        <div class="footer__theme"><?php require __DIR__ . '/theme-switch.php' ?></div>
      </div>
    </div>
  </div>
</footer>
