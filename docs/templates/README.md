# Design catalog (`/{lang}/templates`)

Customers browse Pixelite's website designs, try the live preview, choose one and order a customised website
(the **Template website** package, price from the pricing data – never hard-coded). Pixelite builds the site.

## What a design consists of
| Part | Where | Public? |
|---|---|---|
| Catalog metadata (slug, category, style, tags, media, origin/components, `demo`) | `config/templates.php` | yes |
| Copy (title, description, 3 features – EN + CS) and filter labels | `lang/en.php`, `lang/cs.php` → `templates.*` | yes |
| Live demo document (the real template) | `templates/demos/<demo>.html` (private dir, served by PHP at `/{lang}/templates/<slug>/demo`) | via the route only |
| Demo assets (CSS, JS, fonts, images, vendored libs + their licence notices) | `public/assets/templates/<slug>/…` | yes |
| Gallery poster/preview (`poster-640.jpg`, `poster-1280.jpg`, `preview.webm`) | `public/assets/templates/<slug>/` | yes |
| **Implementation prompt + development notes** | SQLite table `template_prompts` (admin-only) | **never** |

The demo document is served with its own strict CSP (same-origin only, no inline script/style blocks, `frame-ancestors 'self'`,
`noindex`); the rest of the site keeps `frame-ancestors 'none'`. The design page embeds it in a sandboxed iframe with a
Desktop/Phone toggle and a link to open it full-size.

## Adding a design
1. Put the template's HTML in `templates/demos/<slug>.html` and its assets in `public/assets/templates/<slug>/`.
   It must run without third-party hosts: self-host fonts/libs/images, move inline `<style>`/`<script>` blocks to files,
   use `/assets/templates/<slug>/…` paths, add `<meta name="robots" content="noindex,nofollow">`. Serve JS libraries as `.js`
   (some hosts do not send a JavaScript MIME type for `.mjs`, which breaks module loading).
   Keep licence notices of vendored components next to them.
2. Add the entry to `config/templates.php` (copy the Lumora entry) and the EN + CS strings under `templates.items.<slug>`
   (plus any new category/style/tag labels – both languages, same key order; the parity test enforces it).
3. `docker compose up -d`, then `node scripts/capture-template-previews.mjs <slug>` – it runs the page at 390/768/1440 px
   (fails on console errors, overflow, failed or third-party requests) and records the posters + `preview.webm` from the live demo.
4. Write the prompt (below), `php tests/run.php`, `php scripts/build-endora.php && php scripts/verify-endora-artifact.php build/endora --http`.

Filters (category/style/sort) only appear once the catalog has more than one option for them.

## Private prompts (the repository is PUBLIC)
Prompt text and development notes never go into tracked files or `public/`. The package lives in the git-ignored
`private/template-prompts/prompts.json`:

```json
{"prompts":[{"slug":"lumora","version":1,"origin":"Authored by Pixelite","notes":"…","body":"…"}]}
```

Populate production once (and after edits): sign in at `/admin`, open **Design prompts**, upload the JSON under *Import a prompt
package*. The import validates every entry first (known slug, plain text, ≤ 20 000 chars, ≤ 1 MB, CSRF) and writes nothing if any
entry is invalid. Prompts can also be edited per design there; a changed text creates a new version. The table lives in
`storage/leads.sqlite` (outside the web root, never uploaded by the deploy – `*.sqlite*` is excluded).

## Orders
`/{lang}/order?template=<slug>` preselects the design (thumbnail shown, selectable), project type *Website* and the Template
package. The server validates the slug against the catalog and stores `orders.template` + `orders.template_name` (server-derived
English title); both appear in the admin order list/detail and in the Telegram notification.

## Lumora
Pixelite's own template (the owner's "Futuro" project, a single `index.html`). Adapted only to run in Pixelite:
inline CSS/JS moved verbatim to `lumora.css`/`lumora.js`; Google Fonts → self-hosted Onest (SIL OFL 1.1); unpkg importmap →
vendored Lenis 1.3.23 (MIT, `vendor/lenis.js`); the two hero images hotlinked from `api.getlayers.ai` → `img/`. Rendered
pixel-identical to the original at 1440×900 and 390×844 (first viewport and after scrolling through all reveals).
**Open point:** confirm you hold the rights to the two hero photographs (they came from getlayers.ai asset storage).

## Asterform, Stillwave, Vantage, Emberveil
Integrated from a supplied package of four ready-made sites (HTML/CSS/JS, originally with Vite scaffolding that is not shipped):

| Slug | What it is | Notable parts | Self-hosted extras |
|---|---|---|---|
| `asterform` | dark editorial studio, one page | full-screen video hero, scroll reveals, 2 service dialogs, newsletter form (client-side only) | 5 videos, Instrument Serif (OFL) |
| `stillwave` | cinematic product page (visual-creation tool) | full-viewport video, style panel, prompt dialog (no backend) | 1 video |
| `vantage` | dark data-product landing page | video background, collapsible nav, watch-demo card | 1 video, thumbnail |
| `emberveil` | playful portfolio hero | tile-wipe intro, word reveal, pointer spotlight reveal (canvas mask) | Inter (OFL), 2 hero PNGs, logo |

Adaptations (and nothing else): inline `<style>`/`<script>` blocks moved verbatim to files, Google Fonts → self-hosted woff2, remote videos/images →
local copies (videos re-encoded to 960–1280 px H.264 without audio, ~0.3–0.9 MB each), `noindex` meta added, sample e-mail addresses moved to the reserved
`.example` TLD and external social links to `#`. Emberveil's pointer loop now stops when the spotlight has caught up and also listens to `pointermove` (touch/pen).
**Open licence points (not cleared):** the original videos were hot-linked from a third-party AI-asset host (`cloudfront.net/…/hf_*.mp4`), and Emberveil's two hero
images and logo from `figma.site` / `framerusercontent.com`. Confirm the rights before showing them to customers as deliverables, or replace them.
Private implementation prompts for these four live in the git-ignored `private/template-prompts/prompts.json` (import them through the admin).
