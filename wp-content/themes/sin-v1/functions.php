<?php
/**
 * ServiceInvest v1 – Theme für das Paket serviceinvest-website (Handlungsanweisung Live-Schaltung serviceinvest.de v1.0,
 * im Folgenden „HA“).
 *
 * tools/v1/build.php macht aus dem Paket statisches HTML ohne support.js (content/v1-build/); tools/v1/import.php speichert
 * je Seite den <body> unverändert als Beitragsinhalt und den <head> als Post-Meta. Das Theme gibt beides so aus: Titel,
 * Beschreibung, canonical, hreflang, Open Graph, CSS und site.js kommen aus dem Build. Die übrigen Dateien (assets/,
 * Favicons, sitemap.xml, robots.txt, dokumente/) liegen im Stammverzeichnis. wp_head()/wp_footer() werden bewusst nicht
 * aufgerufen: Die Seiten brauchen nichts aus WordPress, und so kommt nichts hinzu (keine Cookies, keine Fremdquellen, HA 12).
 *
 * Beim Ausgeben ändert sich nur:
 *   - Formulare Kontakt und Mieter: Endpunkt, Honeypot, Zeitstempel, Sprache (inc/formulare.php; HA 6)
 *   - Anlegerzugang: Endpunkt der Passwortprüfung (inc/fonds.php; HA 7)
 *
 * Post-Meta je Seite:
 *   _v1_file   Paketdatei (index.html, kontakt.html, en/contact.html …)
 *   _v1_lang   Sprache (de, en)
 *   _v1_head   Inhalt des <head>
 */

defined( 'ABSPATH' ) || exit;

const SIN_LIVE_URL = 'https://www.serviceinvest.de';

require_once __DIR__ . '/inc/headers.php';
require_once __DIR__ . '/inc/redirects.php';
require_once __DIR__ . '/inc/formulare.php';
require_once __DIR__ . '/inc/fonds.php';

add_action( 'after_setup_theme', function () {
	add_filter( 'show_admin_bar', '__return_false' );
	add_filter( 'wp_speculation_rules_configuration', '__return_null' );
} );

/** Post-Meta der angezeigten Seite ('' außerhalb von Seiten des Pakets). */
function sin_page_meta( string $key ): string {
	$id = is_singular( 'page' ) ? get_queried_object_id() : 0;
	return $id ? (string) get_post_meta( $id, $key, true ) : '';
}

/** Sprache der Anfrage: aus der Seite, sonst aus dem Pfad (/en/…), sonst de. */
function sin_lang(): string {
	$lang = sin_page_meta( '_v1_lang' );
	if ( '' !== $lang ) {
		return $lang;
	}
	return preg_match( '#^en(/|$)#', sin_request_path() ) ? 'en' : 'de';
}

/** Angefragter Pfad relativ zur Installation, ohne führende und abschließende Schrägstriche. */
function sin_request_path(): string {
	$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
	$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $base && '/' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) - 1 );
	}
	return trim( rawurldecode( $path ), '/' );
}

/** Body für die Ausgabe (siehe Kopfkommentar). */
function sin_render_body( string $html ): string {
	return sin_fonds_prepare( sin_formulare_prepare( $html ) );
}
