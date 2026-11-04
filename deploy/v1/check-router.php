<?php
/**
 * Router für die Prüfung VOR dem Umschalten (deploy/v1/deploy.sh check): PHP-Server auf dem Webspace, Anfragen mit
 * Host: www.serviceinvest.de und X-Forwarded-Proto: https. Wie Apache mit der Live-.htaccess: Dateien direkt, sonst
 * WordPress; Sperren wie dort (sensible Dateien, Fehlerseiten-Vorlagen, README.md).
 */
$root = getcwd();
$path = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
if ( 'https' === ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) ) {
	$_SERVER['HTTPS'] = 'on';
}
if ( false !== strpos( $path, "\0" ) || preg_match( '#(^|/)\.[^/]|\.(sql|gz|zip|bak|log|sh|md)$|^/wp-config|^/xmlrpc\.php$|/fallback-[a-z]+\.html$#i', $path ) ) {
	http_response_code( 403 );
	exit;
}
$real = realpath( $root . $path );
if ( false !== $real && 0 !== strpos( $real, $root ) ) {
	http_response_code( 403 );
	exit;
}
if ( false !== $real && is_file( $real ) ) {
	if ( str_ends_with( $real, '.avif' ) ) {
		header( 'Content-Type: image/avif' );   // der eingebaute Server kennt AVIF nicht
		readfile( $real );
		return true;
	}
	return false;
}
if ( false !== $real && is_dir( $real ) && is_file( $real . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
	require $real . '/index.php';
	return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
