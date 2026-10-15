#!/usr/bin/env bash
# Post-deployment check of the LIVE site. Usage: scripts/smoke-live.sh https://pixelite.cz
# Verifies the real app is served (not the host's placeholder), assets load, both languages route, SEO endpoints
# answer, and that no private file is reachable. Prints only URLs/status codes, never secrets.
set -u
base="${1:?usage: smoke-live.sh https://host}"; base="${base%/}"
fail=0; ok() { echo "  ok   $1"; }; bad() { echo "  FAIL $1"; fail=1; }
req() { curl -s -m 25 -A 'PixeliteSmoke/1.0' "$@"; }
code() { req -o /dev/null -w '%{http_code}' "$1"; }

echo "waiting for the new deployment to be served…"
for i in $(seq 1 12); do
  if req -L "$base/" | grep -qi 'pixelite'; then ok "homepage serves the Pixelite app (attempt $i)"; break; fi
  [ "$i" = 12 ] && bad "homepage still not the Pixelite app after 12 attempts"; sleep 10
done

home="$(req -L "$base/")"
echo "$home" | grep -qi 'Hello World' && bad "placeholder 'Hello World' still visible" || ok "no placeholder text"
r="$(req -o /dev/null -w '%{http_code} %{redirect_url}' "$base/")"
case "$r" in "302 $base/en/"|"302 $base/cs/"|"200 ") ok "GET / -> $r (language redirect)";; *) bad "GET / -> $r";; esac
for p in en/ cs/ en/pricing cs/pricing en/process cs/process en/order cs/contact en/privacy en/cookies en/terms; do
  c="$(code "$base/$p")"; [ "$c" = 200 ] && ok "GET /$p -> 200" || bad "GET /$p -> $c"
done
en="$(req "$base/en/")"; cs="$(req "$base/cs/")"
echo "$en" | grep -q '<html lang="en">' && ok "/en/ html lang=en" || bad "/en/ lang"
echo "$cs" | grep -q '<html lang="cs">' && ok "/cs/ html lang=cs" || bad "/cs/ lang"

echo "assets emitted by the homepage:"
assets="$(echo "$en" | grep -oE '(href|src)="/(assets/[^"]+|favicon\.ico)"' | sed -E 's/^(href|src)="//; s/"$//' | sort -u)"
[ -n "$assets" ] || bad "no asset URLs found in the homepage"
for a in $assets; do
  c="$(code "$base$a")"; [ "$c" = 200 ] && ok "GET $a -> 200" || bad "GET $a -> $c"
done

echo "SEO endpoints:"
rb="$(req "$base/robots.txt")"; echo "$rb" | grep -q "Sitemap: $base/sitemap.xml" && ok "robots.txt names $base/sitemap.xml" || bad "robots.txt sitemap line"
echo "$rb" | grep -q 'Disallow: /admin' && ok "robots.txt disallows /admin" || bad "robots.txt /admin"
sm="$(req "$base/sitemap.xml")"; echo "$sm" | grep -q "<loc>$base/en/pricing</loc>" && ok "sitemap lists absolute $base URLs" || bad "sitemap absolute URLs"
echo "$en" | grep -q "<link rel=\"canonical\" href=\"$base/en/\">" && ok "canonical uses the live origin" || bad "canonical origin"
echo "$en" | grep -q 'name="robots" content="index,follow"' && ok "/en/ indexable" || bad "/en/ robots meta"
[ "$(req "$base/en/order/sent" | grep -c 'noindex')" -ge 1 ] && ok "/en/order/sent noindex" || echo "  info order/sent noindex not verified (needs a session)"

echo "private files must NOT be reachable:"
for p in src/bootstrap.php templates/layout.php lang/en.php config/consent.php storage/ storage/leads.sqlite .env .env.example CLAUDE.md Dockerfile docker-compose.yml tests/run.php bin/hash-password.php public/index.php public/assets/style.css .git/config .htaccess .ftp-deploy-sync-state.json .ftp-deploy-sync-state-app.json; do
  c="$(code "$base/$p")"; b="$(req "$base/$p" | head -c 400)"
  if { [ "$c" = 403 ] || [ "$c" = 404 ]; } && ! echo "$b" | grep -qE '<\?php|namespace Pixelite|# Pixelite\.cz|FROM php'; then ok "/$p -> $c"; else bad "/$p -> $c (reachable or leaking)"; fi
done
[ "$fail" = 0 ] && echo "SMOKE PASSED" || { echo "SMOKE FAILED"; exit 1; }
