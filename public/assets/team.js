(function () {
  // Team page: tag filter. Without JavaScript (or with fewer than two tags) every member simply stays visible.
  var bar = document.querySelector('[data-team-filter]');
  var grid = document.querySelector('[data-team-grid]');
  if (!bar || !grid) { return; }
  bar.hidden = false;
  bar.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-tag]');
    if (!btn) { return; }
    var tag = btn.getAttribute('data-tag');
    bar.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
    grid.querySelectorAll('.team-card').forEach(function (li) {
      var tags = (li.getAttribute('data-tags') || '').split(' ');
      li.hidden = tag !== '' && tags.indexOf(tag) === -1;
    });
  });
})();
