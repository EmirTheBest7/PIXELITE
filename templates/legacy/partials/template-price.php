<?php /** Price context for a gallery order: the Template-website package, same data and VAT wording as the pricing cards. */ ?>
<div class="tprice">
  <p class="tprice__line"><?= e(t('templates.price_line', ['price' => \Pixelite\TemplateCatalog::priceText()])) ?></p>
  <p class="tprice__vat"><?php if (vat_mode() !== ''): ?><?= e(t('vat.card_' . vat_mode())) ?><?php else: ?><span class="placeholder"><?= e(t('vat.unset')) ?></span><?php endif ?></p>
  <p class="tprice__note"><?= e(t('templates.cta_note')) ?></p>
</div>
