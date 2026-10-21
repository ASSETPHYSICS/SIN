<?php
/**
 * Startseite, Änderungen vom 21.10.2026 (Texte, Kicker).
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/startseite-2026-10-21.php
 * Idempotent: bereits angewendete Änderungen werden übersprungen.
 *
 * Divi speichert Inhalte JSON-maskiert (" für ", < für <); diese Zeichenfolgen werden deshalb
 * aus einem Backslash zusammengesetzt.
 */
$page = get_page_by_path( 'startseite' );
if ( ! $page ) { WP_CLI::error( 'Seite "startseite" nicht gefunden.' ); }

$bs      = chr( 92 );
$quote   = $bs . 'u0022';
$lt      = $bs . 'u003c';
$content = $page->post_content;

$replacements = array(
	// Header: Einleitung
	'Institutionelle Anleger stehen vor steigenden Anforderungen an Governance, Reporting und Regulierung. Wir schaffen die Strukturen, Prozesse und Transparenz, damit Sie sich auf fundierte Investitionsentscheidungen konzentrieren können.'
		=> 'Institutionelle Anleger stehen vor wachsenden Anforderungen an Governance, Reporting und Regulierung. Wir schaffen klare Strukturen, verlässliche Prozesse und transparente Informationen, damit Anlageentscheidungen auf einer belastbaren Grundlage getroffen werden können.',
	// Header: erste Zeile
	'Master-KVG · AIFM · Beratung' => 'Investoren-Master-KVG · AIFM · Investment Consulting',
	// Section "Unser Versprechen" (Kicker, nicht die Überschrift "Unsere Überzeugungen" der Nachhaltigkeitsseite)
	'Unsere Überzeugung' . $lt => 'Unser Versprechen' . $lt,
	'Real Assets bieten langfristige Chancen.'
		=> 'Real Assets erfordern langfristiges Denken, belastbare Strukturen und einen klaren Überblick über Risiken, Verantwortlichkeiten und Entwicklungen.',
	'Komplexität zu reduzieren, Verantwortlichkeiten klar zu strukturieren und jederzeit den Überblick zu behalten.'
		=> 'sich auf das Wesentliche zu konzentrieren, klare Entscheidungen zu treffen und jederzeit den Überblick zu behalten.',
);
foreach ( $replacements as $old => $new ) {
	if ( false !== strpos( $content, $old ) ) {
		$content = str_replace( $old, $new, $content );
		WP_CLI::log( 'Ersetzt: ' . mb_substr( $new, 0, 60 ) . '…' );
	}
}

// Die Reihenfolge der Sections regelt inzwischen startseite-leistungen-gruppe.php (Ansatz vor Gruppe).

if ( $content !== $page->post_content ) {
	wp_update_post( array( 'ID' => $page->ID, 'post_content' => wp_slash( $content ) ) );
	WP_CLI::success( 'Startseite aktualisiert.' );
} else {
	WP_CLI::success( 'Nichts zu tun (bereits angewendet).' );
}
