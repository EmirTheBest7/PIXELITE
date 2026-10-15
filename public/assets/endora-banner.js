(function () {
  'use strict';

  /*
   * Endora injects its branding outside Pixelite's own templates. When its link is present,
   * mark only the small external wrapper so the matching rule in style.css can hide it.
   * Never hide a wrapper that contains Pixelite navigation, page content, forms, or footer.
   */
  var hiddenAttr = 'data-pixelite-endora-banner-hidden';
  var ownContent = '#main, #navbar, [data-nav-toggle], .header__logo, .footer__logo, form, [role="main"]';

  function isEndoraUrl(value) {
    try {
      var host = new URL(value, window.location.href).hostname.toLowerCase();
      return host === 'endora.cz' || host.endsWith('.endora.cz') ||
        host === 'endora.sk' || host.endsWith('.endora.sk');
    } catch (_) {
      return false;
    }
  }

  function isEndoraElement(el) {
    if (el.matches('iframe[src]') && isEndoraUrl(el.getAttribute('src') || '')) return true;
    return !!(el.matches('a[href]') && isEndoraUrl(el.getAttribute('href') || ''));
  }

  function isSafeBannerWrapper(el) {
    if (!el || el === document.body || el === document.documentElement ||
        el === document.head || el.hasAttribute(hiddenAttr)) return false;
    if (el.matches('html, body, head, main, #main, #navbar, [data-nav-toggle], form')) return false;
    if (el.matches('.header, .footer, .header__logo, .footer__logo, [role="main"]')) return false;
    if (el.querySelector(ownContent)) return false;

    var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
    if (text.length > 1500) return false;
    if (el.querySelectorAll('*').length > 60) return false;

    // Do not hide any ancestor that contains links back into Pixelite.
    var links = el.querySelectorAll('a[href]');
    for (var i = 0; i < links.length; i++) {
      var raw = (links[i].getAttribute('href') || '').trim();
      if (!raw || raw.charAt(0) === '#' || /^javascript:/i.test(raw)) continue;
      try {
        var u = new URL(raw, window.location.href);
        if (u.origin === window.location.origin) return false;
      } catch (_) {
        return false;
      }
    }
    return true;
  }

  function scan() {
    var injected = document.querySelectorAll('a[href], iframe[src]');
    injected.forEach(function (source) {
      if (!isEndoraElement(source)) return;

      // If the advertisement itself is an Endora-hosted iframe, hide only that iframe.
      if (source.tagName === 'IFRAME') {
        source.setAttribute(hiddenAttr, '');
        return;
      }

      var node = source.parentElement;
      var best = null;
      // Walk outward only while the wrapper looks like a standalone ad, not Pixelite UI.
      for (var depth = 0; node && node !== document.body && depth < 8; depth++) {
        if (!isSafeBannerWrapper(node)) break;
        best = node;
        node = node.parentElement;
      }
      if (best) best.setAttribute(hiddenAttr, '');
    });
  }

  scan();

  // Some hosters add the bar after the initial HTML has been parsed.
  if (document.documentElement && window.MutationObserver) {
    var observer = new MutationObserver(function () { scan(); });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }
})();