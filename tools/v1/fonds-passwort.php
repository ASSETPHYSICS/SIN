<?php
/**
 * Passwort eines Fonds setzen (nur der Hash wird gespeichert). Das Passwort kommt über die Standardeingabe, damit es nicht
 * in der Shell-Historie oder Prozessliste steht.
 *
 *   printf '%s' "<passwort>" | wp eval-file tools/v1/fonds-passwort.php <slug>
 *   Slugs: debeka-immobilien-invest-i, tas-wohnen-1
 */

defined( 'ABSPATH' ) || exit;

$slug = (string) ( $args[0] ?? '' );
if ( ! sin_fund_by_slug( $slug ) ) {
	WP_CLI::error( 'Unbekannter Fonds. Möglich: ' . implode( ', ', wp_list_pluck( sin_funds(), 'slug' ) ) );
}
$pw = rtrim( (string) stream_get_contents( STDIN ), "\r\n" );
if ( strlen( $pw ) < 10 ) {
	WP_CLI::error( 'Das Passwort muss mindestens 10 Zeichen haben.' );
}
sin_fonds_set_password( $slug, $pw );
WP_CLI::success( "Passwort für $slug gesetzt." );
