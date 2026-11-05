#!/usr/bin/env bash
# Neue Website www.serviceinvest.de (Paket serviceinvest-website) auf den IONOS-Webspace su172769 bringen. Läuft lokal
# (Git Bash oder WSL); vorher lokal bauen: bash tools/v1/build.sh (das Ergebnis content/v1-build/ ist versioniert).
#
#   deploy/v1/deploy.sh          ~/public/_sin_v1 einrichten/aktualisieren, fertig konfiguriert für https://www.serviceinvest.de.
#                                Seit 05.11.2026 live (Panel zeigt auf /_sin_v1); die bisherige Website ~/public/_sin samt
#                                Tabellen wp_* ist entfernt, remote.sh überspringt die Schritte, die sie brauchten.
#   deploy/v1/deploy.sh check    Prüfung VOR dem Umschalten: startet auf dem Webspace einen PHP-Server für _sin_v1
#                                (127.0.0.1:18092, Host www.serviceinvest.de) und führt dort tools/v1/check.php aus
#                                (ohne .htaccess; die Apache-Teile prüft check.php nach dem Umschalten).
#
# Mehrfach ausführbar (aktualisiert Code, Build und Seiten; Installation, Admin und Fondspasswörter bleiben).
# Eine Stage-Domain gibt es nicht: serviceinvest.de liegt bei AWS Route 53.
set -euo pipefail

MODE="${1:-deploy}"
HOST="${SSH_HOST:-su172769@access-5019820309.webspace-host.com}"
KEY="${SSH_KEY:-$HOME/.ssh/ionos_2ip}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SSH=(ssh -i "$KEY" -o BatchMode=yes "$HOST")

if [ "$MODE" = check ]; then
	PORT=18092
	# Prozess über die PID beenden: pkill -f träfe auch die eigene SSH-Shell, deren Kommandozeile den Startbefehl enthält
	"${SSH[@]}" "cd ~/public/_sin_v1 && { PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:$PORT ~/sin-v1-check/router.php > /dev/null 2>&1 < /dev/null & PID=\$!; }; sleep 2; php ~/sin-v1-check/tools/v1/check.php http://127.0.0.1:$PORT --host=www.serviceinvest.de; RC=\$?; kill \$PID; exit \$RC" < /dev/null
	exit $?
fi
[ "$MODE" = deploy ] || { echo "Aufruf: deploy/v1/deploy.sh [check]"; exit 1; }

for p in wp-content/themes/sin-v1/functions.php wp-content/mu-plugins/sin-hardening.php wp-content/mu-plugins/site-mail.php \
	content/v1-build/index.html content/v1-build/en/index.html content/v1-build/_fehler/404-de.html content/v1-build/assets/js/site.js \
	content/v1-build/favicon.ico tools/v1/import.php tools/v1/fonds-migration.php tools/v1/copy-static.sh tools/v1/check.php \
	deploy/v1/htaccess-live deploy/v1/check-router.php; do
	[ -e "$ROOT/$p" ] || { echo "Fehlt: $p"; exit 1; }
done
# Build passt zum Paket und zu den Ergänzungen? (sonst zuerst bash tools/v1/build.sh)
for f in "$ROOT"/content/v1-vorlage/*.html "$ROOT"/content/v1-zusatz/* "$ROOT"/tools/v1/build.php; do
	[ "$f" -ot "$ROOT/content/v1-build/index.html" ] || { echo "Build veraltet ($f neuer) – zuerst bash tools/v1/build.sh"; exit 1; }
done

echo "== Hochladen nach ~/temp/sin-v1-src"
"${SSH[@]}" "rm -rf ~/temp/sin-v1-src && mkdir -p ~/temp/sin-v1-src"
tar czf - -C "$ROOT" --exclude='wp-content/themes/sin-v1/fallback-*.html' --exclude='content/v1-build/favicon-64.png' \
	wp-content/themes/sin-v1 wp-content/mu-plugins/sin-hardening.php wp-content/mu-plugins/site-mail.php \
	deploy/v1 content/v1-build tools/v1/import.php tools/v1/fonds-migration.php tools/v1/fonds-passwort.php tools/v1/copy-static.sh tools/v1/check.php \
	| "${SSH[@]}" "tar xzf - -C ~/temp/sin-v1-src"
# Fonds-Offenlegungen (nicht im Repository): ../_sin-geschuetzt/<fonds>/*.pdf ist der maßgebliche Stand
SAFE_SRC="$ROOT/../_sin-geschuetzt"
if ls "$SAFE_SRC"/debeka-immobilien-invest-i/*.pdf "$SAFE_SRC"/tas-wohnen-1/*.pdf >/dev/null 2>&1; then
	echo "== Fonds-Offenlegungen hochladen"
	(cd "$SAFE_SRC" && tar czf - debeka-immobilien-invest-i/*.pdf tas-wohnen-1/*.pdf) \
		| "${SSH[@]}" "mkdir -p ~/temp/sin-v1-src/geschuetzt && tar xzf - -C ~/temp/sin-v1-src/geschuetzt"
fi

echo "== Einrichten auf dem Server"
"${SSH[@]}" "bash ~/temp/sin-v1-src/deploy/v1/remote.sh" < /dev/null
