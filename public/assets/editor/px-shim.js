/*
 * Pixelite visual editor (EXPERIMENTAL) – minimal stand-in for the few Bootstrap 5 JavaScript classes that the vendored VvvebJs builder.js calls
 * (Tab, Modal, Toast, Offcanvas). Pixelite does not ship Bootstrap's JavaScript; the editor never opens VvvebJs' modals, toasts or tabs
 * (the shell replaces them), so these only need to exist and be harmless. Original code, not derived from Bootstrap.
 */
(function () {
  "use strict";
  function make() {
    const cache = new WeakMap();
    return class {
      constructor(el) { this._el = el; }
      static getInstance(el) { return cache.get(el) || null; }
      static getOrCreateInstance(el) {
        if (!cache.has(el)) cache.set(el, new this(el));
        return cache.get(el);
      }
      show() {}
      hide() {}
      toggle() {}
    };
  }
  window.bootstrap = { Tab: make(), Modal: make(), Toast: make(), Offcanvas: make() };
})();
