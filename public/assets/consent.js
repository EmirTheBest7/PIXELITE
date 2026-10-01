// Cookie consent. Necessary storage needs no consent; optional categories stay OFF until the visitor opts in.
// Nothing optional exists today (see config/consent.php) – this is the mechanism that keeps future ones gated.
(function () {
  var banner = document.getElementById('cookie-banner');
  var dialog = document.getElementById('cookie-dialog');
  if (!banner || !dialog) { return; }
  var VERSION = parseInt(banner.getAttribute('data-version'), 10) || 1;
  var MAX = (parseInt(banner.getAttribute('data-max-age-days'), 10) || 180) * 86400;
  var CATS = (banner.getAttribute('data-categories') || '').split(',').filter(Boolean);
  var NAME = 'pixelite_consent';
  var listeners = [];
  var opener = null;

  function read() {
    var m = document.cookie.match(/(?:^|; )pixelite_consent=([^;]*)/);
    if (!m) { return null; }
    try {
      var o = JSON.parse(decodeURIComponent(m[1]));
      if (!o || o.v !== VERSION || typeof o.c !== 'object' || o.c === null || typeof o.t !== 'number') { return null; }
      if (Date.now() / 1000 - o.t > MAX) { return null; }
      return o;
    } catch (e) { return null; }
  }
  function write(choice) {
    var c = {};
    CATS.forEach(function (k) { c[k] = choice[k] === true ? 1 : 0; });   // unknown/new categories default to OFF
    var o = { v: VERSION, c: c, t: Math.floor(Date.now() / 1000) };
    document.cookie = NAME + '=' + encodeURIComponent(JSON.stringify(o)) + '; path=/; max-age=' + MAX +
      '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    return o;
  }
  function allows(cat) { var s = read(); return !!(s && s.c && s.c[cat] === 1); }

  // Optional scripts stay inert (type="text/plain") until their category is allowed.
  function activate() {
    document.querySelectorAll('script[type="text/plain"][data-consent]').forEach(function (el) {
      if (!allows(el.getAttribute('data-consent')) || el.getAttribute('data-activated')) { return; }
      if (!el.getAttribute('data-src')) { return; }   // inline code is blocked by the CSP; only external same-policy scripts can be gated
      var s = document.createElement('script');
      s.src = el.getAttribute('data-src');
      el.setAttribute('data-activated', '1');
      el.parentNode.insertBefore(s, el.nextSibling);
    });
  }

  function pad() {   // keep the fixed banner from covering the end of the page (CSSOM is allowed by the CSP)
    document.body.style.paddingBottom = banner.hidden ? '' : banner.offsetHeight + 'px';
  }
  function showBanner(show) { banner.hidden = !show; pad(); }

  function save(choice, focusTarget) {
    var o = write(choice);
    showBanner(false);
    closeDialog();
    activate();
    listeners.forEach(function (fn) { fn(o); });
    var t = focusTarget || document.getElementById('main');
    if (t) { t.focus({ preventScroll: true }); }
  }
  function all(v) { var c = {}; CATS.forEach(function (k) { c[k] = v; }); return c; }

  function isOpen() { return dialog.hasAttribute('open'); }
  function closeDialog() { if (!isOpen()) { return; } if (typeof dialog.close === 'function') { dialog.close(); } else { dialog.removeAttribute('open'); } }
  function openDialog(from) {
    opener = from || document.activeElement;
    var s = read();
    dialog.querySelectorAll('[data-consent-category]').forEach(function (cb) {
      cb.checked = !!(s && s.c && s.c[cb.getAttribute('data-consent-category')] === 1);
    });
    if (typeof dialog.showModal === 'function') { dialog.showModal(); } else { dialog.setAttribute('open', ''); }
  }
  dialog.addEventListener('close', function () { if (opener && document.contains(opener) && !opener.closest('[hidden]')) { opener.focus(); } });
  dialog.addEventListener('click', function (e) { if (e.target === dialog) { closeDialog(); } });   // backdrop click

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-consent-accept],[data-consent-reject],[data-consent-save],[data-consent-close],[data-cookie-settings]');
    if (!t) { return; }
    if (t.hasAttribute('data-consent-accept')) { save(all(true)); }
    else if (t.hasAttribute('data-consent-reject')) { save(all(false)); }
    else if (t.hasAttribute('data-consent-save')) {
      var c = {};
      dialog.querySelectorAll('[data-consent-category]').forEach(function (cb) { c[cb.getAttribute('data-consent-category')] = cb.checked; });
      save(c);
    } else if (t.hasAttribute('data-consent-close')) { closeDialog(); }
    else { e.preventDefault(); openDialog(t); }   // "Cookie settings" link/button (works as a normal link without JS)
  });

  window.PixeliteConsent = { allows: allows, refresh: activate, open: function () { openDialog(); }, onChange: function (fn) { listeners.push(fn); } };
  window.addEventListener('resize', pad);
  showBanner(!read());
  activate();
  if (location.hash === '#settings') { openDialog(document.querySelector('[data-cookie-settings]')); }
})();
