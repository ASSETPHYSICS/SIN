<?php
/**
 * Baut die englischen Seiten aus dem Übersetzungsspeicher (content/i18n/en/*.json) und verknüpft sie mit Polylang.
 * Erstellt außerdem das englische Menü und den Sprachumschalter. Braucht keinen DeepL-Schlüssel.
 *
 * Aufruf im WordPress-Stammverzeichnis:  php wp-cli.phar eval-file <Repo>/tools/i18n/import.php
 * Idempotent: vorhandene englische Seiten werden aktualisiert, nicht doppelt angelegt.
 */
if ( ! function_exists( 'pll_set_post_language' ) ) { WP_CLI::error( 'Polylang ist nicht aktiv.' ); }

$cfg  = require __DIR__ . '/config.php';
$root = dirname( __DIR__, 2 );
$dir  = $root . '/content/i18n/en';

$find_de = static function ( $slug ) {
	$p = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'lang' => 'de' ) );
	return $p ? $p[0] : null;
};

// Meta-Felder, die nicht auf die Übersetzung kopiert werden (Caches, Bearbeitungsstatus, Übersetzungsdaten).
$skip_meta = '/^(_edit_|_divi_dynamic_assets|_et_builder_post_features_cache|_et_pb_first_image|_wp_old_|_thumbnail_id$)/';

// --- 1. Seiten anlegen bzw. aktualisieren (ohne Inhalt), damit alle Ziel-URLs feststehen.
$pairs = array();
foreach ( $cfg['pages'] as $de_slug => $pc ) {
	$de = $find_de( $de_slug );
	$tm_file = $dir . '/' . $de_slug . '.json';
	if ( ! $de ) { WP_CLI::warning( 'Deutsche Seite fehlt: ' . $de_slug ); continue; }
	if ( ! file_exists( $tm_file ) ) { WP_CLI::warning( 'Keine Übersetzung für ' . $de_slug . ' (translate.php ausführen).' ); continue; }
	$tm = json_decode( file_get_contents( $tm_file ), true );

	$en_id  = pll_get_post( $de->ID, 'en' );
	$parent = $de->post_parent ? (int) pll_get_post( $de->post_parent, 'en' ) : 0;
	$data   = array(
		'post_type'      => 'page',
		'post_title'     => $tm['title']['en'] ?: $de->post_title,
		'post_name'      => $pc['slug'],
		'post_status'    => $pc['status'],
		'post_parent'    => $parent,
		'menu_order'     => $de->menu_order,
		'post_password'  => $de->post_password,
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
	);
	if ( $en_id ) {
		$data['ID'] = $en_id;
		wp_update_post( wp_slash( $data ) );
	} else {
		$data['post_content'] = '';
		$en_id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $en_id ) ) { WP_CLI::error( $de_slug . ': ' . $en_id->get_error_message() ); }
		pll_set_post_language( $en_id, 'en' );
		pll_save_post_translations( array_merge( pll_get_post_translations( $de->ID ), array( 'de' => $de->ID, 'en' => $en_id ) ) );
		WP_CLI::log( 'Angelegt: ' . $de_slug . ' -> ' . $pc['slug'] . ' (#' . $en_id . ')' );
	}
	pll_set_post_language( $en_id, 'en' );

	foreach ( get_post_meta( $de->ID ) as $k => $vals ) {
		if ( preg_match( $skip_meta, $k ) ) { continue; }
		delete_post_meta( $en_id, $k );
		foreach ( $vals as $v ) { add_post_meta( $en_id, $k, wp_slash( maybe_unserialize( $v ) ) ); }
	}
	$pairs[ $de_slug ] = array( 'de' => $de, 'en_id' => (int) $en_id, 'tm' => $tm );
}

// --- 2. Link-Zuordnung: deutsche Pfade -> englische Pfade (nur für übersetzte Seiten).
$path = static function ( $url ) { return (string) wp_parse_url( $url, PHP_URL_PATH ); };
$link_map = array();
foreach ( $pairs as $p ) {
	$link_map[ $path( get_permalink( $p['de'] ) ) ] = $path( get_permalink( $p['en_id'] ) );
}
uksort( $link_map, static function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );

$rewrite_links = static function ( $html ) use ( $link_map ) {
	return preg_replace_callback( '/href="([^"#?]*)([#?][^"]*)?"/', static function ( $m ) use ( $link_map ) {
		$p = $m[1];
		if ( '' === $p || preg_match( '#^(https?:|mailto:|tel:)#', $p ) ) { return $m[0]; }
		return isset( $link_map[ $p ] ) ? 'href="' . $link_map[ $p ] . ( $m[2] ?? '' ) . '"' : $m[0];
	}, $html );
};

// --- 3. Inhalte setzen: Textmodule aus dem Übersetzungsspeicher, alles andere (Layout, Klassen) unverändert.
foreach ( $pairs as $de_slug => $p ) {
	$map = array();
	foreach ( $p['tm']['segments'] as $s ) { if ( null !== $s['en'] ) { $map[ $s['id'] ] = $s['en']; } }
	$missing = 0;
	$walk = static function ( array $blocks ) use ( &$walk, $map, &$missing, $rewrite_links ) {
		foreach ( $blocks as &$b ) {
			if ( 'divi/text' === $b['blockName'] && isset( $b['attrs']['content']['innerContent']['desktop']['value'] ) ) {
				$html = $b['attrs']['content']['innerContent']['desktop']['value'];
				if ( '' !== trim( wp_strip_all_tags( $html ) ) ) {
					$id = sha1( $html );
					if ( isset( $map[ $id ] ) ) {
						$b['attrs']['content']['innerContent']['desktop']['value'] = $rewrite_links( $map[ $id ] );
					} else {
						$missing++;
					}
				}
			}
			if ( ! empty( $b['innerBlocks'] ) ) { $b['innerBlocks'] = $walk( $b['innerBlocks'] ); }
		}
		return $blocks;
	};
	$content = serialize_blocks( $walk( parse_blocks( $p['de']->post_content ) ) );
	wp_update_post( array( 'ID' => $p['en_id'], 'post_content' => wp_slash( $content ) ) );
	WP_CLI::log( sprintf( '%-18s -> %s%s', $de_slug, get_permalink( $p['en_id'] ), $missing ? '  (' . $missing . ' Abschnitte ohne Übersetzung, deutsch übernommen)' : '' ) );
}

// --- 4. Englisches Menü aus dem deutschen ableiten; Zuordnung für das Theme speichern.
$labels   = $cfg['menu_labels'] ?? array();
$de_menus = array();
foreach ( wp_get_nav_menus() as $m ) {
	if ( '(EN)' !== substr( $m->name, -4 ) ) { $de_menus[] = $m; }
}
$menu_map = array();
foreach ( $de_menus as $dm ) {
	$name  = $dm->name . ' (EN)';
	$en_m  = wp_get_nav_menu_object( $name );
	$en_id = $en_m ? $en_m->term_id : wp_create_nav_menu( $name );
	foreach ( wp_get_nav_menu_items( $en_id ) ?: array() as $old ) { wp_delete_post( $old->ID, true ); }

	$id_map = array();
	foreach ( wp_get_nav_menu_items( $dm->term_id ) ?: array() as $it ) {
		$title = html_entity_decode( $it->title, ENT_QUOTES, 'UTF-8' );
		$args  = array(
			'menu-item-title'     => $labels[ $title ] ?? $title,
			'menu-item-parent-id' => $id_map[ (int) $it->menu_item_parent ] ?? 0,
			'menu-item-position'  => $it->menu_order,
			'menu-item-classes'   => implode( ' ', array_filter( (array) $it->classes ) ),
			'menu-item-target'    => $it->target,
			'menu-item-status'    => 'publish',
		);
		if ( 'post_type' === $it->type ) {
			$target = (int) pll_get_post( $it->object_id, 'en' );
			$target = ( $target && 'publish' === get_post_status( $target ) ) ? $target : (int) $it->object_id;
			$args  += array( 'menu-item-type' => 'post_type', 'menu-item-object' => $it->object, 'menu-item-object-id' => $target );
		} elseif ( '#pll_switcher' === $it->url ) {
			continue;   // Sprachumschalter wird unten neu angelegt
		} else {
			$url = $it->url;
			$home_de = home_url( '/' );
			if ( '/' === $url || $home_de === $url ) {
				$url = pll_home_url( 'en' );
			} elseif ( 0 === strpos( $url, $home_de . '#' ) || 0 === strpos( $url, '/#' ) ) {
				$url = pll_home_url( 'en' ) . substr( $url, strpos( $url, '#' ) );
			}
			$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => $url );
		}
		$id_map[ (int) $it->ID ] = wp_update_nav_menu_item( $en_id, 0, $args );
	}
	$menu_map[ (int) $dm->term_id ] = (int) $en_id;
}
update_option( 'ip_menu_translations', $menu_map, false );

// --- 5. Sprachumschalter (Polylang) am Ende beider Hauptmenüs: zeigt jeweils die andere Sprache.
$switcher_opts = array( 'hide_if_no_translation' => 0, 'hide_current' => 1, 'force_home' => 0, 'show_flags' => 0, 'show_names' => 1, 'dropdown' => 0 );
foreach ( $menu_map as $de_id => $en_id ) {
	if ( empty( $cfg['switcher_menus'] ) || ! in_array( wp_get_nav_menu_object( $de_id )->slug, $cfg['switcher_menus'], true ) ) { continue; }
	foreach ( array( $de_id, $en_id ) as $mid ) {
		$exists = false;
		foreach ( wp_get_nav_menu_items( $mid ) ?: array() as $it ) { if ( '#pll_switcher' === $it->url ) { $exists = true; } }
		if ( ! $exists ) {
			$item = wp_update_nav_menu_item( $mid, 0, array(
				'menu-item-title' => __( 'Languages', 'polylang' ), 'menu-item-url' => '#pll_switcher',
				'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-position' => 999,
			) );
			update_post_meta( $item, '_pll_menu_item', $switcher_opts );
		}
	}
}

// Polylang speichert die Startseite je Sprache in der Sprachliste zwischen: neu aufbauen, damit /en/ greift.
PLL()->model->clean_languages_cache();
flush_rewrite_rules( false );

WP_CLI::success( count( $pairs ) . ' Seite(n) importiert, Menüs: ' . wp_json_encode( $menu_map ) );
