#!/usr/bin/env bash
# Exportiert Inhalte aus der lokalen WordPress-Installation nach content/ (zur Versionierung).
# Aufruf im Projektstamm:  WP="php ~/wp-cli.phar" tools/export-content.sh
# Nicht exportiert: passwortgeschützte Seiten (Fondsseiten), Entwürfe, Benutzer, Optionen mit Zugangsdaten.
set -euo pipefail
cd "$(dirname "$0")/.."
WP=${WP:-wp}
mkdir -p content/pages content/theme-builder
rm -f content/pages/*.html content/theme-builder/*.html

$WP post list --post_type=page --post_status=publish --fields=ID,post_name,post_title,post_parent,menu_order,post_password --format=json 2>/dev/null |
php -r '
  $rows = json_decode(stream_get_contents(STDIN), true);
  $meta = [];
  foreach ($rows as $r) {
    if ($r["post_password"] !== "") { continue; }
    $meta[] = ["id"=>(int)$r["ID"],"slug"=>$r["post_name"],"title"=>$r["post_title"],"parent"=>(int)$r["post_parent"],"menu_order"=>(int)$r["menu_order"]];
  }
  usort($meta, fn($a,$b)=>$a["id"]<=>$b["id"]);
  file_put_contents("content/pages.json", json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
  foreach ($meta as $m) { echo $m["id"], " ", $m["slug"], "\n"; }
' | while read -r id slug; do
  $WP post get "$id" --field=post_content >"content/pages/$slug.html" 2>/dev/null
  echo "Seite: $slug"
done

for type in et_header_layout et_footer_layout; do
  for id in $($WP post list --post_type=$type --post_status=publish --field=ID 2>/dev/null); do
    $WP post get "$id" --field=post_content >"content/theme-builder/$type-$id.html" 2>/dev/null
    echo "Theme Builder: $type-$id"
  done
done

for m in menu footer; do
  $WP menu item list "$m" --fields=db_id,menu_item_parent,position,title,link,type,object --format=json 2>/dev/null >"content/menu-$m.json" || true
done
