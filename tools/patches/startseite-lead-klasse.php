<?php
/**
 * Startseite: Einleitungsabsatz des Headers wiederherstellen.
 * Hintergrund: Wird der Text im Divi-Editor bearbeitet, speichert Divi den Absatz anders:
 *   a) <p>Text</p> ohne Klasse                       -> Klasse "ip-lead" ergänzen
 *   b) <p class="ip-lead"></p><div>Text</div><br><p></p> -> <p class="ip-lead">Text</p>
 * Dadurch fehlen sonst Schriftgröße und Spaltenbreite.
 *
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/startseite-lead-klasse.php
 * Idempotent; ändert nichts, wenn die Struktur schon stimmt oder unerwartet ist.
 *
 * Divi speichert JSON-maskiert (" für ", < für <, > für >, \n für Zeilenumbruch); die Zeichenfolgen
 * werden aus einem Backslash zusammengesetzt.
 */
$page = get_page_by_path( 'startseite' );
if ( ! $page ) { WP_CLI::error( 'Seite "startseite" nicht gefunden.' ); }

$bs      = chr( 92 );
$lt      = $bs . 'u003c';
$gt      = $bs . 'u003e';
$quote   = $bs . 'u0022';
$nl      = $bs . 'n';
$content = $page->post_content;
$q       = static function ( $s ) { return preg_quote( $s, '/' ); };

if ( false === strpos( $content, 'Institutionelle Anleger stehen vor' ) ) { WP_CLI::error( 'Einleitungstext nicht gefunden.' ); }

$lead_open = $lt . 'p class=' . $quote . 'ip-lead' . $quote . $gt;
$p_close   = $lt . '/p' . $gt;

// b) Text steht in einem <div> hinter einem leeren Absatz mit Klasse.
$pattern_b = '/' . $q( $lead_open . $p_close . $nl . $lt . 'div' . $gt ) . '(.*?)' . $q( $lt . '/div' . $gt )
	. '(?:' . $q( $nl . $lt . 'br /' . $gt ) . ')?'
	. '(?:' . $q( $nl . $lt . 'p' . $gt . $lt . '/p' . $gt ) . ')?/s';

$changed = false;
$result  = preg_replace_callback( $pattern_b, static function ( $m ) use ( $lead_open, $p_close, $lt ) {
	if ( false !== strpos( $m[1], $lt ) ) { return $m[0]; }   // enthält Markup: nicht anfassen
	return $lead_open . $m[1] . $p_close;
}, $content, 1, $count );
if ( $count && $result !== $content ) {
	$content = $result;
	$changed = true;
	WP_CLI::log( 'Fall b: Text zurück in <p class="ip-lead"> gesetzt.' );
}

// a) <p ...> ohne Klasse unmittelbar vor dem Text.
if ( ! $changed ) {
	$k      = strpos( $content, 'Institutionelle Anleger stehen vor' );
	$before = substr( $content, 0, $k );
	$pat_a  = '/(' . $q( $lt ) . 'p(?:(?!' . $q( $gt ) . ')[^<])*' . $q( $gt ) . '|<p[^>]*>)\s*$/s';
	if ( ! preg_match( $pat_a, $before, $m, PREG_OFFSET_CAPTURE ) ) {
		WP_CLI::error( 'Unerwartete Struktur vor dem Text. Bitte Markup prüfen: ...' . substr( $before, -300 ) );
	}
	$tag = $m[1][0];
	if ( false !== strpos( $tag, 'ip-lead' ) ) {
		WP_CLI::success( 'Klasse ip-lead ist bereits gesetzt, nichts zu tun.' );
		return;
	}
	WP_CLI::log( 'Fall a, altes Tag: ' . $tag );
	$new_tag = ( 0 === strpos( $tag, $lt ) ) ? $lead_open : '<p class="ip-lead">';
	$content = substr( $content, 0, $m[1][1] ) . $new_tag . substr( $content, $m[1][1] + strlen( $tag ) );
	$changed = true;
}

wp_update_post( array( 'ID' => $page->ID, 'post_content' => wp_slash( $content ) ) );
WP_CLI::success( 'Einleitungsabsatz wiederhergestellt.' );
