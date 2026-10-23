<?php
/**
 * EXPERIMENTAL visual editor – the editable document of the Vantage design (config/editor.php 'view'). Server-rendered from validated values (e() everywhere).
 * Markup and styling are those of templates/demos/vantage.html (which is NOT modified); this copy only differs in:
 *   - every editable value comes from $v; links use the validated href; the primary call to action is an <a> (it needs a destination);
 *   - $edit = true adds data-px-* markers the editor reads (never rendered in the preview);
 *   - the draft's colours arrive as CSS variables from theme.css, applied by vantage-theme.css;
 *   - the Endora banner guard is kept, and no sample-site <meta> tags are emitted.
 * @var array<string,mixed> $v  @var bool $edit  @var string $themeUrl  @var callable $mediaUrl
 */
$fld = static fn(string $key, string $type): string => $edit ? ' data-px-field="' . $key . '" data-px-type="' . $type . '"' : '';
$href = static function (string $key) use ($v): string {
    $h = (string) $v[$key];
    return ' href="' . e($h) . '"' . (preg_match('#^https?://#i', $h) === 1 ? ' target="_blank" rel="noopener noreferrer"' : '');
};
$link = static fn(string $key): string => $href($key) . $fld($key, 'link');
// navigation entry: label + destination live on the same <a>
$nav = static fn(string $text, string $dest): string => $href($dest) . $fld($text, 'navlink') . ($edit ? ' data-px-href="' . $dest . '"' : '');
$img = (string) $v['demo_image'] !== '' ? $mediaUrl((string) $v['demo_image']) : '/assets/templates/vantage/img/watch-demo-thumbnail.jpg';
$lines = array_map('e', explode("\n", (string) $v['hero_copy']));
$defaults = \Pixelite\Editor\Schema::defaults();
// Edited headlines may be longer than the demo's; only then may they wrap (an untouched draft must lay out exactly like the demo).
$customTitle = $v['hero_line1'] !== $defaults['hero_line1'] || $v['hero_line2'] !== $defaults['hero_line2'];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#000000" />
    <meta name="color-scheme" content="dark" />
    <title><?= e($v['hero_line1']) ?> <?= e($v['hero_line2']) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <script src="/assets/templates/vantage/vantage-head.js"></script>
    <link rel="stylesheet" href="/assets/templates/vantage/vantage.css">
    <link rel="stylesheet" href="/assets/editor/vantage-theme.css">
    <link rel="stylesheet" href="<?= e($themeUrl) ?>">
<?php if ($edit): ?>
    <link rel="stylesheet" href="/assets/editor/canvas.css">
<?php endif ?>
    <link rel="stylesheet" href="/assets/endora-hide.css">
  </head>
  <body<?= $edit ? ' data-px-canvas' : '' ?>>
    <main class="viewport">
      <section class="screen" id="screen">
        <video class="background" autoplay muted loop playsinline disablepictureinpicture aria-hidden="true">
          <source src="/assets/templates/vantage/video/background.mp4" type="video/mp4">
        </video>

        <header class="header">
          <a class="brand" href="#" aria-label="Vantage home">
            <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <defs>
                <clipPath id="brand-clip">
                  <circle cx="12.5" cy="12.5" r="12.5" />
                </clipPath>
              </defs>
              <g clip-path="url(#brand-clip)">
                <rect width="25" height="25" fill="#ededed" />
                <polygon points="12.5,0 25,6 18,14 12.5,9" fill="#050606" />
                <polygon points="0,5 12.5,0 12.5,9 5,13" fill="#737778" />
                <polygon points="5,13 12.5,9 15,19 7,22" fill="#fafafa" />
                <polygon points="12.5,9 18,14 25,18 15,19" fill="#0a0b0b" />
                <polygon points="7,22 15,19 25,25 0,25" fill="#050606" />
                <polygon points="0,5 5,13 0,19" fill="#737778" />
              </g>
            </svg>
          </a>

          <div class="header-actions" id="tablet-navigation">
            <nav class="nav">
              <a<?= $nav('nav_home', 'nav_home_href') ?> class="active"><?= e($v['nav_home']) ?></a>
              <a<?= $nav('nav_about', 'nav_about_href') ?>><?= e($v['nav_about']) ?></a>
              <a<?= $nav('nav_services', 'nav_services_href') ?>><?= e($v['nav_services']) ?></a>
              <a<?= $nav('nav_contact', 'nav_contact_href') ?>><?= e($v['nav_contact']) ?></a>
            </nav>

            <div class="time-panel">
              <span class="time-label">Timezone</span>
              <span class="time-value">9:47 PM&nbsp; • &nbsp;14 July 2026</span>
            </div>

            <button type="button" class="sign-up"<?= $fld('signup_label', 'text') ?>><?= e($v['signup_label']) ?></button>
          </div>

          <button type="button" class="menu-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="tablet-navigation">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
              <line class="menu-bar menu-bar-top" x1="3" y1="6" x2="17" y2="6" />
              <line class="menu-bar menu-bar-bottom" x1="3" y1="14" x2="17" y2="14" />
            </svg>
          </button>
        </header>

        <section class="hero">
          <div class="hero-content">
            <h1 class="hero-title<?= $customTitle ? ' px-wrap' : '' ?>">
              <span class="line line-one">
                <span class="line-reveal"<?= $fld('hero_line1', 'text') ?><?= $edit ? ' data-px-default="' . e($defaults['hero_line1']) . '"' : '' ?>><?= e($v['hero_line1']) ?></span>
              </span>
              <span class="line line-two">
                <span class="line-reveal"<?= $fld('hero_line2', 'text') ?><?= $edit ? ' data-px-default="' . e($defaults['hero_line2']) . '"' : '' ?>><?= e($v['hero_line2']) ?></span>
              </span>
            </h1>

            <p class="hero-copy"<?= $fld('hero_copy', 'multiline') ?>><?= implode("<br>\n", $lines) ?></p>

            <a class="primary-cta"<?= $link('cta_href') ?>>
              <span class="label"<?= $fld('cta_label', 'text') ?>><?= e($v['cta_label']) ?></span>
              <span class="arrow-box">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M5 12h14"></path>
                  <path d="m12 5 7 7-7 7"></path>
                </svg>
              </span>
            </a>
          </div>

<?php if ($v['show_demo_card'] || $edit): ?>
          <article class="demo-card"<?= $fld('show_demo_card', 'section') ?><?= $edit ? ' data-px-visible="' . ($v['show_demo_card'] ? '1' : '0') . '"' : '' ?>>
            <div class="demo-visual">
              <img src="<?= e($img) ?>" alt="<?= e($v['demo_alt']) ?>"<?= $fld('demo_image', 'image') ?><?= $edit ? ' data-px-media="' . e($v['demo_image']) . '" data-px-alt="demo_alt"' : '' ?>>
              <button type="button" class="play" aria-label="Play demo">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#ffffff" aria-hidden="true">
                  <polygon points="6,3 20,12 6,21"></polygon>
                </svg>
              </button>
            </div>
            <button type="button" class="watch-button"<?= $fld('watch_label', 'text') ?>><?= e($v['watch_label']) ?></button>
          </article>
<?php endif ?>
        </section>
      </section>
    </main>

    <script src="/assets/templates/vantage/vantage.js"></script>
    <script src="/assets/endora-banner.js" defer></script>
  </body>
</html>
