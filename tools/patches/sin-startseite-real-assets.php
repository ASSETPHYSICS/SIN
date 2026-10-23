<?php
/**
 * ServiceInvest: Startseite neu setzen (Real Assets statt Immobilien, keine Produkt- und Einheitennamen)
 * und die aus 2IP übernommene grüne Divi-Linkfarbe/-Palette auf die SI-Farben umstellen.
 *   - Startseite aus tools/build/build_home.py (home.txt in $args[0])
 *   - Suchmaschinen-Beschreibung der deutschen Startseite
 * Aufruf:  php wp-cli.phar eval-file tools/patches/sin-startseite-real-assets.php <Verzeichnis mit home.txt>
 * Idempotent. Die englische Startseite danach mit tools/i18n/import.php aktualisieren.
 */
$dir = $args[0] ?? '';
if ( ! is_readable( "$dir/home.txt" ) ) { WP_CLI::error( 'home.txt fehlt (Verzeichnis als Argument übergeben).' ); }

// --- 1. Startseite (deutsch)
$home = get_posts( array( 'post_type' => 'page', 'name' => 'startseite', 'post_status' => 'any', 'numberposts' => 1, 'lang' => '', 'suppress_filters' => true ) );
if ( ! $home ) { WP_CLI::error( 'Seite "startseite" nicht gefunden.' ); }
$new = file_get_contents( "$dir/home.txt" );
if ( $home[0]->post_content === $new ) {
	WP_CLI::log( 'Startseite unverändert.' );
} else {
	wp_update_post( array( 'ID' => $home[0]->ID, 'post_content' => wp_slash( $new ) ) );
	WP_CLI::log( 'Startseite gesetzt (#' . $home[0]->ID . ')' );
}

// --- 2. Suchmaschinen-Beschreibung, Linkfarbe und Farbpalette (Divi-Optionen)
$o = get_option( 'et_divi' );
$o['divi_seo_home_descriptiontext'] = 'Asset Servicing und Partner-Fonds für Asset Manager und KVGen von Real Asset-Vermögen – KVG-Insourcing im Middle und Back Office.';
$o['link_color']         = '#8E0A0A';
$o['divi_color_palette'] = '#1B1B1C|#FFFFFF|#C50000|#8E0A0A|#4A0E0E|#D9604F|#F2F2F3|#595A5C';
update_option( 'et_divi', $o );
WP_CLI::log( 'Divi: Beschreibung, Linkfarbe und Palette gesetzt.' );

if ( function_exists( 'PLL' ) ) { PLL()->model->clean_languages_cache(); }
wp_cache_flush();
WP_CLI::success( 'Startseite aktualisiert.' );
