<?php
/**
 * Plugin Name: Appointment Booking
 * Description: Terminbuchung und Anfrageformular mit Konfliktprüfung, Missbrauchsschutz und E-Mail-Benachrichtigung
 * Author: Matteo Bertuzzi,
 * Version: 1.1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'APPOINTMENT_BOOKING_VERSION', '1.1.0' );
define( 'APPOINTMENT_BOOKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APPOINTMENT_BOOKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Konfiguration. Jeder Wert kann vor dem Laden des Plugins in der wp-config.php
 * mit define() überschrieben werden.
 */
$appointment_booking_defaults = array(
	'APPOINTMENT_BOOKING_TO'             => 'info@2ig.de', // Empfänger der internen Benachrichtigungen
	'APPOINTMENT_BOOKING_FROM'           => 'contact@2ig.de', // Absenderadresse aller Mails
	'APPOINTMENT_BOOKING_FROM_NAME'      => '2IP',
	'APPOINTMENT_BOOKING_RETENTION_DAYS' => 180,           // Buchungsdaten werden so lange nach dem Termin aufbewahrt
	'APPOINTMENT_BOOKING_RATE_IP'        => 5,             // Absendungen pro IP und Stunde
	'APPOINTMENT_BOOKING_RATE_EMAIL'     => 2,             // Bestätigungsmails pro Empfängeradresse und Stunde
	'APPOINTMENT_BOOKING_MAX_DAYS'       => 365,           // Buchbar bis zu so vielen Tagen im Voraus
);
foreach ( $appointment_booking_defaults as $constant => $value ) {
	if ( ! defined( $constant ) ) {
		define( $constant, $value );
	}
}
unset( $appointment_booking_defaults, $constant, $value );

class Appointment_Booking {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'schedule_cleanup' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'appointment_booking_cleanup', array( $this, 'cleanup_old_appointments' ) );

		add_shortcode( 'appointment_booking', array( $this, 'render_booking_form' ) );

		foreach ( array( 'get_available_slots', 'book_appointment', 'submit_anfrage' ) as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'ajax_' . $action ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'ajax_' . $action ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Infrastruktur
	 * ---------------------------------------------------------------- */

	public function register_post_type() {
		register_post_type( 'appointment', array(
			'labels'          => array(
				'name'          => 'Appointments',
				'singular_name' => 'Appointment',
			),
			'public'          => false,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'capability_type' => 'post',
			'supports'        => array( 'title' ),
			'has_archive'     => false,
		) );
	}

	/**
	 * Skripte und Styles nur registrieren; geladen werden sie erst, wenn der Shortcode auf der Seite steht.
	 */
	public function register_assets() {
		$url = APPOINTMENT_BOOKING_PLUGIN_URL;
		wp_register_style( 'flatpickr', $url . 'assets/vendor/flatpickr/flatpickr.min.css', array(), '4.6.13' );
		wp_register_style( 'appointment-booking-css', $url . 'assets/css/appointment-booking.css', array(), APPOINTMENT_BOOKING_VERSION );
		wp_register_script( 'flatpickr', $url . 'assets/vendor/flatpickr/flatpickr.min.js', array(), '4.6.13', true );
		wp_register_script( 'flatpickr-de', $url . 'assets/vendor/flatpickr/l10n/de.js', array( 'flatpickr' ), '4.6.13', true );
		wp_register_script( 'appointment-booking-js', $url . 'assets/js/appointment-booking.js', array( 'jquery', 'flatpickr', 'flatpickr-de' ), APPOINTMENT_BOOKING_VERSION, true );
	}

	private function enqueue_assets() {
		wp_enqueue_style( 'flatpickr' );
		wp_enqueue_style( 'appointment-booking-css' );
		wp_enqueue_script( 'appointment-booking-js' );
		wp_localize_script( 'appointment-booking-js', 'appointmentBooking', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'appointment_booking_nonce' ),
		) );
	}

	public function render_booking_form( $atts = array() ) {
		$atts             = shortcode_atts( array( 'tab' => '', 'hide_investor' => '' ), $atts, 'appointment_booking' );
		$ab_hide_investor = ( '1' === (string) $atts['hide_investor'] );
		$ab_tab           = sanitize_key( $atts['tab'] );

		$this->enqueue_assets();

		ob_start();
		include APPOINTMENT_BOOKING_PLUGIN_DIR . 'templates/booking-form.php';
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * Hilfsfunktionen: Eingaben, Missbrauchsschutz, Validierung
	 * ---------------------------------------------------------------- */

	private function fail( $message ) {
		wp_send_json_error( array( 'message' => $message ) );
	}

	/** Liest ein POST-Feld, bereinigt es und kürzt es auf $max Zeichen. */
	private function field( $key, $max = 200, $multiline = false ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}
		$value = wp_unslash( (string) $_POST[ $key ] );
		$value = $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		return trim( mb_substr( $value, 0, $max ) );
	}

	private function email_field( $key ) {
		return sanitize_email( mb_substr( $this->field( $key, 254 ), 0, 254 ) );
	}

	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return (string) apply_filters( 'appointment_booking_client_ip', filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown' );
	}

	/**
	 * Einfache Begrenzung über Transients. Liefert false, wenn das Limit erreicht ist.
	 */
	private function allow( $bucket, $identity, $limit ) {
		$key   = 'ab_rl_' . md5( $bucket . '|' . $identity );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/** Gemeinsame Prüfungen aller Absende-Aktionen: Honigtopf, Nonce, Rate-Limit pro IP. */
	private function guard( $success_message, $limit_bucket ) {
		// Honeypot: Bots füllen das versteckte Feld. Wir tun so, als wäre alles gut.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'message' => $success_message ) );
		}
		if ( ! check_ajax_referer( 'appointment_booking_nonce', 'nonce', false ) ) {
			$this->fail( 'Sicherheitsüberprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.' );
		}
		if ( ! $this->allow( $limit_bucket, $this->client_ip(), (int) APPOINTMENT_BOOKING_RATE_IP ) ) {
			$this->fail( 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.' );
		}
	}

	/** Gültiges, zukünftiges Datum (Mo–Fr) im Format Y-m-d; sonst null. */
	private function valid_date( $date ) {
		$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$e = DateTimeImmutable::getLastErrors();
		if ( ! $d || ( $e && ( $e['warning_count'] || $e['error_count'] ) ) || $d->format( 'Y-m-d' ) !== $date ) {
			return null;
		}
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$max   = $today->modify( '+' . (int) APPOINTMENT_BOOKING_MAX_DAYS . ' days' );
		if ( $d < $today || $d > $max || (int) $d->format( 'N' ) > 5 ) {
			return null;
		}
		return $d;
	}

	/** Alle buchbaren Uhrzeiten: 09:00–12:30 und 14:00–17:00 im 30-Minuten-Takt. */
	private function get_all_time_slots() {
		$slots = array();
		foreach ( array( array( 9 * 60, 12 * 60 + 30 ), array( 14 * 60, 17 * 60 ) ) as $range ) {
			for ( $m = $range[0]; $m <= $range[1]; $m += 30 ) {
				$slots[] = sprintf( '%02d:%02d', floor( $m / 60 ), $m % 60 );
			}
		}
		return $slots;
	}

	private function get_booked_slots( $date ) {
		$appointments = get_posts( array(
			'post_type'      => 'appointment',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'appointment_date',
					'value'   => $date,
					'compare' => '=',
				),
			),
		) );
		$booked = array();
		foreach ( $appointments as $id ) {
			$time = get_post_meta( $id, 'appointment_time', true );
			if ( $time ) {
				$booked[] = $time;
			}
		}
		return $booked;
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ---------------------------------------------------------------- */

	public function ajax_get_available_slots() {
		if ( ! check_ajax_referer( 'appointment_booking_nonce', 'nonce', false ) ) {
			$this->fail( 'Sicherheitsüberprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.' );
		}
		if ( ! $this->allow( 'slots', $this->client_ip(), 60 ) ) {
			$this->fail( 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.' );
		}
		$date = $this->field( 'date', 10 );
		if ( ! $this->valid_date( $date ) ) {
			$this->fail( 'Bitte wählen Sie einen gültigen Werktag.' );
		}
		$available = array_diff( $this->get_all_time_slots(), $this->get_booked_slots( $date ) );
		wp_send_json_success( array( 'slots' => array_values( $available ) ) );
	}

	public function ajax_book_appointment() {
		$this->guard( 'Termin erfolgreich gebucht! Sie erhalten in Kürze eine Bestätigungs-E-Mail.', 'book' );

		$date          = $this->field( 'date', 10 );
		$time          = $this->field( 'time', 5 );
		$name          = $this->field( 'name', 100 );
		$company       = $this->field( 'company', 150 );
		$investor_type = $this->field( 'investor_type', 80 );
		$email         = $this->email_field( 'email' );
		$phone         = $this->field( 'phone', 40 );
		$message       = $this->field( 'message', 2000, true );

		if ( '' === $date || '' === $time || '' === $name || '' === $company || '' === $investor_type || '' === $email ) {
			$this->fail( 'Bitte füllen Sie alle Pflichtfelder aus.' );
		}
		if ( ! is_email( $email ) ) {
			$this->fail( 'Bitte geben Sie eine gültige E-Mail-Adresse ein.' );
		}
		if ( ! $this->valid_date( $date ) || ! in_array( $time, $this->get_all_time_slots(), true ) ) {
			$this->fail( 'Bitte wählen Sie einen gültigen Termin.' );
		}
		if ( ! $this->allow( 'mail', strtolower( $email ), (int) APPOINTMENT_BOOKING_RATE_EMAIL ) ) {
			$this->fail( 'Für diese E-Mail-Adresse wurden bereits Nachrichten versendet. Bitte versuchen Sie es später erneut.' );
		}

		// Doppelbuchungen verhindern: Prüfung und Anlegen unter einer Datenbanksperre.
		global $wpdb;
		$lock = 'ab_' . md5( $date . ' ' . $time );
		$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );

		if ( in_array( $time, $this->get_booked_slots( $date ), true ) ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			$this->fail( 'Dieser Termin wurde bereits gebucht. Bitte wählen Sie einen anderen Zeitpunkt.' );
		}

		$post_id = wp_insert_post( array(
			'post_type'   => 'appointment',
			'post_title'  => sprintf( '%s - %s %s', $name, $date, $time ),
			'post_status' => 'publish',
		) );
		if ( ! is_wp_error( $post_id ) && $post_id ) {
			update_post_meta( $post_id, 'appointment_date', $date );
			update_post_meta( $post_id, 'appointment_time', $time );
			update_post_meta( $post_id, 'customer_name', $name );
			update_post_meta( $post_id, 'customer_company', $company );
			update_post_meta( $post_id, 'investor_type', $investor_type );
			update_post_meta( $post_id, 'customer_email', $email );
			update_post_meta( $post_id, 'customer_phone', $phone );
			update_post_meta( $post_id, 'customer_message', $message );
		}
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$this->fail( 'Fehler beim Speichern des Termins.' );
		}

		$formatted_date = $this->valid_date( $date )->format( 'd.m.Y' );

		$this->send_customer_email( $email, $name, $formatted_date, $time );
		$this->send_manager_email( $name, $company, $investor_type, $email, $phone, $formatted_date, $time, $message );

		wp_send_json_success( array( 'message' => 'Termin erfolgreich gebucht! Sie erhalten in Kürze eine Bestätigungs-E-Mail.' ) );
	}

	public function ajax_submit_anfrage() {
		$this->guard( 'Ihre Anfrage wurde erfolgreich gesendet!', 'anfrage' );

		$name          = $this->field( 'name', 100 );
		$company       = $this->field( 'company', 150 );
		$has_investor  = isset( $_POST['investor_type'] );
		$investor_type = $has_investor ? $this->field( 'investor_type', 80 ) : '';
		$email         = $this->email_field( 'email' );
		$phone         = $this->field( 'phone', 40 );
		$message       = $this->field( 'message', 2000, true );

		if ( '' === $name || '' === $company || ( $has_investor && '' === $investor_type ) || '' === $email || '' === $message ) {
			$this->fail( 'Bitte füllen Sie alle Pflichtfelder aus.' );
		}
		if ( ! is_email( $email ) ) {
			$this->fail( 'Bitte geben Sie eine gültige E-Mail-Adresse ein.' );
		}
		if ( ! $this->allow( 'mail', strtolower( $email ), (int) APPOINTMENT_BOOKING_RATE_EMAIL ) ) {
			$this->fail( 'Für diese E-Mail-Adresse wurden bereits Nachrichten versendet. Bitte versuchen Sie es später erneut.' );
		}

		$this->send_anfrage_customer_email( $email, $name );
		$this->send_anfrage_manager_email( $name, $company, $investor_type, $email, $phone, $message );

		wp_send_json_success( array( 'message' => 'Ihre Anfrage wurde erfolgreich gesendet!' ) );
	}

	/* ------------------------------------------------------------------
	 * E-Mails
	 * ---------------------------------------------------------------- */

	private function headers( $reply_name = '', $reply_email = '' ) {
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . APPOINTMENT_BOOKING_FROM_NAME . ' <' . APPOINTMENT_BOOKING_FROM . '>',
		);
		if ( '' !== $reply_email && is_email( $reply_email ) ) {
			// Name ist bereits einzeilig (sanitize_text_field); Anführungszeichen entfernen für den Header.
			$headers[] = 'Reply-To: ' . str_replace( array( '"', '<', '>' ), '', $reply_name ) . ' <' . $reply_email . '>';
		}
		return $headers;
	}

	private function recipient() {
		return (string) apply_filters( 'appointment_booking_recipient', APPOINTMENT_BOOKING_TO );
	}

	private function send_customer_email( $to, $name, $date, $time ) {
		$body  = "Guten Tag " . $name . ",\n\n";
		$body .= "vielen Dank für Ihr Interesse an 2IP und Ihre Terminbuchung am " . $date . " um " . $time . ".\n\n";
		$body .= "Der Termin wird per MS-Teams-Call stattfinden, die Einladung mit Einwahl-Link erhalten Sie in einer separaten E-Mail direkt von Ihrem Gesprächspartner.\n\n";
		$body .= "Sollten Sie den Termin doch nicht wahrnehmen können oder Fragen haben, kommen Sie gern auf uns zu.\n\n";
		$body .= "Mit freundlichen Grüßen\n";
		$body .= "Ihr 2IP-Team";

		wp_mail( $to, 'Terminbestätigung - Ihr Termin bei 2IP', $body, $this->headers() );
	}

	private function send_manager_email( $name, $company, $investor_type, $email, $phone, $date, $time, $message ) {
		$body  = "Hallo 2IP-Team,\n\n";
		$body .= $name . " möchte einen Teams-Call am " . $date . " um " . $time . " vereinbaren.\n\n";
		$body .= "Unternehmen: " . $company . "\n";
		$body .= "Anlegertyp: " . $investor_type . "\n";
		$body .= "E-Mail: " . $email . "\n";
		if ( '' !== $phone ) {
			$body .= "Telefon: " . $phone . "\n";
		}
		$body .= "\n";
		if ( '' !== $message ) {
			$body .= "Optionale Nachricht:\n" . $message . "\n\n";
		}
		$body .= "Bitte koordiniert den Termin intern und sendet entsprechende Einwahldaten an den Interessenten.\n\n";
		$body .= "Vielen Dank und viele Grüße\n";
		$body .= "Euer 2IP-Webmaster";

		wp_mail( $this->recipient(), 'Neue Terminbuchung - ' . $date . ' um ' . $time, $body, $this->headers( $name, $email ) );
	}

	/** Bestätigung an den Absender – bewusst ohne Wiedergabe des Freitexts. */
	private function send_anfrage_customer_email( $to, $name ) {
		$body  = "Guten Tag " . $name . ",\n\n";
		$body .= "vielen Dank für Ihr Interesse an 2IP.\n\n";
		$body .= "Ihre Anfrage haben wir erhalten, ein Kollege wird sich baldmöglichst bei Ihnen melden.\n\n";
		$body .= "Mit freundlichen Grüßen\n";
		$body .= "Ihr 2IP-Team";

		wp_mail( $to, 'Bestätigung Ihrer Anfrage bei 2IP', $body, $this->headers() );
	}

	private function send_anfrage_manager_email( $name, $company, $investor_type, $email, $phone, $message ) {
		$body  = "Hallo 2IP-Team,\n\n";
		$body .= $name . " hat eine Anfrage gestellt.\n\n";
		$body .= "Unternehmen: " . $company . "\n";
		if ( '' !== $investor_type ) {
			$body .= "Anlegertyp: " . $investor_type . "\n";
		}
		$body .= "E-Mail: " . $email . "\n";
		if ( '' !== $phone ) {
			$body .= "Telefon: " . $phone . "\n";
		}
		$body .= "\nAnfrage:\n" . $message . "\n\n";
		$body .= "Bitte meldet euch baldmöglichst beim Interessenten.\n\n";
		$body .= "Vielen Dank und viele Grüße\n";
		$body .= "Euer 2IP-Webmaster";

		wp_mail( $this->recipient(), 'Neue Anfrage von ' . $name, $body, $this->headers( $name, $email ) );
	}

	/* ------------------------------------------------------------------
	 * Datenschutz: Aufbewahrungsfrist
	 * ---------------------------------------------------------------- */

	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'appointment_booking_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'appointment_booking_cleanup' );
		}
	}

	/** Löscht Buchungen, deren Termin länger als die Aufbewahrungsfrist zurückliegt. */
	public function cleanup_old_appointments() {
		$cutoff = gmdate( 'Y-m-d', time() - (int) APPOINTMENT_BOOKING_RETENTION_DAYS * DAY_IN_SECONDS );
		$ids    = get_posts( array(
			'post_type'      => 'appointment',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'appointment_date',
					'value'   => $cutoff,
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
		) );
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}
}

function appointment_booking_init() {
	return Appointment_Booking::get_instance();
}
add_action( 'plugins_loaded', 'appointment_booking_init' );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'appointment_booking_cleanup' );
} );
