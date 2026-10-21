<?php
/**
 * Schreibweise "Principal Adverse Impacts" / "Principal Adverse Impact Statement" (statt "Principle ...").
 * Betrifft die Download-Bezeichnungen des PAI-Statements (deutsch und englisch) und einen Satz der englischen Seite.
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/principal-adverse-impacts.php
 * Idempotent.
 */
$map = array(
	'Principle Adverse Impacts statement on sustainability factors' => 'Principal Adverse Impact Statement on sustainability factors',
	'considers Principle Adverse Impacts'                         => 'considers Principal Adverse Impacts',
);
$done = 0;
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'lang' => '' ) ) as $p ) {
	if ( false === strpos( $p->post_content, 'Principle Adverse Impacts' ) ) { continue; }
	$walk = static function ( array $blocks ) use ( &$walk, $map ) {
		foreach ( $blocks as &$b ) {
			if ( 'divi/text' === $b['blockName'] && isset( $b['attrs']['content']['innerContent']['desktop']['value'] ) ) {
				$b['attrs']['content']['innerContent']['desktop']['value'] = strtr( $b['attrs']['content']['innerContent']['desktop']['value'], $map );
			}
			if ( ! empty( $b['innerBlocks'] ) ) { $b['innerBlocks'] = $walk( $b['innerBlocks'] ); }
		}
		return $blocks;
	};
	$content = serialize_blocks( $walk( parse_blocks( $p->post_content ) ) );
	if ( $content !== $p->post_content ) {
		wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $content ) ) );
		WP_CLI::log( 'Korrigiert: #' . $p->ID . ' ' . $p->post_name . ' (' . ( function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $p->ID ) : '' ) . ')' );
		$done++;
	}
}
WP_CLI::success( $done ? $done . ' Seite(n) korrigiert.' : 'Nichts zu tun (bereits angewendet).' );
