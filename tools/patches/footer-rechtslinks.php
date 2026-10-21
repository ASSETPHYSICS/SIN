<?php
/**
 * Fußzeile: feste Links "Impressum · Datenschutz" durch den sprachabhängigen Shortcode [ip_legal_links] ersetzen.
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/footer-rechtslinks.php
 * Idempotent.
 */
$done = 0;
foreach ( get_posts( array( 'post_type' => 'et_footer_layout', 'post_status' => 'any', 'numberposts' => -1 ) ) as $layout ) {
	$changed = false;
	$walk = static function ( array $blocks ) use ( &$walk, &$changed ) {
		foreach ( $blocks as &$b ) {
			if ( 'divi/text' === $b['blockName'] && isset( $b['attrs']['content']['innerContent']['desktop']['value'] ) ) {
				$html = $b['attrs']['content']['innerContent']['desktop']['value'];
				$new  = preg_replace( '#<p class="ip-footer__links">.*?</p>#s', '<p class="ip-footer__links">[ip_legal_links]</p>', $html );
				if ( $new !== $html ) {
					$b['attrs']['content']['innerContent']['desktop']['value'] = $new;
					$changed = true;
				}
			}
			if ( ! empty( $b['innerBlocks'] ) ) { $b['innerBlocks'] = $walk( $b['innerBlocks'] ); }
		}
		return $blocks;
	};
	$content = serialize_blocks( $walk( parse_blocks( $layout->post_content ) ) );
	if ( $changed ) {
		wp_update_post( array( 'ID' => $layout->ID, 'post_content' => wp_slash( $content ) ) );
		WP_CLI::log( 'Fußzeile #' . $layout->ID . ' umgestellt.' );
		$done++;
	}
}
WP_CLI::success( $done ? $done . ' Fußzeile(n) aktualisiert.' : 'Nichts zu tun (bereits angewendet).' );
