<?php
/**
 * Kontakt- und Mieterformular (HA 6). site.js prüft im Browser wie das Paket und sendet per fetch (multipart/form-data) an
 * data-endpoint; bei Status 2xx erscheint die Danke-Ansicht, sonst die Meldung mit info@serviceinvest.de.
 *
 * Endpunkte: admin-ajax.php?action=sin_kontakt | sin_mieter (gleiche Domain, also connect-src 'self').
 * Empfänger: SIN_KONTAKT_TO und SIN_MIETER_TO (wp-config, Standard info@serviceinvest.de; das Postfach für Mieteranliegen
 * benennt ServiceInvest noch, HA 15). Absender contact@2ig.de mit Alias SITE_MAIL_FROM_NAME ("ServiceInvest") über das
 * mu-Plugin site-mail.php (Amazon SES eu-central-1). Reply-To ist die angegebene Adresse; keine Kopie an sie (sonst Spam-Relais).
 * Spamschutz ohne Drittanbieter: unsichtbares Honeypot-Feld, Mindestausfüllzeit (signierter Zeitstempel), höchstens
 * SIN_FORM_LIMIT Anfragen je IP-Adresse und Stunde (nur als Hash, eine Stunde lang). Gespeichert wird nichts; das
 * Mail-Protokoll (site-mail.php) enthält keine Inhalte.
 */

defined( 'ABSPATH' ) || exit;

const SIN_FORM_MIN_SECONDS = 3;
const SIN_FORM_MAX_AGE     = 2 * DAY_IN_SECONDS;
const SIN_FORM_LIMIT       = 5;

/** Werte des Feldes "anliegen" mit der Bezeichnung der deutschen Kontaktseite (leer erlaubt wie im Paket). */
function sin_kontakt_anliegen(): array {
	return array(
		''                       => '–',
		'partnerfonds'           => 'Partnerfonds auflegen',
		'leistungen'             => 'Einzelne Leistungen',
		'anleger-institutionell' => 'Institutioneller Anleger',
		'anleger-privat'         => 'Privatanleger',
		'mieter'                 => 'Für Mieter',
		'presse'                 => 'Presse',
		'sonstiges'              => 'Sonstiges',
	);
}

/** Werte des Feldes "art" im Mieterformular mit der deutschen Bezeichnung. */
function sin_mieter_arten(): array {
	return array(
		'erreichbarkeit' => 'Erreichbarkeit der Hausverwaltung',
		'instandhaltung' => 'Instandhaltung und Reparaturen',
		'abrechnung'     => 'Nebenkosten und Abrechnung',
		'sonstiges'      => 'Sonstiges',
	);
}

function sin_form_token( int $time ): string {
	return $time . '.' . substr( wp_hash( "sin_form|$time", 'nonce' ), 0, 20 );
}

/** Formulare für die Ausgabe vorbereiten: Endpunkt, Honeypot, Zeitstempel, Sprache. Sichtbar ändert sich nichts. */
function sin_formulare_prepare( string $html ): string {
	foreach ( array( 'k-form' => 'sin_kontakt', 'm-form' => 'sin_mieter' ) as $id => $action ) {
		if ( false === strpos( $html, 'id="' . $id . '"' ) ) {
			continue;
		}
		$endpoint = esc_attr( add_query_arg( 'action', $action, admin_url( 'admin-ajax.php' ) ) );
		$html     = preg_replace( '#(<form\b[^>]*\bid="' . $id . '"[^>]*\bdata-endpoint=")"#', '${1}' . $endpoint . '"', $html, 1 );
		$hidden   = '<div hidden aria-hidden="true"><label>Website <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label></div>'
			. '<input type="hidden" name="_ts" value="' . esc_attr( sin_form_token( time() ) ) . '">'
			. '<input type="hidden" name="sprache" value="' . esc_attr( sin_lang() ) . '">';
		$html     = preg_replace( '#(<form\b[^>]*\bid="' . $id . '".*?)(</form>)#s', '${1}' . $hidden . '${2}', $html, 1 );
	}
	return $html;
}

/** Antwort an site.js: nur der Status zählt. */
function sin_form_respond( int $status, string $code ): never {
	nocache_headers();
	wp_send_json( array( 'ok' => $status < 300, 'code' => $code ), $status );
	exit;
}

/** Eine Zeile Text aus $_POST: Steuerzeichen entfernen, Länge begrenzen. */
function sin_form_line( string $key, int $max ): string {
	$v = trim( (string) wp_unslash( $_POST[ $key ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- öffentliches Formular, Schutz siehe oben
	$v = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $v );
	return mb_substr( (string) $v, 0, $max );
}

/** Mehrzeiliger Text aus $_POST. */
function sin_form_text( string $key ): string {
	$v = trim( str_replace( "\r\n", "\n", (string) wp_unslash( $_POST[ $key ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return mb_substr( (string) preg_replace( '/[\x00-\x08\x0B-\x1F\x7F]+/u', '', $v ), 0, 10000 );
}

/** E-Mail-Prüfung wie im Browser (site.js) und zusätzlich is_email(). */
function sin_form_email_ok( string $mail ): bool {
	return (bool) preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/u', $mail ) && is_email( $mail );
}

/** Gemeinsame Prüfungen vor dem Auswerten: Methode, Honeypot, Zeit, Rate-Limit. Gibt den Schlüssel des Zählers zurück. */
function sin_form_guard(): string {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		sin_form_respond( 405, 'method' );
	}
	// Honeypot gefüllt oder zu schnell: wie Erfolg antworten, damit Bots nichts lernen; nichts senden.
	$ts  = (string) wp_unslash( $_POST['_ts'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$t   = (int) strtok( $ts, '.' );
	$age = time() - $t;
	if ( '' !== (string) ( $_POST['website'] ?? '' ) || ! hash_equals( sin_form_token( $t ), $ts ) || $age < SIN_FORM_MIN_SECONDS ) { // phpcs:ignore WordPress.Security.NonceVerification
		sin_form_respond( 200, 'ok' );
	}
	if ( $age > SIN_FORM_MAX_AGE ) {
		sin_form_respond( 400, 'expired' );   // Seite sehr lange offen: Meldung mit info@serviceinvest.de
	}
	$key = 'sin_form_' . substr( wp_hash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), 'nonce' ), 0, 24 );
	if ( (int) get_transient( $key ) >= SIN_FORM_LIMIT ) {
		sin_form_respond( 429, 'limit' );
	}
	return $key;
}

function sin_form_send( string $key, string $to, string $subject, array $lines, string $reply_to ): never {
	// 15 Minuten, passend zur Meldung „in einigen Minuten“ (wie auf 2ip.de)
	set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
	$sent = wp_mail( $to, $subject, implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $reply_to ) );
	sin_form_respond( $sent ? 200 : 500, $sent ? 'ok' : 'mail' );
}

function sin_form_lang_name(): string {
	return 'en' === sin_form_line( 'sprache', 2 ) ? 'Englisch' : 'Deutsch';
}

function sin_kontakt_handle(): void {
	$key       = sin_form_guard();
	$anliegen  = sin_form_line( 'anliegen', 40 );
	$name      = sin_form_line( 'name', 200 );
	$firma     = sin_form_line( 'unternehmen', 200 );
	$email     = sin_form_line( 'email', 254 );
	$telefon   = sin_form_line( 'telefon', 60 );
	$nachricht = sin_form_text( 'nachricht' );
	$list      = sin_kontakt_anliegen();
	if ( ! isset( $list[ $anliegen ] ) || '' === $name || ! sin_form_email_ok( $email ) || '' === $nachricht || empty( $_POST['datenschutz'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		sin_form_respond( 400, 'invalid' );
	}
	$to = defined( 'SIN_KONTAKT_TO' ) ? SIN_KONTAKT_TO : 'info@serviceinvest.de';
	sin_form_send( $key, $to, 'Kontaktanfrage serviceinvest.de: ' . ( '' !== $anliegen ? $list[ $anliegen ] : 'ohne Anliegen' ) . ' – ' . $name, array(
		'Neue Kontaktanfrage über www.serviceinvest.de',
		'',
		'Anliegen:     ' . $list[ $anliegen ],
		'Name:         ' . $name,
		'E-Mail:       ' . $email,
		'Unternehmen:  ' . ( '' !== $firma ? $firma : '–' ),
		'Telefon:      ' . ( '' !== $telefon ? $telefon : '–' ),
		'Seite:        ' . sin_form_lang_name(),
		'Datenschutz:  Hinweise gelesen (bestätigt)',
		'Eingang:      ' . wp_date( 'j. F Y, H:i' ) . ' Uhr',
		'',
		'Nachricht:',
		$nachricht,
		'',
		'-- ',
		'Antworten an den Absender: "Antworten" verwenden (Reply-To).',
	), $email );
}
add_action( 'wp_ajax_sin_kontakt', 'sin_kontakt_handle' );
add_action( 'wp_ajax_nopriv_sin_kontakt', 'sin_kontakt_handle' );

function sin_mieter_handle(): void {
	$key = sin_form_guard();
	$f   = array();
	foreach ( array( 'strasse' => 200, 'plz' => 20, 'ort' => 120, 'einheit' => 120, 'hausverwaltung' => 200, 'hv_ansprechpartner' => 200, 'art' => 40, 'name' => 200, 'email' => 254, 'telefon' => 60 ) as $k => $max ) {
		$f[ $k ] = sin_form_line( $k, $max );
	}
	$text  = sin_form_text( 'beschreibung' );
	$arten = sin_mieter_arten();
	foreach ( array( 'strasse', 'plz', 'ort', 'hausverwaltung', 'name' ) as $need ) {
		if ( '' === $f[ $need ] ) {
			sin_form_respond( 400, 'invalid' );
		}
	}
	if ( ! isset( $arten[ $f['art'] ] ) || '' === $text || ! sin_form_email_ok( $f['email'] ) || empty( $_POST['weiterleitung'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		sin_form_respond( 400, 'invalid' );
	}
	$to = defined( 'SIN_MIETER_TO' ) ? SIN_MIETER_TO : 'info@serviceinvest.de';
	sin_form_send( $key, $to, 'Mieteranliegen serviceinvest.de: ' . $arten[ $f['art'] ] . ' – ' . $f['strasse'] . ', ' . $f['ort'], array(
		'Neues Anliegen eines Mieters über www.serviceinvest.de (Seite "Für Mieter")',
		'Bitte an die zuständige Hausverwaltung weiterleiten.',
		'',
		'Immobilie',
		'  Straße:          ' . $f['strasse'],
		'  PLZ, Ort:        ' . $f['plz'] . ' ' . $f['ort'],
		'  Wohnung/Einheit: ' . ( '' !== $f['einheit'] ? $f['einheit'] : '–' ),
		'',
		'Hausverwaltung',
		'  Name:            ' . $f['hausverwaltung'],
		'  Ansprechpartner: ' . ( '' !== $f['hv_ansprechpartner'] ? $f['hv_ansprechpartner'] : '–' ),
		'',
		'Anliegen:          ' . $arten[ $f['art'] ],
		$text,
		'',
		'Mieter',
		'  Name:            ' . $f['name'],
		'  E-Mail:          ' . $f['email'],
		'  Telefon:         ' . ( '' !== $f['telefon'] ? $f['telefon'] : '–' ),
		'  Weiterleitung:   an die Hausverwaltung einverstanden (bestätigt)',
		'  Seite:           ' . sin_form_lang_name(),
		'  Eingang:         ' . wp_date( 'j. F Y, H:i' ) . ' Uhr',
		'',
		'-- ',
		'Antworten an den Mieter: "Antworten" verwenden (Reply-To).',
	), $f['email'] );
}
add_action( 'wp_ajax_sin_mieter', 'sin_mieter_handle' );
add_action( 'wp_ajax_nopriv_sin_mieter', 'sin_mieter_handle' );
