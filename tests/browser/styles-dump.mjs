// Dumps computed styles of every element, so a refactor can be diffed against a baseline.
// usage: node tests/browser/styles-dump.mjs <out.json> [baseUrl]
import fs from 'node:fs';
import { launch } from './cdp.mjs';

const [, , out, base = 'http://localhost:8080'] = process.argv;
const CONSENT = encodeURIComponent(JSON.stringify({ v: 1, c: {}, t: Math.floor(Date.now() / 1000) }));
const PAGES = ['/en/', '/cs/', '/en/order', '/en/contact', '/en/privacy', '/en/portfolio', '/en/zzz', '/admin/login'];
const WIDTHS = [1440, 375];
const PROPS = ['color', 'backgroundColor', 'backgroundImage', 'borderTopColor', 'borderRightColor', 'borderBottomColor', 'borderLeftColor',
  'borderTopWidth', 'borderRadius', 'boxShadow', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textAlign', 'textDecorationLine',
  'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'display', 'position', 'opacity', 'transform'];

const browser = await launch();
const result = {};
for (const width of WIDTHS) {
  const page = await browser.newPage({ width, height: 900, mobile: width < 700, dpr: 1, scheme: 'light' });
  await page.setCookie(base, 'pixelite_consent', CONSENT);   // no banner: it is a new component, compared separately
  for (const p of PAGES) {
    await page.goto(base + p);
    result[`${width}${p}`] = await page.eval(`(() => {
      const props = ${JSON.stringify(PROPS)}; const out = {}; const seen = {};
      const SKIP = '#cookie-banner, #cookie-dialog, footer, .header__theme, .header__el--theme, .skip-link, script, style, template';
      const id = (e) => e.tagName.toLowerCase() + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\\s+/)[0] : '');
      for (const el of document.querySelectorAll('body *')) {
        if (el.closest(SKIP)) continue;
        const chain = []; for (let n = el, i = 0; n && n !== document.body && i < 3; n = n.parentElement, i++) chain.unshift(id(n));
        const key = chain.join(' > '); seen[key] = (seen[key] || 0) + 1;
        const cs = getComputedStyle(el), r = el.getBoundingClientRect();
        const o = {}; for (const p of props) o[p] = cs[p];
        o.w = Math.round(r.width); o.h = Math.round(r.height);
        out[key + '#' + seen[key]] = o;
      }
      return out;
    })()`);
  }
  await page.close();
}
await browser.close();
fs.writeFileSync(out, JSON.stringify(result));
console.log('elements per page:', Object.entries(result).map(([k, v]) => `${k}=${Object.keys(v).length}`).join(' '));
