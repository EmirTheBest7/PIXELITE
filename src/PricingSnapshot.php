<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Historical record of the public pricing context a customer saw when choosing a package.
 * Captured server-side at submission (never from the browser) and stored with the order, so later price or
 * wording changes cannot rewrite history. `orders.package` stays the canonical id; these fields are display-only,
 * and a starting price is NOT the agreed contract price.
 */
final class PricingSnapshot
{
    /** @return array{package_name:string,package_price:string,price_vat_mode:string} all '' when no package was chosen */
    public static function capture(string $packageId, string $locale): array
    {
        $empty = ['package_name' => '', 'package_price' => '', 'price_vat_mode' => ''];
        if ($packageId === '') {
            return $empty;
        }
        foreach (I18n::load($locale)['services']['items'] as $item) {
            if ($item['id'] === $packageId) {
                return [
                    'package_name' => (string) $item['name'],                                                        // as displayed, in the customer's language
                    'package_price' => $item['from'] . ' ' . $item['amount'] . ' ' . $item['currency'],             // "<from> <amount> <currency>" exactly as rendered
                    'price_vat_mode' => vat_mode() ?: 'unset',                                                       // 'unset' = the placeholder was showing
                ];
            }
        }
        return $empty;
    }

    private const VAT_LABELS = [
        'incl' => 'prices include VAT',
        'excl' => 'prices exclude VAT',
        'none' => 'not a VAT payer, no VAT added',
        'unset' => 'VAT info was not configured',
    ];

    /**
     * What to show for a stored order. Uses ONLY stored values (never today's translations).
     * @return ?array{id:string,name:string,price:string,vat:string,recorded:bool}  null when no package was chosen
     */
    public static function describe(array $order): ?array
    {
        $id = (string) ($order['package'] ?? '');
        if ($id === '') {
            return null;
        }
        $name = (string) ($order['package_name'] ?? '');
        $price = (string) ($order['package_price'] ?? '');
        $mode = (string) ($order['price_vat_mode'] ?? '');
        return [
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'vat' => self::VAT_LABELS[$mode] ?? '',
            'recorded' => $name !== '' && $price !== '',   // false for orders placed before snapshots existed
        ];
    }
}
