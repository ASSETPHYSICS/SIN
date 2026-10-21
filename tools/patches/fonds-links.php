<?php
/**
 * Fondsseiten (deutsch): beim Import verrutschte Links und fehlende Leerzeichen korrigieren.
 *   Invesco: Link "https://www.dgnb.de/).LEED"      -> "https://www.dgnb.de/" + "). LEED"
 *   W&W:     Link "https://www.gresb.com/nl-en/).Die" -> "https://www.gresb.com/nl-en/" + "). Die"
 *   Invesco, W&W: "berücksichtigt.Das Finanzprodukt" -> "berücksichtigt. Das Finanzprodukt"
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/fonds-links.php
 * Idempotent.
 */
$fixes = array(
	'#<a href="https://www\.dgnb\.de/\)\.LEED"([^>]*)>https://www\.dgnb\.de/\)\.LEED</a>#'           => '<a href="https://www.dgnb.de/"$1>https://www.dgnb.de/</a>). LEED',
	'#<a href="https://www\.gresb\.com/nl-en/\)\.Die"([^>]*)>https://www\.gresb\.com/nl-en/\)\.Die</a>#' => '<a href="https://www.gresb.com/nl-en/"$1>https://www.gresb.com/nl-en/</a>). Die',
	'#nicht berücksichtigt\.Das Finanzprodukt#u'                                                       => 'nicht berücksichtigt. Das Finanzprodukt',
);
$done = 0;
foreach ( array( 'invesco-real-ge-2014', 'ww-real-estate-international-1' ) as $slug ) {
	$p = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'lang' => 'de' ) );
	if ( ! $p ) { WP_CLI::warning( 'Seite fehlt: ' . $slug ); continue; }
	$p = $p[0];
	$walk = static function ( array $blocks ) use ( &$walk, $fixes ) {
		foreach ( $blocks as &$b ) {
			if ( 'divi/text' === $b['blockName'] && isset( $b['attrs']['content']['innerContent']['desktop']['value'] ) ) {
				$b['attrs']['content']['innerContent']['desktop']['value'] = preg_replace( array_keys( $fixes ), array_values( $fixes ), $b['attrs']['content']['innerContent']['desktop']['value'] );
			}
			if ( ! empty( $b['innerBlocks'] ) ) { $b['innerBlocks'] = $walk( $b['innerBlocks'] ); }
		}
		return $blocks;
	};
	$content = serialize_blocks( $walk( parse_blocks( $p->post_content ) ) );
	if ( $content !== $p->post_content ) {
		wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $content ) ) );
		WP_CLI::log( 'Korrigiert: ' . $slug );
		$done++;
	}
}
WP_CLI::success( $done ? $done . ' Seite(n) korrigiert.' : 'Nichts zu tun (bereits angewendet).' );
