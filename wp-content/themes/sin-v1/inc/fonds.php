<?php
/**
 * Geschützte Fonds-Offenlegungen (HA 7), nach dem Vorbild von 2ip-anlegerzugang (2ip.de).
 *
 *   - Je Fonds ein Passwort, gespeichert nur als Hash (Option sin_fonds_passwords). Die Prüfung macht nur der Server
 *     (admin-ajax.php?action=sin_fonds); im HTML und JavaScript steht kein Passwort.
 *   - Nach richtiger Eingabe: signiertes Sitzungs-Cookie (HttpOnly, Secure, SameSite=Strict, 2 Stunden) nur für diesen Fonds.
 *     Es ist das einzige Cookie der Website und entsteht nur bei Nutzung des geschützten Bereichs.
 *   - Dokumente liegen außerhalb des Webroots in SIN_FONDS_DIR/<slug>/ und werden nur über ?sin_doc=<slug>/<datei> nach
 *     Prüfung der Sitzung ausgeliefert (X-Robots-Tag: noindex, Cache-Control: private, no-store). Ausnahme wie auf 2ip.de: die
 *     bisherigen öffentlichen Adressen unter /wp-content/uploads/ liefern die aktuelle Fassung (sin_fonds_legacy_map()).
 *   - Fehlversuche: höchstens SIN_FONDS_MAX_FAILS je IP in 15 Minuten; Protokoll SIN_FONDS_LOG (IP gekürzt, keine Passwörter).
 * Passwörter setzen: Einstellungen → Fonds-Offenlegungen (Administratoren) oder
 *   printf '%s' "<passwort>" | wp eval-file tools/v1/fonds-passwort.php <slug>
 *
 * Konfiguration (wp-config.php): SIN_FONDS_DIR (Standard <Home>/geschuetzt/sin), SIN_FONDS_LOG (Standard
 * <Home>/logs/sin-fonds.log), SIN_FONDS_MAX_FAILS (Standard 5).
 */

defined( 'ABSPATH' ) || exit;

foreach ( array(
	'SIN_FONDS_DIR'       => dirname( ABSPATH, 2 ) . '/geschuetzt/sin',
	'SIN_FONDS_LOG'       => dirname( ABSPATH, 2 ) . '/logs/sin-fonds.log',
	'SIN_FONDS_MAX_FAILS' => 5,
) as $sin_c => $sin_v ) {
	if ( ! defined( $sin_c ) ) {
		define( $sin_c, $sin_v );
	}
}
unset( $sin_c, $sin_v );

const SIN_FONDS_COOKIE  = 'sin_fonds';
const SIN_FONDS_SESSION = 2 * HOUR_IN_SECONDS;

/** Fonds in der Reihenfolge der Seite Anlegerzugang (data-fund-toggle="0", "1"); Slug = Ordner = bisherige Fondsseite. */
function sin_funds(): array {
	return array(
		0 => array( 'slug' => 'debeka-immobilien-invest-i', 'name' => 'Debeka Immobilien Invest I' ),
		1 => array( 'slug' => 'tas-wohnen-1', 'name' => 'TAS Wohnen I' ),
	);
}

function sin_fund_by_slug( string $slug ): ?array {
	foreach ( sin_funds() as $f ) {
		if ( $f['slug'] === $slug ) {
			return $f;
		}
	}
	return null;
}

function sin_fonds_texts( string $lang ): array {
	return array(
		'de' => array(
			'wrong'  => 'Das Passwort ist nicht korrekt.',
			'locked' => 'Zu viele Fehlversuche. Bitte versuchen Sie es in 15 Minuten erneut.',
			'error'  => 'Ein Fehler ist aufgetreten. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
		),
		'en' => array(
			'wrong'  => 'The password is not correct.',
			'locked' => 'Too many failed attempts. Please try again in 15 minutes.',
			'error'  => 'An error has occurred. Please reload the page and try again.',
		),
	)[ 'en' === $lang ? 'en' : 'de' ];
}

/** Seite Anlegerzugang: Endpunkt eintragen (data-endpoint der Fondsliste). */
function sin_fonds_prepare( string $html ): string {
	if ( false === strpos( $html, 'data-funds data-endpoint=""' ) ) {
		return $html;
	}
	return str_replace( 'data-funds data-endpoint=""', 'data-funds data-endpoint="' . esc_attr( admin_url( 'admin-ajax.php' ) ) . '"', $html );
}

/* --- Passwörter --------------------------------------------------------------------------------------------------- */

function sin_fonds_set_password( string $slug, string $password ): void {
	$all          = (array) get_option( 'sin_fonds_passwords', array() );
	$all[ $slug ] = wp_hash_password( $password );
	update_option( 'sin_fonds_passwords', $all, false );
}

function sin_fonds_check_password( string $slug, string $password ): bool {
	$all = (array) get_option( 'sin_fonds_passwords', array() );
	return '' !== $password && isset( $all[ $slug ] ) && wp_check_password( $password, $all[ $slug ] );
}

/* --- Sitzung: Cookie "<base64 json>.<hmac>", Inhalt {"f":[slugs],"e":ablauf} ---------------------------------------- */

function sin_fonds_sign( string $data ): string {
	return hash_hmac( 'sha256', $data, wp_salt( 'auth' ) . '|sin_fonds' );
}

function sin_fonds_session(): array {
	$raw = isset( $_COOKIE[ SIN_FONDS_COOKIE ] ) ? (string) $_COOKIE[ SIN_FONDS_COOKIE ] : '';
	if ( ! preg_match( '#^([A-Za-z0-9_-]+)\.([0-9a-f]{64})$#', $raw, $m ) || ! hash_equals( sin_fonds_sign( $m[1] ), $m[2] ) ) {
		return array();
	}
	$data = json_decode( (string) base64_decode( strtr( $m[1], '-_', '+/' ) ), true );
	if ( ! is_array( $data ) || (int) ( $data['e'] ?? 0 ) < time() ) {
		return array();
	}
	return array_values( array_filter( (array) ( $data['f'] ?? array() ), 'is_string' ) );
}

function sin_fonds_session_add( string $slug ): void {
	$funds = array_values( array_unique( array_merge( sin_fonds_session(), array( $slug ) ) ) );
	$data  = rtrim( strtr( base64_encode( wp_json_encode( array( 'f' => $funds, 'e' => time() + SIN_FONDS_SESSION ) ) ), '+/', '-_' ), '=' );
	setcookie( SIN_FONDS_COOKIE, $data . '.' . sin_fonds_sign( $data ), array(
		'expires'  => 0, // Sitzungs-Cookie; die Gültigkeit (2 Stunden) steckt im signierten Inhalt
		'path'     => '/',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Strict',
	) );
}

function sin_fonds_can( string $slug ): bool {
	return in_array( $slug, sin_fonds_session(), true ) || current_user_can( 'manage_options' );
}

/* --- Dokumente: SIN_FONDS_DIR/<slug>/<datei>; Titel in Option sin_fonds_titles ("<slug>/<datei>" => {de, en, order}) --- */

function sin_fonds_valid_name( string $name ): bool {
	return '' !== $name && basename( $name ) === $name && '.' !== $name[0] && (bool) preg_match( '#^[A-Za-z0-9._ ()+-]+\.pdf$#i', $name );
}

function sin_fonds_docs( string $slug, string $lang ): array {
	$dir = trailingslashit( SIN_FONDS_DIR ) . $slug;
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$titles = (array) get_option( 'sin_fonds_titles', array() );
	$out    = array();
	foreach ( (array) scandir( $dir ) as $name ) {
		if ( ! sin_fonds_valid_name( (string) $name ) || ! is_file( "$dir/$name" ) ) {
			continue;
		}
		$t     = (array) ( $titles[ "$slug/$name" ] ?? array() );
		$out[] = array(
			'title' => (string) ( $t[ $lang ] ?? $t['de'] ?? pathinfo( $name, PATHINFO_FILENAME ) ),
			'order' => (int) ( $t['order'] ?? 0 ),
			'url'   => add_query_arg( 'sin_doc', rawurlencode( "$slug/$name" ), home_url( '/' ) ),
		);
	}
	usort( $out, fn( $a, $b ) => array( $a['order'], $a['title'] ) <=> array( $b['order'], $b['title'] ) );
	return array_map( fn( $d ) => array( 'title' => $d['title'], 'url' => $d['url'] ), $out );
}

/**
 * Bisherige öffentliche Adressen der Offenlegungen (Links in Unterlagen, E-Mails, bei Anlegern) liefern die aktuelle Fassung
 * aus SIN_FONDS_DIR ohne Passwort aus, wie auf 2ip.de: nicht für Suchmaschinen (X-Robots-Tag noindex), nicht in der Sitemap,
 * nicht auf der Website verlinkt. Pfad unter wp-content/uploads/ => "<slug>/<datei>".
 */
function sin_fonds_legacy_map(): array {
	return array(
		'2025/08/Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.-10-SFDR_Art_8-c-final.pdf' => 'debeka-immobilien-invest-i/Debeka-Immobilien-Invest-I_Offenlegung-Art-10-SFDR_DE.pdf',
		'2025/08/Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.10-SFDR-english.pdf'      => 'debeka-immobilien-invest-i/Debeka-Immobilien-Invest-I_Offenlegung-Art-10-SFDR_EN.pdf',
		'2025/04/2024-06-19-VOe_Internetseite-Art.-10-SFDR-TAS-Wohnen-I-c.pdf'               => 'tas-wohnen-1/2024-06-19-VOe_Internetseite-Art.-10-SFDR-TAS-Wohnen-I-c.pdf',
		'2025/04/2024-04-19-website-disclosure-Art.-10-SFDR-TAS-Wohnen-I-c.pdf'              => 'tas-wohnen-1/2024-04-19-website-disclosure-Art.-10-SFDR-TAS-Wohnen-I-c.pdf',
	);
}

/** Dokument ausliefern: über ?sin_doc= nur mit Sitzung für den Fonds, sonst 404 (kein Hinweis, ob es die Datei gibt). */
add_action( 'template_redirect', function () {
	$legacy = sin_fonds_legacy_map();
	$upload = preg_match( '#^wp-content/uploads/(.+)$#', sin_request_path(), $u ) ? $u[1] : '';
	if ( '' !== $upload && isset( $legacy[ $upload ] ) && is_file( trailingslashit( SIN_FONDS_DIR ) . $legacy[ $upload ] ) ) {
		sin_fonds_log( 'bisherige-adresse', $legacy[ $upload ] );
		sin_fonds_send( trailingslashit( SIN_FONDS_DIR ) . $legacy[ $upload ] );
	}
	if ( ! isset( $_GET['sin_doc'] ) ) {
		return;
	}
	$key = wp_unslash( (string) $_GET['sin_doc'] );
	$ok  = preg_match( '#^([a-z0-9-]+)/([^/]+)$#', $key, $m ) && sin_fund_by_slug( $m[1] ) && sin_fonds_valid_name( $m[2] ) && sin_fonds_can( $m[1] );
	$path = trailingslashit( SIN_FONDS_DIR ) . $key;
	header( 'X-Robots-Tag: noindex, nofollow' );
	if ( ! $ok || ! is_file( $path ) ) {
		sin_fonds_log( 'download-verweigert', $key );
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}
	sin_fonds_send( $path );
}, 0 );

/** PDF aus SIN_FONDS_DIR senden und die Anfrage beenden. */
function sin_fonds_send( string $path ): never {
	status_header( 200 );   // WordPress hat Adressen unter /wp-content/uploads/ schon als 404 eingestuft
	header_remove( 'Content-Security-Policy' );   // object-src 'none' würde den PDF-Viewer des Browsers blockieren
	nocache_headers();
	header( 'Cache-Control: private, no-store' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Type: application/pdf' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', basename( $path ) ) . '"' );
	readfile( $path );
	exit;
}

/* --- AJAX ---------------------------------------------------------------------------------------------------------- */

function sin_fonds_lang(): string {
	return isset( $_POST['lang'] ) && 'en' === $_POST['lang'] ? 'en' : 'de'; // phpcs:ignore WordPress.Security.NonceVerification
}

function sin_fonds_ajax(): void {
	nocache_headers();
	header( 'X-Robots-Tag: noindex' );
	$t = sin_fonds_texts( sin_fonds_lang() );
	if ( sin_fonds_locked() ) {
		sin_fonds_log( 'gesperrt', '' );
		wp_send_json( array( 'ok' => false, 'message' => $t['locked'] ) );
	}
	$funds = sin_funds();
	$i     = isset( $_POST['fund'] ) ? (int) $_POST['fund'] : -1; // phpcs:ignore WordPress.Security.NonceVerification
	$pw    = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! isset( $funds[ $i ] ) ) {
		wp_send_json( array( 'ok' => false, 'message' => $t['error'] ) );
	}
	$slug = $funds[ $i ]['slug'];
	if ( ! sin_fonds_check_password( $slug, $pw ) ) {
		sin_fonds_fail( $slug );
		wp_send_json( array( 'ok' => false, 'message' => $t['wrong'] ) );
	}
	sin_fonds_session_add( $slug );
	sin_fonds_log( 'ok', $slug );
	wp_send_json( array( 'ok' => true, 'docs' => sin_fonds_docs( $slug, sin_fonds_lang() ) ) );
}
add_action( 'wp_ajax_sin_fonds', 'sin_fonds_ajax' );
add_action( 'wp_ajax_nopriv_sin_fonds', 'sin_fonds_ajax' );

/** Beim Laden der Seite: Fonds mit gültiger Sitzung und ihre Dokumente. */
function sin_fonds_status_ajax(): void {
	nocache_headers();
	header( 'X-Robots-Tag: noindex' );
	$open = array();
	foreach ( sin_funds() as $i => $f ) {
		if ( in_array( $f['slug'], sin_fonds_session(), true ) ) {
			$open[ $i ] = sin_fonds_docs( $f['slug'], sin_fonds_lang() );
		}
	}
	wp_send_json( array( 'ok' => true, 'open' => (object) $open ) );
}
add_action( 'wp_ajax_sin_fonds_status', 'sin_fonds_status_ajax' );
add_action( 'wp_ajax_nopriv_sin_fonds_status', 'sin_fonds_status_ajax' );

/* --- Rate-Limit und Protokoll ---------------------------------------------------------------------------------------- */

function sin_fonds_ip(): string {
	$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
}

function sin_fonds_locked(): bool {
	return (int) get_transient( 'sin_fonds_fail_' . md5( sin_fonds_ip() ) ) >= (int) SIN_FONDS_MAX_FAILS;
}

function sin_fonds_fail( string $slug ): void {
	$key = 'sin_fonds_fail_' . md5( sin_fonds_ip() );
	set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
	sin_fonds_log( 'falsch', $slug );
}

/** Eine Zeile je Ereignis; IP gekürzt (IPv4: zwei Blöcke, IPv6: drei Blöcke), keine Passwörter. */
function sin_fonds_log( string $event, string $detail ): void {
	$ip  = sin_fonds_ip();
	$ip  = str_contains( $ip, ':' ) ? implode( ':', array_slice( explode( ':', $ip ), 0, 3 ) ) . '::' : preg_replace( '#\.\d+\.\d+$#', '.x.x', $ip );
	$dir = dirname( SIN_FONDS_LOG );
	if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
		return;
	}
	file_put_contents( SIN_FONDS_LOG, sprintf( "%s\t%s\t%s\t%s\n", wp_date( 'Y-m-d H:i:s' ), $event, $ip, str_replace( array( "\t", "\n" ), ' ', $detail ) ), FILE_APPEND | LOCK_EX );
}

/** Protokoll: Einträge älter als 30 Tage täglich löschen (Speicherdauer wie auf 2ip.de). */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'sin_fonds_log_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sin_fonds_log_cleanup' );
	}
} );

add_action( 'sin_fonds_log_cleanup', function () {
	if ( ! is_file( SIN_FONDS_LOG ) ) {
		return;
	}
	$cut  = wp_date( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
	$keep = array_filter( (array) file( SIN_FONDS_LOG ), fn( $l ) => strncmp( (string) $l, $cut, 19 ) >= 0 );
	file_put_contents( SIN_FONDS_LOG, implode( '', $keep ), LOCK_EX );
} );

/* --- Backend: Einstellungen → Fonds-Offenlegungen (nur Administratoren) -------------------------------------------- */

add_action( 'admin_menu', function () {
	add_options_page( 'Fonds-Offenlegungen', 'Fonds-Offenlegungen', 'manage_options', 'sin-fonds', 'sin_fonds_admin_page' );
} );

function sin_fonds_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$notice = '';
	if ( isset( $_POST['sin_fonds_slug'] ) && check_admin_referer( 'sin_fonds_pw' ) ) {
		$slug = sanitize_key( wp_unslash( $_POST['sin_fonds_slug'] ) );
		$pw   = (string) wp_unslash( $_POST['sin_fonds_pw'] ?? '' );
		if ( sin_fund_by_slug( $slug ) && strlen( $pw ) >= 10 ) {
			sin_fonds_set_password( $slug, $pw );
			sin_fonds_log( 'passwort-geaendert', $slug . ' durch Benutzer ' . get_current_user_id() );
			$notice = '<div class="notice notice-success"><p>Passwort für ' . esc_html( sin_fund_by_slug( $slug )['name'] ) . ' gespeichert. Bisherige Sitzungen bleiben bis zu 2 Stunden gültig.</p></div>';
		} else {
			$notice = '<div class="notice notice-error"><p>Das Passwort muss mindestens 10 Zeichen haben.</p></div>';
		}
	}
	$set = (array) get_option( 'sin_fonds_passwords', array() );
	echo '<div class="wrap"><h1>Fonds-Offenlegungen</h1>' . $notice; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p>Je Fonds ein Passwort für die Seite <a href="' . esc_url( home_url( '/anlegerzugang/' ) ) . '">Anlegerzugang</a>. Gespeichert wird nur ein Hash. Dokumente liegen auf dem Server in <code>' . esc_html( SIN_FONDS_DIR ) . '/&lt;Fonds&gt;/</code> (außerhalb des Webroots).</p>';
	foreach ( sin_funds() as $f ) {
		echo '<h2>' . esc_html( $f['name'] ) . '</h2><p>Passwort: ' . ( isset( $set[ $f['slug'] ] ) ? 'gesetzt' : '<strong>nicht gesetzt</strong>' ) . '</p><ul>';
		foreach ( sin_fonds_docs( $f['slug'], 'de' ) as $d ) {
			echo '<li><a href="' . esc_url( $d['url'] ) . '">' . esc_html( $d['title'] ) . '</a></li>';
		}
		echo '</ul><form method="post">';
		wp_nonce_field( 'sin_fonds_pw' );
		echo '<input type="hidden" name="sin_fonds_slug" value="' . esc_attr( $f['slug'] ) . '"><input type="password" name="sin_fonds_pw" autocomplete="new-password" minlength="10" required> ';
		submit_button( 'Neues Passwort speichern', 'secondary', 'submit', false );
		echo '</form>';
	}
	echo '</div>';
}
