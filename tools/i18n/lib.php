<?php
/**
 * Gemeinsame Hilfsfunktionen der Übersetzungsskripte.
 */

/**
 * Findet eine Seite über Slug und Sprache. Deutsche und englische Seite können denselben Slug haben
 * (unter verschiedenen Elternseiten), deshalb wird die Sprache ausdrücklich über Polylang geprüft.
 */
function ip_i18n_find_page( $slug, $lang = 'de' ) {
	$ids = get_posts( array(
		'post_type'        => 'page',
		'name'             => $slug,
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'lang'             => '',      // Polylang-Filter aus; die Sprache wird unten geprüft
		'suppress_filters' => true,
	) );
	foreach ( $ids as $id ) {
		$l = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $id ) : 'de';
		if ( $l === $lang || ( ! $l && 'de' === $lang ) ) {
			return get_post( $id );
		}
	}
	return null;
}
