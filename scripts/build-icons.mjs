// Rebuilds the raster icons from the logo mark (host-only; needs Node + Google Chrome).
//   node scripts/build-icons.mjs
// Writes public/favicon.ico (16/32/48 PNG-in-ICO), assets/icons/apple-touch-icon.png (180, opaque, full-bleed
// because iOS applies its own mask) and assets/icons/icon-512.png. The vector files (img/logo.svg, icons/favicon.svg)
// are hand-kept; keep the geometry below in sync with them.
import { execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const chrome = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const INK = '#0c0d10', PAPER = '#f4f5f7', ACCENT = '#4F6BFF';
const cells = [[0, 0], [15, 0], [0, 15], [30, 15], [0, 30], [15, 30], [30, 30], [0, 45]];
const mark = `<g fill="${PAPER}">${cells.map(([x, y]) => `<rect x="${x}" y="${y}" width="12" height="12" rx="2.5"/>`).join('')}</g><rect x="30" y="0" width="12" height="12" rx="2.5" fill="${ACCENT}"/>`;

// size px, tile corner radius as a share of the side (0 = full-bleed), mark height as a share of the side
function render(size, radius, share, out) {
  const s = (size * share) / 57, x = (size - 42 * s) / 2, y = (size - 57 * s) / 2;
  const html = `<!doctype html><meta charset="utf-8"><style>html,body{margin:0;background:transparent}</style>` +
    `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="display:block">` +
    `<rect width="${size}" height="${size}" rx="${size * radius}" fill="${INK}"/><g transform="translate(${x} ${y}) scale(${s})">${mark}</g></svg>`;
  const dir = mkdtempSync(join(tmpdir(), 'pxicon-'));
  const page = join(dir, 'i.html');
  writeFileSync(page, html);
  execFileSync(chrome, ['--headless=new', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1', '--default-background-color=00000000',
    `--window-size=${size},${size}`, `--screenshot=${out}`, `file://${page}`], { stdio: 'ignore' });
}

const icons = join(root, 'public/assets/icons');
render(180, 0, 0.62, join(icons, 'apple-touch-icon.png'));
render(512, 0.22, 0.6, join(icons, 'icon-512.png'));

const sizes = [16, 32, 48], pngs = sizes.map((n) => {
  const f = join(mkdtempSync(join(tmpdir(), 'pxico-')), `${n}.png`);
  render(n, 0.22, 0.66, f);
  return readFileSync(f);
});
const head = Buffer.alloc(6); head.writeUInt16LE(1, 2); head.writeUInt16LE(sizes.length, 4);
let off = 6 + 16 * sizes.length;
const dir = Buffer.concat(sizes.map((n, i) => {
  const e = Buffer.alloc(16); e[0] = n; e[1] = n; e.writeUInt16LE(1, 4); e.writeUInt16LE(32, 6);
  e.writeUInt32LE(pngs[i].length, 8); e.writeUInt32LE(off, 12); off += pngs[i].length; return e;
}));
writeFileSync(join(root, 'public/favicon.ico'), Buffer.concat([head, dir, ...pngs]));
console.log('icons written');
