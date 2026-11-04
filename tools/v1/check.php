<?php
/**
 * Prüft eine Installation gegen den Build (HA 13, soweit ohne Browser prüfbar). Text und Darstellung gegen das Paket prüft
 * tools/v1/browser-check.js.
 *
 *   php tools/v1/check.php <Basis-URL> [--host=www.serviceinvest.de]
 *
 * - alle 24 Seiten: Status 200, HTML = Build-Datei (Byte für Byte, nach Rücknahme der Ausgabe-Änderungen: Formular- und
 *   Fonds-Endpunkt, verborgene Felder), genau eine H1, canonical und hreflang
 * - Sicherheits- und Cache-Header, keine Cookies, keine fremden Quellen
 * - Weiterleitungen nach HA 5.2, 404-Seiten de/en, sitemap.xml, robots.txt, Dateien, PAI-PDFs
 * - geschützte Dokumente ohne Sitzung nicht erreichbar; Formular-Endpunkte lehnen Bots und GET ab (ohne Mailversand)
 * --host: Host-Header setzen (Prüfung über einen PHP-Server auf dem Webspace, bevor die Domain umgestellt ist).
 */

$base = rtrim( $argv[1] ?? 'http://localhost:8092', '/' );
$host = null;
foreach ( array_slice( $argv, 2 ) as $a ) {
	if ( 0 === strpos( $a, '--host=' ) ) {
		$host = substr( $a, 7 );
	}
}
$build = dirname( __DIR__, 2 ) . '/content/v1-build';
$fail  = 0;
$ok    = 0;

function req( string $url, array $opt = array() ): array {
	global $host;
	$ch = curl_init( $url );
	$h  = array();
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_HEADERFUNCTION => function ( $c, $line ) use ( &$h ) {
			if ( strpos( $line, ':' ) ) {
				list( $k, $v ) = explode( ':', $line, 2 );
				$h[ strtolower( trim( $k ) ) ][] = trim( $v );
			}
			return strlen( $line );
		},
	) + $opt );
	if ( $host ) {
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Host: $host", 'X-Forwarded-Proto: https' ) );
	}
	$body = (string) curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	return array( $code, $h, $body );
}

function t( bool $cond, string $msg ): void {
	global $fail, $ok;
	if ( $cond ) {
		$ok++;
	} else {
		$fail++;
		echo "FEHLER  $msg\n";
	}
}

/** Ausgabe auf den Build zurückführen (nur die dokumentierten Änderungen beim Ausgeben). */
function normalize( string $html ): string {
	$html = preg_replace( '#(<form\b[^>]*\bid="[km]-form"[^>]*\bdata-endpoint=")[^"]*"#', '$1"', $html );
	$html = preg_replace( '#<div hidden aria-hidden="true"><label>Website <input[^>]*></label></div><input type="hidden" name="_ts" value="[^"]*"><input type="hidden" name="sprache" value="[a-z]{2}">(</form>)#', '$1', $html );
	return preg_replace( '#data-funds data-endpoint="[^"]*"#', 'data-funds data-endpoint=""', $html );
}

// Datei => Adresse aus der Sitemap des Builds (gleiche Reihenfolge wie build.php)
$pages = array();
foreach ( array_merge( glob( "$build/*.html" ), glob( "$build/en/*.html" ) ) as $f ) {
	preg_match( '#<link rel="canonical" href="https://www\.serviceinvest\.de(/[^"]*)">#', (string) file_get_contents( $f ), $m );
	$pages[ substr( $f, strlen( $build ) + 1 ) ] = $m[1] ?? '';
}
t( 24 === count( $pages ), 'Build enthält ' . count( $pages ) . ' statt 24 Seiten' );

echo "== Seiten ($base)\n";
foreach ( $pages as $file => $path ) {
	list( $c, $h, $b ) = req( $base . $path );
	t( 200 === $c, "$path Status $c" );
	$want = file_get_contents( "$build/$file" );
	$got  = normalize( $b );
	if ( $got !== $want ) {
		$i = 0;
		while ( $i < min( strlen( $got ), strlen( $want ) ) && $got[ $i ] === $want[ $i ] ) {
			$i++;
		}
		t( false, sprintf( "%s weicht vom Build ab ab Byte %d:\n   Build: %s\n   Seite: %s", $path, $i, json_encode( substr( $want, max( 0, $i - 40 ), 120 ) ), json_encode( substr( $got, max( 0, $i - 40 ), 120 ) ) ) );
	} else {
		t( true, '' );
	}
	t( 1 === preg_match_all( '#<h1\b#', $b ), "$path nicht genau eine H1" );
	t( 1 === preg_match_all( '#<link rel="canonical" href="https://www\.serviceinvest\.de' . preg_quote( $path, '#' ) . '">#', $b ), "$path canonical" );
	t( 3 === preg_match_all( '#<link rel="alternate" hreflang="(de|en|x-default)"#', $b ), "$path hreflang" );
	t( empty( $h['set-cookie'] ), "$path setzt Cookies: " . implode( ' | ', $h['set-cookie'] ?? array() ) );
	$csp = $h['content-security-policy'][0] ?? '';
	t( false !== strpos( $csp, "default-src 'self'" ) && false !== strpos( $csp, "script-src 'self'" ) && false !== strpos( $csp, "frame-ancestors 'none'" ) && false === strpos( $csp, 'unsafe-eval' ), "$path CSP: $csp" );
	t( 'DENY' === ( $h['x-frame-options'][0] ?? '' ), "$path X-Frame-Options" );
	t( 'nosniff' === ( $h['x-content-type-options'][0] ?? '' ), "$path X-Content-Type-Options" );
	t( 'strict-origin-when-cross-origin' === ( $h['referrer-policy'][0] ?? '' ), "$path Referrer-Policy" );
	t( (bool) preg_match( '#camera=\(\).*microphone=\(\).*geolocation=\(\)#', $h['permissions-policy'][0] ?? '' ), "$path Permissions-Policy" );
	t( false !== stripos( implode( ',', $h['cache-control'] ?? array() ), 'no-cache' ), "$path Cache-Control: " . implode( ',', $h['cache-control'] ?? array() ) );
	// Fremde Quellen: nur Links (<a href>) und Angaben im Kopf (canonical, Open Graph) dürfen nach außen zeigen
	preg_match_all( '#\b(?:src|srcset|href|imagesrcset)="(https?:)?//([^/"]+)#', preg_replace( '#<a\b[^>]*>#', '', preg_replace( '#<link rel="(?:canonical|alternate)"[^>]*>|<meta [^>]*>#', '', $b ) ), $ext );
	$hosts = array_diff( array_unique( $ext[2] ), array( parse_url( $base, PHP_URL_HOST ) ) );
	t( ! $hosts, "$path lädt von fremden Servern: " . implode( ', ', $hosts ) );
	if ( false !== strpos( $b, 'id="k-form"' ) ) {
		t( (bool) preg_match( '#id="k-form"[^>]*data-endpoint="[^"]*admin-ajax\.php\?action=sin_kontakt"#', $b ), "$path Kontaktformular ohne Endpunkt" );
	}
	if ( false !== strpos( $b, 'id="m-form"' ) ) {
		t( (bool) preg_match( '#id="m-form"[^>]*data-endpoint="[^"]*admin-ajax\.php\?action=sin_mieter"#', $b ), "$path Mieterformular ohne Endpunkt" );
	}
	if ( false !== strpos( $b, 'data-funds' ) ) {
		t( (bool) preg_match( '#data-funds data-endpoint="[^"]*admin-ajax\.php"#', $b ), "$path Fondsliste ohne Endpunkt" );
	}
}

echo "== Weiterleitungen (HA 5.2)\n";
$pai = '/dokumente/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_';
$redir = array(
	'/kontakt'                     => '/kontakt/',
	'/en/contact'                  => '/en/contact/',
	'/startseite/'                 => '/',
	'/nachhaltigkeit-2/'           => '/nachhaltigkeit/',
	'/erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2/' => '/pai-statement/',
	'/debeka-immobilien-invest-i/' => '/anlegerzugang/',
	'/tas-wohnen-1/'               => '/anlegerzugang/',
	'/serviceinvest-impressum/'    => '/impressum/',
	'/datenschutzerklaerung/'      => '/datenschutz/',
	'/datenschutzhinweisefuermieter/' => '/datenschutz-mieter/',
	'/en/home/'                    => '/en/',
	'/en/complaints-office/'       => '/en/complaints/',
	'/en/debeka-immobilien-invest-i-en/' => '/en/investor-access/',
	'/en/tas-wohnen-1-en/'         => '/en/investor-access/',
	'/en/imprint/'                 => '/en/legal-notice/',
	'/en/privacy-policy/'          => '/en/privacy/',
	'/en/data-protection-information/' => '/en/privacy-tenants/',
	'/en/statement-on-principal-adverse-impacts/' => '/en/pai-statement/',
	'/wp-content/uploads/2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf'  => $pai . 'DE-clean.pdf',
	'/wp-content/uploads/2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_ENG-clean.pdf' => $pai . 'ENG-clean.pdf',
	'/wp-content/uploads/2024/12/2023-10-31-2IG-Stewardship-Policy.pdf' => '/stewardship-policy/',
);
$self = $host ? 'https://' . $host : $base;
foreach ( $redir as $from => $to ) {
	list( $c, $h ) = req( $base . $from );
	$loc           = $h['location'][0] ?? '';
	t( 301 === $c && $loc === $self . $to, "$from -> $c $loc (erwartet 301 $self$to)" );
}
foreach ( array( '/beschwerdestelle/', '/verguetungspolitik/', '/en/sustainability/', '/en/remuneration-policy/' ) as $same ) {
	list( $c ) = req( $base . $same );
	t( 200 === $c, "$same (unveränderte Adresse) Status $c" );
}

echo "== Fehlerseiten\n";
foreach ( array( '/gibt-es-nicht/' => array( 'de', 'Seite nicht gefunden' ), '/en/does-not-exist/' => array( 'en', 'Page not found' ), '/?s=test' => array( 'de', 'Seite nicht gefunden' ), '/feed/' => array( 'de', 'Seite nicht gefunden' ) ) as $p => list( $lang, $txt ) ) {
	list( $c, , $b ) = req( $base . $p );
	t( 404 === $c, "$p Status $c" );
	t( false !== strpos( $b, "<html lang=\"$lang\">" ) && false !== strpos( $b, 'name="robots" content="noindex"' ) && false !== strpos( $b, $txt ) && false !== strpos( $b, 'de' === $lang ? 'href="/kontakt/" class="btn"' : 'href="/en/contact/" class="btn"' ), "$p Fehlerseite ($lang) unvollständig" );
	t( false === strpos( $b, 'rel="canonical"' ), "$p Fehlerseite mit canonical" );
}

echo "== Dateien\n";
foreach ( array( '/sitemap.xml', '/assets/js/site.js', '/assets/fonts/source-sans-3-variable.woff2', '/favicon.ico', '/apple-touch-icon.png', '/assets/og/serviceinvest.jpg', '/assets/logo/serviceinvest-logo-weiss.svg', '/assets/img/v/hero-1-quartier-1600.avif' ) as $p ) {
	list( $c, , $b ) = req( $base . $p );
	t( 200 === $c, "$p Status $c" );
	t( $b === file_get_contents( $build . $p ), "$p weicht vom Build ab" );
}
list( $c, , $b ) = req( $base . '/robots.txt' );
// lokal sperrt local-setup.sh die Indexierung
t( 200 === $c && ( false !== strpos( $b, 'Sitemap: https://www.serviceinvest.de/sitemap.xml' ) || preg_match( '#^http://(localhost|127)#', $base ) ), '/robots.txt' );
list( , , $b ) = req( $base . '/sitemap.xml' );
t( 24 === substr_count( $b, '<loc>' ) && 72 === substr_count( $b, 'hreflang=' ), 'sitemap.xml: 24 Adressen mit hreflang' );
foreach ( array( 'DE', 'ENG' ) as $l ) {
	list( $c, $h ) = req( $base . $pai . "$l-clean.pdf", array( CURLOPT_NOBODY => true ) );
	t( 200 === $c && 'application/pdf' === ( $h['content-type'][0] ?? '' ), "PAI-PDF $l: $c " . ( $h['content-type'][0] ?? '' ) );
}

echo "== Geschützter Bereich (HA 7)\n";
foreach ( array( 'debeka-immobilien-invest-i/Debeka-Immobilien-Invest-I_Offenlegung-Art-10-SFDR_DE.pdf', 'tas-wohnen-1/2024-06-19-VOe_Internetseite-Art.-10-SFDR-TAS-Wohnen-I-c.pdf', '../../wp-config.php' ) as $doc ) {
	list( $c, $h, $b ) = req( $base . '/?sin_doc=' . rawurlencode( $doc ) );
	t( 404 === $c && false === strpos( $b, '%PDF' ), "Dokument ohne Sitzung: $doc -> $c" );
	t( false !== strpos( implode( ',', $h['x-robots-tag'] ?? array() ), 'noindex' ), "Dokument-Aufruf ohne X-Robots-Tag noindex: $doc" );
}
// Bisherige öffentliche Adressen liefern die aktuelle Fassung (wie 2ip.de), nicht für Suchmaschinen
foreach ( array(
	'2025/08/Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.-10-SFDR_Art_8-c-final.pdf',
	'2025/08/Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.10-SFDR-english.pdf',
	'2025/04/2024-06-19-VOe_Internetseite-Art.-10-SFDR-TAS-Wohnen-I-c.pdf',
	'2025/04/2024-04-19-website-disclosure-Art.-10-SFDR-TAS-Wohnen-I-c.pdf',
) as $old ) {
	list( $c, $h, $b ) = req( $base . '/wp-content/uploads/' . $old );
	t( 200 === $c && 0 === strpos( $b, '%PDF' ) && false !== strpos( implode( ',', $h['x-robots-tag'] ?? array() ), 'noindex' ), "bisherige Adresse $old: $c " . implode( ',', $h['x-robots-tag'] ?? array() ) );
	if ( false !== strpos( $old, 'Debeka' ) ) {
		t( false !== strpos( $h['content-disposition'][0] ?? '', 'Debeka-Immobilien-Invest-I_Offenlegung-Art-10-SFDR_' ), "bisherige Adresse $old liefert nicht die überarbeitete Fassung" );
	}
}
list( $c, , $b ) = req( $base . '/wp-admin/admin-ajax.php', array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => array( 'action' => 'sin_fonds_status' ) ) );
t( 200 === $c && '{"ok":true,"open":{}}' === $b, "Status ohne Sitzung: $c $b" );
list( $c, , $b ) = req( $base . '/wp-admin/admin-ajax.php', array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => array( 'action' => 'sin_fonds', 'fund' => '7', 'password' => 'x' ) ) );
t( 200 === $c && false !== strpos( $b, '"ok":false' ), "unbekannter Fonds: $c $b" );
t( false === strpos( (string) file_get_contents( "$build/anlegerzugang.html" ) . file_get_contents( "$build/assets/js/site.js" ), 'lokal-test' ), 'Passwort im HTML/JavaScript' );

echo "== Formular-Endpunkte (ohne Mailversand)\n";
foreach ( array( 'sin_kontakt', 'sin_mieter' ) as $action ) {
	$ep = $base . '/wp-admin/admin-ajax.php?action=' . $action;
	list( $c ) = req( $ep, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => array( 'name' => 'Test' ) ) );
	t( 200 === $c, "$action ohne Zeitstempel: $c (erwartet 200 wie bei Bots, ohne Versand)" );
	list( $c ) = req( $ep, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => array( 'name' => 'x', 'website' => 'spam' ) ) );
	t( 200 === $c, "$action Honeypot: $c" );
	list( $c ) = req( $ep );
	t( 405 === $c, "$action GET: $c" );
}

// Sperren und Header aus der .htaccess (nicht beim PHP-Server, der sie nicht kennt)
if ( ! $host && ! preg_match( '#^http://(localhost|127\.0\.0\.1)#', $base ) ) {
	echo "== Webserver\n";
	foreach ( array( '/wp-content/themes/sin-v1/fallback-de.html', '/xmlrpc.php', '/wp-config.php', '/README.md', '/wp-content/debug.log', '/.htaccess', '/favicon-64.png' ) as $p ) {
		list( $c ) = req( $base . $p );
		t( in_array( $c, array( 403, 404 ), true ), "$p erreichbar ($c)" );
	}
	list( $c, $h ) = req( $base . '/assets/img/v/hero-1-quartier-1600.avif' );
	t( false !== strpos( implode( ',', $h['cache-control'] ?? array() ), 'immutable' ) && 'image/avif' === ( $h['content-type'][0] ?? '' ), '/assets/img/v/ Cache-Control/Typ: ' . implode( ',', $h['cache-control'] ?? array() ) . ' ' . ( $h['content-type'][0] ?? '' ) );
	list( $c, $h ) = req( $base . '/assets/js/site.js' );
	t( false !== strpos( implode( ',', $h['cache-control'] ?? array() ), 'max-age=' ), '/assets/js/site.js Cache-Control' );
	t( false !== strpos( $h['strict-transport-security'][0] ?? '', 'max-age=31536000' ) || 0 !== strpos( $base, 'https' ), 'HSTS fehlt' );
	foreach ( array( 'http://www.serviceinvest.de/kontakt/', 'https://serviceinvest.de/kontakt/', 'http://serviceinvest.de/kontakt/' ) as $u ) {
		if ( 0 === strpos( $base, 'https://www.serviceinvest.de' ) ) {
			list( $c, $h ) = req( $u );
			t( 301 === $c && 'https://www.serviceinvest.de/kontakt/' === ( $h['location'][0] ?? '' ), "$u -> $c " . ( $h['location'][0] ?? '' ) );
		}
	}
	list( $c, $h ) = req( $base . '/', array( CURLOPT_ENCODING => '' ) );
	t( in_array( $h['content-encoding'][0] ?? '', array( 'br', 'gzip' ), true ), 'Kompression der HTML-Seiten: ' . ( $h['content-encoding'][0] ?? 'keine' ) );
}

printf( "\n%d Prüfungen, %d Fehler\n", $ok + $fail, $fail );
exit( $fail ? 1 : 0 );
