<?php
/**
 * Übernimmt offizielle englische Texte (keine Maschinenübersetzung) in den Übersetzungsspeicher.
 *
 * Eingabe: Verzeichnis mit <deutscher-slug>.json = {"title": "…", "segments": ["<html>", …]} (z. B. aus build_sin_en.py).
 * Die englischen Abschnitte werden in Reihenfolge den Textmodulen der deutschen Seite zugeordnet; die Anzahl muss passen.
 * Ergebnis: content/i18n/en/<deutscher-slug>.json mit "source": "official". translate.php übersetzt diese Abschnitte
 * nicht neu, solange sich der deutsche Text nicht ändert.
 *
 * Aufruf:  php wp-cli.phar eval-file tools/i18n/official.php <Verzeichnis>
 */
require_once __DIR__ . '/lib.php';
$in   = $args[0] ?? '';
$root = dirname( __DIR__, 2 );
if ( ! is_dir( $in ) ) { WP_CLI::error( 'Eingabeverzeichnis fehlt.' ); }

$segments_of = static function ( $content ) {
	$list = array();
	$walk = static function ( $blocks ) use ( &$walk, &$list ) {
		foreach ( $blocks as $b ) {
			if ( 'divi/text' === $b['blockName'] && isset( $b['attrs']['content']['innerContent']['desktop']['value'] ) ) {
				$h = $b['attrs']['content']['innerContent']['desktop']['value'];
				if ( '' !== trim( wp_strip_all_tags( $h ) ) ) { $list[] = $h; }
			}
			if ( ! empty( $b['innerBlocks'] ) ) { $walk( $b['innerBlocks'] ); }
		}
	};
	$walk( parse_blocks( $content ) );
	return $list;
};

$dir = $root . '/content/i18n/en';
if ( ! is_dir( $dir ) ) { mkdir( $dir, 0755, true ); }
$errors = 0;
foreach ( glob( $in . '/*.json' ) as $file ) {
	$slug = basename( $file, '.json' );
	$en   = json_decode( file_get_contents( $file ), true );
	$de   = ip_i18n_find_page( $slug, 'de' );
	if ( ! $de ) { WP_CLI::warning( "Deutsche Seite fehlt: $slug" ); $errors++; continue; }
	$de_segs = $segments_of( $de->post_content );
	if ( count( $de_segs ) !== count( $en['segments'] ) ) {
		WP_CLI::warning( sprintf( '%s: %d deutsche, aber %d englische Abschnitte', $slug, count( $de_segs ), count( $en['segments'] ) ) );
		$errors++;
		continue;
	}
	$out = array();
	foreach ( $de_segs as $i => $h ) {
		$out[] = array( 'id' => sha1( $h ), 'de' => $h, 'en' => $en['segments'][ $i ], 'source' => 'official' );
	}
	$data = array( 'page' => $slug, 'title' => array( 'de' => $de->post_title, 'en' => $en['title'] ), 'segments' => $out );
	file_put_contents( "$dir/$slug.json", wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
	WP_CLI::log( sprintf( '%-90s %d Abschnitt(e) übernommen', $slug, count( $out ) ) );
}
$errors ? WP_CLI::error( "$errors Seite(n) nicht übernommen." ) : WP_CLI::success( 'Offizielle Texte übernommen.' );
