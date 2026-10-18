// Host-only tool (NOT shipped, not part of the Endora artifact): records the gallery previews of catalog designs.
//
//   node scripts/capture-template-previews.mjs [--base http://localhost:8080] [slug ...]
//
// A design's preview is the design itself: this loads /en/templates/<slug>/demo from the running Pixelite app (the
// same document customers see in the live preview), records the intro, a cursor sweep over the hero and a scroll-through,
// and writes public/assets/templates/<slug>/poster-640.jpg, poster-1280.jpg and preview.webm (muted, 640 px wide).
// It also fails (exit 1) if the page logs console errors, throws, requests anything that is not same-origin, or
// overflows horizontally at 390 / 768 / 1440 px. Slugs default to every file in templates/demos/.
// Needs: Node 22+, Google Chrome, ffmpeg (brew install ffmpeg), the app running (docker compose up -d).
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { launch } from '../tests/browser/cdp.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const base = args.includes('--base') ? args[args.indexOf('--base') + 1] : 'http://localhost:8080';
const only = args.filter((a, i) => !a.startsWith('--') && args[i - 1] !== '--base');
const slugs = fs.readdirSync(path.join(root, 'templates/demos')).filter((f) => f.endsWith('.html')).map((f) => f.slice(0, -5)).filter((s) => !only.length || only.includes(s)).sort();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const chrome = await launch();
let failed = 0;
for (const slug of slugs) {
  const url = `${base}/en/templates/${slug}/demo`;
  const problems = [];
  for (const [w, h] of [[390, 844], [768, 1024], [1440, 900]]) {
    const p = await chrome.newPage({ width: w, height: h, mobile: w < 700 });
    await p.goto(url); await sleep(4200);
    const overflow = await p.eval('document.documentElement.scrollWidth - document.documentElement.clientWidth');
    const external = await p.eval(`[...performance.getEntriesByType('resource')].map(r => r.name).filter(n => !n.startsWith(${JSON.stringify(new URL(base).origin)}) && !n.startsWith('data:'))`);
    if (overflow > 1) problems.push(`${w}px: horizontal overflow ${overflow}px`);
    if (p.log.exceptions.length || p.log.console.length) problems.push(`${w}px: ${JSON.stringify([...p.log.exceptions, ...p.log.console]).slice(0, 240)}`);
    if (p.log.failed.length || p.log.badResponses.length) problems.push(`${w}px: failed requests ${JSON.stringify([...p.log.failed, ...p.log.badResponses]).slice(0, 240)}`);
    if (external.length) problems.push(`${w}px: external requests ${external.join(', ')}`);
    await p.close();
  }
  if (problems.length) { failed++; console.log(`FAIL ${slug}\n   - ${problems.join('\n   - ')}`); continue; }

  const out = path.join(root, 'public/assets/templates', slug);
  fs.mkdirSync(out, { recursive: true });
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), `frames-${slug}-`));
  const page = await chrome.newPage({ width: 1280, height: 800 });
  let n = 0;
  const frame = async () => { await page.screenshot(path.join(tmp, `f${String(n++).padStart(3, '0')}.png`), { full: false }); };
  await page.goto(url);                               // from here on the page is live: record the intro too
  for (let i = 0; i < 34; i++) { await frame(); await sleep(40); }        // loader + hero reveals
  await sleep(300);
  await page.screenshot(path.join(tmp, 'poster.png'), { full: false });   // poster = settled first viewport
  for (let i = 0; i < 26; i++) {                                           // cursor sweep over the hero
    const t = i / 25;
    await page.move(Math.round(380 + t * 520), Math.round(300 + Math.sin(t * Math.PI * 2) * 160 + t * 120));
    await sleep(30); await frame();
  }
  const total = await page.eval('Math.max(0, Math.min(document.documentElement.scrollHeight - innerHeight, innerHeight * 3.4))');
  for (let i = 0; i < 40; i++) {                                           // scroll-through and back
    const t = i / 39, p = t < .55 ? t / .55 : 1 - (t - .55) / .45, e = p * p * (3 - 2 * p);
    await page.eval(`window.scrollTo(0, ${Math.round(total * e)})`);
    await sleep(120); await frame();
  }
  for (const w of [640, 1280]) execFileSync('ffmpeg', ['-y', '-v', 'error', '-i', path.join(tmp, 'poster.png'), '-vf', `scale=${w}:-2`, '-q:v', '4', path.join(out, `poster-${w}.jpg`)]);
  execFileSync('ffmpeg', ['-y', '-v', 'error', '-framerate', '10', '-i', path.join(tmp, 'f%03d.png'), '-vf', 'scale=640:-2', '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', '38', '-an', '-pix_fmt', 'yuv420p', path.join(out, 'preview.webm')]);
  const bytes = fs.readdirSync(out).filter((f) => /^(poster|preview)/.test(f)).reduce((a, f) => a + fs.statSync(path.join(out, f)).size, 0);
  console.log(`ok   ${slug.padEnd(20)} poster+preview ${(bytes / 1024).toFixed(0)} KB  (${n} frames)`);
  await page.close();
  fs.rmSync(tmp, { recursive: true, force: true });
}
await chrome.close();
if (failed) { console.error(`${failed} design(s) failed the quality gate`); process.exit(1); }
