// usage: node tests/browser/styles-diff.mjs baseline.json current.json
// Compares computed styles element-by-element (keyed tag.class#n). New/removed elements are listed separately.
import fs from 'node:fs';
const [, , a, b] = process.argv;
const A = JSON.parse(fs.readFileSync(a)), B = JSON.parse(fs.readFileSync(b));
let changed = 0, removed = 0, added = 0; const samples = [];
for (const page of Object.keys(A)) {
  for (const key of Object.keys(A[page])) {
    if (!B[page]?.[key]) { removed++; samples.push(`REMOVED ${page} ${key}`); continue; }
    for (const prop of Object.keys(A[page][key])) {
      if (A[page][key][prop] !== B[page][key][prop]) { changed++; samples.push(`${page} ${key} ${prop}: ${A[page][key][prop]} -> ${B[page][key][prop]}`); }
    }
  }
  for (const key of Object.keys(B[page] || {})) if (!A[page][key]) added++;
}
console.log(`changed properties: ${changed}, removed elements: ${removed}, added elements: ${added}`);
console.log(samples.slice(0, +(process.env.N || 25)).join('\n'));
