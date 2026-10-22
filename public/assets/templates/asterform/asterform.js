/**
 * ASTERFORM — Creative Strategy & Design Studio
 * Core Client Controller (script.js)
 * Native HTML5 / CSS3 / Vanilla JavaScript. No libraries or external frameworks.
 */

// ==========================================================================
// 1. CONFIGURATION & EDITABLE ASSET CONSTANTS
// ==========================================================================

const HERO_VIDEO_SRC =
  '/assets/templates/asterform/video/hero.mp4';

const FEATURED_VIDEO_SRC =
  '/assets/templates/asterform/video/approach.mp4';

const PHILOSOPHY_VIDEO_SRC =
  '/assets/templates/asterform/video/philosophy.mp4';

const STRATEGY_VIDEO_SRC =
  '/assets/templates/asterform/video/strategy.mp4';

const DESIGN_VIDEO_SRC =
  '/assets/templates/asterform/video/design.mp4';

const SOCIAL_LINKS = {
  instagram: '#',
  x: '#',
  website: '#about'
};

const CONTACT_EMAIL = 'hello@asterform.example';

// ==========================================================================
// 2. HERO FULL-VIEWPORT VIDEO ENGINE (with requestAnimationFrame fading)
// ==========================================================================

class HeroVideoController {
  constructor(videoElement) {
    this.video = videoElement;
    this.currentAnimFrame = null;
    this.isFading = false;
    this.isFadingOutAtEnd = false;
    this.hasEndedHandled = false;

    if (!this.video) return;

    this.init();
  }

  init() {
    this.video.src = HERO_VIDEO_SRC;
    this.video.style.opacity = '0';

    this.handleCanPlay = this.handleCanPlay.bind(this);
    this.handleTimeUpdate = this.handleTimeUpdate.bind(this);
    this.handleEnded = this.handleEnded.bind(this);
    this.handleError = this.handleError.bind(this);

    this.video.addEventListener('canplay', this.handleCanPlay, { once: true });
    this.video.addEventListener('timeupdate', this.handleTimeUpdate);
    this.video.addEventListener('ended', this.handleEnded);
    this.video.addEventListener('error', this.handleError);

    // Initial play attempt
    const playPromise = this.video.play();
    if (playPromise !== undefined) {
      playPromise
        .then(() => {
          this.fadeTo(1, 500);
        })
        .catch(() => {
          // Autoplay blocked or restricted by browser policy; fallback background displays smoothly
        });
    }
  }

  handleCanPlay() {
    const playPromise = this.video.play();
    if (playPromise !== undefined) {
      playPromise
        .then(() => {
          this.fadeTo(1, 500);
        })
        .catch(() => {});
    }
  }

  handleTimeUpdate() {
    if (!this.video.duration || this.isFadingOutAtEnd || this.hasEndedHandled) return;

    const remaining = this.video.duration - this.video.currentTime;
    // When 0.55 seconds or less remaining, fade from current opacity to zero over ~500ms
    if (remaining <= 0.55 && remaining > 0) {
      this.isFadingOutAtEnd = true;
      this.fadeTo(0, 500);
    }
  }

  handleEnded() {
    if (this.hasEndedHandled) return;
    this.hasEndedHandled = true;

    if (this.currentAnimFrame) {
      cancelAnimationFrame(this.currentAnimFrame);
    }

    this.video.style.opacity = '0';

    // Wait approximately 100ms, reset currentTime, restart playback, and fade back to opacity 1
    setTimeout(() => {
      try {
        this.video.currentTime = 0;
        this.isFadingOutAtEnd = false;
        this.hasEndedHandled = false;

        const replayPromise = this.video.play();
        if (replayPromise !== undefined) {
          replayPromise
            .then(() => {
              this.fadeTo(1, 500);
            })
            .catch(() => {});
        }
      } catch (err) {
        this.hasEndedHandled = false;
      }
    }, 100);
  }

  handleError() {
    // Graceful fallback: set opacity 0 so CSS animated gradient mesh remains visible
    this.video.style.opacity = '0';
  }

  fadeTo(targetOpacity, durationMs = 500) {
    if (this.currentAnimFrame) {
      cancelAnimationFrame(this.currentAnimFrame);
    }

    const startOpacity = parseFloat(this.video.style.opacity) || 0;
    const opacityDelta = targetOpacity - startOpacity;
    const startTime = performance.now();

    const step = (now) => {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / durationMs, 1);
      // Linear or smooth ease
      const easeVal = progress * (2 - progress);
      const currentVal = startOpacity + opacityDelta * easeVal;
      this.video.style.opacity = currentVal.toFixed(3);

      if (progress < 1) {
        this.currentAnimFrame = requestAnimationFrame(step);
      } else {
        this.video.style.opacity = targetOpacity.toString();
        this.currentAnimFrame = null;
      }
    };

    this.currentAnimFrame = requestAnimationFrame(step);
  }

  destroy() {
    if (this.currentAnimFrame) {
      cancelAnimationFrame(this.currentAnimFrame);
    }
    if (this.video) {
      this.video.removeEventListener('canplay', this.handleCanPlay);
      this.video.removeEventListener('timeupdate', this.handleTimeUpdate);
      this.video.removeEventListener('ended', this.handleEnded);
      this.video.removeEventListener('error', this.handleError);
    }
  }
}

// ==========================================================================
// 3. SECONDARY VIDEOS INITIALIZER
// ==========================================================================

function initSecondaryVideos() {
  const featuredVideo = document.getElementById('featuredVideo');
  if (featuredVideo) {
    featuredVideo.src = FEATURED_VIDEO_SRC;
    const p = featuredVideo.play();
    if (p) p.catch(() => {});
  }

  const philosophyVideo = document.getElementById('philosophyVideo');
  if (philosophyVideo) {
    philosophyVideo.src = PHILOSOPHY_VIDEO_SRC;
    const p = philosophyVideo.play();
    if (p) p.catch(() => {});
  }

  const strategyVideo = document.getElementById('strategyVideo');
  if (strategyVideo) {
    strategyVideo.src = STRATEGY_VIDEO_SRC;
    const p = strategyVideo.play();
    if (p) p.catch(() => {});
  }

  const designVideo = document.getElementById('designVideo');
  if (designVideo) {
    designVideo.src = DESIGN_VIDEO_SRC;
    const p = designVideo.play();
    if (p) p.catch(() => {});
  }
}

// ==========================================================================
// 4. MOBILE NAVIGATION CONTROLLER
// ==========================================================================

function initMobileNavigation() {
  const menuToggle = document.getElementById('menuToggle');
  const mobileNavPanel = document.getElementById('mobileNavPanel');

  if (!menuToggle || !mobileNavPanel) return;

  const toggleMenu = (open) => {
    const shouldOpen = open !== undefined ? open : !mobileNavPanel.classList.contains('is-open');
    if (shouldOpen) {
      mobileNavPanel.classList.add('is-open');
      menuToggle.setAttribute('aria-expanded', 'true');
      menuToggle.textContent = 'Close';
    } else {
      mobileNavPanel.classList.remove('is-open');
      menuToggle.setAttribute('aria-expanded', 'false');
      menuToggle.textContent = 'Menu';
    }
  };

  menuToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMenu();
  });

  // Close when clicking outside
  document.addEventListener('click', (e) => {
    if (
      mobileNavPanel.classList.contains('is-open') &&
      !mobileNavPanel.contains(e.target) &&
      !menuToggle.contains(e.target)
    ) {
      toggleMenu(false);
    }
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileNavPanel.classList.contains('is-open')) {
      toggleMenu(false);
      menuToggle.focus();
    }
  });

  // Close after selecting a link
  const mobileLinks = mobileNavPanel.querySelectorAll('a, button');
  mobileLinks.forEach((link) => {
    link.addEventListener('click', () => {
      toggleMenu(false);
    });
  });
}

// ==========================================================================
// 5. INTERSECTION OBSERVER SCROLL REVEALS
// ==========================================================================

function initScrollReveals() {
  const revealElements = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');

  // Check reduced motion preference
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReduced) {
    revealElements.forEach((el) => el.classList.add('is-visible'));
    return;
  }

  if (!('IntersectionObserver' in window)) {
    revealElements.forEach((el) => el.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    },
    {
      root: null,
      rootMargin: '0px 0px -60px 0px',
      threshold: 0.1
    }
  );

  revealElements.forEach((el) => observer.observe(el));
}

// ==========================================================================
// 6. NEWSLETTER SUBSCRIPTION FORM (Local Validation & Accessible Feedback)
// ==========================================================================

function initNewsletterForm() {
  const form = document.getElementById('newsletterForm');
  const emailInput = document.getElementById('newsletterEmail');
  const feedback = document.getElementById('formFeedback');

  if (!form || !emailInput || !feedback) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const value = emailInput.value.trim();

    // Native pattern email check
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!value || !emailRegex.test(value)) {
      feedback.textContent = 'Please enter a valid email address.';
      feedback.style.color = '#FFAA6E';
      emailInput.focus();
      return;
    }

    // Success response
    feedback.textContent = 'Thanks. The newsletter form is ready to connect to your mailing service.';
    feedback.style.color = 'var(--accent-blue)';
    emailInput.value = '';

    // Clear confirmation after 8 seconds
    setTimeout(() => {
      if (feedback.textContent.includes('ready to connect')) {
        feedback.textContent = '';
      }
    }, 8000);
  });
}

// ==========================================================================
// 7. SERVICE DETAIL ACCESSIBLE DIALOGS
// ==========================================================================

function initServiceDialogs() {
  const strategyBtn = document.getElementById('openStrategyDialogBtn');
  const designBtn = document.getElementById('openDesignDialogBtn');
  const strategyDialog = document.getElementById('strategyDialog');
  const designDialog = document.getElementById('designDialog');

  let lastActiveTrigger = null;

  const openDialog = (dialog, triggerBtn) => {
    if (!dialog) return;
    lastActiveTrigger = triggerBtn;
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      dialog.setAttribute('open', '');
    }
    const closeBtn = dialog.querySelector('.dialog-close-btn');
    if (closeBtn) closeBtn.focus();
  };

  const closeDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.close === 'function') {
      dialog.close();
    } else {
      dialog.removeAttribute('open');
    }
    if (lastActiveTrigger) {
      lastActiveTrigger.focus();
    }
  };

  if (strategyBtn && strategyDialog) {
    strategyBtn.addEventListener('click', () => openDialog(strategyDialog, strategyBtn));
  }

  if (designBtn && designDialog) {
    designBtn.addEventListener('click', () => openDialog(designDialog, designBtn));
  }

  const allDialogs = [strategyDialog, designDialog].filter(Boolean);
  allDialogs.forEach((dialog) => {
    // Backdrop click handling
    dialog.addEventListener('click', (e) => {
      const rect = dialog.getBoundingClientRect();
      const isInDialog =
        rect.top <= e.clientY &&
        e.clientY <= rect.top + rect.height &&
        rect.left <= e.clientX &&
        e.clientX <= rect.left + rect.width;

      if (!isInDialog) {
        closeDialog(dialog);
      }
    });

    // Close button click
    const closeButtons = dialog.querySelectorAll('.dialog-close-btn, .dialog-secondary-btn');
    closeButtons.forEach((btn) => {
      btn.addEventListener('click', () => closeDialog(dialog));
    });

    // Keyboard Escape listener
    dialog.addEventListener('cancel', () => {
      if (lastActiveTrigger) {
        setTimeout(() => lastActiveTrigger.focus(), 50);
      }
    });
  });
}

// ==========================================================================
// 8. INTERACTIVE ACTION ROUTING & ACCESSIBLE LINK SHORTCUTS
// ==========================================================================

function initActions() {
  // Join the list buttons focus the newsletter email input
  const joinBtns = document.querySelectorAll('[data-action="join-list"]');
  const emailInput = document.getElementById('newsletterEmail');

  joinBtns.forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (emailInput) {
        emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => emailInput.focus(), 600);
      }
    });
  });

  // Philosophy buttons smooth scroll to philosophy section
  const explorePhilosophyBtns = document.querySelectorAll('[data-action="explore-philosophy"]');
  const philosophySection = document.getElementById('philosophy');

  explorePhilosophyBtns.forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (philosophySection) {
        philosophySection.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  // Social link wiring
  const igLink = document.getElementById('socialInstagram');
  if (igLink && SOCIAL_LINKS.instagram) {
    igLink.href = SOCIAL_LINKS.instagram;
  }
  const xLink = document.getElementById('socialX');
  if (xLink && SOCIAL_LINKS.x) {
    xLink.href = SOCIAL_LINKS.x;
  }
  const webLink = document.getElementById('socialWebsite');
  if (webLink && SOCIAL_LINKS.website) {
    webLink.href = SOCIAL_LINKS.website;
  }

  // Back to top button
  const backToTopBtn = document.getElementById('backToTopBtn');
  if (backToTopBtn) {
    backToTopBtn.addEventListener('click', (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
}

// ==========================================================================
// 9. DOM READY BOOTSTRAP
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
  const heroVideoEl = document.getElementById('heroVideo');
  if (heroVideoEl) {
    new HeroVideoController(heroVideoEl);
  }

  initSecondaryVideos();
  initMobileNavigation();
  initScrollReveals();
  initNewsletterForm();
  initServiceDialogs();
  initActions();
});
