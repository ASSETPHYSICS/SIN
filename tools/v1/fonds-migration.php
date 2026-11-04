<?php
/**
 * Fonds-Offenlegungen aus der bisherigen Website übernehmen (HA 7) – läuft im Deploy, idempotent.
 *
 *   wp eval-file tools/v1/fonds-migration.php [<Tabellenpräfix der bisherigen Website, Standard wp_>]
 *
 * - Passwörter: Die bisherigen Fondsseiten (/debeka-immobilien-invest-i/, /tas-wohnen-1/, gleiche Datenbank, Tabellen wp_*)
 *   waren mit dem WordPress-Seitenpasswort geschützt (deutsche und englische Seite gleich). Übernommen wird es nur als Hash
 *   und nur, solange für den Fonds noch kein Passwort gesetzt ist; Anleger können ihr bisheriges Passwort weiter nutzen.
 * - Titel der Dokumente (Option sin_fonds_titles). Die Dateien bringt das Deploy aus dem lokalen Ordner ../_sin-geschuetzt/
 *   nach SIN_FONDS_DIR/<slug>/ (deploy/v1/remote.sh).
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$old = preg_replace( '/[^a-z0-9_]/i', '', $args[0] ?? 'wp_' );
if ( $old === $wpdb->prefix ) {
	WP_CLI::error( 'Bisheriges Präfix gleich dem eigenen.' );
}

$set = (array) get_option( 'sin_fonds_passwords', array() );
foreach ( sin_funds() as $f ) {
	if ( isset( $set[ $f['slug'] ] ) ) {
		WP_CLI::log( "{$f['slug']}: Passwort bereits gesetzt (unverändert)" );
		continue;
	}
	$pw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_password FROM {$old}posts WHERE post_type = 'page' AND post_name = %s AND post_status = 'publish' LIMIT 1", $f['slug'] ) );
	if ( '' === $pw ) {
		WP_CLI::warning( "{$f['slug']}: kein bisheriges Passwort gefunden – bitte unter Einstellungen → Fonds-Offenlegungen setzen" );
		continue;
	}
	sin_fonds_set_password( $f['slug'], $pw );
	WP_CLI::log( "{$f['slug']}: bisheriges Passwort übernommen (als Hash)" );
}

// Titel wie auf 2ip.de: Sprache der Fassung im Titel, Deutsch zuerst.
$de = array( 'de' => 'Nachhaltigkeitsbezogene Offenlegungen (deutsche Fassung)', 'en' => 'Sustainability-related disclosures (German version)', 'order' => 1 );
$en = array( 'de' => 'Nachhaltigkeitsbezogene Offenlegungen (englische Fassung)', 'en' => 'Sustainability-related disclosures (English version)', 'order' => 2 );
update_option( 'sin_fonds_titles', array(
	'debeka-immobilien-invest-i/Debeka-Immobilien-Invest-I_Offenlegung-Art-10-SFDR_DE.pdf' => $de,
	'debeka-immobilien-invest-i/Debeka-Immobilien-Invest-I_Offenlegung-Art-10-SFDR_EN.pdf' => $en,
	'tas-wohnen-1/2024-06-19-VOe_Internetseite-Art.-10-SFDR-TAS-Wohnen-I-c.pdf'                             => $de,
	'tas-wohnen-1/2024-04-19-website-disclosure-Art.-10-SFDR-TAS-Wohnen-I-c.pdf'                            => $en,
), false );

foreach ( sin_funds() as $f ) {
	WP_CLI::log( sprintf( '%s: %d Dokumente in %s', $f['slug'], count( sin_fonds_docs( $f['slug'], 'de' ) ), SIN_FONDS_DIR . '/' . $f['slug'] ) );
}
WP_CLI::success( 'Fonds-Offenlegungen eingerichtet.' );
