// New-design browser suite (real Chrome via the repo's dependency-free CDP driver).
//   node tests/browser/new-design.mjs [--base http://localhost:8080] [--quick] [--shots <dir>] [--allow-submit]
// Default design = the new one. Covers: page matrix (devices x 4 languages x light/dark), menu, language + theme switch, the Legacy menu item both ways,
// notebook animation, gallery/detail/order journeys, cookie dialog, protected routes, exposure and header checks.
// --allow-submit also sends ONE real test order: only against a container whose Telegram is disabled (never against a developer's real .env).
// Admin/customer/project/preview flows live in tests/browser/platform.mjs.
import fs from 'node:fs';
import path from 'node:path';
import { launch } from './cdp.mjs';

const args = process.argv.slice(2);
const opt = (n, d) => (args.includes(n) ? args[args.indexOf(n) + 1] : d);
const BASE = opt('--base', 'http://localhost:8080');
const SUBMIT = args.includes('--allow-submit');
const SHOTS = opt('--shots', '');
const QUICK = args.includes('--quick');
if (SHOTS) fs.mkdirSync(SHOTS, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const CONSENT = encodeURIComponent(JSON.stringify({ v: 1, c: {}, t: Math.floor(Date.now() / 1000) }));

let passed = 0, failed = 0; const failures = [];
const ok = (name, cond, detail = '') => { if (cond) passed++; else { failed++; failures.push(name + (detail ? ' — ' + detail : '')); } };

const LANGS = ['en', 'cs', 'ru', 'uk'];
const DEVICES = [
  { id: '320', w: 320, h: 568, dpr: 2, mobile: true },
  { id: '375', w: 375, h: 667, dpr: 2, mobile: true, quick: true },
  { id: '390', w: 390, h: 844, dpr: 3, mobile: true },
  { id: '768', w: 768, h: 1024, dpr: 2, mobile: true, quick: true },
  { id: '1024', w: 1024, h: 768, dpr: 1, mobile: false },
  { id: '1100', w: 1100, h: 800, dpr: 1, mobile: false },
  { id: "1280", w: 1280, h: 800, dpr: 1, mobile: false, quick: true },
  { id: '1440', w: 1440, h: 900, dpr: 1, mobile: false, quick: true },
].filter((d) => !QUICK || d.quick);
const PAGES = [
  ['home', '', 200], ['pricing', 'pricing', 200], ['process', 'process', 200], ['contact', 'contact', 200], ['privacy', 'privacy', 200],
  ['cookies', 'cookies', 200], ['terms', 'terms', 200], ['portfolio', 'portfolio', 200], ['templates', 'templates', 200],
  ['template', 'templates/lumora', 200], ['order', 'order?package=basic&template=lumora', 200], ['team', 'team', 200], ['careers', 'careers', 200], ['login', 'login', 200], ['register', 'register', 200], ['contact-design', 'contact?template=lumora', 200], ['404', 'zzz-nope', 404],
];

const AUDIT = `(() => {
  const W = innerWidth, o = { over: [], noalt: [], h1: document.querySelectorAll('h1').length, sw: document.documentElement.scrollWidth, W,
    unlabeled: [], dupIds: [], small: [] };
  const vis = (e) => { const r = e.getBoundingClientRect(), c = getComputedStyle(e); return r.width > 0 && r.height > 0 && c.visibility !== 'hidden' && c.display !== 'none'; };
  const skip = (e) => e.closest('.skip-link,.form__trap,[hidden],.sr-only,.sheet:not(.is-open),dialog:not([open]),.compare-wrap,.table-wrap,.hero__canvas,.tpreview__stage');
  for (const e of document.querySelectorAll('body *')) {
    if (skip(e) || !vis(e)) continue;
    const r = e.getBoundingClientRect(), c = getComputedStyle(e);
    if (c.position !== 'fixed' && r.right > W + 1 && !e.closest('.hero-laptop')) o.over.push(e.tagName.toLowerCase() + '.' + String(e.className).split(' ')[0] + ' r=' + Math.round(r.right));
  }
  for (const i of document.querySelectorAll('img')) if (!i.hasAttribute('alt')) o.noalt.push(i.src);
  for (const f of document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]),select,textarea')) {
    if (skip(f)) continue; if (!(f.id && document.querySelector('label[for="' + f.id + '"]')) && !f.getAttribute('aria-label')) o.unlabeled.push(f.name || f.id);
  }
  const ids = {}; for (const e of document.querySelectorAll('[id]')) { ids[e.id] = (ids[e.id] || 0) + 1; } o.dupIds = Object.keys(ids).filter((k) => ids[k] > 1);
  for (const e of document.querySelectorAll('a[href],button,select,input:not([type=hidden]),summary')) {
    if (skip(e) || !vis(e) || e.closest('.hero-laptop')) continue; const r = e.getBoundingClientRect();
    if (e.tagName === 'A' && getComputedStyle(e).display === 'inline') continue;
    if (r.height < 32 || r.width < 32) o.small.push((e.tagName + ':' + (e.textContent || e.name || '').trim().slice(0, 18)) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height));
  }
  return o; })()`;

const chrome = await launch();
try {
  /* ---------- 1. page matrix ---------- */
  const advisories = new Set();
  for (const d of DEVICES) for (const lang of LANGS) for (const scheme of ['light', 'dark']) {
    const page = await chrome.newPage({ width: d.w, height: d.h, dpr: d.dpr, mobile: d.mobile, scheme });
    await page.setCookie(BASE, 'pixelite_consent', CONSENT);
    for (const [key, p, status] of PAGES) {
      const tag = `[${d.id}/${lang}/${scheme}/${key}]`;
      await page.goto(`${BASE}/${lang}/${p}`); await sleep(350);
      const a = await page.eval(AUDIT);
      ok(`${tag} no horizontal scroll`, a.sw <= a.W + 1, `scrollWidth=${a.sw} > ${a.W}`);
      ok(`${tag} nothing past the viewport edge`, a.over.length === 0, a.over.slice(0, 3).join('; '));
      ok(`${tag} one h1`, a.h1 === 1, `h1=${a.h1}`);
      ok(`${tag} images have alt`, a.noalt.length === 0, a.noalt.join(','));
      ok(`${tag} fields labelled`, a.unlabeled.length === 0, a.unlabeled.join(','));
      ok(`${tag} unique ids`, a.dupIds.length === 0, a.dupIds.join(','));
      const bad = page.log.badResponses.filter((r) => !(status === 404 && r.url.endsWith('/' + p)));
      ok(`${tag} no failed/4xx/5xx requests`, bad.length === 0 && page.log.failed.length === 0, JSON.stringify(bad.concat(page.log.failed)).slice(0, 200));
      const cons = page.log.console.filter((m) => !(status === 404 && m.includes('status of 404')));
      ok(`${tag} no console errors/exceptions/CSP violations`, cons.length === 0 && page.log.exceptions.length === 0, (cons.concat(page.log.exceptions))[0]?.slice(0, 200));
      if (a.small.length) advisories.add(`${tag} small targets: ${a.small.slice(0, 3).join(', ')}`);
      if (SHOTS && ['home', 'pricing', 'templates', 'template', 'order', 'terms'].includes(key) && lang === 'en' && ['390', '1440'].includes(d.id)) {
        await page.screenshot(path.join(SHOTS, `${key}-${d.id}-${scheme}.png`));
      }
    }
    await page.close();
  }

  /* ---------- 2. navigation, language, theme, concept switch ---------- */
  {
    const page = await chrome.newPage({ width: 390, height: 844, dpr: 2, mobile: true });
    await page.setCookie(BASE, 'pixelite_consent', CONSENT);
    await page.goto(`${BASE}/en/pricing`); await sleep(300);
    ok('mobile: menu closed initially', await page.eval(`!document.getElementById('navbar').classList.contains('is-open') && document.querySelector('[data-nav-toggle]').getAttribute('aria-expanded')==='false'`));
    await page.tap('[data-nav-toggle]'); await sleep(300);
    ok('mobile: menu opens, aria-expanded=true, body locked', await page.eval(`document.getElementById('navbar').classList.contains('is-open') && document.querySelector('[data-nav-toggle]').getAttribute('aria-expanded')==='true' && document.body.classList.contains('has-sheet')`));
    ok('mobile: focus moves into the menu', await page.eval(`document.getElementById('navbar').contains(document.activeElement)`));
    await page.key('Escape'); await sleep(300);
    ok('mobile: Escape closes the menu and returns focus to the toggle', await page.eval(`!document.getElementById('navbar').classList.contains('is-open') && document.activeElement===document.querySelector('[data-nav-toggle]')`));
    await page.tap('[data-nav-toggle]'); await sleep(250);
    await page.tap('#navbar .sheet__link[href$="/contact"]'); await sleep(600);
    ok('mobile: menu link navigates', (await page.eval('location.pathname')) === '/en/contact');
    // language switch keeps the page
    await page.goto(`${BASE}/en/process`); await sleep(200);
    ok('language links keep the same page', await page.eval(`[...document.querySelectorAll('.pill .lang__link')].map(a=>new URL(a.href).pathname).join()`) === '/en/process,/cs/process,/ru/process,/uk/process');
    // Legacy website: a discreet item in the mobile menu; opens the legacy design on the corresponding page and language, and the way back works
    for (const [lang, p, label, back] of [['en', 'pricing', 'Legacy website', 'New design'], ['cs', 'process', 'Původní web', 'Nový design'], ['ru', 'contact', 'Прежняя версия сайта', 'Новый дизайн'], ['uk', 'templates/lumora', 'Попередня версія сайту', 'Новий дизайн']]) {
      await page.goto(`${BASE}/${lang}/${p}`); await sleep(300);
      await page.tap('[data-nav-toggle]'); await sleep(350);
      const item = await page.eval(`(() => { const a = document.querySelector('#navbar [data-design-switch]'); const r = a.getBoundingClientRect(); return { text: a.textContent.trim(), href: a.getAttribute('href'), vis: r.width > 0 && r.height > 0, size: parseFloat(getComputedStyle(a).fontSize) }; })()`);
      ok(`mobile menu (${lang}): discreet localized "${label}" item linking to /legacy/${lang}/${p}`, item.text === label && item.href === `/legacy/${lang}/${p}` && item.vis && item.size <= 16, JSON.stringify(item));
      await page.tap('#navbar [data-design-switch]'); await sleep(1500);
      const L = await page.eval(`({ path: location.pathname, css: !!document.querySelector('link[href*="/assets/style.css"]'), newcss: !!document.querySelector('link[href*="/assets/site.css"]'), lang: document.documentElement.lang, robots: document.querySelector('meta[name=robots]').content, canon: document.querySelector('link[rel=canonical]').getAttribute('href') })`);
      ok(`legacy design opens (${lang}) on the same page and language, noindex,follow, canonical = the new-design URL`, L.path === `/legacy/${lang}/${p}` && L.css && !L.newcss && L.lang === lang && L.robots === 'noindex,follow' && L.canon.endsWith(`/${lang}/${p}`) && !L.canon.includes('/legacy'), JSON.stringify(L));
      ok(`legacy design (${lang}): no console errors, no failed requests`, page.log.console.length === 0 && page.log.exceptions.length === 0 && page.log.failed.length === 0 && page.log.badResponses.length === 0, JSON.stringify([...page.log.console, ...page.log.badResponses]).slice(0, 200));
      const wasOpen = await page.eval(`(() => { const t = document.querySelector('.navbar-toggle, [data-nav-toggle]'); t.click(); return true; })()`); await sleep(400);
      const bk = await page.eval(`(() => { const a = document.querySelector('[data-design-switch]'); return { text: a.textContent.trim(), href: a.getAttribute('href') }; })()`);
      ok(`legacy design (${lang}): "${back}" way back, same page`, bk.text === back && bk.href === `/${lang}/${p}`, JSON.stringify(bk));
      await page.eval(`document.querySelector('[data-design-switch]').click(); true`); await sleep(1500);
      const N = await page.eval(`({ path: location.pathname, newcss: !!document.querySelector('link[href*="/assets/site.css"]'), robots: document.querySelector('meta[name=robots]').content })`);
      ok(`returning to the new design works (${lang}) – no redirect loop`, N.path === `/${lang}/${p}` && N.newcss && /^index/.test(N.robots), JSON.stringify(N));
    }
    await page.close();
  }
  {
    const page = await chrome.newPage({ width: 1440, height: 900, dpr: 1, scheme: 'light' });
    await page.setCookie(BASE, 'pixelite_consent', CONSENT);
    await page.goto(`${BASE}/en/`); await sleep(300);
    await page.tap('[data-theme-cycle]'); await sleep(200);
    ok('theme cycle light→(system→light→dark) changes data-theme', await page.eval(`document.documentElement.getAttribute('data-theme')==='light'`));
    await page.tap('[data-theme-cycle]'); await sleep(200);
    ok('theme cycle reaches dark', await page.eval(`document.documentElement.getAttribute('data-theme')==='dark'`));
    ok('desktop: nav links present, aria-current on Home', await page.eval(`document.querySelectorAll('.pill__link').length===7 && document.querySelector('.pill__link[aria-current]').getAttribute('href')==='/en/' && document.querySelector('.pill__link--version').getAttribute('href')==='/legacy/en/'`));
    await page.tap('.pill__link--version'); await sleep(1500);
    ok('desktop: the Legacy item opens /legacy/en/ (legacy design) and the legacy header offers "New design"', (await page.eval('location.pathname')) === '/legacy/en/' && await page.eval(`document.querySelector('[data-design-switch]').getAttribute('href')==='/en/'`));
    await page.tap('[data-design-switch]'); await sleep(1500);
    ok('desktop: …and back to the new design', (await page.eval('location.pathname')) === '/en/' && await page.eval(`!!document.querySelector('link[href*="site.css"]')`));
    await page.close();
  }

  /* ---------- 3. notebook animation ---------- */
  for (const [label, rm, w] of [['desktop', false, 1440], ['narrow 320', false, 320], ['reduced motion', true, 390]]) {
    const page = await chrome.newPage({ width: w, height: 800, dpr: 2, mobile: w < 700, reducedMotion: rm });
    await page.setCookie(BASE, 'pixelite_consent', CONSENT);
    await page.goto(`${BASE}/en/`); await sleep(100);
    const early = await page.eval(`(() => { const l = document.querySelector('.lp-lid'); const c = getComputedStyle(l); return {name:c.animationName, tf:c.transform}; })()`);
    if (rm) ok('reduced motion: no lid animation (open end state)', early.name === 'none');
    else {
      ok(`${label}: lid animation defined`, early.name === 'lp-open');
      await sleep(2800);
      const end = await page.eval(`(() => { const l = document.querySelector('.lp-lid'); const r = document.querySelector('.hero-laptop').getBoundingClientRect(); return {tf:getComputedStyle(l).transform, left:r.left, right:r.right, W:innerWidth, running:document.getAnimations().filter(a=>a.playState==='running').length}; })()`);
      ok(`${label}: notebook fits the viewport`, end.left >= -1 && end.right <= end.W + 1, JSON.stringify(end));
      ok(`${label}: animation finishes (no endless loops)`, end.running === 0 || end.running <= 1, 'running=' + end.running);
    }
    ok(`${label}: no layout shift of the laptop box`, await page.eval(`(() => { const r = document.querySelector('.hero-laptop').getBoundingClientRect(); return Math.abs(r.width / r.height - 31.25 / 18.4) < 0.02; })()`));
    if (SHOTS && !rm) await page.screenshot(path.join(SHOTS, `notebook-${w}.png`), { full: false });
    await page.close();
  }

  /* ---------- 4. gallery, detail, preview, order journeys ---------- */
  {
    const page = await chrome.newPage({ width: 1440, height: 900, dpr: 1 });
    await page.setCookie(BASE, 'pixelite_consent', CONSENT);
    await page.goto(`${BASE}/en/templates?q=zzzzqq`); await sleep(250);
    ok('gallery: empty state for no match', await page.eval(`!!document.querySelector('.tempty') && document.querySelectorAll('.tcard').length===0`));
    await page.goto(`${BASE}/en/templates?q=lumora`); await sleep(250);
    ok('gallery: search finds Lumora, status line updates', await page.eval(`document.querySelectorAll('.tcard').length===1 && /1/.test(document.querySelector('.tresults').textContent)`));
    await page.tap('.tcard__title a'); await sleep(1800);
    ok('gallery: card opens the right detail page', (await page.eval('location.pathname')) === '/en/templates/lumora');
    ok('detail: iframe points at the same-origin demo', await page.eval(`document.querySelector('.tpreview__frame')?.getAttribute('src')==='/en/templates/lumora/demo'`));
    await page.tap('[data-size="phone"]'); await sleep(300);
    ok('detail: Phone toggle resizes the frame to 390px', await page.eval(`document.querySelector('.tpreview__frame').style.width==='390px' && document.querySelector('[data-size="phone"]').getAttribute('aria-pressed')==='true'`));
    ok('detail: price line from the pricing data (From … CZK)', await page.eval(`/2[, \\u00a0]?999/.test(document.querySelector('.tprice__line').textContent)`));
    await page.tap('.tdetail__cta'); await sleep(1800);
    ok('order: design preselected from the detail page', await page.eval(`location.pathname==='/en/order' && document.getElementById('f-template').value==='lumora' && !document.querySelector('[data-template-thumb]').hidden`));
    await page.goto(`${BASE}/cs/order?package=custom`); await sleep(250);
    ok('order: ?package preselects package + Website type', await page.eval(`document.getElementById('f-package').value==='custom' && document.getElementById('f-project_type').value==='website'`));
    // validation
    await page.goto(`${BASE}/en/order`); await sleep(2000);   // past the minimum fill time
    await page.eval(`document.getElementById('f-name').value='  '; true`);
    await page.tap('#order button[type=submit]'); await sleep(900);
    ok('order: invalid submit shows the error summary and field errors, keeps the page', await page.eval(`!!document.querySelector('[data-form-alert]') && document.querySelectorAll('.form__error').length>0 && document.querySelector('[aria-invalid="true"]')!==null && location.pathname==='/en/order'`));
    ok('order: error summary receives focus', await page.eval(`document.activeElement===document.querySelector('[data-form-alert]')`));
    if (SUBMIT) {
    await page.goto(`${BASE}/en/order?template=lumora`); await sleep(1800);   // minimum fill time
    await page.eval(`(() => { const set=(i,v)=>{document.getElementById(i).value=v;}; set('f-name','Concept Test'); set('f-email','concept-test@example.com'); set('f-description','Automated concept test order – safe to delete.');
      const sel=(i)=>{const s=document.getElementById(i); s.value=[...s.options].find(o=>o.value&&!o.disabled).value;}; sel('f-budget'); sel('f-timeframe'); document.getElementById('f-consent').checked=true; return true; })()`);
    await page.tap('#order button[type=submit]'); await sleep(1500);
    ok('order: valid submit → 303 → success page', (await page.eval('location.pathname')) === '/en/order/sent', await page.eval('location.pathname'));
    ok('order: success page has one h1 and a way back', await page.eval(`document.querySelectorAll('h1').length===1 && !!document.querySelector('main a.btn')`));
    } else { console.log('  (valid order submit skipped: pass --allow-submit against a container with Telegram disabled)'); }
    await page.close();
  }

  /* ---------- 5. cookies dialog, FAQ, protected area ---------- */
  {
    const page = await chrome.newPage({ width: 1280, height: 800, dpr: 1 });
    await page.goto(`${BASE}/en/`); await sleep(500);
    ok('consent banner shows when no choice exists', await page.eval(`!document.getElementById('cookie-banner').hidden`));
    await page.tap('[data-consent-settings]'); await sleep(400);
    ok('cookie settings dialog opens (modal) and Esc closes it', await page.eval(`document.getElementById('cookie-dialog').open`));
    await page.key('Escape'); await sleep(300);
    ok('dialog closed', await page.eval(`!document.getElementById('cookie-dialog').open`));
    await page.tap('[data-consent-reject]'); await sleep(300);
    ok('Reject non-essential hides the banner', await page.eval(`document.getElementById('cookie-banner').hidden`));
    await page.goto(`${BASE}/en/pricing`); await sleep(300);
    await page.tap('.faq__q'); await sleep(200);
    ok('FAQ accordion opens', await page.eval(`document.querySelector('.faq__item').open`));
    await page.goto(`${BASE}/en/cookies#settings`); await sleep(600);
    ok('/cookies#settings opens the dialog', await page.eval(`document.getElementById('cookie-dialog').open`));
    await page.close();
  }
  for (const p of ['/admin', '/admin/templates', '/admin/orders/1', '/admin/templates/lumora']) {
    const r = await fetch(BASE + p, { redirect: 'manual' });
    ok(`protected: ${p} redirects anonymous users to login`, r.status === 302 && (r.headers.get('location') || '').endsWith('/admin/login'), `${r.status}`);
  }
  {
    const r = await fetch(BASE + '/admin/login'); const t = await r.text();
    ok('admin login: credentials are configured', !/not configured/.test(t));
    ok('admin login: renders, noindex, no-store', r.status === 200 && /noindex/.test(t) && /no-store/.test(r.headers.get('cache-control') || ''));
  }
  {
    const t = await (await fetch(`${BASE}/en/`)).text();
    ok('header CTA has a single arrow (disc only)', !/pill__cta[^>]*>(?:(?!<\/a>).)*→/s.test(t));
  }
  for (const p of ['/.env', '/src/bootstrap.php', '/storage/leads.sqlite', '/assets/../.env', '/templates/pages/home.php', '/private/', '/lang/en.php']) {
    const r = await fetch(BASE + p, { redirect: 'manual' }); const t = await r.text();
    ok(`exposure: ${p} not served`, r.status !== 200 || (!/TELEGRAM|<\?php|SQLite format/.test(t)), `${r.status}`);
  }
  {
    const r = await fetch(`${BASE}/en/templates/lumora/demo`); const csp = r.headers.get('content-security-policy') || '';
    ok('demo keeps its own CSP, frame-ancestors self and noindex', /frame-ancestors 'self'/.test(csp) && /noindex/.test(r.headers.get('x-robots-tag') || '') || /noindex/.test(await r.text()));
    const h = await fetch(`${BASE}/en/`); const hc = h.headers.get('content-security-policy') || '';
    ok('public pages: strict CSP unchanged (no unsafe-inline/eval, no third-party hosts)', /default-src 'self'/.test(hc) && !/unsafe-inline|unsafe-eval|https?:/.test(hc), hc);
    ok('X-Frame-Options DENY on public pages', (h.headers.get('x-frame-options') || '') === 'DENY');
    const body = await h.text();
    ok('no secrets in HTML', !/TELEGRAM|bot\d+:|FTP_|ADMIN_PASSWORD/i.test(body));
    ok('the home page is indexable in the default design (index,follow) and the legacy one is not', /content="index,follow"/.test(body) && /content="noindex,follow"/.test(await (await fetch(`${BASE}/legacy/en/`)).text()));
    ok('no third-party URLs in the HTML', !/(src|href)="https?:\/\/(?!127\.0\.0\.1|localhost|pixelite\.cz|www\.pixelite\.cz)/.test(body.replace(/href="https?:\/\/(www\.)?(coi\.gov\.cz)[^"]*"/g, '')));
  }
  if (advisories.size) { console.log('\nAdvisory (not failing):'); [...advisories].slice(0, 25).forEach((a) => console.log('  - ' + a)); if (advisories.size > 25) console.log(`  … ${advisories.size - 25} more`); }
} finally {
  await chrome.close();
}
console.log(`\nnew-design suite: ${passed} passed, ${failed} failed`);
if (failed) { failures.slice(0, 60).forEach((f) => console.log('  ✗ ' + f)); if (failures.length > 60) console.log(`  … ${failures.length - 60} more`); process.exit(1); }
