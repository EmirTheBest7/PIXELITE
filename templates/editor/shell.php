<?php
/**
 * EXPERIMENTAL visual editor – the editor page. Same-origin shell around the canvas iframe; loads no inline script/style and no third-party host.
 * The ids below (#iframe-wrapper, #select-box, #wysiwyg-editor …) are the ones the vendored VvvebJs builder looks up; the buttons in #pxe-stubs
 * only exist so that its initialisation finds them – they are hidden and not wired to anything.
 * @var array{id:int,name:string} $project  @var string $template  @var string $csrf  @var string $base  @var int $version  @var bool $imageUploads
 */
$ico = static fn(string $d): string => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Visual editor (experimental) · <?= e($project['name']) ?></title>
<?php require \Pixelite\Paths::root('templates/partials/head-assets.php') ?>
  <link rel="stylesheet" href="<?= e(asset('editor/editor.css')) ?>">
</head>
<body class="pxe" data-base="<?= e($base) ?>" data-csrf="<?= e($csrf) ?>" data-version="<?= (int) $version ?>" data-uploads="<?= $imageUploads ? '1' : '0' ?>">
<a class="pxe-skip" href="#canvas">Skip to the page</a>
<header class="pxe-bar">
  <a class="pxe-brand" href="<?= e(url('account')) ?>" title="Back to your account"><?= logo_mark('pxe-brand__mark') ?><span>PIXELITE<i>.cz</i></span><b class="pxe-badge">Experimental</b></a>
  <div class="pxe-title"><strong><?= e($project['name']) ?></strong><span><?= e($template) ?> · draft</span></div>
  <div class="pxe-bar__group" role="group" aria-label="Mode">
    <button type="button" class="pxe-seg" data-mode-btn="edit" aria-pressed="true"><?= $ico('<path d="M4 20h4L19 9l-4-4L4 16z"/>') ?><span>Edit</span></button>
    <button type="button" class="pxe-seg" data-mode-btn="preview" aria-pressed="false"><?= $ico('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>') ?><span>Preview</span></button>
  </div>
  <div class="pxe-bar__group" role="group" aria-label="Screen size">
    <button type="button" class="pxe-seg pxe-seg--icon" data-device-btn="desktop" aria-pressed="true" title="Desktop" aria-label="Desktop width"><?= $ico('<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>') ?></button>
    <button type="button" class="pxe-seg pxe-seg--icon" data-device-btn="tablet" aria-pressed="false" title="Tablet" aria-label="Tablet width"><?= $ico('<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11 18h2"/>') ?></button>
    <button type="button" class="pxe-seg pxe-seg--icon" data-device-btn="phone" aria-pressed="false" title="Phone" aria-label="Phone width"><?= $ico('<rect x="8" y="3" width="8" height="18" rx="2"/><path d="M11 18h2"/>') ?></button>
  </div>
  <div class="pxe-bar__group" role="group" aria-label="History">
    <button type="button" class="pxe-seg pxe-seg--icon" id="pxe-undo" title="Undo (Ctrl/⌘ Z)" aria-label="Undo" disabled><?= $ico('<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>') ?></button>
    <button type="button" class="pxe-seg pxe-seg--icon" id="pxe-redo" title="Redo (Ctrl/⌘ Shift Z)" aria-label="Redo" disabled><?= $ico('<path d="m15 14 5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/>') ?></button>
  </div>
  <div class="pxe-status" id="pxe-status" data-state="loading" role="status" aria-live="polite"><i class="pxe-status__dot" aria-hidden="true"></i><span class="pxe-status__text">Loading…</span><button type="button" class="pxe-link" id="pxe-reload" hidden>Reload</button></div>
  <button type="button" class="btn btn--ghost btn--sm btn--plain" id="pxe-revert" disabled>Discard changes</button>
  <button type="button" class="btn btn--accent btn--sm btn--plain" id="pxe-save" disabled>Save draft</button>
</header>

<div class="pxe-main">
  <aside id="left-panel" class="pxe-panel pxe-panel--left" aria-label="Page settings">
    <section class="pxe-card">
      <h2 class="pxe-card__title">Page blocks</h2>
      <div class="pxe-check"><input type="checkbox" id="pxe-block-card" checked><label for="pxe-block-card">Show the demo card</label></div>
      <p class="pxe-field__hint">This template is one full-screen page, so there are no sections to reorder. The demo card is the only block that can be hidden.</p>
    </section>
    <section class="pxe-card">
      <h2 class="pxe-card__title">Colours</h2>
      <div id="px-theme"></div>
      <p class="pxe-field__hint">“Reset” goes back to the template’s own colour. Button text switches between dark and light by itself so it stays readable.</p>
    </section>
    <section class="pxe-card pxe-card--note">
      <h2 class="pxe-card__title">About this editor</h2>
      <p class="pxe-field__hint">An experiment: it saves a private draft only. Nothing is published, and your changes are not visible to anyone else.</p>
      <p class="pxe-field__hint">Shortcuts: <kbd>Ctrl/⌘ S</kbd> save · <kbd>Ctrl/⌘ Z</kbd> undo · double-click text to type.</p>
    </section>
  </aside>

  <section id="canvas" class="pxe-stage" data-device="desktop" aria-label="Page being edited" tabindex="-1">
    <div id="iframe-wrapper" class="pxe-frame">
      <iframe title="Editable page" src="about:blank"></iframe>
      <div id="iframe-layer">
        <div class="loading-message active" role="status"><span class="pxe-spinner" aria-hidden="true"></span><span>Loading the page…</span></div>
        <div id="highlight-box">
          <div id="highlight-name"><span class="name"></span><span class="type"></span></div>
          <div id="section-actions"><a id="add-section-btn" href="#" tabindex="-1" aria-hidden="true"></a></div>
        </div>
        <div id="select-box">
          <div id="select-actions">
            <a id="drag-btn" href="#"></a><a id="down-btn" href="#"></a><a id="up-btn" href="#"></a><a id="clone-btn" href="#"></a><a id="parent-btn" href="#"></a>
            <a id="save-reusable-btn" href="#"></a><a id="edit-code-btn" href="#"></a><a id="delete-btn" href="#"></a>
          </div>
        </div>
        <div id="add-section-box"><button type="button" id="close-section-btn" tabindex="-1"></button><i id="box-sections-tab"></i><i id="box-components-tab"></i></div>
      </div>
    </div>
    <div class="pxe-preview" aria-hidden="true"><iframe id="pxe-preview-frame" title="Preview of the saved draft"></iframe></div>
  </section>

  <aside id="right-panel" class="pxe-panel pxe-panel--right" aria-label="Selected element">
    <section class="pxe-card">
      <p id="pxe-empty" class="pxe-empty">Click a part of the page that lights up to change it. Double-click a headline or menu item to type directly on the page.</p>
      <div class="component-properties"><div class="tab-pane" data-section="content"></div></div>
      <span class="component-properties-tab" hidden><a href="#" tabindex="-1"></a></span>
    </section>
  </aside>
</div>

<div id="pxe-toasts" class="pxe-toasts" aria-live="polite"></div>

<div id="pxe-stubs" hidden>
  <div id="wysiwyg-editor">
    <a id="bold-btn" href="#"></a><a id="italic-btn" href="#"></a><a id="underline-btn" href="#"></a><a id="strike-btn" href="#"></a><a id="link-btn" href="#"></a>
    <input id="fore-color"><input id="back-color"><select id="font-size"></select><select id="font-family"></select><div id="justify-btn"></div>
  </div>
</div>

<script src="<?= e(asset('editor/px-shim.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/builder.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/undo.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/inputs.js')) ?>"></script>
<script src="<?= e(asset('editor/px-templates.js')) ?>"></script>
<script src="<?= e(asset('editor/px-editor.js')) ?>"></script>
<script src="<?= e(asset('endora-banner.js')) ?>" defer></script>
</body>
</html>
