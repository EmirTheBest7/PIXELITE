<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Legal identity shown in the footer, privacy policy and terms. Everything comes from .env; nothing is hard-coded.
 * Empty values stay empty here — templates render a visible "to be completed" placeholder instead of inventing data.
 */
final class Company
{
    /** @return array{name:string,ico:string,dic:string,address:string,register:string,email:string,phone:string,location:string} */
    public static function all(): array
    {
        return [
            'name' => Env::get('COMPANY_NAME'),
            'ico' => Env::get('COMPANY_ICO'),           // shown exactly as configured
            'dic' => Env::get('COMPANY_DIC'),           // optional VAT ID
            'address' => Env::get('COMPANY_ADDRESS'),   // registered office
            'register' => Env::get('COMPANY_REGISTER'), // optional free text, e.g. which register the business is entered in
            'email' => Env::get('CONTACT_EMAIL'),
            'phone' => Env::get('CONTACT_PHONE'),
            'location' => Env::get('CONTACT_LOCATION'),
        ];
    }

    /** Required identification fields that are still empty (for the CLAUDE.md checklist / admin hints). */
    public static function missing(): array
    {
        $c = self::all();
        return array_keys(array_filter(['name' => $c['name'] === '', 'ico' => $c['ico'] === '', 'address' => $c['address'] === '', 'email' => $c['email'] === '']));
    }
}
