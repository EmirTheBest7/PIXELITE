/*
 * Pixelite site builder (EXPERIMENTAL) – glue between the Pixelite builder shell and the vendored VvvebJs engine (vendor/vvvebjs, Apache-2.0).
 * Original code (not part of VvvebJs).
 *
 * VvvebJs provides: the canvas iframe, highlight/selection, drag & drop of elements/blocks/sections, inline text editing, the property panels (content,
 * style = CSS rules per selector and breakpoint, advanced), the layer tree, breadcrumb and the undo history.
 * This file provides: project-scoped pages (list/create/delete/switch), saving (the page is sent as HTML + CSS text and the SERVER sanitises it: whatever
 * comes back is what was stored), the image library, preview, code view, dirty tracking and the status line.
 *
 * Trust: the canvas document runs no script (server CSP: sandbox + script-src 'none'), so customer markup is inert while editing; this page keeps a strict
 * CSP of its own and never evaluates strings.
 */
(function () {
  "use strict";

  const BODY = document.body;
  const CFG = { api: BODY.dataset.api || "", upload: BODY.dataset.upload || "", anonymous: BODY.dataset.anonymous === "1", csrf: BODY.dataset.csrf || "", version: parseInt(BODY.dataset.version || "0", 10) || 0, uploads: BODY.dataset.uploads === "1", sanitizer: BODY.dataset.sanitizer === "1" };
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const state = { version: CFG.version, pages: [], slug: "index", saved: "", saving: false, preview: false, savedAt: null, loadedOnce: false, device: "none" };
  let frameDoc = null;

  // ---------------------------------------------------------------------------------------------------------------- small helpers
  function notify(message, kind = "info") {
    const host = $("#pxe-toasts");
    if (!host) return;
    const t = document.createElement("div");
    t.className = "pxe-toast pxe-toast--" + kind;
    t.setAttribute("role", kind === "error" ? "alert" : "status");
    t.textContent = message;
    host.append(t);
    setTimeout(() => t.remove(), kind === "error" ? 9000 : 4000);
  }
  window.displayToast = (type, title, message) => notify(String(message || title || ""), type === "danger" ? "error" : "info");   // text only

  async function call(path, opts = {}) {
    let res;
    try { res = await fetch(path, { credentials: "same-origin", ...opts, headers: { "X-Requested-With": "pixelite-editor", ...(opts.headers || {}) } }); }
    catch (e) { throw Object.assign(new Error("No connection to the server. Your changes are still here."), { network: true }); }
    let json = null;
    try { json = await res.json(); } catch (e) { /* not JSON */ }
    return { res, json };
  }
  const form = (fields) => new URLSearchParams({ _csrf: CFG.csrf, ...fields });
  const FORM_HEADERS = { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" };
  const pageUrl = (slug, kind) => `${CFG.api}/pages/${encodeURIComponent(slug)}/${kind}`;
  const currentPage = () => state.pages.find((p) => p.slug === state.slug) || { slug: state.slug, title: "" };

  // ---------------------------------------------------------------------------------------------------------------- Vvveb setup
  const noop = () => {};
  Vvveb.dragIcon = "html";
  Vvveb.helpersCss = "/assets/editor/builder-canvas.css";
  Vvveb.Builder.confirmLeave = () => false;    // the top window's beforeunload handler warns only when the page really differs from the saved one
  Vvveb.SectionList = { loadSections: noop, init: noop };
  Vvveb.NewSection = { init: noop };
  Vvveb.StyleList = { load: noop };
  Vvveb.ColorPaletteManager.init = noop;
  Vvveb.Gui.shortcuts = noop;
  // the layer tree is only drawn while its tab is visible: draw it when the tab is shown
  document.querySelector('[data-bs-target="#pxb-tab-layers"]').addEventListener("shown.bs.tab", () => { if (frameDoc) Vvveb.TreeList.loadComponents(); });

  // what the customer may add. Items missing from the loaded component files are skipped by the builder.
  Vvveb.ComponentsGroup = {
    "Text": ["html/heading", "html/paragraph", "html/link", "html/blockquote", "html/list", "html/preformatted"],
    "Layout": ["html/container", "html/gridrow", "html/card", "html/listgroup", "html/hr"],
    "Buttons and notices": ["html/btn", "html/buttongroup", "html/badge", "html/alert", "html/progress"],
    "Pictures": ["html/image"],
    "Navigation": ["html/breadcrumbs", "html/pagination"],
    "Tables": ["html/table"],
    "Forms": ["html/form", "html/textinput", "html/textareainput", "html/selectinput", "html/checkbox", "html/radiobutton", "html/label", "html/button"],
  };

  // Image library (picker used by every "Choose image" input)
  const media = { images: [], loaded: false };
  async function refreshMedia() {
    try {
      const { res, json } = await call(CFG.api + "/media");
      if (res.ok && json && json.ok) { media.images = json.images; media.loaded = true; }
    } catch (e) { /* keep the old list */ }
    renderMedia();
  }
  function mediaGrid(host, onPick) {
    host.replaceChildren();
    if (!media.images.length) {
      const p = document.createElement("p"); p.className = "pxe-field__hint"; p.textContent = "No images yet."; host.append(p); return;
    }
    media.images.forEach((m) => {
      const f = document.createElement("figure"); f.className = "pxb-media__item";
      const img = document.createElement("img"); img.src = m.url; img.alt = ""; img.loading = "lazy";
      f.append(img);
      if (onPick) {
        const b = document.createElement("button"); b.type = "button"; b.className = "pxb-media__pick"; b.setAttribute("aria-label", "Use this image");
        b.append(img); b.addEventListener("click", () => onPick(m.url)); f.replaceChildren(b);
      } else {
        const d = document.createElement("button"); d.type = "button"; d.className = "pxb-media__del"; d.textContent = m.used ? "In use" : "Delete"; d.disabled = !!m.used;
        d.title = m.used ? "Remove it from the page first" : "Delete this image";
        d.addEventListener("click", () => deleteImage(m, d));
        f.append(d);
      }
      host.append(f);
    });
  }
  function renderMedia() { mediaGrid($("#pxb-media")); const p = $("#pxb-picker"); if (p) mediaGrid(p, pickedUrl); }
  let pickCb = null;
  function pickedUrl(url) { if (pickCb) pickCb(url); pickCb = null; bootstrap.Modal.getOrCreateInstance($("#pxb-media-modal")).hide(); }
  Vvveb.MediaPicker = { open(cb) { pickCb = cb; renderMedia(); bootstrap.Modal.getOrCreateInstance($("#pxb-media-modal")).show(); refreshMedia(); } };

  async function uploadImage(file) {
    if (!CFG.uploads) { notify("Image uploads are not available on this server.", "error"); return; }
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { notify("Use a JPEG, PNG or WebP image.", "error"); return; }
    if (file.size > 4 * 1024 * 1024) { notify("Images must be at most 4 MB.", "error"); return; }
    const fd = new FormData(); fd.append("_csrf", CFG.csrf); fd.append("image", file);
    notify("Uploading…");
    try {
      const { res, json } = await call(CFG.upload, { method: "POST", body: fd });
      if (!res.ok || !json || !json.ok) throw new Error((json && json.error) || "The image could not be uploaded.");
      notify("Image uploaded.");
      await refreshMedia();
    } catch (e) { notify(e.message, "error"); }
  }
  async function deleteImage(m, btn) {
    if (!btn.dataset.armed) { btn.dataset.armed = "1"; btn.textContent = "Click again"; setTimeout(() => { delete btn.dataset.armed; if (btn.isConnected) btn.textContent = "Delete"; }, 3500); return; }
    try {
      const { res, json } = await call(`${CFG.api}/media/${m.id}/delete`, { method: "POST", headers: FORM_HEADERS, body: form({}) });
      if (!res.ok || !json || !json.ok) throw new Error((json && json.error) || "The image could not be deleted.");
      await refreshMedia();
    } catch (e) { notify(e.message, "error"); }
  }

  // ---------------------------------------------------------------------------------------------------------------- reading the canvas
  function cleanBody() {
    const b = frameDoc.body.cloneNode(true);
    $$("[contenteditable]", b).forEach((e) => e.removeAttribute("contenteditable"));
    $$("[spellcheckker]", b).forEach((e) => e.removeAttribute("spellcheckker"));
    $$("*", b).forEach((e) => { Array.from(e.attributes).forEach((a) => { if (/^data-vvveb-/i.test(a.name)) e.removeAttribute(a.name); }); });
    b.removeAttribute("data-vvvebjs-editor");
    return Vvveb.Builder.removeHelpers(b.innerHTML.trim());
  }
  const cssText = () => { const s = frameDoc.getElementById("vvvebjs-styles"); return s ? s.textContent : ""; };
  const pageTitle = () => ($("#pxb-page-title").value || "").trim();
  const collect = () => ({ title: pageTitle(), html: cleanBody(), css: cssText() });
  const snapshot = () => JSON.stringify(collect());
  const isDirty = () => frameDoc !== null && !state.preview && snapshot() !== state.saved;

  // ---------------------------------------------------------------------------------------------------------------- status / buttons
  function setStatus(kind, text, reload) {
    const el = $("#pxe-status");
    el.dataset.state = kind;
    const t = { saving: "Saving…", saved: state.savedAt ? "Draft saved " + state.savedAt.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : "Draft saved", dirty: "Unsaved changes", error: text || "Error", loading: "Loading…" }[kind];
    $(".pxe-status__text", el).textContent = t;
    $("#pxe-reload").hidden = !reload;
  }
  function refresh() {
    if (!frameDoc) return;
    const dirty = isDirty(), U = Vvveb.Undo;
    $("#pxe-save").disabled = !dirty || state.saving || state.preview;
    $("#undo-btn").disabled = U.undoIndex < 0 || state.preview;
    $("#redo-btn").disabled = U.undoIndex >= U.mutations.length - 1 || state.preview;
    $("#pxe-revert").disabled = !dirty;
    const cur = $("#pxe-status").dataset.state;
    if (!state.saving && cur !== "error") setStatus(dirty ? "dirty" : "saved");
    else if (cur === "error" && !dirty) setStatus("saved");
  }
  let refreshTimer = 0;
  const refreshSoon = () => { clearTimeout(refreshTimer); refreshTimer = setTimeout(refresh, 120); };

  // ---------------------------------------------------------------------------------------------------------------- saving
  async function save() {
    if (!frameDoc || state.saving || state.preview) return false;
    if (!isDirty()) { setStatus("saved"); return true; }
    state.saving = true; setStatus("saving");
    const data = collect();
    try {
      const { res, json } = await call(`${CFG.api}/pages/${encodeURIComponent(state.slug)}`, { method: "POST", headers: FORM_HEADERS, body: form({ base_version: String(state.version), title: data.title, html: data.html, css: data.css }) });
      if (res.ok && json && json.ok) {
        state.version = json.version; state.savedAt = new Date();
        const p = currentPage(); if (data.title) p.title = data.title;
        renderPages();
        if (json.notes && json.notes.length) {
          json.notes.slice(0, 4).forEach((n) => notify(n, "error"));
          notify("Some content was changed to keep your page safe. The page was reloaded to show what was saved.", "error");
          state.saved = ""; reloadCanvas();
        } else {
          state.saved = JSON.stringify(data);
          setStatus("saved");
        }
        return true;
      }
      if (res.status === 409) setStatus("error", (json && json.error) || "Changed in another window.", true);
      else if (res.status === 401 || res.status === 403) setStatus("error", "Your session has expired. Open the builder again from your account (unsaved changes cannot be kept).");
      else if (res.status === 429) setStatus("error", "Too many saves in a short time. Wait a moment and try again.");
      else setStatus("error", (json && json.error) || "The page could not be saved. Try again.");
      return false;
    } catch (e) { setStatus("error", e.message); return false; }
    finally { state.saving = false; refresh(); }
  }

  // ---------------------------------------------------------------------------------------------------------------- pages
  function renderPages() {
    const ul = $("#pxb-pages"); ul.replaceChildren();
    state.pages.forEach((p) => {
      const li = document.createElement("li"); li.className = "pxb-list__item" + (p.slug === state.slug ? " is-current" : "");
      const open = document.createElement("button"); open.type = "button"; open.className = "pxb-list__open"; open.textContent = p.title || p.slug; open.title = "/" + p.slug;
      open.setAttribute("aria-current", String(p.slug === state.slug));
      open.addEventListener("click", () => switchPage(p.slug));
      li.append(open);
      if (p.slug !== "index") {
        const del = document.createElement("button"); del.type = "button"; del.className = "pxb-list__del"; del.textContent = "Delete"; del.setAttribute("aria-label", "Delete page " + (p.title || p.slug));
        del.addEventListener("click", () => deletePage(p, del));
        li.append(del);
      }
      ul.append(li);
    });
    $("#pxb-page-label").textContent = currentPage().title || state.slug;
  }
  async function loadSite() {
    const { res, json } = await call(CFG.api + "/site");
    if (!res.ok || !json || !json.ok) throw new Error("The site could not be loaded.");
    state.pages = json.pages; state.version = json.version;
    if (!state.pages.some((p) => p.slug === state.slug)) state.slug = "index";
  }
  function reloadCanvas() { Vvveb.Builder.loadUrl(pageUrl(state.slug, "canvas") + "?t=" + Date.now()); }
  async function switchPage(slug) {
    if (slug === state.slug) return;
    if (isDirty() && !(await save())) { notify("Save or discard your changes before switching pages.", "error"); return; }
    state.slug = slug; Vvveb.FileManager.currentFile = slug; setStatus("loading"); renderPages(); reloadCanvas();
  }
  async function deletePage(p, btn) {
    if (!btn.dataset.armed) { btn.dataset.armed = "1"; btn.textContent = "Click again"; setTimeout(() => { delete btn.dataset.armed; if (btn.isConnected) btn.textContent = "Delete"; }, 3500); return; }
    try {
      const { res, json } = await call(`${CFG.api}/pages/${encodeURIComponent(p.slug)}/delete`, { method: "POST", headers: FORM_HEADERS, body: form({ base_version: String(state.version) }) });
      if (!res.ok || !json || !json.ok) throw new Error((json && json.error) || "The page could not be deleted.");
      state.version = json.version; state.pages = state.pages.filter((x) => x.slug !== p.slug);
      if (state.slug === p.slug) { state.slug = "index"; state.saved = ""; renderPages(); reloadCanvas(); } else renderPages();
    } catch (e) { notify(e.message, "error"); }
  }
  const slugify = (t) => t.toLowerCase().normalize("NFKD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 40);
  async function createPage(e) {
    e.preventDefault();
    const title = $("#pxb-new-title").value.trim(), slug = slugify(title);
    if (!title || !slug) { notify("Enter a name for the page (letters or digits).", "error"); return; }
    if (state.pages.some((p) => p.slug === slug)) { notify("A page with that name already exists.", "error"); return; }
    if (isDirty() && !(await save())) { notify("Save or discard your changes first.", "error"); return; }
    try {
      const { res, json } = await call(CFG.api + "/pages", { method: "POST", headers: FORM_HEADERS, body: form({ slug, title, base_version: String(state.version) }) });
      if (!res.ok || !json || !json.ok) throw new Error((json && json.error) || "The page could not be created.");
      state.version = json.version; state.pages.push({ slug, title }); $("#pxb-new-title").value = "";
      state.slug = slug; Vvveb.FileManager.currentFile = slug; renderPages(); setStatus("loading"); reloadCanvas();
    } catch (err) { notify(err.message, "error"); }
  }

  // ---------------------------------------------------------------------------------------------------------------- inserting by click, code view, preview, devices
  function componentFor(li) {
    const t = li.dataset.dragType, id = li.dataset.type;
    return t === "block" ? Vvveb.Blocks.get(id) : t === "section" ? Vvveb.Sections.get(id) : Vvveb.Components.get(id);
  }
  function insertClicked(li) {
    const comp = componentFor(li);
    if (!comp || !comp.html || !frameDoc) return;
    const node = generateElements(comp.html.replace("RANDOM_ID", Math.floor(Math.random() * 1000)))[0];
    if (!node) return;
    const sel = Vvveb.Builder.selectedEl;
    const isSection = li.dataset.dragType === "section" || /^(SECTION|HEADER|FOOTER|NAV|MAIN)$/.test(node.tagName);
    let anchor = sel && sel !== frameDoc.body ? sel : null;
    if (isSection && anchor) { while (anchor.parentElement && anchor.parentElement !== frameDoc.body) anchor = anchor.parentElement; }
    if (anchor) anchor.after(node); else frameDoc.body.append(node);
    if (comp.afterDrop) comp.afterDrop(node);
    Vvveb.Undo.addMutation({ type: "childList", target: node.parentNode, addedNodes: [node], nextSibling: node.nextSibling });
    Vvveb.Builder.selectNode(node); Vvveb.Builder.loadNodeComponent(node); Vvveb.TreeList.loadComponents();
    pxScrollToNode(node);
  }

  function fillCode() { if (!frameDoc) return; $("#pxb-html-code").value = cleanBody(); $("#css-editor").value = cssText(); }
  Vvveb.Gui.toggleEditor = function () {
    const open = $("#vvveb-code-editor").hidden;
    $("#vvveb-code-editor").hidden = !open;
    $(".breadcrumb-navigator").hidden = open;
    BODY.classList.toggle("code-open", open);
    $("#code-editor-btn").setAttribute("aria-pressed", String(open));
    if (open) fillCode();
  };
  function applyHtml() {
    const old = frameDoc.body.innerHTML;
    frameDoc.body.innerHTML = $("#pxb-html-code").value;
    Vvveb.Undo.addMutation({ type: "characterData", target: frameDoc.body, oldValue: old, newValue: frameDoc.body.innerHTML });
    Vvveb.Builder.selectNode(false); Vvveb.TreeList.loadComponents(); refresh();
  }
  function applyCss() {
    const old = cssText(), next = $("#css-editor").value;
    Vvveb.StyleManager.styles = {};
    Vvveb.StyleManager.setCss(next);
    Vvveb.Undo.addMutation({ type: "style", target: frameDoc.body, oldValue: old, newValue: next });
    refresh();
  }
  Vvveb.ModalCodeEditor.init();   // "HTML of this element"

  async function setPreview(on) {
    if (on) {
      if (isDirty() && !(await save())) { notify("Save the page before previewing: see the message at the top.", "error"); return; }
      $("#pxe-preview-frame").src = pageUrl(state.slug, "preview") + "?t=" + Date.now();
    }
    state.preview = on;
    BODY.classList.toggle("is-preview", on);
    $(".pxe-preview").setAttribute("aria-hidden", String(!on));
    Vvveb.Builder.highlightEnabled = !on; Vvveb.Builder.isPreview = on;
    Vvveb.Builder.selectNode(false);
    $$("[data-mode-btn]").forEach((b) => b.setAttribute("aria-pressed", String((b.dataset.modeBtn === "preview") === on)));
    if (on) { $("#pxe-save").disabled = true; $("#undo-btn").disabled = true; $("#redo-btn").disabled = true; } else refresh();
  }

  // responsive styles: Vvveb's viewport() sets the frame width and the CSS breakpoint future style edits apply to
  const origViewport = Vvveb.Gui.viewport;
  Vvveb.Gui.viewport = function () {
    origViewport.call(this);
    const bp = Vvveb.StyleManager.currentBreakpoint;
    $$("[data-device-btn]").forEach((b) => b.setAttribute("aria-pressed", String(b.dataset.view ? b.dataset.view === bp : bp === "none")));
    const w = $("#iframe-wrapper").style.width;
    $(".pxe-preview").style.width = w;
    Vvveb.Builder.selectNode(false);
  };
  function desktopView() {
    const active = $(".responsive-btns [data-vvveb-action=viewport].active");
    if (active) active.click();
    else { $$("[data-device-btn]").forEach((b) => b.setAttribute("aria-pressed", String(b.dataset.deviceBtn === "desktop"))); }
  }

  let revertTimer = 0;
  function revert() {
    const b = $("#pxe-revert");
    if (!b.dataset.armed) {
      b.dataset.armed = "1"; b.dataset.label = b.textContent; b.textContent = "Click again to discard";
      revertTimer = setTimeout(() => { delete b.dataset.armed; b.textContent = b.dataset.label; }, 4000);
      return;
    }
    clearTimeout(revertTimer); delete b.dataset.armed; b.textContent = b.dataset.label; state.saved = ""; setStatus("loading"); reloadCanvas();
  }
  function shortcut(e) {
    if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
    const k = e.key.toLowerCase();
    if (k === "s") { e.preventDefault(); save(); }
    else if (k === "z") { e.preventDefault(); (e.shiftKey ? Vvveb.Gui.redo : Vvveb.Gui.undo).call(Vvveb.Gui); refresh(); }
    else if (k === "y") { e.preventDefault(); Vvveb.Gui.redo.call(Vvveb.Gui); refresh(); }
  }

  // ---------------------------------------------------------------------------------------------------------------- the canvas document
  function installFrameGuards(doc) {
    const stop = (e) => { e.preventDefault(); e.stopPropagation(); };
    // paste as plain text (rich paste would drag foreign styles/markup into the page; the server would strip most of it anyway)
    doc.addEventListener("paste", (e) => {
      const el = e.target.closest && e.target.closest("[contenteditable]");
      if (!el) return;
      e.preventDefault();
      const text = (e.clipboardData && e.clipboardData.getData("text/plain")) || "";
      doc.execCommand("insertText", false, text);
    }, true);
    ["drop", "dragover"].forEach((n) => doc.addEventListener(n, stop, true));   // no dropping files, links or markup into the page
    doc.addEventListener("input", refreshSoon, true);
    doc.addEventListener("keydown", shortcut, true);
    // links and buttons must never navigate or submit while editing (the document is sandboxed anyway)
    doc.addEventListener("submit", stop, true);
  }

  window.addEventListener("Vvveb.iframe.loaded", (ev) => {
    frameDoc = ev.detail;
    installFrameGuards(frameDoc);
    ["vvveb.undo.add", "vvveb.undo.restore"].forEach((n) => Vvveb.Builder.frameBody.addEventListener(n, (e) => {
      if (e.detail && e.detail.type === "style") { Vvveb.StyleManager.styles = {}; Vvveb.StyleManager.loadCss(); }
      refresh(); if ($("#vvveb-code-editor") && !$("#vvveb-code-editor").hidden) fillCode();
    }));
    Vvveb.Undo.reset();
    $("#pxb-page-title").value = currentPage().title || "";
    state.saved = snapshot();
    Vvveb.TreeList.loadComponents();
    $("#pxe-empty").hidden = false;
    setStatus("saved"); refresh();
    BODY.classList.add("is-ready");
  });
  window.addEventListener("Vvveb.Builder.selectNode", (e) => { $("#pxe-empty").hidden = !!(e.detail && e.detail.target); });
  window.addEventListener("beforeunload", (e) => { if (isDirty()) { e.preventDefault(); e.returnValue = ""; } });

  // ---------------------------------------------------------------------------------------------------------------- wiring + boot
  $("#pxe-save").addEventListener("click", save);
  $("#pxe-revert").addEventListener("click", revert);
  $("#pxe-reload").addEventListener("click", () => { state.saved = ""; location.reload(); });
  $$("[data-mode-btn]").forEach((b) => b.addEventListener("click", () => setPreview(b.dataset.modeBtn === "preview")));
  $("#pxb-view-desktop").addEventListener("click", desktopView);
  $("#pxb-new-page").addEventListener("submit", createPage);
  $("#pxb-page-title").addEventListener("input", () => { $("#pxb-page-label").textContent = pageTitle() || state.slug; refreshSoon(); });
  $("#pxb-upload").addEventListener("change", (e) => { const f = e.target.files && e.target.files[0]; e.target.value = ""; if (f) uploadImage(f); });
  $("#pxb-apply-html").addEventListener("click", applyHtml);
  $("#pxb-apply-css").addEventListener("click", applyCss);
  $$(".drag-elements-sidepane").forEach((pane) => pane.addEventListener("click", (e) => {
    const li = e.target.closest("li[data-drag-type]");
    if (!li) return;
    if (pane.closest("#add-section-box")) return;   // the add-box has its own click handler (inserts relative to the highlighted element)
    insertClicked(li);
  }));
  $("#add-section-box").addEventListener("click", () => setTimeout(refresh, 50));
  document.addEventListener("keydown", shortcut);
  setStatus("loading");

  (async function boot() {
    try {
      if (!CFG.sanitizer) throw new Error("Saving pages is not available on this server (PHP DOM extension missing).");
      Vvveb.Gui.init();
      Vvveb.TreeList.init();
      Vvveb.Breadcrumb.init();
      await loadSite();
      renderPages();
      Vvveb.Builder.init(pageUrl(state.slug, "canvas"), null);
      refreshMedia();
    } catch (e) { setStatus("error", e.message); $(".loading-message").classList.remove("active"); }
  })();
})();
