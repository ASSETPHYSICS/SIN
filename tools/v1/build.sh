#!/usr/bin/env bash
# Paket -> content/v1-build/ (lokal in WSL; das Ergebnis wird versioniert und deployt). Mehrfach ausführbar.
#   bash tools/v1/build.sh
# Braucht: PHP ≥ 8.1, Node mit sharp (~/sin-imgtools: npm install sharp@0.34.4), Python 3 mit Pillow (Favicon).
set -euo pipefail
cd "$(dirname "$0")/../.."
NODE_PATH="${NODE_PATH:-$HOME/sin-imgtools/node_modules}" node tools/v1/images.mjs content/v1-vorlage content/v1-build
if [ ! -f content/v1-build/favicon.ico ] || [ content/v1-build/favicon-64.png -nt content/v1-build/favicon.ico ]; then
	python3 -I - content/v1-build/favicon-64.png content/v1-build/favicon.ico <<'PY'
import sys
from PIL import Image
Image.open(sys.argv[1]).save(sys.argv[2], sizes=[(16, 16), (32, 32), (48, 48)])
PY
fi
php tools/v1/build.php
