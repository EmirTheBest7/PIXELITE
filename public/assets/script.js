(function () {
  // Mobile navigation toggle (replaces Bootstrap's jQuery collapse; same .collapse / .in classes).
  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.getElementById('navbar');
  if (toggle && nav) {
    var set = function (open) {
      nav.classList.toggle('in', open);
      toggle.classList.toggle('collapsed', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    toggle.addEventListener('click', function () { set(!nav.classList.contains('in')); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('in')) { set(false); toggle.focus(); }
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a[href*="#"]')) { set(false); }
    });
  }

  // Remember an explicitly chosen language (used only to pick where bare "/" redirects).
  document.querySelectorAll('[data-lang]').forEach(function (a) {
    a.addEventListener('click', function () {
      document.cookie = 'pixelite_lang=' + a.getAttribute('data-lang') + '; path=/; max-age=31536000; SameSite=Lax' +
        (location.protocol === 'https:' ? '; Secure' : '');
    });
  });

  // Order form: focus the error summary after a failed submit; block double submits.
  var alertBox = document.querySelector('[data-form-alert]');
  if (alertBox) { alertBox.focus(); }
  var form = document.getElementById('order');
  if (form) {
    form.addEventListener('submit', function () {
      var b = form.querySelector('button[type="submit"]');
      if (b) { setTimeout(function () { b.disabled = true; }, 0); }
    });
  }

  // Order form: show the thumbnail of the chosen design and keep it in sync when the visitor changes the selection.
  var pick = document.querySelector('[data-template-pick]');
  if (pick) {
    var sel = pick.querySelector('select');
    var thumb = pick.querySelector('[data-template-thumb]');
    sel.addEventListener('change', function () {
      var src = sel.options[sel.selectedIndex].getAttribute('data-thumb');
      if (src) { thumb.src = src; thumb.hidden = false; } else { thumb.hidden = true; }
    });
  }
})();
