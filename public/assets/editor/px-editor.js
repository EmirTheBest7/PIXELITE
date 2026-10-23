/*
 * Pixelite visual editor (EXPERIMENTAL) – glue between the Pixelite editor shell and the vendored VvvebJs builder (vendor/vvvebjs, Apache-2.0).
 *
 * VvvebJs provides the canvas iframe, element highlight/selection, the property-panel machinery, inline text editing and the undo history.
 * Everything else is Pixelite's, and deliberately narrow:
 *   - only elements marked data-px-field by the server can be hovered, selected or edited (Builder.resolveTarget); the rest of the template is inert;
 *   - no drag & drop, clone, delete, code view or free-form styling is wired up (their VvvebJs buttons do not exist in the shell);
 *   - saving NEVER sends HTML: collect() reads typed values (text, link, image id, colour, bool) out of the canvas, the server validates every
 *     one against config/editor.php and re-renders the document itself.
 * Original code (not part of VvvebJs).
 */
(function () {
  "use strict";

  const BODY = document.body;
  const CFG = { base: BODY.dataset.base || "", csrf: BODY.dataset.csrf || "", version: parseInt(BODY.dataset.version || "0", 10) || 0, uploads: BODY.dataset.uploads === "1" };
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // Field metadata for the UI. The limits mirror config/editor.php for friendliness only; the server enforces them.
  const FIELDS = {
    nav_home: { label: "Menu item 1", max: 18 }, nav_about: { label: "Menu item 2", max: 18 }, nav_services: { label: "Menu item 3", max: 18 }, nav_contact: { label: "Menu item 4", max: 18 },
    signup_label: { label: "Sign-up button", max: 14 }, hero_line1: { label: "Headline, line 1", max: 22 }, hero_line2: { label: "Headline, line 2", max: 22 },
    hero_copy: { label: "Intro text", max: 260, lines: 4 }, cta_label: { label: "Main button label", max: 14 }, cta_href: { label: "Main button link" },
    show_demo_card: { label: "Demo card" }, demo_image: { label: "Demo card picture" }, demo_alt: { label: "Picture description", max: 120 }, watch_label: { label: "Demo button label", max: 16 },
    nav_home_href: { label: "Menu item 1 link" }, nav_about_href: { label: "Menu item 2 link" }, nav_services_href: { label: "Menu item 3 link" }, nav_contact_href: { label: "Menu item 4 link" },
  };
  const THEME = [
    { key: "color_title_1", cssVar: "--px-title-1", label: "Headline, line 1" },
    { key: "color_title_2", cssVar: "--px-title-2", label: "Headline, line 2" },
    { key: "color_copy", cssVar: "--px-copy", label: "Intro text" },
    { key: "color_cta", cssVar: "--px-cta-bg", label: "Main button" },
  ];
  const TYPE_NAME = { text: "Text", navlink: "Menu link", multiline: "Text", link: "Link", image: "Image", section: "Block" };
  const HEX = /^#[0-9a-f]{6}$/;

  // ---------------------------------------------------------------------------------------------------------------- helpers
  const state = { version: CFG.version, saved: "", saving: false, preview: false, device: "desktop", statusUntil: 0 };
  let frameDoc = null;

  function notify(message, kind = "info") {
    const host = $("#pxe-toasts");
    if (!host) return;
    const t = document.createElement("div");
    t.className = "pxe-toast pxe-toast--" + kind;
    t.setAttribute("role", kind === "error" ? "alert" : "status");
    t.textContent = message;
    host.append(t);
    setTimeout(() => t.remove(), kind === "error" ? 9000 : 3500);
  }
  // VvvebJs' own toast helper would write HTML into the page; route it to the text-only one.
  window.displayToast = (type, title, message) => notify(String(message || title || ""), type === "danger" ? "error" : "info");

  const onColor = (hex) => {
    const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)));
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2] > 0.179 ? "#111111" : "#ffffff";
  };
  // Mirrors Schema::link() in PHP: https/http, mailto, tel or #anchor only.
  function linkProblem(v) {
    if (v === "" || /^#[A-Za-z0-9_-]{0,64}$/.test(v)) return "";
    if (v.length > 300 || /[\s"'<>\\`]/.test(v)) return "Enter a valid link.";
    if (/^https?:\/\/[A-Za-z0-9.-]+\.[A-Za-z0-9.-]+(?:[/?#:][^\s]*)?$/i.test(v)) return "";
    if (/^mailto:[^\s@?]+@[^\s@?]+\.[^\s@?]+$/i.test(v) || /^tel:\+?[0-9]{5,20}$/.test(v)) return "";
    return "Links may start with https://, http://, mailto:, tel: or #.";
  }

  // ---------------------------------------------------------------------------------------------------------------- undoable edits
  function push(mutation) { Vvveb.Undo.addMutation(mutation); }
  function setAttr(el, name, value) {
    const old = el.getAttribute(name);
    if (value === null || value === "") el.removeAttribute(name); else el.setAttribute(name, value);
    push({ type: "attributes", target: el, attributeName: name, oldValue: old, newValue: value === "" ? null : value });
  }
  function setText(el, text) {
    const old = el.innerHTML;
    el.textContent = text;
    push({ type: "characterData", target: el, oldValue: old, newValue: el.innerHTML });
  }
  function fillLines(el, text) {
    el.replaceChildren();
    text.split("\n").forEach((line, i) => {
      if (i) el.append(el.ownerDocument.createElement("br"));
      el.append(el.ownerDocument.createTextNode(line));
    });
  }
  function setLines(el, text) {
    const old = el.innerHTML;
    fillLines(el, text);
    push({ type: "characterData", target: el, oldValue: old, newValue: el.innerHTML });
  }
  function setThemeColor(item, hex) {
    const body = frameDoc.body, old = body.getAttribute("style");
    if (hex) {
      body.style.setProperty(item.cssVar, hex);
      if (item.key === "color_cta") body.style.setProperty("--px-cta-text", onColor(hex));
    } else {
      body.style.removeProperty(item.cssVar);
      if (item.key === "color_cta") body.style.removeProperty("--px-cta-text");
    }
    if (!body.getAttribute("style")) body.removeAttribute("style");
    push({ type: "attributes", target: body, attributeName: "style", oldValue: old, newValue: body.getAttribute("style") });
  }
  const themeValue = (item) => { const v = (frameDoc.body.style.getPropertyValue(item.cssVar) || "").trim().toLowerCase(); return HEX.test(v) ? v : ""; };

  // ---------------------------------------------------------------------------------------------------------------- reading the canvas
  function nodeLines(el) {
    let out = "";
    el.childNodes.forEach((n) => { out += n.nodeType === 3 ? n.nodeValue : n.nodeName === "BR" ? "\n" : n.textContent; });
    return out.split("\n").map((l) => l.replace(/[ \t ]+/g, " ").trim()).filter(Boolean).join("\n");
  }
  const oneLine = (s) => s.replace(/\s+/g, " ").trim();

  /** The draft as typed values – never markup. This is the only thing that is ever sent to the server. */
  function collect() {
    const v = {};
    $$("[data-px-field]", frameDoc).forEach((el) => {
      const key = el.dataset.pxField, type = el.dataset.pxType;
      if (type === "text") v[key] = oneLine(el.textContent);
      else if (type === "navlink") { v[key] = oneLine(el.textContent); v[el.dataset.pxHref] = el.getAttribute("href") || ""; }
      else if (type === "multiline") v[key] = nodeLines(el);
      else if (type === "link") v[key] = el.getAttribute("href") || "";
      else if (type === "image") { v[key] = el.getAttribute("data-px-media") || ""; v[el.dataset.pxAlt] = oneLine(el.getAttribute("alt") || ""); }
      else if (type === "section") v[key] = el.getAttribute("data-px-visible") !== "0";
    });
    THEME.forEach((t) => { v[t.key] = themeValue(t); });
    return v;
  }
  const snapshot = () => { const v = collect(); return JSON.stringify(v, Object.keys(v).sort()); };
  const isDirty = () => frameDoc !== null && snapshot() !== state.saved;

  // ---------------------------------------------------------------------------------------------------------------- inputs
  const PxTextInput = { ...TextInput, events: [["change", "onChange", "input"]] };
  const PxTextareaInput = { ...TextareaInput, events: [["change", "onChange", "textarea"]] };
  const PxColorInput = {
    ...ColorInput,
    events: [["change", "onChange", "input[type=color]"], ["click", "onReset", "[data-color-reset]"]],
    onReset(event, node, input) { node.dispatchEvent(new CustomEvent("propertyChange", { detail: { value: "", input, origEvent: event } })); },
    setValue(value) {
      const root = this.element[0], i = $("input[type=color]", root), label = $("[data-color-label]", root);
      if (HEX.test(value || "")) { i.value = value; label.textContent = value.toUpperCase(); root.classList.add("is-set"); }
      else { i.value = "#000000"; label.textContent = "Template default"; root.classList.remove("is-set"); }
    },
    init(data) { return this.render("colorinput", data); },
  };
  const PxImageInput = {
    ...Input,
    events: [["change", "onFile", "input[type=file]"], ["click", "onReset", "[data-image-reset]"]],
    init(data) { return this.render("pximage", data); },
    setValue(src) { const img = $("img", this.element[0]); if (img) img.src = src || ""; },
    onReset(event, node, input) { node.dispatchEvent(new CustomEvent("propertyChange", { detail: { value: { id: "", url: "" }, input, origEvent: event } })); },
    async onFile(event, node, input) {
      const file = this.files && this.files[0];
      this.value = "";
      if (!file) return;
      try {
        const res = await uploadImage(file);
        node.dispatchEvent(new CustomEvent("propertyChange", { detail: { value: res, input, origEvent: event } }));
      } catch (e) { notify(e.message, "error"); }
    },
  };
  const PxCheckboxInput = { ...CheckboxInput };

  // ---------------------------------------------------------------------------------------------------------------- components (the whitelist)
  const limitFor = (el) => (FIELDS[el.dataset.pxField] || {}).max;
  const applyMax = (el, input) => { const i = $("input,textarea", input), m = limitFor(el); if (i && m) i.maxLength = m; };
  const textProp = (name) => ({
    name, key: "text", inputtype: PxTextInput,
    init: (el) => oneLine(el.textContent), afterInit: applyMax,
    onChange: (el, value) => { const v = oneLine(value); if (v === "") { notify("This text cannot be empty.", "error"); return el; } setText(el, v.slice(0, limitFor(el) || 200)); return el; },
  });
  const linkProp = (name, resolve) => ({
    name, key: "href", inputtype: PxTextInput, data: { placeholder: "https://… , mailto:… , tel:… or #section" },
    init: (el) => resolve(el).getAttribute("href") || "",
    onChange: (el, value) => {
      const v = value.trim(), problem = linkProblem(v);
      if (problem) { notify(problem, "error"); return el; }
      setAttr(resolve(el), "href", v === "" ? "#" : v);
      return el;
    },
  });
  const self = (el) => el;

  Vvveb.Components.add("_base", { name: "Element", properties: [] });
  Vvveb.Components.add("px/text", { name: "Text", attributes: { "data-px-type": "text" }, properties: [textProp("Wording")] });
  Vvveb.Components.add("px/navlink", { name: "Menu link", attributes: { "data-px-type": "navlink" }, properties: [textProp("Wording"), linkProp("Link", self)] });
  Vvveb.Components.add("px/link", { name: "Link", attributes: { "data-px-type": "link" }, properties: [linkProp("Where the button goes", self)] });
  Vvveb.Components.add("px/multiline", {
    name: "Text block", attributes: { "data-px-type": "multiline" },
    properties: [{
      name: "Text", key: "text", inputtype: PxTextareaInput, data: { rows: 5 },
      init: (el) => nodeLines(el), afterInit: applyMax,
      onChange: (el, value) => {
        const max = limitFor(el), lines = value.replace(/\r/g, "").split("\n").map((l) => l.replace(/[ \t]+/g, " ").trim()).filter(Boolean);
        if (!lines.length) { notify("This text cannot be empty.", "error"); return el; }
        if (lines.length > 4) { notify("Use at most 4 lines.", "error"); return el; }
        if (lines.join("\n").length > max) { notify("Use at most " + max + " characters.", "error"); return el; }
        setLines(el, lines.join("\n"));
        return el;
      },
    }],
  });
  Vvveb.Components.add("px/image", {
    name: "Picture", attributes: { "data-px-type": "image" },
    properties: [
      {
        name: "Picture", key: "image", inputtype: PxImageInput, init: (el) => el.getAttribute("src") || "",
        afterInit: (el, input) => { if (!CFG.uploads) { $("label.btn", input).setAttribute("aria-disabled", "true"); $("input[type=file]", input).disabled = true; $(".pxe-image", input).append(Object.assign(document.createElement("p"), { className: "pxe-field__hint", textContent: "Image uploads are not available on this server." })); } },
        // One undo step: only data-px-media is recorded; src is always derived from it (syncImages), also after undo/redo.
        onChange: (el, value) => { setAttr(el, "data-px-media", value.id || ""); syncImages(); return el; },
      },
      {
        name: "Description (for screen readers)", key: "alt", inputtype: PxTextInput, init: (el) => el.getAttribute("alt") || "",
        afterInit: applyMax,
        onChange: (el, value) => { setAttr(el, "alt", oneLine(value).slice(0, 120)); return el; },
      },
    ],
  });
  const toggleCard = (card, on) => { if ((card.getAttribute("data-px-visible") !== "0") !== on) setAttr(card, "data-px-visible", on ? "1" : "0"); };
  Vvveb.Components.add("px/section", {
    name: "Demo card", attributes: { "data-px-type": "section" },
    properties: [{
      name: "", key: "visible", inputtype: PxCheckboxInput, data: { text: "Show this block" },
      init: (el) => el.getAttribute("data-px-visible") !== "0",
      onChange: (el, checked) => { toggleCard(el, !!checked); return el; },
    }],
  });

  // Names shown on the hover label
  Vvveb.Builder._getElementType = (el) => {
    const f = el.dataset && el.dataset.pxField;
    return [f && FIELDS[f] ? FIELDS[f].label : "", (TYPE_NAME[el.dataset && el.dataset.pxType] || "")];
  };
  // Only whitelisted elements can be selected; only text-like ones (and never <button>) can be typed into on the canvas.
  Vvveb.Builder.resolveTarget = (node) => (node && node.closest ? node.closest("[data-px-field]") : null);
  Vvveb.Builder.canEditText = (el) => ["text", "navlink", "multiline"].includes(el.dataset.pxType) && !el.closest("button");

  // Neutralise the VvvebJs modules this experiment does not use (they expect UI that the shell does not have, or write inline <style>).
  const noop = () => {};
  Vvveb.TreeList = { selectComponent: noop, loadComponents: noop };
  Vvveb.SectionList = { loadSections: noop };
  Vvveb.Breadcrumb = { loadBreadcrumb: noop };
  Vvveb.NewSection = { init: noop };
  Vvveb.StyleList = { load: noop };
  Vvveb.StyleManager = { init: noop, getStyle: () => "" };
  Vvveb.ColorPaletteManager = { init: noop };
  Vvveb.Gui.shortcuts = noop;
  Vvveb.Builder.confirmLeave = () => false;   // the top window's beforeunload handler (below) warns only when the draft really differs from the saved one
  Vvveb.helpersCss = "/assets/editor/canvas.css";

  // ---------------------------------------------------------------------------------------------------------------- inline text editing (plain text only)
  const supportsPlaintext = (() => { try { const d = document.createElement("div"); d.contentEditable = "plaintext-only"; return d.contentEditable === "plaintext-only"; } catch (e) { return false; } })();
  Vvveb.WysiwygEditor.edit = function (el) {
    el.setAttribute("contenteditable", supportsPlaintext ? "plaintext-only" : "true");
    el.setAttribute("spellcheck", "false");
    this.element = el; this.isActive = true; this.oldValue = el.innerHTML;
    el.focus();
  };
  Vvveb.WysiwygEditor.destroy = function () {
    const el = this.element;
    this.isActive = false;
    if (!el) return;
    el.removeAttribute("contenteditable"); el.removeAttribute("spellcheck");
    const multi = el.dataset.pxType === "multiline", max = limitFor(el) || 200;
    if (multi) fillLines(el, nodeLines(el).slice(0, max)); else el.textContent = oneLine(el.textContent).slice(0, max);   // whatever was typed or pasted becomes plain text again
    if (!oneLine(el.textContent)) { el.innerHTML = this.oldValue; notify("This text cannot be empty.", "error"); }
    if (el.innerHTML !== this.oldValue) push({ type: "characterData", target: el, oldValue: this.oldValue, newValue: el.innerHTML });
    refresh();
  };
  function finishEditing() {
    const b = Vvveb.Builder, el = b.texteditEl;
    if (!el) return;
    Vvveb.WysiwygEditor.destroy(el);
    b.selectPadding = 0; b.texteditEl = null;
    $("#select-box").classList.remove("text-edit");
    b.selectNode(el);
  }

  function installFrameGuards(doc) {
    const stop = (e) => { e.preventDefault(); e.stopPropagation(); };
    // Enter ends a one-line edit; in the intro text it inserts a line break (VvvebJs' own handler does that).
    doc.addEventListener("keydown", (e) => {
      const el = e.target.closest && e.target.closest("[contenteditable]");
      if (!el) return;
      if (e.key === "Escape") { stop(e); finishEditing(); }
      else if (e.key === "Enter" && el.dataset.pxType !== "multiline") { e.preventDefault(); e.stopImmediatePropagation(); finishEditing(); }
    }, true);
    // Paste as plain text, never as markup.
    doc.addEventListener("paste", (e) => {
      const el = e.target.closest && e.target.closest("[contenteditable]");
      if (!el) return;
      e.preventDefault();
      let text = (e.clipboardData && e.clipboardData.getData("text/plain")) || "";
      if (el.dataset.pxType !== "multiline") text = text.replace(/\s+/g, " ");
      doc.execCommand("insertText", false, text);
    }, true);
    ["drop", "dragover"].forEach((n) => doc.addEventListener(n, stop, true));   // no dropping files, links or markup into the page
    doc.addEventListener("beforeinput", (e) => { if (/^(format|insertFromDrop|insertFromYank)/.test(e.inputType || "")) e.preventDefault(); }, true);
    doc.addEventListener("input", refresh, true);
    doc.addEventListener("keydown", shortcut, true);
  }

  // ---------------------------------------------------------------------------------------------------------------- server calls
  async function call(url, opts) {
    let res;
    try { res = await fetch(CFG.base + url, { credentials: "same-origin", ...opts, headers: { "X-Requested-With": "pixelite-editor", ...(opts.headers || {}) } }); }
    catch (e) { throw Object.assign(new Error("No connection to the server. Your changes are still here."), { network: true }); }
    let json = null;
    try { json = await res.json(); } catch (e) { /* non-JSON error page */ }
    return { res, json };
  }
  async function uploadImage(file) {
    if (!CFG.uploads) throw new Error("Image uploads are not available on this server.");
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) throw new Error("Use a JPEG, PNG or WebP image.");
    if (file.size > 4 * 1024 * 1024) throw new Error("Images must be at most 4 MB.");
    const fd = new FormData();
    fd.append("_csrf", CFG.csrf); fd.append("image", file);
    notify("Uploading…");
    const { res, json } = await call("/media", { method: "POST", body: fd });
    if (!res.ok || !json || !json.ok) throw new Error((json && json.error) || "The image could not be uploaded.");
    return { id: json.id, url: json.url };
  }

  async function save() {
    if (!frameDoc || state.saving) return false;
    if (!isDirty()) { setStatus("saved"); return true; }
    state.saving = true; setStatus("saving");
    const values = collect();
    try {
      const { res, json } = await call("/draft", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body: new URLSearchParams({ _csrf: CFG.csrf, base_version: String(state.version), values: JSON.stringify(values) }),
      });
      if (res.ok && json && json.ok) {
        state.version = json.version; state.saved = snapshot(); state.savedAt = new Date();
        setStatus("saved");
        return true;
      }
      if (res.status === 422 && json && json.fields) {
        const msgs = Object.entries(json.fields).map(([k, m]) => ((FIELDS[k] || {}).label || k) + ": " + m);
        setStatus("error", msgs[0]); msgs.slice(0, 3).forEach((m) => notify(m, "error"));
      } else if (res.status === 409) setStatus("error", json.error, true);
      else if (res.status === 401 || res.status === 403) setStatus("error", "Your session has expired. Open the editor again from your account (your unsaved changes cannot be kept).");
      else if (res.status === 429) setStatus("error", "Too many saves in a short time. Wait a moment and try again.");
      else setStatus("error", (json && json.error) || "The draft could not be saved. Try again.");
      return false;
    } catch (e) { setStatus("error", e.message); return false; }
    finally { state.saving = false; refresh(); }
  }

  // ---------------------------------------------------------------------------------------------------------------- UI state
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
    $("#pxe-undo").disabled = U.undoIndex < 0 || state.preview;
    $("#pxe-redo").disabled = U.undoIndex >= U.mutations.length - 1 || state.preview;
    $("#pxe-revert").disabled = !dirty;
    const cur = $("#pxe-status").dataset.state;
    if (!state.saving && cur !== "error") setStatus(dirty ? "dirty" : "saved");
    else if (cur === "error" && !dirty) setStatus("saved");
    syncPanels();
  }
  const IMG_DEFAULT = "/assets/templates/vantage/img/watch-demo-thumbnail.jpg";
  function syncImages() {
    $$("[data-px-type=image]", frameDoc).forEach((img) => {
      const id = img.getAttribute("data-px-media") || "", want = id ? CFG.base + "/media/" + id : IMG_DEFAULT;
      if (img.getAttribute("src") !== want) img.setAttribute("src", want);
    });
  }
  function syncTitleWrap() {   // same rule as templates/demos/vantage-editable.php: wrap only once a headline differs from the template's
    const t = $(".hero-title", frameDoc), lines = $$("[data-px-default]", frameDoc);
    if (t) t.classList.toggle("px-wrap", lines.some((l) => oneLine(l.textContent) !== l.dataset.pxDefault));
  }
  function syncPanels() {
    if (!frameDoc) return;
    syncImages();
    syncTitleWrap();
    THEME.forEach((t) => { const row = $('[data-theme-row="' + t.key + '"]'); if (row) PxColorInput.setValue.call({ element: [$(".pxe-color", row)] }, themeValue(t)); });
    const card = $("[data-px-type=section]", frameDoc), box = $("#pxe-block-card");
    if (card && box) box.checked = card.getAttribute("data-px-visible") !== "0";
  }
  function undo() { if (state.preview) return; if (Vvveb.WysiwygEditor.isActive) Vvveb.WysiwygEditor.undo(); else Vvveb.Undo.undo(); Vvveb.Builder.selectNode(); refresh(); }
  function redo() { if (state.preview) return; if (Vvveb.WysiwygEditor.isActive) Vvveb.WysiwygEditor.redo(); else Vvveb.Undo.redo(); Vvveb.Builder.selectNode(); refresh(); }
  function shortcut(e) {
    if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
    const k = e.key.toLowerCase();
    if (k === "s") { e.preventDefault(); save(); }
    else if (k === "z") { e.preventDefault(); e.shiftKey ? redo() : undo(); }
    else if (k === "y") { e.preventDefault(); redo(); }
  }

  function buildPanels() {
    const host = $("#px-theme");
    host.replaceChildren();
    THEME.forEach((t) => {
      const row = document.createElement("div");
      row.className = "pxe-field"; row.dataset.themeRow = t.key;
      row.innerHTML = '<span class="pxe-field__label"></span><div class="input"></div>';
      $(".pxe-field__label", row).textContent = t.label;
      const input = PxColorInput.init({ key: t.key, name: t.label });
      $(".input", row).append(input);
      input.addEventListener("propertyChange", (e) => { const v = e.detail.value; setThemeColor(t, HEX.test(v) ? v : ""); refresh(); });
      host.append(row);
    });
    $("#pxe-block-card").addEventListener("change", (e) => { const card = $("[data-px-type=section]", frameDoc); if (card) { toggleCard(card, e.target.checked); refresh(); } });
  }

  const SIZES = { desktop: [1280, 800], tablet: [820, 1100], phone: [390, 844] };
  function fit() {
    const st = $("#canvas"), [w, h] = SIZES[state.device];
    st.style.setProperty("--k", String(Math.max(0.2, Math.min(1, (st.clientWidth - 32) / w, (st.clientHeight - 32) / h))));
  }
  function setDevice(name) {
    state.device = name;
    $("#canvas").dataset.device = name;
    fit();
    $$("[data-device-btn]").forEach((b) => b.setAttribute("aria-pressed", String(b.dataset.deviceBtn === name)));
    Vvveb.Builder.selectNode(false);
  }
  async function setPreview(on) {
    if (on) {
      if (isDirty() && !(await save())) { notify("Save the draft before previewing — see the message at the top.", "error"); return; }
      $("#pxe-preview-frame").src = CFG.base + "/preview?t=" + Date.now();
    }
    state.preview = on;
    BODY.classList.toggle("is-preview", on);
    $(".pxe-preview").setAttribute("aria-hidden", String(!on));
    Vvveb.Builder.highlightEnabled = !on;
    Vvveb.Builder.selectNode(false);
    $$("[data-mode-btn]").forEach((b) => b.setAttribute("aria-pressed", String((b.dataset.modeBtn === "preview") === on)));
    if (!on) refresh(); else { $("#pxe-save").disabled = true; $("#pxe-undo").disabled = true; $("#pxe-redo").disabled = true; }
  }

  let revertTimer = 0;
  function revert() {
    const b = $("#pxe-revert");
    if (!b.dataset.armed) {
      b.dataset.armed = "1"; b.dataset.label = b.textContent; b.textContent = "Click again to discard";
      revertTimer = setTimeout(() => { delete b.dataset.armed; b.textContent = b.dataset.label; }, 4000);
      return;
    }
    clearTimeout(revertTimer); state.saved = snapshot(); location.reload();   // reload shows the last saved draft
  }

  // ---------------------------------------------------------------------------------------------------------------- boot
  window.addEventListener("beforeunload", (e) => { if (isDirty()) { e.preventDefault(); e.returnValue = ""; } });

  window.addEventListener("Vvveb.iframe.loaded", (ev) => {
    frameDoc = ev.detail;
    // Colours arrive from the server's theme.css; copy them to the body's own style so that body.style is the single source of truth for undo and collect().
    THEME.forEach((t) => {
      const v = (getComputedStyle(frameDoc.body).getPropertyValue(t.cssVar) || "").trim().toLowerCase();
      if (HEX.test(v)) { frameDoc.body.style.setProperty(t.cssVar, v); if (t.key === "color_cta") frameDoc.body.style.setProperty("--px-cta-text", onColor(v)); }
    });
    installFrameGuards(frameDoc);
    ["vvveb.undo.add", "vvveb.undo.restore"].forEach((n) => Vvveb.Builder.frameBody.addEventListener(n, refresh));
    window.addEventListener("Vvveb.Builder.selectNode", (e) => { $("#pxe-empty").hidden = !!(e.detail && e.detail.target); });
    state.saved = snapshot();
    buildPanels();
    setStatus("saved");
    refresh();
    BODY.classList.add("is-ready");
  }, { once: true });

  $("#pxe-save").addEventListener("click", save);
  $("#pxe-undo").addEventListener("click", undo);
  $("#pxe-redo").addEventListener("click", redo);
  $("#pxe-revert").addEventListener("click", revert);
  $("#pxe-reload").addEventListener("click", () => { state.saved = snapshot(); location.reload(); });
  $$("[data-device-btn]").forEach((b) => b.addEventListener("click", () => setDevice(b.dataset.deviceBtn)));
  $$("[data-mode-btn]").forEach((b) => b.addEventListener("click", () => setPreview(b.dataset.modeBtn === "preview")));
  document.addEventListener("keydown", shortcut);
  new ResizeObserver(() => { fit(); Vvveb.Builder.selectNode(false); }).observe($("#canvas"));
  fit();
  setStatus("loading");

  Vvveb.Builder.init(CFG.base + "/canvas", null);
  if (window.matchMedia("(max-width: 700px)").matches) setDevice("phone");   // on a phone the phone-sized canvas is the one that fits
})();
