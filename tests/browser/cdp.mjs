// Minimal Chrome DevTools Protocol driver (no dependencies; Node 22+ and a local Chrome).
// Each page lives in its own incognito-like browser context, so cookies/localStorage never leak between scenarios.
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const CHROME = process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export async function launch() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pixelite-chrome-'));
  const proc = spawn(CHROME, ['--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run',
    '--remote-debugging-port=0', `--user-data-dir=${dir}`, 'about:blank'], { stdio: 'ignore' });
  let port;
  for (let i = 0; i < 80 && !port; i++) {
    try { port = fs.readFileSync(path.join(dir, 'DevToolsActivePort'), 'utf8').split('\n')[0]; } catch { await sleep(150); }
  }
  if (!port) throw new Error('Chrome did not start');
  const { webSocketDebuggerUrl } = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json();
  const ws = new WebSocket(webSocketDebuggerUrl);
  await new Promise((r) => (ws.onopen = r));

  let id = 0;
  const pending = new Map();
  const listeners = new Map(); // sessionId -> fn(method, params)
  ws.onmessage = (m) => {
    const d = JSON.parse(m.data);
    if (d.id && pending.has(d.id)) {
      const { res, rej } = pending.get(d.id); pending.delete(d.id);
      d.error ? rej(new Error(`${d.error.message}`)) : res(d.result);
    } else if (d.method && listeners.has(d.sessionId)) listeners.get(d.sessionId)(d.method, d.params);
  };
  const send = (method, params = {}, sessionId) => new Promise((res, rej) => {
    const i = ++id; pending.set(i, { res, rej });
    ws.send(JSON.stringify({ id: i, method, params, ...(sessionId ? { sessionId } : {}) }));
  });

  async function newPage(opts = {}) {
    const o = { width: 1280, height: 800, dpr: 1, mobile: false, ua: null, scheme: 'light', reducedMotion: false, ...opts };
    const { browserContextId } = await send('Target.createBrowserContext', { disposeOnDetach: true });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const s = (method, params) => send(method, params, sessionId);

    const log = { console: [], exceptions: [], failed: [], badResponses: [], requests: 0 };
    const inflight = new Map();
    let loaded = false;
    listeners.set(sessionId, (method, p) => {
      if (method === 'Runtime.exceptionThrown') log.exceptions.push(p.exceptionDetails.exception?.description || p.exceptionDetails.text);
      if (method === 'Runtime.consoleAPICalled' && ['error', 'assert'].includes(p.type)) log.console.push(p.args.map((a) => a.value ?? a.description).join(' '));
      if (method === 'Log.entryAdded' && p.entry.level === 'error') log.console.push(`${p.entry.source}: ${p.entry.text} ${p.entry.url || ''}`);
      if (method === 'Network.requestWillBeSent') { log.requests++; inflight.set(p.requestId, p.request.url); }
      if (method === 'Network.loadingFailed' && !p.canceled) log.failed.push(`${inflight.get(p.requestId)} (${p.errorText})`);
      if (method === 'Network.responseReceived' && p.response.status >= 400) log.badResponses.push({ url: p.response.url, status: p.response.status, type: p.type });
      if (method === 'Page.loadEventFired') loaded = true;
    });
    for (const d of ['Page', 'Runtime', 'Log', 'Network']) await s(`${d}.enable`);
    const ua = o.ua || (o.mobile ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' : null);
    if (ua) await s('Emulation.setUserAgentOverride', { userAgent: ua });
    if (o.mobile) await s('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
    await s('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: o.scheme }, { name: 'prefers-reduced-motion', value: o.reducedMotion ? 'reduce' : 'no-preference' }] });
    const metrics = (w, h) => s('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: o.dpr, mobile: o.mobile, screenWidth: w, screenHeight: h });
    await metrics(o.width, o.height);

    const page = {
      log, opts: o,
      async goto(url) {
        loaded = false; log.console.length = log.exceptions.length = log.failed.length = log.badResponses.length = 0;
        const nav = await s('Page.navigate', { url });
        for (let i = 0; i < 200 && !loaded; i++) await sleep(50);
        await page.eval('document.fonts.ready.then(() => true)');
        return nav;
      },
      async eval(expr) {
        const r = await s('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true });
        if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
        return r.result.value;
      },
      /** Everything stored for the current page: cookies (with attributes), localStorage keys/values, sessionStorage keys. */
      async storage() {
        const href = await page.eval('location.href');
        const { cookies } = await s('Network.getCookies', { urls: [href] });
        const ls = await page.eval(`(() => { try { return Object.fromEntries(Object.keys(localStorage).map((k) => [k, localStorage.getItem(k)])); } catch (e) { return {}; } })()`);
        const ss = await page.eval(`(() => { try { return Object.keys(sessionStorage); } catch (e) { return []; } })()`);
        const idb = await page.eval(`(async () => (indexedDB.databases ? (await indexedDB.databases()).map((d) => d.name) : []))()`);
        const caches_ = await page.eval(`(async () => (window.caches ? await caches.keys() : []))()`);
        const sw = await page.eval(`(async () => (navigator.serviceWorker ? (await navigator.serviceWorker.getRegistrations()).length : 0))()`);
        return { cookies: cookies.map((c) => ({ name: c.name, session: c.session, days: c.session ? null : Math.round(((c.expires - Date.now() / 1000) / 86400) * 10) / 10, httpOnly: c.httpOnly, secure: c.secure, sameSite: c.sameSite, path: c.path })), localStorage: ls, sessionStorage: ss, indexedDB: idb, caches: caches_, serviceWorkers: sw };
      },
      /** Run JS before any page script on every navigation (e.g. to simulate blocked storage). */
      async initScript(src) { await s('Page.addScriptToEvaluateOnNewDocument', { source: src }); },
      /** Pre-set a cookie for the page's origin (e.g. a consent choice) before navigating. */
      async setCookie(url, name, value) { await s('Network.setCookie', { url, name, value }); },
      async reload() { loaded = false; await s('Page.reload'); for (let i = 0; i < 200 && !loaded; i++) await sleep(50); await page.eval('document.fonts.ready.then(() => true)'); },
      /** Real pointer/touch tap at the centre of the first element matching selector. */
      async tap(selector) {
        const r = await page.eval(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); if (!e) return null; e.scrollIntoView({block:'center'}); const b = e.getBoundingClientRect(); return {x:b.x+b.width/2, y:b.y+b.height/2}; })()`);
        if (!r) throw new Error(`tap: no element ${selector}`);
        const base = { x: r.x, y: r.y };
        if (o.mobile) {
          await s('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [base] });
          await s('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else {
          await s('Input.dispatchMouseEvent', { type: 'mousePressed', ...base, button: 'left', clickCount: 1 });
          await s('Input.dispatchMouseEvent', { type: 'mouseReleased', ...base, button: 'left', clickCount: 1 });
        }
        await sleep(250);
      },
      async key(key, code = key) {
        const vk = key === 'Tab' ? 9 : key === 'Escape' ? 27 : key === 'Enter' ? 13 : key === ' ' ? 32 : 0;
        const text = key === 'Enter' ? '\r' : key === ' ' ? ' ' : undefined;
        await s('Input.dispatchKeyEvent', { type: text ? 'keyDown' : 'rawKeyDown', key, code, windowsVirtualKeyCode: vk, ...(text ? { text } : {}) });
        await s('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: vk });
        await sleep(120);
      },
      async setViewport(w, h) { await metrics(w, h); await sleep(150); },
      async screenshot(file, { full = true } = {}) {
        if (full) {
          const h = await page.eval('Math.min(document.documentElement.scrollHeight, 7000)');
          await metrics(o.width, h); await sleep(200);
        }
        const r = await s('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(file, Buffer.from(r.data, 'base64'));
        if (full) await metrics(o.width, o.height);
      },
      async close() { listeners.delete(sessionId); await send('Target.disposeBrowserContext', { browserContextId }).catch(() => {}); },
    };
    return page;
  }

  return {
    newPage,
    async close() {
      try { ws.close(); } catch {}
      const exited = new Promise((r) => proc.once('exit', r));
      proc.kill();
      await Promise.race([exited, sleep(3000)]);
      fs.rmSync(dir, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
    },
  };
}
