(function () {
  // Design gallery previews. Posters are always visible; videos load only on an explicit or deliberate action,
  // at most one plays at a time, and they pause when scrolled out of view. No tracking, no third-party requests.
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var hoverCapable = window.matchMedia('(hover: hover) and (pointer: fine)');
  var current = null;

  function stop(card) {
    if (!card) { return; }
    var v = card.querySelector('video');
    var b = card.querySelector('[data-preview-toggle]');
    if (v) { v.pause(); }
    card.classList.remove('is-playing');
    if (b) {
      b.setAttribute('aria-pressed', 'false');
      b.querySelector('.tcard__play-label').textContent = b.getAttribute('data-label-play');
    }
    if (current === card) { current = null; }
  }

  function start(card) {
    var v = card.querySelector('video');
    var b = card.querySelector('[data-preview-toggle]');
    if (!v || card.classList.contains('is-failed')) { return; }
    if (current && current !== card) { stop(current); }
    if (!v.getAttribute('src')) { v.setAttribute('src', v.getAttribute('data-src')); }
    var p = v.play();
    if (p && p.catch) { p.catch(function () { fail(card); }); }
    card.classList.add('is-playing');
    current = card;
    if (b) {
      b.setAttribute('aria-pressed', 'true');
      b.querySelector('.tcard__play-label').textContent = b.getAttribute('data-label-pause');
    }
  }

  function fail(card) {
    stop(card);
    card.classList.add('is-failed');
    var b = card.querySelector('[data-preview-toggle]');
    if (b) {
      b.disabled = true;
      b.querySelector('.tcard__play-label').textContent = b.getAttribute('data-label-failed');
    }
  }

  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (en) { if (!en.isIntersecting && en.target === current) { stop(current); } });
  }, { threshold: 0.25 }) : null;

  document.querySelectorAll('[data-template-card]').forEach(function (card) {
    var media = card.querySelector('[data-preview-media]');
    var btn = card.querySelector('[data-preview-toggle]');
    var v = card.querySelector('video');
    if (!media || !btn || !v) { return; }
    btn.hidden = false;                      // without JS the poster + "View design" link remain
    v.addEventListener('error', function () { fail(card); });
    btn.addEventListener('click', function () { card.classList.contains('is-playing') ? stop(card) : start(card); });
    if (io) { io.observe(card); }
    // Deliberate pointer hover (not touch, not reduced-motion) previews; touch users tap the button.
    media.addEventListener('mouseenter', function () { if (hoverCapable.matches && !reduce.matches) { start(card); } });
    media.addEventListener('mouseleave', function () { if (hoverCapable.matches && card.classList.contains('is-playing')) { stop(card); } });
  });

  // Detail page: live preview frame. Desktop = the design laid out at 1440 px and scaled down to the available width;
  // Phone = a 390 px wide viewport. Without JS the frame simply fills the column (responsive layout of the design).
  var stage = document.querySelector('[data-stage]');
  if (stage) {
    var frame = stage.querySelector('iframe'), toggle = document.querySelector('[data-size-toggle]'), mode = 'desktop';
    var DIMS = { desktop: [1440, 900], phone: [390, 800] };
    var fit = function () {
      var d = DIMS[mode], avail = stage.parentNode.clientWidth, sc = Math.min(1, avail / d[0]);
      stage.classList.add('is-scaled');
      frame.style.width = d[0] + 'px'; frame.style.height = d[1] + 'px';
      frame.style.transform = 'translateX(' + (-d[0] * sc / 2) + 'px) scale(' + sc + ')';
      stage.style.height = Math.round(d[1] * sc) + 'px';
    };
    if (toggle) {
      toggle.hidden = false;
      toggle.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-size]'); if (!b) { return; }
        mode = b.getAttribute('data-size');
        [].forEach.call(toggle.querySelectorAll('button'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        fit();
      });
    }
    fit(); addEventListener('resize', fit);
  }
})();
