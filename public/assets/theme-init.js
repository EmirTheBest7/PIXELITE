// Runs synchronously in <head>, before first paint, so an explicit theme choice never flashes the wrong theme.
// "System" = no attribute; CSS then follows prefers-color-scheme.
(function () {
  try {
    var t = localStorage.getItem('pixelite_theme');
    if (t === 'light' || t === 'dark') { document.documentElement.setAttribute('data-theme', t); }
  } catch (e) { /* storage blocked: fall back to the system theme */ }
})();
