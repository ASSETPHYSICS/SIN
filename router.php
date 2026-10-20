<?php
/**
 * Router für den eingebauten PHP-Server – NUR für die lokale Entwicklung.
 *
 * Start:  php -S 127.0.0.1:8080 router.php
 *
 * Liefert vorhandene Dateien aus, leitet alles andere an WordPress und
 * verhindert Zugriffe außerhalb des Projektordners sowie auf sensible Dateien.
 */
$root = realpath( __DIR__ );
$path = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

if ( false !== strpos( $path, "\0" ) ) {
	http_response_code( 400 );
	exit;
}

$real = realpath( $root . $path );
if ( false !== $real && $real !== $root && 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
	http_response_code( 403 );
	exit;
}

// Sensible Dateien nie ausliefern.
if ( preg_match( '#(^|/)\.[^/]|\.(sql|gz|zip|bak|log|sh|env)$#i', $path ) ) {
	http_response_code( 403 );
	exit;
}

if ( false !== $real && is_file( $real ) ) {
	return false; // Statische Datei oder PHP-Skript direkt ausliefern.
}

if ( false !== $real && is_dir( $real ) && is_file( $real . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
	require $real . '/index.php';
	return;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
