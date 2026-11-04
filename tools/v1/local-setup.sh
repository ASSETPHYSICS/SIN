#!/usr/bin/env bash
# Lokale Einrichtung von SIN_V1 (WSL): Build-Dateien ins Stammverzeichnis, Theme, Import. Mehrfach ausführbar.
#   bash tools/v1/build.sh && bash tools/v1/local-setup.sh
#   Server starten: PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8092 router.php   ->  http://localhost:8092
# Dokumente lokal (nicht im Repository): ../_sin-dokumente/ (PAI-PDFs, wird nach dokumente/ kopiert) und
# ../_sin-geschuetzt/<fonds>/ (SIN_FONDS_DIR).
set -euo pipefail
cd "$(dirname "$0")/../.."
WP() { php -d memory_limit=512M ~/wp-cli.phar "$@"; }

bash tools/v1/copy-static.sh content/v1-build .
# Lokal: keine Indexierung
printf 'User-agent: *\nDisallow: /\n' > robots.txt
mkdir -p dokumente
[ -d ../_sin-dokumente ] && cp -a ../_sin-dokumente/. dokumente/

WP config set SITE_MAIL_FROM_NAME 'ServiceInvest' --quiet
WP config set SIN_KONTAKT_TO 'info@serviceinvest.de' --quiet
WP config set SIN_MIETER_TO 'info@serviceinvest.de' --quiet
WP config set SIN_FONDS_DIR "$(cd .. && pwd)/_sin-geschuetzt" --quiet
WP config set SIN_FONDS_LOG "$(cd .. && pwd)/_sin-geschuetzt/sin-fonds.log" --quiet
WP theme activate sin-v1
WP rewrite structure '/%postname%/' --quiet
WP option update blog_public 0 --quiet
WP eval-file tools/v1/import.php
WP rewrite flush --quiet
