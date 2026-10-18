// Lumora – original template script; only the Lenis import and the hero image path were adapted to run inside Pixelite.
    import Lenis from './vendor/lenis.js';   // Lenis 1.3.23 (MIT) is vendored next to this file

    // 1) Adaptive Grid Runtime Scaler for screens > 1920px
    function applyAdaptiveGrid() {
      const FONT_BASE = 16, baseWidth = 1920, coef = 0.6666;
      const w = window.innerWidth;
      const widthReduction = ((baseWidth - w) / baseWidth) * 100;
      const size = FONT_BASE - (FONT_BASE * (widthReduction * coef)) / 100;
      if (size > FONT_BASE) {
        document.documentElement.style.fontSize = size + 'px';
      } else {
        document.documentElement.style.removeProperty('font-size');
      }
    }
    applyAdaptiveGrid();
    window.addEventListener('resize', applyAdaptiveGrid);

    // 2) Lenis Smooth Scrolling & Scroll Lock Model
    window.scrollTo(0, 0);
    const lenis = new Lenis({ smoothWheel: true });
    function raf(t) {
      lenis.raf(t);
      requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);

    let scrollLocks = 0;
    function stopScroll() {
      scrollLocks++;
      if (scrollLocks === 1) {
        lenis.stop();
        document.documentElement.style.position = 'relative';
        document.documentElement.style.overflow = 'hidden';
        document.documentElement.style.height = '100%';
      }
    }

    function startScroll() {
      scrollLocks = Math.max(0, scrollLocks - 1);
      if (scrollLocks === 0) {
        lenis.start();
        document.documentElement.style.removeProperty('position');
        document.documentElement.style.removeProperty('overflow');
        document.documentElement.style.removeProperty('height');
      }
    }

    // ScrollTo ID helper
    function scrollToId(id) {
      const target = document.getElementById(id);
      if (!target) return;
      stopScroll();
      setTimeout(() => {
        const top = target.getBoundingClientRect().top + window.pageYOffset;
        window.scrollTo({ top, behavior: 'smooth' });
      }, 50);
      setTimeout(() => {
        startScroll();
      }, 100);
    }

    // 3) Global ready state gating hero reveals
    let ready = false;

    // 4) PageLoader logic
    stopScroll(); // lock scroll on mount
    const pageLoader = document.getElementById('page-loader');
    const loaderFill = document.getElementById('loader-fill');
    const loaderCounter = document.getElementById('loader-counter');
    const loaderCenter = document.getElementById('loader-center');

    const FILL_MS = 1300;
    const startTime = performance.now();

    function easeInOutCubic(t) {
      return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
    }

    function stepLoader(now) {
      const elapsed = now - startTime;
      const rawT = Math.min(elapsed / FILL_MS, 1);
      const easedT = easeInOutCubic(rawT);
      const progress = Math.min(100, Math.round(easedT * 100));

      loaderFill.style.width = progress + '%';
      loaderCounter.textContent = String(progress).padStart(3, '0');
      pageLoader.setAttribute('aria-valuenow', progress);

      if (rawT < 1) {
        requestAnimationFrame(stepLoader);
      } else {
        // Count complete -> exit animation
        setTimeout(exitLoader, 80);
      }
    }
    requestAnimationFrame(stepLoader);

    function exitLoader() {
      loaderCenter.style.opacity = '0';
      loaderCenter.style.transform = 'translateY(-12px)';
      pageLoader.style.transform = 'translateY(-100%)';

      setTimeout(() => {
        ready = true;
        startScroll();
        pageLoader.remove();
        triggerHeroReveals();
      }, 700);
    }

    // 5) Hero Entrance Reveals (Gated on `ready`)
    function triggerHeroReveals() {
      const header = document.getElementById('site-header');
      const eyebrow = document.getElementById('hero-eyebrow');
      const h1Lines = document.querySelectorAll('#hero-h1 .reveal-line-inner');
      const watermark = document.getElementById('hero-watermark');
      const card = document.getElementById('hero-card');
      const partners = document.getElementById('hero-partners');
      const rating = document.getElementById('hero-rating');
      const ctas = document.getElementById('hero-ctas');
      const statusBar = document.getElementById('hero-status-bar');

      setTimeout(() => header?.classList.add('is-revealed'), 150);
      setTimeout(() => eyebrow?.classList.add('is-revealed'), 200);

      // Hero H1 staggered lines
      setTimeout(() => {
        h1Lines.forEach((line, idx) => {
          setTimeout(() => line.classList.add('is-revealed'), idx * 120);
        });
      }, 250);

      setTimeout(() => watermark?.classList.add('is-revealed'), 300);
      setTimeout(() => card?.classList.add('is-revealed'), 400);
      setTimeout(() => partners?.classList.add('is-revealed'), 550);
      setTimeout(() => rating?.classList.add('is-revealed'), 650);
      setTimeout(() => ctas?.classList.add('is-revealed'), 750);
      setTimeout(() => statusBar?.classList.add('is-revealed'), 900);
    }

    // 6) Live Clock
    const clockTimeEl = document.getElementById('clock-time');
    const clockDateEl = document.getElementById('clock-date');
    const navMenuTimeEl = document.getElementById('nav-menu-local-time');

    const monthNames = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];

    function updateLiveClock() {
      const now = new Date();
      const rawHours = now.getHours();
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const meridiem = rawHours >= 12 ? 'pm' : 'am';
      const hours = rawHours % 12 || 12;
      const formattedTime = `${hours}:${minutes}${meridiem}`;

      const day = now.getDate();
      const month = monthNames[now.getMonth()];
      const year = now.getFullYear();
      const formattedDate = `${day} ${month}, ${year}`;

      if (clockTimeEl) clockTimeEl.textContent = formattedTime;
      if (clockDateEl) clockDateEl.textContent = formattedDate;
      if (navMenuTimeEl) navMenuTimeEl.textContent = `Local time — ${formattedTime}`;
    }
    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    // 7) LiquidReveal Cursor-Paint Effect
    const liquidWrap = document.getElementById('liquid-wrap');
    const liquidCanvas = document.getElementById('liquid-canvas');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!prefersReducedMotion && liquidCanvas && liquidWrap) {
      const mainCtx = liquidCanvas.getContext('2d');
      const coverCanvas = document.createElement('canvas');
      const coverCtx = coverCanvas.getContext('2d');
      const brushCanvas = document.createElement('canvas');
      const brushCtx = brushCanvas.getContext('2d');

      const brushRadius = 143;
      const decay = 0.016;
      let dpr = Math.min(window.devicePixelRatio || 1, 2);
      let radius = brushRadius * dpr;
      let diameter = Math.ceil(radius * 2);

      let points = [];
      let lastPoint = null;
      let idle = 0;
      let afterImgLoaded = false;

      const afterImg = new Image();
      afterImg.crossOrigin = 'anonymous';
      afterImg.src = '/assets/templates/lumora/img/before.jpg';

      function updateCanvasSizing() {
        const rect = liquidWrap.getBoundingClientRect();
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        radius = brushRadius * dpr;
        diameter = Math.ceil(radius * 2);

        liquidCanvas.width = Math.round(rect.width * dpr);
        liquidCanvas.height = Math.round(rect.height * dpr);
        coverCanvas.width = liquidCanvas.width;
        coverCanvas.height = liquidCanvas.height;

        brushCanvas.width = diameter;
        brushCanvas.height = diameter;

        drawCoverImage();
      }

      function drawCoverImage() {
        if (!afterImgLoaded || !coverCanvas.width || !coverCanvas.height) return;
        const imgAspect = afterImg.naturalWidth / afterImg.naturalHeight;
        const canvasAspect = coverCanvas.width / coverCanvas.height;
        let drawW, drawH, drawX, drawY;

        if (canvasAspect > imgAspect) {
          drawW = coverCanvas.width;
          drawH = coverCanvas.width / imgAspect;
          drawX = 0;
          drawY = (coverCanvas.height - drawH) / 2;
        } else {
          drawH = coverCanvas.height;
          drawW = coverCanvas.height * imgAspect;
          drawX = (coverCanvas.width - drawW) / 2;
          drawY = 0;
        }

        coverCtx.clearRect(0, 0, coverCanvas.width, coverCanvas.height);
        coverCtx.drawImage(afterImg, drawX, drawY, drawW, drawH);
      }

      afterImg.onload = () => {
        afterImgLoaded = true;
        drawCoverImage();
      };

      const resizeObserver = new ResizeObserver(() => {
        updateCanvasSizing();
      });
      resizeObserver.observe(liquidWrap);
      updateCanvasSizing();

      window.addEventListener('pointermove', (e) => {
        const rect = liquidWrap.getBoundingClientRect();
        const x = (e.clientX - rect.left) * dpr;
        const y = (e.clientY - rect.top) * dpr;

        if (x < -radius || x > liquidCanvas.width + radius || y < -radius || y > liquidCanvas.height + radius) {
          lastPoint = null;
          return;
        }

        if (!lastPoint) {
          lastPoint = { x, y };
          points.push({ x, y });
          return;
        }

        const dist = Math.hypot(x - lastPoint.x, y - lastPoint.y);
        const step = Math.max(radius * 0.3, 1);
        const n = Math.min(Math.ceil(dist / step), 60);

        for (let i = 1; i <= n; i++) {
          points.push({
            x: lastPoint.x + (x - lastPoint.x) * (i / n),
            y: lastPoint.y + (y - lastPoint.y) * (i / n)
          });
        }
        lastPoint = { x, y };
      }, { passive: true });

      window.addEventListener('pointerleave', () => {
        lastPoint = null;
      });

      function stamp(x, y) {
        brushCtx.clearRect(0, 0, diameter, diameter);
        brushCtx.globalCompositeOperation = 'source-over';

        const grad = brushCtx.createRadialGradient(radius, radius, 0, radius, radius, radius);
        grad.addColorStop(0, 'rgba(255,255,255,1)');
        grad.addColorStop(0.55, 'rgba(255,255,255,0.82)');
        grad.addColorStop(1, 'rgba(255,255,255,0)');
        brushCtx.fillStyle = grad;
        brushCtx.fillRect(0, 0, diameter, diameter);

        brushCtx.globalCompositeOperation = 'source-in';
        brushCtx.drawImage(
          coverCanvas,
          x - radius, y - radius, diameter, diameter,
          0, 0, diameter, diameter
        );

        mainCtx.globalCompositeOperation = 'source-over';
        mainCtx.drawImage(brushCanvas, x - radius, y - radius);
      }

      function renderLiquid() {
        requestAnimationFrame(renderLiquid);
        const drawing = points.length > 0;
        if (drawing) {
          idle = 0;
        } else {
          idle++;
          if (idle > 120) return;
        }

        const fade = drawing ? decay : Math.min(decay + idle * 0.004, 0.5);
        mainCtx.globalCompositeOperation = 'destination-out';
        mainCtx.fillStyle = `rgba(0,0,0,${fade})`;
        mainCtx.fillRect(0, 0, liquidCanvas.width, liquidCanvas.height);

        if (drawing && afterImgLoaded) {
          for (let i = 0; i < points.length; i++) {
            stamp(points[i].x, points[i].y);
          }
          points.length = 0;
        }

        if (idle === 120) {
          mainCtx.clearRect(0, 0, liquidCanvas.width, liquidCanvas.height);
        }
      }
      requestAnimationFrame(renderLiquid);
    }

    // 8) HeroCard Carousel Logic
    const heroSlides = document.querySelectorAll('.hero-card-slide');
    const heroDashes = document.querySelectorAll('.hero-card-dash');
    const heroCardPrevBtn = document.getElementById('hero-card-prev');
    const heroCardNextBtn = document.getElementById('hero-card-next');
    const heroCardClickArea = document.getElementById('hero-card-click-area');
    let activeCardIndex = 0;
    const totalCards = heroSlides.length;

    function setCardIndex(newIndex, direction = 1) {
      if (newIndex === activeCardIndex) return;
      const prevIdx = activeCardIndex;
      activeCardIndex = (newIndex + totalCards) % totalCards;

      heroSlides.forEach((slide, idx) => {
        if (idx === activeCardIndex) {
          slide.classList.remove('is-prev', 'is-next');
          slide.classList.add('is-active');
        } else if (idx === prevIdx) {
          slide.classList.remove('is-active');
          if (direction > 0) {
            slide.classList.add('is-prev');
          } else {
            slide.classList.add('is-next');
          }
        } else {
          slide.classList.remove('is-active');
          slide.classList.add(direction > 0 ? 'is-next' : 'is-prev');
        }
      });

      heroDashes.forEach((dash, idx) => {
        if (idx === activeCardIndex) {
          dash.classList.remove('inactive');
          dash.classList.add('active');
        } else {
          dash.classList.remove('active');
          dash.classList.add('inactive');
        }
      });
    }

    heroCardNextBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      setCardIndex(activeCardIndex + 1, 1);
    });

    heroCardPrevBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      setCardIndex(activeCardIndex - 1, -1);
    });

    heroCardClickArea?.addEventListener('click', (e) => {
      if (e.target.closest('.hero-card-arrow-btn')) return;
      setCardIndex(activeCardIndex + 1, 1);
    });

    // 9) Word Reveal for About Statement H2
    const aboutH2 = document.getElementById('about-statement-h2');
    if (aboutH2) {
      const part1 = "We partner with ambitious teams to ship ";
      const part2 = "digital products, brand systems, and the strategy that holds them together.";
      aboutH2.innerHTML = '';

      const words1 = part1.trim().split(/\s+/);
      const words2 = part2.trim().split(/\s+/);
      let wordIdx = 0;

      words1.forEach((word) => {
        const span = document.createElement('span');
        span.className = 'reveal-word';
        span.textContent = word + '\u00A0';
        span.style.transitionDelay = `${wordIdx * 35}ms`;
        aboutH2.appendChild(span);
        wordIdx++;
      });

      words2.forEach((word) => {
        const span = document.createElement('span');
        span.className = 'reveal-word muted-part';
        span.textContent = word + '\u00A0';
        span.style.transitionDelay = `${wordIdx * 35}ms`;
        aboutH2.appendChild(span);
        wordIdx++;
      });
    }

    // 10) IntersectionObserver for Scroll-driven Entrance Reveals
    const scrollRevealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const el = entry.target;
          const delayStr = el.style.getPropertyValue('--delay') || '0ms';
          const delayMs = parseInt(delayStr, 10) || 0;

          setTimeout(() => {
            el.classList.add('is-revealed');
            // If it contains line-reveal-target lines:
            const innerLines = el.querySelectorAll('.reveal-line-inner');
            innerLines.forEach((line, lIdx) => {
              line.classList.add('is-revealed');
            });
            // If it's the about statement, trigger word reveal
            const words = el.querySelectorAll('.reveal-word');
            words.forEach(w => w.classList.add('is-revealed'));
          }, delayMs);

          scrollRevealObserver.unobserve(el);
        }
      });
    }, {
      threshold: 0.15,
      rootMargin: '0px 0px -50px 0px'
    });

    document.querySelectorAll('.reveal-on-scroll, .line-reveal-target, #about-statement-h2').forEach((el) => {
      scrollRevealObserver.observe(el);
    });

    // 11) Stats Scroll-Progress Count-Up
    const statCounters = document.querySelectorAll('.stat-counter');
    let lastStatsTick = 0;

    function handleStatsCountUp() {
      const now = performance.now();
      if (now - lastStatsTick < 30) return;
      lastStatsTick = now;

      const vh = window.innerHeight;
      statCounters.forEach((counter) => {
        const rect = counter.getBoundingClientRect();
        const target = parseFloat(counter.getAttribute('data-target')) || 0;

        // start: top of element reaches bottom of viewport (rect.top == vh)
        // end: center of element reaches center of viewport (rect.top + rect.height/2 == vh/2)
        const startY = vh;
        const endY = vh / 2;
        const currentY = rect.top + rect.height / 2;

        let progress = (startY - currentY) / (startY - endY);
        progress = Math.max(0, Math.min(1, progress));

        const val = Math.round(progress * target);
        counter.textContent = String(val);
      });
    }
    window.addEventListener('scroll', handleStatsCountUp, { passive: true });
    handleStatsCountUp();

    // 12) Full-Screen NavMenu Overlay Controls
    const navOverlay = document.getElementById('nav-menu-overlay');
    const navOpenBtn = document.getElementById('menu-open-btn');
    const navCloseBtn = document.getElementById('nav-menu-close-btn');

    function openNavMenu() {
      navOverlay.classList.add('is-open');
      navOverlay.setAttribute('aria-hidden', 'false');
      stopScroll();
    }

    function closeNavMenu() {
      navOverlay.classList.remove('is-open');
      navOverlay.setAttribute('aria-hidden', 'true');
      startScroll();
    }

    navOpenBtn?.addEventListener('click', openNavMenu);
    navCloseBtn?.addEventListener('click', closeNavMenu);

    document.querySelectorAll('.nav-menu-link-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        closeNavMenu();
        const target = btn.getAttribute('data-target');
        if (target) {
          setTimeout(() => scrollToId(target), 200);
        }
      });
    });

    // 13) Request Modal Dialog Controls
    const modalOverlay = document.getElementById('request-modal-overlay');
    const modalCard = document.getElementById('modal-card');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalFormState = document.getElementById('modal-form-state');
    const modalSuccessState = document.getElementById('modal-success-state');
    const modalSuccessCloseBtn = document.getElementById('modal-success-close-btn');
    const requestForm = document.getElementById('request-form');
    const submitBtnLabel = document.getElementById('submit-btn-label');

    function openRequestModal() {
      if (navOverlay.classList.contains('is-open')) {
        closeNavMenu();
      }
      modalOverlay.classList.add('is-open');
      modalOverlay.setAttribute('aria-hidden', 'false');
      stopScroll();
    }

    function closeRequestModal() {
      modalOverlay.classList.remove('is-open');
      modalOverlay.setAttribute('aria-hidden', 'true');
      startScroll();

      // Reset form after exit transition
      setTimeout(() => {
        if (requestForm) requestForm.reset();
        modalFormState.style.display = 'block';
        modalSuccessState.style.display = 'none';
        if (submitBtnLabel) submitBtnLabel.textContent = 'Send request';
      }, 350);
    }

    document.querySelectorAll('.open-modal-trigger').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        openRequestModal();
      });
    });

    modalCloseBtn?.addEventListener('click', closeRequestModal);
    modalSuccessCloseBtn?.addEventListener('click', closeRequestModal);

    modalOverlay?.addEventListener('click', (e) => {
      if (e.target === modalOverlay) {
        closeRequestModal();
      }
    });

    modalCard?.addEventListener('click', (e) => {
      e.stopPropagation();
    });

    // Escape key closes modals/overlays
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (modalOverlay.classList.contains('is-open')) {
          closeRequestModal();
        } else if (navOverlay.classList.contains('is-open')) {
          closeNavMenu();
        }
      }
    });

    // Form Submission (Stubbed Submit)
    requestForm?.addEventListener('submit', (e) => {
      e.preventDefault();
      if (submitBtnLabel) submitBtnLabel.textContent = 'Sending…';

      setTimeout(() => {
        modalFormState.style.display = 'none';
        modalSuccessState.style.display = 'flex';
      }, 500);
    });

    // 14) Click navigation routing helper
    document.querySelectorAll('[data-target]').forEach((el) => {
      el.addEventListener('click', (e) => {
        const targetId = el.getAttribute('data-target');
        if (targetId && !el.classList.contains('open-modal-trigger')) {
          e.preventDefault();
          scrollToId(targetId);
        }
      });
    });

    document.getElementById('header-brand-btn')?.addEventListener('click', () => {
      scrollToId('home');
    });
