#!/usr/bin/env bash
# Läuft auf dem Server (von deploy/v1/deploy.sh per SSH gestartet). Siehe deploy/v1/README.md.
# Richtet ~/public/_sin_v1 für https://www.serviceinvest.de ein; die bisherige Website ~/public/_sin bleibt unverändert.
set -euo pipefail

URL="https://www.serviceinvest.de"
PREFIX="v1_"
SRC="$HOME/temp/sin-v1-src"
OLD="$HOME/public/_sin"          # bisherige Website (nur gelesen)
DIR="$HOME/public/_sin_v1"       # neue Website
SAFE="$HOME/geschuetzt/sin"      # Fonds-Offenlegungen, außerhalb des Webroots (SIN_FONDS_DIR)
CLI="$HOME/wp-cli.phar"
WP() { php -d memory_limit=512M "$CLI" --path="$DIR" "$@"; }
OLDWP() { php -d memory_limit=512M "$CLI" --path="$OLD" "$@"; }
export TMPDIR="$HOME/temp"   # /tmp ist auf dem Webspace zu klein
mkdir -p "$TMPDIR" "$HOME/backups" "$HOME/config" "$HOME/logs"

# --- Schutz ---
[ "$DIR" != "$OLD" ] || { echo "Zielordner ist der bisherige Live-Ordner"; exit 1; }
[ -f "$OLD/wp-includes/version.php" ] || { echo "Bisherige Installation $OLD nicht gefunden"; exit 1; }
[ -d "$SRC/wp-content/themes/sin-v1" ] || { echo "Upload fehlt: $SRC"; exit 1; }

echo "-- Datenbank sichern (bisherige Tabellen wp_* und, falls vorhanden, ${PREFIX}*)"
STAMP="$(date +%Y%m%d-%H%M%S)"
OLDWP db export "$HOME/backups/sin-vor-v1-$STAMP.sql" >/dev/null
chmod 600 "$HOME/backups/sin-vor-v1-$STAMP.sql"
echo "   ~/backups/sin-vor-v1-$STAMP.sql"

echo "-- WordPress-Kern (Version der bisherigen Installation: $(OLDWP core version))"
mkdir -p "$DIR/wp-admin" "$DIR/wp-includes"
cp -a "$OLD/wp-admin/." "$DIR/wp-admin/"
cp -a "$OLD/wp-includes/." "$DIR/wp-includes/"
for f in index.php wp-activate.php wp-blog-header.php wp-comments-post.php wp-cron.php wp-links-opml.php wp-load.php wp-login.php wp-mail.php wp-settings.php wp-signup.php wp-trackback.php xmlrpc.php; do
	cp -a "$OLD/$f" "$DIR/$f"
done

echo "-- Build-Dateien ins Stammverzeichnis (assets/, Favicons, sitemap.xml, robots.txt)"
bash "$SRC/tools/v1/copy-static.sh" "$SRC/content/v1-build" "$DIR"

echo "-- PAI-PDFs nach /dokumente/ (HA 8, aus der bisherigen Website)"
mkdir -p "$DIR/dokumente"
for f in 2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf 2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_ENG-clean.pdf; do
	cp -p "$OLD/wp-content/uploads/2026/06/$f" "$DIR/dokumente/$f"
done

echo "-- Fonds-Offenlegungen nach $SAFE (HA 7, außerhalb des Webroots)"
mkdir -p "$SAFE"
chmod 700 "$HOME/geschuetzt" "$SAFE"
for slug in debeka-immobilien-invest-i tas-wohnen-1; do
	mkdir -p "$SAFE/$slug"
	if ls "$SRC/geschuetzt/$slug/"*.pdf >/dev/null 2>&1; then
		# hochgeladener Stand ersetzt den Ordner (ältere Fassungen entfallen)
		rm -f "$SAFE/$slug/"*.pdf
		cp -p "$SRC/geschuetzt/$slug/"*.pdf "$SAFE/$slug/"
		echo "   $slug: $(ls "$SAFE/$slug" | tr '\n' ' ')"
	fi
done
find "$SAFE" -type d -exec chmod 700 {} + ; find "$SAFE" -type f -exec chmod 600 {} +

echo "-- Theme, mu-Plugins"
mkdir -p "$DIR/wp-content"/{themes,plugins,mu-plugins,uploads,languages}
[ -f "$DIR/wp-content/index.php" ] || printf '<?php\n// Silence is golden.\n' > "$DIR/wp-content/index.php"
[ -f "$DIR/wp-content/plugins/index.php" ] || printf '<?php\n// Silence is golden.\n' > "$DIR/wp-content/plugins/index.php"
rm -rf "$DIR/wp-content/themes/sin-v1"
cp -a "$SRC/wp-content/themes/sin-v1" "$DIR/wp-content/themes/sin-v1"
cp -a "$SRC/wp-content/mu-plugins/sin-hardening.php" "$SRC/wp-content/mu-plugins/site-mail.php" "$DIR/wp-content/mu-plugins/"
[ -d "$OLD/wp-content/languages" ] && cp -a "$OLD/wp-content/languages/." "$DIR/wp-content/languages/"

echo "-- wp-config.php"
if [ ! -f "$DIR/wp-config.php" ]; then
	# Zugangsdaten der Datenbank und Mail-Einbindung (~/config/mail-smtp.php) aus der bisherigen Installation
	cp "$OLD/wp-config.php" "$DIR/wp-config.php"
	WP config set table_prefix "$PREFIX" --type=variable >/dev/null
	WP config shuffle-salts >/dev/null
fi
grep -q "^\$table_prefix = '${PREFIX}';" "$DIR/wp-config.php" || { echo "Tabellenpräfix stimmt nicht – Abbruch vor dem ersten Datenbankzugriff"; exit 1; }
WP config set WP_ENVIRONMENT_TYPE production --type=constant >/dev/null
WP config set DISALLOW_FILE_EDIT true --raw --type=constant >/dev/null
WP config set WP_DEBUG false --raw --type=constant >/dev/null
WP config set WP_CACHE false --raw --type=constant >/dev/null
WP config set SITE_MAIL_FROM_NAME 'ServiceInvest' --type=constant >/dev/null
WP config set SIN_KONTAKT_TO 'info@serviceinvest.de' --type=constant >/dev/null
# Postfach für Mieteranliegen: von ServiceInvest noch zu benennen (HA 15); bis dahin info@serviceinvest.de
grep -q "'SIN_MIETER_TO'" "$DIR/wp-config.php" || WP config set SIN_MIETER_TO 'info@serviceinvest.de' --type=constant >/dev/null
chmod 600 "$DIR/wp-config.php"

echo "-- .htaccess"
install -m 644 "$SRC/deploy/v1/htaccess-live" "$DIR/.htaccess"

echo "-- Installation (nur beim ersten Mal)"
if ! WP db query "SELECT 1 FROM ${PREFIX}options LIMIT 1" >/dev/null 2>&1; then
	PASS="$(openssl rand -base64 30 | tr -dc 'A-Za-z0-9' | head -c 24)"
	WP core install --url="$URL" --title="ServiceInvest" --admin_user=sin-v1-admin \
		--admin_password="$PASS" --admin_email=michael.benninghoff@2ig.de --skip-email >/dev/null
	( umask 077; printf 'Neue Website www.serviceinvest.de (v1)\nAnmeldung: %s/wp-admin/\nBenutzer:   sin-v1-admin\nPasswort:   %s\n' "$URL" "$PASS" > "$HOME/config/sin-v1-admin.txt" )
	unset PASS
	WP site empty --yes >/dev/null
	echo "   installiert; Zugang in ~/config/sin-v1-admin.txt"
else
	echo "   bereits installiert"
fi

echo "-- Adresse $URL"
WP option update home "$URL" >/dev/null
WP option update siteurl "$URL" >/dev/null
# wp core install hängt auf diesem Server den Ordnerpfad an; nur Tabellen mit Präfix v1_
WP search-replace "$URL/public/_sin_v1" "$URL" --all-tables-with-prefix --skip-columns=guid >/dev/null 2>&1 || true

echo "-- Einstellungen"
WP language core install de_DE >/dev/null 2>&1 || true
WP site switch-language de_DE >/dev/null 2>&1 || true
WP option update timezone_string Europe/Berlin >/dev/null
WP option update date_format 'j. F Y' >/dev/null
WP option update time_format 'G:i' >/dev/null
WP option update default_comment_status closed >/dev/null
WP option update default_ping_status closed >/dev/null
WP option update users_can_register 0 >/dev/null
WP option update blog_public 1 >/dev/null
WP theme activate sin-v1 >/dev/null
WP rewrite structure '/%postname%/' >/dev/null
install -m 644 "$SRC/deploy/v1/htaccess-live" "$DIR/.htaccess"   # WordPress darf die Datei nicht ändern

echo "-- Seiten importieren"
WP eval-file "$SRC/tools/v1/import.php" "$SRC/content/v1-build" 2>&1 | tail -1
echo "-- Fonds-Offenlegungen (Passwörter der bisherigen Fondsseiten als Hash, Titel)"
WP eval-file "$SRC/tools/v1/fonds-migration.php" wp_ 2>&1 | sed 's/^/   /'
WP rewrite flush >/dev/null
WP cache flush >/dev/null 2>&1 || true

echo "-- Werkzeuge außerhalb des Webroots (deploy.sh check, Passwort setzen)"
rm -rf "$HOME/sin-v1-check" && mkdir -p "$HOME/sin-v1-check/tools/v1" "$HOME/sin-v1-check/content"
cp -a "$SRC/content/v1-build" "$HOME/sin-v1-check/content/v1-build"
cp "$SRC/deploy/v1/check-router.php" "$HOME/sin-v1-check/router.php"
cp "$SRC/tools/v1/check.php" "$SRC/tools/v1/fonds-passwort.php" "$HOME/sin-v1-check/tools/v1/"

# xargs in kleinen Blöcken: "find -exec chmod {} +" scheitert auf dem Webspace an der Argumentlänge
find "$DIR" -type d -print0 | xargs -0 -n 200 chmod 755
find "$DIR" -type f ! -name wp-config.php -print0 | xargs -0 -n 200 chmod 644
chmod 600 "$DIR/wp-config.php"
rm -rf "$SRC"

echo "-- Kontrolle"
printf '   WordPress %s | home=%s | Theme=%s | blog_public=%s | Seiten=%s | Alias=%s\n' "$(WP core version)" "$(WP option get home)" \
	"$(WP option get stylesheet)" "$(WP option get blog_public)" "$(WP post list --post_type=page --post_status=publish --format=count)" \
	"$(WP config get SITE_MAIL_FROM_NAME)"
printf '   Bisherige Website: home=%s (unverändert), Tabellen wp_*: %s\n' "$(OLDWP option get home)" \
	"$(OLDWP db query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'wp\\_%'" --skip-column-names)"
