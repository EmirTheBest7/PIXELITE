/*
 * Pixelite visual editor (EXPERIMENTAL) – the property-panel templates VvvebJs asks for through tmpl().
 * Upstream compiles <script type="text/html"> templates with new Function(); that needs 'unsafe-eval'. These are plain functions that
 * escape every value, so the editor page can keep script-src 'self'. Original code; markup uses Pixelite classes (see editor.css).
 */
(function () {
  "use strict";
  const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  let uid = 0;
  const id = (p) => "pxe-" + p + "-" + (++uid);

  window.PixeliteTemplates = {
    "vvveb-property": (d) => `<div class="pxe-field" data-key="${esc(d.key)}">${d.name ? `<span class="pxe-field__label">${esc(d.name)}</span>` : ""}<div class="input"></div>${d.hint ? `<p class="pxe-field__hint">${esc(d.hint)}</p>` : ""}</div>`,

    "vvveb-input-sectioninput": (d) => `<div><h3 class="pxe-section" data-header="${esc(d.key)}"><span>${esc(d.header)}</span></h3><div class="section" data-section="${esc(d.key)}"></div></div>`,

    "vvveb-input-textinput": (d) => { const i = id("t"); return `<div><input id="${i}" class="form__field pxe-input" name="${esc(d.key)}" type="text" autocomplete="off" spellcheck="false"${d.maxlength ? ` maxlength="${Number(d.maxlength)}"` : ""}${d.placeholder ? ` placeholder="${esc(d.placeholder)}"` : ""}></div>`; },

    "vvveb-input-textareainput": (d) => { const i = id("a"); return `<div><textarea id="${i}" class="form__field pxe-input" name="${esc(d.key)}" rows="${Number(d.rows) || 4}" spellcheck="false"${d.maxlength ? ` maxlength="${Number(d.maxlength)}"` : ""}></textarea></div>`; },

    "vvveb-input-checkboxinput": (d) => { const i = id("c"); return `<div class="pxe-check"><input id="${i}" name="${esc(d.key)}" type="checkbox"><label for="${i}">${esc(d.text || "")}</label></div>`; },

    "vvveb-input-select": (d) => `<div><select class="form__field pxe-input" name="${esc(d.key)}">${(d.options || []).map((o) => `<option value="${esc(o.value)}">${esc(o.text)}</option>`).join("")}</select></div>`,

    // colour: native picker + an explicit "template default" state (a colour input cannot be empty)
    "vvveb-input-colorinput": (d) => { const i = id("k"); return `<div class="pxe-color"><input id="${i}" name="${esc(d.key)}" type="color" value="#000000" aria-label="${esc(d.name || "Colour")}"><span class="pxe-color__value" data-color-label>Template default</span><button type="button" class="pxe-link" data-color-reset>Reset</button></div>`; },

    // image: preview + file chooser (upload handled by px-editor.js; the file never reaches the page as markup)
    "vvveb-input-pximage": (d) => { const i = id("f"); return `<div class="pxe-image"><img class="pxe-image__thumb" alt="" width="96" height="64"><div><label class="btn btn--ghost btn--sm btn--plain" for="${i}"><span class="btn__roll"><span>Replace image</span><span aria-hidden="true">Replace image</span></span></label><input id="${i}" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp"><button type="button" class="pxe-link" data-image-reset>Use template picture</button></div></div>`; },
  };
})();
