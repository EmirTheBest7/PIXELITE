# Telegram bots hosted next to Pixelite

Decision (2026-11-16): every bot lives in **its own repository and deploys itself from its own CI** to the same Endora
hosting as pixelite.cz. This repository contains **no bot code** and its deploy never touches a bot. Pixelite only links
to the bots and (later) monitors them through each bot's `/health`.

Why: a broken bot push cannot take pixelite.cz down and vice versa, private bot code never enters this public repo, and
each bot keeps its own tests, secrets, database and release rhythm. The cost: no single place records which bot version
is live, so every bot reports its deployed commit in `/health` (see the contract below).

## Server layout (FTP login directory)
```
./                                Pixelite private app (src/ templates/ lang/ config/ storage/ .env) – owned by THIS repo
./public_html/                    Pixelite web root – owned by THIS repo
./public_html/tg/<random-path>/   bot web entry: index.php + .htaccess only – owned by the bot repo
./bots/<slug>/                    bot private files: src/ config/ lang/ migrations/ storage/ .env – owned by the bot repo
```
- **Reserved names:** `./bots/` and `./public_html/tg/` belong to bots. Pixelite must never ship anything there; the
  artifact verifier already enforces it (`public_html` top level is exactly `index.php .htaccess favicon.ico assets`,
  the private top level is exactly `config lang src storage templates`). Pixelite has no `/tg…` route.
- `<random-path>` is a long random segment (defence in depth for `/hook` and `/tick`, which are also protected by their
  secret headers). It is a secret of the bot repo and is never written into this public repository.
- Apache: the bot directory's own `.htaccess` (with `RewriteEngine On`) replaces Pixelite's rewrite rule for that
  directory. Pixelite's `Require all denied` rules for dotfiles and data extensions are inherited, which only adds
  protection.
- `.env`, `storage/`, the SQLite database and logs of a bot are created on the server once, by hand or by the bot's
  first run, and are never uploaded or deleted by CI.

## Rules for a bot's deploy workflow
1. FTP-Deploy-Action (Pixelite pins `SamKirkland/FTP-Deploy-Action@8e83cea8672e3fbcbb9fdafff34debf6ae4c5f65`, v4.3.5),
   `dangerous-clean-slate: false`, dry run unless the bot repo's variable `DEPLOY_ENABLED` is `true`.
2. `server-dir` is **only** `./bots/<slug>/` or `./public_html/tg/<random-path>/`. Never `./` or `./public_html/`:
   the action deletes whatever its state file lists, and Pixelite's own state files live there
   (`./.ftp-deploy-sync-state-app.json`, `./public_html/.ftp-deploy-sync-state.json`). One wrong `server-dir` or
   `state-name` can delete the website.
3. A unique `state-name` per step (e.g. `.ftp-deploy-sync-state-bot-<slug>.json`).
4. Exclude `.env*`, `storage/**` except `.gitkeep`/`.htaccess`, `*.sqlite*`, `*.log`, `.git*`.
5. The front controller must resolve its root to `./bots/<slug>/` and fail closed (empty 500) if that root is not the
   bot's own. Resolving to the FTP login directory would load **Pixelite's** `src/bootstrap.php` and `.env`.

## Secrets
GitHub secrets are per repository (a personal account has no shared secrets), so the FTP values are added to each bot
repo by hand, same names as here: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`; variables `FTP_PROTOCOL`, `FTP_PORT`,
`FTP_SECURITY` (the host's certificate does not match the FTP host name; see `docs/deployment/endora.md`).
```bash
gh secret set FTP_SERVER   -R EmirTheBest7/<bot-repo>
gh secret set FTP_USERNAME -R EmirTheBest7/<bot-repo>
gh secret set FTP_PASSWORD -R EmirTheBest7/<bot-repo>
```

## Isolation (accepted risk, owner's own bots only)
All PHP on one Endora hosting runs as the same user without `open_basedir`, and the FTP account can write everything.
A bot bug or a leaked bot-repo secret can therefore read Pixelite's `.env` or change the website. That is accepted for
bots the owner writes. **Customer bots, or any code Pixelite does not control, go on a separate hosting** with its own
FTP account, using the same layout and workflow.

## Server config from Pixelite: `.github/workflows/provision-bots.yml`
A bot's production `.env` must never be in a repository, so this repo provisions it from a GitHub secret:
- Secret **`BOT_LASH_ENV`** (this repo) = the bot's whole production `.env`. Set it from the file, never by pasting:
  `gh secret set BOT_LASH_ENV -R EmirTheBest7/PIXELITE < ~/Projects/GITHUB/TG-PHPBot/.env.production`
- The workflow validates it (APP_ENV, BASE_URL `https://pixelite.cz/tg/<16-64 chars>`, a token-shaped BOT_TOKEN,
  WEBHOOK_SECRET, TICK_SECRET non-empty; no DB_PATH/LOG_DIR) and refuses to upload a broken file. Then it uploads
  `./bots/lash/.env` and `./storage/bot-urls.ini` (`BOT_LASH_URL = "<BASE_URL>"`, read by `/admin/bots`), each with its
  own FTP state file, so it can only replace those two files. Nothing is echoed; the path segment is masked.
- Runs on a push that changes the workflow or `config/bots.php`, or by hand (Actions → Run workflow). **Changing the
  secret alone does nothing until it runs.** Live only when `DEPLOY_ENABLED=true`, same as the site deploy. Without the
  secret it skips with a notice.
- The secret is the source of truth: a hand edit of the server `.env` is overwritten only when the secret changes and
  the workflow runs again (the action compares against its own state, not the server file).
- The bot repo's secrets must agree with it: `BOT_PUBLIC_PATH` = the path segment of `BASE_URL`, `TICK_SECRET` = the
  same `TICK_SECRET`.

## Monitoring: `/admin/bots`
Staff page (same guard as the rest of `/admin`, read-only, no CSRF needed). Every load calls each registered bot's
`/health` from the server (`src/BotMonitor.php`: GET only, no redirects, 3 s connect / 5 s total, body capped at 8 KB) and
shows up / degraded / down / not configured, the live commit (linked to the bot repo), deploy time, database flag and
response time. Only the host is displayed, never the secret path, and error texts are stripped of the URL.
- Register a bot in `config/bots.php` (`name`, `repo`, `env`). Its base URL comes from Pixelite's `.env` under that
  key, else from `storage/bot-urls.ini` written by the provisioning workflow. Without either it shows "not configured".
- The check runs from the Endora server to its own public domain. If a bot shows "down" while it works from outside,
  check that the host can reach its own HTTPS address before suspecting the bot.

## `/health` contract (for monitoring from Pixelite)
`GET https://pixelite.cz/tg/<random-path>/health` → `200`, `Content-Type: application/json`, no cookies, < 1 s, e.g.
`{"ok":true,"bot":"<slug>","version":"<git sha>","deployed_at":"<UTC ISO-8601>","db":true}`.
No token, no secrets, no personal data, no Telegram calls. `ok:false` (still JSON) when the database or `.env` is broken.

## Scheduled work
The host has no cron. A bot that needs periodic work exposes a secret-protected endpoint (TG-PHPBot: `/tick` with
`X-Tick-Secret`) that an external scheduler calls. GitHub Actions `schedule` works but often runs late.

## Hosted bots
| Slug | Repository | Purpose | Status |
|---|---|---|---|
| `lash` | `EmirTheBest7/TG-PHPBot` (private) | lash studio booking bot | in development |
