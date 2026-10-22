(function() {
  const root = document.documentElement;
  let fallbackTimer = null;

  function cleanupMotion() {
    root.classList.remove('motion-pending');
    if (fallbackTimer) {
      clearTimeout(fallbackTimer);
      fallbackTimer = null;
    }
  }

  if (root.classList.contains('motion-pending')) {
    fallbackTimer = setTimeout(cleanupMotion, 3500);

    const demoCard = document.querySelector('.demo-card');
    if (demoCard) {
      demoCard.addEventListener('animationend', cleanupMotion, { once: true });
    }
  }

  const header = document.querySelector('.header');
  const menuToggle = document.querySelector('.menu-toggle');
  const tabletNav = document.getElementById('tablet-navigation');

  function updateInertState() {
    const isCollapsible = window.matchMedia('(max-width: 1100px)').matches;
    if (!tabletNav) return;
    if (isCollapsible) {
      if (header.classList.contains('menu-open')) {
        tabletNav.removeAttribute('inert');
      } else {
        tabletNav.setAttribute('inert', '');
      }
    } else {
      tabletNav.removeAttribute('inert');
    }
  }

  function toggleMenu(force) {
    if (!header || !menuToggle || !tabletNav) return;
    const open = force !== undefined ? force : !header.classList.contains('menu-open');
    header.classList.toggle('menu-open', open);
    menuToggle.setAttribute('aria-expanded', String(open));
    updateInertState();

    if (open) {
      const firstNavElement = tabletNav.querySelector('a, button');
      if (firstNavElement) firstNavElement.focus();
    } else {
      menuToggle.focus();
    }
  }

  if (menuToggle) {
    menuToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      toggleMenu();
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && header && header.classList.contains('menu-open')) {
      toggleMenu(false);
    }
  });

  document.addEventListener('pointerdown', function(e) {
    if (header && header.classList.contains('menu-open')) {
      if (!header.contains(e.target)) {
        toggleMenu(false);
      }
    }
  });

  if (tabletNav) {
    tabletNav.querySelectorAll('a').forEach(function(link) {
      link.addEventListener('click', function() {
        if (header && header.classList.contains('menu-open')) {
          toggleMenu(false);
        }
      });
    });
  }

  window.addEventListener('resize', updateInertState);
  updateInertState();
})();
    