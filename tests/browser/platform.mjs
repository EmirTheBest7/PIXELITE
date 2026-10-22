// Platform flows in a real browser + real HTTP: staff area (new design), careers CRUD, customers, projects, uploads and the isolated preview origin.
//   ADMIN_EMAIL=… ADMIN_PASSWORD=… node tests/browser/platform.mjs --base http://localhost:8095 --preview http://127.0.0.1:8095 [--fixtures <dir>]
// Needs a test container (Telegram disabled, its own storage) whose staff account the caller knows; never run it against a developer's real data.
// --preview must be a DIFFERENT HOST than --base (cookies ignore ports): localhost vs 127.0.0.1 locally.
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { execFileSync } from 'node:child_process';
import { launch } from './cdp.mjs';

const args = process.argv.slice(2);
const opt = (n, d) => (args.includes(n) ? args[args.indexOf(n) + 1] : d);
const MAIN = opt('--base', 'http://localhost:8095'), PREV = opt('--preview', 'http://127.0.0.1:8095');
const EMAIL = process.env.ADMIN_EMAIL, PW = process.env.ADMIN_PASSWORD;
if (!EMAIL || !PW) { console.error('ADMIN_EMAIL and ADMIN_PASSWORD are required (test container only)'); process.exit(2); }
const w = (ms) => new Promise((r) => setTimeout(r, ms));
let pass = 0, fail = 0; const bad = [];
const ok = (n, c, d = '') => { c ? pass++ : (fail++, bad.push(n + (d ? ' — ' + String(d).slice(0, 240) : ''))); };

// --- fixtures: a tiny static site + hostile archives, built with the system zip/python (no dependencies)
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pixelite-platform-'));
const site = path.join(dir, 'site'); fs.mkdirSync(path.join(site, 'img'), { recursive: true });
fs.writeFileSync(path.join(site, 'index.html'), `<!doctype html><html><head><meta charset="utf-8"><title>Client preview</title><link rel="stylesheet" href="style.css"></head><body><h1>Client site</h1><img src="img/dot.png" alt=""><script>
(async () => {
  const out = { cookie: document.cookie, ls: Object.keys(localStorage), origin: location.origin };
  try { const r = await fetch(${JSON.stringify(MAIN)} + '/admin/projects', { credentials: 'include' }); out.fetchAdmin = r.status; } catch (e) { out.fetchAdmin = 'blocked'; }
  try { out.fetchSame = (await fetch('/admin/projects')).status; } catch (e) { out.fetchSame = 'blocked'; }
  try { out.fetchAccount = (await fetch('/en/account')).status; } catch (e) { out.fetchAccount = 'blocked'; }
  try { await navigator.serviceWorker.register('sw.js'); out.sw = 'registered'; } catch (e) { out.sw = 'blocked'; }
  try { const i = new Image(); i.src = ${JSON.stringify(MAIN)} + '/assets/img/logo.svg'; await new Promise((res) => { i.onload = () => { out.extImg = 'loaded'; res(); }; i.onerror = () => { out.extImg = 'blocked'; res(); }; setTimeout(res, 1500); }); } catch (e) { out.extImg = 'err'; }
  window.__probe = out; document.body.setAttribute('data-done', '1');
})();
</script></body></html>`);
fs.writeFileSync(path.join(site, 'style.css'), 'h1{color:#1b45ff}');
fs.writeFileSync(path.join(site, 'img/dot.png'), Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64'));
execFileSync('zip', ['-qr', path.join(dir, 'good.zip'), 'index.html', 'style.css', 'img'], { cwd: site });
execFileSync('python3', ['-c', `
import zipfile
d=${JSON.stringify(dir)}
z=zipfile.ZipFile(d+'/evil.zip','w'); z.writestr('index.html','ok'); z.writestr('../escape.html','x'); z.close()
z=zipfile.ZipFile(d+'/php.zip','w'); z.writestr('index.html','ok'); z.writestr('shell.php','<?php phpinfo();'); z.close()
z=zipfile.ZipFile(d+'/link.zip','w'); i=zipfile.ZipInfo('link.html'); i.create_system=3; i.external_attr=(0o120777<<16); z.writestr(i,'/etc/passwd'); z.writestr('index.html','ok'); z.close()
`]);

// --- tiny cookie-jar HTTP client
const jar = {};
const cookie = () => Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ');
async function req(url, init = {}) {
  const r = await fetch(url, { redirect: 'manual', ...init, headers: { ...(init.headers || {}), cookie: cookie() } });
  for (const c of r.headers.getSetCookie?.() || []) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); }
  return r;
}
const csrfOf = (html) => (html.match(/name="_csrf" value="([a-f0-9]+)"/) || [])[1];
const form = (o) => ({ method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(o) });
const getText = async (p) => (await req(MAIN + p)).text();

const chrome = await launch();
try {
  // ---------------------------------------------------------------- staff login (HTTP) + cookie flags
  const sc = (await fetch(MAIN + '/en/login')).headers.getSetCookie().join(' | ');
  ok('session cookie is HttpOnly + SameSite=Lax', /HttpOnly/i.test(sc) && /SameSite=Lax/i.test(sc), sc);
  let html = await getText('/admin/login');
  let r = await req(MAIN + '/admin/login', form({ _csrf: csrfOf(html), email: EMAIL, password: PW }));
  ok('staff login works', r.status === 303 && r.headers.get('location') === '/admin', r.status);

  // ---------------------------------------------------------------- staff area pages in the new design: every section, desktop + phone
  const ap = await chrome.newPage({ width: 1440, height: 900 });
  await ap.goto(MAIN + '/admin/login'); await w(300);
  await ap.eval(`document.getElementById('a-email').value=${JSON.stringify(EMAIL)}; document.getElementById('a-pass').value=${JSON.stringify(PW)}; true`);
  await ap.tap('main form button[type=submit]'); await w(1200);
  ok('browser: staff session is live', (await ap.eval('location.pathname')) === '/admin');
  for (const [vw, vh, mobile] of [[1440, 900, false], [390, 844, true]]) {
    const p = await chrome.newPage({ width: vw, height: vh, mobile, dpr: mobile ? 2 : 1 });
    await p.goto(MAIN + '/admin/login'); await w(250);
    await p.eval(`document.getElementById('a-email').value=${JSON.stringify(EMAIL)}; document.getElementById('a-pass').value=${JSON.stringify(PW)}; true`);
    await p.tap('main form button[type=submit]'); await w(1000);
    for (const u of ['/admin', '/admin/customers', '/admin/projects', '/admin/projects/new', '/admin/careers', '/admin/careers/new', '/admin/templates', '/admin/templates/lumora']) {
      await p.goto(MAIN + u); await w(300);
      const a = await p.eval(`({ sw: document.documentElement.scrollWidth, W: innerWidth, h1: document.querySelectorAll('h1').length, css: !!document.querySelector('link[href*="/assets/site.css"]'), nav: document.querySelectorAll('.admin__nav a').length })`);
      ok(`admin ${u} @${vw}: new design, one h1, no horizontal scroll, section navigation`, a.css && a.h1 >= 1 && a.sw <= a.W + 1 && a.nav === 5, JSON.stringify(a));
      ok(`admin ${u} @${vw}: no console errors / failed requests / CSP violations`, !p.log.console.length && !p.log.exceptions.length && !p.log.failed.length && !p.log.badResponses.length, JSON.stringify([...p.log.console, ...p.log.badResponses]).slice(0, 200));
    }
    await p.close();
  }

  // ---------------------------------------------------------------- careers: create -> publish -> public (4 languages) -> unpublish -> delete (confirmation)
  await ap.goto(MAIN + '/admin/careers/new'); await w(300);
  await ap.eval(`(() => { const set=(i,v)=>{document.getElementById(i).value=v;}; set('t-en','Front-end developer'); set('d-en','Build fast, accessible websites.\\n\\nWork with the founder.'); set('t-ru','Фронтенд-разработчик'); set('d-ru','Создавать быстрые доступные сайты.'); return true; })()`);
  await ap.tap('main form button[type=submit]'); await w(900);
  const cid = (await ap.eval('location.pathname')).match(/\/admin\/careers\/(\d+)/)?.[1];
  ok('careers: a vacancy is created as a draft through the form', !!cid);
  for (const lg of ['en', 'cs', 'ru', 'uk']) ok(`careers (${lg}): a draft is not public`, !(await (await fetch(`${MAIN}/${lg}/careers`)).text()).includes('Front-end developer'));
  html = await getText(`/admin/careers/${cid}`);
  r = await req(`${MAIN}/admin/careers/${cid}/publish`, form({ _csrf: csrfOf(html) }));
  ok('careers: publish', r.status === 303);
  for (const [lg, want] of [['en', 'Front-end developer'], ['cs', 'Front-end developer'], ['ru', 'Фронтенд-разработчик'], ['uk', 'Front-end developer']]) {
    const t = await (await fetch(`${MAIN}/${lg}/careers`)).text();
    ok(`careers (${lg}): the published vacancy shows (${want}) with an apply mailto`, t.includes(want) && t.includes('mailto:') && t.includes('class="vacancy"'));
  }
  const cp = await chrome.newPage({ width: 390, height: 844, mobile: true, dpr: 2 });
  await cp.setCookie(MAIN, 'pixelite_consent', encodeURIComponent(JSON.stringify({ v: 1, c: {}, t: Math.floor(Date.now() / 1000) })));
  await cp.goto(MAIN + '/ru/careers'); await w(400);
  ok('careers page on a phone: no horizontal scroll, vacancy visible, no console errors', await cp.eval('document.documentElement.scrollWidth <= innerWidth + 1 && !!document.querySelector(".vacancy")') && !cp.log.console.length && !cp.log.exceptions.length);
  await ap.goto(MAIN + `/admin/careers/${cid}/delete`); await w(300);
  ok('careers: delete asks for confirmation (nothing deleted by the GET)', await ap.eval(`/Delete this vacancy/.test(document.body.innerText)`) && (await (await fetch(`${MAIN}/en/careers`)).text()).includes('Front-end developer'));
  await ap.tap('main form button[type=submit]'); await w(900);
  ok('careers: confirmed delete removes it from the public page', !(await (await fetch(`${MAIN}/en/careers`)).text()).includes('Front-end developer'));

  // ---------------------------------------------------------------- customers: register + sign in over real HTTP, account page isolation
  const cj = {}; const creq = async (url, init = {}) => { const rr = await fetch(url, { redirect: 'manual', ...init, headers: { ...(init.headers || {}), cookie: Object.entries(cj).map(([k, v]) => `${k}=${v}`).join('; ') } }); for (const c of rr.headers.getSetCookie?.() || []) { const [kv] = c.split(';'); const i = kv.indexOf('='); cj[kv.slice(0, i)] = kv.slice(i + 1); } return rr; };
  const reg = async (lang, name, mail) => {
    const h = await (await creq(`${MAIN}/${lang}/register`)).text(); await w(2300);
    return creq(`${MAIN}/${lang}/register`, form({ _csrf: csrfOf(h), name, email: mail, password: 'a long enough password', password2: 'a long enough password', consent: '1' }));
  };
  r = await reg('ru', 'Иван Тест', 'ivan@example.cz');
  ok('customer: registration (Russian UI) → 303 /ru/login', r.status === 303 && r.headers.get('location') === '/ru/login', r.headers.get('location'));
  const lh = await (await creq(`${MAIN}/ru/login`)).text();
  ok('customer: login page shows the flash and the DISABLED Dreamers placeholder', lh.includes('Спасибо') && /auth-alt__btn[^>]*disabled/.test(lh) && lh.includes('Продолжить через Dreamers'));
  r = await creq(`${MAIN}/ru/login?next=https://evil.example`, form({ _csrf: csrfOf(lh), email: 'ivan@example.cz', password: 'a long enough password' }));
  ok('customer: login succeeds and ignores a hostile ?next', r.status === 303 && r.headers.get('location') === '/ru/account', r.headers.get('location'));
  ok('customer: account page shows only the customer\'s own profile', (await (await creq(`${MAIN}/ru/account`)).text()).includes('ivan@example.cz'));
  r = await creq(`${MAIN}/admin`); ok('customer session cannot open the staff area', r.status === 302 && r.headers.get('location') === '/admin/login');
  r = await creq(`${MAIN}/admin/customers`); ok('customer session cannot open /admin/customers', r.status === 302 && r.headers.get('location') === '/admin/login');
  html = await getText('/admin/customers'); ok('staff: the customers list shows the new customer (no password hash)', html.includes('ivan@example.cz') && !html.includes('$2y$'));

  // ---------------------------------------------------------------- projects: uploads over real multipart, hostile archives, preview origin
  html = await getText('/admin/projects/new');
  r = await req(MAIN + '/admin/projects', form({ _csrf: csrfOf(html), name: 'Platform test project', notes: 'private note', customer_email: 'ivan@example.cz' }));
  const pid = (r.headers.get('location') || '').match(/\/admin\/projects\/(\d+)/)?.[1];
  ok('project: created and linked to the customer account', !!pid, r.status);
  const upload = async (file) => {
    const pg = await getText(`/admin/projects/${pid}`);
    const fd = new FormData(); fd.set('_csrf', csrfOf(pg)); fd.set('mode', 'replace'); fd.set('zip', new Blob([fs.readFileSync(path.join(dir, file))], { type: 'application/zip' }), 'site.zip');
    const res = await req(`${MAIN}/admin/projects/${pid}/upload`, { method: 'POST', body: fd }); return res.status;
  };
  for (const [f, label] of [['evil.zip', 'path traversal ../'], ['php.zip', 'a PHP file'], ['link.zip', 'a symlink entry']]) ok(`upload: a ZIP with ${label} is refused (422)`, (await upload(f)) === 422);
  ok('upload: a valid ZIP is accepted', (await upload('good.zip')) === 303);
  let pg = await getText(`/admin/projects/${pid}`);
  ok('project page: 3 files stored, link still OFF, no share URL shown yet, no iframe on the admin origin', /3 file\(s\)/.test(pg) && !/\/p\/[A-Za-z0-9_-]{43}/.test(pg) && !/<iframe/.test(pg));
  r = await req(`${MAIN}/admin/projects/${pid}/access`, form({ _csrf: csrfOf(pg), access: 'on' }));
  pg = await getText(`/admin/projects/${pid}`);
  const purl = (pg.match(/https?:\/\/[^"<\s]+\/p\/[A-Za-z0-9_-]{43}\//) || [])[0];
  ok('the share URL lives on the separate preview origin', !!purl && purl.startsWith(PREV), purl);
  const tp = new URL(purl).pathname;
  r = await fetch(PREV + tp + 'index.html');
  const hd = Object.fromEntries(r.headers);
  ok('preview: anonymous access works; strict CSP, no-referrer, no-store, nosniff, no cookie', r.status === 200 && /connect-src 'self'/.test(hd['content-security-policy']) && hd['referrer-policy'] === 'no-referrer' && /no-store/.test(hd['cache-control']) && hd['x-content-type-options'] === 'nosniff' && !hd['set-cookie'], JSON.stringify(hd));
  ok('isolation: the same path on the main origin is 404; the preview origin serves no application', (await fetch(MAIN + tp + 'index.html')).status === 404 && (await fetch(PREV + '/admin/login')).status === 404 && (await fetch(PREV + '/en/')).status === 404);
  // browser: staff is signed in on MAIN; the client page runs on PREV
  await ap.goto(PREV + tp); for (let i = 0; i < 40 && (await ap.eval(`document.body && document.body.getAttribute('data-done')`)) !== '1'; i++) await w(250);
  const pr = JSON.parse((await ap.eval('JSON.stringify(window.__probe || null)')) || 'null') || {};
  ok('preview in the browser: the client page (inline script, relative CSS and image) works', (await ap.eval(`getComputedStyle(document.querySelector('h1')).color`)) === 'rgb(27, 69, 255)' && pr.origin === PREV, JSON.stringify(pr));
  ok('isolation: scripts in the preview see no Pixelite cookie and no shared storage', pr.cookie === '' && Array.isArray(pr.ls) && pr.ls.length === 0, JSON.stringify(pr));
  ok('isolation: the preview cannot call the staff area (CSP/CORS), cannot register a service worker, cannot load the main origin\'s images', String(pr.fetchAdmin).startsWith('blocked') && pr.fetchSame === 404 && pr.fetchAccount === 404 && pr.sw === 'blocked' && pr.extImg === 'blocked', JSON.stringify(pr));
  await ap.goto(MAIN + '/admin/projects'); await w(300);
  ok('the staff session is untouched after opening a hostile page on the preview origin', (await ap.eval('location.pathname')) === '/admin/projects');
  await ap.goto(MAIN + '/en/login'); await w(300);
  const framed = await ap.eval(`(async () => { const f = document.createElement('iframe'); f.src = ${JSON.stringify(PREV + tp)}; document.body.appendChild(f); await new Promise(r => setTimeout(r, 1500)); try { return f.contentDocument ? 'accessible' : 'null'; } catch (e) { return 'cross-origin'; } })()`);
  ok('the preview cannot be framed into a Pixelite page', framed !== 'accessible', framed);
  // customer sees only their own project, with the preview link
  const acc = await (await creq(`${MAIN}/en/account`)).text();
  ok('customer account: shows the customer\'s own project with its preview link', acc.includes('Platform test project') && acc.includes(tp));
  // revoke / regenerate / delete
  r = await req(`${MAIN}/admin/projects/${pid}/token`, form({ _csrf: csrfOf(pg) }));
  ok('regenerate: the old link dies at once', (await fetch(PREV + tp + 'index.html')).status === 404);
  pg = await getText(`/admin/projects/${pid}`); const tp2 = new URL((pg.match(/https?:\/\/[^"<\s]+\/p\/[A-Za-z0-9_-]{43}\//) || [])[0]).pathname;
  ok('regenerate: the new link works', (await fetch(PREV + tp2 + 'index.html')).status === 200 && tp2 !== tp);
  r = await req(`${MAIN}/admin/projects/${pid}/access`, form({ _csrf: csrfOf(pg), access: 'off' }));
  ok('switching the link off revokes access', (await fetch(PREV + tp2 + 'index.html')).status === 404);
  const del = await req(`${MAIN}/admin/projects/${pid}/delete`, form({ _csrf: csrfOf(await getText(`/admin/projects/${pid}/delete`)) }));
  ok('delete: project and files are removed', del.status === 303 && (await req(`${MAIN}/admin/projects/${pid}`)).status === 404);
  await ap.close();
} finally {
  await chrome.close();
  fs.rmSync(dir, { recursive: true, force: true });
}
console.log(`platform suite: ${pass} passed, ${fail} failed`); bad.forEach((b) => console.log('  ✗ ' + b)); process.exit(fail ? 1 : 0);
