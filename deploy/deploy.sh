#!/usr/bin/env bash
# Deployment der ServiceInvest-Website auf IONOS (per SSH im WordPress-Stammverzeichnis ausführen).
#
#   cd <WordPress-Stammverzeichnis>
#   SRC_DIR=$HOME/sin-code NEW_URL=http://<service-domain> ADMIN_MAIL=name@example.de bash $SRC_DIR/deploy/deploy.sh
#   (SRC_DIR = entpacktes Code-Paket aus "git archive"; ohne SRC_DIR wird $REPO geklont, nur bei öffentlichem Repo)
#
# Voraussetzungen: SSH, PHP-CLI >= 8.3 (PHP=/usr/bin/php8.3-cli), bereits installiertes WordPress mit Divi,
# bereinigter Dump unter $DUMP (deploy/build-dump.sh, nicht im Repository!). Schriften und PDFs kommen aus dem Assets-Paket.
#
# Ablauf: Backup -> Divi-Lizenz sichern -> Theme/Plugins -> DB ersetzen -> URLs ersetzen -> Admin anlegen -> Prüfung.
set -euo pipefail

PHP=${PHP:-php}
NEW_URL=${NEW_URL:?NEW_URL fehlt (z. B. http://<service-domain>)}
ADMIN_MAIL=${ADMIN_MAIL:?ADMIN_MAIL fehlt}
ADMIN_USER=${ADMIN_USER:-SIN_Admin}
DUMP=${DUMP:-$HOME/sin-live.sql}
OLD_URL=${OLD_URL:-http://localhost:8081}
INDEXING=${INDEXING:-0}          # 0 = Suchmaschinen sperren (Staging), 1 = Livegang
REPO=${REPO:-https://github.com/ASSETPHYSICS/SIN.git}
NEW_URL=${NEW_URL%/}
# Bei IONOS ist /tmp zu klein (WordPress-, Plugin-Downloads scheitern) – eigenes Temp-Verzeichnis im Home
export TMPDIR=${DEPLOY_TMPDIR:-$HOME/temp}
mkdir -p "$TMPDIR"

[ -f wp-config.php ] || { echo "Bitte im WordPress-Stammverzeichnis ausführen (wp-config.php fehlt)."; exit 1; }
[ -f "$DUMP" ] || { echo "Dump nicht gefunden: $DUMP"; exit 1; }
[ -f wp-cli.phar ] || curl -sSLo wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
WP() { $PHP -d memory_limit=512M wp-cli.phar "$@"; }
fix_perms() { # Verzeichnisse 755, Dateien 644 (Zip-Archive aus Windows enthalten keine Unix-Rechte)
  for d in "$@"; do
    [ -e "$d" ] || continue
    find "$d" -type d -print0 | xargs -0 -r -n 50 chmod 755
    find "$d" -type f -print0 | xargs -0 -r -n 50 chmod 644
  done
}

echo "== PHP: $($PHP -r 'echo PHP_VERSION;')"
echo "== 1/9 Backup der vorhandenen Datenbank"
STAMP=$(date +%Y%m%d-%H%M%S)
WP db export "$HOME/backup-vor-deploy-$STAMP.sql"
chmod 600 "$HOME/backup-vor-deploy-$STAMP.sql"

echo "== 2/9 Divi-Lizenz sichern"
WP option get et_automatic_updates_options --format=json > "$HOME/.divi-lizenz.json" 2>/dev/null || echo '' > "$HOME/.divi-lizenz.json"
chmod 600 "$HOME/.divi-lizenz.json"

echo "== 3/9 Theme und Plugins"
fix_perms wp-content/themes/Divi-SIN wp-content/uploads
TMP=$(mktemp -d)
if [ -n "${SRC_DIR:-}" ]; then   # entpacktes Code-Paket (Repo ist privat, der Server kann nicht klonen)
  [ -d "$SRC_DIR/wp-content/themes/Divi-SIN" ] || { echo "SRC_DIR ungültig: $SRC_DIR"; exit 1; }
  ln -s "$SRC_DIR" "$TMP/repo"
else
  git clone --depth 1 "$REPO" "$TMP/repo"
fi
mkdir -p wp-content/themes wp-content/plugins wp-content/mu-plugins
copy_dir() { # Quelle Ziel [rsync-Optionen]
  if command -v rsync >/dev/null; then rsync -a --delete "${@:3}" "$1/" "$2/"; else rm -rf "$2"; mkdir -p "$2"; cp -a "$1/." "$2/"; fi
}
mkdir -p wp-content/themes/Divi-SIN
if [ -d wp-content/themes/Divi-SIN/fonts ]; then
  copy_dir "$TMP/repo/wp-content/themes/Divi-SIN" wp-content/themes/Divi-SIN --exclude=fonts/
else
  copy_dir "$TMP/repo/wp-content/themes/Divi-SIN" wp-content/themes/Divi-SIN
fi
copy_dir "$TMP/repo/wp-content/plugins/appointment-booking" wp-content/plugins/appointment-booking
cp -a "$TMP/repo/wp-content/mu-plugins/." wp-content/mu-plugins/
HTACCESS_SRC=$(mktemp); cp "$TMP/repo/deploy/htaccess" "$HTACCESS_SRC"
cp "$TMP/repo/router.php" .deploy-router.php   # für die Prüfung in Schritt 9
trap 'rm -f .deploy-router.php deploy-check-*.txt "$HTACCESS_SRC"; [ -z "${SRV:-}" ] || kill "$SRV" 2>/dev/null' EXIT
rm -rf "$TMP"
WP plugin is-installed polylang || WP plugin install polylang ${POLYLANG_VERSION:+--version="$POLYLANG_VERSION"}
[ -f wp-content/themes/Divi-SIN/fonts/NeueHaasGroteskDisplay-Roman.woff2 ] || echo "!! WARNUNG: Schriften fehlen in wp-content/themes/Divi-SIN/fonts/ (Assets-Paket entpacken)"
[ -f wp-content/uploads/2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf ] || echo "!! WARNUNG: PDFs fehlen in wp-content/uploads/ (Assets-Paket entpacken)"

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

echo "== 7/9 Divi-Lizenz zurückschreiben, Theme/Plugins aktivieren, Admin anlegen"
if grep -q "{" "$HOME/.divi-lizenz.json"; then WP option update et_automatic_updates_options --format=json < "$HOME/.divi-lizenz.json"; fi
WP theme activate Divi-SIN
WP plugin activate polylang appointment-booking
WP option update blog_public "$INDEXING"
WP option update admin_email "$ADMIN_MAIL"
WP rewrite structure '/%postname%/'
# WP-CLI darf die .htaccess nicht schreiben; ohne sie liefern alle Unterseiten 404
if [ ! -f .htaccess ] || ! grep -q "BEGIN WordPress" .htaccess; then
  [ -f .htaccess ] && cp .htaccess "$HOME/htaccess-vorher-$STAMP.bak"
  cp "$HTACCESS_SRC" .htaccess; chmod 644 .htaccess
  echo ".htaccess aus deploy/htaccess angelegt"
fi
WP cache flush
rm -rf wp-content/et-cache
# Rollen neu anlegen (der bereinigte Dump enthält wp_user_roles nicht)
WP eval 'require_once ABSPATH . "wp-admin/includes/schema.php"; populate_roles();'
ADMIN_PW=$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)
WP user create "$ADMIN_USER" "$ADMIN_MAIL" --role=administrator --user_pass="$ADMIN_PW" --porcelain >/dev/null
echo "Admin: $ADMIN_USER  Passwort (einmalig, sofort in den Passwortmanager): $ADMIN_PW"

echo "== 8/9 Dateirechte"
fix_perms wp-content/themes/Divi-SIN wp-content/plugins/appointment-booking wp-content/plugins/polylang wp-content/mu-plugins wp-content/uploads
chmod 600 wp-config.php

echo "== 9/9 Prüfung (erwartet: 200 für Seiten und PDF, 301 für alte Adressen, 403/404/405 für xmlrpc.php und die Benutzerliste)"
PAGES=("" nachhaltigkeit-2/ serviceinvest-impressum/ datenschutzerklaerung/ datenschutzhinweisefuermieter/ beschwerdestelle/ verguetungspolitik/
       tas-wohnen-1/ en/ en/imprint/ en/sustainability/ nachhaltigkeit/ home-en/
       wp-content/uploads/2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf xmlrpc.php wp-json/wp/v2/users)
AUTH=(); [ -n "${BASIC_AUTH:-}" ] && AUTH=(-u "$BASIC_AUTH")   # BASIC_AUTH=user:passwort, falls Staging-Schutz aktiv
check() { # Basis-URL [curl-Optionen]
  local base=$1 p; shift
  for p in "${PAGES[@]}"; do
    printf "%-60s %s\n" "/$p" "$(curl -s -m 30 -o /dev/null -w '%{http_code} %{redirect_url}' "$@" "$base/$p" 2>/dev/null || true)"
  done
}
# Zeigt NEW_URL schon auf diese Installation? Sonst (Domain noch nicht umgestellt) über einen lokalen PHP-Server prüfen.
MARK=deploy-check-$$.txt; echo "$STAMP" > "$MARK"
if [ "$(curl -s -m 15 "${AUTH[@]}" "$NEW_URL/$MARK" 2>/dev/null)" = "$STAMP" ]; then
  echo "$NEW_URL zeigt auf diese Installation:"
  check "$NEW_URL" "${AUTH[@]}"
else
  PORT=${CHECK_PORT:-8099}
  echo "$NEW_URL zeigt noch nicht auf diese Installation – Prüfung über lokalen PHP-Server (ohne .htaccess):"
  $PHP -S "127.0.0.1:$PORT" .deploy-router.php >/dev/null 2>&1 & SRV=$!
  sleep 2
  check "http://127.0.0.1:$PORT" -H "Host: $NEW_HOST"
fi
echo "Fertig. Backup: ~/backup-vor-deploy-$STAMP.sql  |  Indexierung: blog_public=$INDEXING"
