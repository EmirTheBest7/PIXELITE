// Theme switch: Light / Dark / System. A choice is stored (localStorage) only when the visitor actively picks one.
(function () {
  var KEY = 'pixelite_theme';
  var root = document.documentElement;
  var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var ORDER = ['system', 'light', 'dark'];

  function stored() {
    try { var v = localStorage.getItem(KEY); return v === 'light' || v === 'dark' ? v : 'system'; } catch (e) { return 'system'; }
  }
  function effectiveDark(mode) { return mode === 'dark' || (mode === 'system' && mq && mq.matches); }

  function render(mode) {
    if (mode === 'system') { root.removeAttribute('data-theme'); } else { root.setAttribute('data-theme', mode); }
    document.querySelectorAll('[data-theme-set]').forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-theme-set') === mode ? 'true' : 'false');
    });
    document.querySelectorAll('[data-theme-cycle]').forEach(function (b) {
      var labels = JSON.parse(b.getAttribute('data-labels'));
      var next = ORDER[(ORDER.indexOf(mode) + 1) % ORDER.length];
      b.setAttribute('data-mode', mode);
      var text = labels.theme.replace('{mode}', labels[mode]) + '. ' + labels.next.replace('{mode}', labels[next]);
      b.setAttribute('aria-label', text);
      b.setAttribute('title', text);
    });
    var color = effectiveDark(mode) ? '#0a0f1a' : '#3a9fff';
    document.querySelectorAll('meta[name="theme-color"]').forEach(function (m) { m.setAttribute('content', color); });
  }

  var current = stored();   // in-memory, so cycling still works when localStorage is blocked
  function choose(mode) {
    current = mode;
    try { if (mode === 'system') { localStorage.removeItem(KEY); } else { localStorage.setItem(KEY, mode); } } catch (e) { /* not persisted */ }
    render(mode);
  }

  document.addEventListener('click', function (e) {
    var set = e.target.closest('[data-theme-set]');
    if (set) { choose(set.getAttribute('data-theme-set')); return; }
    var cyc = e.target.closest('[data-theme-cycle]');
    if (cyc) { choose(ORDER[(ORDER.indexOf(current) + 1) % ORDER.length]); }
  });
  if (mq && mq.addEventListener) { mq.addEventListener('change', function () { if (current === 'system') { render('system'); } }); }
  render(current);
})();
