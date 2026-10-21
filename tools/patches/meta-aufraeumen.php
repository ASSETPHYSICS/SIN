<?php
/**
 * Metadaten aufräumen (Altlasten der INRADR-Seite) und für 2IP setzen.
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/meta-aufraeumen.php
 * Idempotent.
 *
 * - Divi-SEO der Startseite: Titel und Beschreibung für 2IP statt INRADR; Meta-Keywords aus
 * - Divi-Integration "Body": altes Modal-Skript der INRADR-Seite entfernen (erzeugte auf jeder Seite einen JS-Fehler)
 * - Untertitel der Website (blogdescription)
 * - Papierkorb leeren, leere/verwaiste Metadaten löschen
 */
global $wpdb;

$home_title       = 'Institutional Investment Partners – Investoren-Master-KVG für Real Assets';
$home_description = 'Investoren-Master-KVG und AIFM für Real Assets: klare Strukturen, verlässliche Prozesse und transparente Informationen für institutionelle Anleger.';
$tagline          = 'Investoren-Master-KVG · AIFM · Investment Consulting';

// --- Divi-Optionen
$o = get_option( 'et_divi' );
if ( ! is_array( $o ) ) { WP_CLI::error( 'Option et_divi nicht gefunden.' ); }
$before = $o;
$o['divi_seo_home_title']           = 'on';
$o['divi_seo_home_titletext']       = $home_title;
$o['divi_seo_home_description']     = 'on';
$o['divi_seo_home_descriptiontext'] = $home_description;
$o['divi_seo_home_keywords']        = 'false';
$o['divi_seo_home_keywordstext']    = '';
foreach ( array( 'head', 'body', 'single_top', 'single_bottom' ) as $slot ) {
	if ( ! empty( $o[ 'divi_integration_' . $slot ] ) ) {
		WP_CLI::log( 'Divi-Integration "' . $slot . '" geleert (' . strlen( $o[ 'divi_integration_' . $slot ] ) . ' Zeichen).' );
	}
	$o[ 'divi_integration_' . $slot ] = '';
}
foreach ( array( 'header', 'body', 'singletop', 'singlebottom' ) as $slot ) {
	$o[ 'divi_integrate_' . $slot . '_enable' ] = 'false';
}
if ( $o !== $before ) {
	update_option( 'et_divi', $o );
	WP_CLI::log( 'Divi-SEO der Startseite gesetzt: "' . $home_title . '"' );
}

if ( get_option( 'blogdescription' ) !== $tagline ) {
	update_option( 'blogdescription', $tagline );
	WP_CLI::log( 'Untertitel gesetzt.' );
}

// --- Papierkorb leeren (Altseiten der INRADR-Installation)
$trashed = get_posts( array( 'post_type' => 'any', 'post_status' => 'trash', 'numberposts' => -1, 'fields' => 'ids' ) );
foreach ( $trashed as $id ) {
	wp_delete_post( $id, true );
}
if ( $trashed ) { WP_CLI::log( 'Papierkorb geleert: ' . count( $trashed ) . ' Einträge.' ); }

// --- Leere bzw. verwaiste Metadaten
$n  = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_thumbnail_id','footnotes','inline_featured_image') AND (meta_value = '' OR meta_value IS NULL)" );
$n += $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wp_trash_meta_status','_wp_trash_meta_time','_wp_desired_post_slug','_edit_lock')" );
$n += $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
$n += $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} a ON a.ID = pm.meta_value WHERE pm.meta_key = '_thumbnail_id' AND a.ID IS NULL" );
if ( $n ) { WP_CLI::log( 'Metadaten gelöscht: ' . $n . ' Einträge.' ); }

wp_cache_flush();
WP_CLI::success( 'Metadaten aufgeräumt.' );
