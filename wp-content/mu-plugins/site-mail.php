<?php
/**
 * Plugin Name: Site Mail
 * Description: Einheitlicher Mailversand (Must-Use-Plugin): Absender contact@2ig.de mit Alias je Seite, Versand über Amazon SES (SMTP). Gleiche Datei auf 2ip, 2ig, sin und acb.
 * Version: 1.1.0
 *
 * Konfiguration in der wp-config.php (Alias) und in ~/config/mail-smtp.php (Zugangsdaten, für alle Seiten gemeinsam):
 *   SITE_MAIL_FROM_NAME   Alias vor der Absenderadresse, z. B. 'Institutional Investment Partners (2IP)'
 *   SITE_MAIL_FROM        Absenderadresse (Standard contact@2ig.de)
 *   SITE_MAIL_SMTP_HOST / _PORT / _USER / _PASS   SES-SMTP-Zugang
 * Fehlen die Zugangsdaten (z. B. lokal), bleibt es beim Versand über PHP mail().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SITE_MAIL_FROM' ) ) {
	define( 'SITE_MAIL_FROM', 'contact@2ig.de' );
}

// Absender für alle Mails festlegen, auch wenn ein Plugin einen eigenen From-Header mitgibt.
add_filter( 'wp_mail_from', function () {
	return SITE_MAIL_FROM;
}, PHP_INT_MAX );

add_filter( 'wp_mail_from_name', function ( $name ) {
	return defined( 'SITE_MAIL_FROM_NAME' ) ? SITE_MAIL_FROM_NAME : $name;
}, PHP_INT_MAX );

// Versand über SES statt über das lokale mail() des Webspace (dort wäre der Absender sh-…@eu.hosting-webspace.io).
add_action( 'phpmailer_init', function ( $phpmailer ) {
	if ( ! defined( 'SITE_MAIL_SMTP_USER' ) || ! defined( 'SITE_MAIL_SMTP_PASS' ) || '' === SITE_MAIL_SMTP_PASS ) {
		return;
	}
	$phpmailer->isSMTP();
	$phpmailer->Host       = defined( 'SITE_MAIL_SMTP_HOST' ) ? SITE_MAIL_SMTP_HOST : 'email-smtp.eu-central-1.amazonaws.com';
	$phpmailer->Port       = defined( 'SITE_MAIL_SMTP_PORT' ) ? (int) SITE_MAIL_SMTP_PORT : 587;
	$phpmailer->SMTPSecure = 'tls';
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = SITE_MAIL_SMTP_USER;
	$phpmailer->Password   = SITE_MAIL_SMTP_PASS;
	$phpmailer->Sender     = SITE_MAIL_FROM;
	$phpmailer->Timeout    = 15;
}, PHP_INT_MAX );

/*
 * Protokoll ~/logs/site-mail.log (außerhalb der Webroots, für alle Seiten gemeinsam): eine Zeile je Mail mit Seite,
 * Versandweg, Empfänger und Ergebnis. Keine Betreffzeilen oder Inhalte; externe Empfänger nur gekürzt (m***@example.de).
 * Fehlt der Ordner (z. B. lokal), gehen nur Fehlschläge ins PHP-Fehlerprotokoll.
 */
function site_mail_log( $to, $result ) {
	$internal = array( '2ig.de', '2ip.de', 'serviceinvest.de', 'acb-gmbh.de' );
	$masked   = array();
	foreach ( (array) $to as $address ) {
		$parts    = explode( '@', (string) $address, 2 );
		$domain   = strtolower( $parts[1] ?? '' );
		$masked[] = in_array( $domain, $internal, true ) ? $address : substr( $parts[0], 0, 1 ) . '***@' . $domain;
	}
	$smtp = defined( 'SITE_MAIL_SMTP_PASS' ) && '' !== SITE_MAIL_SMTP_PASS;
	$line = sprintf( "%s %s %s an %s: %s\n", wp_date( 'Y-m-d H:i:s' ), wp_parse_url( home_url(), PHP_URL_HOST ),
		$smtp ? 'SES' : 'mail()', implode( ', ', $masked ), str_replace( array( "\r", "\n" ), ' ', $result ) );

	$dir = dirname( ABSPATH, 2 ) . '/logs';
	if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
		if ( 'OK' !== $result ) {
			error_log( 'site-mail: ' . trim( $line ) );
		}
		return;
	}
	$file = $dir . '/site-mail.log';
	if ( is_file( $file ) && filesize( $file ) > 1048576 ) {
		rename( $file, $file . '.1' ); // höchstens ca. 2 MB: aktuelle Datei + eine Vorgängerin
	}
	$new = ! is_file( $file );
	file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
	if ( $new ) {
		chmod( $file, 0600 );
	}
}

add_action( 'wp_mail_succeeded', function ( $mail ) {
	site_mail_log( $mail['to'] ?? array(), 'OK' );
} );

add_action( 'wp_mail_failed', function ( $error ) {
	$data = $error->get_error_data();
	site_mail_log( $data['to'] ?? array(), 'FEHLER ' . $error->get_error_message() );
} );
