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
- Fonts (Lato 300/400/700, includes Czech diacritics), images and CSS are **self-hosted** (no CDN, no hot-linking → no third-party requests, GDPR-friendlier, strict CSP).

## Layout
```
public/            docroot. index.php = front controller; .htaccess rewrites to it
  assets/          style.css (tokens at top), script.js, img/, fonts/, vendor/
src/               namespace Pixelite\ (autoloaded by src/bootstrap.php)
  bootstrap.php    router table, error handling, security headers
  Controllers/     Page, Order, Admin, Seo (+ BaseController)
  OrderValidator, OrderService, OrderRepository, TelegramNotifier   ← order pipeline
  Company (legal identity from .env), Auth, Csrf, Session, RateLimiter, Database, I18n, Env, Logger, View, Router, Request, Response
templates/         layout.php, partials/ (header, footer, contact-details, consent, theme-switch, theme-cycle, head-assets), pages/, admin/, errors/
lang/en.php cs.php all copy. Identical key sets (tests enforce it)
config/portfolio.php  portfolio data (placeholders, clearly marked)
config/consent.php    cookie/consent registry (storage inventory + optional services: empty)
storage/           git-ignored runtime data: leads.sqlite, logs/app.log, .salt
tests/run.php      dependency-free PHP test runner (+ fake_telegram.php)
tests/browser/    real-Chrome CDP suite (run.mjs matrix, cdp.mjs driver, styles-dump/diff for CSS regression checks)
bin/hash-password.php
```

## Design system (from the original landing page; tokens are `:root` vars in style.css)
| Token | Value | Use |
|---|---|---|
| `--c-blue` / hover | `#3a9fff` / `#63b2fc` | primary buttons, links, footer logo |
| `--c-violet` / hover | `#6f79ff` / `#878ef3` | violet band, mid card |
| `--c-purple` / hover | `#8a3aff` / `#b284f6` | first card accent |
| `--c-ink` | `#1f4568` | body text ("black"); footer bg `--c-ink-footer #1f4467` |
| `--c-muted` / light | `#8198ae` / `#aec0d2` | secondary text |
| `--c-border` / soft | `#c9d9e9` / `#ededed` | outlines |
| `--c-error` | `#c43131` | **added** for form errors only |
- Font Lato; h-sizes set per component (hero 30px, section title 26px, sub 18px, body 14–16px).
- Radius `6px` (cards/inputs), `20px` (pill buttons). Shadow `0 4px 8px rgba(0,0,0,.2)`. Transition `.4s`.
- Container = Bootstrap 3 (750/970/1170). Breakpoints in use: 1024 (header collapses, fixed white bar), 991, 767, 480.
- Components: `.btn` (+`--revert`, `--white`, `--purple/violet/blue`, `--width` fixed 120px, `--auto` fits label), `.price-box` cards, `.form__field/.form__select/.form__textarea`, `.article-pre` cards, `.sect--violet` band, `.footer`.
- Muted `#8198ae` on the pale background is below WCAG AA for small text – inherited from the approved design; new body copy uses `--c-ink`.

## What changed vs. the original landing page (and why)
Visual identity untouched. Content was a stock template, so:
- Hero/pricing/contact/band copy rewritten for Pixelite (EN+CS). Pricing cards → **service cards** (Landing page / Business website / Web apps). **No prices shown** ("Custom quote") – add real ones in `lang/*.php` if wanted.
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
| `GET /{lang}/contact` | contact details from env + CTA to order |
| `GET /{lang}/privacy` | privacy policy (controller identity from `.env`; unknown legal facts stay `[placeholders]`) |
| `GET /{lang}/cookies` | cookie policy: every cookie / storage item + "Cookie settings" (`#settings` opens the dialog) |
| `GET /{lang}/terms` | non-binding-request terms + consumer info + ADR (Česká obchodní inspekce); owner placeholders |
| `GET /{lang}/portfolio` | **hidden**: reachable, noindex, not linked, not in sitemap |
| `GET /robots.txt`, `/sitemap.xml` | sitemap has hreflang alternates; excludes portfolio/admin/sent |
| `GET /admin/login`, `POST /admin/login` | staff login (English only) |
| `GET /admin` · `GET /admin/orders/{id}` · `POST /admin/orders/{id}/retry` · `POST /admin/logout` | protected |
Not built on purpose: separate Services/About pages (services live on the landing). Terms exist but only state facts about how the site works; commercial terms are owner placeholders.
To publish the portfolio later: add real projects to `config/portfolio.php`, remove `noindex` (`PageController::portfolio`), add it to nav/footer + `SeoController::INDEXABLE`.

## Company identity (legal footer)
`src/Company.php` reads `COMPANY_NAME`, `COMPANY_ICO`, `COMPANY_ADDRESS` (+ optional `COMPANY_DIC`, `COMPANY_REGISTER`) and reuses the existing `CONTACT_EMAIL` / `CONTACT_PHONE` / `CONTACT_LOCATION`. The footer on **every** public page and the privacy/terms text render these values exactly as configured (IČO untouched, everything escaped). A missing value shows a dashed, italic **"[to be completed]"** placeholder – never an invented value. Nothing is hard-coded and no secret is involved (these are public business facts).

## Cookies and consent
- Storage that exists: `pixelite` (session cookie, only on the order form / staff login, CSRF), `pixelite_consent` (the choice, 6 months), `pixelite_lang` (cookie, 1 year, only after clicking the language switch), `pixelite_theme` (localStorage, only after choosing a theme). Registry: `config/consent.php`; texts: `lang/*.php` (`consent.*`, `cookies.*`).
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
**Owner/legal must decide or complete:** whether consumers are in scope · retention periods · hosting & email provider names · Telegram operator/location and any transfer safeguards · server-log retention · registered office · complaint (reklamace) procedure · withdrawal text for consumers · pricing/payment/IP terms · "last updated" date · whether language/theme persistence needs consent in your reading of ePrivacy · whether to show a register note (`COMPANY_REGISTER`).

## Localization
URL prefix decides the language (`/en/…`, `/cs/…`), so the choice persists through every link, and hreflang/canonical/`<html lang>`/meta are per language. The header switch keeps you on the same page; clicking it also sets cookie `pixelite_lang` (only used to choose where bare `/` goes). Default = `APP_LOCALE` (`en`). Add copy to **both** `lang/en.php` and `lang/cs.php`; `php tests/run.php` fails if keys differ. Missing keys fall back to English and are logged. Telegram messages are always English.

## Order system
Form fields: name, company, email, phone, project type, budget, timeframe, description, consent (+ hidden honeypot `website`). Option values live in `src/OrderOptions.php`, labels in `lang/*.php` (`order.options.*`). Budgets are in EUR per the brief.
Pipeline: `OrderController` → `OrderValidator` → `OrderService` → `OrderRepository` (SQLite `orders`) → `TelegramNotifier`.
- **Accepted = persisted.** The user sees success once the row is saved. Telegram is a notification on top: result stored as `notification_status` = `sent` | `failed` | `skipped` (not configured) with a short redacted error. A failed/skipped notification never loses the order; retry from `/admin/orders/{id}`.
- Stored: the form fields, locale, `created_at`, `consent_at`, notification status. **No IP, user agent or referrer** is stored with an order. Rate limiting keeps only a salted HMAC of the IP in `rate_limits` (purged after 24 h).
- Telegram: `sendMessage` over the official Bot API, plain text (no `parse_mode`, so user input can't break formatting), truncated < 4096 chars, timeout `TELEGRAM_TIMEOUT`. Token never reaches the browser/logs.
- Env vars: see `.env.example` (`TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `TELEGRAM_TIMEOUT`, `CONTACT_EMAIL/PHONE/LOCATION`, `CONTACT_RATE_LIMIT/WINDOW`, `TRUST_PROXY`, `LEADS_DATABASE_PATH`, `ADMIN_*`, `APP_URL`, `APP_LOCALE`). `.env` is parsed by `src/Env.php` (not docker `env_file`, which would mangle `$` in hashes).

## Authentication (decision)
No customer accounts. Only a **single staff account** for `/admin` (orders list/detail, retry Telegram). Credentials come from env: `ADMIN_EMAIL` + `ADMIN_PASSWORD_HASH` (bcrypt via `bin/hash-password.php`; **single-quote it in .env**). `password_verify`, session id regenerated on login, 2 h idle timeout (12 h absolute), CSRF on all POSTs, login rate-limited (10 attempts / 15 min / IP, atomic; cleared on successful login), generic error message, `Cache-Control: no-store`, `noindex`. No registration, no reset flow (change the env hash instead). Login is disabled until both vars are set.

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
