<?php
/**
 * Startseite: Klasse "ip-lead" am Einleitungsabsatz des Headers wiederherstellen.
 * Hintergrund: Wird der Text im Divi-Editor bearbeitet, speichert Divi den Absatz oft ohne Klasse (bzw. mit
 * eigenen Attributen); dadurch fehlen Schriftgröße und Spaltenbreite.
 *
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/startseite-lead-klasse.php
 * Idempotent; ändert nichts, wenn die Klasse schon gesetzt ist oder die Struktur unerwartet ist.
 *
 * Divi speichert JSON-maskiert (" für ", < für <, > für >); die Zeichenfolgen werden aus einem
 * Backslash zusammengesetzt.
 */
$page = get_page_by_path( 'startseite' );
if ( ! $page ) { WP_CLI::error( 'Seite "startseite" nicht gefunden.' ); }

$bs      = chr( 92 );
$lt      = $bs . 'u003c';
$gt      = $bs . 'u003e';
$quote   = $bs . 'u0022';
$content = $page->post_content;

$needle = 'Institutionelle Anleger stehen vor';
$k      = strpos( $content, $needle );
if ( false === $k ) { WP_CLI::error( 'Einleitungstext nicht gefunden.' ); }

$before = substr( $content, 0, $k );
// Öffnendes <p ...> unmittelbar vor dem Text (maskiert oder unmaskiert).
$pattern = '/(' . preg_quote( $lt, '/' ) . 'p(?:(?!' . preg_quote( $gt, '/' ) . ')[^<])*' . preg_quote( $gt, '/' ) . '|<p[^>]*>)\s*$/s';
if ( ! preg_match( $pattern, $before, $m, PREG_OFFSET_CAPTURE ) ) {
	WP_CLI::error( 'Vor dem Text steht kein <p>-Tag. Bitte Markup prüfen: ...' . substr( $before, -300 ) );
}
$tag = $m[1][0];
if ( false !== strpos( $tag, 'ip-lead' ) ) {
	WP_CLI::success( 'Klasse ip-lead ist bereits gesetzt, nichts zu tun.' );
	return;
}
WP_CLI::log( 'Altes Tag: ' . $tag );

$escaped = ( 0 === strpos( $tag, $lt ) );
$new_tag = $escaped
	? $lt . 'p class=' . $quote . 'ip-lead' . $quote . $gt
	: '<p class="ip-lead">';
$content = substr( $content, 0, $m[1][1] ) . $new_tag . substr( $content, $m[1][1] + strlen( $tag ) );

wp_update_post( array( 'ID' => $page->ID, 'post_content' => wp_slash( $content ) ) );
WP_CLI::success( 'Klasse ip-lead wiederhergestellt.' );
