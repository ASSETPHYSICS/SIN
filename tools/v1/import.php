<?php
/**
 * Übernimmt den Build (content/v1-build/, aus tools/v1/build.php) in WordPress – idempotent.
 *
 *   php ~/wp-cli.phar eval-file tools/v1/import.php [<Build-Ordner>]
 *
 * - Je Seite eine WordPress-Seite mit der Adresse nach HA 5.1: Deutsch auf oberster Ebene, Englisch als Unterseiten der
 *   Seite "en".
 * - Body unverändert als Beitragsinhalt (direkt per $wpdb, damit kses nichts entfernt), Head unverändert als Post-Meta.
 * - Alle übrigen veröffentlichten Seiten werden auf Entwurf gesetzt (nicht gelöscht).
 * - Fehlerseiten des Themes (fallback-de|en.html) aus _fehler/404-de|en.html.
 * Die übrigen Dateien (assets/, Favicons, sitemap.xml …) kopiert das Deploy bzw. local-setup.sh ins Stammverzeichnis.
 */

defined( 'ABSPATH' ) || exit;

$src   = $args[0] ?? dirname( __DIR__, 2 ) . '/content/v1-build';
$theme = get_theme_root() . '/sin-v1';
if ( ! is_file( "$src/index.html" ) || ! is_dir( $theme ) ) {
	WP_CLI::error( "Build ($src) oder Theme ($theme) fehlt." );
}

// Datei => array( Slug, Elternseite (Datei) ). Reihenfolge: Eltern vor Kindern.
$pages = array(
	'index.html'                  => array( 'startseite', '' ),
	'nachhaltigkeit.html'         => array( 'nachhaltigkeit', '' ),
	'anlegerzugang.html'          => array( 'anlegerzugang', '' ),
	'mieter.html'                 => array( 'mieter', '' ),
	'kontakt.html'                => array( 'kontakt', '' ),
	'impressum.html'              => array( 'impressum', '' ),
	'datenschutz.html'            => array( 'datenschutz', '' ),
	'datenschutz-mieter.html'     => array( 'datenschutz-mieter', '' ),
	'pai-statement.html'          => array( 'pai-statement', '' ),
	'stewardship-policy.html'     => array( 'stewardship-policy', '' ),
	'verguetungspolitik.html'     => array( 'verguetungspolitik', '' ),
	'beschwerdestelle.html'       => array( 'beschwerdestelle', '' ),
	'en/index.html'               => array( 'en', '' ),
	'en/sustainability.html'      => array( 'sustainability', 'en/index.html' ),
	'en/investor-access.html'     => array( 'investor-access', 'en/index.html' ),
	'en/tenants.html'             => array( 'tenants', 'en/index.html' ),
	'en/contact.html'             => array( 'contact', 'en/index.html' ),
	'en/legal-notice.html'        => array( 'legal-notice', 'en/index.html' ),
	'en/privacy.html'             => array( 'privacy', 'en/index.html' ),
	'en/privacy-tenants.html'     => array( 'privacy-tenants', 'en/index.html' ),
	'en/pai-statement.html'       => array( 'pai-statement', 'en/index.html' ),
	'en/stewardship-policy.html'  => array( 'stewardship-policy', 'en/index.html' ),
	'en/remuneration-policy.html' => array( 'remuneration-policy', 'en/index.html' ),
	'en/complaints.html'          => array( 'complaints', 'en/index.html' ),
);
$found = array_map( fn( $f ) => substr( $f, strlen( $src ) + 1 ), array_merge( glob( "$src/*.html" ), glob( "$src/en/*.html" ) ) );
sort( $found );
$expected = array_keys( $pages );
sort( $expected );
if ( $found !== $expected ) {
	WP_CLI::error( "Seitenliste und Build passen nicht zusammen:\n  Build: " . implode( ', ', array_diff( $found, $expected ) ) . "\n  Liste: " . implode( ', ', array_diff( $expected, $found ) ) );
}

global $wpdb;
$ids   = array();
$order = 0;

foreach ( $pages as $file => list( $slug, $parent_file ) ) {
	$html = file_get_contents( "$src/$file" );
	if ( ! preg_match( '#^<!doctype html>\n<html lang="([a-z]{2})">\n<head>\n(.*?)\n</head>\n<body>\n(.*?)\n</body>\n</html>\n$#s', (string) $html, $m ) ) {
		WP_CLI::error( "Aufbau <html lang><head><body> nicht erkannt: $file" );
	}
	list( , $lang, $head, $body ) = $m;
	preg_match( '#<title>(.*?)</title>#s', $head, $t );
	$title = html_entity_decode( preg_replace( '# – ServiceInvest.*$#', '', $t[1] ?? $slug ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$parent = '' !== $parent_file ? $ids[ $parent_file ] : 0;
	$id     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name = %s AND post_parent = %d AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1", $slug, $parent ) );
	if ( ! $id ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => $parent ), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id );
		}
		WP_CLI::log( "angelegt: $slug ($id)" );
	}
	$ids[ $file ] = $id;

	$old  = get_post( $id );
	$data = array(
		'post_content'   => $body,
		'post_title'     => $title,
		'post_status'    => 'publish',
		'post_password'  => '',
		'post_parent'    => $parent,
		'menu_order'     => $order++,
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
	);
	if ( $old->post_content !== $body || get_post_meta( $id, '_v1_head', true ) !== $head ) {
		$data += array( 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) );
	}
	$wpdb->update( $wpdb->posts, $data, array( 'ID' => $id ) );
	clean_post_cache( $id );
	update_post_meta( $id, '_v1_file', $file );
	update_post_meta( $id, '_v1_lang', $lang );
	update_post_meta( $id, '_v1_head', wp_slash( $head ) );
	delete_post_meta( $id, '_wp_page_template' );
	if ( get_post_meta( $id, '_v1_head', true ) !== $head || get_post_field( 'post_content', $id, 'raw' ) !== $body ) {
		WP_CLI::error( "Gespeicherter Inhalt weicht vom Build ab: $file" );
	}
	WP_CLI::log( sprintf( '%-30s -> %-28s #%d', $file, wp_make_link_relative( get_permalink( $id ) ), $id ) );
}

// Übrige veröffentlichte Seiten auf Entwurf (nicht löschen).
$others = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND ID NOT IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' );
foreach ( $others as $oid ) {
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $oid ) );
	clean_post_cache( (int) $oid );
	WP_CLI::log( 'Entwurf: ' . get_post_field( 'post_name', $oid ) . " #$oid" );
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['index.html'] );
update_option( 'page_for_posts', 0 );
update_option( 'blogname', 'ServiceInvest' );
update_option( 'blogdescription', 'Kapitalverwaltungsgesellschaft' );

foreach ( array( 'de', 'en' ) as $lang ) {
	if ( ! copy( "$src/_fehler/404-$lang.html", "$theme/fallback-$lang.html" ) ) {
		WP_CLI::error( "Fehlerseite $lang nicht kopiert." );
	}
}

WP_CLI::success( count( $ids ) . ' Seiten übernommen, ' . count( $others ) . ' auf Entwurf gesetzt, Fehlerseiten de/en.' );
