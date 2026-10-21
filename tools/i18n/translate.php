<?php
/**
 * Übersetzt die in config.php eingetragenen Seiten mit DeepL und schreibt den Übersetzungsspeicher
 * content/i18n/en/<deutscher-slug>.json. Die Datenbank wird nicht verändert (dafür: import.php).
 *
 * Aufruf (lokal, Schlüssel nur in ~/.config/2ip/deepl.env, nie im Repository):
 *   set -a; . ~/.config/2ip/deepl.env; set +a
 *   php wp-cli.phar eval-file tools/i18n/translate.php [deutscher-slug ...]
 *
 * Bereits vorhandene Abschnitte (gleicher deutscher Text) werden nicht neu übersetzt; manuelle Korrekturen im
 * Feld "en" bleiben dadurch erhalten. Ändert sich der deutsche Text eines Abschnitts, wird nur dieser neu übersetzt.
 */
$cfg  = require __DIR__ . '/config.php';
$root = dirname( __DIR__, 2 );
$key  = getenv( 'DEEPL_API_KEY' );
if ( ! $key ) { WP_CLI::error( 'DEEPL_API_KEY fehlt (set -a; . ~/.config/2ip/deepl.env; set +a).' ); }
$api  = ( ':fx' === substr( $key, -3 ) ) ? 'https://api-free.deepl.com' : 'https://api.deepl.com';

$deepl = static function ( $method, $path, $body = null ) use ( $api, $key ) {
	$args = array(
		'method'  => $method,
		'timeout' => 90,
		'headers' => array( 'Authorization' => 'DeepL-Auth-Key ' . $key, 'Content-Type' => 'application/json' ),
	);
	if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
	$r = wp_remote_request( $api . $path, $args );
	if ( is_wp_error( $r ) ) { WP_CLI::error( 'DeepL: ' . $r->get_error_message() ); }
	$code = wp_remote_retrieve_response_code( $r );
	if ( $code >= 300 ) { WP_CLI::error( 'DeepL HTTP ' . $code . ': ' . wp_remote_retrieve_body( $r ) ); }
	return json_decode( wp_remote_retrieve_body( $r ), true );
};

// --- Glossar (wird bei Änderung neu angelegt; die ID liegt lokal neben dem Schlüssel)
$glossary_id = null;
if ( ! empty( $cfg['glossary'] ) ) {
	$tsv = '';
	foreach ( $cfg['glossary'] as $de => $en ) { $tsv .= $de . "\t" . $en . "\n"; }
	$hash  = sha1( $tsv );
	$cache = getenv( 'HOME' ) . '/.config/2ip/deepl-glossary.json';
	$c     = file_exists( $cache ) ? json_decode( file_get_contents( $cache ), true ) : array();
	if ( ! empty( $c['hash'] ) && $c['hash'] === $hash ) {
		$glossary_id = $c['id'];
	} else {
		if ( ! empty( $c['id'] ) ) { $deepl( 'DELETE', '/v2/glossaries/' . $c['id'] ); }
		$g = $deepl( 'POST', '/v2/glossaries', array(
			'name' => '2IP DE-EN', 'source_lang' => 'de', 'target_lang' => 'en', 'entries' => $tsv, 'entries_format' => 'tsv',
		) );
		$glossary_id = $g['glossary_id'];
		file_put_contents( $cache, wp_json_encode( array( 'hash' => $hash, 'id' => $glossary_id ) ) );
		chmod( $cache, 0600 );
		WP_CLI::log( 'Glossar angelegt (' . count( $cfg['glossary'] ) . ' Einträge).' );
	}
}

/** Übersetzt HTML-Abschnitte; Shortcodes werden vor DeepL geschützt. */
$translate = static function ( array $texts, $html = true ) use ( $deepl, $cfg, $glossary_id ) {
	$out = array();
	foreach ( array_chunk( $texts, 40, true ) as $chunk ) {
		$keep = array();
		$send = array();
		foreach ( $chunk as $i => $t ) {
			if ( $html ) {
				$t = preg_replace_callback( '/\[[a-z_]+[^\]]*\]/', static function ( $m ) use ( &$keep ) {
					$keep[] = $m[0];
					return '<span translate="no" data-ipsc="' . ( count( $keep ) - 1 ) . '">#</span>';
				}, $t );
			}
			$send[] = $t;
		}
		$body = array( 'text' => $send, 'source_lang' => $cfg['source'], 'target_lang' => $cfg['target'], 'preserve_formatting' => true );
		if ( $html ) { $body['tag_handling'] = 'html'; }
		if ( $glossary_id ) { $body['glossary_id'] = $glossary_id; }
		$r = $deepl( 'POST', '/v2/translate', $body );
		$k = 0;
		foreach ( array_keys( $chunk ) as $i ) {
			$t = $r['translations'][ $k++ ]['text'];
			if ( $html ) {
				$t = preg_replace_callback( '/<span translate="no" data-ipsc="(\d+)">.*?<\/span>/s', static function ( $m ) use ( $keep ) {
					return $keep[ (int) $m[1] ];
				}, $t );
			}
			$out[ $i ] = $t;
		}
	}
	return $out;
};

/** Liefert die Textmodule einer Seite in Reihenfolge (HTML). */
$segments_of = static function ( $content ) {
	$list = array();
	$walk = static function ( $blocks ) use ( &$walk, &$list ) {
		foreach ( $blocks as $b ) {
			if ( 'divi/text' === $b['blockName'] && isset( $b['attrs']['content']['innerContent']['desktop']['value'] ) ) {
				$html = $b['attrs']['content']['innerContent']['desktop']['value'];
				if ( '' !== trim( wp_strip_all_tags( $html ) ) ) { $list[] = $html; }
			}
			if ( ! empty( $b['innerBlocks'] ) ) { $walk( $b['innerBlocks'] ); }
		}
	};
	$walk( parse_blocks( $content ) );
	return $list;
};

$only = isset( $args ) ? $args : array();
$dir  = $root . '/content/i18n/en';
if ( ! is_dir( $dir ) ) { mkdir( $dir, 0755, true ); }
$chars = 0;

foreach ( $cfg['pages'] as $de_slug => $page_cfg ) {
	if ( $only && ! in_array( $de_slug, $only, true ) ) { continue; }
	$de = get_page_by_path( $de_slug ) ?: get_posts( array( 'post_type' => 'page', 'name' => $de_slug, 'post_status' => 'any', 'numberposts' => 1, 'lang' => 'de' ) )[0] ?? null;
	if ( ! $de ) { WP_CLI::warning( 'Seite nicht gefunden: ' . $de_slug ); continue; }

	$file = $dir . '/' . $de_slug . '.json';
	$tm   = file_exists( $file ) ? json_decode( file_get_contents( $file ), true ) : array();
	$old  = array();
	foreach ( $tm['segments'] ?? array() as $s ) { $old[ $s['id'] ] = $s; }

	$segments = array();
	$todo     = array();
	foreach ( $segments_of( $de->post_content ) as $html ) {
		$id = sha1( $html );
		if ( isset( $segments[ $id ] ) ) { continue; }
		$segments[ $id ] = $old[ $id ] ?? array( 'id' => $id, 'de' => $html, 'en' => null );
		if ( null === $segments[ $id ]['en'] ) { $todo[ $id ] = $html; $chars += strlen( $html ); }
	}

	$title_de = $de->post_title;
	$title    = $tm['title'] ?? array( 'de' => null, 'en' => null );
	if ( ! empty( $page_cfg['title'] ) ) {
		$title = array( 'de' => $title_de, 'en' => $page_cfg['title'] );
	} elseif ( $title['de'] !== $title_de || empty( $title['en'] ) ) {
		$title = array( 'de' => $title_de, 'en' => $translate( array( $title_de ), false )[0] );
	}

	if ( $todo ) {
		foreach ( $translate( $todo ) as $id => $en ) { $segments[ $id ]['en'] = $en; }
	}

	$data = array(
		'page'     => $de_slug,
		'title'    => $title,
		'segments' => array_values( $segments ),
	);
	file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
	WP_CLI::log( sprintf( '%-18s %2d Abschnitte, davon %2d neu übersetzt', $de_slug, count( $segments ), count( $todo ) ) );
}
WP_CLI::success( 'Übersetzung fertig (' . number_format( $chars, 0, ',', '.' ) . ' Zeichen an DeepL).' );
