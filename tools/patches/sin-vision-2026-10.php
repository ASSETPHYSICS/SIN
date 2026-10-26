<?php
/**
 * ServiceInvest: Startseite (DE/EN) – Section „Unsere Vision“ neu (Freiräume schaffen), „darüber hinaus“ in der Box
 * Partner-Fonds entfernt, in „Ihr Nutzen“ erste und letzte Box getauscht.
 * Alte und neue Abschnitte je Seite stehen in sin-vision-2026-10.json (aus dem Übersetzungsspeicher erzeugt).
 * Aufruf:  php wp-cli.phar eval-file tools/patches/sin-vision-2026-10.php tools/patches/sin-vision-2026-10.php
 *          (Pfad zum Skript als Argument: eval-file setzt __DIR__ nicht auf den Ordner des Skripts)
 * Idempotent. build_home.py und content/i18n/en/startseite.json sind bereits angepasst.
 */
$json  = dirname( realpath( $args[0] ?? '' ) ?: __FILE__ ) . '/sin-vision-2026-10.json';
$pages = is_readable( $json ) ? json_decode( file_get_contents( $json ), true ) : null;
if ( ! $pages ) { WP_CLI::error( "$json nicht gefunden (Pfad zum Skript als Argument übergeben)." ); }
$mask = static function ( $s ) {
	$b = chr( 92 );   // Backslash per chr(): eval-file würde "<" im Quelltext bereits auflösen
	return str_replace( array( "\n", '<', '>', '"', '&', '--' ),
		array( $b . 'n', $b . 'u003c', $b . 'u003e', $b . 'u0022', $b . 'u0026', $b . 'u002d' . $b . 'u002d' ), $s );
};

foreach ( $pages as $slug => $pairs ) {
	$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1, 'lang' => '', 'suppress_filters' => true ) );
	if ( ! $found ) { WP_CLI::warning( "Seite $slug nicht gefunden" ); continue; }
	foreach ( $found as $p ) {
		$c = $p->post_content;
		foreach ( $pairs as $i => $pair ) {
			list( $old, $new ) = array_map( $mask, $pair );
			if ( false !== strpos( $c, $new ) ) { WP_CLI::log( "$slug (#{$p->ID}) Abschnitt $i bereits aktuell" ); continue; }
			if ( false === strpos( $c, $old ) ) { WP_CLI::warning( "$slug (#{$p->ID}) Abschnitt $i: alter Text nicht gefunden – übersprungen" ); continue; }
			$c = str_replace( $old, $new, $c );
			WP_CLI::log( "$slug (#{$p->ID}) Abschnitt $i ersetzt" );
		}
		if ( $c !== $p->post_content ) { wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $c ) ) ); }
	}
}
