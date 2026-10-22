#!/usr/bin/env bash
# Spielt nur Code-Änderungen (Theme, Plugin, MU-Plugin) ein; die Datenbank bleibt unberührt.
#
#   cd <WordPress-Stammverzeichnis>
#   SRC_DIR=$HOME/sin-code bash $SRC_DIR/deploy/update-code.sh
#
# SRC_DIR = entpacktes Code-Paket ("git archive"). Schriften im Theme-Ordner bleiben erhalten.
set -euo pipefail

PHP=${PHP:-php}
SRC_DIR=${SRC_DIR:?SRC_DIR fehlt (entpacktes Code-Paket)}
[ -f wp-config.php ] || { echo "Bitte im WordPress-Stammverzeichnis ausführen."; exit 1; }
[ -d "$SRC_DIR/wp-content/themes/Divi-SIN" ] || { echo "SRC_DIR ungültig: $SRC_DIR"; exit 1; }

copy_dir() { # Quelle Ziel [rsync-Optionen]
  if command -v rsync >/dev/null; then rsync -a --delete "${@:3}" "$1/" "$2/"; else cp -a "$1/." "$2/"; fi
}
copy_dir "$SRC_DIR/wp-content/themes/Divi-SIN" wp-content/themes/Divi-SIN --exclude=fonts/
copy_dir "$SRC_DIR/wp-content/plugins/appointment-booking" wp-content/plugins/appointment-booking
cp -a "$SRC_DIR/wp-content/mu-plugins/." wp-content/mu-plugins/

for d in wp-content/themes/Divi-SIN wp-content/plugins/appointment-booking wp-content/mu-plugins; do
  find "$d" -type d -print0 | xargs -0 -n 50 chmod 755
  find "$d" -type f -print0 | xargs -0 -n 50 chmod 644
done
rm -rf wp-content/et-cache
$PHP -d memory_limit=512M wp-cli.phar cache flush >/dev/null 2>&1 || true
echo "Code aktualisiert: $(date '+%F %T')"
