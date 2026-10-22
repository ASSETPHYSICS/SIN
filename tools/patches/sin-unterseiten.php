<?php
/**
 * ServiceInvest: Unterseiten anlegen bzw. befüllen und das Menü vervollständigen.
 * Die Adressen entsprechen der bisherigen serviceinvest.de (flache Struktur), damit externe Links weiter funktionieren.
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
$by_slug = static function ( $slug ) {   // direkte Abfrage: findet auch Entwürfe zuverlässig
	global $wpdb;
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name = %s AND post_status <> 'trash' ORDER BY ID LIMIT 1", $slug ) );
	return $id ? get_post( (int) $id ) : null;
};

// --- 1. Umzug von der zwischenzeitlichen Struktur (/impressum/…, /nachhaltigkeit/…) auf die bisherigen Adressen
$moves = array(
	'impressum'                     => 'serviceinvest-impressum',
	'nachhaltigkeit'                => 'nachhaltigkeit-2',
	'pai-statement'                 => 'erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2',
	'datenschutzhinweise-mieter'    => 'datenschutzhinweisefuermieter',
	'tas-wohnen-i'                  => 'tas-wohnen-1',
);
foreach ( $moves as $old => $new ) {
	$p = $by_slug( $old );
	if ( $p && ! $by_slug( $new ) ) {
		wp_update_post( array( 'ID' => $p->ID, 'post_name' => $new, 'post_parent' => 0 ) );
		WP_CLI::log( "Verschoben: $old -> /$new/" );
	}
}
$fonds = $by_slug( 'fonds' );
if ( $fonds ) {
	foreach ( get_children( array( 'post_parent' => $fonds->ID, 'post_type' => 'page' ) ) as $child ) {
		wp_update_post( array( 'ID' => $child->ID, 'post_parent' => 0 ) );
	}
	wp_delete_post( $fonds->ID, true );
	WP_CLI::log( 'Gruppierungsseite "Fonds" entfernt (Gruppe bleibt im Menü).' );
}

$template = $by_slug( 'serviceinvest-impressum' );   // Divi-Einstellungen dieser Seite dienen als Vorlage für neue Seiten
if ( ! $template ) { WP_CLI::error( 'Impressum-Seite fehlt.' ); }

/** Legt eine Seite auf oberster Ebene an (falls nötig) und setzt Titel und Inhalt. */
$ensure = static function ( $slug, $title, $content ) use ( $template, $by_slug ) {
	$page = $by_slug( $slug );
	$data = array( 'post_type' => 'page', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => 0,
		'post_status' => 'publish', 'comment_status' => 'closed', 'ping_status' => 'closed', 'post_content' => $content );
	if ( $page ) {
		$data['ID'] = $page->ID;
		wp_update_post( wp_slash( $data ) );
		$id = $page->ID;
	} else {
		$id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) { WP_CLI::error( $slug . ': ' . $id->get_error_message() ); }
		foreach ( get_post_meta( $template->ID ) as $k => $vals ) {
			if ( preg_match( '/^(_et_|_wp_page_template$)/', $k ) && ! preg_match( '/cache|first_image/', $k ) ) {
				foreach ( $vals as $v ) { add_post_meta( $id, $k, wp_slash( maybe_unserialize( $v ) ) ); }
			}
		}
		WP_CLI::log( "Angelegt: /$slug/ (#$id)" );
	}
	if ( function_exists( 'pll_set_post_language' ) ) { pll_set_post_language( $id, 'de' ); }
	$got = get_post_field( 'post_name', $id );
	if ( $got !== $slug ) { WP_CLI::warning( "Slug von #$id ist '$got' statt '$slug' (Adresse belegt?)" ); }
	return (int) $id;
};

// --- 2. Seiten mit den Adressen der bisherigen Website
$ensure( 'serviceinvest-impressum', 'Impressum', $read( 'legal_14195.txt' ) );
$ensure( 'datenschutzerklaerung', 'Datenschutzerklärung', $read( 'legal_14200.txt' ) );
$ensure( 'datenschutzhinweisefuermieter', 'Datenschutzhinweise für Mieter', $read( 'legal_14296.txt' ) );
$ensure( 'beschwerdestelle', 'Beschwerdestelle für Fondsanleger', $read( 'beschwerdestelle.txt' ) );
$ensure( 'verguetungspolitik', 'Vergütungspolitik', $read( 'verguetungspolitik.txt' ) );
$ensure( 'nachhaltigkeit-2', 'Nachhaltigkeit', $read( 'nach.txt' ) );
$ensure( 'erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2', 'PAI Statement', $read( 'pai-statement.txt' ) );
$ensure( 'debeka-immobilien-invest-i', 'Debeka Immobilien Invest I', $read( 'debeka-immobilien-invest-i.txt' ) );
$ensure( 'tas-wohnen-1', 'TAS Wohnen I', $read( 'tas-wohnen-i.txt' ) );

// --- 3. Menüs (Gliederung wie bisher; die Seiten selbst liegen flach)
$pid  = static function ( $slug ) use ( $by_slug ) { $p = $by_slug( $slug ); return $p ? $p->ID : 0; };
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
	'imp' => array( 'title' => 'Impressum', 'page' => $pid( 'serviceinvest-impressum' ) ),
	'ds'  => array( 'title' => 'Datenschutzerklärung', 'page' => $pid( 'datenschutzerklaerung' ), 'parent' => 'imp' ),
	'dsm' => array( 'title' => 'Datenschutzhinweise für Mieter', 'page' => $pid( 'datenschutzhinweisefuermieter' ), 'parent' => 'imp' ),
	'bs'  => array( 'title' => 'Beschwerdestelle', 'page' => $pid( 'beschwerdestelle' ), 'parent' => 'imp' ),
	'vg'  => array( 'title' => 'Vergütungspolitik', 'page' => $pid( 'verguetungspolitik' ), 'parent' => 'imp' ),
);
$fill( 'menu', array(
	'home'    => array( 'title' => 'ServiceInvest', 'url' => '/' ),
	'nh'      => array( 'title' => 'Nachhaltigkeit', 'page' => $pid( 'nachhaltigkeit-2' ) ),
	'pai'     => array( 'title' => 'PAI Statement', 'page' => $pid( 'erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2' ), 'parent' => 'nh' ),
	'fonds'   => array( 'title' => 'Fonds', 'url' => '#', 'parent' => 'nh', 'classes' => 'ip-nolink' ),
	'debeka'  => array( 'title' => 'Debeka Immobilien Invest I', 'page' => $pid( 'debeka-immobilien-invest-i' ), 'parent' => 'fonds' ),
	'tas'     => array( 'title' => 'TAS Wohnen I', 'page' => $pid( 'tas-wohnen-1' ), 'parent' => 'fonds' ),
	'jobs'    => array( 'title' => 'Karriere', 'url' => 'https://www.2ig.de/jobs/', 'blank' => true ),
	'kontakt' => array( 'title' => 'Kontakt', 'url' => home_url( '/#kontakt' ) ),
) + $legal );
$fill( 'footer', $legal );

flush_rewrite_rules( false );
wp_cache_flush();
WP_CLI::success( 'Unterseiten und Menüs aktualisiert.' );
