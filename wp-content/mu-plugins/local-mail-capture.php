<?php
/**
 * Plugin Name: Lokal – Mails abfangen
 * Description: NUR lokal (wird nicht deployt): schreibt Mails nach wp-content/mail-capture.log statt sie zu senden.
 */
if ( 'local' !== wp_get_environment_type() ) {
	return;
}
add_filter( 'pre_wp_mail', function ( $r, $atts ) {
	$from = apply_filters( 'wp_mail_from_name', '' ) . ' <' . apply_filters( 'wp_mail_from', '' ) . '>';
	file_put_contents( WP_CONTENT_DIR . '/mail-capture.log', "=== " . gmdate( 'c' ) . "\nFrom: $from\nTo: " . implode( ',', (array) $atts['to'] ) . "\nSubject: {$atts['subject']}\nHeaders: " . implode( ' | ', (array) $atts['headers'] ) . "\n\n{$atts['message']}\n\n", FILE_APPEND );
	return true;
}, 10, 2 );
