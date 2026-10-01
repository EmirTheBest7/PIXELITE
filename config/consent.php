<?php
// Cookie / consent registry. Single source for the banner, the settings dialog and the cookie policy page.
// Labels and descriptions live in lang/*.php (consent.*, cookies.*).
return [
    // Bump when categories change in a way that needs a fresh decision; everyone is asked again.
    'version' => 1,
    'max_age_days' => 180,

    // Everything this site can store in a browser. Keep in sync with lang cookies.items.<id>.
    'storage' => [
        ['id' => 'session', 'group' => 'necessary',   'name' => 'pixelite',         'type' => 'cookie'],
        ['id' => 'consent', 'group' => 'necessary',   'name' => 'pixelite_consent', 'type' => 'cookie'],
        ['id' => 'lang',    'group' => 'preferences', 'name' => 'pixelite_lang',    'type' => 'cookie'],
        ['id' => 'theme',   'group' => 'preferences', 'name' => 'pixelite_theme',   'type' => 'storage'],
    ],

    // Optional services by category. EMPTY ON PURPOSE – nothing optional is used today, and none is invented here.
    // To add one later: list its name under its category (the dialog then shows a switch for that category), load it with
    //   <script type="text/plain" data-consent="analytics" data-src="https://…"></script>
    // (consent.js activates it only after consent) and allow its host in the CSP in src/bootstrap.php.
    'optional' => [
        'analytics' => [],
        'marketing' => [],
    ],
];
