(() => {
  'use strict';

  /* ------------------------------------------------------------------
     CONFIG: replace with the client's real data.
     ------------------------------------------------------------------ */
  const CONFIG = {
    booking:   '#kontakt',                       // online booking URL (Altegio, Reservio, ...)
    instagram: '',                               // e.g. https://instagram.com/azur.lash.studio
    whatsapp:  '',                               // e.g. https://wa.me/420000000000
    phone:     'tel:+420000000000',
    email:     'mailto:ahoj@azur.example',
    map:       '#',
    promoEnd:  ''                                // ISO date, e.g. '2026-12-31T23:59:59'. Empty = no countdown
  };

  const doc = document;
  const root = doc.documentElement;
  root.classList.add('js');
  const $ = (s, c = doc) => c.querySelector(s);
  const $$ = (s, c = doc) => [...c.querySelectorAll(s)];
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Links from CONFIG ---------- */
  const external = (el, href) => {
    el.setAttribute('href', href);
    if (/^https?:/.test(href)) { el.target = '_blank'; el.rel = 'noopener'; }
    else { el.removeAttribute('target'); el.removeAttribute('rel'); }
  };
  $$('[data-book]').forEach(a => external(a, CONFIG.booking));
  ['instagram', 'whatsapp', 'phone', 'email', 'map'].forEach(k => {
    if (CONFIG[k]) $$(`[data-link="${k}"]`).forEach(a => external(a, CONFIG[k]));
  });
  const y = $('#year'); if (y) y.textContent = new Date().getFullYear();

  /* ---------- Header state ---------- */
  const header = $('#site-header');
  const floatBtn = $('.float-book');
  const onScroll = () => {
    header.classList.toggle('is-scrolled', scrollY > 40);
    floatBtn.classList.toggle('is-hidden', scrollY < 500);
  };
  onScroll();
  addEventListener('scroll', onScroll, { passive: true });

  /* ---------- Mobile menu ---------- */
  const toggle = $('.menu-toggle');
  const menu = $('#mobile-menu');
  const focusables = () => [toggle, ...$$('a', menu)];
  const setMenu = open => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Zavřít menu' : 'Otevřít menu');
    menu.hidden = !open;
    doc.body.classList.toggle('no-scroll', open);
    header.classList.toggle('menu-open', open);
    if (open) $('a', menu).focus();
  };
  toggle.addEventListener('click', () => setMenu(menu.hidden));
  menu.addEventListener('click', e => { if (e.target.closest('a')) setMenu(false); });
  doc.addEventListener('keydown', e => {
    if (menu.hidden) return;
    if (e.key === 'Escape') { setMenu(false); toggle.focus(); return; }
    if (e.key !== 'Tab') return;
    const f = focusables(), first = f[0], last = f[f.length - 1];
    if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  matchMedia('(min-width: 960px)').addEventListener('change', e => { if (e.matches && !menu.hidden) setMenu(false); });

  /* ---------- Smooth anchors (focus management; CSS handles the scroll) ---------- */
  doc.addEventListener('click', e => {
    const a = e.target.closest('a[href^="#"]');
    if (!a || a.getAttribute('href') === '#') return;
    const t = $(a.getAttribute('href'));
    if (!t) return;
    e.preventDefault();
    t.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    try { history.replaceState(null, '', a.getAttribute('href')); } catch (_) { /* sandboxed preview frame */ }
    if (!t.hasAttribute('tabindex')) t.setAttribute('tabindex', '-1');
    t.focus({ preventScroll: true });
  });

  /* ---------- Reveal on scroll ---------- */
  const revealEls = $$('.reveal');
  if ('IntersectionObserver' in window && !reduceMotion) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
    }, { threshold: .12, rootMargin: '0px 0px -6% 0px' });
    revealEls.forEach((el, i) => {
      const sibs = el.parentElement.children;
      el.style.setProperty('--d', `${Math.min([...sibs].indexOf(el) % 3, 2) * .08}s`);
      io.observe(el);
    });
  } else revealEls.forEach(el => el.classList.add('is-in'));

  /* ---------- Sliders (reviews + studio) ---------- */
  $$('[data-slider]').forEach(slider => {
    const items = $$('.quote, .slide', slider);
    const dotsWrap = $('.dots', slider);
    let i = 0, timer;
    const dots = items.map((_, n) => {
      const d = doc.createElement('button');
      d.type = 'button'; d.className = 'dot'; d.setAttribute('role', 'tab');
      d.setAttribute('aria-label', `Zobrazit ${n + 1}`);
      d.addEventListener('click', () => { go(n); restart(); });
      dotsWrap.appendChild(d); return d;
    });
    const go = n => {
      i = (n + items.length) % items.length;
      items.forEach((el, k) => el.classList.toggle('is-active', k === i));
      dots.forEach((d, k) => { d.classList.toggle('is-active', k === i); d.setAttribute('aria-selected', k === i); });
    };
    const restart = () => { clearInterval(timer); if (!reduceMotion) timer = setInterval(() => go(i + 1), 7000); };
    $('[data-prev]', slider).addEventListener('click', () => { go(i - 1); restart(); });
    $('[data-next]', slider).addEventListener('click', () => { go(i + 1); restart(); });
    slider.addEventListener('mouseenter', () => clearInterval(timer));
    slider.addEventListener('mouseleave', restart);
    slider.addEventListener('keydown', e => {
      if (e.key === 'ArrowLeft') { go(i - 1); restart(); }
      if (e.key === 'ArrowRight') { go(i + 1); restart(); }
    });
    // Fix quote track height to the tallest quote so layout doesn't jump
    const track = $('.quote-track', slider);
    if (track) {
      const fit = () => {
        let h = 0;
        items.forEach(el => { const was = el.style.cssText; el.style.cssText = 'position:relative;visibility:hidden;opacity:0'; h = Math.max(h, el.offsetHeight); el.style.cssText = was; });
        track.style.minHeight = h + 'px';
      };
      fit(); addEventListener('resize', fit);
      doc.fonts && doc.fonts.ready.then(fit);
    }
    go(0); restart();
  });

  /* ---------- FAQ accordion ---------- */
  $$('[data-accordion] .faq-q').forEach(btn => {
    const panel = doc.getElementById(btn.getAttribute('aria-controls'));
    btn.addEventListener('click', () => {
      const open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!open));
      if (reduceMotion) { panel.hidden = open; return; }
      if (open) {
        panel.style.height = panel.scrollHeight + 'px';
        requestAnimationFrame(() => { panel.style.transition = 'height .4s cubic-bezier(.22,.8,.24,1)'; panel.style.height = '0px'; });
        panel.addEventListener('transitionend', () => { panel.hidden = true; panel.style.cssText = ''; }, { once: true });
      } else {
        panel.hidden = false; panel.style.height = '0px';
        requestAnimationFrame(() => { panel.style.transition = 'height .4s cubic-bezier(.22,.8,.24,1)'; panel.style.height = panel.scrollHeight + 'px'; });
        panel.addEventListener('transitionend', () => { panel.style.cssText = ''; }, { once: true });
      }
    });
  });

  /* ---------- Lightbox ---------- */
  const lb = $('#lightbox'), lbImg = $('#lb-img'), lbCap = $('#lb-cap');
  const tiles = $$('[data-lightbox]');
  let idx = 0, opener = null;
  const show = n => {
    idx = (n + tiles.length) % tiles.length;
    const t = tiles[idx], im = $('img', t);
    lbImg.src = t.dataset.full || im.src; lbImg.alt = im.alt; lbCap.textContent = t.dataset.lightbox;
  };
  const openLb = n => {
    opener = tiles[n]; show(n); lb.hidden = false; doc.body.classList.add('no-scroll'); $('.lb-close', lb).focus();
  };
  const closeLb = () => {
    lb.hidden = true; doc.body.classList.remove('no-scroll'); lbImg.src = ''; if (opener) opener.focus();
  };
  tiles.forEach((t, n) => t.addEventListener('click', () => openLb(n)));
  $$('[data-lb-close]', lb).forEach(el => el.addEventListener('click', closeLb));
  $('.lb-prev', lb).addEventListener('click', () => show(idx - 1));
  $('.lb-next', lb).addEventListener('click', () => show(idx + 1));
  doc.addEventListener('keydown', e => {
    if (lb.hidden) return;
    if (e.key === 'Escape') closeLb();
    else if (e.key === 'ArrowLeft') show(idx - 1);
    else if (e.key === 'ArrowRight') show(idx + 1);
    else if (e.key === 'Tab') {
      const f = $$('button', lb), first = f[0], last = f[f.length - 1];
      if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  let sx = 0;
  lb.addEventListener('touchstart', e => { sx = e.changedTouches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - sx;
    if (Math.abs(dx) > 50) show(idx + (dx < 0 ? 1 : -1));
  }, { passive: true });

  /* ---------- Promo countdown ---------- */
  const cd = $('#countdown');
  if (cd && CONFIG.promoEnd) {
    const end = new Date(CONFIG.promoEnd).getTime();
    const pad = n => String(n).padStart(2, '0');
    const tick = () => {
      const left = end - Date.now();
      if (!(left > 0)) { cd.hidden = true; return false; }
      const v = { d: Math.floor(left / 864e5), h: Math.floor(left / 36e5) % 24, m: Math.floor(left / 6e4) % 60, s: Math.floor(left / 1e3) % 60 };
      Object.entries(v).forEach(([k, n]) => { $(`[data-cd="${k}"]`, cd).textContent = pad(n); });
      return true;
    };
    if (tick()) { cd.hidden = false; const t = setInterval(() => { if (!tick()) clearInterval(t); }, 1000); }
  }
})();
