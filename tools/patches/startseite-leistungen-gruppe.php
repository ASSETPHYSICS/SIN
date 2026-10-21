<?php
/**
 * Startseite: Texte der Sections "Leistungen" und "Unser Ansatz" ändern, Section "Die Gruppe" neu setzen (ohne Boxen),
 * ausgeblendete Section "Standorte" entfernen, Reihenfolge "Unser Ansatz" vor "Die Gruppe".
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/startseite-leistungen-gruppe.php
 * Idempotent: bereits angewendete Änderungen werden übersprungen.
 *
 * Divi speichert JSON-maskiert (" für ", < für <, > für >, \n für Zeilenumbruch); diese Zeichenfolgen
 * werden aus einem Backslash zusammengesetzt.
 */
$page = get_page_by_path( 'startseite' );
if ( ! $page ) { WP_CLI::error( 'Seite "startseite" nicht gefunden.' ); }

$bs      = chr( 92 );
$lt      = $bs . 'u003c';
$gt      = $bs . 'u003e';
$quote   = $bs . 'u0022';
$content = $page->post_content;

/** Wandelt HTML in die in der Datenbank übliche JSON-maskierte Form (ohne umschließende Anführungszeichen). */
$esc = static function ( $html ) use ( $bs ) {
	$s = json_encode( $html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$s = substr( $s, 1, -1 );
	$s = str_replace( array( $bs . '"', '<', '>', '&', '--' ), array( $bs . 'u0022', $bs . 'u003c', $bs . 'u003e', $bs . 'u0026', $bs . 'u002d' . $bs . 'u002d' ), $s );
	return $s;
};

// --- Leistungen
$replacements = array(
	$lt . 'h3' . $gt . 'Master-KVG' . $lt . '/h3' . $gt . $lt . 'p' . $gt . 'Professionelle'
		=> $lt . 'h3' . $gt . 'Investoren-Master-KVG' . $lt . '/h3' . $gt . $lt . 'p' . $gt . 'Professionelle',
	'für grenzüberschreitende Fondsstrukturen'          => 'für internationale Fondsstrukturen',
	'bis zur Portfoliooptimierung und Second Opinions'  => 'bis zur Manager Selection und Second Opinions',
	'Institutional Investment Consulting GmbH'          => 'Institutional Investment Consulting Partners GmbH',
	// Unser Ansatz, Kachel "Innovation"
	$lt . 'p' . $gt . 'Technologie setzen wir dort ein' => $lt . 'p' . $gt . 'Innovative Technologie setzen wir dort ein',
);
foreach ( $replacements as $old => $new ) {
	if ( false !== strpos( $content, $old ) ) {
		$content = str_replace( $old, $new, $content );
		WP_CLI::log( 'Ersetzt: ' . $new );
	}
}

// --- Die Gruppe
$gruppe_html = <<<'HTML'
<div id="gruppe" class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
<p class="ip-kicker ">Die Gruppe</p>
<h2>Institutional Solutions Division der Institutional Investment Group</h2>
<p class="ip-lead">Institutional Investment Partners GmbH, Institutional Investment Partners S.à.r.l. und Institutional Investment Consulting Partners GmbH bilden die Investor Solutions Division der Institutional Investment Group (2IG). Sie vereint alle Leistungen der 2IG, die sich direkt an institutionelle Investoren richten.</p>
</div>
<div class="ip-col">
<p>Die Institutional Investment Group (2IG) ist eine der führenden unabhängigen Investoren-Plattformen für Real Asset-Investments in Deutschland und Europa. 2IG hat sich mit ihren Geschäftsbereichen Investor Solutions Division und Asset Servicing Division auf Dienstleistungen für institutionelle Real Asset-Fonds nach deutschem und luxemburgischem Recht spezialisiert.</p>
<p>Mit mehr als 350 Mitarbeitern verwaltet 2IG über 50 Milliarden Euro Immobilienvermögen in ca. 25 Ländern und nimmt damit eine marktführende Position in der Administration von Real Assets ein.</p>
<p><strong>100% unabhängig von Banken, Versicherungen, anderen professionellen Immobilieneignern und auch Asset Managern.</strong></p>
<p class="ip-group-link"><a href="https://www.2ig.de" target="_blank" rel="noopener">→ www.2ig.de</a></p>
</div>
</div>
HTML;
$gruppe_html = trim( $gruppe_html );

$start = strpos( $content, $lt . 'div id=' . $quote . 'gruppe' . $quote );
if ( false === $start ) { WP_CLI::error( 'Section "gruppe" nicht gefunden.' ); }
$end = strpos( $content, '"', $start );   // alle Anführungszeichen im Text sind maskiert; das erste echte beendet den JSON-Wert
if ( false === $end ) { WP_CLI::error( 'Ende des Text-Moduls nicht gefunden.' ); }
$old_inner = substr( $content, $start, $end - $start );
if ( false !== strpos( $old_inner, $bs . 'u003c/section' ) || strlen( $old_inner ) > 20000 ) { WP_CLI::error( 'Unerwartete Struktur der Gruppen-Section.' ); }
$new_inner = $esc( $gruppe_html );
if ( $old_inner !== $new_inner ) {
	$content = substr( $content, 0, $start ) . $new_inner . substr( $content, $end );
	WP_CLI::log( 'Section "Die Gruppe" neu gesetzt (' . strlen( $old_inner ) . ' -> ' . strlen( $new_inner ) . ' Zeichen).' );
}

// --- Ausgeblendete Section "Standorte" entfernen
$parts = preg_split( '/(?=<!-- wp:divi\/section )/', $content );
foreach ( $parts as $i => $part ) {
	if ( false !== strpos( $part, 'id=' . $quote . 'standorte' . $quote ) ) {
		if ( $i === count( $parts ) - 1 || 0 === $i ) { WP_CLI::error( 'Section "Standorte" an unerwarteter Position.' ); }
		unset( $parts[ $i ] );
		$content = implode( '', $parts );
		WP_CLI::log( 'Section "Standorte" entfernt.' );
		break;
	}
}

// --- Reihenfolge: "Unser Ansatz" vor "Die Gruppe"
$parts  = preg_split( '/(?=<!-- wp:divi\/section )/', $content );
$ansatz = $gruppe = null;
foreach ( $parts as $i => $part ) {
	if ( false !== strpos( $part, 'id=' . $quote . 'ansatz' . $quote ) ) { $ansatz = $i; }
	if ( false !== strpos( $part, 'id=' . $quote . 'gruppe' . $quote ) )  { $gruppe = $i; }
}
if ( null === $ansatz || null === $gruppe ) { WP_CLI::error( 'Sections "ansatz"/"gruppe" nicht gefunden.' ); }
if ( $ansatz > $gruppe ) {
	if ( $ansatz !== $gruppe + 1 ) { WP_CLI::error( 'Sections stehen nicht direkt hintereinander.' ); }
	$g    = rtrim( $parts[ $gruppe ], "\n" );
	$a    = rtrim( $parts[ $ansatz ], "\n" );
	$tail = substr( $parts[ $ansatz ], strlen( $a ) );
	$parts[ $gruppe ] = $a . "\n";
	$parts[ $ansatz ] = $g . $tail;
	$content = implode( '', $parts );
	WP_CLI::log( 'Sections getauscht: Unser Ansatz steht vor Die Gruppe.' );
}

if ( $content !== $page->post_content ) {
	wp_update_post( array( 'ID' => $page->ID, 'post_content' => wp_slash( $content ) ) );
	WP_CLI::success( 'Startseite aktualisiert.' );
} else {
	WP_CLI::success( 'Nichts zu tun (bereits angewendet).' );
}
