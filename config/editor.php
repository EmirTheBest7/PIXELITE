<?php
declare(strict_types=1);

/**
 * EXPERIMENTAL visual editor – per-template field schema (the server's source of truth).
 *
 * The editor never stores or accepts HTML. A draft is a flat map `field key => typed value`; each template view
 * (templates/demos/<template>-editable.php) renders those values with e(). Anything not listed here cannot be saved.
 *
 * Field types: text (one line) · multiline (a few lines, rendered with <br>) · link (https:, http:, mailto:, tel:, #anchor)
 *              · image (id of an uploaded, re-encoded image of THIS project; '' = the template's own picture) · color (#rrggbb; '' = template default)
 *              · bool.
 * Defaults are the copy of the original demo (templates/demos/vantage.html), so an untouched draft renders exactly like the demo.
 */
return [
    'vantage' => [
        'title' => 'Vantage',
        'view' => 'demos/vantage-editable',
        'fields' => [
            'nav_home' => ['type' => 'text', 'max' => 18, 'default' => 'Home'],
            'nav_about' => ['type' => 'text', 'max' => 18, 'default' => 'About'],
            'nav_services' => ['type' => 'text', 'max' => 18, 'default' => 'Services'],
            'nav_contact' => ['type' => 'text', 'max' => 18, 'default' => 'Contact'],
            'nav_home_href' => ['type' => 'link', 'default' => '#home'],
            'nav_about_href' => ['type' => 'link', 'default' => '#about'],
            'nav_services_href' => ['type' => 'link', 'default' => '#services'],
            'nav_contact_href' => ['type' => 'link', 'default' => '#contact'],
            'signup_label' => ['type' => 'text', 'max' => 14, 'default' => 'Sign Up'],
            'hero_line1' => ['type' => 'text', 'max' => 22, 'default' => 'Stop Digging'],
            'hero_line2' => ['type' => 'text', 'max' => 22, 'default' => 'Through Dashboards.'],
            'hero_copy' => ['type' => 'multiline', 'max' => 260, 'lines' => 4, 'default' => "Your metrics are scattered across a dozen dashboards.\nVantage bring them into one clear signal, so every\ndecision is backed by data you actually trust."],
            'cta_label' => ['type' => 'text', 'max' => 14, 'default' => 'Get Started'],
            'cta_href' => ['type' => 'link', 'default' => '#'],
            'show_demo_card' => ['type' => 'bool', 'default' => true],
            'demo_image' => ['type' => 'image', 'default' => ''],
            'demo_alt' => ['type' => 'text', 'max' => 120, 'default' => 'Abstract red and blue smoke', 'optional' => true],
            'watch_label' => ['type' => 'text', 'max' => 16, 'default' => 'Watch Demo'],
            'color_title_1' => ['type' => 'color', 'default' => ''],
            'color_title_2' => ['type' => 'color', 'default' => ''],
            'color_copy' => ['type' => 'color', 'default' => ''],
            'color_cta' => ['type' => 'color', 'default' => ''],
        ],
    ],
];
