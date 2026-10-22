# Deploying Pixelite.cz to Endora (shared hosting)

## Verified facts (and where they come from)

| Fact | Evidence |
|---|---|
| The web server serves **`public_html/`** as the site root; `.htaccess` lives there. | Endora help: "Co je .htaccess" (root directory of the web = `public_html`) and "Nastavení PHP – phpinfo" (save `phpinfo.php` into `public_html`, open it at `vasedomena.cz/phpinfo.php`). Live check on 2026-10-15: files uploaded to `public_html/src/…` were reachable as `https://pixelite.cz/src/…`. |
| FTP host is `ftp.<domain>`; user name is usually the domain; password from MyEndora → Hosting → FTP a soubory. | Endora help "Jak se přihlásit k FTP?". |
| PHP 7.4 / 8.2 / 8.3 / 8.4 / 8.5 selectable per domain in MyEndora → Hosting → PHP nastavení. Free plan: `max_execution_time` 10, `memory_limit` 64 MB. | Endora help "Jak zjistit a změnit verzi PHP?", "Srovnání webhostingů". |
| Apache + `.htaccess` are available (nginx in front; HTTP→HTTPS redirect happens there, `X-Forwarded-Proto` is set). | Endora help ".htaccess", live response headers. |
| Endora creates a placeholder `public_html/index.php` that prints "Hello World!". | Live: `GET /` and `GET /index.php` both returned it. |
| Port 21 speaks plain FTP and accepts explicit FTPS (its certificate does not match the host name in the secret, so strict verification fails); port **222 is SFTP** (`SSH-2.0-mod_sftp` banner); 22 and 990 are closed. | Inspect workflow run 38772926699; banner grab on 222. The deployment currently uses plain FTP on 21 (FTP-Deploy-Action cannot do SFTP). |
| PHP 8.5.9 via FPM, no `open_basedir`, the login directory (parent of `public_html`) is readable, writable and **not web-served**; `.htaccess` rewrites and `Require all denied` work; pdo_sqlite, mbstring, curl, openssl, intl present; `memory_limit` 256M, `max_execution_time` 90. | Temporary probe in the inspect workflow (randomly named, removed in the same run). |
| The host **injects an ad banner** ("Na FreeHostingu …", with an Endora link and an inline `<style>` that `@import`s Google Fonts) at the top of every HTML response. The strict CSP blocks that inline style, so the banner shows unstyled and the console logs one CSP violation per page. | Live HTML of `/en/`. Removing it requires a paid Endora plan (Fun/Max: "bez reklamy"); the CSP is deliberately not loosened. |

## Root cause of the "Hello World" page

The first workflow uploaded the **repository root** with `server-dir: ./public_html/`. The repository has no root-level
`index.php`; the real front controller is `public/index.php`, so it landed at `public_html/public/index.php`. Endora's
own placeholder `public_html/index.php` was never overwritten and kept answering `/`. Log evidence: run 38278313970 shows
the upload list (`src/`, `tests/`, `CLAUDE.md`, `Dockerfile`, `public/index.php`, …) and "first publish" for
`./public_html/`. The same upload also published every private file: on 2026-10-15 `/CLAUDE.md`, `/src/bootstrap.php` and
`/public/assets/style.css` were readable and `/tests/run.php` was even *executable* from the internet.

Earlier runs used wrong `server-dir` values (`./web/`, `./home/html/<user>/…`, missing trailing slash); some created stray
directories under the FTP login directory (see "Cleanup").

## Layout that is deployed

```
FTP login directory (private, not web-served)
├── src/ templates/ lang/ config/        application code (from the repo)
├── storage/                             SQLite DB, logs, rate-limit salt (created/written by PHP; never uploaded)
├── .env                                 created by hand once, NEVER uploaded by CI
└── public_html/                         the only web-served directory  (= repo public/)
    ├── index.php  .htaccess  favicon.ico
    └── assets/…
```

`public_html/index.php` requires `../src/bootstrap.php`, exactly like `public/index.php` does in development, so no
path logic changed. `Paths::publicDir()` finds `public/` (dev/Docker) or `public_html/` (Endora) for cache-busting.

## How a deployment works

1. `php tests/run.php` (PHP suite).
2. `php scripts/build-endora.php` → `build/endora/{public_html,app}`. Explicit allow-list: tests, docs, bin, Docker files,
   `.git*`, `.env*`, databases and logs are never copied.
3. `php scripts/verify-endora-artifact.php build/endora --http` — fails the run on a wrong layout, missing asset/font, any
   env/test/doc/DB/log file, token- or hash-shaped strings, and serves the built artifact with PHP's server to check real
   URLs (pages, assets, sitemap, robots, security headers, 404 for every private path).
4. FTP upload (`SamKirkland/FTP-Deploy-Action`, pinned by commit SHA, secrets from GitHub Actions only), twice:
   private app → `./`, then web root → `./public_html/`. `dangerous-clean-slate` stays **false**: the action only deletes
   files *it uploaded earlier* (tracked in its state file), never anything else.
5. `scripts/smoke-live.sh` against the live URL.

Safety switch: the upload is a **dry run** (it prints exactly what it would create/delete) unless the repository variable
`DEPLOY_ENABLED` is `true`. Manual runs have a `dry_run` checkbox (default on).

## One-time server setup (manual, by the owner)

1. In MyEndora → Hosting → **PHP nastavení**: choose PHP **8.3** (or newer) for the domain.
2. Create `.env` in the **FTP login directory, next to `public_html/`** (MyEndora → FTP a soubory → Správa souborů, or any
   FTP client), based on `.env.example`. Minimum for production: `APP_URL=https://pixelite.cz`, `APP_LOCALE=cs` (or `en`),
   leave `TRUST_PROXY` unset (the 2026-10-15 probe showed the host already sets `HTTPS=on` and `REMOTE_ADDR` = the visitor IP, so
   trusting `X-Forwarded-For` would only make the rate limiter spoofable),
   `COMPANY_*`, `CONTACT_*`, `PRICE_VAT_MODE`, `TELEGRAM_*`, `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH` (single-quoted).
   Never commit it and never paste it into an issue/chat.
3. GitHub → Settings → Secrets and variables → Actions: secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`; optional
   variables `FTP_PROTOCOL` (`ftp` | `ftps`), `FTP_PORT`, `SITE_URL`, `DEPLOY_ENABLED`.

## Cleanup of the earlier wrong uploads

The first real deployment of this layout removes, via the old state file, the files the first workflow put in the web
root (`src/`, `tests/`, `CLAUDE.md`, …). Stray directories created by the failed experiments under the FTP login directory
(for example `home/`) are not web-served; remove them manually in MyEndora → Správa souborů after checking their content.
Rotate the FTP password once everything works (it passed through several failed runs).

## Rollback

Re-run the workflow from an earlier commit (Actions → Deploy to Endora → Run workflow on that ref). MyEndora also keeps
daily FTP backups on paid plans.

## Known limits

- Free plan: 10 s execution limit, 64 MB memory, 5 PHP workers; the host may inject its own markup (check the live HTML
  against the strict CSP).
- Transport: port 21 offers explicit FTPS, but its certificate is a `*.nameserver.sk` wildcard (issued to the hoster, valid
  until 2026-10-25), never `ftp.pixelite.cz`, so strict host-name verification cannot succeed. Options: `FTP_PROTOCOL=ftps` with
  `FTP_SECURITY=loose` (encrypted, but not authenticated against active man-in-the-middle) or plain `ftp` (cleartext). Port 222
  (SFTP, host-key authenticated) is the proper channel but needs a different upload tool; ask Endora which host name matches
  the certificate if you want strict FTPS.

## Client previews, uploads and accounts on Endora (added with the CRM/project features)

What was **verified from this repository** (not assumed): PHP 8.5 FPM with `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `intl`; the login directory next to `public_html/` is writable and not web-served (so `storage/projects/` is private); `.htaccess` rules work. The ZIP reader is plain PHP on purpose – `ZipArchive` was **not** confirmed on the host.

**Not verifiable without the owner's MyEndora account** (so the feature degrades safely instead of guessing):
1. **A separate preview host.** Client previews run arbitrary HTML/JS and must not share Pixelite's origin. Endora's panel must allow a second domain or subdomain pointing at the **same** `public_html/` (domain alias / addon domain). Until `PREVIEW_ORIGIN` is set (and differs from `APP_URL`'s host) previews stay **off** and the admin says so. A separate registrable domain (for example `pixelite-preview.cz`) is preferred; `preview.pixelite.cz` works but the admin shows a "weaker isolation" note (a page on it could set cookies for `pixelite.cz`; the session cookie is `__Host-` protected over HTTPS). Never set a cookie `Domain`.
2. **Upload limits.** `upload_max_filesize` / `post_max_size` come from the host's PHP settings (the Docker image uses 2 MB / 8 MB; the admin shows the effective values). The application limits are 10 MB per file and 50 MB per project. Raise the PHP limits in MyEndora → PHP nastavení if larger sites must be uploaded; free plan: 10 s execution, 64 MB memory.
3. **E-mail.** No mail transport was verified, so password reset, e-mail verification and customer e-mails are **not** implemented (the order notification still goes through Telegram).
4. **Advertising.** The "Na FreeHostingu … Endora" panel is injected into every HTML response by the host (live check of https://pixelite.cz/en/ on 2026-10-21 found `endora-panel` markup that exists nowhere in this repository's templates). The application cannot and should not remove it; a paid Endora plan ("bez reklamy") does. `public/assets/endora-banner.js` (older, hides the wrapper in the browser) is unchanged and not extended.

Setup when the preview host exists: add `PREVIEW_ORIGIN=https://<preview host>` to `.env`, point the host at `public_html/`, open `/admin/projects`, create a project, upload a ZIP, switch the link on. Verify with `curl -I https://<preview host>/en/` (must be 404) and that `https://pixelite.cz/p/<token>/` is 404.
