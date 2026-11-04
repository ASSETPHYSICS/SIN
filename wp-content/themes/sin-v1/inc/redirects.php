<?php
/**
 * Weiterleitungen der bisherigen Website (HA 5.2, 8) und alles, was es auf dieser Website nicht gibt.
 * sitemap.xml und robots.txt sind Dateien im Stammverzeichnis (der Webserver liefert sie direkt aus).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bisherige Adressen (Pfad ohne Schrägstriche) => neues Ziel. Quelle: HA 5.2 und die veröffentlichten Seiten der bisherigen
 * Website (WordPress/Divi mit Polylang, ~/public/_sin) am 4. November 2026. /beschwerdestelle/, /verguetungspolitik/,
 * /en/sustainability/ und /en/remuneration-policy/ bleiben unverändert und stehen deshalb nicht hier.
 */
function sin_redirect_map(): array {
	return array(
		'startseite'                     => '/',
		'home'                           => '/',
		'nachhaltigkeit-2'               => '/nachhaltigkeit/',
		'erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2' => '/pai-statement/',
		'debeka-immobilien-invest-i'     => '/anlegerzugang/',
		'tas-wohnen-1'                   => '/anlegerzugang/',
		'serviceinvest-impressum'        => '/impressum/',
		'datenschutzerklaerung'          => '/datenschutz/',
		'datenschutzhinweisefuermieter'  => '/datenschutz-mieter/',
		'en/home'                        => '/en/',
		'en/complaints-office'           => '/en/complaints/',
		'en/debeka-immobilien-invest-i-en' => '/en/investor-access/',
		'en/tas-wohnen-1-en'             => '/en/investor-access/',
		'en/imprint'                     => '/en/legal-notice/',
		'en/privacy-policy'              => '/en/privacy/',
		'en/data-protection-information' => '/en/privacy-tenants/',
		'en/statement-on-principal-adverse-impacts' => '/en/pai-statement/',
		// bisherige Dateien: PAI-PDFs an den neuen Ablageort (HA 8). Die Fonds-Offenlegungen liefert inc/fonds.php unter der
		// bisherigen Adresse aus; die Weiterleitung auf den Anlegerzugang greift nur, wenn die Datei fehlt.
		'wp-content/uploads/2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf'  => '/dokumente/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf',
		'wp-content/uploads/2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_ENG-clean.pdf' => '/dokumente/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_ENG-clean.pdf',
		'wp-content/uploads/2025/08/Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.-10-SFDR_Art_8-c-final.pdf' => '/anlegerzugang/',
		'wp-content/uploads/2025/08/Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.10-SFDR-english.pdf'      => '/en/investor-access/',
		'wp-content/uploads/2025/04/2024-06-19-VOe_Internetseite-Art.-10-SFDR-TAS-Wohnen-I-c.pdf'               => '/anlegerzugang/',
		'wp-content/uploads/2025/04/2024-04-19-website-disclosure-Art.-10-SFDR-TAS-Wohnen-I-c.pdf'              => '/en/investor-access/',
		'wp-content/uploads/2024/12/2023-10-31-2IG-Stewardship-Policy.pdf'                                      => '/stewardship-policy/',
		'wp-content/themes/Divi-SIN/downloads/2023-10-31-2IG-Stewardship-Policy.pdf'                            => '/stewardship-policy/',
	);
}

/** Ziel für einen angefragten Pfad oder null. Übrige /en/…-Adressen der bisherigen Website ohne Entsprechung: 404. */
function sin_redirect_target( string $path ): ?string {
	$map = sin_redirect_map();
	return isset( $map[ $path ] ) ? home_url( $map[ $path ] ) : null;
}

// Vor redirect_canonical (Priorität 10), damit WordPress nicht selbst eine ähnliche Seite "errät".
add_action( 'template_redirect', function () {
	$to = sin_redirect_target( sin_request_path() );
	if ( $to ) {
		wp_safe_redirect( $to, 301, 'ServiceInvest' );
		exit;
	}
}, 1 );
add_filter( 'do_redirect_guess_404_permalink', '__return_false' );

// Feeds, Archive, Suche, Anhänge, Beiträge: gibt es auf dieser Website nicht.
add_action( 'template_redirect', function () {
	if ( is_feed() || is_search() || is_archive() || is_attachment() || is_single() || ( is_home() && ! is_front_page() ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		// sonst liefert template-loader.php für Feeds trotzdem den Feed aus
		include get_theme_file_path( 'index.php' );
		exit;
	}
}, 2 );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

// Sitemap und robots.txt kommen aus dem Build (Dateien im Stammverzeichnis).
add_filter( 'wp_sitemaps_enabled', '__return_false' );
remove_action( 'wp_head', 'feed_links', 2 );
