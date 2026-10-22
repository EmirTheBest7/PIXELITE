/* Pixelite concept – small, dependency-free behaviour (CSP: external file only).
   Navigation sheet · language cookie · order-form helpers · hero shader · notebook tilt · legal TOC · switch offset. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false, addEventListener: function () {} };

  /* ---- mobile navigation sheet (replaces the original bootstrap-style collapse) ---- */
  var toggle = doc.querySelector('[data-nav-toggle]');
  var sheet = doc.getElementById('navbar');
  if (toggle && sheet) {
    var panel = sheet.querySelector('.sheet__panel');
    var focusables = function () { return [].slice.call(panel.querySelectorAll('a[href],button:not([disabled])')).filter(function (el) { return el.offsetParent !== null; }); };
    var setOpen = function (open) {
      sheet.classList.toggle('is-open', open); sheet.classList.toggle('in', open); toggle.classList.toggle('collapsed', !open);
      sheet.setAttribute('aria-hidden', open ? 'false' : 'true');
      if ('inert' in sheet) { sheet.inert = !open; }
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      doc.body.classList.toggle('has-sheet', open);
      if (open) { var f = focusables(); if (f[0]) { f[0].focus(); } } else { toggle.focus(); }
    };
    if ('inert' in sheet) { sheet.inert = true; }
    toggle.addEventListener('click', function () { setOpen(!sheet.classList.contains('is-open')); });
    sheet.addEventListener('click', function (e) {
      if (e.target.closest('[data-nav-close]') || e.target.closest('a[href*="#"]')) { setOpen(false); }
    });
    doc.addEventListener('keydown', function (e) {
      if (!sheet.classList.contains('is-open')) { return; }
      if (e.key === 'Escape') { setOpen(false); return; }
      if (e.key === 'Tab') {
        var f = focusables(); if (!f.length) { return; }
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    var mq = window.matchMedia('(min-width:1360px)');
    var closeOnWide = function () { if (mq.matches && sheet.classList.contains('is-open')) { sheet.classList.remove('is-open', 'in'); doc.body.classList.remove('has-sheet'); toggle.setAttribute('aria-expanded', 'false'); } };
    if (mq.addEventListener) { mq.addEventListener('change', closeOnWide); }
  }

  /* ---- remember an explicitly chosen language (same behaviour as the original: only picks where "/" redirects) ---- */
  doc.querySelectorAll('[data-lang]').forEach(function (a) {
    a.addEventListener('click', function () {
      doc.cookie = 'pixelite_lang=' + a.getAttribute('data-lang') + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    });
  });

  /* ---- order form: focus the error summary, block double submits, design thumbnail ---- */
  var alertBox = doc.querySelector('[data-form-alert]');
  if (alertBox) { alertBox.focus(); }
  var form = doc.getElementById('order');
  if (form) {
    form.addEventListener('submit', function () {
      var b = form.querySelector('button[type="submit"]');
      if (b) { setTimeout(function () { b.disabled = true; }, 0); }
    });
  }
  var pick = doc.querySelector('[data-template-pick]');
  if (pick) {
    var sel = pick.querySelector('select'), thumb = pick.querySelector('[data-template-thumb]');
    sel.addEventListener('change', function () {
      var src = sel.options[sel.selectedIndex].getAttribute('data-thumb');
      if (src) { thumb.src = src; thumb.hidden = false; } else { thumb.hidden = true; }
    });
  }

  /* ---- hero: fluted-glass shader (decorative; static frame under reduced motion; CSS gradient is the fallback) ---- */
  var canvas = doc.getElementById('hero-canvas');
  if (canvas) { initShader(canvas); }
  function isDark() {
    var t = root.getAttribute('data-theme');
    return t === 'dark' || (t !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }
  function initShader(cv) {
    var gl = null;
    try { gl = cv.getContext('webgl', { alpha: false, antialias: false, depth: false, stencil: false, powerPreference: 'low-power' }); } catch (e) { gl = null; }
    if (!gl) { return; }
    var vs = 'attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}';
    var fs = [
      'precision mediump float;uniform vec2 r;uniform float t;uniform float d;',
      'float h(vec2 p){p=fract(p*vec2(123.34,456.21));p+=dot(p,p+45.32);return fract(p.x*p.y);}',
      'mat2 rot(float a){float s=sin(a),c=cos(a);return mat2(c,-s,s,c);}',
      'float chroma(vec2 u,float t){float n1=sin(u.x*2.1+t*.4)*cos(u.y*1.8-t*.35);float n2=cos(u.x*1.6-t*.25)*sin(u.y*2.3+t*.3);',
      'vec2 f1=vec2(.72+.18*sin(t*.25),.42+.15*cos(t*.2));vec2 f2=vec2(.28+.15*cos(t*.3),.65+.12*sin(t*.22));',
      'float b1=smoothstep(.75,.05,length(u-f1));float b2=smoothstep(.85,.1,length(u-f2));',
      'return clamp((b1*.55+b2*.45+(n1+n2)*.15)*.32,0.,.35);}',
      'void main(){vec2 uv=gl_FragCoord.xy/r;float asp=r.x/r.y;vec2 p=uv;p.x*=asp;float tt=t*.15;',
      'mat2 m=rot(.541052);vec2 rp=m*p;float fw=sin(rp.x*7.5*6.2831853);float rs=cos(rp.x*7.5*6.2831853);',
      'float glint=pow(clamp(rs*.5+.5,0.,1.),6.)*mix(.12,.025,d);vec2 dd=m*vec2(rs,0.);',
      'float cr=chroma(uv+dd*.044,tt),cg=chroma(uv+dd*.032,tt),cb=chroma(uv+dd*.02,tt);',
      'vec3 light=mix(vec3(1.),vec3(.941),clamp(uv.y*.6+uv.x*.4+fw*.02,0.,1.));vec3 dark=mix(vec3(.075,.078,.105),vec3(.05,.052,.07),clamp(uv.y*.7+fw*.04,0.,1.));',
      'vec3 base=mix(light,dark,d);vec3 acc=vec3(.106,.271,1.);float k=mix(.62,.62,d);',
      'vec3 c=mix(base,acc,vec3(cr*k,cg*.65*k,cb*1.05*k));c+=vec3(glint);c-=vec3(smoothstep(-1.,1.,rs)*.02);',
      'c+=(h(gl_FragCoord.xy+fract(t*19.37))-.5)*.04;gl_FragColor=vec4(clamp(c,0.,1.),1.);}'
    ].join('');
    function sh(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null; }
    var v = sh(gl.VERTEX_SHADER, vs), f = sh(gl.FRAGMENT_SHADER, fs); if (!v || !f) { return; }
    var pr = gl.createProgram(); gl.attachShader(pr, v); gl.attachShader(pr, f); gl.linkProgram(pr);
    if (!gl.getProgramParameter(pr, gl.LINK_STATUS)) { return; }
    gl.useProgram(pr);
    var buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), gl.STATIC_DRAW);
    var loc = gl.getAttribLocation(pr, 'p'); gl.enableVertexAttribArray(loc); gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
    var uR = gl.getUniformLocation(pr, 'r'), uT = gl.getUniformLocation(pr, 't'), uD = gl.getUniformLocation(pr, 'd');
    var raf = 0, running = false, start = performance.now(), last = 0;
    function size() {
      var rect = cv.getBoundingClientRect(), s = Math.min(window.devicePixelRatio || 1, 1) * 0.75;   // modest resolution: it is a soft background
      var w = Math.max(1, Math.floor(rect.width * s)), h = Math.max(1, Math.floor(rect.height * s));
      if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; gl.viewport(0, 0, w, h); }
    }
    function draw(now) {
      size();
      gl.uniform2f(uR, cv.width, cv.height); gl.uniform1f(uT, (now - start) * 0.001); gl.uniform1f(uD, isDark() ? 1 : 0);
      gl.drawArrays(gl.TRIANGLES, 0, 6);
      cv.classList.add('is-live');
    }
    function loop(now) {
      if (!running) { return; }
      if (now - last > 33) { last = now; draw(now); }          // ~30 fps is plenty for a slow ambient field
      raf = requestAnimationFrame(loop);
    }
    function play() { if (running || reduce.matches) { return; } running = true; raf = requestAnimationFrame(loop); }
    function stop() { running = false; cancelAnimationFrame(raf); }
    function still() { draw(start + 4000); }
    if (reduce.matches) { still(); } else if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) { en[0].isIntersecting && !doc.hidden ? play() : stop(); }, { threshold: 0.02 }).observe(cv);
    } else { play(); }
    doc.addEventListener('visibilitychange', function () { if (doc.hidden) { stop(); } else if (!reduce.matches) { play(); } });
    if (reduce.addEventListener) { reduce.addEventListener('change', function () { if (reduce.matches) { stop(); still(); } else { play(); } }); }
    new MutationObserver(function () { if (!running) { still(); } }).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    window.addEventListener('resize', function () { if (!running) { still(); } }, { passive: true });
    cv.addEventListener('webglcontextlost', function (e) { e.preventDefault(); stop(); cv.classList.remove('is-live'); });
  }

  /* ---- notebook: a very light pointer tilt (fine pointers only, never under reduced motion) ---- */
  var stage = doc.querySelector('.hero__stage'), tilt = doc.querySelector('.lp-tilt');
  if (stage && tilt && window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
    var pend = false, ex = 0, ey = 0;
    stage.addEventListener('pointermove', function (e) {
      if (reduce.matches) { return; }
      var r = stage.getBoundingClientRect();
      ex = ((e.clientX - r.left) / r.width - 0.5) * 2; ey = ((e.clientY - r.top) / r.height - 0.5) * 2;
      if (!pend) { pend = true; requestAnimationFrame(function () { pend = false; tilt.style.setProperty('--ty', (ex * 5).toFixed(2) + 'deg'); tilt.style.setProperty('--tx', (-ey * 3).toFixed(2) + 'deg'); }); }
    });
    stage.addEventListener('pointerleave', function () { tilt.style.setProperty('--ty', '0deg'); tilt.style.setProperty('--tx', '0deg'); });
  }

  /* ---- legal pages: table of contents highlights the section in view ---- */
  var toc = doc.querySelector('[data-toc]');
  if (toc && 'IntersectionObserver' in window) {
    var links = {}; toc.querySelectorAll('a[href^="#"]').forEach(function (a) { links[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (en) {
      en.forEach(function (x) {
        if (x.isIntersecting && links[x.target.id]) {
          Object.keys(links).forEach(function (k) { links[k].classList.remove('is-current'); links[k].removeAttribute('aria-current'); });
          links[x.target.id].classList.add('is-current'); links[x.target.id].setAttribute('aria-current', 'location');
        }
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    Object.keys(links).forEach(function (id) { var el = doc.getElementById(id); if (el) { io.observe(el); } });
  }

  /* ---- keep the concept switch above the cookie banner while it is visible ---- */
  var banner = doc.getElementById('cookie-banner');
  if (banner) {
    var sync = function () { root.style.setProperty('--pad-b', banner.hidden ? '0px' : banner.offsetHeight + 12 + 'px'); };
    new MutationObserver(sync).observe(banner, { attributes: true, attributeFilter: ['hidden'] });
    if ('ResizeObserver' in window) { new ResizeObserver(sync).observe(banner); }
    sync();
  }
})();
