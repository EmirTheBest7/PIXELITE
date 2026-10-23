// EXPERIMENTAL visual editor in a real Chrome: selection whitelist, panel edits, undo/redo, save → reload, colours, link validation, image upload,
// hide block, preview, phone width, template animation/interaction still working.
//   EDITOR_EMAIL=… EDITOR_PASSWORD=… node tests/browser/editor.mjs --base http://localhost:8080 --project 1 [--other 2] [--shots dir]
// Needs a LOCAL app with EDITOR_ENABLED=true and a customer account that owns project --project (and, for the isolation check, a project --other
// that belongs to somebody else). Never run it against production or real customer data: it saves drafts.
import fs from 'node:fs';
import { launch } from './cdp.mjs';

const args = process.argv.slice(2);
const opt = (n, d) => (args.includes(n) ? args[args.indexOf(n) + 1] : d);
const B = opt('--base', 'http://localhost:8080'), PID = opt('--project', '1'), OTHER = opt('--other', ''), SHOTS = opt('--shots', '');
const EMAIL = process.env.EDITOR_EMAIL, PW = process.env.EDITOR_PASSWORD;
if (!EMAIL || !PW) { console.error('EDITOR_EMAIL and EDITOR_PASSWORD are required (local test account only)'); process.exit(2); }
if (!/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(B)) { console.error('refusing to run against a non-local host'); process.exit(2); }
const w = (ms) => new Promise((r) => setTimeout(r, ms));
let pass = 0, fail = 0; const bad = [];
const ok = (n, c, d = '') => { if (process.env.VERBOSE) console.log((c ? '  ok   ' : '  FAIL ') + n); c ? pass++ : (fail++, bad.push(n + (d ? ' — ' + String(d).slice(0, 1500) : ''))); };
if (SHOTS) fs.mkdirSync(SHOTS, { recursive: true });

const br = await launch();
try {
  const p = await br.newPage({ width: 1440, height: 900 });
  await p.goto(B + '/en/login');
  await p.eval(`document.querySelector('input[name=email]').value=${JSON.stringify(EMAIL)};document.querySelector('input[name=password]').value=${JSON.stringify(PW)};document.querySelector('form[action$="/login"]').submit()`);
  await w(1500);

  const F = (expr) => p.eval(`(() => { const d = document.querySelector('#iframe-wrapper iframe').contentDocument; return ${expr}; })()`);
  const rect = (sel, fx = 0.5, fy = 0.5) => p.eval(`(() => { const f = document.querySelector('#iframe-wrapper iframe'), r = f.getBoundingClientRect(), e = f.contentDocument.querySelector(${JSON.stringify(sel)}), fx = ${fx}, fy = ${fy}; if (!e) return null; const b = e.getBoundingClientRect(); const k = r.width / f.clientWidth; return { x: r.x + (b.x + b.width * fx) * k, y: r.y + (b.y + b.height * fy) * k }; })()`);   // the iframe is scaled to fit
  const click = async (sel, n = 1, fx = 0.5, fy = 0.5) => { const r = await rect(sel, fx, fy); if (!r) throw new Error('no ' + sel); await p.mouse(r.x, r.y, n); };
  const setInput = (sel, v) => p.eval(`(() => { const i = document.querySelector(${JSON.stringify(sel)}); i.value = ${JSON.stringify(v)}; i.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  const shot = async (n) => { if (SHOTS) await p.screenshot(`${SHOTS}/${n}.png`, { full: false }); };
  const state = () => p.eval(`document.querySelector('#pxe-status').dataset.state`);
  const boot = async (width) => { await p.goto(B + '/editor/projects/' + PID); await w(2200); };

  // ---- 0. start from the template's own values (drafts persist between runs)
  const resetDraft = () => p.eval(`(async () => { const t = document.body.dataset.csrf, j = await fetch('${B}/editor/projects/${PID}/draft').then(r => r.json());
    const v = { hero_line1: 'Stop Digging', hero_line2: 'Through Dashboards.', nav_about_href: '#about', color_title_1: '', color_title_2: '', color_copy: '', color_cta: '', demo_image: '', show_demo_card: true };
    await fetch('${B}/editor/projects/${PID}/draft', { method: 'POST', headers: { 'X-Requested-With': 'pixelite-editor', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _csrf: t, base_version: String(j.version), values: JSON.stringify(v) }) }); })()`);
  await p.goto(B + '/editor/projects/' + PID); await w(800);
  await resetDraft(); await w(500);

  // ---- 1. loads cleanly, strict CSP, nothing third-party
  await boot();
  ok('editor loads without exceptions or console errors', p.log.exceptions.length === 0 && p.log.console.length === 0, JSON.stringify([p.log.exceptions, p.log.console]));
  ok('no failed or 4xx/5xx requests', p.log.failed.length === 0 && p.log.badResponses.length === 0, JSON.stringify([p.log.failed, p.log.badResponses]));
  ok('canvas page is ready and status says saved', (await state()) === 'saved' && (await p.eval(`document.body.classList.contains('is-ready')`)));
  ok('save is disabled while nothing changed', await p.eval(`document.querySelector('#pxe-save').disabled`));
  const reqHosts = await p.eval(`performance.getEntriesByType('resource').map(e => new URL(e.name).origin)`);
  ok('every request is same-origin (no third parties)', reqHosts.every((o) => o === B), JSON.stringify([...new Set(reqHosts)]));
  await shot('01-loaded');

  // ---- 2. whitelist: only marked elements react
  await click('.time-panel');
  ok('an element outside the whitelist cannot be selected', await p.eval(`getComputedStyle(document.querySelector('#select-box')).display`) === 'none');
  await click('.hero-title .line-one .line-reveal');
  ok('clicking the headline selects it and shows its panel', (await p.eval(`document.querySelector('#right-panel input[name=text]')?.value`)) === 'Stop Digging');
  await shot('02-selected');

  // ---- 3. panel edit, undo, redo, dirty state
  await setInput('#right-panel input[name=text]', 'Know your numbers');
  ok('panel edit changes the canvas', (await F(`d.querySelector('.line-one .line-reveal').textContent`)) === 'Know your numbers');
  ok('unsaved changes are shown and save enabled', (await state()) === 'dirty' && !(await p.eval(`document.querySelector('#pxe-save').disabled`)));
  await p.eval(`document.querySelector('#pxe-undo').click()`); await w(200);
  ok('undo restores the headline and clears the unsaved state', (await F(`d.querySelector('.line-one .line-reveal').textContent`)) === 'Stop Digging' && (await state()) === 'saved');
  await p.eval(`document.querySelector('#pxe-redo').click()`); await w(200);
  ok('redo re-applies it', (await F(`d.querySelector('.line-one .line-reveal').textContent`)) === 'Know your numbers' && (await state()) === 'dirty');

  // ---- 4. typing on the canvas (double-click) is plain text only
  await click('.hero-title .line-two .line-reveal', 2);
  ok('double-click makes the headline editable as plain text', await F(`d.querySelector('.line-two .line-reveal').isContentEditable`));
  await p.eval(`document.querySelector('#iframe-wrapper iframe').contentDocument.execCommand('selectAll')`);
  await p.type('Made <b>simple</b>');
  await p.key('Enter'); await w(200);
  ok('typed markup stays text (no element created)', (await F(`d.querySelector('.line-two .line-reveal').children.length`)) === 0 && (await F(`d.querySelector('.line-two .line-reveal').textContent`)) === 'Made <b>simple</b>');
  ok('Enter ends editing', !(await F(`d.querySelector('.line-two .line-reveal').isContentEditable`)));

  // ---- 5. colours, links, validation
  await setInput('#left-panel [data-theme-row=color_title_1] input[type=color]', '#ff8800');
  ok('colour is applied live', (await F(`getComputedStyle(d.querySelector('.line-one')).color`)) === 'rgb(255, 136, 0)');
  await setInput('#left-panel [data-theme-row=color_cta] input[type=color]', '#fff200');
  ok('button label colour follows the background for contrast', (await F(`getComputedStyle(d.querySelector('.primary-cta .label')).color`)) === 'rgb(17, 17, 17)');
  await click('.nav a:nth-child(2)');
  await setInput('#right-panel input[name=href]', 'javascript:alert(1)');
  ok('javascript: links are refused on the client', (await F(`d.querySelector('.nav a:nth-child(2)').getAttribute('href')`)) === '#about');
  await setInput('#right-panel input[name=href]', 'https://example.com/team');
  ok('https link accepted', (await F(`d.querySelector('.nav a:nth-child(2)').getAttribute('href')`)) === 'https://example.com/team');

  // ---- 6. image upload (re-encoded server-side), hide block
  await click('.demo-visual img', 1, 0.15, 0.15);   // the play button covers the centre
  await p.eval(`(async () => { const c = document.createElement('canvas'); c.width = 400; c.height = 300; const x = c.getContext('2d'); x.fillStyle = '#2a9d8f'; x.fillRect(0, 0, 400, 300); const b = await new Promise((r) => c.toBlob(r, 'image/png')); const dt = new DataTransfer(); dt.items.add(new File([b], 'x.png', { type: 'image/png' })); const i = document.querySelector('#right-panel input[type=file]'); i.files = dt.files; i.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  await w(1500);
  const media = await F(`d.querySelector('.demo-visual img').getAttribute('data-px-media')`);
  ok('uploaded picture is applied (server-issued id, same-origin URL)', /^m[a-f0-9]{16}$/.test(media) && (await F(`d.querySelector('.demo-visual img').getAttribute('src')`)).startsWith('/editor/projects/' + PID + '/media/'), media);
  await p.eval(`document.querySelector('#pxe-undo').click()`); await w(300);
  ok('undoing the picture replacement is ONE step: src and the saved id go back together', ((await F(`d.querySelector('.demo-visual img').getAttribute('data-px-media')`)) || '') === '' && (await F(`d.querySelector('.demo-visual img').getAttribute('src')`)).endsWith('watch-demo-thumbnail.jpg'));
  await p.eval(`document.querySelector('#pxe-redo').click()`); await w(300);
  ok('redo brings the uploaded picture back (src and id together)', (await F(`d.querySelector('.demo-visual img').getAttribute('data-px-media')`)) === media && (await F(`d.querySelector('.demo-visual img').getAttribute('src')`)).endsWith('/media/' + media));
  await p.eval(`document.querySelector('#pxe-block-card').click()`); await w(200);
  ok('demo card can be hidden (dimmed in the editor)', (await F(`d.querySelector('.demo-card').getAttribute('data-px-visible')`)) === '0');

  // ---- 7. save, hard reload, everything survives
  await p.eval(`document.querySelector('#pxe-save').click()`); await w(1200);
  ok('save succeeds', (await state()) === 'saved', await p.eval(`document.querySelector('.pxe-status__text').textContent`));
  await boot();
  ok('after reload: text, link, colours, picture and hidden block are all still there',
    (await F(`d.querySelector('.line-one .line-reveal').textContent`)) === 'Know your numbers'
    && (await F(`d.querySelector('.line-two .line-reveal').textContent`)) === 'Made <b>simple</b>'
    && (await F(`d.querySelector('.nav a:nth-child(2)').getAttribute('href')`)) === 'https://example.com/team'
    && (await F(`getComputedStyle(d.querySelector('.line-one')).color`)) === 'rgb(255, 136, 0)'
    && (await F(`d.querySelector('.demo-visual img').getAttribute('data-px-media')`)) === media
    && (await F(`d.querySelector('.demo-card').getAttribute('data-px-visible')`)) === '0');
  ok('after reload nothing is marked unsaved', (await state()) === 'saved');
  const rendered = await p.eval(`fetch('${B}/editor/projects/${PID}/preview').then(r => r.text())`);
  ok('preview HTML escapes the typed text and has no editor markers', rendered.includes('Made &lt;b&gt;simple&lt;/b&gt;') && !rendered.includes('data-px-') && !rendered.includes('class="demo-card"'));

  // ---- 8. preview mode (iframe) + phone width + interactions
  await p.eval(`document.querySelector('[data-mode-btn=preview]').click()`); await w(1500);
  ok('preview shows the saved draft', await p.eval(`document.querySelector('#pxe-preview-frame').contentDocument.querySelector('.line-one .line-reveal')?.textContent === 'Know your numbers'`));
  await shot('03-preview');
  await p.eval(`document.querySelector('[data-mode-btn=edit]').click()`);
  await p.eval(`document.querySelector('[data-device-btn=phone]').click()`); await w(800);
  ok('phone width is 390px', (await p.eval(`document.querySelector('#iframe-wrapper iframe').clientWidth`)) === 390);
  ok('page has no horizontal overflow at phone width', await F(`d.documentElement.scrollWidth <= d.documentElement.clientWidth + 1`));
  await click('.menu-toggle'); await w(500);
  ok('template menu button still works inside the editor canvas', await F(`d.querySelector('.header').classList.contains('menu-open')`));
  await shot('04-phone');
  const anim = await F(`[getComputedStyle(d.querySelector('.hero-title .line-reveal')).animationName, getComputedStyle(d.querySelector('.demo-card')).animationName]`);
  ok('template animations still run in the editor canvas (the template keyframes are applied)', anim[0] === 'entrance-line' && anim[1] === 'entrance-card', JSON.stringify(anim));
  await click('.menu-toggle'); await w(500);   // close the menu again: it covers the headline
  await click('.hero-title .line-one .line-reveal');
  await setInput('#right-panel input[name=text]', 'W'.repeat(22));
  ok('the long headline went into the headline field', (await F(`d.querySelector('.line-one .line-reveal').textContent.length`)) === 22);
  ok('the longest allowed headline stays inside the phone screen (wraps instead of overflowing)', await F(`d.querySelector('.line-one .line-reveal').getBoundingClientRect().right <= d.documentElement.clientWidth + 1`));
  await shot('05-long-headline');
  await p.eval(`document.querySelector('#pxe-undo').click()`);
  await p.eval(`document.querySelector('[data-device-btn=desktop]').click()`);

  // ---- 8b. an untouched draft lays out exactly like the original demo (our colour/wrap overrides change nothing by default), same animations
  await p.goto(B + '/editor/projects/' + PID); await w(800);
  await resetDraft(); await w(500);
  const probe = `(() => { const r = (s) => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return [Math.round(b.x), Math.round(b.y), Math.round(b.width), Math.round(b.height)]; };
    return { title: r('.hero-title'), l1: r('.line-one'), l2: r('.line-two'), copy: r('.hero-copy'), cta: r('.primary-cta'), card: r('.demo-card'), nav: r('.nav'), time: r('.time-panel'),
      anim: [getComputedStyle(document.querySelector('.line-reveal')).animationName, getComputedStyle(document.querySelector('.demo-card')).animationName, getComputedStyle(document.querySelector('.hero-title .line-one')).color] }; })()`;
  const q = await br.newPage({ width: 1280, height: 800 });
  for (const [vw, vh] of [[1280, 800], [820, 1100], [390, 844]]) {
    await q.setViewport(vw, vh);
    await q.goto(B + '/en/templates/vantage/demo'); await w(4500);
    const demo = await q.eval(probe);
    const inFrame = probe.replace(/document\./g, 'd.');   // the same measurements, run against the preview inside an iframe of the signed-in page
    const prev = await p.eval(`(async () => { const f = document.createElement('iframe'); f.style.cssText = 'position:fixed;left:0;top:0;width:${vw}px;height:${vh}px;border:0;z-index:99'; f.src = '${B}/editor/projects/${PID}/preview'; document.body.append(f); await new Promise((r) => (f.onload = r)); await new Promise((r) => setTimeout(r, 4500)); const d = f.contentDocument; const v = ${inFrame}; f.remove(); return v; })()`);
    ok(`default draft = demo layout at ${vw}px (headline, copy, button, card, menu positions and sizes)`, JSON.stringify({ ...prev, anim: 0 }) === JSON.stringify({ ...demo, anim: 0 }), JSON.stringify([demo, prev]));
    ok(`default draft = demo animations and colours at ${vw}px`, JSON.stringify(prev.anim) === JSON.stringify(demo.anim), JSON.stringify([demo.anim, prev.anim]));
  }
  await q.close();

  // ---- 9. server-side guards from inside the logged-in page
  const post = (body, hdr = { 'X-Requested-With': 'pixelite-editor', 'Content-Type': 'application/x-www-form-urlencoded' }) => p.eval(`fetch('${B}/editor/projects/${PID}/draft', { method: 'POST', headers: ${JSON.stringify(hdr)}, body: ${JSON.stringify(body)} }).then(async r => [r.status, await r.text()])`);
  const csrf = await p.eval(`document.body.dataset.csrf`);
  const ver = await p.eval(`fetch('${B}/editor/projects/${PID}/draft').then(r => r.json()).then(j => j.version)`);
  ok('save without CSRF token is rejected (403)', (await post('base_version=' + ver + '&values=%7B%7D'))[0] === 403);
  ok('save without the custom header is rejected (403)', (await post('_csrf=' + csrf + '&base_version=' + ver + '&values=%7B%7D', { 'Content-Type': 'application/x-www-form-urlencoded' }))[0] === 403);
  ok('unknown fields are rejected (422)', (await post('_csrf=' + csrf + '&base_version=' + ver + '&values=' + encodeURIComponent('{"../../etc/passwd":"x"}')))[0] === 422);
  ok('stale version is refused (409)', (await post('_csrf=' + csrf + '&base_version=0&values=%7B%7D'))[0] === 409);
  ok('path-like media id is a 404', await p.eval(`fetch('${B}/editor/projects/${PID}/media/..%2F..%2Fleads').then(r => r.status)`) === 404);
  if (OTHER) {
    ok("someone else's project is a 404 for the editor page, draft and canvas", (await p.eval(`Promise.all(['', '/draft', '/canvas', '/preview'].map(s => fetch('${B}/editor/projects/${OTHER}' + s).then(r => r.status)))`)).every((s) => s === 404));
  }
} finally { await br.close(); }
console.log(`\neditor browser suite: ${pass} passed, ${fail} failed`);
for (const b of bad) console.log('  ✗ ' + b);
process.exit(fail ? 1 : 0);
