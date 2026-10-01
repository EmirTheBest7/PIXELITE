<?php $c = contact(); ?>
<div class="contacts">
  <a href="<?= e(url('')) ?>" class="contacts__link"><?= logo_mark('contacts__img') ?> <span class="contacts_title-ag">PIXELITE<span class="contacts--light">.cz</span></span></a>
<?php if (!array_filter($c)): ?>
  <p class="contacts__address"><?= e(t('contact.unset')) ?></p>
<?php else: ?>
<?php if ($c['location']): ?>
  <p class="contacts__address"><?= nl2br(e($c['location'])) ?></p>
<?php endif ?>
<?php if ($c['phone']): ?>
  <p class="contacts__info"><?= e(t('contact.phone')) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>" class="contacts__info-link"><?= e($c['phone']) ?></a></p>
<?php endif ?>
<?php if ($c['email']): ?>
  <p class="contacts__info"><?= e(t('contact.email')) ?> <a href="mailto:<?= e($c['email']) ?>" class="contacts__info-link"><?= e($c['email']) ?></a></p>
<?php endif ?>
<?php endif ?>
</div>
