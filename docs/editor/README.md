# Site builder (VvvebJs, free-form) – EXPERIMENTAL

`/editor/projects/{id}/builder` (same flag, same auth as the Vantage editor): a customer builds a website from scratch. VvvebJs is the engine; the shell is Pixelite's.

**What the customer can do:** add elements (headings, paragraphs, links, lists, tables, images, cards, grids, buttons, forms …), Bootstrap 5 *blocks* and ready-made page *sections* (navigation, heroes, features, gallery, pricing, team, quotes, FAQ, numbers, call to action, contact, footer – original neutral placeholder content, local placeholder pictures); drag & drop or click to insert; select, move up/down/into the parent, duplicate, delete (undo/redo as the safety net); type on the page with a text toolbar (bold, italic, underline, strike, link, colour, size, font, alignment); change content, **CSS styles** (per element, with hover/pressed/focus states and **responsive breakpoints**: the phone/tablet buttons write @media rules), and attributes in the right panel; edit the HTML of one element or the whole page and the page CSS in the code view; layer tree and breadcrumb; several pages (up to 10); an image library with upload (re-encoded), picker and delete; preview of the saved page; save draft with unsaved-changes tracking.

**What it deliberately cannot do – customer JavaScript does not exist in this design.** No `<script>`, no event attributes (`onclick` …), no embeds (iframe, video, audio, svg, maps), no external images/fonts/stylesheets/URLs, no Bootstrap JavaScript widgets (carousel, accordion, modal, dropdown, collapsing navbar – the sections use plain `<details>` for FAQ and a static navbar), and forms cannot submit anywhere (their `action` is removed; the sample forms use plain buttons). Interactive behaviour and form handling would need a separate, isolated origin per customer and a form backend; that is not a switch on this feature.

## Security model of the builder (different from the Vantage editor: here the customer authors markup)
1. **Server owns the document.** The browser sends `html` (body fragment), `css` and `title` as text; the server never accepts a `<head>`. `src/Editor/Builder/HtmlSanitizer.php` (DOMDocument parse → allowlist → own serialiser) and `CssSanitizer.php` rebuild both from allowlists; the reply carries the sanitised result and a list of what was removed, and the builder reloads the canvas with exactly what was stored.
   * Elements: allowlist; `script style svg math template noscript iframe object embed base link meta title xmp plaintext video audio …` are removed *with their content*, unknown elements unwrapped. Attributes: allowlist per element; no `on*`, `srcdoc`, `srcset`, `formaction`; form `action/method` removed; `id`/`class` tokens `[A-Za-z0-9_-]`; `data-*`/`aria-*` values without `< > \``. Links: `https/http/mailto/tel/#anchor` only (`rel=noopener noreferrer` added for `_blank`). Images: this project's own uploads and the bundled placeholders only (no external or data: URLs).
   * CSS: property allowlist (~200 properties + custom properties), `@media` and `@keyframes` only, no `@import/@font-face/@charset/…`, no backslash escapes, no `<`, `url()` only to the project's own images/placeholders, no `expression()/behavior/-moz-binding/image-set()`; output is canonical text.
   * **Fixpoint gate:** sanitising the output again must give the same bytes (up to 4 passes), otherwise the save is refused – the tripwire against parser differences (mXSS). Size/complexity limits: 300 KB HTML, 100 KB CSS, 6000 nodes, depth 40, 2000 CSS rules.
   * Stored data is sanitised **again at render time**, so a tampered `site.json` cannot inject anything.
2. **Documents that show customer markup run no script at all.** Canvas and preview are served with `Content-Security-Policy: sandbox allow-same-origin; default-src 'none'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'`. `allow-same-origin` is needed so the editor can reach into the iframe; scripts, forms, popups and top-navigation are off, so even a sanitiser bug cannot execute script. (Measured in Chrome: hostile `onerror`, `<script>`, `javascript:` links and hand-injected script elements run nothing; see `tests/browser/builder.mjs`.)
3. **The builder page keeps the strict page CSP** (`script-src 'self'`, no `unsafe-eval`, no inline script). The VvvebJs property-panel templates are **precompiled at build time** (`scripts/build-editor-templates.mjs` → `px-templates.generated.js`, escaping by default) instead of being compiled in the browser with `new Function()`. Bootstrap 5's JavaScript (MIT) is used in the builder shell only (tabs, modals); Bootstrap's CSS is used inside the canvas only. Page-derived strings that VvvebJs wrote into the UI with `innerHTML` (layer tree titles, element names) are escaped.
4. Same ownership/CSRF/`X-Requested-With`/rate-limit/version rules as the Vantage editor (one shared base class `EditorBase`); per-site optimistic version; image delete refuses images still referenced by a page.

## Builder limitations
* No publishing, no hosting, no custom domains, no export; forms and interactive widgets are not available (see above). A published customer site would need its own origin per customer.
* The sanitiser needs PHP's DOM extension (present in the dev image; **not verified on Endora** – the builder reports "saving is not available" and refuses saves if it is missing). PHP 8.5 on Endora has `Dom\HTMLDocument` (HTML5 parser) that would be a better base than the libxml HTML4 parser; the fixpoint gate compensates today.
* VvvebJs' style manager rewrites the page CSS from the rules it understood: complex hand-written CSS (multi-rule @media blocks, @keyframes) can be lost if the customer edits styles through the panels after loading it. Use the CSS code view for such CSS.
* Elements list is curated (Base HTML + Bootstrap 5 components); upstream's widgets/embeds/server components/icons/sections are not included. Rich text is limited to what the sanitiser keeps (`b i u` + styled spans; no `font`, `strike` tags).
* Only Chrome tested; mobile/touch editing untested; the panels stack below the canvas under 1100 px.
* Pages are addressed by slug inside the builder only (links between pages are not generated; use anchors or external URLs).

# Visual editor – EXPERIMENTAL proof of concept

**Status:** experiment, off by default, never deployed. Draft-only: nothing a customer edits is published or visible to anyone else.
It is **not** license-free: it ships a modified subset of VvvebJs (Apache-2.0, see "Licensing" below).

## What it is
A customer who owns a project (`projects.customer_id`) can open `/editor/projects/{id}` and edit a private draft of the **Vantage** design:
headline lines, intro text, menu labels and links, button label + destination, the demo-card picture (upload) and its description, four colours,
show/hide the demo card, desktop/tablet/phone widths, undo/redo, unsaved-changes tracking, save draft, preview of the saved draft.
VvvebJs supplies the canvas iframe, highlight/selection, inline text editing, the property-panel machinery and the undo history; everything else is Pixelite's.

**Why Vantage:** of the five designs it is the only one whose copy is static HTML (no text generated by JS – Emberveil's headline is typed in by script, Lumora/Asterform/Stillwave rebuild text/layout from JS and per-element inline animation delays), it has real text, links, a real `<img>`, a CSS-variable-friendly colour scheme and almost no script (menu toggle only), so the template's own animation and responsive behaviour can be kept intact. Its limitation: it is one full-screen page, so **reordering sections is not applicable** (only the demo card can be hidden). Azur (10 sections, 26 images, static HTML) would be the better second experiment for section reorder/hide.

## Run it locally
```bash
# 1. .env (git-ignored): EDITOR_ENABLED=true        (documented in .env.example; absent/false = every /editor/… URL is 404)
docker compose up -d --build                         # the image now includes PHP GD (image re-encoding)
# 2. a customer who owns a project: register at /en/register, then in /admin/projects create a project with that customer's e-mail
#    (or create both with a short PHP snippet, see the report)
# 3. sign in at /en/login → /en/account → "Edit visually (experimental)"  (or open /editor/projects/<id>)
```
Try: click the headline → edit in the right panel; double-click it to type on the page; change colours (left panel); click the demo picture → replace image;
Ctrl/⌘ S saves, Ctrl/⌘ Z undo; reload – the draft is back; "Preview" shows the saved draft; the device buttons switch widths.

## Tests
```bash
docker compose exec app php tests/run.php            # includes tests/editor.php (switch, auth, isolation, CSRF, schema, images, escaping, vendored-code checks)
EDITOR_EMAIL=… EDITOR_PASSWORD=… node tests/browser/editor.mjs --base http://localhost:8080 --project <id> [--other <someone-elses-project-id>] [--shots dir]
```
`tests/browser/editor.mjs` refuses non-local hosts and **saves drafts** – use a throw-away local account.

## Security model
* No HTML ever crosses the wire. The browser sends typed values; `src/Editor/Schema.php` validates each against `config/editor.php` (known keys only, length limits, control/bidi characters removed, links limited to `https:`/`http:`/`mailto:`/`tel:`/`#anchor`, colours `#rrggbb`, image ids must exist in *this* project). Documents are rendered on the server with `e()`. So no customer-authored markup or script runs on the Pixelite origin – this is why the editing canvas can be same-origin (VvvebJs needs to reach into the iframe) without the usual risk. If a template ever needs customer-authored HTML/JS, the preview must move to the separate preview origin first.
* Auth/tenancy: every route resolves the project from the signed-in customer's session (`Projects::find` + `customer_id`), unknown/foreign/archived projects are an identical 404; no route accepts a file name, directory or customer id; storage paths come from the project's random 16-hex uid.
* CSRF: session token + required `X-Requested-With: pixelite-editor` header on every mutation; the token is not rotated per save; rate limits on save (120/10 min) and upload (30/h) per customer; optimistic locking (`base_version`, 409 on conflict).
* Images: ≤4 MB, JPEG/PNG/WebP by *content*, ≤12 MP and ≤8000 px (checked from the header before decoding), decoded and re-encoded with GD (drops EXIF/GPS/ICC/trailing data; EXIF orientation applied), max 1600 px, ≤20 per draft; served only through an authenticated route with `nosniff` and a `sandbox` CSP; fails closed when GD is missing.
* CSP: editor page, canvas and preview all use `script-src 'self'` (no `unsafe-eval`, no `unsafe-inline`); `style-src-attr 'unsafe-inline'` only because VvvebJs and the template set style attributes; no third-party requests.
* Drafts live in `storage/projects/<uid>/editor/` – a sibling of `site/`, so the client-preview host never serves them; they are deleted with the project.

## Known limitations / not implemented
* **No publishing, no hosting of customer sites, no custom domains, no multi-site/multi-template support** (one template, hard-coded in `Schema::TEMPLATE`).
* No section reordering (not applicable to Vantage), no adding/removing/cloning elements, no free-form styling, no raw HTML/CSS/JS, no fonts, no page creation, no media library (one image slot), no image cropping, no localisation of the editor UI (English only), no autosave, no multi-user/real-time editing, no draft history/versions beyond one saved version, no "reset draft to template" button (Discard changes reloads the last save).
* Staff cannot open a customer's editor (no admin entry point).
* The canvas is same-origin by necessity (see above); the editor page's strict CSP and the values-only model are what make that acceptable. Re-evaluate before any template with customer HTML.
* Very long headlines wrap (limit 22 characters per line) and can crowd the header on phones.
* Text typed on the canvas is plain text only (`contenteditable="plaintext-only"` where supported, otherwise paste/format events are blocked and the result is flattened to text).
* Untested: Safari/Firefox (only Chrome via CDP), screen-reader behaviour, touch editing on a real device.

## Hosting assumptions to verify before any real use (Endora shared hosting)
* PHP **GD** with JPEG/PNG (and ideally WebP) – the local Docker image now installs it; Endora is untested. Without GD uploads are refused.
* Persistent, writable `storage/projects/` outside `public_html` (already required by the existing projects feature) and enough quota for drafts (≤20 MB images per draft).
* `flock()` and atomic `rename()` on that storage (used for draft saves).
* `upload_max_filesize` / `post_max_size` ≥ 4 MB.
* Session cookies work as for the existing account area.
* Nothing in this experiment needs the preview origin, a database change or a different provider. Publishing customer sites is a separate, much larger question (isolated origins per customer, wildcard DNS/TLS, static export, quotas) that shared hosting probably cannot answer.

## Licensing summary
* **Imported code:** `builder.js`, `undo.js`, `inputs.js` (modified subset of VvvebJs, Apache-2.0, © 2017 Ziadin Givan) + upstream `LICENSE` (unchanged) + `MODIFICATIONS.txt`. Headers retained and marked as modified.
* **Not imported:** the Vvveb CMS (AGPL), upstream `css/`, `fonts/`, `img/`, `demo/`, icons (`Credits.md` lists CC BY / custom-licence icon sets), plugins, Bootstrap (CSS or JS), jQuery. No AGPL code is present.
* **Embedded third-party text in `builder.js`:** the upstream "buildParams" helper carries a "John Resig – MIT" comment; the Resig template compiler itself was removed. Keep the comment.
* **Dependencies added:** none (PHP GD is a PHP extension in the dev image; no npm/Composer packages). Design assets used by the Vantage template are the ones already in the repository (their status is unchanged: see `docs/templates/README.md`, "rights to the sample videos … unconfirmed").
* **Remaining uncertainty:** upstream has no NOTICE file (none reproduced); I did not audit upstream's transitive licence claims beyond the three imported files; have counsel confirm the Apache-2.0 §4 compliance text if this ever ships. The product must not be described as license-free.


---

# VvvebJs (vendored subset) – Pixelite visual editor, EXPERIMENTAL

| | |
|---|---|
| Upstream | https://github.com/givanz/VvvebJs (the **editor library only**; the separate CMS https://github.com/givanz/Vvveb is *not* used) |
| Upstream commit | `1acbab7ebfe3e7b004f1f18c039d26550fc04bd8` (2026-07-01) |
| Licence | Apache License 2.0 – `LICENSE` in this folder is the upstream file, unchanged |
| Copyright | © 2017 Ziadin Givan (header of each file, retained) |
| NOTICE file | upstream has none, so none is reproduced here |

## Files copied (all JavaScript, all modified – each starts with a "MODIFIED BY PIXELITE" notice)

| File | From upstream | Modifications |
|---|---|---|
| `builder.js` | `libs/builder/builder.js` | see below |
| `undo.js` | `libs/builder/undo.js` | notice only (code unchanged) |
| `inputs.js` | `libs/builder/inputs.js` | reduced to the inputs this experiment uses: `Input, TextInput, TextareaInput, CheckboxInput, SelectInput, LinkInput, ColorInput, ToggleInput, SectionInput, ButtonInput`. Removed: the inputs that post to the sample `upload.php`, use jQuery, the sample media gallery, or fetch remote lists (`ImageInput`, `VideoInput`, `SvgInput`, `FileUploadInput`, `HtmlListSelectInput`, `ListInput`, `Autocomplete*`, `TagsInput`, `NoticeInput`, grid/range/number/css-unit/radio/date inputs …). |

### Modifications to `builder.js`
1. **No `new Function()`.** The micro-template compiler (John Resig's, MIT – the notice stays in the file for the code that remains) is replaced by a lookup of plain template functions in `window.PixeliteTemplates` (`../../px-templates.js`), so the editor page needs no `'unsafe-eval'`.
2. **Helper stylesheet path** for the canvas document is configurable (`Vvveb.helpersCss`).
3. **Selection whitelist hooks.** `Vvveb.Builder.resolveTarget(node)` / `canEditText(node)` (defaults = upstream behaviour) are consulted by the hover, click and double-click handlers, so the host page decides which elements can be highlighted, selected or typed into.
4. **Leave warning** in the iframe asks the host page (`Vvveb.Builder.confirmLeave`) instead of warning whenever the undo history is non-empty.

## What is deliberately NOT here
`editor.html`, `css/` (compiled Bootstrap + VvvebJs theme), `fonts/`, `img/`, `demo/`, `libs/builder/icons/`, the sample `save.php` / `upload.php` / `scan.php`, jQuery-free but unused plugins (CodeMirror, TinyMCE, CKEditor, Coloris, JSZip, media gallery, AI assistant, Google fonts), Bootstrap and the Bootstrap JS. Upstream's `Credits.md` lists several icon sets under CC BY / custom licences; none of them is imported. The editor shell uses its own inline SVG icons and the site's own CSS.

## Pixelite code that sits next to this folder (not derived from VvvebJs)
`../../px-shim.js` (stand-ins for the four Bootstrap JS classes `builder.js` calls), `../../px-templates.js` (CSP-safe property-panel templates), `../../px-editor.js` (whitelisted components, save/undo/dirty logic), `../../editor.css`, `../../canvas.css`, `../../vantage-theme.css`.


## Updating or removing
Replace the three files with a newer upstream copy and re-apply the four changes above (`grep -n PIXELITE builder.js`), or delete `public/assets/editor/`, `src/Editor/`, `templates/editor/`, `templates/demos/vantage-editable.php`, `config/editor.php` and the route block in `src/bootstrap.php`.

## Public builder (no login, temporary workspaces)

* **URL:** `/{lang}/builder` (en, cs, ru, uk; also under `/legacy`). "Start building" (POST with a start token) opens `/builder/w`.
* **What a visitor can do:** the full builder (blocks, sections, text and style editing, pages up to 5, images up to 6, undo/redo, preview). **Nothing is published**; there is no public URL for a draft.
* **Storage:** `storage/builder/<key>/` (`BUILDER_DIR` overrides), outside the web root. Ownership = one httpOnly cookie `pixelite_bw` (`__Host-` over HTTPS) holding `<key>.<secret>`; only `sha256(secret)` is stored. Nothing in a URL/body/header names a workspace.
* **Expiry:** 24 h after the last activity. An expired workspace stops working immediately; files are removed by opportunistic cleanup (every ≥15 min on any builder start) and by `php bin/cleanup-builder.php [--dry-run]` (cron optional). Cleanup only touches workspace-key directories under the builder root and never follows symlinks; customer projects and their editor data live elsewhere.
* **Limits:** per workspace 5 pages / 400 KB / 6 images (6 MB); globally 250 workspaces / 150 MB (`BUILDER_MAX_WORKSPACES`, `BUILDER_MAX_BYTES`); 6 new workspaces per hour per IP; rate limits on saves, page changes, media.
* **Switches:** `BUILDER_PUBLIC` (default on; `false` removes routes, nav link, sitemap entry). `EDITOR_ENABLED` (default off) only controls the authenticated customer editor/builder.
* **Hosting needs:** PHP `dom` and `gd`, writable `storage/`. If absent the landing page shows "not available" and starting is refused.
* **Limitations:** no transfer of a temporary draft to an account, no export, no publishing, no custom domains. Anonymous visitors share the IP-based limits behind NAT. No cron on shared hosting: space is reclaimed only when someone starts a workspace or the script runs. Draft data is plain files, not backed up.
* **Licensing:** unchanged (VvvebJs Apache-2.0 with modification notices, Bootstrap 5.3.3 MIT, own code). The AGPL Vvveb CMS is not used.
