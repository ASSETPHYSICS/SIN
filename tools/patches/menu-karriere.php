<?php
/**
 * Hauptmenü: "Karriere" verlinkt auf die Stellenseite der Gruppe (extern, neuer Tab).
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Pfad>/menu-karriere.php
 * Danach tools/i18n/import.php ausführen, damit das englische Menü ("Careers") folgt.
 * Idempotent.
 */
$url  = 'https://www.2ig.de/jobs/';
$menu = wp_get_nav_menu_object( 'menu' );
if ( ! $menu ) { WP_CLI::error( 'Menü "menu" nicht gefunden.' ); }

$item = null;
foreach ( wp_get_nav_menu_items( $menu->term_id ) ?: array() as $it ) {
	if ( 'Karriere' === html_entity_decode( $it->title, ENT_QUOTES, 'UTF-8' ) ) { $item = $it; break; }
}
if ( ! $item ) { WP_CLI::error( 'Menüeintrag "Karriere" nicht gefunden.' ); }

if ( 'custom' === $item->type && $url === $item->url && '_blank' === $item->target ) {
	WP_CLI::success( 'Nichts zu tun (bereits angewendet).' );
	return;
}

wp_update_nav_menu_item( $menu->term_id, $item->ID, array(
	'menu-item-title'     => 'Karriere',
	'menu-item-type'      => 'custom',
	'menu-item-url'       => $url,
	'menu-item-target'    => '_blank',
	'menu-item-xfn'       => 'noopener',
	'menu-item-parent-id' => (int) $item->menu_item_parent,
	'menu-item-position'  => $item->menu_order,
	'menu-item-classes'   => implode( ' ', array_filter( (array) $item->classes ) ),
	'menu-item-status'    => 'publish',
) );
WP_CLI::success( 'Karriere verlinkt auf ' . $url );
