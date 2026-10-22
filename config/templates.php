<?php
declare(strict_types=1);

/**
 * Website-design catalog – PUBLIC metadata only (never put prompt text or development notes here; those live in the
 * admin-only SQLite table template_prompts). Texts: lang/*.php → templates.items.<slug>.
 *
 * Every entry is a template owned by Pixelite. Its preview is the template itself, running at
 * /{lang}/templates/<slug>/demo (templates/demos/<demo>.html + public/assets/templates/<slug>/…), plus a poster and a
 * short animated preview captured from that same page by scripts/capture-template-previews.mjs.
 *
 * category, style: keys under templates.categories / templates.styles; tags: templates.tags; featured: editorial pick.
 * third_party: components shipped inside the template with their own licences (notice files live next to the assets).
 */
return [
    [
        'slug' => 'lumora',
        'category' => 'studio',
        'style' => 'light',
        'featured' => true,
        'tags' => ['one-page', 'animated', 'cursor-effect', 'smooth-scroll', 'contact-form'],
        'media' => ['dir' => 'templates/lumora', 'poster' => ['640' => 'poster-640.jpg', '1280' => 'poster-1280.jpg'], 'video' => 'preview.webm'],
        'demo' => 'lumora',
        'stack' => ['HTML', 'CSS', 'JavaScript (ES modules)'],
        'origin' => [
            'author' => 'Pixelite',
            'third_party' => [
                ['name' => 'Lenis 1.3.23', 'license' => 'MIT', 'url' => 'https://github.com/darkroomengineering/lenis', 'notice' => '/assets/templates/lumora/vendor/LICENSE-lenis.txt'],
                ['name' => 'Onest', 'license' => 'SIL OFL 1.1', 'url' => 'https://github.com/googlefonts/onest', 'notice' => '/assets/templates/lumora/fonts/OFL-Onest.txt'],
            ],
        ],
        'prompt_ref' => 'lumora',   // key into the admin-only template_prompts table
    ],
    [
        'slug' => 'asterform',
        'category' => 'studio',
        'style' => 'dark',
        'featured' => false,
        'tags' => ['one-page', 'animated', 'video-hero', 'scroll-reveal'],
        'media' => ['dir' => 'templates/asterform', 'poster' => ['640' => 'poster-640.jpg', '1280' => 'poster-1280.jpg'], 'video' => 'preview.webm'],
        'demo' => 'asterform',
        'stack' => ['HTML', 'CSS', 'JavaScript'],
        'origin' => [
            'author' => 'Pixelite',
            'third_party' => [
                ['name' => 'Instrument Serif', 'license' => 'SIL OFL 1.1', 'url' => 'https://github.com/Instrument/instrument-serif', 'notice' => '/assets/templates/asterform/fonts/OFL-Instrument-Serif.txt'],
            ],
        ],
        'prompt_ref' => 'asterform',
    ],
    [
        'slug' => 'stillwave',
        'category' => 'saas',
        'style' => 'dark',
        'featured' => false,
        'tags' => ['one-page', 'animated', 'video-hero', 'prompt-dialog'],
        'media' => ['dir' => 'templates/stillwave', 'poster' => ['640' => 'poster-640.jpg', '1280' => 'poster-1280.jpg'], 'video' => 'preview.webm'],
        'demo' => 'stillwave',
        'stack' => ['HTML', 'CSS', 'JavaScript'],
        'origin' => [
            'author' => 'Pixelite',
        ],
        'prompt_ref' => 'stillwave',
    ],
    [
        'slug' => 'vantage',
        'category' => 'saas',
        'style' => 'dark',
        'featured' => false,
        'tags' => ['one-page', 'animated', 'video-hero'],
        'media' => ['dir' => 'templates/vantage', 'poster' => ['640' => 'poster-640.jpg', '1280' => 'poster-1280.jpg'], 'video' => 'preview.webm'],
        'demo' => 'vantage',
        'stack' => ['HTML', 'CSS', 'JavaScript'],
        'origin' => [
            'author' => 'Pixelite',
        ],
        'prompt_ref' => 'vantage',
    ],
    [
        'slug' => 'emberveil',
        'category' => 'studio',
        'style' => 'light',
        'featured' => false,
        'tags' => ['one-page', 'animated', 'cursor-effect'],
        'media' => ['dir' => 'templates/emberveil', 'poster' => ['640' => 'poster-640.jpg', '1280' => 'poster-1280.jpg'], 'video' => 'preview.webm'],
        'demo' => 'emberveil',
        'stack' => ['HTML', 'CSS', 'JavaScript'],
        'origin' => [
            'author' => 'Pixelite',
            'third_party' => [
                ['name' => 'Inter', 'license' => 'SIL OFL 1.1', 'url' => 'https://github.com/rsms/inter', 'notice' => '/assets/templates/emberveil/fonts/OFL-Inter.txt'],
            ],
        ],
        'prompt_ref' => 'emberveil',
    ],
];
