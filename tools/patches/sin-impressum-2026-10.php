<?php
/**
 * ServiceInvest: Impressum (DE/EN) ohne Telefonnummer; Bildnachweis Startseite = KI-Video (Gemini).
 * Ersetzt die Texte im Seiteninhalt (HTML liegt im Divi-Markup JSON-maskiert vor: < … >).
 * Aufruf:  php wp-cli.phar eval-file tools/patches/sin-impressum-2026-10.php
 * Idempotent. Der Übersetzungsspeicher content/i18n/en/serviceinvest-impressum.json ist bereits angepasst.
 */
$pages = array(
	'serviceinvest-impressum' => array(
		'<br>Tel.: +49 (0) 69 484 485 518</p>'                   => '</p>',
		'<br>Startseite unten: Rainer Sturm / pixelio.de<br>'   => '<br>Startseite: Video KI-generiert mit Gemini<br>',
	),
	'imprint'                 => array(
		'<br>Tel.: +49 (0) 69 484 485 518</p>'                   => '</p>',
		'<br>/home-en bottom: Rainer Sturm / pixelio.de<br>'    => '<br>Home page: video AI-generated with Gemini<br>',
	),
);
$mask = static function ( $s ) {
	$u = chr( 92 ) . 'u00';   // Backslash per chr(): eval-file würde "<" im Quelltext bereits auflösen
	return str_replace( array( '<', '>', '"', '&' ), array( $u . '3c', $u . '3e', $u . '22', $u . '26' ), $s );
};

foreach ( $pages as $slug => $pairs ) {
	$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1, 'lang' => '', 'suppress_filters' => true ) );
	if ( ! $found ) { WP_CLI::warning( "Seite $slug nicht gefunden" ); continue; }
	foreach ( $found as $p ) {
		$c = $p->post_content;
		foreach ( $pairs as $old => $new ) {
			$c = str_replace( array( $old, $mask( $old ) ), array( $new, $mask( $new ) ), $c );
		}
		if ( $c === $p->post_content ) { WP_CLI::log( "$slug (#{$p->ID}) unverändert" ); continue; }
		wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $c ) ) );
		WP_CLI::log( "$slug (#{$p->ID}) aktualisiert" );
	}
	foreach ( $found as $p ) {
		$c = get_post_field( 'post_content', $p->ID, 'raw' );
		if ( false !== strpos( $c, '484 485' ) || false !== strpos( $c, 'pixelio' ) ) { WP_CLI::warning( "$slug (#{$p->ID}): Alttext noch vorhanden" ); }
	}
}
