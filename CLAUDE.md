# Pixelite.cz

Czech digital agency: **clean, affordable websites and practical digital solutions** for people and small businesses.
Brand: **blue, black, white**. The original landing page is the visual source of truth.

## Important constraints (read first)
- **Do not redesign.** Colours, type, spacing, cards, buttons, shadows and hover states come from the original landing page. New UI must be built from the existing classes/tokens in `public/assets/style.css`.
- **Do not invent company facts**: no client names, testimonials, statistics, prices, years, team size, awards, addresses or phone numbers. Missing data = visible, honest placeholder.
- **Never commit or expose secrets.** `.env` is git-ignored. The Telegram token is server-side only.
- **Keep it light**: plain PHP 8.3, no framework, no Composer, no JS framework, no new dependencies without a strong reason.
- Update this file when an architectural decision changes.

## Stack
- PHP 8.3 (Apache in Docker), SQLite (PDO), hand-written router. No Composer.
- Frontend: server-rendered PHP templates, Bootstrap 3 **grid/reset only** (vendored: `public/assets/vendor/bootstrap.min.css`) + `style.css`, a few small vanilla-JS files with no dependencies (`script.js` nav + language cookie + form guard, `theme-init.js`/`theme.js`, `consent.js`). jQuery/Bootstrap JS were removed.
- Fonts (**Onest**, SIL OFL, variable weight 100–900, subsets cyrillic / cyrillic-ext / latin / latin-ext in `public/assets/fonts/onest/` + `OFL.txt`; used by both designs and the admin; replaces Lato, which had no Cyrillic), images and CSS are **self-hosted** (no CDN, no hot-linking → no third-party requests, GDPR-friendlier, strict CSP).

## Layout
```
public/            docroot. index.php = front controller; .htaccess rewrites to it
  assets/          style.css (tokens at top), script.js, img/, fonts/, vendor/
src/               namespace Pixelite\ (autoloaded by src/bootstrap.php)
  bootstrap.php    router table, error handling, security headers
  Controllers/     Page, Order, Admin, AdminTemplates, Templates, Seo (+ BaseController)
  TemplateCatalog, TemplatePrompts   ← design gallery (public metadata) and the admin-only prompt table
  OrderValidator, OrderService, PricingSnapshot, OrderRepository, TelegramNotifier   ← order pipeline
  Company (legal identity from .env), Auth, Csrf, Session, RateLimiter, Database, I18n, Env, Logger, View, Router, Request, Response
templates/         NEW design (the default): layout.php, partials/ (ui helpers, header with the Legacy menu item, footer, consent …), pages/, admin/, errors/; demos/ (live demo documents of catalog designs, shared by both designs); legacy/ = the previous design (same view contract), served under /legacy (package-cards, credits, faq, steps, header, footer, contact-details, consent, theme-switch, theme-cycle, head-assets), pages/, admin/, errors/
lang/en.php cs.php ru.php uk.php all copy. Identical key sets (tests enforce it)
config/portfolio.php  portfolio data (placeholders, clearly marked)
config/templates.php  design catalog (public metadata only; prompts never here)
config/consent.php    cookie/consent registry (storage inventory + optional services: empty)
config/team.php       people on /team (name + role only; add an entry to add a member)
private/           git-ignored local package of admin-only prompts (repo is public; never deployed)
storage/           git-ignored runtime data: leads.sqlite, logs/app.log, .salt
tests/run.php      dependency-free PHP test runner (+ fake_telegram.php)
tests/browser/    real-Chrome CDP suite (run.mjs matrix, cdp.mjs driver, styles-dump/diff for CSS regression checks)
bin/hash-password.php
scripts/            build-endora.php (artifact), verify-endora-artifact.php (pre-upload gate), smoke-live.sh (live check)
.github/workflows/  deploy.yml (CI → Endora FTP), endora-inspect.yml (read-only host inspection)
```

## Two designs, one application (new = default, legacy = /legacy)
The approved redesign is now the production design. The previous one is kept, fully working, under **`/legacy/{lang}/…`** (same controllers, validators, database, auth and authorization – only the templates differ).
- `src/Design.php` decides the variant per request; `View` loads `templates/<file>.php` (new) or `templates/legacy/<file>.php`; `url()` adds the `/legacy` prefix while the legacy design renders, so every link, form action and redirect stays inside the chosen design. Assets: new = `public/assets/site.css` + `site.js` + `site-theme.js`; legacy = `style.css` + `script.js` + `theme.js`.
- **Switch (moved to the footer; the header only has the language dropdown, Sign in and Start a project):** a discreet link – "Legacy website" / "Původní web" / "Прежняя версия сайта" / "Попередня версія сайту" in the new header (desktop menu and mobile menu), "New design" / "Nový design" / "Новый дизайн" / "Новий дизайн" in the legacy header. It keeps language and page (`url_other_design()`), is `rel="nofollow"`, never redirects automatically (no loops).
- **SEO:** legacy pages are `noindex,follow`, canonical + hreflang + sitemap always name the default-design URLs, so nothing is indexed twice. `/legacy/admin`, `/legacy/p/…`, sitemap/robots etc. are not routed: staff and utility routes exist once, in the default design. `/legacy` redirects to `/legacy/<best language>/`.
- **Rollback lever:** `DESIGN_DEFAULT=legacy` in `.env` makes legacy the default (at `/en/…`) and the new design the alternate (`/new/en/…`) with no deploy; unset/invalid = `new`.
- **Tests:** `tests/run.php` renders the LEGACY templates at the normal URLs (its ~800 markup assertions were written for them; `TEST_DESIGN=new` is not meaningful for those), `tests/design.php` proves the new default, the switch, canonical/noindex, admin and re-runs `tests/features.php` on the new templates; the original real-browser suite (`tests/browser/run.mjs`) is run against a container with `DESIGN_DEFAULT=legacy`; `tests/browser/new-design.mjs` and `tests/browser/platform.mjs` cover the new design and the staff/customer/project flows.
- `new/` (concept source) is superseded by the integrated design and kept untracked as reference only.
- The desktop pill navigation appears from 1360 px (Russian/Ukrainian labels need the room); below that the bottom-sheet menu is used.

## Design system (legacy tokens, kept for the /legacy design; tokens are `:root` vars in style.css)
| Token | Value | Use |
|---|---|---|
| `--c-blue` / hover | `#3a9fff` / `#63b2fc` | primary buttons, links, footer logo |
| `--c-violet` / hover | `#6f79ff` / `#878ef3` | violet band, mid card |
| `--c-purple` / hover | `#8a3aff` / `#b284f6` | first card accent |
| `--c-ink` | `#1f4568` | body text ("black"); footer bg `--c-ink-footer #1f4467` |
| `--c-muted` / light | `#8198ae` / `#aec0d2` | secondary text |
| `--c-border` / soft | `#c9d9e9` / `#ededed` | outlines |
| `--c-error` | `#c43131` | **added** for form errors only |
- Font Onest (was Lato); h-sizes set per component (hero 30px, section title 26px, sub 18px, body 14–16px).
- Radius `6px` (cards/inputs), `20px` (pill buttons). Shadow `0 4px 8px rgba(0,0,0,.2)`. Transition `.4s`.
- Container = Bootstrap 3 (750/970/1170). Breakpoints in use: 1024 (header collapses, fixed white bar), 991, 767, 480.
- Components: `.btn` (+`--revert`, `--white`, `--purple/violet/blue`, `--width` fixed 120px, `--auto` fits label), `.price-box` cards, `.form__field/.form__select/.form__textarea`, `.article-pre` cards, `.sect--violet` band, `.footer`.
- Muted `#8198ae` on the pale background is below WCAG AA for small text – inherited from the approved design; new body copy uses `--c-ink`.

## What changed vs. the original landing page (and why)
Visual identity untouched. Content was a stock template, so:
- Hero/pricing/contact/band copy rewritten for Pixelite (EN+CS). The three `.price-box` cards are now the **three website packages with real starting prices** (see "Pricing" below).
- **Hidden (not rendered; CSS kept):** Success-stories carousel (fake CEOs), partner logo grid (implied partnerships), Blog section + nav link (no blog), Careers band (reused as "Start a project" band), social icons (`href="#"`), fake New-York address/phone.
- Header "Sign In →" button slot → **"Start a project →"** (auth is staff-only, so no public sign-in).
- Landing contact form (was non-functional) → CTA card linking to `/order`. One form, one pipeline.
- `<h1>` logo/footer/band headings became spans/h2/h3 with identical styling; structural HTML bugs fixed.

## Routes
Every public route exists per language: `/en/…` and `/cs/…`. Bare `/` → 302 to `cookie pixelite_lang` › `Accept-Language` › `APP_LOCALE`.
| Route | Notes |
|---|---|
| `GET /{lang}/` | landing (services anchor `#services`, contact, CTA band) |
| `GET /{lang}/order` `?type=landing\|website\|webapp…` | project request form (prefilled type) |
| `POST /{lang}/order` | validate → save → Telegram → 303 to sent |
| `GET /{lang}/order/sent` | one-time success page (flash), noindex |
| `GET /{lang}/pricing` | packages (shared cards partial), credits strip, why-they-differ, comparison table, included / not included, price factors, FAQ, CTA. Data only from `services.items` + `pricing.*` / `faq.*` lang keys; VAT line from `PRICE_VAT_MODE`; exact exclusions are a visible owner placeholder |
| `GET /{lang}/process` | five-step workflow (`process.steps`), notes, owner placeholder for timelines/revisions/hosting/handover, CTA to order |
| `GET /{lang}/contact` | contact details from env + CTA to order |
| `GET /{lang}/privacy` | privacy policy (controller identity from `.env`; unknown legal facts stay `[placeholders]`) |
| `GET /{lang}/cookies` | cookie policy: every cookie / storage item + "Cookie settings" (`#settings` opens the dialog) |
| `GET /{lang}/terms` | non-binding-request terms + consumer info + ADR (Česká obchodní inspekce); owner placeholders |
| `GET /{lang}/templates` `?q=&category=&style=&sort=&page=` | design gallery (search/filters/sort/pagination; filtered views are noindex with the plain gallery as canonical) |
| `GET /{lang}/templates/{slug}` | design page: live preview (iframe, Desktop/Phone), features, price line from the Template package, order CTA |
| `GET /{lang}/templates/{slug}/demo` | the design itself, own CSP, `frame-ancestors 'self'`, noindex; not in the sitemap |
| `GET /{lang}/team` · `GET /{lang}/careers` | Meet the team (Emir Aliev – Founder / Team member; no invented bio, monogram instead of a photo) · Careers (published vacancies from the DB; "not hiring" + the configured `CONTACT_EMAIL` mailto when none) |
| `GET/POST /{lang}/login` · `/register` · `POST /logout` · `GET/POST /{lang}/account` | customer accounts (see "Customer accounts"); all noindex, never cached; "Continue with Dreamers" is a **disabled placeholder** |
| `GET /{lang}/contact?template=<slug>` | the contact page shows "Chosen design: <catalog title>" only for a valid slug; the CTA passes it on to the order form |
| `GET /p/<token>/…` | client project preview – served **only on the separate preview host** (`PREVIEW_ORIGIN`), 404 on the main host |
| `GET /{lang}/portfolio` | **hidden**: reachable, noindex, not linked, not in sitemap |
| `GET /robots.txt`, `/sitemap.xml` | sitemap has hreflang alternates; excludes portfolio/admin/sent |
| `GET /admin/login`, `POST /admin/login` | staff login (English only) |
| `GET /admin/templates` · `GET/POST /admin/templates/{slug}` · `POST /admin/templates/import` | admin-only prompt library (list/filter, view + copy, edit with versions, JSON import) |
| `GET /admin` · `GET /admin/orders/{id}` · `POST /admin/orders/{id}/retry` · `POST /admin/orders/{id}/link` · `POST /admin/logout` | protected |
| `/admin/careers…` · `/admin/projects…` · `/admin/customers…` | protected (staff session + CSRF on every change): vacancies (create/edit/publish/unpublish/delete with confirmation), client projects (create/edit/upload ZIP or files/preview link on-off/regenerate/delete), customer records (disable/enable, link orders deliberately) |
Not built on purpose: `/about` (waits for real founder/company content), separate Services pages (services live on the landing). Terms exist but only state facts about how the site works; commercial terms are owner placeholders.
To publish the portfolio later: add real projects to `config/portfolio.php`, remove `noindex` (`PageController::portfolio`), add it to nav/footer + `SeoController::INDEXABLE`.

## Pricing, credits and consultation (landing `#services`)
- Three packages, all content in `lang/en.php` / `lang/cs.php` under `services.items` (**no price text in templates**; a test enforces it). Stable ids: `template`, `basic`, `custom`. Prices are "from" prices in **CZK**: Template website / Web ze šablony **from 2,999 Kč**, Basic website / Základní web **from 32,900 Kč**, Custom website / Individuální web **from 64,900 Kč**. Each card carries a note that the final price depends on scope; the template card says plainly that it is a template implementation, not a custom design, and that additional work can increase the price. The section subtitle repeats that these are starting prices. Czech prices use non-breaking spaces (`2 999`) so a price never wraps mid-number.
- Cards link to the order form with `?package=<id>` (`template`, `basic`, `custom`). The **package is its own field**, separate from the project type: an optional `Package` select (labels built from the pricing data: "Basic website – From 32,900 CZK"), whitelisted in `OrderOptions::PACKAGES`, stored in `orders.package` (`""` = none), included in the Telegram message and shown in the admin list + detail. `?package=` also preselects project type **Website** (all packages are websites) unless an explicit `?type=` is given; the visitor can change either field independently. Databases created before this field are migrated automatically (`ALTER TABLE … ADD COLUMN`, old rows keep `""`).
- **Pricing snapshot (history never changes).** When an order with a package is submitted, the server stores what the customer saw next to the canonical id: `package_name`, `package_price` (the displayed starting price, e.g. "<from> <amount> <currency>", in the customer's language) and `price_vat_mode` (`incl|excl|none`, or `unset` if the placeholder was showing). Captured by `src/PricingSnapshot.php` from the server's own data – a client-sent value is never read. Admin list/detail and Telegram show the **snapshot**, never today's translations, so editing a price later cannot rewrite old orders. It is a *starting price*, **not the agreed contract price** (the admin says so). Orders from before snapshots existed (package id only) show "price not recorded"; orders with no package have empty snapshot fields. `orders.package` stays the only canonical identifier.
- **Dreamers Ad Credits** strip under the cards: "Orders from 30,000 CZK qualify for **up to** $100 in Dreamers Ad Credits for advertising" – a promotional advertising credit, **not a cash discount**, offered across Pixelite services once the order value reaches the threshold. No expiry/value/refund/eligibility rules are stated anywhere; they are an **owner placeholder in `/terms#dreamers`** (the strip links there).
- "Nezávazná konzultace / Non-binding consultation" is its own section (`#consultation`) below the cards, with a CTA to `/order`. It deliberately does not say "free".
- Order budgets (`OrderOptions::BUDGETS`, server-side whitelist) are CZK ranges: `under10k, 10k-30k, 30k-65k, 65k-100k, 100k-plus, unsure`. Legacy EUR values are rejected.
- **VAT (DPH) display – never guessed.** One optional setting, `PRICE_VAT_MODE` in `.env`: `incl` (prices include VAT), `excl` (prices exclude VAT; VAT added), `none` (not a VAT payer, no VAT added). It drives the small line under each price and the VAT sentence in `/terms` (`lang` → `vat.*`). Empty/unknown/misspelled = **visible placeholder** "[VAT information – to be completed]" on the cards and in `/terms`. The amounts themselves never change with this setting. Set it only to what is true for the company.
- `/terms` states that prices are starting prices in CZK, that the template price depends on the scope, shows the VAT sentence from `PRICE_VAT_MODE`, and keeps payment terms plus the **Dreamers conditions as four separate owner placeholders** (who issues the credits · eligibility and how order value is counted · issuing/redemption · validity, transferability, cash value/refundability).

## Company identity (legal footer)
`src/Company.php` reads `COMPANY_NAME`, `COMPANY_ICO`, `COMPANY_ADDRESS` (+ optional `COMPANY_DIC`, `COMPANY_REGISTER`) and reuses the existing `CONTACT_EMAIL` / `CONTACT_PHONE` / `CONTACT_LOCATION`. The footer on **every** public page and the privacy/terms text render these values exactly as configured (IČO untouched, everything escaped). A missing value shows a dashed, italic **"[to be completed]"** placeholder – never an invented value. Nothing is hard-coded and no secret is involved (these are public business facts).

## Cookies and consent
- Storage that exists: `pixelite` / `__Host-pixelite` over HTTPS (session cookie, only on the order form, account pages and staff login: CSRF + customer sign-in), `pixelite_consent` (the choice, 6 months), `pixelite_lang` (cookie, 1 year, only after clicking the language switch), `pixelite_theme` (localStorage, only after choosing a theme). Registry: `config/consent.php`; texts: `lang/*.php` (`consent.*`, `cookies.*`).
- **No optional cookies/services exist** (no analytics, marketing, third-party content) and none were invented; `optional` is an empty registry. The banner says so honestly.
- UI: non-modal bottom banner (hidden until `consent.js` sees no valid choice) with **Accept all / Reject non-essential** (identical `.btn` weight) and **Cookie settings** (secondary). Settings = native `<dialog>` (focus trap, Esc, focus returns). Footer "Cookie settings" is a link to `/cookies#settings`: with JS it opens the dialog, without JS it lands on the cookie policy page (which explains the storage and how to delete it in the browser).
- Gate for future services: list the service under its category in `config/consent.php`, add `<script type="text/plain" data-consent="analytics" data-src="…">` and allow its host in the CSP (`src/bootstrap.php`). `consent.js` runs it only after consent; unknown/new categories default OFF; bump `version` to re-ask everyone. Withdrawn consent needs a reload for already-loaded scripts. **Adding a service means bumping `version`** (otherwise existing visitors are never asked) and only external scripts via `data-src` can be gated (inline code is blocked by the CSP).
- Language and theme preferences are written **only on an explicit user action** (never on page load). **Whether that persistence needs consent is NOT decided here – flagged for legal review below.** If legal review says it does, gate the two writes in `script.js` / `theme.js` behind `PixeliteConsent`.

## Dark mode
Light is the default and is unchanged. All components use **semantic tokens** (`--bg --surface --field-bg --text --text-muted --border --link --btn-bg … --lp-*`); a theme is just a set of values. Dark = `:root[data-theme="dark"]` plus `@media (prefers-color-scheme: dark){:root:not([data-theme="light"])}` (**the two blocks must stay identical – `tests/run.php` fails if they diverge**). Palette: near-black navy surfaces (`#0a0f1a / #111a2b`), off-white text `#e3eaf4` (≥14:1), muted `#9bb0c8` (≥7.8:1), brand blue kept as accent, buttons deepened (`#2068c0`) so white text reaches ≥4.9:1.
Switch: Light / Dark / System. Desktop header = compact cycle button; ≤1024px = 3-way control in the menu; footer = 3-way control. `theme-init.js` (blocking, first in `<head>`) applies an explicit choice before paint (no flash); "System" follows the OS. Choice stored in `localStorage.pixelite_theme` only after a click. `theme-color`/`color-scheme` metas follow the theme.

## Branding assets
Favicon/logo come from the owner-supplied files: `public/favicon.ico`, `public/assets/icons/favicon.svg`, `public/assets/icons/apple-touch-icon.png` (180px), and `public/assets/img/logo.svg` (byte-identical logo mark, used in header, footer, contact block, admin bar via `logo_mark()`). Not generated or edited. No web manifest (no 192/512 icons supplied). The mark is a square tile; the wordmark "PIXELITE.cz" next to it is live text. The older cube illustrations (service cards, CTA band) are untouched approved artwork.
Hero visual = pure-CSS 3D laptop (`.hero-laptop`, adapted from the owner's CodePen): em-based, scales by container query, reserves its height via `aspect-ratio` (no layout shift), lid opens once (transform only), skipped under `prefers-reduced-motion`; screen is a branded wireframe (no embed). `hero.png` remains **only** as the Open Graph share image.

## Legal review status (not legal advice)
Implemented: controller identity, per-activity legal bases (Art. 6(1)(b)/(f); (a) only for future optional services), recipients incl. Telegram, accurate log/IP wording, retention placeholders, rights + ÚOOÚ complaint, cookie policy, non-binding-request terms, ADR pointer to Česká obchodní inspekce (`coi.gov.cz`, verified on the official site; the EU ODR platform is discontinued and deliberately not referenced). The order checkbox is an **acknowledgement that the privacy notice was read**, not a consent (`orders.consent_at` stores that time).
**Owner/legal must decide or complete:** set `PRICE_VAT_MODE` to the company's real VAT status (cards and `/terms` show a placeholder until then) · the Dreamers Ad Credits conditions, who issues them and whether the name may be used · confirm every listed package feature is actually delivered (e.g. Czech-language administration, analytics tools, "low running costs") · whether consumers are in scope · retention periods · hosting & email provider names · Telegram operator/location and any transfer safeguards · server-log retention · registered office · complaint (reklamace) procedure · withdrawal text for consumers · pricing/payment/IP terms · "last updated" date · whether language/theme persistence needs consent in your reading of ePrivacy · whether to show a register note (`COMPANY_REGISTER`).

## SEO notes
One URL per page: `canonicalRedirect()` (`src/bootstrap.php`) 301s `www.` → apex (host from `APP_URL`), `/en` → `/en/`, `/en/pricing/` → `/en/pricing`; only GET/HEAD with a real `REQUEST_URI`. Head carries canonical, reciprocal hreflang (+x-default), OG (with image size) and Twitter tags. JSON-LD (`layout.php`, one block per page): home = `Organization` + `WebSite` graph; other indexable pages = `BreadcrumbList`. No ratings/reviews/Offer markup on purpose (nothing verified to mark up). `tests/run.php` "seo regression" covers titles/descriptions uniqueness, canonical/hreflang, JSON-LD, sitemap, robots, redirects, local assets. Not verifiable without accounts: Search Console indexing, CrUX field data.

## Design gallery and prompts
Customers pick a design and order a customised website (Template-website package price, from the pricing data). Full guide: `docs/templates/README.md`.
- Catalog = `config/templates.php` (public metadata) + `lang/*` (`templates.items.<slug>`). Entries: **Lumora**, **Asterform**, **Stillwave**, **Vantage**, **Emberveil**, **Azur** (Pixelite templates; no third-party template collection). Each has a demo document `templates/demos/<slug>.html` and assets `public/assets/templates/<slug>/` (vendored components' notices shipped next to them: Lenis MIT, Onest / Instrument Serif / Inter / Marcellus / Albert Sans OFL, Unsplash License for Azur's stand-in photos). Asterform, Stillwave and Vantage use silent, re-encoded `video/*.mp4` self-hosted in place of the original hot-linked media; **the rights to the sample videos (Asterform ×5, Stillwave, Vantage) and Emberveil's hero artwork/logo are unconfirmed** – see `docs/templates/README.md`. Azur has Czech sample copy and unconfirmed-as-real placeholder prices/reviews (see `docs/templates/README.md`). Categories `studio`/`saas`/`beauty`, styles `light`/`dark`, so the gallery shows its category/style/sort filters.
- The demo route sets its own CSP (same-origin, no inline blocks, `style-src-attr 'unsafe-inline'` for animation-delay attributes) and `frame-ancestors 'self'`; everything else keeps `frame-ancestors 'none'`.
- Orders store `template` + server-derived `template_name` (migrated in place; old rows keep `""`); shown in admin and Telegram.
- Prompts/dev notes: SQLite `template_prompts` only (admin-only, CSRF, validated import, versions). The package file lives in git-ignored `private/` and is never deployed; production is populated through the admin import.
- `scripts/capture-template-previews.mjs` (host-only) records posters/preview from the live demo and gates console errors, overflow and third-party requests.

## Localization
Four languages: **en, cs, ru, uk** (`I18n::LOCALES`). The staff area stays English. URL prefix decides the language (`/en/…`, `/cs/…`, `/ru/…`, `/uk/…`), so the choice persists through every link, and hreflang/canonical/`<html lang>`/meta are per language. The header switch keeps you on the same page; clicking it also sets cookie `pixelite_lang` (only used to choose where bare `/` goes). Default = `APP_LOCALE` (`en`). Add copy to **both** `lang/en.php` and `lang/cs.php`; `php tests/run.php` fails if keys differ. Missing keys fall back to English and are logged. Telegram messages are always English. Russian/Ukrainian prices keep a non-breaking space (`2 999`) and currency `CZK`; the ADR link in /terms points to the English page of the Czech Trade Inspection. Terminology and style rules for ru/uk: `docs/i18n/TERMINOLOGY.md` (a project request is a *заявка*, the account is *аккаунт* / *обліковий запис*, `проєкт` in Ukrainian, no `(а)` forms; `tests/i18n.php` pins them). Report of the last audit and the open legal checklist: `docs/i18n/LOCALIZATION-REPORT.md`.
- **Text outside the dictionaries:** `config/portfolio.php` entries are per-language maps (en, cs, ru, uk) read through `localized()` (active language → English → any filled language, never blank); careers rows count a language as translated only when title **and** description exist, otherwise the whole English text is shown with `lang="en"` and the line `careers.english_only`; the demo documents are sample sites and stay English (see `docs/templates/README.md`); the staff area and Telegram stay English.
- **The language and design switches keep the query string** (`current_query()`, `Request::querySuffix()`: GET only, `next` dropped, max 300 bytes) so `?template=`, `?package=` and gallery filters survive; canonical/hreflang/sitemap never carry a query.

## Order system
Form fields: name, company, email, phone, project type, package (optional), budget, timeframe, description, consent (+ hidden honeypot `hp_site`). Option values live in `src/OrderOptions.php`, labels in `lang/*.php` (`order.options.*`). Budgets are CZK ranges (see Pricing).
Pipeline: `OrderController` → `OrderValidator` → `OrderService` → `OrderRepository` (SQLite `orders`) → `TelegramNotifier`.
- **Accepted = persisted.** The user sees success once the row is saved. Telegram is a notification on top: result stored as `notification_status` = `sent` | `failed` | `skipped` (not configured) with a short redacted error. A failed/skipped notification never loses the order; retry from `/admin/orders/{id}`.
- Stored: the form fields, locale, `created_at`, `consent_at`, notification status. **No IP, user agent or referrer** is stored with an order. Rate limiting keeps only a salted HMAC of the IP in `rate_limits` (purged after 24 h).
- Telegram: `sendMessage` over the official Bot API, plain text (no `parse_mode`, so user input can't break formatting), truncated < 4096 chars, timeout `TELEGRAM_TIMEOUT`. Token never reaches the browser/logs.
- Env vars: see `.env.example` (`TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `TELEGRAM_TIMEOUT`, `PRICE_VAT_MODE`, `COMPANY_*`, `CONTACT_EMAIL/PHONE/LOCATION`, `CONTACT_RATE_LIMIT/WINDOW`, `TRUST_PROXY`, `LEADS_DATABASE_PATH`, `ADMIN_*`, `APP_URL`, `APP_LOCALE`). `.env` is parsed by `src/Env.php` (not docker `env_file`, which would mangle `$` in hashes).

## Authentication (decision)
No customer accounts. Only a **single staff account** for `/admin` (orders list/detail, retry Telegram). Credentials come from env: `ADMIN_EMAIL` + `ADMIN_PASSWORD_HASH` (bcrypt via `bin/hash-password.php`; **single-quote it in .env**). `password_verify`, session id regenerated on login, 2 h idle timeout (12 h absolute), CSRF on all POSTs, login rate-limited (10 attempts / 15 min / IP, atomic; cleared on successful login), generic error message, `Cache-Control: no-store`, `noindex`. No registration, no reset flow (change the env hash instead). Login is disabled until both vars are set.

## Customer accounts, careers, projects (added after the first release)
- **Accounts** (`customers` table, `Customers`, `CustomerAuth`, `AccountController`): e-mail + password (`password_hash`, 10–72 bytes), registration needs the privacy acknowledgement, honeypot + 2 s fill time, rate limits (login: 10/15 min per connection AND 8/15 min per e-mail; registration 6/h). Registration and "e-mail already used" give the **same** response (no enumeration; no auto-login). Login rotates the session id and drops the CSRF token; customer session = 2 h idle / 12 h absolute; `?next=` only accepts `/<lang>/account`. A disabled account loses access immediately. **No password reset and no e-mail verification** (no verified mail infrastructure) – the pages say so. The staff login (`Auth`, env credentials) is separate; both use the same hardened session cookie (`__Host-pixelite` over HTTPS so a sibling subdomain cannot shadow it; `pixelite` over plain http).
- **Orders ↔ customers:** `orders.customer_id` is set only when the customer was signed in while ordering, or when staff link an order (`/admin/orders/{id}/link`). Never matched by e-mail. The account page lists only that customer's own orders/projects; there is no route that takes a customer/order/project id.
- **Chosen design** field on the order form exists only for a valid `?template=` (or a tampered POST value so it can be corrected).
- **Careers** (`careers` table, titles/descriptions JSON per locale, English required): only `status = published` is ever public.
- **Projects** (`projects` table; files in `storage/projects/<uid>/site/` – `<uid>` is a random 16-hex id, tokens are 43-char base64url from 256 random bits stored with the project; `PROJECTS_DIR` overrides the folder). Upload: ZIP (read by the dependency-free `ZipReader`, never extracted to disk, symlinks/encryption/ZIP64/traversal/oversize/CRC mismatch refused, whole archive or nothing) or single files; whitelist of static extensions, 10 MB/file, 50 MB/project, 1000 files; files stored 0644; nothing is ever included or executed by PHP.
- **Previews run arbitrary client HTML/JS, so they are served from a DIFFERENT HOST** (`PREVIEW_ORIGIN`, e.g. `https://pixelite-preview.cz`; same-registrable-domain subdomains work but are flagged as weaker because they can set parent-domain cookies). On that host the app serves only `/p/<token>/…` (no router, no session, no cookies); CSP `default-src 'self'` (inline allowed, no external hosts, `connect-src 'self'`, no framing, `form-action 'none'`, no workers), `Referrer-Policy: no-referrer` (the token is in the URL), `no-store`, nosniff, COOP/CORP same-origin. Without `PREVIEW_ORIGIN` previews are **off** (the admin explains why). The link is a bearer credential: on/off switch, regenerate (old link dies at once), delete (files removed). Admin pages never embed a preview.
- Existing `endora-banner.js` (hides the hosting provider's injected banner) is loaded by the public layouts and the admin layout (the rule that hides the marked wrapper lives in both `style.css` and `site.css`); the banner itself is injected by Endora's free plan, not by our templates.

## Security implemented
CSRF tokens on all forms · server-side validation with whitelists and length limits · prepared statements only · output escaped with `e()` (JSON-LD uses `JSON_HEX_TAG`) · honeypot + minimum fill time · SQLite rate limiting · CSP (`default-src 'self'`, no inline script/style), nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS when HTTPS · sessions only started on order/admin pages (plain visitors get no cookie), HttpOnly + SameSite=Lax + Secure on HTTPS · exceptions logged, users see a generic 500 · only `public/` is web-exposed (`.env`, `src/`, `storage/` are outside the docroot) · `X-Forwarded-For` ignored unless `TRUST_PROXY=true`.

## Development
```bash
cp .env.example .env            # then edit (APP_URL=http://localhost:8080 locally)
docker compose up -d --build    # http://localhost:8080  (works with OrbStack)
docker compose exec app php tests/run.php        # PHP suite; never touches the real Telegram chat
node tests/browser/run.mjs [--quick] [--shots]   # real-Chrome matrix (needs Node + Chrome on the host, app running):
                                                 #   10 devices x EN/CS x light/dark + consent, theme, menu, forms, hero, exposure
node tests/browser/styles-dump.mjs out.json && node tests/browser/styles-diff.mjs a.json b.json   # computed-style regression diff
docker compose logs -f app                        # apache + PHP errors + app log lines
docker compose exec app tail -f storage/logs/app.log
docker compose run --rm app php bin/hash-password.php   # → ADMIN_PASSWORD_HASH='…' for .env
```
Verify real credentials with `docker compose exec app php bin/telegram-check.php` (sends one labelled test message; exit 0 = delivered).
Telegram: create a bot with @BotFather, message it, read `https://api.telegram.org/bot<TOKEN>/getUpdates` for `chat.id`, set both vars, submit `/en/order`. Tests exercise the real HTTP path against `tests/fake_telegram.php`; **a real bot token has not been tested from this repo.**
Edits to PHP/CSS are live (bind mount). Production: build the image (code is `COPY`'d) and mount a persistent volume at `/var/www/html/storage`, provide secrets via a mounted `.env` or real environment variables (`APP_ENV`/`APP_DEBUG` in `.env.example` are legacy keys the code does not read).

## Deployment (Endora shared hosting)
Endora serves only `public_html/`. CI never uploads the repo root: `scripts/build-endora.php` maps `public/` → `public_html/` and ships `src/ templates/ lang/ config/ storage/.gitkeep` as private siblings (FTP login dir); `.env` is created by hand next to `public_html/` and never uploaded. `scripts/verify-endora-artifact.php --http` is the pre-upload gate (layout, leaked files, assets, real HTTP smoke test) and runs in CI and in `tests/run.php`. Upload is a dry run unless repo variable `DEPLOY_ENABLED=true`; `dangerous-clean-slate` stays off. Facts, evidence, setup and rollback: `docs/deployment/endora.md`. `Paths::publicDir()` resolves `public/` or `public_html/`.

## Status
- [x] Audit, design tokens, self-hosted assets, HTML fixes
- [x] Router, i18n (EN/CS), landing, order, contact, privacy, hidden portfolio
- [x] Order pipeline + SQLite + Telegram notifier (+ retry), admin login/list/detail
- [x] Rate limiting, CSRF, honeypot, CSP/headers, SEO (canonical, hreflang, OG, JSON-LD, robots, sitemap)
- [x] Docker, tests, browser/screenshot checks at 320/375/768/1024/1440
- [ ] Fill real data: `COMPANY_*` (name, IČO, address) + `CONTACT_*` in `.env`; retention periods, providers, transfer details, complaint/withdrawal text in `lang/*.php` (search for `to be completed`); real prices (optional); real portfolio
- [ ] Real Telegram token smoke test; set `ADMIN_*`; production deploy + HTTPS (`APP_URL`, `TRUST_PROXY` if behind a proxy)
- [ ] Social links (footer) once accounts exist; a 1200×630 OG image (currently the old hero.png); Czech copy review by a native speaker
- Post-review hardening done: atomic rate limiter, fail-closed time-trap, single-use CSRF per accepted order + 2-min duplicate suppression, notification errors can never report a saved order as unsaved, q-value aware `Accept-Language`, last-hop `X-Forwarded-*` (assumes ONE trusted proxy), 12 h absolute admin session, paged admin list.
- Known issues: light-theme contrast of inherited design colours (muted `#8198ae` ≈3:1, white-on-`#3a9fff` buttons ≈2.8:1, band subtitle ≈2.3:1) is left as approved – dark theme meets AA · muted-text contrast inherited from design (see above) · no email auto-reply to the customer · no backup of `storage/` configured.
