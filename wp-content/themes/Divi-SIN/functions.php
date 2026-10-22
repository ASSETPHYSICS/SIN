<?php
/**
 * Divi ServiceInvest – Child-Theme (abgeleitet vom 2IP-Theme; Funktionsnamen divi_2ip_* bleiben für den Abgleich gleich).
 */

// Version = Theme-Version + Änderungszeit der Datei (Cache-Busting).
function divi_2ip_ver( $file ) {
	return wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/' . $file );
}

add_action( 'wp_enqueue_scripts', function () {
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style( 'divi-2ip', get_stylesheet_uri(), array( 'divi-style' ), divi_2ip_ver( 'style.css' ) );

	// Navigation (Sidebar) und Hamburger-Farbwechsel nach Hintergrund.
	wp_enqueue_script( 'divi-2ip-nav', $uri . '/js/nav.js', array(), divi_2ip_ver( 'js/nav.js' ), true );
	wp_enqueue_script( 'divi-2ip-burger', $uri . '/js/burger.js', array(), divi_2ip_ver( 'js/burger.js' ), true );

	// Logo im Header (Divi-Layout, Link "/") führt auf die Startseite der aktuellen Sprache.
	if ( function_exists( 'pll_home_url' ) && 'de' !== divi_2ip_lang() ) {
		wp_add_inline_script( 'divi-2ip-nav', 'document.querySelectorAll(".et_pb_menu__logo a[href=\'/\'],.et_pb_menu__logo-wrap a[href=\'/\']").forEach(function(a){a.setAttribute("href",' . wp_json_encode( pll_home_url() ) . ');});' );
	}

	// Menü-Skript von Divi 5 immer laden; die automatische Erkennung greift für Theme-Builder-Header nicht zuverlässig.
	if ( defined( 'ET_BUILDER_5_URI' ) ) {
		wp_enqueue_script( 'divi-script-library-menu', ET_BUILDER_5_URI . '/visual-builder/build/script-library-menu.js', array( 'jquery' ), ET_CORE_VERSION, true );
	}

	// Startseite: Kontaktformular ein-/ausklappen.
	if ( is_front_page() ) {
		wp_enqueue_script( 'divi-2ip-contact', $uri . '/js/contact.js', array(), divi_2ip_ver( 'js/contact.js' ), true );
	}

	// Disclaimer-Hinweis nur auf „Second Opinions“.
	if ( is_page( 'second-opinions' ) ) {
		wp_enqueue_script( 'divi-2ip-disclaimer', $uri . '/js/disclaimer.js', array(), divi_2ip_ver( 'js/disclaimer.js' ), true );
	}
}, 20 );

// Schriften früh laden (Roman + Bold decken Fließtext und Überschriften ab).
add_action( 'wp_head', function () {
	$base = get_stylesheet_directory_uri() . '/fonts/';
	foreach ( array( 'NeueHaasGroteskDisplay-Roman.woff2', 'NeueHaasGroteskDisplay-Bold.woff2' ) as $file ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $base . $file ) );
	}
}, 1 );

/* ------------------------------------------------------------------
 * Zweisprachigkeit (Polylang): Deutsch ohne Präfix, Englisch unter /en/
 * ---------------------------------------------------------------- */

function divi_2ip_lang() {
	return function_exists( 'pll_current_language' ) ? ( pll_current_language() ?: 'de' ) : 'de';
}

// Divi-Menümodul verweist fest auf das deutsche Menü: auf englischen Seiten das englische einsetzen
// (Zuordnung schreibt tools/i18n/import.php in die Option ip_menu_translations).
add_filter( 'wp_nav_menu_args', function ( $args ) {
	if ( 'de' === divi_2ip_lang() || empty( $args['menu'] ) ) {
		return $args;
	}
	$map  = (array) get_option( 'ip_menu_translations', array() );
	$menu = wp_get_nav_menu_object( $args['menu'] );
	if ( $menu && isset( $map[ $menu->term_id ] ) ) {
		$args['menu'] = (int) $map[ $menu->term_id ];
	}
	return $args;
} );

// Seitenpfade von Impressum und Datenschutzerklärung. ServiceInvest übernimmt die Adressen der bisherigen
// Website (flache Struktur), damit bestehende externe Links weiter funktionieren.
function divi_2ip_legal_path( $key ) {
	$paths = array( 'impressum' => 'serviceinvest-impressum', 'datenschutz' => 'datenschutzerklaerung' );
	return $paths[ $key ];
}

// [ip_legal_links] – Impressum und Datenschutz in der aktuellen Sprache (Fußzeile).
add_shortcode( 'ip_legal_links', function () {
	$en    = ( 'en' === divi_2ip_lang() );
	$links = array(
		divi_2ip_legal_path( 'impressum' )   => $en ? 'Imprint' : 'Impressum',
		divi_2ip_legal_path( 'datenschutz' ) => $en ? 'Privacy policy' : 'Datenschutz',
	);
	$out = array();
	foreach ( $links as $path => $label ) {
		$page = get_page_by_path( $path );
		if ( ! $page ) {
			continue;
		}
		$id = $page->ID;
		if ( $en && function_exists( 'pll_get_post' ) ) {
			$tr = pll_get_post( $id, 'en' );
			$id = ( $tr && 'publish' === get_post_status( $tr ) ) ? $tr : $id;
		}
		$out[] = '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $label ) . '</a>';
	}
	return implode( ' · ', $out );
} );

// Kontaktformular: Link zur Datenschutzerklärung in der Sprache der Seite (sofern übersetzt).
add_filter( 'appointment_booking_privacy_url', function ( $url ) {
	$page = get_page_by_path( divi_2ip_legal_path( 'datenschutz' ) );
	if ( ! $page ) {
		return $url;
	}
	if ( function_exists( 'pll_get_post' ) && 'de' !== divi_2ip_lang() ) {
		$tr = pll_get_post( $page->ID, divi_2ip_lang() );
		if ( $tr && 'publish' === get_post_status( $tr ) ) {
			return get_permalink( $tr );
		}
	}
	return get_permalink( $page );
} );

// Titel und Beschreibung der Startseite für Suchmaschinen auf Englisch.
add_filter( 'et_get_option_et_divi_divi_seo_home_titletext', function ( $value ) {
	return 'en' === divi_2ip_lang() ? 'ServiceInvest – Asset Servicing and Partner Funds' : $value;
} );
add_filter( 'et_get_option_et_divi_divi_seo_home_descriptiontext', function ( $value ) {
	return 'en' === divi_2ip_lang() ? 'Asset servicing and partner funds for asset managers and KVGs of real estate assets – KVG insourcing in the middle and back office.' : $value;
} );

// Viewport ohne Zoom-Sperre (Divi setzt maximum-scale=1 und user-scalable=0; das verhindert Vergrößern auf Mobilgeräten).
add_action( 'after_setup_theme', function () {
	remove_action( 'wp_head', 'et_add_viewport_meta' );
}, 20 );
add_action( 'wp_head', function () {
	echo '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n";
}, 1 );

// Kopfbereich aufräumen: keine Feeds (es gibt keinen Blog), kein RSD/oEmbed-Discovery, kein Shortlink.
remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

// Favicon: Bildmarke ohne Schriftzug (überschreibt das in WordPress hinterlegte Website-Icon).
add_filter( 'get_site_icon_url', function ( $url, $size ) {
	$pick = 512;
	foreach ( array( 32, 180, 192, 512 ) as $s ) {
		if ( (int) $size <= $s ) {
			$pick = $s;
			break;
		}
	}
	return get_stylesheet_directory_uri() . '/images/favicon-' . $pick . '.png';
}, 10, 2 );
add_filter( 'has_site_icon', '__return_true' );

// Menüeinträge mit Anker (#…) gelten nie als „aktuelle Seite“; der Startseiten-Link ist auf der Startseite aktuell.
add_filter( 'nav_menu_css_class', function ( $classes, $item ) {
	if ( empty( $item->url ) ) {
		return $classes;
	}
	if ( false !== strpos( $item->url, '#' ) ) {
		if ( '#' !== $item->url ) {
			$classes = array_diff( $classes, array( 'current-menu-item', 'current_page_item', 'current-menu-ancestor', 'current-menu-parent', 'current_page_parent', 'current_page_ancestor' ) );
		}
	} elseif ( is_front_page() && '' === untrailingslashit( preg_replace( '#^https?://[^/]+#', '', $item->url ) ) ) {
		$classes[] = 'current-menu-item';
		$classes[] = 'current_page_item';
	}
	return $classes;
}, 20, 2 );
