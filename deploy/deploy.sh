#!/usr/bin/env bash
# Deployment der 2IP-Website auf IONOS (per SSH im WordPress-Stammverzeichnis ausführen).
#
#   cd <WordPress-Stammverzeichnis>
#   NEW_URL=https://2ip4896.live-website.com ADMIN_MAIL=name@2ip.de ./deploy.sh
#
# Voraussetzungen: SSH, PHP-CLI >= 8.3 (PHP=/usr/bin/php8.3-cli), git, bereits installiertes WordPress mit Divi,
# bereinigter Dump unter $DUMP (nicht im Repository!). Schriften werden separat hochgeladen.
#
# Ablauf: Backup -> Divi-Lizenz sichern -> Theme/Plugins aus GitHub -> DB ersetzen -> URLs ersetzen -> Admin anlegen -> Prüfung.
set -euo pipefail

PHP=${PHP:-php}
NEW_URL=${NEW_URL:?NEW_URL fehlt (z. B. https://2ip4896.live-website.com)}
ADMIN_MAIL=${ADMIN_MAIL:?ADMIN_MAIL fehlt}
ADMIN_USER=${ADMIN_USER:-2ip-admin}
DUMP=${DUMP:-$HOME/2ip-live.sql}
OLD_URL=${OLD_URL:-http://localhost:8080}
INDEXING=${INDEXING:-0}          # 0 = Suchmaschinen sperren (Staging), 1 = Livegang
REPO=${REPO:-https://github.com/ASSETPHYSICS-org/2ip.git}
NEW_URL=${NEW_URL%/}

[ -f wp-config.php ] || { echo "Bitte im WordPress-Stammverzeichnis ausführen (wp-config.php fehlt)."; exit 1; }
[ -f "$DUMP" ] || { echo "Dump nicht gefunden: $DUMP"; exit 1; }
[ -f wp-cli.phar ] || curl -sSLo wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
WP() { $PHP -d memory_limit=512M wp-cli.phar "$@"; }

echo "== PHP: $($PHP -r 'echo PHP_VERSION;')"
echo "== 1/9 Backup der vorhandenen Datenbank"
STAMP=$(date +%Y%m%d-%H%M%S)
WP db export "$HOME/backup-vor-deploy-$STAMP.sql"
chmod 600 "$HOME/backup-vor-deploy-$STAMP.sql"

echo "== 2/9 Divi-Lizenz sichern"
WP option get et_automatic_updates_options --format=json > "$HOME/.divi-lizenz.json" 2>/dev/null || echo '' > "$HOME/.divi-lizenz.json"
chmod 600 "$HOME/.divi-lizenz.json"

echo "== 3/9 Theme und Plugins aus GitHub"
TMP=$(mktemp -d)
git clone --depth 1 "$REPO" "$TMP/repo"
mkdir -p wp-content/themes wp-content/plugins wp-content/mu-plugins
copy_dir() { # Quelle Ziel [rsync-Optionen]
  if command -v rsync >/dev/null; then rsync -a --delete "${@:3}" "$1/" "$2/"; else rm -rf "$2"; mkdir -p "$2"; cp -a "$1/." "$2/"; fi
}
mkdir -p wp-content/themes/Divi-2IP
if [ -d wp-content/themes/Divi-2IP/fonts ]; then
  copy_dir "$TMP/repo/wp-content/themes/Divi-2IP" wp-content/themes/Divi-2IP --exclude=fonts/
else
  copy_dir "$TMP/repo/wp-content/themes/Divi-2IP" wp-content/themes/Divi-2IP
fi
copy_dir "$TMP/repo/wp-content/plugins/appointment-booking" wp-content/plugins/appointment-booking
cp -a "$TMP/repo/wp-content/mu-plugins/." wp-content/mu-plugins/
rm -rf "$TMP"
[ -f wp-content/themes/Divi-2IP/fonts/NeueHaasGroteskDisplay-Roman.woff2 ] || echo "!! WARNUNG: Schriften fehlen in wp-content/themes/Divi-2IP/fonts/ (separat hochladen)"

echo "== 4/9 Konfiguration"
WP config set table_prefix wp_ --type=variable
WP config set WP_ENVIRONMENT_TYPE production
WP config set DISALLOW_FILE_EDIT true --raw
WP config set WP_DEBUG false --raw
WP config shuffle-salts

echo "== 5/9 Datenbank ersetzen"
WP db reset --yes
WP db import "$DUMP"

echo "== 6/9 URLs ersetzen ($OLD_URL -> $NEW_URL)"
WP option update siteurl "$NEW_URL"
WP option update home "$NEW_URL"
WP search-replace "$OLD_URL" "$NEW_URL" --all-tables --skip-columns=guid
OLD_ESC=${OLD_URL//\//\\/}; NEW_ESC=${NEW_URL//\//\\/}
WP search-replace "$OLD_ESC" "$NEW_ESC" --all-tables --skip-columns=guid
OLD_HOST=${OLD_URL#*://}; NEW_HOST=${NEW_URL#*://}
echo "Verbleibende Treffer für $OLD_HOST:"; WP db query "select count(*) from wp_posts where post_content like '%${OLD_HOST}%'" --skip-column-names

echo "== 7/9 Divi-Lizenz zurückschreiben, Theme/Plugin aktivieren, Admin anlegen"
if [ -s "$HOME/.divi-lizenz.json" ]; then WP option update et_automatic_updates_options --format=json < "$HOME/.divi-lizenz.json"; fi
WP theme activate Divi-2IP
WP plugin activate appointment-booking
WP option update blog_public "$INDEXING"
WP rewrite structure '/%postname%/' --hard
WP cache flush
rm -rf wp-content/et-cache
ADMIN_PW=$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)
WP user create "$ADMIN_USER" "$ADMIN_MAIL" --role=administrator --user_pass="$ADMIN_PW" --porcelain >/dev/null
echo "Admin: $ADMIN_USER  Passwort (einmalig, sofort in den Passwortmanager): $ADMIN_PW"

echo "== 8/9 Dateirechte"
find wp-content/themes/Divi-2IP wp-content/plugins/appointment-booking wp-content/mu-plugins -type d -exec chmod 755 {} \; -o -type f -exec chmod 644 {} \;
chmod 600 wp-config.php

echo "== 9/9 Prüfung"
for p in "" nachhaltigkeit/ second-opinions/ impressum/ impressum/datenschutzerklaerung/ nachhaltigkeit/pai-statement/ nachhaltigkeit/ausgewaehlte-fonds/icf-glog/ xmlrpc.php wp-json/wp/v2/users; do
  AUTH=(); [ -n "${BASIC_AUTH:-}" ] && AUTH=(-u "$BASIC_AUTH")   # BASIC_AUTH=user:passwort, falls Staging-Schutz aktiv
  printf "%-60s %s\n" "/$p" "$(curl -s -o /dev/null -w '%{http_code}' "${AUTH[@]}" "$NEW_URL/$p" 2>/dev/null || echo '-')"
done
echo "Fertig. Backup: ~/backup-vor-deploy-$STAMP.sql  |  Indexierung: blog_public=$INDEXING"
