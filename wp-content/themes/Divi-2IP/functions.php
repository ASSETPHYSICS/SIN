<?php
/**
 * Divi 2IP – Child-Theme.
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
