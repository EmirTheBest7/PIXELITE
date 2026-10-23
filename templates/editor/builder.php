<?php
/**
 * EXPERIMENTAL site builder – the builder page (VvvebJs engine, Pixelite shell). Same-origin shell around the canvas iframe; loads no inline script/style and no
 * third-party host. The ids below (#iframe-wrapper, #select-box, #highlight-box, #add-section-box, #tree-list …) are the ones the vendored builder looks up.
 * @var array{id:int,name:string} $project  @var string $csrf  @var string $api  @var string $upload  @var int $version  @var bool $imageUploads  @var bool $sanitizer  @var bool $anonymous  @var string $home  @var int|null $ttlHours
 */
$ico = static fn(string $d, int $s = 18): string => '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$I = [
    'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>', 'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'desktop' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>', 'tablet' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11 18h2"/>', 'phone' => '<rect x="8" y="3" width="8" height="18" rx="2"/><path d="M11 18h2"/>',
    'undo' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>', 'redo' => '<path d="m15 14 5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/>', 'code' => '<path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 5l-4 14"/>',
    'move' => '<path d="M12 3v18M3 12h18M12 3l-3 3M12 3l3 3M12 21l-3-3M12 21l3-3M3 12l3-3M3 12l3 3M21 12l-3-3M21 12l-3 3"/>', 'up' => '<path d="m6 15 6-6 6 6"/>', 'down' => '<path d="m6 9 6 6 6-6"/>', 'parent' => '<path d="M6 18V8a2 2 0 0 1 2-2h10M14 2l4 4-4 4"/>',
    'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>', 'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>', 'plus' => '<path d="M12 5v14M5 12h14"/>', 'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
    'bold' => '<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/>', 'italic' => '<path d="M14 4h-4M14 20h-4M15 4 9 20"/>', 'underline' => '<path d="M7 4v7a5 5 0 0 0 10 0V4M5 20h14"/>', 'strike' => '<path d="M4 12h16M16 7c0-1.5-1.8-3-4-3s-4 1-4 3 2 3 4 3M8 17c0 1.5 1.8 3 4 3s4-1 4-3"/>',
    'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>', 'align' => '<path d="M4 6h16M4 10h10M4 14h16M4 18h10"/>',
    'tree' => '<path d="M4 5h6v4H4zM14 11h6v4h-6zM14 17h6v3h-6zM7 9v8h7M7 13h7"/>', 'pages' => '<path d="M6 3h8l4 4v14H6zM14 3v4h4"/>', 'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 9"/>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Site builder (experimental) · <?= e($project['name']) ?></title>
<?php require \Pixelite\Paths::root('templates/partials/head-assets.php') ?>
  <link rel="stylesheet" href="<?= e(asset('editor/editor.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('editor/builder.css')) ?>">
</head>
<body class="pxe pxb" data-api="<?= e($api) ?>" data-upload="<?= e($upload) ?>" data-anonymous="<?= !empty($anonymous) ? '1' : '0' ?>" data-csrf="<?= e($csrf) ?>" data-version="<?= (int) $version ?>" data-uploads="<?= $imageUploads ? '1' : '0' ?>" data-sanitizer="<?= $sanitizer ? '1' : '0' ?>">
<a class="pxe-skip" href="#canvas">Skip to the page</a>
<header class="pxe-bar" id="top-panel">
  <a class="pxe-brand" href="<?= e($home) ?>" title="<?= e(!empty($anonymous) ? 'Back to the builder start page' : 'Back to your account') ?>"><?= logo_mark('pxe-brand__mark') ?><span>PIXELITE<i>.cz</i></span><b class="pxe-badge">Experimental</b></a>

  <div class="pxe-title"<?= !empty($anonymous) ? ' data-note="deleted after ' . (int) $ttlHours . ' h idle · not published"' : '' ?>><strong><?= e($project['name']) ?></strong><span id="pxb-page-label">Home</span></div>
  <div class="pxe-bar__group" role="group" aria-label="Mode">
    <button type="button" class="pxe-seg" data-mode-btn="edit" aria-pressed="true"><?= $ico($I['edit']) ?><span>Edit</span></button>
    <button type="button" class="pxe-seg" data-mode-btn="preview" aria-pressed="false"><?= $ico($I['eye']) ?><span>Preview</span></button>
  </div>
  <div class="pxe-bar__group responsive-btns" role="group" aria-label="Screen size and responsive styles">
    <button type="button" class="pxe-seg pxe-seg--icon" id="pxb-view-desktop" data-device-btn="desktop" aria-pressed="true" title="Desktop: edits apply to every screen size" aria-label="Desktop"><?= $ico($I['desktop']) ?></button>
    <button type="button" class="pxe-seg pxe-seg--icon" id="tablet-view" data-vvveb-action="viewport" data-view="md" data-device-btn="tablet" aria-pressed="false" title="Tablet: CSS edits apply up to 768 px" aria-label="Tablet"><?= $ico($I['tablet']) ?></button>
    <button type="button" class="pxe-seg pxe-seg--icon" id="mobile-view" data-vvveb-action="viewport" data-view="sm" data-device-btn="phone" aria-pressed="false" title="Phone: CSS edits apply up to 576 px" aria-label="Phone"><?= $ico($I['phone']) ?></button>
  </div>
  <div class="pxe-bar__group" role="group" aria-label="History and tools">
    <button type="button" class="pxe-seg pxe-seg--icon" id="undo-btn" data-vvveb-action="undo" title="Undo (Ctrl/⌘ Z)" aria-label="Undo" disabled><?= $ico($I['undo']) ?></button>
    <button type="button" class="pxe-seg pxe-seg--icon" id="redo-btn" data-vvveb-action="redo" title="Redo (Ctrl/⌘ Shift Z)" aria-label="Redo" disabled><?= $ico($I['redo']) ?></button>
    <button type="button" class="pxe-seg pxe-seg--icon" id="code-editor-btn" data-vvveb-action="toggleEditor" aria-pressed="false" title="HTML and CSS code" aria-label="Code view"><?= $ico($I['code']) ?></button>
  </div>
  <div class="pxe-status" id="pxe-status" data-state="loading" role="status" aria-live="polite"><i class="pxe-status__dot" aria-hidden="true"></i><span class="pxe-status__text">Loading…</span><button type="button" class="pxe-link" id="pxe-reload" hidden>Reload</button></div>
  <button type="button" class="btn btn--ghost btn--sm btn--plain" id="pxe-revert" disabled>Discard changes</button>
  <button type="button" class="btn btn--accent btn--sm btn--plain save-btn" id="pxe-save" disabled>Save draft</button>
</header>

<div class="pxe-main pxb-main" id="vvveb-builder">
  <aside id="left-panel" class="pxe-panel pxb-left" aria-label="Elements, layers, pages and images">
    <div class="pxb-tabs" role="tablist">
      <button type="button" class="pxb-tab active" role="tab" data-bs-toggle="tab" data-bs-target="#pxb-tab-add" aria-selected="true"><?= $ico($I['plus'], 16) ?><span>Add</span></button>
      <button type="button" class="pxb-tab" role="tab" data-bs-toggle="tab" data-bs-target="#pxb-tab-layers" aria-selected="false"><?= $ico($I['tree'], 16) ?><span>Layers</span></button>
      <button type="button" class="pxb-tab" role="tab" data-bs-toggle="tab" data-bs-target="#pxb-tab-pages" aria-selected="false"><?= $ico($I['pages'], 16) ?><span>Pages</span></button>
      <button type="button" class="pxb-tab" role="tab" data-bs-toggle="tab" data-bs-target="#pxb-tab-media" aria-selected="false"><?= $ico($I['image'], 16) ?><span>Images</span></button>
    </div>
    <div class="tab-content pxb-tabcontent">
      <div class="tab-pane show active" id="pxb-tab-add" role="tabpanel">
        <div class="pxb-subtabs" role="tablist">
          <button type="button" class="pxb-subtab active" role="tab" data-bs-toggle="tab" data-bs-target="#pxb-add-elements">Elements</button>
          <button type="button" class="pxb-subtab" role="tab" data-bs-toggle="tab" data-bs-target="#pxb-add-blocks">Blocks</button>
          <button type="button" class="pxb-subtab" role="tab" data-bs-toggle="tab" data-bs-target="#pxb-add-sections">Sections</button>
        </div>
        <p class="pxe-field__hint pxb-hint">Drag onto the page, or click an item to insert it after the selected element.</p>
        <div class="tab-content">
          <div class="tab-pane show active" id="pxb-add-elements"><div class="pxb-search"><input type="search" class="form-control" placeholder="Search elements" aria-label="Search elements" data-vvveb-action="search" data-vvveb-on="keyup"><button type="button" class="clear-backspace" aria-label="Clear search" data-vvveb-action="clearSearch"><?= $ico($I['close'], 14) ?></button></div><div class="drag-elements-sidepane"><ul class="components-list" data-type="leftpanel"></ul></div></div>
          <div class="tab-pane" id="pxb-add-blocks"><div class="pxb-search"><input type="search" class="form-control" placeholder="Search blocks" aria-label="Search blocks" data-vvveb-action="search" data-vvveb-on="keyup"><button type="button" class="clear-backspace" aria-label="Clear search" data-vvveb-action="clearSearch"><?= $ico($I['close'], 14) ?></button></div><div class="drag-elements-sidepane"><ul class="blocks-list" data-type="leftpanel"></ul></div></div>
          <div class="tab-pane" id="pxb-add-sections"><div class="pxb-search"><input type="search" class="form-control" placeholder="Search sections" aria-label="Search sections" data-vvveb-action="search" data-vvveb-on="keyup"><button type="button" class="clear-backspace" aria-label="Clear search" data-vvveb-action="clearSearch"><?= $ico($I['close'], 14) ?></button></div><div class="drag-elements-sidepane"><ul class="sections-list" data-type="leftpanel"></ul></div></div>
        </div>
      </div>
      <div class="tab-pane" id="pxb-tab-layers" role="tabpanel">
        <div id="tree-list" class="pxb-tree"><div class="header" hidden></div><div class="tree"><ol></ol></div></div>
      </div>
      <div class="tab-pane" id="pxb-tab-pages" role="tabpanel">
        <div class="pxe-field"><label class="pxe-field__label" for="pxb-page-title">Title of this page</label><input id="pxb-page-title" class="form-control" type="text" maxlength="80" autocomplete="off"><p class="pxe-field__hint">Shown as the browser-tab title.</p></div>
<?php if (!empty($anonymous)): ?>
        <p class="pxb-anon-note"><strong>Temporary workspace.</strong> Saved in our server storage for you only and deleted after <?= (int) $ttlHours ?> hours without activity. Nothing is published, and there is no account: keep your draft by saving often, and do not enter personal or confidential data.</p>
<?php endif ?>
        <h3 class="pxe-card__title">Pages</h3>
        <ul id="pxb-pages" class="pxb-list"></ul>
        <form id="pxb-new-page" class="pxb-newpage" autocomplete="off">
          <label class="pxe-field__label" for="pxb-new-title">New page</label>
          <div class="pxb-inline"><input id="pxb-new-title" class="form-control" type="text" maxlength="80" placeholder="Page name, e.g. About"><button type="submit" class="btn btn-outline-primary btn-sm">Add</button></div>
          <p class="pxe-field__hint">Up to 10 pages. Save the current page first; unsaved changes stay with the page you are on.</p>
        </form>
      </div>
      <div class="tab-pane" id="pxb-tab-media" role="tabpanel">
        <div class="pxb-upload"><label class="btn btn-outline-primary btn-sm" for="pxb-upload"><?= $imageUploads ? 'Upload image' : 'Uploads unavailable' ?></label><input id="pxb-upload" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp"<?= $imageUploads ? '' : ' disabled' ?>></div>
        <p class="pxe-field__hint">JPEG, PNG or WebP, up to 4 MB. Images are re-encoded on upload. Use them via an image element’s “Choose image”.</p>
        <div id="pxb-media" class="pxb-media" aria-live="polite"></div>
      </div>
    </div>
  </aside>

  <section id="canvas" class="pxe-stage pxb-stage" data-device="desktop" aria-label="Page being edited" tabindex="-1">
    <div id="iframe-wrapper" class="pxe-frame">
      <iframe id="iframe1" title="Editable page" src="about:blank"></iframe>
      <div id="iframe-layer">
        <div class="loading-message active" role="status"><span class="pxe-spinner" aria-hidden="true"></span><span>Loading the page…</span></div>
        <div id="highlight-box">
          <div id="highlight-name"><span class="name"></span><span class="type"></span></div>
          <div id="section-actions"><a id="add-section-btn" href="#" title="Add an element here" aria-label="Add an element here"><?= $ico($I['plus'], 14) ?></a></div>
        </div>
        <div id="select-box">
          <div id="wysiwyg-editor" class="default-editor">
            <a id="bold-btn" href="#" title="Bold" aria-label="Bold"><?= $ico($I['bold'], 15) ?></a>
            <a id="italic-btn" href="#" title="Italic" aria-label="Italic"><?= $ico($I['italic'], 15) ?></a>
            <a id="underline-btn" href="#" title="Underline" aria-label="Underline"><?= $ico($I['underline'], 15) ?></a>
            <a id="strike-btn" href="#" title="Strikethrough" aria-label="Strikethrough"><?= $ico($I['strike'], 15) ?></a>
            <a id="link-btn" href="#" title="Link" aria-label="Link"><?= $ico($I['link'], 15) ?></a>
            <div id="justify-btn" class="pxb-justify" role="group" aria-label="Alignment"><a href="#" data-value="Left" title="Align left" aria-label="Align left"><?= $ico($I['align'], 15) ?></a><a href="#" data-value="Center" title="Center" aria-label="Center">C</a><a href="#" data-value="Right" title="Align right" aria-label="Align right">R</a></div>
            <input id="fore-color" type="color" title="Text colour" aria-label="Text colour"><input id="back-color" type="color" title="Highlight colour" aria-label="Highlight colour">
            <select id="font-size" aria-label="Font size"></select><select id="font-family" aria-label="Font"><option value="">Font</option><option>system-ui</option><option>Arial</option><option>Georgia</option><option>Times New Roman</option><option>Courier New</option><option>Onest</option></select>
          </div>
          <div id="select-actions">
            <a id="drag-btn" href="#" title="Move (drag)" aria-label="Move (drag)"><?= $ico($I['move'], 15) ?></a>
            <a id="parent-btn" href="#" title="Select parent" aria-label="Select parent"><?= $ico($I['parent'], 15) ?></a>
            <a id="up-btn" href="#" title="Move up" aria-label="Move up"><?= $ico($I['up'], 15) ?></a>
            <a id="down-btn" href="#" title="Move down" aria-label="Move down"><?= $ico($I['down'], 15) ?></a>
            <a id="edit-code-btn" href="#" title="Edit HTML of this element" aria-label="Edit HTML of this element"><?= $ico($I['code'], 15) ?></a>
            <a id="clone-btn" href="#" title="Duplicate" aria-label="Duplicate"><?= $ico($I['copy'], 15) ?></a>
            <a id="delete-btn" href="#" title="Delete" aria-label="Delete"><?= $ico($I['trash'], 15) ?></a>
            <a id="save-reusable-btn" href="#" hidden></a>
          </div>
        </div>
      </div>
      <div id="add-section-box" class="drag-elements">
        <div class="pxb-pop-head">
          <div class="pxb-subtabs" role="tablist">
            <button type="button" id="box-components-tab" class="pxb-subtab active" data-bs-toggle="tab" data-bs-target="#box-components">Elements</button>
            <button type="button" id="box-blocks-tab" class="pxb-subtab" data-bs-toggle="tab" data-bs-target="#box-blocks">Blocks</button>
            <button type="button" id="box-sections-tab" class="pxb-subtab" data-bs-toggle="tab" data-bs-target="#box-sections">Sections</button>
          </div>
          <button type="button" id="close-section-btn" class="pxb-iconbtn" aria-label="Close"><?= $ico($I['close'], 16) ?></button>
        </div>
        <div class="pxb-insert"><label><input type="radio" name="add-section-insert-mode" id="add-section-insert-mode-after" value="after" checked> After</label><label><input type="radio" name="add-section-insert-mode" id="add-section-insert-mode-inside" value="inside"> Inside</label></div>
        <div class="tab-content">
          <div class="tab-pane show active" id="box-components"><div class="drag-elements-sidepane"><ul class="components-list" data-type="addbox"></ul></div></div>
          <div class="tab-pane" id="box-blocks"><div class="drag-elements-sidepane"><ul class="blocks-list" data-type="addbox"></ul></div></div>
          <div class="tab-pane" id="box-sections"><div class="drag-elements-sidepane"><ul class="sections-list" data-type="addbox"></ul></div></div>
        </div>
      </div>
    </div>
    <div class="pxe-preview" aria-hidden="true"><iframe id="pxe-preview-frame" title="Preview of the saved page"></iframe></div>
  </section>

  <aside id="right-panel" class="pxe-panel pxb-right" aria-label="Selected element">
    <p id="pxe-empty" class="pxe-empty">Click an element on the page to edit its content, style and attributes. Double-click text to type on the page.</p>
    <div class="component-properties">
      <div class="pxb-tabs pxb-tabs--props" role="tablist" id="properties-tabs">
        <button type="button" class="pxb-tab active" role="tab" data-bs-toggle="tab" data-bs-target="#content-tab" aria-selected="true"><span>Content</span></button>
        <button type="button" class="pxb-tab" role="tab" data-bs-toggle="tab" data-bs-target="#style-tab" aria-selected="false"><span>Style</span></button>
        <button type="button" class="pxb-tab" role="tab" data-bs-toggle="tab" data-bs-target="#advanced-tab" aria-selected="false"><span>Advanced</span></button>
      </div>
      <div class="tab-content">
        <div class="tab-pane show active" id="content-tab" role="tabpanel" data-section="content"></div>
        <div class="tab-pane" id="style-tab" role="tabpanel" data-section="style"><div class="pxb-state"><label for="pxb-state">Style applies when</label><select id="pxb-state" class="form-select" data-vvveb-action="setState" data-vvveb-on="change"><option value="">Always</option><option value="hover">Hovered</option><option value="active">Pressed</option><option value="focus">Focused</option></select></div></div>
        <div class="tab-pane" id="advanced-tab" role="tabpanel" data-section="advanced"></div>
      </div>
    </div>
    <span class="component-properties-tab" hidden><a href="#" tabindex="-1"></a></span>
  </aside>
</div>

<div id="bottom-panel" class="pxb-bottom">
  <nav class="breadcrumb-navigator" aria-label="Element path"><ol class="breadcrumb"></ol></nav>
  <div id="vvveb-code-editor" class="pxb-code" hidden>
    <div class="pxb-code__tabs" role="tablist">
      <button type="button" class="pxb-subtab active" role="tab" data-bs-toggle="tab" data-bs-target="#code-tab">HTML of the page</button>
      <button type="button" class="pxb-subtab" role="tab" data-bs-toggle="tab" data-bs-target="#csscode-tab">CSS of the page</button>
      <span class="pxe-field__hint">Scripts, embeds, event attributes and external addresses are removed when the page is saved.</span>
    </div>
    <div class="tab-content">
      <div class="tab-pane show active" id="code-tab"><textarea id="pxb-html-code" class="form-control" spellcheck="false" aria-label="HTML of the page"></textarea><button type="button" class="btn btn-outline-primary btn-sm" id="pxb-apply-html">Apply HTML</button></div>
      <div class="tab-pane" id="csscode-tab"><textarea id="css-editor" class="form-control" spellcheck="false" aria-label="CSS of the page"></textarea><button type="button" class="btn btn-outline-primary btn-sm" id="pxb-apply-css">Apply CSS</button></div>
    </div>
  </div>
</div>

<div class="modal" id="codeEditorModal" tabindex="-1" aria-labelledby="pxb-code-modal-title" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title" id="pxb-code-modal-title">HTML of this element</h2><button type="button" class="pxb-iconbtn" data-bs-dismiss="modal" aria-label="Close"><?= $ico($I['close'], 16) ?></button></div>
  <div class="modal-body"><textarea class="form-control" rows="14" spellcheck="false" aria-label="HTML of the selected element"></textarea><p class="pxe-field__hint">Only safe markup is kept when the page is saved.</p></div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary btn-sm save-btn">Apply</button></div>
</div></div></div>

<div class="modal" id="pxb-media-modal" tabindex="-1" aria-labelledby="pxb-media-modal-title" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title" id="pxb-media-modal-title">Choose an image</h2><button type="button" class="pxb-iconbtn" data-bs-dismiss="modal" aria-label="Close"><?= $ico($I['close'], 16) ?></button></div>
  <div class="modal-body"><div id="pxb-picker" class="pxb-media"></div><p class="pxe-field__hint">Upload images in the “Images” tab of the left panel.</p></div>
</div></div></div>

<div id="pxe-toasts" class="pxe-toasts" aria-live="polite"></div>

<script src="<?= e(asset('editor/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/builder.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/undo.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/inputs.js')) ?>"></script>
<script src="<?= e(asset('editor/px-templates.generated.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/components-common.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/components-html.js')) ?>"></script>
<script src="<?= e(asset('editor/vendor/vvvebjs/components-bootstrap5.js')) ?>"></script>
<script src="<?= e(asset('editor/px-blocks.js')) ?>"></script>
<script src="<?= e(asset('editor/px-builder.js')) ?>"></script>
<script src="<?= e(asset('endora-banner.js')) ?>" defer></script>
</body>
</html>
