<?php
/**
 * ServiceInvest: Unterseiten anlegen bzw. befüllen und das Menü vervollständigen.
 * Inhalte aus tools/build/build_legal.py, build_nach.py und build_sin_pages.py (Ausgabeverzeichnis als Argument).
 * Aufruf:  php wp-cli.phar eval-file tools/patches/sin-unterseiten.php <Verzeichnis>
 * Idempotent.
 */
$dir = $args[0] ?? '';
if ( ! is_dir( $dir ) ) { WP_CLI::error( 'Ausgabeverzeichnis der Build-Skripte fehlt.' ); }

$read = static function ( $f ) use ( $dir ) {
	if ( ! is_readable( "$dir/$f" ) ) { WP_CLI::error( "Datei fehlt: $f" ); }
	return file_get_contents( "$dir/$f" );
};
$template = get_page_by_path( 'impressum' );   // Divi-Einstellungen dieser Seite dienen als Vorlage für neue Seiten
if ( ! $template ) { WP_CLI::error( 'Seite "impressum" fehlt.' ); }

/** Legt eine Seite an (falls nötig) und setzt Titel, Inhalt, Elternseite und Status. */
$ensure = static function ( $path, $title, $parent_id, $content, $status = 'publish' ) use ( $template ) {
	$slug = basename( $path );
	$page = get_page_by_path( $path );
	if ( ! $page ) {
		$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'lang' => '', 'suppress_filters' => true ) );
		$page  = $found ? $found[0] : null;
	}
	$data = array( 'post_type' => 'page', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => $parent_id,
		'post_status' => $status, 'comment_status' => 'closed', 'ping_status' => 'closed' );
	if ( null !== $content ) { $data['post_content'] = $content; }
	if ( $page ) {
		$data['ID'] = $page->ID;
		wp_update_post( wp_slash( $data ) );
		$id = $page->ID;
	} else {
		$id = wp_insert_post( wp_slash( $data + array( 'post_content' => '' ) ), true );
		if ( is_wp_error( $id ) ) { WP_CLI::error( $slug . ': ' . $id->get_error_message() ); }
		foreach ( get_post_meta( $template->ID ) as $k => $vals ) {
			if ( preg_match( '/^(_et_|_wp_page_template$)/', $k ) && ! preg_match( '/cache|first_image/', $k ) ) {
				foreach ( $vals as $v ) { add_post_meta( $id, $k, wp_slash( maybe_unserialize( $v ) ) ); }
			}
		}
		WP_CLI::log( "Angelegt: $path (#$id)" );
	}
	if ( function_exists( 'pll_set_post_language' ) ) { pll_set_post_language( $id, 'de' ); }
	return (int) $id;
};

// --- Bestehende Seiten
$imp  = $ensure( 'impressum', 'Impressum', 0, $read( 'legal_14195.txt' ) );
$ensure( 'impressum/datenschutzerklaerung', 'Datenschutzerklärung', $imp, $read( 'legal_14200.txt' ) );
$ensure( 'impressum/datenschutzhinweise-mieter', 'Datenschutzhinweise für Mieter', $imp, $read( 'legal_14296.txt' ) );
$nh   = $ensure( 'nachhaltigkeit', 'Nachhaltigkeit', 0, $read( 'nach.txt' ) );
$ensure( 'nachhaltigkeit/pai-statement', 'PAI Statement', $nh, $read( 'pai-statement.txt' ) );

// --- Neue Seiten
$ensure( 'impressum/beschwerdestelle', 'Beschwerdestelle für Fondsanleger', $imp, $read( 'beschwerdestelle.txt' ) );
$ensure( 'impressum/verguetungspolitik', 'Vergütungspolitik', $imp, $read( 'verguetungspolitik.txt' ) );
$fonds = $ensure( 'nachhaltigkeit/fonds', 'Fonds', $nh, null, 'draft' );   // nur Gruppierung (wie bei 2IP), keine eigene Seite
$ensure( 'nachhaltigkeit/fonds/debeka-immobilien-invest-i', 'Debeka Immobilien Invest I', $fonds, $read( 'debeka-immobilien-invest-i.txt' ) );
$ensure( 'nachhaltigkeit/fonds/tas-wohnen-i', 'TAS Wohnen I', $fonds, $read( 'tas-wohnen-i.txt' ) );

// --- Menüs
$pid  = static function ( $path ) { $p = get_page_by_path( $path ); return $p ? $p->ID : 0; };
$fill = static function ( $menu_slug, array $items ) {
	$menu = wp_get_nav_menu_object( $menu_slug );
	if ( ! $menu ) { WP_CLI::warning( "Menü $menu_slug fehlt" ); return; }
	foreach ( wp_get_nav_menu_items( $menu->term_id ) ?: array() as $it ) { wp_delete_post( $it->ID, true ); }
	$ids = array();
	$pos = 1;
	foreach ( $items as $key => $it ) {
		$args = array( 'menu-item-title' => $it['title'], 'menu-item-status' => 'publish', 'menu-item-position' => $pos++,
			'menu-item-parent-id' => isset( $it['parent'] ) ? $ids[ $it['parent'] ] : 0 );
		if ( array_key_exists( 'page', $it ) ) {
			if ( ! $it['page'] ) { WP_CLI::warning( 'Seite für Menüeintrag fehlt: ' . $it['title'] ); continue; }
			$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $it['page'] );
		} else {
			$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => $it['url'] );
		}
		if ( ! empty( $it['blank'] ) ) { $args += array( 'menu-item-target' => '_blank', 'menu-item-xfn' => 'noopener' ); }
		if ( ! empty( $it['classes'] ) ) { $args['menu-item-classes'] = $it['classes']; }
		$ids[ $key ] = wp_update_nav_menu_item( $menu->term_id, 0, $args );
	}
	WP_CLI::log( "Menü $menu_slug: " . count( $ids ) . ' Einträge' );
};
$legal = array(
	'imp' => array( 'title' => 'Impressum', 'page' => $pid( 'impressum' ) ),
	'ds'  => array( 'title' => 'Datenschutzerklärung', 'page' => $pid( 'impressum/datenschutzerklaerung' ), 'parent' => 'imp' ),
	'dsm' => array( 'title' => 'Datenschutzhinweise für Mieter', 'page' => $pid( 'impressum/datenschutzhinweise-mieter' ), 'parent' => 'imp' ),
	'bs'  => array( 'title' => 'Beschwerdestelle', 'page' => $pid( 'impressum/beschwerdestelle' ), 'parent' => 'imp' ),
	'vg'  => array( 'title' => 'Vergütungspolitik', 'page' => $pid( 'impressum/verguetungspolitik' ), 'parent' => 'imp' ),
);
$fill( 'menu', array(
	'home'    => array( 'title' => 'ServiceInvest', 'url' => '/' ),
	'nh'      => array( 'title' => 'Nachhaltigkeit', 'page' => $pid( 'nachhaltigkeit' ) ),
	'pai'     => array( 'title' => 'PAI Statement', 'page' => $pid( 'nachhaltigkeit/pai-statement' ), 'parent' => 'nh' ),
	'fonds'   => array( 'title' => 'Fonds', 'url' => '#', 'parent' => 'nh', 'classes' => 'ip-nolink' ),
	'debeka'  => array( 'title' => 'Debeka Immobilien Invest I', 'page' => $pid( 'nachhaltigkeit/fonds/debeka-immobilien-invest-i' ), 'parent' => 'fonds' ),
	'tas'     => array( 'title' => 'TAS Wohnen I', 'page' => $pid( 'nachhaltigkeit/fonds/tas-wohnen-i' ), 'parent' => 'fonds' ),
	'jobs'    => array( 'title' => 'Karriere', 'url' => 'https://www.2ig.de/jobs/', 'blank' => true ),
	'kontakt' => array( 'title' => 'Kontakt', 'url' => home_url( '/#kontakt' ) ),
) + $legal );
$fill( 'footer', $legal );

wp_cache_flush();
WP_CLI::success( 'Unterseiten und Menüs aktualisiert.' );
