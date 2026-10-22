#!/usr/bin/env bash
# Erzeugt aus der lokalen Datenbank einen bereinigten Dump für den Server (lokal in WSL ausführen).
#
#   bash deploy/build-dump.sh            -> ../_deploy/sin/sin-live.sql
#
# Entfernt: Benutzer, Kommentare, Rollen, Mediathek (die Seiten nutzen nur Theme-Bilder und PDFs per Pfad),
# Entwürfe/Revisionen, ungenutzte Theme-Builder-Vorlagen der Altseite und Optionen fremder Plugins (inkl. SMTP-Daten).
# Der Dump enthält die Passwörter der Fondsseiten (post_password): nie ins Repository, nach dem Deploy löschen.
set -euo pipefail
cd "$(dirname "$0")/.."
SRC_DB=${SRC_DB:-sin_neu}
TMP_DB=sin_deploy
OUT=${OUT:-../_deploy/sin/sin-live.sql}
M() { sudo mariadb "$@"; }
D() { sudo mariadb "$TMP_DB" -N -e "$1"; }

M -e "drop database if exists $TMP_DB; create database $TMP_DB character set utf8mb4"
sudo mariadb-dump --single-transaction "$SRC_DB" | M "$TMP_DB"
echo "Beiträge vorher: $(D 'select count(*) from wp_posts')"

# Theme-Builder: nur die Vorlage behalten, die der Theme Builder als Standard nutzt, samt ihren Layouts
KEEP_TPL=$(D "select ID from wp_posts p join wp_postmeta m on m.post_id=p.ID and m.meta_key='_et_default' and m.meta_value='1' where p.post_type='et_template' and p.post_status='publish'" | tr '\n' ',' | sed 's/,$//')
KEEP_LAY=$(D "select meta_value from wp_postmeta where post_id in ($KEEP_TPL) and meta_key in ('_et_header_layout_id','_et_footer_layout_id','_et_body_layout_id') and meta_value<>'0'" | tr '\n' ',' | sed 's/,$//')
echo "Theme Builder: Vorlage $KEEP_TPL, Layouts $KEEP_LAY"
D "delete from wp_posts where post_type='et_template' and ID not in ($KEEP_TPL)"
D "delete from wp_posts where post_type in ('et_header_layout','et_footer_layout','et_body_layout') and ID not in (${KEEP_LAY:-0})"
D "delete from wp_postmeta where meta_key='_et_template' and meta_value not in ($KEEP_TPL)"

D "delete from wp_posts where post_type in ('attachment','revision','auto-draft','customize_changeset','oembed_cache','_et_pb_speculation','wp_navigation','post')"
D "delete from wp_posts where post_status in ('draft','trash','auto-draft','private')"
D "delete pm from wp_postmeta pm left join wp_posts p on p.ID=pm.post_id where p.ID is null"
D "delete tr from wp_term_relationships tr left join wp_posts p on p.ID=tr.object_id where p.ID is null"

D "delete from wp_options where option_name regexp '^_(site_)?transient'"
D "delete from wp_options where option_name regexp '^(__fluentform|_cdp_|_cf7sg|_fluent|_irb_|_tifm|_et_builder_da_feature_cache|action_scheduler|activate_plugins|AHB_|analyst_|auto_core_update_notified|autodescription|bodhi_|bsr_|career_|category_[0-9]|cfdb7|cookie_notice|cp_cpappb|DRA_|enable_full_version|et_account_status|et_automatic_updates_options|et_support_site_id|flag_cache|fluent|frmpro|gpdr|groups_children|hack_file|https_detection_errors|installed_appointment-hour|jb_setup|layerslider|license_key_token|local_google_fonts|mailjet|media-category|mj_|mk_fm|moove|portfolio_|recently_|recovery_keys|new_admin_email|adminhash|cron$|fresh_site|sbg_|schema-ActionScheduler|secret_key|shop_|sm_|staff_|supercache|the_seo_framework|theme_mods_(bosa|cesis|corpo)|trial_period|uninstall_plugins|vc_version|wf_plugin|wp_ssa_|wp-dark-mode|wpmudev|wsp_|wp_user_roles$)'"
D "delete from wp_options where option_name regexp '^widget_(aboutmewidget|adsensewidget|advwidget|bookingwidget|custom_post_widget|fluentform_widget|forminator_widget|frm_show_form|koko|layerslider|rev-slider|wp_mailjet|text$)'"
D "update wp_options set option_value='0' where option_name in ('site_icon','users_can_register')"
D "truncate wp_users"; D "truncate wp_usermeta"; D "truncate wp_comments"; D "truncate wp_commentmeta"

echo "Beiträge nachher: $(D 'select count(*) from wp_posts')"
D "select post_type,post_status,count(*) from wp_posts group by 1,2" | tr '\n' ';'; echo
echo "Passwortgeschützte Seiten: $(D "select count(*) from wp_posts where post_password<>''")"
echo "Reste der Altseite (2ig/INRADR/connos/cunio) in Inhalten: $(D "select count(*) from wp_posts where post_content regexp 'neu\\\\.2ig\\\\.de|inradr|connos|cunio'")"

mkdir -p "$(dirname "$OUT")"
sudo mariadb-dump --single-transaction --skip-comments --no-tablespaces --default-character-set=utf8mb4 "$TMP_DB" > "$OUT"
chmod 600 "$OUT"
M -e "drop database $TMP_DB"
echo "Dump: $OUT ($(du -k "$OUT" | cut -f1) KB), Treffer localhost:8081: $(grep -c 'localhost:8081' "$OUT" || true)"
