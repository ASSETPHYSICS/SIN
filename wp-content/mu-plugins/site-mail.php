<?php
/**
 * Plugin Name: Site Mail
 * Description: Einheitlicher Mailversand (Must-Use-Plugin): Absender contact@2ig.de mit Alias je Seite, Versand über Amazon SES (SMTP). Gleiche Datei auf 2ip, 2ig, sin und acb.
 * Version: 1.0.0
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

// Fehlschläge ins PHP-Fehlerprotokoll schreiben (nur Fehlertext und Empfänger, keine Inhalte).
add_action( 'wp_mail_failed', function ( $error ) {
	$data = $error->get_error_data();
	$to   = isset( $data['to'] ) ? implode( ', ', (array) $data['to'] ) : '';
	error_log( 'site-mail: Versand an ' . $to . ' fehlgeschlagen: ' . $error->get_error_message() );
} );
