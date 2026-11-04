#!/usr/bin/env bash
# Kopiert die Dateien des Builds, die keine Seiten sind, ins WordPress-Stammverzeichnis (/assets/…, /favicon.ico,
# /apple-touch-icon.png, /sitemap.xml, /robots.txt). Seiten und Fehlerseiten übernimmt tools/v1/import.php.
#   bash tools/v1/copy-static.sh <Build-Ordner> <WordPress-Stammverzeichnis>
set -euo pipefail
SRC="${1:?Build-Ordner fehlt}"; DST="${2:?Zielordner fehlt}"
FILES=(favicon.ico apple-touch-icon.png sitemap.xml robots.txt)
for f in "${FILES[@]}" assets/js/site.js assets/og/serviceinvest.jpg; do
	[ -f "$SRC/$f" ] || { echo "Fehlt im Build: $f"; exit 1; }
done
rm -rf "$DST/assets"
cp -a "$SRC/assets" "$DST/assets"
for f in "${FILES[@]}"; do cp -a "$SRC/$f" "$DST/$f"; done
