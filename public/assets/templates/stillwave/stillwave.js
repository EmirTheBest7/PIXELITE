/**
 * ============================================================================
 * Stillwave — Cinematic AI Visual Studio Landing Page
 * Native Vanilla JavaScript (Zero external dependencies)
 * ============================================================================
 */

(function () {
  'use strict';

  /* --------------------------------------------------------------------------
   * 1. Constants & Configuration
   * -------------------------------------------------------------------------- */

  /**
   * Primary video asset URL.
   * If commercial rights are not confirmed, replace with your licensed asset (e.g. 'hero-video.mp4').
   */
  const VIDEO_SRC =
    '/assets/templates/stillwave/video/hero.mp4';

  const BOOMERANG_CONFIG = {
    maxFrames: 90,           // Maximum offscreen frames captured to conserve memory
    maxWidth: 640,           // Max width for offscreen downscaled frames
    minFramesRequired: 18,   // Minimum captured frames required to activate boomerang canvas
    targetFps: 28,           // Playback frame rate for the boomerang sequence (~24-30 fps)
  };

  const PARALLAX_CONFIG = {
    maxOffsetPx: 20,         // Maximum translation in pixels
    lerpFactor: 0.06,        // Smooth linear interpolation factor per frame
    baseScale: 1.08,         // Scale to ensure no edges are visible during translation
  };

  /* --------------------------------------------------------------------------
   * 2. DOM Elements
   * -------------------------------------------------------------------------- */
  const videoEl = document.getElementById('hero-video');
  const canvasEl = document.getElementById('boomerang-canvas');
  const fallbackEl = document.getElementById('media-fallback');
  const mediaContainer = document.getElementById('media-container');
  const atmosphericOverlay = document.getElementById('atmospheric-overlay');

  // Navigation elements
  const mobileToggleBtn = document.getElementById('mobile-toggle-btn');
  const mobilePanel = document.getElementById('mobile-panel');

  // CTA & Interactive elements
  const btnCreateVisual = document.getElementById('btn-create-visual');
  const btnExploreStyles = document.getElementById('btn-explore-styles');
  const styleSelectorPanel = document.getElementById('style-selector-panel');
  const styleOptions = document.querySelectorAll('.style-option-btn');
  const stylePanelClose = document.getElementById('style-panel-close');

  // Creation-prompt Dialog elements
  const promptDialog = document.getElementById('prompt-dialog');
  const promptForm = document.getElementById('prompt-form');
  const promptTextarea = document.getElementById('prompt-textarea');
  const promptCharCount = document.getElementById('prompt-char-count');
  const promptDialogClose = document.getElementById('dialog-close-btn');
  const promptPreviewResult = document.getElementById('prompt-preview-result');
  const promptPreviewText = document.getElementById('prompt-preview-text');

  let lastFocusedElement = null;

  /* --------------------------------------------------------------------------
   * 3. Boomerang Frame Capture & Display
   * -------------------------------------------------------------------------- */
  const capturedFrames = [];
  let isCapturing = false;
  let lastRecordedTime = -1;
  let boomerangAnimationId = null;
  let boomerangDirection = 1; // 1 for forward, -1 for reverse
  let currentFrameIndex = 0;
  let displayCtx = null;

  function initVideoAndBoomerang() {
    if (!videoEl || !canvasEl) return;

    try {
      displayCtx = canvasEl.getContext('2d', { alpha: false });
    } catch (e) {
      console.warn('Canvas 2D context creation failed:', e);
    }

    // Set video source
    videoEl.src = VIDEO_SRC;

    videoEl.addEventListener('error', onVideoError);
    videoEl.addEventListener('loadedmetadata', onVideoMetadataLoaded);
    videoEl.addEventListener('ended', onVideoEnded);

    // Initial play attempt
    const playPromise = videoEl.play();
    if (playPromise !== undefined) {
      playPromise.catch((err) => {
        console.warn('Video auto-playback restriction:', err);
        // If autoplay is blocked, show fallback gently or wait for user gesture
      });
    }
  }

  function onVideoError() {
    console.warn('Video failed to load or cross-origin blocked. Activating CSS animated fallback.');
    if (fallbackEl) {
      fallbackEl.classList.add('is-visible');
    }
    if (videoEl) {
      videoEl.style.opacity = '0';
    }
  }

  function onVideoMetadataLoaded() {
    // Determine canvas display dimensions matching viewport
    updateCanvasSize();
    window.addEventListener('resize', updateCanvasSize);

    // Start capturing frames
    isCapturing = true;
    lastRecordedTime = -1;

    if ('requestVideoFrameCallback' in videoEl) {
      videoEl.requestVideoFrameCallback(captureFrameWithRvfc);
    } else {
      requestAnimationFrame(captureFrameWithRaf);
    }
  }

  function updateCanvasSize() {
    if (!canvasEl) return;
    canvasEl.width = window.innerWidth;
    canvasEl.height = window.innerHeight;
  }

  /**
   * Captures a single video frame into an offscreen canvas
   */
  function recordCurrentFrame() {
    if (!videoEl || videoEl.videoWidth === 0 || videoEl.videoHeight === 0) return false;

    try {
      // Calculate scaled dimensions to keep memory constrained
      const aspect = videoEl.videoHeight / videoEl.videoWidth;
      const targetW = Math.min(videoEl.videoWidth, BOOMERANG_CONFIG.maxWidth);
      const targetH = Math.round(targetW * aspect);

      const offscreen = document.createElement('canvas');
      offscreen.width = targetW;
      offscreen.height = targetH;
      const offCtx = offscreen.getContext('2d', { alpha: false });

      if (!offCtx) return false;

      offCtx.drawImage(videoEl, 0, 0, targetW, targetH);

      // Verify canvas is not tainted by cross-origin security rules
      // (This safely catches file:// or CORS-restricted domains)
      offCtx.getImageData(0, 0, 1, 1);

      capturedFrames.push(offscreen);
      return true;
    } catch (err) {
      // SecurityError on CORS/file:// or memory issue
      console.warn('Frame capture restricted (CORS/file:// security). Reverting to native looping video:', err);
      isCapturing = false;
      fallbackToNormalVideoLoop();
      return false;
    }
  }

  function captureFrameWithRvfc(now, metadata) {
    if (!isCapturing) return;

    if (metadata.mediaTime !== lastRecordedTime) {
      lastRecordedTime = metadata.mediaTime;
      const success = recordCurrentFrame();
      if (!success) return; // Stopped due to error or restriction
    }

    if (capturedFrames.length >= BOOMERANG_CONFIG.maxFrames || videoEl.ended) {
      isCapturing = false;
      onCaptureComplete();
    } else {
      videoEl.requestVideoFrameCallback(captureFrameWithRvfc);
    }
  }

  function captureFrameWithRaf() {
    if (!isCapturing) return;

    if (videoEl.currentTime !== lastRecordedTime) {
      lastRecordedTime = videoEl.currentTime;
      const success = recordCurrentFrame();
      if (!success) return;
    }

    if (capturedFrames.length >= BOOMERANG_CONFIG.maxFrames || videoEl.ended) {
      isCapturing = false;
      onCaptureComplete();
    } else {
      requestAnimationFrame(captureFrameWithRaf);
    }
  }

  function onVideoEnded() {
    if (isCapturing) {
      isCapturing = false;
      onCaptureComplete();
    }
  }

  function fallbackToNormalVideoLoop() {
    if (!videoEl) return;
    videoEl.loop = true;
    videoEl.play().catch(() => {});
  }

  function onCaptureComplete() {
    if (capturedFrames.length >= BOOMERANG_CONFIG.minFramesRequired) {
      // Pause underlying video and switch smoothly to canvas boomerang
      videoEl.pause();
      canvasEl.classList.add('is-active');
      startBoomerangLoop();
    } else {
      // Fallback to native video loop if insufficient frames were recorded
      fallbackToNormalVideoLoop();
    }
  }

  let lastFrameTimestamp = 0;
  const frameIntervalMs = 1000 / BOOMERANG_CONFIG.targetFps;

  function startBoomerangLoop() {
    if (boomerangAnimationId) cancelAnimationFrame(boomerangAnimationId);
    currentFrameIndex = 0;
    boomerangDirection = 1;
    lastFrameTimestamp = performance.now();
    renderBoomerangFrame(lastFrameTimestamp);
  }

  function renderBoomerangFrame(now) {
    if (document.hidden) {
      // Pause rendering loop when tab is hidden to save CPU/battery
      boomerangAnimationId = requestAnimationFrame(renderBoomerangFrame);
      return;
    }

    const elapsed = now - lastFrameTimestamp;

    if (elapsed >= frameIntervalMs && displayCtx && capturedFrames.length > 0) {
      lastFrameTimestamp = now - (elapsed % frameIntervalMs);

      const frameCanvas = capturedFrames[currentFrameIndex];
      if (frameCanvas) {
        // Draw image scaled with object-fit: cover logic
        drawCover(displayCtx, frameCanvas, canvasEl.width, canvasEl.height);
      }

      // Step frame index and boomerang reversal
      currentFrameIndex += boomerangDirection;
      if (currentFrameIndex >= capturedFrames.length - 1) {
        currentFrameIndex = capturedFrames.length - 1;
        boomerangDirection = -1;
      } else if (currentFrameIndex <= 0) {
        currentFrameIndex = 0;
        boomerangDirection = 1;
      }
    }

    boomerangAnimationId = requestAnimationFrame(renderBoomerangFrame);
  }

  /**
   * Helper to draw source canvas onto target canvas using object-fit: cover math
   */
  function drawCover(ctx, source, destW, destH) {
    const srcW = source.width;
    const srcH = source.height;
    const scale = Math.max(destW / srcW, destH / srcH);
    const drawW = srcW * scale;
    const drawH = srcH * scale;
    const drawX = (destW - drawW) / 2;
    const drawY = (destH - drawH) / 2;

    ctx.drawImage(source, drawX, drawY, drawW, drawH);
  }

  /* --------------------------------------------------------------------------
   * 4. Subtle Pointer-Based Parallax
   * -------------------------------------------------------------------------- */
  let targetX = 0;
  let targetY = 0;
  let currentX = 0;
  let currentY = 0;
  let parallaxRafId = null;

  function initParallax() {
    if (!mediaContainer) return;

    // Check pointer capabilities and reduced motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isTouchOnly = window.matchMedia('(pointer: coarse)').matches;

    if (prefersReducedMotion || isTouchOnly) {
      // Keep static centered transform
      mediaContainer.style.transform = `translate3d(0, 0, 0) scale(${PARALLAX_CONFIG.baseScale})`;
      return;
    }

    window.addEventListener('mousemove', onMouseMove, { passive: true });
    document.addEventListener('visibilitychange', onVisibilityChange);

    runParallaxLoop();
  }

  function onMouseMove(e) {
    const halfW = window.innerWidth / 2;
    const halfH = window.innerHeight / 2;

    // Calculate pointer position relative to viewport center [-1 to 1]
    const normX = (e.clientX - halfW) / halfW;
    const normY = (e.clientY - halfH) / halfH;

    targetX = normX * PARALLAX_CONFIG.maxOffsetPx;
    targetY = normY * PARALLAX_CONFIG.maxOffsetPx;
  }

  function runParallaxLoop() {
    if (!document.hidden) {
      currentX += (targetX - currentX) * PARALLAX_CONFIG.lerpFactor;
      currentY += (targetY - currentY) * PARALLAX_CONFIG.lerpFactor;

      // Apply smoothed translation with scale
      mediaContainer.style.transform = `translate3d(${currentX.toFixed(2)}px, ${currentY.toFixed(2)}px, 0) scale(${PARALLAX_CONFIG.baseScale})`;
    }

    parallaxRafId = requestAnimationFrame(runParallaxLoop);
  }

  function onVisibilityChange() {
    if (document.hidden) {
      // Pause loops when hidden
    } else {
      lastFrameTimestamp = performance.now();
    }
  }

  /* --------------------------------------------------------------------------
   * 5. Mobile Navigation Menu
   * -------------------------------------------------------------------------- */
  function initMobileMenu() {
    if (!mobileToggleBtn || !mobilePanel) return;

    mobileToggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = mobilePanel.classList.contains('is-open');
      setMobileMenuOpen(!isOpen);
    });

    // Close when clicking outside panel
    document.addEventListener('click', (e) => {
      if (
        mobilePanel.classList.contains('is-open') &&
        !mobilePanel.contains(e.target) &&
        !mobileToggleBtn.contains(e.target)
      ) {
        setMobileMenuOpen(false);
      }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && mobilePanel.classList.contains('is-open')) {
        setMobileMenuOpen(false);
        mobileToggleBtn.focus();
      }
    });

    // Auto-close on link click
    const mobileLinks = mobilePanel.querySelectorAll('a, button');
    mobileLinks.forEach((link) => {
      link.addEventListener('click', () => {
        setMobileMenuOpen(false);
      });
    });
  }

  function setMobileMenuOpen(open) {
    if (open) {
      mobilePanel.classList.add('is-open');
      mobileToggleBtn.setAttribute('aria-expanded', 'true');
    } else {
      mobilePanel.classList.remove('is-open');
      mobileToggleBtn.setAttribute('aria-expanded', 'false');
    }
  }

  /* --------------------------------------------------------------------------
   * 6. Visual-Style Selector
   * -------------------------------------------------------------------------- */
  const STYLE_PRESETS = {
    editorial: {
      glow: 'rgba(46, 102, 255, 0.22)',
      secondary: 'rgba(18, 55, 160, 0.18)',
      centerX: '28%',
      centerY: '42%',
      radius: '55vw',
      vignette: 'rgba(5, 6, 9, 0.88)',
    },
    dreamlike: {
      glow: 'rgba(56, 140, 255, 0.32)',
      secondary: 'rgba(30, 80, 200, 0.25)',
      centerX: '35%',
      centerY: '38%',
      radius: '65vw',
      vignette: 'rgba(5, 6, 9, 0.76)',
    },
    product: {
      glow: 'rgba(46, 102, 255, 0.18)',
      secondary: 'rgba(12, 35, 110, 0.14)',
      centerX: '50%',
      centerY: '48%',
      radius: '45vw',
      vignette: 'rgba(5, 6, 9, 0.94)',
    },
  };

  function initStyleSelector() {
    if (!btnExploreStyles || !styleSelectorPanel) return;

    btnExploreStyles.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = styleSelectorPanel.classList.contains('is-open');
      setStyleSelectorOpen(!isOpen);
    });

    if (stylePanelClose) {
      stylePanelClose.addEventListener('click', () => {
        setStyleSelectorOpen(false);
        btnExploreStyles.focus();
      });
    }

    styleOptions.forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const styleKey = btn.dataset.style;
        applyVisualStyle(styleKey);

        // Update active class
        styleOptions.forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');

        // Close after selection
        setStyleSelectorOpen(false);
        btnExploreStyles.focus();
      });
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
      if (
        styleSelectorPanel.classList.contains('is-open') &&
        !styleSelectorPanel.contains(e.target) &&
        !btnExploreStyles.contains(e.target)
      ) {
        setStyleSelectorOpen(false);
      }
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && styleSelectorPanel.classList.contains('is-open')) {
        setStyleSelectorOpen(false);
        btnExploreStyles.focus();
      }
    });
  }

  function setStyleSelectorOpen(open) {
    if (open) {
      styleSelectorPanel.classList.add('is-open');
      btnExploreStyles.setAttribute('aria-expanded', 'true');
    } else {
      styleSelectorPanel.classList.remove('is-open');
      btnExploreStyles.setAttribute('aria-expanded', 'false');
    }
  }

  function applyVisualStyle(key) {
    const preset = STYLE_PRESETS[key] || STYLE_PRESETS.editorial;
    const root = document.documentElement;

    root.style.setProperty('--atmosphere-glow-color', preset.glow);
    root.style.setProperty('--atmosphere-secondary-color', preset.secondary);
    root.style.setProperty('--atmosphere-center-x', preset.centerX);
    root.style.setProperty('--atmosphere-center-y', preset.centerY);
    root.style.setProperty('--atmosphere-radius', preset.radius);
    root.style.setProperty('--atmosphere-vignette', preset.vignette);
  }

  /* --------------------------------------------------------------------------
   * 7. Accessible Creation-Prompt Dialog
   * -------------------------------------------------------------------------- */
  function initPromptDialog() {
    if (!promptDialog) return;

    if (btnCreateVisual) {
      btnCreateVisual.addEventListener('click', () => {
        lastFocusedElement = document.activeElement;
        openPromptDialog();
      });
    }

    if (promptDialogClose) {
      promptDialogClose.addEventListener('click', closePromptDialog);
    }

    // Backdrop click handling for native <dialog>
    promptDialog.addEventListener('click', (e) => {
      const rect = promptDialog.getBoundingClientRect();
      const isInDialog =
        rect.top <= e.clientY &&
        e.clientY <= rect.top + rect.height &&
        rect.left <= e.clientX &&
        e.clientX <= rect.left + rect.width;

      if (!isInDialog) {
        closePromptDialog();
      }
    });

    // Escape key closes and manages focus
    promptDialog.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closePromptDialog();
      }
    });

    // Character counter
    if (promptTextarea && promptCharCount) {
      promptTextarea.addEventListener('input', () => {
        const count = promptTextarea.value.length;
        promptCharCount.textContent = `${count} characters`;
      });
    }

    // Form submission (Local preview state without network calls)
    if (promptForm) {
      promptForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = promptTextarea ? promptTextarea.value.trim() : '';
        if (!text) {
          if (promptTextarea) promptTextarea.focus();
          return;
        }

        if (promptPreviewText && promptPreviewResult) {
          promptPreviewText.textContent = `"${text}"`;
          promptPreviewResult.classList.add('is-active');
        }
      });
    }
  }

  function openPromptDialog() {
    if (typeof promptDialog.showModal === 'function') {
      promptDialog.showModal();
    } else {
      // Fallback for older browsers
      promptDialog.setAttribute('open', '');
    }

    if (promptTextarea) {
      promptTextarea.focus();
    }
  }

  function closePromptDialog() {
    if (typeof promptDialog.close === 'function') {
      promptDialog.close();
    } else {
      promptDialog.removeAttribute('open');
    }

    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
      lastFocusedElement.focus();
    }
  }

  /* --------------------------------------------------------------------------
   * 8. Initialization Lifecycle
   * -------------------------------------------------------------------------- */
  function init() {
    initVideoAndBoomerang();
    initParallax();
    initMobileMenu();
    initStyleSelector();
    initPromptDialog();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
