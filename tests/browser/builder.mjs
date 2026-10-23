// EXPERIMENTAL site builder (VvvebJs) in a real Chrome: add sections/blocks/elements, select, type on the page, change CSS through the panels, responsive breakpoints,
// delete + undo/redo, code view with a hostile payload, save → reload, pages, image library + picker, preview, and "customer script never runs" checks.
//   EDITOR_EMAIL=… EDITOR_PASSWORD=… node tests/browser/builder.mjs --base http://localhost:8080 --project 1 [--shots dir]
// Needs a LOCAL app with EDITOR_ENABLED=true and a customer account that owns project --project. It SAVES and DELETES pages/images of that project:
// never run it against production or real customer data.
import fs from 'node:fs';
import { launch } from './cdp.mjs';

const args = process.argv.slice(2);
const opt = (n, d) => (args.includes(n) ? args[args.indexOf(n) + 1] : d);
const B = opt('--base', 'http://localhost:8080'), PID = opt('--project', '1'), SHOTS = opt('--shots', '');
const EMAIL = process.env.EDITOR_EMAIL, PW = process.env.EDITOR_PASSWORD;
if ((!EMAIL || !PW) && !args.includes('--session')) { console.error('EDITOR_EMAIL and EDITOR_PASSWORD are required (local test account only)'); process.exit(2); }
if (!/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(B)) { console.error('refusing to run against a non-local host'); process.exit(2); }
const w = (ms) => new Promise((r) => setTimeout(r, ms));
let pass = 0, fail = 0; const bad = [];
const ok = (n, c, d = '') => { if (process.env.VERBOSE) console.log((c ? '  ok   ' : '  FAIL ') + n); c ? pass++ : (fail++, bad.push(n + (d ? ' — ' + String(d).slice(0, 1500) : ''))); };
if (SHOTS) fs.mkdirSync(SHOTS, { recursive: true });

const br = await launch();
try {
  const p = await br.newPage({ width: 1440, height: 900 });
  const SESSION = opt('--session', '');   // optional: a PHP session id of an already signed-in test customer (saves a login; the login form is rate-limited)
  if (SESSION) await p.setCookie(B, 'pixelite', SESSION);
  else {
    await p.goto(B + '/en/login');
    await p.eval(`document.querySelector('input[name=email]').value=${JSON.stringify(EMAIL)};document.querySelector('input[name=password]').value=${JSON.stringify(PW)};document.querySelector('form[action$="/login"]').submit()`);
    await w(1500);
  }

  const F = (expr) => p.eval(`(() => { const d = document.querySelector('#iframe-wrapper iframe').contentDocument; return ${expr}; })()`);
  const rect = (sel, fx = 0.5, fy = 0.5) => p.eval(`(() => { const f = document.querySelector('#iframe-wrapper iframe'), r = f.getBoundingClientRect(), e = f.contentDocument.querySelector(${JSON.stringify(sel)}); if (!e) return null; const w0 = f.contentWindow, b0 = e.getBoundingClientRect(); if (b0.top < 0 || b0.bottom > w0.innerHeight) { w0.scrollTo({ top: Math.max(0, w0.pageYOffset + b0.top - 120), behavior: 'instant' }); } const b = e.getBoundingClientRect(); const k = r.width / f.clientWidth; return { x: r.x + (b.x + b.width * ${fx}) * k, y: r.y + (b.y + b.height * ${fy}) * k }; })()`);
  const click = async (sel, n = 1, fx = 0.5, fy = 0.5) => { await rect(sel, fx, fy); await w(700); const r = await rect(sel, fx, fy); if (!r) throw new Error('no ' + sel); await p.mouse(r.x, r.y, n); };
  const ui = (sel) => p.eval(`document.querySelector(${JSON.stringify(sel)}).click()`);
  const setVal = (sel, v, ev = 'change') => p.eval(`(() => { const i = document.querySelector(${JSON.stringify(sel)}); if (!i) throw new Error('no ${sel}'); i.value = ${JSON.stringify(v)}; i.dispatchEvent(new Event(${JSON.stringify(ev)}, { bubbles: true })); })()`);
  const state = () => p.eval(`document.querySelector('#pxe-status').dataset.state`);
  const shot = async (n) => { if (SHOTS) await p.screenshot(`${SHOTS}/${n}.png`, { full: false }); };
  const open = async () => { await p.goto(B + '/editor/projects/' + PID + '/builder'); await w(2600); };
  const csrf = () => p.eval(`document.body.dataset.csrf`);
  const api = async (path, post) => p.eval(`(async () => { const r = await fetch('${B}/editor/projects/${PID}/builder${path}', ${post ? `{ method: 'POST', headers: { 'X-Requested-With': 'pixelite-editor', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(Object.assign({ _csrf: document.body.dataset.csrf }, ${JSON.stringify(post)})) }` : '{}'}); const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch (e) {} return { status: r.status, json: j, text: t }; })()`);
  const siteVersion = async () => (await api('/site')).json.version;

  // ---- 0. reset the project's site to one blank page (the test owns this project)
  await open();
  for (const pg of (await api('/site')).json.pages) { if (pg.slug !== 'index') await api(`/pages/${pg.slug}/delete`, { base_version: String(await siteVersion()) }); }
  await api('/pages/index', { base_version: String(await siteVersion()), title: 'Home', html: '', css: '' });
  const img0 = (await api('/media')).json.images;
  for (const m of img0) await api(`/media/${m.id}/delete`, {});

  // ---- 1. loads cleanly
  await open();
  ok('builder loads without exceptions or console errors', p.log.exceptions.length === 0 && p.log.console.length === 0, JSON.stringify([p.log.exceptions, p.log.console]));
  ok('no failed or 4xx/5xx requests (the CSP blocks nothing the page needs)', p.log.failed.length === 0 && p.log.badResponses.length === 0, JSON.stringify([p.log.failed, p.log.badResponses]));
  const hosts = await p.eval(`performance.getEntriesByType('resource').map(e => new URL(e.name).origin)`);
  ok('every request is same-origin (no third parties)', hosts.every((o) => o === B), JSON.stringify([...new Set(hosts)]));
  ok('page ready, nothing unsaved on a fresh load, save disabled', (await state()) === 'saved' && (await p.eval(`document.querySelector('#pxe-save').disabled`)));
  const counts = await p.eval(`[document.querySelectorAll('.components-list[data-type=leftpanel] li[data-drag-type]').length, document.querySelectorAll('.blocks-list[data-type=leftpanel] li[data-drag-type]').length, document.querySelectorAll('.sections-list[data-type=leftpanel] li[data-drag-type]').length]`);
  ok('the left panel offers elements, blocks and sections', counts[0] >= 25 && counts[1] >= 10 && counts[2] >= 10, JSON.stringify(counts));
  await shot('01-empty');

  // ---- 2. add a section by clicking it in the Sections tab
  await ui('[data-bs-target="#pxb-add-sections"]'); await w(300);
  await p.eval(`[...document.querySelectorAll('#pxb-add-sections li[data-drag-type]')].find(l => /Hero – centered/.test(l.textContent)).click()`); await w(1000);
  ok('clicking a section inserts it into the page', (await F(`!!d.querySelector('section[data-name="hero"] h1')`)));
  ok('the page is now marked unsaved and undo is available', (await state()) === 'dirty' && !(await p.eval(`document.querySelector('#undo-btn').disabled`)));
  await ui('[data-bs-target="#pxb-add-blocks"]'); await w(200);
  await p.eval(`[...document.querySelectorAll('#pxb-add-blocks li[data-drag-type]')].find(l => /^\\s*Card\\s*$/.test(l.textContent)).click()`); await w(1000);
  ok('a block is inserted after the selected element', (await F(`d.querySelectorAll('.card').length`)) === 1);
  await ui('[data-bs-target="#pxb-add-elements"]'); await w(200);
  await shot('02-added');

  // ---- 3. select + edit content in the panel, type on the page
  await click('section[data-name="hero"] h1');
  ok('selecting the heading shows its content panel (heading size, text …)', await p.eval(`document.querySelectorAll('#content-tab .pxb-field, #content-tab .row, #content-tab [data-key]').length > 1 && !!document.querySelector('#content-tab')`));
  await click('section[data-name="hero"] h1', 2);
  const dbl = [await F(`d.querySelector('section[data-name="hero"] h1').isContentEditable`), await p.eval(`getComputedStyle(document.querySelector('#wysiwyg-editor')).display`), await p.eval(`Vvveb.Builder.selectedEl && Vvveb.Builder.selectedEl.tagName + '.' + Vvveb.Builder.selectedEl.className`), await p.eval(`Vvveb.WysiwygEditor.isActive`), await p.eval(`document.getElementById('wysiwyg-editor').getAttribute('style')+'|'+document.getElementById('select-box').className+'|'+document.getElementById('select-box').getAttribute('style')`)];
  ok('double-click makes the text editable and shows the formatting toolbar', dbl[0] && dbl[3] && dbl[1] !== 'none', JSON.stringify(dbl));
  await shot('03a-editing');
  await p.eval(`document.querySelector('#iframe-wrapper iframe').contentDocument.execCommand('selectAll')`);
  await p.type('Hello builder');
  await click('section[data-name="hero"] p.lead');
  ok('typed text is kept after leaving the field', (await F(`d.querySelector('section[data-name="hero"] h1').textContent`)) === 'Hello builder');
  await shot('03-typed');

  // ---- 4. CSS through the Style tab: colour rule, then a responsive (tablet) rule
  await click('section[data-name="hero"] h1');
  await ui('[data-bs-target="#style-tab"]'); await w(300);
  const colorSet = await p.eval(`(() => { const i = document.querySelector('#style-tab input[name=color]'); if (!i) return false; i.value = '#ff0000'; i.dispatchEvent(new Event('change', { bubbles: true })); return true; })()`);
  ok('the Style tab has a colour input for the selected element', colorSet);
  await w(300);
  ok('the colour became a CSS rule in the page stylesheet (not an inline style)', /color:\s*#ff0000/i.test(await F(`d.getElementById('vvvebjs-styles').textContent`)) && (await F(`getComputedStyle(d.querySelector('section[data-name="hero"] h1')).color`)) === 'rgb(255, 0, 0)');
  await ui('#tablet-view'); await w(500);
  ok('tablet view narrows the canvas and targets the tablet breakpoint', (await p.eval(`Vvveb.StyleManager.currentBreakpoint`)) === 'md' && (await p.eval(`document.querySelector('#iframe-wrapper').style.width`)) === '767px');
  await click('section[data-name="hero"] h1');
  await p.eval(`(() => { const i = document.querySelector('#style-tab input[name=color]'); i.value = '#0000ff'; i.dispatchEvent(new Event('change', { bubbles: true })); })()`); await w(300);
  const css2 = await F(`d.getElementById('vvvebjs-styles').textContent`);
  ok('a style set in tablet view is written inside an @media (max-width) rule', /@media \(max-width: 767\.98px\)\s*\{[^}]*\{[^}]*color:\s*#0000ff/i.test(css2), css2);
  await ui('#tablet-view'); await w(400);   // back to desktop
  ok('desktop view restores the full width', (await p.eval(`Vvveb.StyleManager.currentBreakpoint`)) === 'none');
  await shot('04-styled');

  // ---- 5. delete + undo + redo
  await click('.card .card-title');
  await p.eval(`(() => { const c = Vvveb.Builder.selectedEl.closest('.card'); Vvveb.Builder.selectNode(c); Vvveb.Builder.loadNodeComponent(c); })()`); await w(200);
  const selCard = await p.eval(`Vvveb.Builder.selectedEl.tagName + '.' + Vvveb.Builder.selectedEl.className`);
  await ui('#delete-btn'); await w(300);
  ok('delete removes the selected element', (await F(`d.querySelectorAll('.card').length`)) === 0, selCard);
  await ui('#undo-btn'); await w(300);
  ok('undo brings it back', (await F(`d.querySelectorAll('.card').length`)) === 1);
  await ui('#redo-btn'); await w(300);
  ok('redo removes it again', (await F(`d.querySelectorAll('.card').length`)) === 0);
  await ui('#undo-btn'); await w(300);

  // ---- 6. images: upload, library, picker
  await ui('[data-bs-target="#pxb-tab-media"]'); await w(200);
  await p.eval(`(async () => { const c = document.createElement('canvas'); c.width = 320; c.height = 200; const x = c.getContext('2d'); x.fillStyle = '#2a9d8f'; x.fillRect(0, 0, 320, 200); const b = await new Promise((r) => c.toBlob(r, 'image/png')); const dt = new DataTransfer(); dt.items.add(new File([b], 'x.png', { type: 'image/png' })); const i = document.querySelector('#pxb-upload'); i.files = dt.files; i.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  await w(1500);
  ok('an uploaded image appears in the library (re-encoded server-side)', (await p.eval(`document.querySelectorAll('#pxb-media img').length`)) === 1);
  await ui('[data-bs-target="#pxb-tab-add"]'); await w(200); await ui('[data-bs-target="#pxb-add-sections"]'); await w(200);
  await p.eval(`[...document.querySelectorAll('#pxb-add-sections li[data-drag-type]')].find(l => /Hero – text and picture/.test(l.textContent)).click()`); await w(1000);
  await click('section[data-name="hero-image"] img');
  await w(300);
  const picked = await p.eval(`(async () => { const b = document.querySelector('#content-tab button[data-pick]'); if (!b) return 'no-pick-button'; b.click(); await new Promise(r => setTimeout(r, 600)); const pick = document.querySelector('#pxb-picker .pxb-media__pick'); if (!pick) return 'no-image-in-picker'; pick.click(); await new Promise(r => setTimeout(r, 400)); return 'ok'; })()`);
  ok('“Choose image” opens the library and applies the picked image', picked === 'ok' && /^\/editor\/projects\/\d+\/media\/m[a-f0-9]{16}$/.test(await F(`d.querySelector('section[data-name="hero-image"] img').getAttribute('src')`)), picked);
  ok('the page image is loaded from the authenticated media route', await F(`d.querySelector('section[data-name="hero-image"] img').naturalWidth > 0`));

  // ---- 7. code view with a hostile payload: nothing runs, the server strips it
  await ui('#code-editor-btn'); await w(400);
  ok('code view shows the HTML and the CSS of the page', (await p.eval(`document.querySelector('#pxb-html-code').value.includes('data-name="hero"')`)) && /color/.test(await p.eval(`document.querySelector('#css-editor').value`)));
  const evil = '<section data-name="evil"><img src=x onerror="parent.__pwn=1"><scr' + 'ipt>parent.__pwn2=1</scr' + 'ipt><a href="javascript:parent.__pwn3=1">x</a><iframe src="https://evil.example"></iframe><iframe srcdoc="&lt;scr' + 'ipt&gt;parent.__pwn6=1&lt;/scr' + 'ipt&gt;"></iframe><meta http-equiv="refresh" content="0;url=https://evil.example/"><p onclick="parent.__pwn4=1" style="background:url(//evil.example/x)">still here</p></section>';
  await p.eval(`window.__pwn = window.__pwn2 = window.__pwn3 = window.__pwn4 = window.__pwn6 = undefined`);
  await p.eval(`document.querySelector('#pxb-html-code').value += ${JSON.stringify(evil)}; document.querySelector('#pxb-apply-html').click()`); await w(1000);
  const pwn = await p.eval(`[window.__pwn, window.__pwn2, window.__pwn3, window.__pwn4, window.__pwn6].map(v => v === undefined ? 'undefined' : v)`);
  ok('hostile HTML pasted in the code view does not execute anything (onerror, script, javascript: link, handler)', pwn.every((v) => v === 'undefined'), JSON.stringify(pwn));
  await F(`d.querySelector('a[href^="javascript"]') && d.querySelector('a[href^="javascript"]').click()`); await w(300);
  ok('even clicking a javascript: link inside the canvas runs nothing', (await p.eval(`window.__pwn3`)) === undefined);
  await p.eval(`(() => { const d = document.querySelector('#iframe-wrapper iframe').contentDocument; const s = d.createElement('script'); s.textContent = 'parent.__pwn5 = 1'; d.body.append(s); })()`); await w(300);
  ok('a <script> element injected into the canvas by hand is not executed (CSP + sandbox)', (await p.eval(`window.__pwn5`)) === undefined);
  ok('the canvas document is sandboxed but still reachable by the editor (same origin)', (await p.eval(`document.querySelector('#iframe-wrapper iframe').contentWindow.origin`)) === B);
  await ui('#code-editor-btn'); await w(300);

  // ---- 8. save: the server sanitises, tells what it removed, and the page reloads with the stored version
  await ui('#pxe-save'); await w(2500);
  const stored = (await api('/pages/index/canvas')).text;
  ok('save succeeded and the stored page has no handler, script, iframe or external URL', (await state()) === 'saved' && !/onerror|<script|<iframe|evil\.example|javascript:|onclick/i.test(stored) && stored.includes('still here'), stored.slice(stored.indexOf('<body'), stored.indexOf('<body') + 400));
  ok('the customer was told that content was removed (error toasts)', (await p.eval(`document.querySelectorAll('#pxe-toasts .pxe-toast--error').length`)) >= 1);
  ok('the stored page keeps the hero, the picked image and the CSS rules', stored.includes('data-name="hero"') && /\/editor\/projects\/\d+\/media\/m[a-f0-9]{16}/.test(stored) && /color:(#ff0000|rgb\(255, 0, 0\))/.test(stored) && /@media \(max-width: 767\.98px\)\{/.test(stored), stored.slice(0, 1200));

  // ---- 9. reload: everything is still there; nothing marked unsaved
  await open();
  ok('after a reload the saved page is back (hero, text colour rule) and nothing is unsaved', (await F(`!!d.querySelector('section[data-name="hero"]') && getComputedStyle(d.querySelector('section[data-name="hero"] h1')).color`)) === 'rgb(255, 0, 0)' && (await state()) === 'saved');
  ok('layers tab lists the page structure', await (async () => { await ui('[data-bs-target="#pxb-tab-layers"]'); await w(600); return (await p.eval(`document.querySelectorAll('#tree-list li[data-component]').length`)) > 3; })());
  await shot('05-reloaded');

  // ---- 10. pages
  await ui('[data-bs-target="#pxb-tab-pages"]'); await w(200);
  await p.eval(`(() => { document.querySelector('#pxb-new-title').value = 'About us'; document.querySelector('#pxb-new-page').requestSubmit(); })()`); await w(2500);
  const pg = [await p.eval(`[...document.querySelectorAll('#pxb-pages .pxb-list__open')].map(b => b.textContent).join('|')`), await p.eval(`document.querySelector('#pxb-page-label').textContent`), await F(`d.body.children.length + ':' + d.body.innerHTML.slice(0, 80)`), await p.eval(`document.querySelector('.pxe-status__text').textContent + ' / toasts: ' + document.querySelector('#pxe-toasts').textContent + ' / exc ' + JSON.stringify(window.__lastErr || '')`), JSON.stringify(p.log.exceptions), JSON.stringify(p.log.console)];
  ok('a new page is created and opened (blank canvas, listed in Pages)', pg[0] === 'Home|About us' && pg[1] === 'About us' && pg[2].startsWith('0:'), JSON.stringify(pg));
  await ui('[data-bs-target="#pxb-tab-add"]'); await w(200); await ui('[data-bs-target="#pxb-add-blocks"]'); await w(200);
  await p.eval(`[...document.querySelectorAll('#pxb-add-blocks li[data-drag-type]')].find(l => /Alert|Notice/.test(l.textContent)).click()`); await w(400);
  await ui('#pxe-save'); await w(1500);
  const about = (await api('/pages/about-us/canvas')).text;
  ok('the second page saves separately', about.includes('alert') && !about.includes('data-name="hero"'));
  await ui('[data-bs-target="#pxb-tab-pages"]'); await w(200);
  await p.eval(`document.querySelector('#pxb-pages .pxb-list__open').click()`); await w(2500);
  ok('switching back to Home loads the first page', await F(`!!d.querySelector('section[data-name="hero"]')`));

  // ---- 11. preview + live mode switch
  await ui('[data-mode-btn=preview]'); await w(1800);
  ok('preview shows the saved page without editor attributes', await p.eval(`(() => { const d = document.querySelector('#pxe-preview-frame').contentDocument; return !!d.querySelector('section[data-name="hero"]') && !d.documentElement.outerHTML.includes('data-vvveb') && !d.querySelector('[contenteditable]'); })()`));
  await shot('06-preview');
  await ui('[data-mode-btn=edit]'); await w(300);

  // ---- 12. server guards, from inside the signed-in page
  const v = await siteVersion();
  const post = (body, hdr) => p.eval(`fetch('${B}/editor/projects/${PID}/builder/pages/index', { method: 'POST', headers: ${JSON.stringify(hdr || { 'X-Requested-With': 'pixelite-editor', 'Content-Type': 'application/x-www-form-urlencoded' })}, body: ${JSON.stringify(body)} }).then(r => r.status)`);
  const tk = await csrf();
  ok('save without CSRF token → 403, without header → 403, stale version → 409', (await post('base_version=' + v + '&html=%3Cp%3Ex%3C%2Fp%3E&css=&title=x')) === 403 && (await post('_csrf=' + tk + '&base_version=' + v + '&html=x&css=&title=x', { 'Content-Type': 'application/x-www-form-urlencoded' })) === 403 && (await post('_csrf=' + tk + '&base_version=0&html=x&css=&title=x')) === 409);
  ok('a page that does not exist is a 404 for the canvas', (await api('/pages/nope/canvas')).status === 404);
} finally { await br.close(); }
console.log(`\nbuilder browser suite: ${pass} passed, ${fail} failed`);
for (const b of bad) console.log('  ✗ ' + b);
process.exit(fail ? 1 : 0);
