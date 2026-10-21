<?php
/**
 * Polylang einrichten: Deutsch (Standard, ohne Präfix) und Englisch (/en/).
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/setup-polylang.php
 * Idempotent.
 */
if ( ! function_exists( 'PLL' ) ) { WP_CLI::error( 'Polylang ist nicht aktiv.' ); }

$model = PLL()->model;
$want  = array(
	array( 'locale' => 'de_DE', 'slug' => 'de', 'name' => 'Deutsch', 'term_group' => 0, 'flag' => 'de' ),
	array( 'locale' => 'en_GB', 'slug' => 'en', 'name' => 'English', 'term_group' => 1, 'flag' => 'gb' ),
);
foreach ( $want as $args ) {
	if ( $model->get_language( $args['slug'] ) ) {
		WP_CLI::log( 'Sprache vorhanden: ' . $args['slug'] );
		continue;
	}
	$r = $model->languages->add( $args );
	if ( is_wp_error( $r ) ) { WP_CLI::error( $args['slug'] . ': ' . $r->get_error_message() ); }
	WP_CLI::log( 'Sprache angelegt: ' . $args['slug'] );
}
$model->clean_languages_cache();

$default = $model->get_default_language();
if ( ! $default || 'de' !== $default->slug ) {
	$e = $model->languages->update_default( 'de' );
	if ( $e->has_errors() ) { WP_CLI::error( $e->get_error_message() ); }
	WP_CLI::log( 'Standardsprache: de' );
}

// URL-Schema: Sprache als Verzeichnis (/en/), Standardsprache ohne Präfix, kein /language/-Präfix,
// englische Startseite unter /en/ statt /en/home/ (redirect_lang), keine Weiterleitung nach Browsersprache
// (Google und Besucher sehen dieselbe Startseite).
$opts = PLL()->options;
foreach ( array(
	'force_lang'    => 1,
	'hide_default'  => true,
	'rewrite'       => true,
	'redirect_lang' => true,
	'browser'       => false,
	'media_support' => false,
) as $key => $value ) {
	$e = $opts->set( $key, $value );
	if ( $e->has_errors() ) { WP_CLI::warning( $key . ': ' . $e->get_error_message() ); }
}
$opts->save();

// Bestehende Inhalte ohne Sprache auf Deutsch setzen.
$model->set_language_in_mass();
flush_rewrite_rules( false );

foreach ( pll_languages_list( array( 'fields' => '' ) ) as $l ) {
	WP_CLI::log( sprintf( '%s  %s  %s  default=%s', $l->slug, $l->locale, $l->name, $l->is_default ? 'ja' : 'nein' ) );
}
WP_CLI::success( 'Polylang eingerichtet.' );
