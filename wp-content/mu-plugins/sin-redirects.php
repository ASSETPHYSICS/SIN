<?php
/**
 * Plugin Name: ServiceInvest – Weiterleitungen der bisherigen Website
 * Description: Leitet Adressen der alten serviceinvest.de, die es auf der neuen Seite nicht (mehr) gibt, auf passende Seiten um,
 *              damit externe Links nicht ins Leere laufen. Greift nur, wenn WordPress keine Seite findet (404).
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', function () {
	// Kategorie- und Schlagwortarchive des alten Blogs gibt es nicht mehr (die Begriffe stammen noch aus der Datenbank)
	if ( is_category() || is_tag() ) {
		wp_safe_redirect( home_url( '/' ), 301, 'ServiceInvest' );
		exit;
	}
	if ( ! is_404() ) {
		return;
	}
	$path = '/' . trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
	$path = rawurldecode( $path );

	$pai = '/erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2/';

	// Dauerhaft umgezogen (301)
	$permanent = array(
		'/nachhaltigkeit' => '/nachhaltigkeit-2/',
		'/erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren' => $pai,
		'/kontakt'        => '/#kontakt',
		'/insights'       => '/',
		'/home/insights'  => '/',
		// News-Beiträge (die News-Sektion entfällt auf der neuen Seite)
		'/das-berichts-und-meldewesen-der-kapitalverwaltungsgesellschaft-als-kriterium-fuer-institutionelle-immobilieninvestoren' => '/',
		'/regionalbanken-depot-a-effizientes-eigenkapitalmanagement-bei-immobilienanlagen' => '/',
		'/institutional-investment-group-2ig-uebernimmt-connos-und-steigert-assets-under-administration-auf-ueber-25-mrd-euro' => '/',
		'/was-kvg-insourcing-spezialisten-fuer-asset-manager-leisten-koennen' => '/',
		'/raum-fuer-wachstum-durch-kvg-insourcing' => '/',
		'/serviceinvest-kapitalverwaltungsgesellschaft-und-bonventure-kooperieren-pro-bono' => '/',
	);

	// Englische Seiten der alten Website: dauerhaft auf die englischen Seiten unter /en/
	$permanent += array(
		'/home-en'                              => '/en/',
		'/home-en/serviceinvest-imprint'        => '/en/imprint/',
		'/sustainability'                       => '/en/sustainability/',
		'/statement-on-principal-adverse-impacts-of-investment-decisions-on-sustainability-factors' => '/en/statement-on-principal-adverse-impacts/',
		'/dataprotectionnote'                   => '/en/data-protection-information/',
		'/privacy-policy-2'                     => '/en/privacy-policy/',
		'/complaints-office'                    => '/en/complaints-office/',
		'/verguetungspolitik-2'                 => '/en/remuneration-policy/',
		'/kontakt-2'                            => '/en/#kontakt',
		'/insights-english'                     => '/en/',
		'/debeka-immobilien-invest-i-english'   => '/en/debeka-immobilien-invest-i-en/',
		'/tas-wohnen-1-en'                      => '/en/tas-wohnen-1-en/',
	);
	$temporary = array();

	$target = null;
	$status = 301;
	if ( isset( $permanent[ $path ] ) ) {
		$target = $permanent[ $path ];
	} elseif ( isset( $temporary[ $path ] ) ) {
		$target = $temporary[ $path ];
		$status = 302;
	} elseif ( preg_match( '#^/(category|tag)/#', $path ) || preg_match( '#/feed$#', $path ) ) {
		$target = '/';   // Archiv- und Feed-Adressen des alten Blogs
	} elseif ( '/redirect-mailto-datenschutz.php' === $path || '/redirect-mailto-press.php' === $path ) {
		// Alte Hilfsskripte, die auf eine E-Mail-Adresse weiterleiteten
		$mail = ( '/redirect-mailto-datenschutz.php' === $path ) ? 'datenschutz@serviceinvest.de' : 'michael.benninghoff@2ig.de';
		header( 'Location: mailto:' . $mail, true, 302 );
		exit;
	}

	if ( null !== $target ) {
		wp_safe_redirect( home_url( $target ), $status, 'ServiceInvest' );
		exit;
	}
}, 1 );   // vor redirect_canonical(), damit WordPress nicht selbst eine ähnliche Adresse errät
