<?php
/**
 * ServiceInvest: Section „Unsere Leistungen“ der Startseite (DE/EN) – neue Überschrift (Partner-Fonds und Insourcing …),
 * Partner-Fonds an erster Stelle, ohne Risikomanagement und Hedging Services, vier Boxen in einer Reihe (ip-values--4),
 * Hinweis „Weitere Informationen: fund-reporting.com“ ausgeblendet.
 * Alter und neuer Abschnitt je Seite stehen in sin-leistungen-2026-10.json (aus dem Übersetzungsspeicher erzeugt).
 * Aufruf:  php wp-cli.phar eval-file tools/patches/sin-leistungen-2026-10.php
 * Idempotent. build_home.py und content/i18n/en/startseite.json sind bereits angepasst.
 */
$pairs = json_decode( file_get_contents( __DIR__ . '/sin-leistungen-2026-10.json' ), true );
if ( ! $pairs ) {
	// eval-file setzt __DIR__ nicht immer auf den Ordner des Skripts
	$pairs = json_decode( file_get_contents( dirname( realpath( $args[0] ?? 'tools/patches/x' ) ) . '/sin-leistungen-2026-10.json' ), true );
}
if ( ! $pairs ) { WP_CLI::error( 'sin-leistungen-2026-10.json nicht gefunden (Pfad zum Skript als Argument übergeben).' ); }
$mask = static function ( $s ) {
	$b = chr( 92 );   // Backslash per chr(): eval-file würde "<" im Quelltext bereits auflösen
	return str_replace( array( "\n", '<', '>', '"', '&', '--' ),
		array( $b . 'n', $b . 'u003c', $b . 'u003e', $b . 'u0022', $b . 'u0026', $b . 'u002d' . $b . 'u002d' ), $s );
};

foreach ( $pairs as $slug => $pair ) {
	list( $old, $new ) = $pair;
	$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1, 'lang' => '', 'suppress_filters' => true ) );
	if ( ! $found ) { WP_CLI::warning( "Seite $slug nicht gefunden" ); continue; }
	foreach ( $found as $p ) {
		$c = $p->post_content;
		if ( false !== strpos( $c, $mask( $new ) ) ) { WP_CLI::log( "$slug (#{$p->ID}) bereits aktuell" ); continue; }
		if ( false === strpos( $c, $mask( $old ) ) ) { WP_CLI::warning( "$slug (#{$p->ID}): alter Abschnitt nicht gefunden – nichts geändert" ); continue; }
		wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( str_replace( $mask( $old ), $mask( $new ), $c ) ) ) );
		WP_CLI::log( "$slug (#{$p->ID}) aktualisiert" );
	}
}
