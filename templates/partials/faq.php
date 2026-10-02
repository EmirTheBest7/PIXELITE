<?php /** Native <details> accordion. @var ?array $faqIds limit to these ids (null = all). Data: lang faq.items. */ $faqIds ??= null; ?>
<div class="faq">
<?php foreach (t('faq.items') as $id => $item): if ($faqIds !== null && !in_array($id, $faqIds, true)) continue; ?>
  <details class="faq__item">
    <summary class="faq__q"><?= e($item['q']) ?></summary>
    <p class="faq__a"><?= e(legal_text($item['a'])) ?></p>
  </details>
<?php endforeach ?>
</div>
