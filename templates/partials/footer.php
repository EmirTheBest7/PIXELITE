<footer class="footer">
  <div class="container">
    <div class="row">
      <div class="col-md-4 col-xs-12">
        <img class="footer__img" src="<?= e(asset('img/logo.png')) ?>" alt="" width="24" height="28"> <span class="footer__title">PIXELITE<span class="footer__light">.cz</span></span>
        <p class="footer__copy"><?= e(t('footer.rights', ['year' => date('Y')])) ?></p>
      </div>
      <div class="col-md-8 col-xs-12">
        <nav class="footer__links" aria-label="<?= e(t('a11y.nav_footer')) ?>">
          <a class="footer__link" href="<?= e(url('order')) ?>"><?= e(t('footer.order')) ?></a>
          <a class="footer__link" href="<?= e(url('contact')) ?>"><?= e(t('footer.contact')) ?></a>
          <a class="footer__link" href="<?= e(url('privacy')) ?>"><?= e(t('footer.privacy')) ?></a>
        </nav>
      </div>
    </div>
  </div>
</footer>
