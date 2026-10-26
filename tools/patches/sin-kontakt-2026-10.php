<?php
/**
 * ServiceInvest: Kontakt-Section der Startseite (DE/EN) ohne Telefonnummer und ohne Standort Hamburg.
 * Ersetzt die Texte im Seiteninhalt (HTML liegt im Divi-Markup JSON-maskiert vor: < … >, Zeilenumbruch als \n).
 * Aufruf:  php wp-cli.phar eval-file tools/patches/sin-kontakt-2026-10.php
 * Idempotent. tools/build/build_home.py und content/i18n/en/startseite.json sind bereits angepasst.
 */
$phone = "\n" . '<address class="ip-address">' . "\n" . '<a href="tel:+494022636430">+49 (0) 40 22 63 64 30</a>' . "\n" . '</address>';
$hh    = '<p>ServiceInvest Kapitalverwaltungsgesellschaft mbH<br>Am Kaiserkai 13<br>20457 Hamburg</p></div>';
$pages = array(
	'startseite' => array(
		$phone => '',
		"\n" . '<div><p class="ip-kicker ip-kicker--light">Standort Hamburg</p>' . $hh => '',
	),
	'home'       => array(
		$phone => '',
		"\n" . '<div><p class="ip-kicker ip-kicker--light">Hamburg office</p>' . $hh => '',
	),
);
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
		foreach ( $pairs as $old => $new ) {
			$c = str_replace( array( $old, $mask( $old ) ), array( $new, $mask( $new ) ), $c );
		}
		if ( $c === $p->post_content ) { WP_CLI::log( "$slug (#{$p->ID}) unverändert" ); } else {
			wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $c ) ) );
			WP_CLI::log( "$slug (#{$p->ID}) aktualisiert" );
		}
		$c = get_post_field( 'post_content', $p->ID, 'raw' );
		if ( false !== strpos( $c, '22636430' ) || false !== strpos( $c, 'Kaiserkai' ) ) { WP_CLI::warning( "$slug (#{$p->ID}): Alttext noch vorhanden" ); }
	}
}
