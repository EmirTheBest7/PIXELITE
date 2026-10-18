(function () {
  // Admin: one-click copy of the prompt text. The button stays hidden when the Clipboard API is unavailable.
  document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
    var src = document.getElementById(btn.getAttribute('data-copy-target'));
    var status = document.querySelector('[data-copy-status]');
    if (!src || !navigator.clipboard) { return; }
    btn.hidden = false;
    btn.addEventListener('click', function () {
      navigator.clipboard.writeText(src.textContent).then(function () {
        if (status) { status.textContent = 'Copied.'; setTimeout(function () { status.textContent = ''; }, 2500); }
      }, function () {
        if (status) { status.textContent = 'Copy failed – select the text manually.'; }
      });
    });
  });
})();
