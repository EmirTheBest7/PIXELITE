// Browser test matrix (real Chrome via CDP). Needs the app running, e.g. `docker compose up -d`.
//   node tests/browser/run.mjs [--base http://localhost:8080] [--quick] [--shots]
// --quick  : only 3 devices (375 / 768 / 1440)         --shots : save screenshots to tests/browser/out/
// Hard failures exit 1. "advisory" lines are reported but do not fail the run.
import fs from 'node:fs';
import path from 'node:path';
import { launch } from './cdp.mjs';

const args = process.argv.slice(2);
const opt = (n, d) => (args.includes(n) ? args[args.indexOf(n) + 1] : d);
const BASE = opt('--base', 'http://localhost:8080');
const QUICK = args.includes('--quick'), SHOTS = args.includes('--shots');
const OUT = new URL('./out/', import.meta.url).pathname;
if (SHOTS) fs.mkdirSync(OUT, { recursive: true });

const DEVICES = [
  { id: 'narrow-320', w: 320, h: 568, dpr: 2, mobile: true },
  { id: 'iphone-se-375', w: 375, h: 667, dpr: 2, mobile: true, quick: true },
  { id: 'iphone-14-390', w: 390, h: 844, dpr: 3, mobile: true },
  { id: 'pixel-7-412', w: 412, h: 915, dpr: 2.625, mobile: true, ua: 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36' },
  { id: 'tablet-768', w: 768, h: 1024, dpr: 2, mobile: true, quick: true },
  { id: 'tablet-820', w: 820, h: 1180, dpr: 2, mobile: true },
  { id: 'tablet-1024', w: 1024, h: 768, dpr: 2, mobile: true },
  { id: 'desktop-1280', w: 1280, h: 800, dpr: 1, mobile: false },
  { id: 'desktop-1440', w: 1440, h: 900, dpr: 1, mobile: false, quick: true },
  { id: 'desktop-1920', w: 1920, h: 1080, dpr: 1, mobile: false },
].filter((d) => !QUICK || d.quick);
const LANGS = ['en', 'cs'];
const THEMES = ['light', 'dark'];
const PAGES = [
  { key: 'home', path: '', h1: 1 }, { key: 'order', path: 'order' }, { key: 'contact', path: 'contact' },
  { key: 'privacy', path: 'privacy' }, { key: 'cookies', path: 'cookies' }, { key: 'terms', path: 'terms' },
  { key: 'portfolio', path: 'portfolio', robots: 'noindex' }, { key: '404', path: 'zzz-not-found', status: 404, robots: 'noindex' },
];
const CONSENT = encodeURIComponent(JSON.stringify({ v: 1, c: {}, t: Math.floor(Date.now() / 1000) }));

let passed = 0, failed = 0;
const failures = [], advisories = new Map();
const ok = (name, cond, detail = '') => { if (cond) passed++; else { failed++; failures.push(`${name}${detail ? ' — ' + detail : ''}`); } };
const advise = (key, detail) => { advisories.set(key, (advisories.get(key) || new Set()).add(detail)); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------- in-page audits (run inside Chrome) ----------
const AUDIT = `(() => {
  const vis = (e) => { const r = e.getBoundingClientRect(), cs = getComputedStyle(e); return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none'; };
  const name = (e) => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\\s+/)[0] : '');
  const W = innerWidth, out = { overflow: [], clipped: [], overlaps: [], small: [], tiny: [], unnamed: [], dupIds: [], headings: [], imgNoAlt: [], unlabeled: [] };
  out.scrollW = document.documentElement.scrollWidth; out.innerW = W;
  const skip = (e) => e.closest('.skip-link, .form__trap, [hidden], dialog:not([open]), .sr-only, thead');
  for (const e of document.querySelectorAll('body *')) {
    if (skip(e) || !vis(e)) continue;
    const r = e.getBoundingClientRect(), cs = getComputedStyle(e);
    if (cs.position !== 'fixed' && r.right > W + 1 && !e.closest('.carousel')) out.overflow.push(name(e) + ' right=' + Math.round(r.right));
    if (['hidden', 'clip'].includes(cs.overflowX) && e.scrollWidth > e.clientWidth + 2 && e.clientWidth > 0 && cs.display !== 'inline') out.clipped.push(name(e));
  }
  // interactive: size, names
  const inter = [...document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button]')].filter((e) => !skip(e) && vis(e));
  for (const e of inter) {
    const r = e.getBoundingClientRect(), cs = getComputedStyle(e);
    const inlineText = e.tagName === 'A' && cs.display === 'inline';
    if (!inlineText) {
      const m = Math.min(r.width, r.height);
      if (m < 24) out.tiny.push(name(e) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height));
      else if (m < 44) out.small.push(name(e) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height));
    }
    const label = (e.getAttribute('aria-label') || e.textContent || e.getAttribute('title') || '').trim() || (e.labels && e.labels.length ? 'x' : '') || e.getAttribute('placeholder') || e.getAttribute('aria-labelledby');
    if (!label) out.unnamed.push(name(e));
    if (['INPUT', 'SELECT', 'TEXTAREA'].includes(e.tagName) && e.type !== 'submit' && !(e.labels && e.labels.length) && !e.getAttribute('aria-label')) out.unlabeled.push(name(e));
  }
  // header overlap
  const hdr = [...document.querySelectorAll('.header a, .header button')].filter((e) => !skip(e) && vis(e));
  for (let i = 0; i < hdr.length; i++) for (let j = i + 1; j < hdr.length; j++) {
    const a = hdr[i], b = hdr[j]; if (a.contains(b) || b.contains(a)) continue;
    const A = a.getBoundingClientRect(), B = b.getBoundingClientRect();
    const w = Math.min(A.right, B.right) - Math.max(A.left, B.left), h = Math.min(A.bottom, B.bottom) - Math.max(A.top, B.top);
    if (w > 2 && h > 2) out.overlaps.push(name(a) + ' x ' + name(b));
  }
  // headings, ids, images
  let prev = 0; for (const h of document.querySelectorAll('h1,h2,h3,h4,h5,h6')) { if (skip(h) || !vis(h)) continue; const l = +h.tagName[1]; if (prev && l > prev + 1) out.headings.push('h' + prev + '->h' + l + ' "' + h.textContent.trim().slice(0, 30) + '"'); prev = l; }
  const ids = {}; for (const e of document.querySelectorAll('[id]')) ids[e.id] = (ids[e.id] || 0) + 1; out.dupIds = Object.keys(ids).filter((k) => ids[k] > 1);
  for (const i of document.querySelectorAll('img')) if (!i.hasAttribute('alt')) out.imgNoAlt.push(i.getAttribute('src'));
  out.brokenImgs = [...document.querySelectorAll('img')].filter((i) => i.getBoundingClientRect().width > 0 && (!i.complete || i.naturalWidth === 0)).map((i) => i.getAttribute('src'));
  out.logos = [...document.querySelectorAll('img[src*="logo"]')].map((i) => ({ src: i.getAttribute('src').split('?')[0], w: Math.round(i.getBoundingClientRect().width), h: Math.round(i.getBoundingClientRect().height), where: i.closest('footer') ? 'footer' : i.closest('.header, .admin__bar') ? 'header' : 'content' }));
  out.h1 = document.querySelectorAll('h1').length; out.lang = document.documentElement.lang; out.title = document.title;
  out.fonts = [...document.fonts].filter((f) => f.status === 'loaded').length;
  out.theme = document.documentElement.getAttribute('data-theme'); out.bg = getComputedStyle(document.body).backgroundColor;
  const q = (s, a) => { const e = document.querySelector(s); return e ? e.getAttribute(a) : null; };
  out.head = { desc: q('meta[name=description]', 'content'), canon: q('link[rel=canonical]', 'href'), robots: q('meta[name=robots]', 'content'), ogTitle: q('meta[property="og:title"]', 'content'),
    ogImg: q('meta[property="og:image"]', 'content'), viewport: q('meta[name=viewport]', 'content'), hreflang: document.querySelectorAll('link[rel=alternate][hreflang]').length,
    icons: [...document.querySelectorAll('link[rel~=icon], link[rel=apple-touch-icon]')].map((l) => l.getAttribute('href')), colorScheme: q('meta[name=color-scheme]', 'content') };
  return out;
})()`;

const CONTRAST = `(() => {
  const parse = (c) => { const m = c.match(/rgba?\\(([^)]+)\\)/); if (!m) return null; const p = m[1].split(',').map(parseFloat); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lum = ({ r, g, b }) => { const f = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
  const over = (top, bot) => ({ r: top.r * top.a + bot.r * (1 - top.a), g: top.g * top.a + bot.g * (1 - top.a), b: top.b * top.a + bot.b * (1 - top.a), a: 1 });
  const bgOf = (e) => { const stack = []; for (let n = e; n; n = n.parentElement) { const c = parse(getComputedStyle(n).backgroundColor); if (c && c.a > 0) { stack.push(c); if (c.a === 1) break; } } let bg = { r: 255, g: 255, b: 255, a: 1 }; for (const c of stack.reverse()) bg = over(c, bg); return bg; };
  const res = new Map();
  const skip = (e) => e.closest('.skip-link, .form__trap, [hidden], dialog:not([open]), .sr-only, thead, script, style');
  for (const e of document.querySelectorAll('body *')) {
    if (skip(e)) continue;
    const own = [...e.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
    if (!own) continue;
    const r = e.getBoundingClientRect(), cs = getComputedStyle(e);
    if (r.width < 1 || r.height < 1 || cs.visibility === 'hidden' || +cs.opacity === 0) continue;
    let fg = parse(cs.color); if (!fg) continue; const bg = bgOf(e); fg = over(fg, bg);
    const L1 = lum(fg), L2 = lum(bg), ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
    const size = parseFloat(cs.fontSize), bold = parseInt(cs.fontWeight) >= 700, large = size >= 24 || (size >= 18.66 && bold);
    const need = large ? 3 : 4.5;
    if (ratio < need) { const isNew = !!e.closest('.consent-banner, .consent-dialog, .cookie-table, .prose, .theme, .theme-cycle, .footer, .lang, .form__alert, .form__error, .form__consent, .form__note, .price-box__desc, .price-box__note, .price-box__from, .credits, .consult') && !e.closest('.footer__title'); const k = (isNew ? '[new] ' : '') + e.tagName.toLowerCase() + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\\s+/)[0] : '') + ' ' + ratio.toFixed(2) + ':1 (need ' + need + ')'; res.set(k, (res.get(k) || 0) + 1); }
  }
  return [...res.keys()];
})()`;

const url = (lang, p) => `${BASE}/${lang}/${p}`;
async function freshPage(browser, d, theme, extra = {}) {
  const page = await browser.newPage({ width: d.w, height: d.h, dpr: d.dpr, mobile: d.mobile, ua: d.ua || null, scheme: theme, ...extra });
  if (extra.consent !== false) await page.setCookie(BASE, 'pixelite_consent', CONSENT);
  return page;
}

// ================= A) MATRIX =================
async function matrix(browser) {
  let loads = 0;
  for (const d of DEVICES) for (const theme of THEMES) {
    const page = await freshPage(browser, d, theme);
    const tag = (lang, p) => `[${d.id}/${lang}/${theme}/${p}]`;
    const targets = [];
    for (const lang of LANGS) for (const p of PAGES) targets.push({ lang, p, u: url(lang, p.path), t: tag(lang, p.key) });
    targets.push({ lang: 'en', p: { key: 'admin-login', path: '', robots: 'noindex', admin: true }, u: `${BASE}/admin/login`, t: `[${d.id}/-/${theme}/admin-login]` });
    for (const { lang, p, u, t } of targets) {
      const nav = await page.goto(u); loads++;
      const expectedStatus = p.status || 200;
      const consoleErrs = page.log.console.filter((m) => !(expectedStatus !== 200 && m.includes(`status of ${expectedStatus}`) && m.includes(u.replace(BASE, ''))));
      const a = await page.eval(AUDIT);
      const expectStatus = p.status || 200;
      ok(`${t} no horizontal overflow`, a.scrollW <= a.innerW && a.overflow.length === 0, `scrollW=${a.scrollW} innerW=${a.innerW} ${a.overflow.slice(0, 3)}`);
      ok(`${t} exactly one h1`, a.h1 === 1, `h1=${a.h1}`);
      ok(`${t} language attr`, p.admin ? a.lang === 'en' : a.lang === lang, a.lang);
      ok(`${t} title`, a.title.length > 3);
      ok(`${t} fonts loaded`, a.fonts >= 2, `fonts=${a.fonts}`);
      ok(`${t} no header overlap`, a.overlaps.length === 0, a.overlaps.slice(0, 3).join('; '));
      ok(`${t} no clipped text`, a.clipped.length === 0, a.clipped.slice(0, 3).join('; '));
      ok(`${t} no console errors/exceptions`, consoleErrs.length === 0 && page.log.exceptions.length === 0, [...consoleErrs, ...page.log.exceptions].slice(0, 2).join(' | '));
      ok(`${t} no failed requests`, page.log.failed.length === 0, page.log.failed.slice(0, 2).join(' | '));
      const bad = page.log.badResponses.filter((r) => !(expectStatus === 404 && r.type === 'Document'));
      ok(`${t} no 4xx/5xx sub-resources`, bad.length === 0, JSON.stringify(bad.slice(0, 2)));
      ok(`${t} form controls labelled`, a.unlabeled.length === 0, a.unlabeled.join(','));
      ok(`${t} buttons/links have names`, a.unnamed.length === 0, a.unnamed.join(','));
      ok(`${t} touch targets >= 24px (WCAG 2.5.8)`, a.tiny.length === 0, a.tiny.slice(0, 4).join('; '));
      if (d.mobile) for (const s of a.small) advise(`touch target < 44px (mobile)`, `${d.id}: ${s}`);
      for (const h of a.headings) advise('heading level skipped', `${p.key}: ${h}`);
      ok(`${t} every visible <img> loaded (no broken images)`, a.brokenImgs.length === 0, a.brokenImgs.join(','));
      ok(`${t} logo is the supplied SVG mark, square, never distorted`, a.logos.length >= (p.admin ? 1 : 2) && a.logos.every((l) => l.src === '/assets/img/logo.svg' && l.w === l.h && l.w >= 15), JSON.stringify(a.logos));
      if (!p.admin) ok(`${t} footer inline links keep their reset (no stray padding)`, await page.eval(`[...document.querySelectorAll('.footer__link--inline')].every((a) => { const c = getComputedStyle(a); return c.display === 'inline' && c.paddingTop === '0px' && c.marginLeft === '0px'; })`));
      ok(`${t} unique ids`, a.dupIds.length === 0, a.dupIds.join(','));
      ok(`${t} images have alt`, a.imgNoAlt.length === 0, a.imgNoAlt.join(','));
      ok(`${t} zoom not disabled`, !/user-scalable\s*=\s*(no|0)|maximum-scale\s*=\s*1(\.0)?\b/.test(a.head.viewport || ''), a.head.viewport);
      ok(`${t} theme applied (${theme})`, theme === 'dark' ? /rgb\(1[0-9], 1[0-9], \d+\)|rgb\(\d, \d+, \d+\)/.test(a.bg) : a.bg === 'rgb(255, 255, 255)', a.bg);
      if (!p.admin && p.key !== '404') {
        ok(`${t} meta description`, !!a.head.desc && a.head.desc.length > 20);
        ok(`${t} canonical absolute`, /^https?:\/\/[^/]+\/(en|cs)\//.test(a.head.canon || ''), a.head.canon);
        ok(`${t} og tags`, !!a.head.ogTitle && !!a.head.ogImg);
        ok(`${t} hreflang pair + x-default`, a.head.hreflang === 3, `${a.head.hreflang}`);
      }
      ok(`${t} robots`, p.robots ? (a.head.robots || '').includes(p.robots) : (a.head.robots || '').startsWith('index'), a.head.robots);
      ok(`${t} favicon + touch icon links`, a.head.icons.length === 3, a.head.icons.join(','));
      ok(`${t} color-scheme meta`, a.head.colorScheme === 'light dark');
      const st = nav.errorText ? 0 : (page.log.badResponses.find((r) => r.type === 'Document')?.status || 200);
      ok(`${t} HTTP ${expectStatus}`, st === expectStatus || (expectStatus === 200 && st === 200), `got ${st}`);
      // contrast: dark theme is ours to control (hard); light theme has known inherited design values (advisory)
      if (d.id.endsWith('375') || d.id.endsWith('1440') || d.id.endsWith('1280') || QUICK) {
        const c = await page.eval(CONTRAST);
        if (theme === 'dark') ok(`${t} text contrast AA (dark)`, c.length === 0, c.slice(0, 3).join('; '));
        else {
          ok(`${t} text contrast AA for new components (light)`, c.filter((x) => x.startsWith('[new]')).length === 0, c.filter((x) => x.startsWith('[new]')).slice(0, 3).join('; '));
          for (const x of c.filter((y) => !y.startsWith('[new]'))) advise('light-theme contrast (inherited design values)', `${p.key}: ${x}`);
        }
      }
      if (SHOTS && (d.id.includes('375') || d.id.includes('1440') || d.id.includes('768')) && !p.admin) await page.screenshot(path.join(OUT, `${d.id}_${lang}_${theme}_${p.key}.png`));
    }
    await page.close();
  }
  return loads;
}

// ================= B) INTERACTIONS =================
const MOBILE = { id: 'iphone-se-375', w: 375, h: 667, dpr: 2, mobile: true };
const DESKTOP = { id: 'desktop-1440', w: 1440, h: 900, dpr: 1, mobile: false };
const cookieOf = (page, n) => page.eval(`(document.cookie.match(/(?:^|; )${n}=([^;]*)/)||[])[1]||null`);
const visible = (page, sel) => page.eval(`(() => { const e = document.querySelector(${JSON.stringify(sel)}); if (!e) return false; const r = e.getBoundingClientRect(), cs = getComputedStyle(e); return !e.hidden && cs.display !== 'none' && cs.visibility !== 'hidden' && r.width > 0 && r.height > 0; })()`);

async function consentScenarios(browser) {
  for (const d of [MOBILE, DESKTOP]) for (const theme of ['light', 'dark']) {
    const T = (s) => `[consent/${d.id}/${theme}] ${s}`;
    // first visit
    let page = await freshPage(browser, d, theme, { consent: false });
    await page.goto(url('en', ''));
    ok(T('first visit: banner visible'), await visible(page, '#cookie-banner'));
    const geo = await page.eval(`(() => { const b = document.getElementById('cookie-banner').getBoundingClientRect(); return { top: b.top, bottom: b.bottom, h: b.height, vh: innerHeight, pad: document.body.style.paddingBottom, pos: getComputedStyle(document.getElementById('cookie-banner')).position }; })()`);
    ok(T('banner is fixed at the bottom and leaves content visible'), geo.pos === 'fixed' && Math.round(geo.bottom) === geo.vh && geo.h < geo.vh * 0.75, JSON.stringify(geo));
    ok(T('page keeps room for the banner (body padding)'), parseInt(geo.pad) >= Math.floor(geo.h) - 1, geo.pad);
    const btns = await page.eval(`(() => [...document.querySelectorAll('#cookie-banner button')].map((b) => { const r = b.getBoundingClientRect(), cs = getComputedStyle(b); return { t: b.textContent.trim(), w: Math.round(r.width), h: Math.round(r.height), bg: cs.backgroundColor, fs: cs.fontSize }; }))()`);
    ok(T('Accept and Reject are the same visual weight'), btns.length === 3 && btns[0].bg === btns[1].bg && btns[0].h === btns[1].h && btns[0].fs === btns[1].fs, JSON.stringify(btns));
    ok(T('banner buttons are >= 44px tall'), btns.every((b) => b.h >= 44), JSON.stringify(btns.map((b) => b.h)));
    ok(T('no tracking cookie before choice (only none set)'), (await page.eval('document.cookie')) === '' , await page.eval('document.cookie'));
    if (d.mobile) { await page.tap('.navbar-toggle'); await page.tap('#navbar a[href$="/contact"]'); } else { await page.tap('.header__link[href$="/contact"]'); }
    await sleep(700);
    ok(T('navigation works while the banner is open (and the banner is not forced away)'), (await page.eval('location.pathname')) === '/en/contact' && (await visible(page, '#cookie-banner')), await page.eval('location.pathname'));
    ok(T('closed <dialog> is not rendered (no-JS/old-browser safe)'), (await page.eval('getComputedStyle(document.getElementById("cookie-dialog")).display')) === 'none');
    await page.close();

    // accept -> persists across reload, navigation, language switch
    page = await freshPage(browser, d, theme, { consent: false });
    await page.goto(url('en', ''));
    await page.tap('#cookie-banner [data-consent-accept]');
    ok(T('accept: banner hides'), !(await visible(page, '#cookie-banner')));
    const raw = await cookieOf(page, 'pixelite_consent');
    const c = raw ? JSON.parse(decodeURIComponent(raw)) : null;
    ok(T('accept: consent cookie v/c/t, no secrets'), !!c && c.v === 1 && typeof c.c === 'object' && c.t > 1.7e9 && Object.keys(c).sort().join() === 'c,t,v', raw);
    ok(T('accept: padding released'), (await page.eval('document.body.style.paddingBottom')) === '');
    await page.reload(); ok(T('accept: stays hidden after reload'), !(await visible(page, '#cookie-banner')));
    await page.goto(url('cs', 'order')); ok(T('accept: stays hidden on another page/language'), !(await visible(page, '#cookie-banner')));
    ok(T('footer "Cookie settings" reopens preferences after a choice'), await (async () => { await page.tap('.footer [data-cookie-settings]'); return page.eval('document.getElementById("cookie-dialog").open'); })());
    ok(T('dialog: focus moved inside'), await page.eval('document.getElementById("cookie-dialog").contains(document.activeElement)'));
    await page.key('Escape');
    ok(T('dialog: Esc closes'), !(await page.eval('document.getElementById("cookie-dialog").open')));
    ok(T('dialog: focus returns to the footer link'), await page.eval('document.activeElement && document.activeElement.hasAttribute("data-cookie-settings")'));
    await page.close();

    // reject
    page = await freshPage(browser, d, theme, { consent: false });
    await page.goto(url('cs', ''));
    await page.tap('#cookie-banner [data-consent-reject]');
    const rc = JSON.parse(decodeURIComponent(await cookieOf(page, 'pixelite_consent')));
    ok(T('reject: stored, no optional category enabled'), Object.values(rc.c).every((v) => v === 0));
    ok(T('reject: PixeliteConsent.allows(analytics) is false'), (await page.eval('window.PixeliteConsent.allows("analytics")')) === false);
    await page.reload(); ok(T('reject: not re-prompted'), !(await visible(page, '#cookie-banner')));
    ok(T('optional scripts stay inert'), (await page.eval('document.querySelectorAll("script[data-consent][data-activated]").length')) === 0);
    await page.close();

    // settings from banner, and #settings deep link
    page = await freshPage(browser, d, theme, { consent: false });
    await page.goto(url('en', ''));
    await page.tap('#cookie-banner [data-consent-settings]');
    ok(T('settings: opens from banner'), await page.eval('document.getElementById("cookie-dialog").open'));
    const dg = await page.eval(`(() => { const r = document.getElementById("cookie-dialog").getBoundingClientRect(); return { l: r.left, r: r.right, t: r.top, b: r.bottom, w: innerWidth, h: innerHeight }; })()`);
    ok(T('settings: dialog fits the viewport'), dg.l >= 0 && dg.r <= dg.w && dg.t >= 0 && dg.b <= dg.h, JSON.stringify(dg));
    ok(T('settings: explains no optional cookies are used'), /No optional cookies/i.test(await page.eval('document.getElementById("cookie-dialog").innerText')));
    await page.tap('#cookie-dialog [data-consent-reject]');
    ok(T('settings: reject closes dialog + banner'), !(await page.eval('document.getElementById("cookie-dialog").open')) && !(await visible(page, '#cookie-banner')));
    await page.close();
    page = await freshPage(browser, d, theme);
    await page.goto(url('cs', 'cookies') + '#settings');
    ok(T('deep link /cookies#settings opens the dialog'), await page.eval('document.getElementById("cookie-dialog").open'));
    await page.close();
  }
}

async function consentGateScenarios(browser) {
  const T = (s) => `[consent-gate] ${s}`;
  const d = DESKTOP;
  const cookieVal = (o) => encodeURIComponent(JSON.stringify(o));
  const now = Math.floor(Date.now() / 1000);
  // 1) the gate: only an allowed category activates its deferred script
  let page = await browser.newPage({ width: d.w, height: d.h, dpr: 1 });
  await page.setCookie(BASE, 'pixelite_consent', cookieVal({ v: 1, c: { analytics: 1, marketing: 0 }, t: now }));
  await page.goto(url('en', ''));
  const gate = await page.eval(`(() => { for (const c of ['analytics', 'marketing']) { const s = document.createElement('script'); s.type = 'text/plain'; s.setAttribute('data-consent', c); s.setAttribute('data-src', '/assets/theme-init.js'); s.id = 'g-' + c; document.body.appendChild(s); } window.PixeliteConsent.refresh(); return { a: document.getElementById('g-analytics').getAttribute('data-activated'), m: document.getElementById('g-marketing').getAttribute('data-activated'), allowsA: window.PixeliteConsent.allows('analytics'), allowsM: window.PixeliteConsent.allows('marketing'), injected: document.querySelectorAll('script[src*="theme-init"]').length }; })()`);
  ok(T('allowed category activates its script'), gate.a === '1' && gate.allowsA === true, JSON.stringify(gate));
  ok(T('non-allowed category stays inert'), gate.m === null && gate.allowsM === false, JSON.stringify(gate));
  await page.close();
  // 2) invalid / expired / old-version / malformed cookies must re-show the banner
  const cases = { 'expired (7 months old)': cookieVal({ v: 1, c: {}, t: now - 215 * 86400 }), 'old version': cookieVal({ v: 0, c: {}, t: now }), 'future version': cookieVal({ v: 99, c: {}, t: now }), 'malformed JSON': 'not-json', 'categories not an object': cookieVal({ v: 1, c: null, t: now }), 'missing timestamp': cookieVal({ v: 1, c: {} }), 'script injection attempt': encodeURIComponent('{"v":1,"c":{"x":"<img src=x onerror=alert(1)>"},"t":' + now + '}') };
  for (const [name, val] of Object.entries(cases)) {
    page = await browser.newPage({ width: d.w, height: d.h, dpr: 1 });
    await page.setCookie(BASE, 'pixelite_consent', val);
    await page.goto(url('en', ''));
    const shown = await visible(page, '#cookie-banner');
    const injected = await page.eval('!!document.querySelector("img[src=x]")');
    ok(T(`cookie "${name}": ${name === 'script injection attempt' ? 'never rendered, valid cookie accepted' : 'banner is shown again'}`), name === 'script injection attempt' ? (!injected && !shown) : shown, `shown=${shown}`);
    ok(T(`cookie "${name}": no exception`), page.log.exceptions.length === 0, page.log.exceptions[0]);
    await page.close();
  }
  // 3) blocked storage: theme cycle must still reach Light and Dark; page must not break
  page = await browser.newPage({ width: d.w, height: d.h, dpr: 1 });
  await page.setCookie(BASE, 'pixelite_consent', cookieVal({ v: 1, c: {}, t: now }));
  await page.initScript(`Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('blocked', 'SecurityError'); } });`);
  await page.goto(url('en', ''));
  ok(T('blocked storage: page loads without errors'), page.log.exceptions.length === 0, page.log.exceptions[0]);
  await page.tap('[data-theme-cycle]'); const m1 = await page.eval('document.documentElement.getAttribute("data-theme")');
  await page.tap('[data-theme-cycle]'); const m2 = await page.eval('document.documentElement.getAttribute("data-theme")');
  ok(T('blocked storage: cycle reaches light then dark (not stuck)'), m1 === 'light' && m2 === 'dark', `${m1} -> ${m2}`);
  await page.close();
}

// Banner visible on first visit, across every device: layout, overlap, contrast, and menu reachability
async function bannerMatrix(browser) {
  for (const d of DEVICES) for (const theme of THEMES) for (const lang of LANGS) {
    const T = (s) => `[banner/${d.id}/${lang}/${theme}] ${s}`;
    const page = await freshPage(browser, d, theme, { consent: false });
    await page.goto(url(lang, ''));
    const a = await page.eval(AUDIT);
    ok(T('first visit: no horizontal overflow'), a.scrollW <= a.innerW && a.overflow.length === 0, `${a.scrollW}/${a.innerW} ${a.overflow.slice(0, 2)}`);
    ok(T('first visit: no overlaps in header'), a.overlaps.length === 0, a.overlaps.join(';'));
    const g = await page.eval(`(() => { const b = document.getElementById('cookie-banner'), r = b.getBoundingClientRect(); const kids = [...b.querySelectorAll('button')].map((e) => e.getBoundingClientRect()); return { vis: !b.hidden, within: kids.every((k) => k.left >= 0 && k.right <= innerWidth && k.bottom <= innerHeight + 1), h: r.height, vh: innerHeight, small: kids.filter((k) => Math.min(k.width, k.height) < 24).length }; })()`);
    ok(T('banner visible, all controls inside the viewport, no tiny targets'), g.vis && g.within && g.small === 0, JSON.stringify(g));
    ok(T('banner leaves at least 40% of the viewport for the page'), g.h <= g.vh * 0.6, `${Math.round(g.h)}/${g.vh}`);
    const c = await page.eval(CONTRAST);
    ok(T('contrast AA with banner open'), c.filter((x) => theme === 'dark' || x.startsWith('[new]')).length === 0, c.slice(0, 3).join('; '));
    if (d.w <= 1024) {   // the open mobile menu (incl. its theme switch) must stay reachable above the banner
      await page.tap('.navbar-toggle');
      const hit = await page.eval(`(() => { const b = document.querySelector('.header__el--theme .theme__btn'); const r = b.getBoundingClientRect(); const el = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return { onScreen: r.bottom <= innerHeight && r.top >= 0, inHeader: !!(el && el.closest('.header')) }; })()`);
      ok(T('mobile menu theme switch is reachable (not covered by the banner)'), hit.onScreen && hit.inHeader, JSON.stringify(hit));
    }
    await page.eval('document.querySelector("#cookie-banner [data-consent-settings]").click()');
    await sleep(150);
    const dg = await page.eval(`(() => { const r = document.getElementById('cookie-dialog').getBoundingClientRect(); return { open: document.getElementById('cookie-dialog').open, fits: r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight, sw: document.documentElement.scrollWidth <= innerWidth }; })()`);
    ok(T('settings dialog opens and fits the viewport'), dg.open && dg.fits && dg.sw, JSON.stringify(dg));
    const c2 = await page.eval(CONTRAST);
    ok(T('contrast AA with dialog open'), c2.filter((x) => theme === 'dark' || x.startsWith('[new]')).length === 0, c2.slice(0, 3).join('; '));
    ok(T('no console errors'), page.log.console.length === 0 && page.log.exceptions.length === 0, [...page.log.console, ...page.log.exceptions].join('|'));
    await page.close();
  }
}

async function themeAndNavScenarios(browser) {
  for (const d of [MOBILE, DESKTOP]) {
    const T = (s) => `[theme+nav/${d.id}] ${s}`;
    // system follows OS, explicit choice persists, no flash
    for (const os of ['light', 'dark']) {
      const page = await freshPage(browser, d, os);
      await page.goto(url('en', ''));
      const bg = await page.eval('getComputedStyle(document.body).backgroundColor');
      ok(T(`System theme follows OS (${os})`), os === 'dark' ? bg !== 'rgb(255, 255, 255)' : bg === 'rgb(255, 255, 255)', bg);
      ok(T(`no stored preference by default (${os})`), (await page.eval('localStorage.getItem("pixelite_theme")')) === null);
      await page.close();
    }
    let page = await freshPage(browser, d, 'light');
    await page.goto(url('en', ''));
    if (d.mobile) { await page.tap('.navbar-toggle'); }
    const group = d.mobile ? '.header__el--theme [data-theme-set="dark"]' : null;
    if (d.mobile) await page.tap(group); else await page.tap('[data-theme-cycle]');   // desktop cycle: system -> light
    if (!d.mobile) await page.tap('[data-theme-cycle]');                              // light -> dark
    ok(T('explicit Dark: data-theme=dark'), (await page.eval('document.documentElement.getAttribute("data-theme")')) === 'dark');
    ok(T('explicit Dark overrides a light OS'), (await page.eval('getComputedStyle(document.body).backgroundColor')) !== 'rgb(255, 255, 255)');
    ok(T('Dark stored in localStorage only after the click'), (await page.eval('localStorage.getItem("pixelite_theme")')) === 'dark');
    ok(T('theme-color meta follows theme'), (await page.eval('document.querySelector("meta[name=theme-color]").content')) === '#0a0f1a');
    await page.goto(url('cs', 'contact'));
    ok(T('Dark persists across pages and language'), (await page.eval('document.documentElement.getAttribute("data-theme")')) === 'dark');
    const firstScripts = await page.eval(`[...document.head.children].filter((e) => e.tagName === 'SCRIPT' || (e.tagName === 'LINK' && e.rel === 'stylesheet')).map((e) => e.tagName + ':' + (e.getAttribute('src') || e.getAttribute('href')).split('?')[0].split('/').pop())`);
    ok(T('theme-init.js is a blocking script before any stylesheet (no flash)'), firstScripts[0] === 'SCRIPT:theme-init.js', firstScripts.join(','));
    // footer group works with keyboard
    await page.eval('document.querySelector(\'.footer [data-theme-set="system"]\').focus()');
    await page.key('Enter');
    ok(T('keyboard: Enter on footer "System" button resets to system'), (await page.eval('document.documentElement.getAttribute("data-theme")')) === null && (await page.eval('localStorage.getItem("pixelite_theme")')) === null);
    ok(T('aria-pressed reflects the current mode'), (await page.eval('document.querySelector(\'.footer [data-theme-set="system"]\').getAttribute("aria-pressed")')) === 'true');
    await page.close();

    // reduced motion
    page = await freshPage(browser, d, 'light', { reducedMotion: true });
    await page.goto(url('en', ''));
    ok(T('prefers-reduced-motion removes transitions'), (await page.eval('getComputedStyle(document.querySelector(".btn")).transitionDuration')).split(',').every((x) => parseFloat(x) === 0));
    await page.close();

    // language switch
    page = await freshPage(browser, d, 'light');
    await page.goto(url('en', 'contact'));
    await page.tap('.lang__link[data-lang="cs"]'); await sleep(500);
    ok(T('language switch keeps the page and flips language'), (await page.eval('location.pathname')) === '/cs/contact' && (await page.eval('document.documentElement.lang')) === 'cs');
    ok(T('language choice stored in pixelite_lang'), (await cookieOf(page, 'pixelite_lang')) === 'cs');
    await page.close();

    // mobile menu
    if (d.mobile) {
      page = await freshPage(browser, d, 'light');
      await page.goto(url('en', ''));
      ok(T('menu is closed initially'), !(await visible(page, '#navbar')));
      await page.tap('.navbar-toggle');
      ok(T('menu toggle opens it (aria-expanded=true)'), (await visible(page, '#navbar')) && (await page.eval('document.querySelector(".navbar-toggle").getAttribute("aria-expanded")')) === 'true');
      const inside = await page.eval(`(() => { const r = document.getElementById('navbar').getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; })()`);
      ok(T('open menu fits the viewport'), inside);
      await page.key('Escape');
      ok(T('Esc closes the menu'), !(await visible(page, '#navbar')));
      await page.tap('.navbar-toggle'); await page.tap('#navbar a[href$="/contact"]'); await sleep(500);
      ok(T('menu link navigates'), (await page.eval('location.pathname')) === '/en/contact');
      await page.close();
    }
  }
}

async function formScenarios(browser) {
  for (const d of [MOBILE, DESKTOP]) for (const theme of ['light', 'dark']) {
    const T = (s) => `[order form/${d.id}/${theme}] ${s}`;
    const page = await freshPage(browser, d, theme);
    await page.goto(url('cs', 'order'));
    const fit = await page.eval(`(() => [...document.querySelectorAll('#order input:not([type=hidden]):not(.form__trap *), #order select, #order textarea, #order button')].filter((e) => !e.closest('.form__trap')).map((e) => e.getBoundingClientRect()).every((r) => r.left >= 0 && r.right <= innerWidth))()`);
    ok(T('all form controls fit the viewport'), fit);
    const h = await page.eval('Math.min(...[...document.querySelectorAll("#order input:not([type=hidden]), #order select, #order textarea, #order button")].filter((e) => !e.closest(".form__trap") && e.type !== "checkbox").map((e) => e.getBoundingClientRect().height))');
    ok(T(d.mobile ? 'inputs/buttons are tall enough to tap (>=44px)' : 'inputs/buttons are at least 32px tall'), h >= (d.mobile ? 44 : 32), `min height ${h}`);
    await sleep(2600);   // the form ignores submits faster than 2s after load (bot time-trap)
    await page.tap('#order button[type=submit]'); await sleep(900);
    ok(T('invalid submit: server shows a localized (Czech) error summary'), await page.eval('!!document.querySelector(".form__alert") && /zvýrazněná/.test(document.querySelector(".form__alert").textContent)'));
    ok(T('invalid submit: focus moves to the error summary'), await page.eval('document.activeElement && document.activeElement.hasAttribute("data-form-alert")'));
    ok(T('invalid fields are linked to their messages (aria-describedby)'), await page.eval('[...document.querySelectorAll("[aria-invalid=true]")].every((e) => document.getElementById(e.getAttribute("aria-describedby")))'));
    const a = await page.eval(AUDIT);
    ok(T('error state: no horizontal overflow'), a.scrollW <= a.innerW && a.overflow.length === 0, `${a.scrollW}/${a.innerW} ${a.overflow.slice(0, 2)}`);
    if (theme === 'dark') { const c = await page.eval(CONTRAST); ok(T('error state: text contrast AA (dark)'), c.length === 0, c.slice(0, 3).join('; ')); }
    const ce = page.log.console.filter((m) => !m.includes('status of 422')); ok(T('error state: no console errors'), ce.length === 0 && page.log.exceptions.length === 0, [...ce, ...page.log.exceptions].join('|'));
    if (SHOTS) await page.screenshot(path.join(OUT, `form-error_${d.id}_${theme}.png`));
    await page.close();
  }
}

// ================= B2) HERO LAPTOP =================
async function heroScenarios(browser) {
  for (const d of DEVICES) for (const lang of LANGS) {
    const T = (s) => `[hero/${d.id}/${lang}] ${s}`;
    const theme = d.id.length % 2 ? 'dark' : 'light';
    const page = await freshPage(browser, d, theme);
    await page.goto(url(lang, ''));
    const early = await page.eval(`(() => { const h = document.querySelector('.hero-laptop'), c = h.closest('.container').getBoundingClientRect(), r = h.getBoundingClientRect(), b = document.querySelector('.lp-base').getBoundingClientRect(), s = document.querySelector('.site__box-link').getBoundingClientRect(), main = document.querySelector('main').getBoundingClientRect();
      return { w: r.width, h: r.height, top: r.top + scrollY, left: r.left, right: r.right, bl: b.left, br: b.right, cl: c.left, cr: c.right, ctaBottom: s.bottom + scrollY, mainH: main.height, anims: document.querySelector('.lp-lid').getAnimations().length, img: document.querySelectorAll('img.site__img, img[src*="hero.png"]').length, ariaHidden: h.getAttribute('aria-hidden'), iframes: document.querySelectorAll('iframe').length, vw: innerWidth }; })()`);
    ok(T('static hero.png is gone from the hero'), early.img === 0);
    ok(T('no iframe / third-party embed'), early.iframes === 0);
    ok(T('laptop is decorative (aria-hidden)'), early.ariaHidden === 'true');
    ok(T('laptop base stays inside the viewport'), early.bl >= -0.5 && early.br <= early.vw + 0.5, `${early.bl}..${early.br} / ${early.vw}`);
    ok(T('laptop does not overlap the CTA buttons'), early.top > early.ctaBottom, `top ${early.top} cta ${early.ctaBottom}`);
    ok(T('laptop is not tiny/oversized'), early.w >= Math.min(280, early.vw - 40) && early.w <= 761, `w=${early.w}`);
    const ratio = early.w / early.h;
    ok(T('aspect ratio reserved (31.25:17.6)'), Math.abs(ratio - 31.25 / 17.6) < 0.02, ratio.toFixed(3));
    ok(T('opening animation is running on load'), early.anims >= 1, `anims=${early.anims}`);
    await sleep(2300);
    const late = await page.eval(`(() => { const h = document.querySelector('.hero-laptop').getBoundingClientRect(), main = document.querySelector('main').getBoundingClientRect(), lid = getComputedStyle(document.querySelector('.lp-lid')).transform, pg = getComputedStyle(document.querySelector('.lp-page')).opacity; return { w: h.width, h: h.height, mainH: main.height, lid, pg, anims: document.querySelector('.lp-lid').getAnimations().filter(a => a.playState === 'running').length, sw: document.documentElement.scrollWidth, iw: innerWidth }; })()`);
    ok(T('no layout shift while animating (size identical before/after)'), Math.abs(late.h - early.h) < 0.5 && Math.abs(late.w - early.w) < 0.5 && Math.abs(late.mainH - early.mainH) < 0.5, JSON.stringify([early.h, late.h, early.mainH, late.mainH]));
    ok(T('lid ends fully open (identity transform), content visible'), (late.lid === 'none' || late.lid === 'matrix(1, 0, 0, 1, 0, 0)' || /matrix3d\(1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1\)/.test(late.lid)) && late.pg === '1', `${late.lid} / ${late.pg}`);
    ok(T('animation finished (nothing keeps running)'), late.anims === 0);
    ok(T('no horizontal overflow after animation'), late.sw <= late.iw, `${late.sw}/${late.iw}`);
    ok(T('no console errors'), page.log.console.length === 0 && page.log.exceptions.length === 0);
    if (SHOTS && (d.id.includes('375') || d.id.includes('1440') || d.id.includes('320') || d.id.includes('768') || d.id.includes('1920'))) await page.screenshot(path.join(OUT, `hero_${d.id}_${lang}_${theme}.png`), { full: false });
    await page.close();
    // reduced motion: static, already open
    if (d.id === 'iphone-se-375' || d.id === 'desktop-1440') {
      const rm = await freshPage(browser, d, 'light', { reducedMotion: true });
      await rm.goto(url(lang, ''));
      const st = await rm.eval(`(() => ({ anims: document.querySelector('.lp-lid').getAnimations().length, lid: getComputedStyle(document.querySelector('.lp-lid')).transform, pg: getComputedStyle(document.querySelector('.lp-page')).opacity, scr: getComputedStyle(document.querySelector('.lp-screen')).backgroundColor }))()`);
      ok(T('prefers-reduced-motion: no animation, laptop shown open and complete'), st.anims === 0 && st.lid === 'none' && st.pg === '1' && st.scr === 'rgb(255, 255, 255)', JSON.stringify(st));
      await rm.close();
    }
  }
}

// ================= B2b) PRICING =================
const NB = '\u00a0';
const PRICING = {
  en: { names: ['Template website', 'Basic website', 'Custom website'], from: 'From', amounts: ['2,999', '32,900', '64,900'], cur: 'CZK', ids: ['template', 'basic', 'custom'],
        credits: ['Orders from 30,000 CZK qualify for up to $100 in Dreamers Ad Credits for advertising.', 'not a cash discount'], consult: 'Non-binding consultation',
        budgets: ['Up to 10,000 CZK', '10,000–30,000 CZK', '30,000–65,000 CZK', '65,000–100,000 CZK', '100,000+ CZK', 'Not sure yet'] },
  cs: { names: ['Web ze šablony', 'Základní web', 'Individuální web'], from: 'Od', amounts: [`2${NB}999`, `32${NB}900`, `64${NB}900`], cur: 'Kč', ids: ['template', 'basic', 'custom'],
        credits: [`od 30${NB}000${NB}Kč`, `až 100${NB}USD`, 'nikoli o slevu v hotovosti'], consult: 'Nezávazná konzultace',
        budgets: [`Do 10${NB}000${NB}Kč`, `10${NB}000–30${NB}000${NB}Kč`, `30${NB}000–65${NB}000${NB}Kč`, `65${NB}000–100${NB}000${NB}Kč`, `100${NB}000${NB}Kč a více`, 'Zatím nevím'] },
};
async function pricingScenarios(browser) {
  for (const d of DEVICES) for (const lang of LANGS) for (const theme of THEMES) {
    const T = (s) => `[pricing/${d.id}/${lang}/${theme}] ${s}`;
    const W = PRICING[lang];
    const page = await freshPage(browser, d, theme);
    await page.goto(url(lang, ''));
    const r = await page.eval(`(() => {
      const cards = [...document.querySelectorAll('.pricing-row .price-box')];
      const info = cards.map((c) => { const w = c.querySelector('.price-box__wrap').getBoundingClientRect(), p = c.querySelector('.price-box__discount'), b = c.querySelector('.price-box__btn a').getBoundingClientRect(); return {
        id: c.dataset.package, name: c.querySelector('.price-box__title').textContent.trim(), from: c.querySelector('.price-box__from').textContent.trim(), amount: c.querySelector('.price-box__amount').textContent.trim(), cur: c.querySelector('.price-box__discount--light').textContent.trim(),
        href: new URL(c.querySelector('.price-box__btn a').href).search, features: c.querySelectorAll('.price-box__list-el').length, note: c.querySelector('.price-box__note').textContent.trim().length > 20, vat: c.querySelector('.price-box__vat').textContent.trim(), vatClipped: c.querySelector('.price-box__vat').scrollWidth > c.querySelector('.price-box__vat').clientWidth + 1,
        left: w.left, right: w.right, h: w.height, priceClipped: p.scrollWidth > p.clientWidth + 1, priceLines: Math.round(p.getBoundingClientRect().height / parseFloat(getComputedStyle(p).lineHeight || 30)), btnIn: b.left >= 0 && b.right <= innerWidth, btnH: b.height }; });
      const cr = document.querySelector('.credits'), cons = document.getElementById('consultation'), last = cards[cards.length - 1].getBoundingClientRect();
      return { info, sw: document.documentElement.scrollWidth, iw: innerWidth,
        credits: cr ? cr.textContent.replace(/\\s+/g, ' ') : '', creditsRect: cr ? cr.getBoundingClientRect().toJSON() : null, consultTitle: cons ? cons.querySelector('h2').textContent.trim() : '', consultTop: cons ? cons.getBoundingClientRect().top + scrollY : 0,
        creditsTop: cr ? cr.getBoundingClientRect().top + scrollY : 0, cardsBottom: last.bottom + scrollY, ctaHref: cons ? new URL(cons.querySelector('a.btn').href).pathname : '',
        consultText: cons ? cons.querySelector('.consult__text').textContent.trim() : '' };
    })()`);
    ok(T('three packages'), r.info.length === 3 && r.info.map((x) => x.id).join() === 'template,basic,custom', JSON.stringify(r.info.map((x) => x.id)));
    r.info.forEach((c, i) => {
      ok(T(`${W.names[i]}: "${W.from} ${W.amounts[i]} ${W.cur}"`), c.name === W.names[i] && c.from === W.from && c.amount === W.amounts[i] && c.cur === W.cur, JSON.stringify(c));
      ok(T(`${c.id}: order link carries package=${W.ids[i]}`), c.href === `?package=${W.ids[i]}`, c.href);
      ok(T(`${c.id}: VAT line present (configured text or the visible placeholder), never empty or clipped`), c.vat.length > 5 && !c.vatClipped, JSON.stringify(c.vat));
      ok(T(`${c.id}: six features + conditional-price note`), c.features === 6 && c.note);
      ok(T(`${c.id}: card inside viewport`), c.left >= -0.5 && c.right <= r.iw + 0.5, `${c.left}..${c.right}/${r.iw}`);
      ok(T(`${c.id}: price not clipped and on at most 2 lines`), !c.priceClipped && c.priceLines <= 2, `lines=${c.priceLines}`);
      ok(T(`${c.id}: CTA inside viewport${d.mobile ? ' and >=44px tall' : ''}`), c.btnIn && (!d.mobile || c.btnH >= 44), `h=${c.btnH}`);
    });
    if (d.w >= 768) ok(T('cards are equal height (desktop/tablet)'), Math.max(...r.info.map((c) => c.h)) - Math.min(...r.info.map((c) => c.h)) < 1.5, JSON.stringify(r.info.map((c) => Math.round(c.h))));
    const ctr = await page.eval(`[...document.querySelectorAll('.pricing-row .price-box')].map((c) => { const w = c.querySelector('.price-box__wrap').getBoundingClientRect(), i = c.querySelector('.price-box__img').getBoundingClientRect(); return Math.abs((i.left + i.width / 2) - (w.left + w.width / 2)); })`);
    ok(T('card illustration (.price-box__img) is horizontally centred in every card'), ctr.length === 3 && ctr.every((x) => x < 1), JSON.stringify(ctr.map((x) => Math.round(x * 10) / 10)));
    ok(T('exactly one VAT line and one price per card; no customer-facing VAT control'), await page.eval(`(() => { const row = document.querySelector('.pricing-row'); return [...row.querySelectorAll('.price-box')].every((c) => c.querySelectorAll('.price-box__vat').length === 1 && c.querySelectorAll('.price-box__amount').length === 1) && row.querySelectorAll('input, select, textarea, button, [role=switch]').length === 0; })()`));
    if (d.w >= 768) {
      const al = await page.eval(`(() => { const top = (sel) => [...document.querySelectorAll('.pricing-row .price-box ' + sel)].map((e) => Math.round(e.getBoundingClientRect().top)); return ['.price-box__title', '.price-box__desc', '.price-box__discount', '.price-box__vat', '.price-box__feat', '.price-box__list'].map((k) => [k, new Set(top(k)).size === 1]); })()`);
      ok(T('card rows (title, text, price, VAT, heading, list) line up across the three cards'), al.every(([, same]) => same), JSON.stringify(al.filter(([, same]) => !same)));
    }
    ok(T('no horizontal overflow'), r.sw <= r.iw, `${r.sw}/${r.iw}`);
    ok(T('Dreamers Ad Credits strip: threshold, "up to", advertising credit not cash'), W.credits.every((x) => r.credits.includes(x.replaceAll(NB, ' '))) && r.credits.includes('Dreamers Ad Credits'), r.credits.slice(0, 160));
    ok(T('credits strip fits the viewport'), r.creditsRect.left >= 0 && r.creditsRect.right <= r.iw, JSON.stringify(r.creditsRect));
    ok(T('credits strip sits below the cards; consultation is a separate section below that'), r.creditsTop > r.cardsBottom - 1 && r.consultTop > r.creditsTop, `${r.cardsBottom}/${r.creditsTop}/${r.consultTop}`);
    ok(T(`consultation heading "${W.consult}" and text`), r.consultTitle === W.consult && r.consultText.length > 60, r.consultTitle);
    ok(T('consultation CTA leads to the order page'), r.ctaHref === `/${lang}/order`, r.ctaHref);
    // contrast of the pricing area (dark: everything; light: new components)
    const c = await page.eval(CONTRAST);
    ok(T('pricing area text contrast AA'), c.filter((x) => (theme === 'dark' || x.startsWith('[new]')) && /price-box|credits|consult/.test(x)).length === 0, c.filter((x) => /price-box|credits|consult/.test(x)).slice(0, 3).join('; '));
    if (SHOTS && ['375', '768', '1440'].some((k) => d.id.includes(k))) { await page.eval('document.getElementById("services").scrollIntoView(); 1'); await page.screenshot(path.join(OUT, `pricing_${d.id}_${lang}_${theme}.png`), { full: false }); }
    // order flow: CTA -> form preselects the type; budget options are CZK
    for (const [idx, id] of [[0, 'template'], [1, 'basic'], [2, 'custom']]) {
      if (theme !== 'light' || (d.id !== 'iphone-se-375' && d.id !== 'desktop-1440')) break;
      await page.goto(url(lang, ''));
      await page.tap(`.pricing-row .price-box:nth-child(${idx + 1}) .price-box__btn a`); await sleep(900);
      const f = await page.eval(`(() => ({ path: location.pathname + location.search, pkg: document.getElementById('f-package').value, pkgText: document.getElementById('f-package').selectedOptions[0].textContent, type: document.getElementById('f-project_type').value, pkgOpts: document.getElementById('f-package').options.length, pkgRequired: document.getElementById('f-package').required, opts: [...document.querySelectorAll('#f-budget option')].slice(1).map((o) => o.textContent), eur: document.body.textContent.includes('€'), fits: [...document.querySelectorAll('#f-package, #f-project_type')].every((e) => { const r = e.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; }), sw: document.documentElement.scrollWidth <= innerWidth }))()`);
      ok(T(`CTA ${idx + 1} opens /order?package=${id} with that package preselected`), f.path === `/${lang}/order?package=${id}` && f.pkg === id, JSON.stringify(f));
      ok(T('package label shows the name and the starting price'), f.pkgText.includes(W.names[idx]) && f.pkgText.includes(W.amounts[idx]) && f.pkgText.includes(W.cur), f.pkgText);
      ok(T('project type is a separate field (Website), package select is optional with 4 options'), f.type === 'website' && f.pkgOpts === 4 && f.pkgRequired === false, JSON.stringify([f.type, f.pkgOpts, f.pkgRequired]));
      ok(T('package + project type controls fit the viewport, no overflow'), f.fits && f.sw);
      ok(T('budget options are the six CZK ranges, no euros'), JSON.stringify(f.opts) === JSON.stringify(W.budgets) && !f.eur, JSON.stringify(f.opts));
    }
    await page.close();
  }
}

// ================= B3) STORAGE AUDIT =================
// What the browser really ends up holding vs. what the cookie policy documents.
async function storageAudit(browser) {
  const T = (s) => `[storage] ${s}`;
  const d = DESKTOP;
  const doc = await (await fetch(url('en', 'cookies'))).text();
  const documented = new Set([...doc.matchAll(/<code>([^<]+)<\/code>/g)].map((m) => m[1]));
  ok(T('cookie policy documents exactly 4 items'), documented.size === 4 && ['pixelite', 'pixelite_consent', 'pixelite_lang', 'pixelite_theme'].every((n) => documented.has(n)), [...documented].join(','));
  const seen = new Set();
  const track = (st) => { st.cookies.forEach((c) => seen.add(c.name)); Object.keys(st.localStorage).forEach((k) => seen.add(k)); };

  // 1) plain visits store NOTHING (banner shown, no choice yet)
  let page = await browser.newPage({ width: d.w, height: d.h });
  for (const lang of LANGS) for (const p of ['', 'contact', 'privacy', 'cookies', 'terms', 'portfolio', 'zzz-404']) {
    await page.goto(url(lang, p)); const st = await page.storage(); track(st);
    ok(T(`/${lang}/${p}: nothing stored on a plain first visit`), st.cookies.length === 0 && Object.keys(st.localStorage).length === 0 && st.sessionStorage.length === 0 && st.indexedDB.length === 0 && st.caches.length === 0 && st.serviceWorkers === 0, JSON.stringify(st));
  }
  // 2) order form -> only the session cookie
  await page.goto(url('en', 'order')); let st = await page.storage(); track(st);
  const sc = st.cookies.find((c) => c.name === 'pixelite');
  ok(T('order form: exactly one cookie, "pixelite"'), st.cookies.length === 1 && !!sc, JSON.stringify(st.cookies));
  ok(T('session cookie: session-only, HttpOnly, SameSite=Lax, path=/'), sc && sc.session === true && sc.httpOnly === true && sc.sameSite === 'Lax' && sc.path === '/', JSON.stringify(sc));
  await page.close();
  page = await browser.newPage({ width: d.w, height: d.h });
  await page.goto(`${BASE}/admin/login`); st = await page.storage(); track(st);
  ok(T('staff login: only the session cookie'), st.cookies.length === 1 && st.cookies[0].name === 'pixelite' && st.cookies[0].httpOnly, JSON.stringify(st.cookies));
  await page.close();
  // 3) explicit actions create exactly the documented items
  page = await browser.newPage({ width: d.w, height: d.h });
  await page.goto(url('en', ''));
  await page.tap('.lang__link[data-lang="cs"]'); await sleep(700);
  st = await page.storage(); track(st); const lc = st.cookies.find((c) => c.name === 'pixelite_lang');
  ok(T('language click: pixelite_lang for ~1 year, Lax, JS-set'), !!lc && lc.days > 360 && lc.days < 366 && lc.sameSite === 'Lax' && lc.httpOnly === false, JSON.stringify(lc));
  ok(T('language click: nothing else stored'), st.cookies.length === 1 && Object.keys(st.localStorage).length === 0, JSON.stringify(st));
  await page.tap('[data-theme-cycle]'); await page.tap('[data-theme-cycle]');
  st = await page.storage(); track(st);
  ok(T('theme choice: localStorage pixelite_theme only (value light|dark)'), Object.keys(st.localStorage).join() === 'pixelite_theme' && ['light', 'dark'].includes(st.localStorage.pixelite_theme), JSON.stringify(st.localStorage));
  await page.tap('[data-theme-cycle]');   // back to System
  st = await page.storage(); ok(T('choosing System removes the stored theme'), Object.keys(st.localStorage).length === 0, JSON.stringify(st.localStorage));
  await page.tap('#cookie-banner [data-consent-accept]');
  st = await page.storage(); track(st); const cc = st.cookies.find((c) => c.name === 'pixelite_consent');
  ok(T('banner choice: pixelite_consent for ~180 days, Lax'), !!cc && cc.days > 178 && cc.days < 181 && cc.sameSite === 'Lax', JSON.stringify(cc));
  const val = JSON.parse(decodeURIComponent(await page.eval('(document.cookie.match(/pixelite_consent=([^;]*)/)||[])[1]')));
  ok(T('consent cookie holds only version, categories and timestamp (no identifiers)'), Object.keys(val).sort().join() === 'c,t,v', JSON.stringify(val));
  ok(T('nothing but the 3 documented cookies + 1 localStorage key was EVER stored'), [...seen].every((n) => documented.has(n)), [...seen].join(','));
  // 4) the documented lifetimes are the ones in the policy text
  ok(T('policy text matches observed lifetimes (session / 6 months / 1 year)'), /Until you close the browser/.test(doc) && /6 months/.test(doc) && /1 year/.test(doc));
  await page.close();
}

// ================= C) HTTP / exposure =================
async function httpChecks() {
  const get = async (p, init = {}) => fetch(BASE + p, { redirect: 'manual', ...init });
  ok('admin requires login (redirect)', (await get('/admin')).status === 302);
  ok('order detail requires login', (await get('/admin/orders/1')).status === 302);
  for (const p of ['/.env', '/.env.example', '/src/Env.php', '/storage/leads.sqlite', '/storage/logs/app.log', '/config/consent.php', '/lang/en.php', '/templates/layout.php', '/tests/run.php', '/CLAUDE.md', '/Dockerfile', '/bin/hash-password.php', '/.git/config', '/iconified/favicon.ico', '/favicon.svg'])
    ok(`private file not served: ${p}`, [404, 403].includes((await get(p)).status), String((await get(p)).status));
  for (const p of ['/favicon.ico', '/assets/icons/favicon.svg', '/assets/icons/apple-touch-icon.png', '/assets/theme-init.js', '/assets/theme.js', '/assets/consent.js', '/assets/script.js', '/assets/style.css']) {
    const r = await get(p); ok(`asset served: ${p}`, r.status === 200 && Number(r.headers.get('content-length') || 1) > 0, String(r.status));
  }
  ok('favicon.svg served as image/svg+xml', (await get('/assets/icons/favicon.svg')).headers.get('content-type')?.startsWith('image/svg+xml'));
  ok('favicon.ico is a real .ico', Buffer.from(await (await get('/favicon.ico')).arrayBuffer()).readUInt16LE(2) === 1);
  const vm = await import('node:vm');
  for (const f of ['script.js', 'theme-init.js', 'theme.js', 'consent.js']) {
    let err = ''; try { new vm.Script(await (await get('/assets/' + f)).text()); } catch (e) { err = String(e.message); }
    ok(`JS parses: ${f}`, err === '', err);
  }
  const sm = await (await get('/sitemap.xml')).text();
  const locs = [...sm.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => new URL(m[1]).pathname);
  ok('sitemap lists the 6 public pages x 2 languages', locs.length === 12, String(locs.length));
  ok('sitemap excludes portfolio/admin/sent/404', !locs.some((l) => /portfolio|admin|sent|zzz/.test(l)));
  ok('sitemap includes cookies and terms', locs.includes('/en/cookies') && locs.includes('/cs/terms'));
  const rb = await (await get('/robots.txt')).text(); ok('robots.txt blocks /admin and points to sitemap', /Disallow: \/admin/.test(rb) && /Sitemap:/.test(rb));
  const hdr = (await get('/en/')).headers;
  ok('CSP present and has no unsafe-inline', /default-src 'self'/.test(hdr.get('content-security-policy') || '') && !/unsafe-inline/.test(hdr.get('content-security-policy') || ''));
  ok('no cookie set for a plain visit', !hdr.get('set-cookie'));
  // exposure: nothing sensitive in served front-end assets
  const env = Object.fromEntries(fs.readFileSync(new URL('../../.env', import.meta.url).pathname, 'utf8').split('\n').filter((l) => l.includes('=') && !l.startsWith('#')).map((l) => [l.split('=')[0], l.slice(l.indexOf('=') + 1).replace(/^['"]|['"]$/g, '')]));
  const secrets = ['TELEGRAM_BOT_TOKEN', 'ADMIN_PASSWORD_HASH'].map((k) => env[k]).filter(Boolean);
  const bodies = [];
  for (const p of ['/en/', '/cs/', '/en/order', '/en/cookies', '/en/privacy', '/en/terms', '/assets/script.js', '/assets/theme.js', '/assets/theme-init.js', '/assets/consent.js', '/assets/style.css']) bodies.push(await (await get(p)).text());
  ok('no secret value in any served HTML/JS/CSS', !bodies.some((b) => secrets.some((s) => s.length > 8 && b.includes(s))));
  ok('front-end JS contains no personal data / emails / tokens', !bodies.slice(6, 10).some((b) => /@[a-z0-9.-]+\.[a-z]{2,}|\d{6,}:[A-Za-z0-9_-]{20,}/i.test(b)));
}

// ---------- run ----------
const t0 = Date.now();
const browser = await launch();
let loads = 0;
try {
  await httpChecks();
  loads = await matrix(browser);
  await consentScenarios(browser);
  await consentGateScenarios(browser);
  await bannerMatrix(browser);
  await themeAndNavScenarios(browser);
  await formScenarios(browser);
  await heroScenarios(browser);
  await pricingScenarios(browser);
  await storageAudit(browser);
} finally { await browser.close(); }

console.log(`\nBrowser matrix: ${DEVICES.length} devices x ${LANGS.length} languages x ${THEMES.length} themes -> ${loads} page loads, ${((Date.now() - t0) / 1000).toFixed(0)}s`);
console.log(`Devices: ${DEVICES.map((d) => `${d.id}(${d.w}x${d.h}@${d.dpr}${d.mobile ? ',touch' : ''})`).join(' ')}`);
if (advisories.size) { console.log('\nAdvisory (not failures):'); for (const [k, v] of advisories) console.log(`  - ${k}: ${v.size} distinct\n      ${[...v].slice(0, 6).join('\n      ')}`); }
if (failures.length) { console.log(`\nFAILURES (${failures.length}):`); for (const f of failures.slice(0, 60)) console.log('  ✗ ' + f); }
console.log(`\n${passed} checks passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
