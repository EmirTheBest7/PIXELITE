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
- Frontend: server-rendered PHP templates, Bootstrap 3 **grid/reset only** (vendored: `public/assets/vendor/bootstrap.min.css`) + `style.css`, ~30 lines of vanilla JS. jQuery/Bootstrap JS were removed.
- Fonts (Lato 300/400/700, includes Czech diacritics), images and CSS are **self-hosted** (no CDN, no hot-linking → no third-party requests, GDPR-friendlier, strict CSP).

## Layout
```
public/            docroot. index.php = front controller; .htaccess rewrites to it
  assets/          style.css (tokens at top), script.js, img/, fonts/, vendor/
src/               namespace Pixelite\ (autoloaded by src/bootstrap.php)
  bootstrap.php    router table, error handling, security headers
  Controllers/     Page, Order, Admin, Seo (+ BaseController)
  OrderValidator, OrderService, OrderRepository, TelegramNotifier   ← order pipeline
  Auth, Csrf, Session, RateLimiter, Database, I18n, Env, Logger, View, Router, Request, Response
templates/         layout.php, partials/ (header, footer, contact-details), pages/, admin/, errors/
lang/en.php cs.php all copy. Identical key sets (tests enforce it)
config/portfolio.php  portfolio data (placeholders, clearly marked)
storage/           git-ignored runtime data: leads.sqlite, logs/app.log, .salt
tests/run.php      dependency-free test runner (+ fake_telegram.php)
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
| `GET /{lang}/privacy` | privacy + cookies (legal placeholders in `[brackets]`) |
| `GET /{lang}/portfolio` | **hidden**: reachable, noindex, not linked, not in sitemap |
| `GET /robots.txt`, `/sitemap.xml` | sitemap has hreflang alternates; excludes portfolio/admin/sent |
| `GET /admin/login`, `POST /admin/login` | staff login (English only) |
| `GET /admin` · `GET /admin/orders/{id}` · `POST /admin/orders/{id}/retry` · `POST /admin/logout` | protected |
Not built on purpose: separate Services/About/Terms pages (services live on the landing; nothing real to say on About/Terms yet; privacy covers cookies).
To publish the portfolio later: add real projects to `config/portfolio.php`, remove `noindex` (`PageController::portfolio`), add it to nav/footer + `SeoController::INDEXABLE`.

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
docker compose exec app php tests/run.php        # 67 checks; needs no real Telegram token
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
- [ ] Fill real data: `CONTACT_*`, legal identity + retention period in `lang/*.php` privacy, real prices (optional), real portfolio
- [ ] Real Telegram token smoke test; set `ADMIN_*`; production deploy + HTTPS (`APP_URL`, `TRUST_PROXY` if behind a proxy)
- [ ] Social links (footer) once accounts exist; OG image 1200×630 (currently hero.png); Czech copy review by a native speaker
- Post-review hardening done: atomic rate limiter, fail-closed time-trap, single-use CSRF per accepted order + 2-min duplicate suppression, notification errors can never report a saved order as unsaved, q-value aware `Accept-Language`, last-hop `X-Forwarded-*` (assumes ONE trusted proxy), 12 h absolute admin session, paged admin list.
- Known issues: muted-text contrast inherited from design (see above) · no email auto-reply to the customer · no backup of `storage/` configured · hero mock-up image still looks like an analytics dashboard (kept: part of the approved visual).
