<?php
/**
 * Sicherheits- und Cache-Header der öffentlichen Seiten (HA 9, 12). Das mu-Plugin sin-hardening.php setzt die Grundwerte
 * für alle Antworten (auch Backend, HSTS); hier kommen die strengeren Werte der HA dazu. Auf IONOS kommen Header aus der
 * .htaccess bei PHP-Antworten nicht an; deshalb setzt sie PHP.
 *
 * CSP: nur eigene Quellen. style-src braucht 'unsafe-inline' (Stil-Attribute und <style> des Pakets), Skripte nur als Datei.
 * HSTS bleibt wie auf allen Seiten dieses Webspace ohne includeSubDomains.
 */

defined( 'ABSPATH' ) || exit;

function sin_csp(): string {
	return implode( '; ', array(
		"default-src 'self'",
		"img-src 'self' data:",
		"style-src 'self' 'unsafe-inline'",
		"script-src 'self'",
		"font-src 'self'",
		"connect-src 'self'",
		"form-action 'self'",
		"frame-ancestors 'none'",
		"base-uri 'self'",
		"object-src 'none'",
	) );
}

add_action( 'send_headers', function () {
	if ( is_admin() || headers_sent() ) {
		return;
	}
	header( 'Content-Security-Policy: ' . sin_csp() );
	header( 'X-Frame-Options: DENY' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()' );
	header( 'Cache-Control: no-cache' );   // HTML kurz (HA 9); Dateien unter /assets/ regelt die .htaccess
	if ( is_ssl() ) {
		// über HTTP ignoriert der Browser den Header und meldet das in der Konsole
		header( 'Cross-Origin-Opener-Policy: same-origin' );
	}
}, 20 );
