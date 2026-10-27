<?php
/**
 * Plugin Name: 2IP Hardening
 * Description: Sicherheits-Grundeinstellungen (Must-Use-Plugin): keine Benutzer-Enumeration, kein XML-RPC, Sicherheits-Header, kein Datei-Editor.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Theme- und Plugin-Editor im Backend abschalten (falls nicht schon in der wp-config.php geschehen).
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// XML-RPC wird nicht benötigt.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );

// WordPress-Version nicht preisgeben.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// Benutzerliste über die REST-API nur für angemeldete Benutzer.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
	}
	return $endpoints;
} );

// Autoren-Archive und ?author=N leiten auf die Startseite (verhindert Auslesen der Benutzernamen).
add_action( 'template_redirect', function () {
	if ( is_author() || ( isset( $_GET['author'] ) && ! is_user_logged_in() ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );
add_filter( 'author_link', function () {
	return home_url( '/' );
} );

// Keine Hinweise, ob Benutzername oder Passwort falsch war.
add_filter( 'login_errors', function () {
	return 'Die Anmeldung ist fehlgeschlagen.';
} );

// Sicherheits-Header (ohne CSP, da Divi Inline-Code verwendet). Die .htaccess setzt sie auf IONOS
// nur für statische Dateien, PHP-Antworten bekommen sie nur von hier.
function hardening_security_headers() {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
	if ( is_ssl() ) {
		// Ohne includeSubDomains: nicht alle Subdomains sind sicher auf HTTPS.
		header( 'Strict-Transport-Security: max-age=31536000' );
	}
	header_remove( 'X-Powered-By' );
}
add_action( 'send_headers', 'hardening_security_headers' );
add_action( 'login_init', 'hardening_security_headers' );
add_action( 'admin_init', 'hardening_security_headers' );
