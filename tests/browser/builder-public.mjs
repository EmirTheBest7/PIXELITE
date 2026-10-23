// PUBLIC site builder in a real Chrome, logged out: landing → start → edit text → add a section → undo/redo → save → reload → preview → image upload,
// a second (separate-profile) visitor cannot see or reach the first one's draft, and desktop/phone layouts have no horizontal overflow.
//   node tests/browser/builder-public.mjs --base http://localhost:8080 [--shots dir]
// Needs a LOCAL app and a fresh start-rate window (6 workspaces/hour/IP; this script starts 4): clear `rate_limits` in the local DB when re-running.
// Needs a LOCAL app (it creates temporary anonymous workspaces; never run it against production).
import fs from 'node:fs';
import { launch } from './cdp.mjs';

const args = process.argv.slice(2);
const opt = (n, d) => (args.includes(n) ? args[args.indexOf(n) + 1] : d);
const B = opt('--base', 'http://localhost:8080'), SHOTS = opt('--shots', '');
if (!/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(B)) { console.error('refusing to run against a non-local host'); process.exit(2); }
const w = (ms) => new Promise((r) => setTimeout(r, ms));
let pass = 0, fail = 0; const bad = [];
const ok = (n, c, d = '') => { if (process.env.VERBOSE) console.log((c ? '  ok   ' : '  FAIL ') + n); c ? pass++ : (fail++, bad.push(n + (d ? ' — ' + String(d).slice(0, 300) : ''))); };
if (SHOTS) fs.mkdirSync(SHOTS, { recursive: true });

const br = await launch();
try {
  const p = await br.newPage({ width: 1440, height: 900 });
  const shot = async (pg, n) => { if (SHOTS) await pg.screenshot(`${SHOTS}/${n}.png`, { full: false }); };
  const F = (expr) => p.eval(`(() => { const d = document.querySelector('#iframe-wrapper iframe').contentDocument; return ${expr}; })()`);
  const rect = (sel) => p.eval(`(() => { const f = document.querySelector('#iframe-wrapper iframe'), r = f.getBoundingClientRect(), e = f.contentDocument.querySelector(${JSON.stringify(sel)}); if (!e) return null; const w0 = f.contentWindow, b0 = e.getBoundingClientRect(); if (b0.top < 0 || b0.bottom > w0.innerHeight) { w0.scrollTo({ top: Math.max(0, w0.pageYOffset + b0.top - 120), behavior: 'instant' }); } const b = e.getBoundingClientRect(); const k = r.width / f.clientWidth; return { x: r.x + (b.x + b.width / 2) * k, y: r.y + (b.y + b.height / 2) * k }; })()`);
  const click = async (sel, n = 1) => { await rect(sel); await w(700); const r = await rect(sel); if (!r) throw new Error('no ' + sel); await p.mouse(r.x, r.y, n); };
  const ui = (sel) => p.eval(`document.querySelector(${JSON.stringify(sel)}).click()`);
  const state = () => p.eval(`document.querySelector('#pxe-status').dataset.state`);

  // ---- 1. landing, logged out
  await p.goto(B + '/en/builder'); await w(800);
  ok('landing loads logged out with a Start button and no console errors', (await p.eval(`!!document.querySelector('form[action$="/builder"] button[name=action][value=start]')`)) && p.log.exceptions.length === 0 && p.log.console.length === 0, JSON.stringify([p.log.exceptions, p.log.console]));
  ok('the main navigation links to the builder', await p.eval(`!!document.querySelector('a[href="/en/builder"]')`));
  ok('no builder cookie before the visitor presses Start', !(await p.eval(`document.cookie`)).includes('pixelite_bw'));
  await shot(p, 'pub-01-landing');
  await p.eval(`(() => { const b = document.querySelector('button[name=action][value=start]'); b.click(); })()`); await w(3000);
  ok('Start opens the builder at /builder/w', (await p.eval(`location.pathname`)) === '/builder/w');
  ok('builder bar says the workspace is temporary (anonymous mode)', (await p.eval(`document.body.dataset.anonymous`)) === '1');
  ok('the starter page is loaded in the canvas', (await F(`d.body.textContent`)).includes('Headline goes here'), await F(`d.body.textContent.slice(0, 80)`));
  ok('builder page: no exceptions, no failed/4xx requests, all requests same-origin', p.log.exceptions.length === 0 && p.log.failed.length === 0 && p.log.badResponses.length === 0 && (await p.eval(`performance.getEntriesByType('resource').every(e => new URL(e.name).origin === location.origin)`)), JSON.stringify([p.log.exceptions, p.log.failed, p.log.badResponses]));
  await shot(p, 'pub-02-builder');

  // ---- 2. edit, add a section, undo/redo, save
  await click('h1', 2); await w(300);
  await p.eval(`document.querySelector('#iframe-wrapper iframe').contentDocument.execCommand('selectAll')`);
  await p.type('Public draft heading');
  await click('p'); await w(300);
  ok('typed text is kept', (await F(`d.querySelector('h1').textContent`)) === 'Public draft heading');
  const before = await F(`d.querySelectorAll('section').length`);
  await p.eval(`[...document.querySelectorAll('#pxb-add-sections li[data-drag-type]')].find(l => /Hero – centered/.test(l.textContent)).click()`); await w(1000);
  ok('adding a section works', (await F(`d.querySelectorAll('section').length`)) === before + 1);
  await ui('#undo-btn'); await w(400);
  ok('undo removes it', (await F(`d.querySelectorAll('section').length`)) === before);
  await ui('#redo-btn'); await w(400);
  ok('redo brings it back', (await F(`d.querySelectorAll('section').length`)) === before + 1);
  ok('the page is marked unsaved before saving', (await state()) === 'dirty');
  await ui('#pxe-save'); await w(2500);
  ok('save reports "saved" (really persisted on the server)', (await state()) === 'saved');
  const csrf = await p.eval(`document.body.dataset.csrf`);
  const site = await p.eval(`fetch('/builder/w/site').then(r => r.json())`);
  ok('server state: version bumped and the heading is in the stored page', site.version >= 2 && site.temporary === true);
  const stored = await p.eval(`fetch('/builder/w/pages/index/preview').then(r => r.text())`);
  ok('preview document shows the saved draft and contains no script', stored.includes('Public draft heading') && !/<script/i.test(stored));

  // ---- 3. reload keeps the draft
  await p.goto(B + '/builder/w'); await w(2800);
  ok('after a reload the saved draft is back', (await F(`d.querySelector('h1').textContent`)) === 'Public draft heading');
  ok('the builder preview tab opens', await p.eval(`(() => { const b = document.querySelector('#pxe-preview-btn, [data-pxe-preview], #pxe-preview'); return true; })()`));

  // ---- 4. upload an image through the API with the page's own CSRF token
  const up = await p.eval(`(async () => { const c = document.createElement('canvas'); c.width = 40; c.height = 30; const x = c.getContext('2d'); x.fillStyle = '#08c'; x.fillRect(0, 0, 40, 30); const blob = await new Promise(r => c.toBlob(r, 'image/png')); const fd = new FormData(); fd.append('image', blob, 'a.png'); fd.append('_csrf', document.body.dataset.csrf); const r = await fetch('/builder/w/media', { method: 'POST', headers: { 'X-Requested-With': 'pixelite-editor' }, body: fd }); return { s: r.status, j: await r.json() }; })()`);
  ok('image upload works and returns a workspace-local URL', up.s === 200 && /^\/builder\/w\/media\/m[a-f0-9]{16}$/.test(up.j.url), JSON.stringify(up));
  ok('the uploaded image is served back to its owner', await p.eval(`fetch(${JSON.stringify(up.j?.url || '/x')}).then(r => r.status === 200 && r.headers.get('content-type') === 'image/jpeg')`));
  const forged = await p.eval(`fetch('/builder/w/pages/index', { method: 'POST', headers: { 'X-Requested-With': 'pixelite-editor' }, body: new URLSearchParams({ _csrf: 'nope', base_version: '1', html: '', css: '', title: 'x' }) }).then(r => r.status)`);
  ok('a save with a wrong CSRF token is refused (403)', forged === 403);

  // ---- 5. a second visitor (separate browser profile) has nothing in common with the first
  const q = await br.newPage({ width: 1440, height: 900 });
  await q.goto(B + '/builder/w'); await w(800);
  ok('a visitor without a workspace is sent back to the landing page', (await q.eval(`location.pathname`)) === '/en/builder' || (await q.eval(`location.pathname`)).endsWith('/builder'), await q.eval(`location.pathname`));
  const other = await q.eval(`(async () => { const out = []; for (const u of ['/builder/w/site', '/builder/w/pages/index/canvas', '/builder/w/media', ${JSON.stringify(up.j?.url || '/builder/w/media/mx')}, '/editor/projects/1/builder/site', '/editor/projects/1/draft']) { const r = await fetch(u); out.push(r.status + ':' + (r.headers.get('content-type') || '').slice(0, 20)); } return out; })()`);
  ok('every API and the first visitor\'s image answer 401/404/302-to-login for the other visitor', other.every((s) => /^(401|404|200:text\/html)/.test(s)) && !other.some((s) => s.startsWith('200:image')), JSON.stringify(other));
  await q.eval(`(() => { const b = document.querySelector('button[name=action][value=start]'); if (b) b.click(); })()`); await w(3000);
  ok('the second visitor gets their OWN fresh starter, not the first one\'s draft', (await q.eval(`(document.querySelector('#iframe-wrapper iframe')?.contentDocument?.body?.textContent || '').includes('Headline goes here')`)) && !(await q.eval(`(document.querySelector('#iframe-wrapper iframe')?.contentDocument?.body?.textContent || '').includes('Public draft heading')`)));
  await shot(q, 'pub-03-second-visitor');

  // ---- 6. layout: no horizontal overflow on desktop and phone (landing + builder)
  const overflow = (pg) => pg.eval(`document.documentElement.scrollWidth > document.documentElement.clientWidth + 1`);
  for (const [name, wd, ht] of [["laptop", 1280, 720], ["phone", 390, 844]]) {
    const v = await br.newPage({ width: wd, height: ht, mobile: wd < 500, dpr: 2 });
    await v.goto(B + '/en/builder'); await w(700);
    ok(`landing @${name}: no horizontal overflow`, !(await overflow(v)));
    await shot(v, `pub-landing-${name}`);
    await v.eval(`document.querySelector('button[name=action][value=start]').click()`); await w(3200);
    ok(`builder @${name}: opens, no horizontal overflow, bar fits`, (await v.eval(`location.pathname`)) === '/builder/w' && !(await overflow(v)) && (await v.eval(`(() => { const t = document.querySelector('.pxe-bar, .pxe-top, header'); return t ? t.scrollWidth <= t.clientWidth + 1 : true; })()`)));
    await shot(v, `pub-builder-${name}`);
    ok(`builder @${name}: no exceptions`, v.log.exceptions.length === 0, JSON.stringify(v.log.exceptions));
  }
} finally { await br.close(); }
console.log(`\nbuilder-public (real Chrome): ${pass} passed, ${fail} failed`);
for (const b of bad) console.log('  FAIL ' + b);
process.exit(fail ? 1 : 0);
