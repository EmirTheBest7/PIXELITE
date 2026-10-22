(function() {
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('motion-pending');
  } else {
    document.documentElement.classList.add('reduced-motion');
  }
})();
    