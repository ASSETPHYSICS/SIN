<?php
/**
 * ServiceInvest: Grundeinrichtung der aus 2IP kopierten Datenbank (einmalig, lokal).
 *   - 2IP-spezifische Seiten entfernen (Second Opinions, 2IP-Fonds, englische Seiten, Karriere-Platzhalter)
 *   - Seitennamen, Menüs, Website-Titel und Suchmaschinen-Angaben auf ServiceInvest umstellen
 *   - Startseite und Fußzeile aus tools/build/build_home.py setzen (Dateien home.txt/footer.txt in $args[0])
 * Aufruf:  php wp-cli.phar eval-file tools/patches/sin-grundeinrichtung.php <Verzeichnis mit home.txt und footer.txt>
 * Idempotent.
 */
$dir = $args[0] ?? '';
if ( ! is_readable( "$dir/home.txt" ) || ! is_readable( "$dir/footer.txt" ) ) { WP_CLI::error( 'home.txt/footer.txt fehlen (Verzeichnis als Argument übergeben).' ); }

$by_slug = static function ( $slug ) {
	return get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1, 'lang' => '', 'suppress_filters' => true ) );
};

// --- 1. Seiten entfernen
$remove = array( 'second-opinions', 'german-living-impact-fund', 'galcap-austria-immobilien-i', 'icf-glog', 'invesco-real-ge-2014',
	'ww-real-estate-international-1', 'ausgewaehlte-fonds', 'karriere', 'kontakt', 'datenschutzhinweise-mieter-2ip-sarl' );
$n = 0;
foreach ( $remove as $slug ) { foreach ( $by_slug( $slug ) as $p ) { wp_delete_post( $p->ID, true ); $n++; } }
if ( function_exists( 'pll_get_post_language' ) ) {
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'lang' => 'en', 'suppress_filters' => false ) ) as $p ) {
		if ( 'en' === pll_get_post_language( $p->ID ) ) { wp_delete_post( $p->ID, true ); $n++; }
	}
}
foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'trash', 'numberposts' => -1 ) ) as $p ) { wp_delete_post( $p->ID, true ); }
WP_CLI::log( "Seiten entfernt: $n" );

// --- 2. Mieter-Datenschutz: neutraler Slug und Titel
foreach ( $by_slug( 'datenschutzhinweise-mieter-2ip-gmbh' ) as $p ) {
	wp_update_post( array( 'ID' => $p->ID, 'post_name' => 'datenschutzhinweise-mieter', 'post_title' => 'Datenschutzhinweise für Mieter' ) );
	WP_CLI::log( 'Seite umbenannt: datenschutzhinweise-mieter' );
}

// --- 3. Englische Menüs entfernen (werden bei Bedarf von tools/i18n/import.php neu erzeugt)
foreach ( wp_get_nav_menus() as $m ) { if ( '(EN)' === substr( $m->name, -4 ) ) { wp_delete_nav_menu( $m->term_id ); } }
delete_option( 'ip_menu_translations' );

// --- 4. Menüs neu aufbauen
$page_id = static function ( $path ) { $p = get_page_by_path( $path ); return $p ? $p->ID : 0; };
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
$fill( 'menu', array(
	'home'   => array( 'title' => 'ServiceInvest', 'url' => '/' ),
	'nh'     => array( 'title' => 'Nachhaltigkeit', 'page' => $page_id( 'nachhaltigkeit' ) ),
	'pai'    => array( 'title' => 'PAI Statement', 'page' => $page_id( 'nachhaltigkeit/pai-statement' ), 'parent' => 'nh' ),
	'jobs'   => array( 'title' => 'Karriere', 'url' => 'https://www.2ig.de/jobs/', 'blank' => true ),
	'kontakt'=> array( 'title' => 'Kontakt', 'url' => home_url( '/#kontakt' ) ),
	'imp'    => array( 'title' => 'Impressum', 'page' => $page_id( 'impressum' ) ),
	'ds'     => array( 'title' => 'Datenschutzerklärung', 'page' => $page_id( 'impressum/datenschutzerklaerung' ), 'parent' => 'imp' ),
	'dsm'    => array( 'title' => 'Datenschutzhinweise für Mieter', 'page' => $page_id( 'impressum/datenschutzhinweise-mieter' ), 'parent' => 'imp' ),
) );
$fill( 'footer', array(
	'imp' => array( 'title' => 'Impressum', 'page' => $page_id( 'impressum' ) ),
	'ds'  => array( 'title' => 'Datenschutzerklärung', 'page' => $page_id( 'impressum/datenschutzerklaerung' ), 'parent' => 'imp' ),
	'dsm' => array( 'title' => 'Datenschutzhinweise für Mieter', 'page' => $page_id( 'impressum/datenschutzhinweise-mieter' ), 'parent' => 'imp' ),
) );

// --- 5. Website-Titel und Suchmaschinen-Angaben
update_option( 'blogname', 'ServiceInvest' );
update_option( 'blogdescription', 'Asset Servicing und Partner-Fonds' );
$o = get_option( 'et_divi' );
$o['divi_seo_home_title']           = 'on';
$o['divi_seo_home_titletext']       = 'ServiceInvest – Asset Servicing und Partner-Fonds';
$o['divi_seo_home_description']     = 'on';
$o['divi_seo_home_descriptiontext'] = 'Asset Servicing und Partner-Fonds für Asset Manager und KVGen von Immobilienvermögen – KVG-Insourcing im Middle und Back Office.';
update_option( 'et_divi', $o );

// --- 6. Startseite und Fußzeile
$home = get_page_by_path( 'startseite' );
if ( $home ) {
	wp_update_post( array( 'ID' => $home->ID, 'post_content' => wp_slash( file_get_contents( "$dir/home.txt" ) ) ) );
	WP_CLI::log( 'Startseite gesetzt (#' . $home->ID . ')' );
}
foreach ( get_posts( array( 'post_type' => 'et_footer_layout', 'post_status' => 'any', 'numberposts' => -1 ) ) as $f ) {
	if ( false !== strpos( $f->post_content, 'ip-footer' ) ) {
		wp_update_post( array( 'ID' => $f->ID, 'post_content' => wp_slash( file_get_contents( "$dir/footer.txt" ) ) ) );
		WP_CLI::log( 'Fußzeile gesetzt (#' . $f->ID . ')' );
	}
}
if ( function_exists( 'PLL' ) ) { PLL()->model->clean_languages_cache(); }
wp_cache_flush();
WP_CLI::success( 'ServiceInvest-Grundeinrichtung abgeschlossen.' );
