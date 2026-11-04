<?php
/**
 * Baut aus dem Paket serviceinvest-website (content/v1-vorlage/) statisches HTML ohne support.js (HA 4) nach
 * content/v1-build/. Läuft lokal (PHP ≥ 8.1), ohne WordPress. Das Ergebnis importiert tools/v1/import.php.
 *
 *   php tools/v1/build.php            (vorher: NODE_PATH=… node tools/v1/images.mjs content/v1-vorlage content/v1-build)
 *
 * Je Seite:
 *   - Kopf neu: Titel aus dem Paket, Beschreibung (HA 10), canonical, hreflang de/en/x-default, Open Graph, Twitter Card,
 *     JSON-LD Organization (Startseiten), Favicons, Schrift-Preload, LCP-Bild-Preload, CSS des Pakets inline (+ Ergänzungen),
 *     site.js mit Versionskennung.
 *   - Body: Vorlagen-Elemente (x-dc, helmet, sc-if, sc-for, {{…}}) im Anfangszustand des Pakets aufgelöst; was das Paket
 *     erst nach einer Aktion zeigt, steht mit [hidden] im HTML (data-if = Name der Bedingung im Paket).
 *   - Adressen nach HA 5.1 (/kontakt/, /en/contact/ …), Bilder als <picture> mit AVIF/WebP/JPEG (HA 9), externe Links in
 *     neuem Tab mit Hinweis für Screenreader (HA 8), PAI-PDFs unter /dokumente/ (HA 8), Skip-Link, Pause-Schaltfläche (HA 11).
 * Texte bleiben unverändert (HA 3.1); tools/v1/check.php vergleicht den Text jeder Seite mit dem Paket.
 */

const SITE = 'https://www.serviceinvest.de';

$root  = dirname( __DIR__, 2 );
$src   = "$root/content/v1-vorlage";
$add   = "$root/content/v1-zusatz";
$dst   = "$root/content/v1-build";
$fails = array();

function fail( string $msg ): never {
	fwrite( STDERR, "FEHLER: $msg\n" );
	exit( 1 );
}

/** Paketdatei => array( Adresse, Beschreibung ) – HA 5.1 und 10. Reihenfolge: Deutsch, dann Englisch (gleiche Position = Sprachpaar). */
function pages(): array {
	return array(
		'de' => array(
			'index.html'              => array( '/', 'Kapitalverwaltungsgesellschaft für Partnerfonds: Asset Manager bringen als Anlageberater ihre Expertise ein, ServiceInvest trägt die Verantwortung. Dazu Leistungen für die regulierte Finanzwelt.' ),
			'nachhaltigkeit.html'     => array( '/nachhaltigkeit/', 'Wie ServiceInvest Nachhaltigkeitsrisiken und nachteilige Nachhaltigkeitsauswirkungen berücksichtigt: Grundsätze, Mitgliedschaften, Stewardship und Vergütung.' ),
			'anlegerzugang.html'      => array( '/anlegerzugang/', 'Informationen für Anleger der Fonds von ServiceInvest: Offenlegungen für institutionelle Anleger und Informationen für Privatanleger.' ),
			'mieter.html'             => array( '/mieter/', 'Anliegen zu Ihrer Wohnung oder Mietfläche in einer Immobilie unserer Fonds: Wir leiten Ihr Anliegen an die zuständige Hausverwaltung weiter.' ),
			'kontakt.html'            => array( '/kontakt/', 'Kontakt zur ServiceInvest Kapitalverwaltungsgesellschaft mbH: Partnerfonds, einzelne Leistungen, Anlegeranfragen und Presse.' ),
			'impressum.html'          => array( '/impressum/', 'Impressum der ServiceInvest Kapitalverwaltungsgesellschaft mbH mit Anbieterangaben, Aufsicht und Verbraucherstreitbeilegung.' ),
			'datenschutz.html'        => array( '/datenschutz/', 'Datenschutzerklärung der ServiceInvest Kapitalverwaltungsgesellschaft mbH für die Website und weitere Verarbeitungen.' ),
			'datenschutz-mieter.html' => array( '/datenschutz-mieter/', 'Datenschutzhinweise für Mieter von Immobilien, die von der ServiceInvest Kapitalverwaltungsgesellschaft mbH für Fonds gehalten werden.' ),
			'pai-statement.html'      => array( '/pai-statement/', 'Erklärung der ServiceInvest zu den wichtigsten nachteiligen Auswirkungen von Investitionsentscheidungen auf Nachhaltigkeitsfaktoren.' ),
			'stewardship-policy.html' => array( '/stewardship-policy/', 'Stewardship-Policy der Institutional Investment Group: wie die Unternehmen verantwortungsvoll Einfluss nehmen.' ),
			'verguetungspolitik.html' => array( '/verguetungspolitik/', 'Vergütungspolitik der ServiceInvest Kapitalverwaltungsgesellschaft mbH nach KAGB und ESMA-Leitlinien.' ),
			'beschwerdestelle.html'   => array( '/beschwerdestelle/', 'Beschwerdestelle für Fondsanleger der ServiceInvest und außergerichtliche Streitschlichtung über die Ombudsstelle für Investmentfonds.' ),
		),
		'en' => array(
			'en/index.html'               => array( '/en/', 'Asset management company for partner funds: asset managers contribute their expertise as investment advisers, ServiceInvest bears responsibility. Plus services for the regulated financial sector.' ),
			'en/sustainability.html'      => array( '/en/sustainability/', 'How ServiceInvest takes sustainability risks and principal adverse impacts into account: principles, memberships, stewardship and remuneration.' ),
			'en/investor-access.html'     => array( '/en/investor-access/', 'Information for investors in ServiceInvest funds: disclosures for institutional investors and information for private investors.' ),
			'en/tenants.html'             => array( '/en/tenants/', 'Concerns about your flat or rented premises in a property held by our funds: we forward your request to the responsible property manager.' ),
			'en/contact.html'             => array( '/en/contact/', 'Contact ServiceInvest Kapitalverwaltungsgesellschaft mbH: partner funds, individual services, investor enquiries and press.' ),
			'en/legal-notice.html'        => array( '/en/legal-notice/', 'Legal notice of ServiceInvest Kapitalverwaltungsgesellschaft mbH: provider details, supervision and consumer dispute resolution.' ),
			'en/privacy.html'             => array( '/en/privacy/', 'Privacy policy of ServiceInvest Kapitalverwaltungsgesellschaft mbH for the website and other processing activities.' ),
			'en/privacy-tenants.html'     => array( '/en/privacy-tenants/', 'Privacy notice for tenants of properties held by ServiceInvest Kapitalverwaltungsgesellschaft mbH on behalf of its funds.' ),
			'en/pai-statement.html'       => array( '/en/pai-statement/', 'ServiceInvest statement on principal adverse impacts of investment decisions on sustainability factors.' ),
			'en/stewardship-policy.html'  => array( '/en/stewardship-policy/', 'Stewardship policy of the Institutional Investment Group: how its companies exercise influence responsibly.' ),
			'en/remuneration-policy.html' => array( '/en/remuneration-policy/', 'Remuneration policy of ServiceInvest Kapitalverwaltungsgesellschaft mbH under the KAGB and ESMA guidelines.' ),
			'en/complaints.html'          => array( '/en/complaints/', 'Complaints office for fund investors of ServiceInvest and out-of-court dispute resolution via the Ombudsman for Investment Funds.' ),
		),
	);
}

/** Texte, die das Paket nicht enthält: Wortlaut wie auf 2ip.de (dort freigegeben am 04.11.2026), für ServiceInvest übernommen. */
function texts( string $lang ): array {
	return array(
		'de' => array(
			'skip'       => 'Zum Inhalt springen',
			'newwin'     => '(öffnet in neuem Fenster)',
			'pause'      => 'Bildwechsel anhalten',
			'play'       => 'Bildwechsel fortsetzen',
			'senderror'  => 'Ihre Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es später erneut oder schreiben Sie an <a href="mailto:info@serviceinvest.de">info@serviceinvest.de</a>.',
			'msenderror' => 'Ihr Anliegen konnte nicht gesendet werden. Bitte versuchen Sie es später erneut oder schreiben Sie an <a href="mailto:info@serviceinvest.de">info@serviceinvest.de</a>.',
			'expired'    => 'Die Seite war zu lange geöffnet. Bitte laden Sie sie neu und senden Sie Ihre Anfrage erneut.',
			'limit'      => 'Zu viele Anfragen in kurzer Zeit. Bitte versuchen Sie es in einigen Minuten erneut.',
			'nodocs'     => 'Derzeit liegen keine Dokumente vor.',
			'404kicker'  => 'Fehler 404',
			'404title'   => 'Seite nicht gefunden',
			'404h1'      => 'Diese Seite gibt es nicht.',
			'404text'    => 'Möglicherweise ist die Adresse falsch geschrieben oder die Seite wurde verschoben. Über die Navigation oder die Startseite finden Sie alle Inhalte von ServiceInvest.',
			'404home'    => 'Zur Startseite',
		),
		'en' => array(
			'skip'       => 'Skip to content',
			'newwin'     => '(opens in a new window)',
			'pause'      => 'Pause slideshow',
			'play'       => 'Resume slideshow',
			'senderror'  => 'Your message could not be sent. Please try again later or write to <a href="mailto:info@serviceinvest.de">info@serviceinvest.de</a>.',
			'msenderror' => 'Your request could not be sent. Please try again later or write to <a href="mailto:info@serviceinvest.de">info@serviceinvest.de</a>.',
			'expired'    => 'This page has been open for too long. Please reload it and send your enquiry again.',
			'limit'      => 'Too many requests in a short time. Please try again in a few minutes.',
			'nodocs'     => 'There are currently no documents.',
			'404kicker'  => 'Error 404',
			'404title'   => 'Page not found',
			'404h1'      => 'This page does not exist.',
			'404text'    => 'The address may be misspelt or the page may have moved. You can find all content from ServiceInvest via the navigation or the home page.',
			'404home'    => 'Home page',
		),
	)[ $lang ];
}

/** PAI-PDFs (HA 8): bisherige Adresse => neue Adresse. Die Dateien kopiert das Deploy aus der bisherigen Installation. */
function pai_pdfs(): array {
	return array(
		SITE . '/wp-content/uploads/2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf'  => '/dokumente/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf',
		SITE . '/wp-content/uploads/2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_ENG-clean.pdf' => '/dokumente/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_ENG-clean.pdf',
	);
}

/** Meldungen für die Antwort-Codes "expired" und "limit" (site.js setzt sie statt der allgemeinen Meldung ein). */
function send_error_attrs( array $t ): string {
	return ' data-expired="' . esc( $t['expired'] ) . '" data-limit="' . esc( $t['limit'] ) . '"';
}

function esc( string $s ): string {
	return htmlspecialchars( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/** Ersetzt genau $n Vorkommen (sonst Abbruch): schützt davor, dass eine Paketänderung still übergangen wird. */
function rep( string $html, string $from, string $to, string $file, int $n = 1 ): string {
	$c = substr_count( $html, $from );
	if ( $c !== $n ) {
		fail( "$file: '$from' erwartet {$n}×, gefunden {$c}×" );
	}
	return str_replace( $from, $to, $html );
}

function rep_re( string $html, string $re, $to, string $file, int $n = 1 ): string {
	$out = is_callable( $to ) ? preg_replace_callback( $re, $to, $html, -1, $c ) : preg_replace( $re, $to, $html, -1, $c );
	if ( $c !== $n ) {
		fail( "$file: Muster $re erwartet {$n}×, gefunden {$c}×" );
	}
	return $out;
}

/**
 * Löst <sc-if value="{{name}}" hint-placeholder-val="{{ true|false }}">…</sc-if> auf (innerste zuerst): Jedes Element auf
 * oberster Ebene des Inhalts bekommt data-if="name", bei false zusätzlich hidden. Der Platzhalterwert des Pakets ist überall
 * der Anfangszustand.
 */
function resolve_scif( string $html, string $file ): string {
	$re = '#<sc-if value="\{\{([\w.]+)\}\}" hint-placeholder-val="\{\{ (true|false) \}\}">((?:(?!<sc-if ).)*?)</sc-if>#s';
	while ( preg_match( $re, $html ) ) {
		$html = preg_replace_callback( $re, function ( $m ) use ( $file ) {
			$attr = ' data-if="' . $m[1] . '"' . ( 'false' === $m[2] ? ' hidden' : '' );
			return mark_top_level( $m[3], $attr, $file );
		}, $html );
	}
	return $html;
}

/** Fügt $attr in jedes Element auf oberster Ebene von $inner ein; Text auf oberster Ebene ist nicht erlaubt. */
function mark_top_level( string $inner, string $attr, string $file ): string {
	$void  = array( 'area', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'wbr' );
	$depth = 0;
	$out   = '';
	$pos   = 0;   // bis hier ist $inner nach $out übernommen
	$gap   = 0;   // Ende des letzten Elements auf oberster Ebene
	$found = 0;
	preg_match_all( '#<(/?)([a-zA-Z][\w-]*)\b[^>]*?(/?)>#', $inner, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );
	foreach ( $tags as $t ) {
		$off = $t[0][1];
		if ( 0 === $depth && '' !== trim( substr( $inner, $gap, $off - $gap ) ) ) {
			fail( "$file: Text außerhalb eines Elements in sc-if: " . substr( $inner, $gap, min( 200, $off - $gap ) ) );
		}
		$name = strtolower( $t[2][0] );
		if ( '/' === $t[1][0] ) {
			--$depth;
			if ( 0 === $depth ) {
				$gap = $off + strlen( $t[0][0] );
			}
		} else {
			if ( 0 === $depth ) {
				$open   = $t[0][0];
				$open   = substr( $open, 0, 1 + strlen( $t[2][0] ) ) . $attr . substr( $open, 1 + strlen( $t[2][0] ) );
				$out   .= substr( $inner, $pos, $off - $pos ) . $open;
				$pos    = $off + strlen( $t[0][0] );
				++$found;
			}
			if ( ! in_array( $name, $void, true ) && '/' !== $t[3][0] ) {
				++$depth;
			} elseif ( 0 === $depth ) {
				$gap = $off + strlen( $t[0][0] );
			}
		}
	}
	if ( 0 !== $depth || ! $found || '' !== trim( substr( $inner, $gap ) ) ) {
		fail( "$file: sc-if-Inhalt nicht wohlgeformt" );
	}
	return $out . substr( $inner, $pos );
}

/** Paket-Pfad (relativ zur Seite) => Ziel; .html-Seiten nach HA 5.1, alles andere absolut ab /. */
function map_url( string $ref, string $file, array $urls ): string {
	if ( '' === $ref || '#' === $ref[0] || '/' === $ref[0] || preg_match( '#^(https?:|mailto:|tel:|data:)#', $ref ) ) {
		return $ref;
	}
	$frag = '';
	if ( false !== ( $p = strpos( $ref, '#' ) ) ) {
		$frag = substr( $ref, $p );
		$ref  = substr( $ref, 0, $p );
	}
	$parts = array();
	foreach ( explode( '/', ( '.' === dirname( $file ) ? '' : dirname( $file ) . '/' ) . $ref ) as $seg ) {
		if ( '..' === $seg ) {
			array_pop( $parts );
		} elseif ( '' !== $seg && '.' !== $seg ) {
			$parts[] = $seg;
		}
	}
	$path = implode( '/', $parts );
	if ( str_ends_with( $path, '.html' ) ) {
		if ( ! isset( $urls[ $path ] ) ) {
			fail( "$file: Link auf unbekannte Seite $path" );
		}
		return $urls[ $path ] . $frag;
	}
	return '/' . $path . $frag;
}

/** Bildvarianten aus content/v1-build/assets/img/v/ (tools/v1/images.mjs). */
function variants( string $dst, string $base ): array {
	$out = array();
	foreach ( glob( "$dst/assets/img/v/$base-*.jpg" ) as $f ) {
		if ( preg_match( '#-(\d+)\.jpg$#', $f, $m ) ) {
			$out[ (int) $m[1] ] = getimagesize( $f );
		}
	}
	ksort( $out );
	if ( ! $out ) {
		fail( "Bildvarianten für $base fehlen (tools/v1/images.mjs)" );
	}
	return $out;
}

function srcset( string $base, array $v, string $ext ): string {
	return implode( ', ', array_map( fn( $w ) => "/assets/img/v/$base-$w.$ext {$w}w", array_keys( $v ) ) );
}

// ---------------------------------------------------------------------------------------------------------------

$pages = pages();
$urls  = array();
foreach ( $pages as $list ) {
	foreach ( $list as $f => $p ) {
		$urls[ $f ] = $p[0];
	}
}
$pairs = array_combine( array_keys( $pages['de'] ), array_keys( $pages['en'] ) );
$pair  = $pairs + array_flip( $pairs );

$found = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	if ( 'html' === $f->getExtension() ) {
		$found[] = str_replace( '\\', '/', substr( $f->getPathname(), strlen( $src ) + 1 ) );
	}
}
sort( $found );
$expected = array_keys( $urls );
sort( $expected );
if ( $found !== $expected ) {
	fail( "Seitenliste und Paket passen nicht zusammen:\n  nur im Paket: " . implode( ', ', array_diff( $found, $expected ) ) . "\n  nur in der Liste: " . implode( ', ', array_diff( $expected, $found ) ) );
}

// Paketdateien, die keine Seiten sind: assets/ (Schrift, Logos, Original-Bilder). support.js und README.md bleiben draußen.
@mkdir( "$dst/assets", 0777, true );
foreach ( array( 'fonts', 'logo', 'img' ) as $d ) {
	@mkdir( "$dst/assets/$d", 0777, true );
	foreach ( glob( "$src/assets/$d/*" ) as $f ) {
		if ( is_file( $f ) ) {
			copy( $f, "$dst/assets/$d/" . basename( $f ) );
		}
	}
}
@mkdir( "$dst/assets/js", 0777, true );
copy( "$add/site.js", "$dst/assets/js/site.js" );
$js_ver   = substr( md5_file( "$add/site.js" ), 0, 10 );
$site_css = trim( (string) file_get_contents( "$add/site.css" ) );
foreach ( array( 'assets/og/serviceinvest.jpg', 'apple-touch-icon.png', 'favicon.ico' ) as $f ) {
	if ( ! is_file( "$dst/$f" ) ) {
		fail( "$f fehlt (tools/v1/build.sh erzeugt es)" );
	}
}

$org = array(
	'@context'           => 'https://schema.org',
	'@type'              => 'Organization',
	'name'               => 'ServiceInvest Kapitalverwaltungsgesellschaft mbH',
	'alternateName'      => 'ServiceInvest',
	'url'                => SITE . '/',
	'logo'               => SITE . '/assets/logo/serviceinvest-logo-farbe.png',
	'email'              => 'info@serviceinvest.de',
	'telephone'          => '+49 89 544344-0',
	'address'            => array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => 'Fürstenrieder Straße 61',
		'postalCode'      => '80686',
		'addressLocality' => 'München',
		'addressCountry'  => 'DE',
	),
	'parentOrganization' => array( '@type' => 'Organization', 'name' => 'Institutional Investment Group', 'url' => 'https://www.2ig.de/' ),
);

$sitemap = array();
foreach ( $pages as $lang => $list ) {
	$t = texts( $lang );
	foreach ( $list as $file => list( $url, $desc ) ) {
		$html = (string) file_get_contents( "$src/$file" );
		$re   = '#^<!doctype html>\n<html lang="' . $lang . '">\n<head>\n(.*?)</head>\n<body>\n<x-dc>\n<helmet>\n<style>\n?(.*?)</style>\n</helmet>\n(.*?)</x-dc>\n<script type="text/x-dc" data-dc-script[^>]*>.*?</script>\n</body>\n</html>\n?$#s';
		if ( ! preg_match( $re, $html, $m ) ) {
			fail( "$file: Aufbau (head, x-dc, helmet, Logik-Skript) nicht erkannt" );
		}
		list( , $head, $css, $body ) = $m;
		if ( ! preg_match( '#^<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<link rel="icon" type="image/svg\+xml" href="(?:\.\./)?assets/logo/serviceinvest-bildmarke-farbe\.svg">\n<title>([^<]+)</title>\n<script src="(?:\.\./)?support\.js"></script>\n$#', $head, $hm ) ) {
			fail( "$file: Kopf weicht vom erwarteten Aufbau ab" );
		}
		$title = $hm[1];

		// --- CSS: Schrift absolut, Ergänzungen anhängen ---
		$css = rep_re( $css, '#url\((?:\.\./)?assets/fonts/#', 'url(/assets/fonts/', $file );
		$css = rtrim( $css ) . "\n/* Ergänzungen (content/v1-zusatz/site.css) */\n" . $site_css;

		// --- Body: Vorlagen-Elemente auflösen ---
		$body = rep( $body, 'aria-expanded="{{menuExp}}" onClick="{{openMenu}}"', 'aria-expanded="false" data-menu-open', $file );
		$body = rep( $body, ' onClick="{{closeMenu}}"', '', $file, substr_count( $body, ' onClick="{{closeMenu}}"' ) );
		$body = rep( $body, 'aria-label="' . ( 'de' === $lang ? 'Menü schließen' : 'Close menu' ) . '"', 'aria-label="' . ( 'de' === $lang ? 'Menü schließen' : 'Close menu' ) . '" data-menu-close', $file );

		if ( str_ends_with( $file, 'index.html' ) ) {
			$body = rep( $body, 'class="{{fx}}"', 'class="fx"', $file );
			for ( $k = 0; $k < 5; $k++ ) {
				$body = rep( $body, 'class="{{c' . $k . '}}"', 'class="' . ( 0 === $k ? 'slide on' : 'slide' ) . '"', $file );
				$body = rep( $body, 'class="{{s' . $k . '}}"', 'class="' . ( 0 === $k ? 'seg on' : 'seg' ) . '"' . ( 0 === $k ? ' aria-current="true"' : '' ), $file );
				$body = rep( $body, ' onClick="{{go' . $k . '}}"', ' data-go="' . $k . '"', $file );
			}
			$body = rep( $body, 'class="{{cntCls}}"', 'class="num cnt"', $file, 4 );
			$body = rep( $body, 'class="{{seqR}}"', 'class="stack rise seq"', $file );
			$body = rep( $body, 'class="{{seqL}}"', 'class="stack2 stack1s rise seq"', $file );
			$body = rep( $body, 'class="{{seqT}}"', 'class="stack rise seq"', $file );
			$body = rep( $body, 'class="{{seqE}}"', 'class="stack rise seq"', $file );
			$body = rep( $body, 'class="{{lageCls}}"', 'class="bgimg zoom approach"', $file );
			// Pause-Schaltfläche (HA 4.2, 11) hinter den Fortschrittsbalken; auf Mobilgeräten allein sichtbar (site.css .sg)
			$icons = '<svg class="i-pause" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M3 2h3v12H3zM10 2h3v12h-3z"></path></svg>'
				. '<svg class="i-play" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M4 2l10 6-10 6z"></path></svg>';
			$body  = rep_re( $body, '#(<button type="button" class="seg" aria-label="[^"]+ 5 [^"]+" data-go="4"><i></i></button>\n)#', '$1<button type="button" class="seg-pause" data-pause aria-pressed="false" aria-label="' . esc( $t['pause'] ) . '" data-label-pause="' . esc( $t['pause'] ) . '" data-label-play="' . esc( $t['play'] ) . '">' . $icons . "</button>\n", $file );
			$body  = rep( $body, '<div class="hide-m" role="group"', '<div class="hide-m sg" role="group"', $file );
		}

		if ( str_contains( $body, '<sc-for' ) ) {   // Anlegerzugang
			$body = rep( $body, 'aria-selected="{{instSel}}" onClick="{{showInst}}"', 'aria-selected="true" data-tab="inst"', $file );
			$body = rep( $body, 'aria-selected="{{privSel}}" onClick="{{showPriv}}"', 'aria-selected="false" tabindex="-1" data-tab="priv"', $file );
			$body = rep_re( $body, '#<sc-for list="\{\{funds\}\}" as="f" hint-placeholder-count="2">\n(.*?)</sc-for>\n#s', function ( $m ) use ( $lang, $t ) {
				$names = array( 'Debeka Immobilien Invest I', 'TAS Wohnen I' );
				$out   = '';
				foreach ( $names as $i => $n ) {
					$li   = str_replace( '{{f.open}}', '{{fundOpen}}', $m[1] );
					$li   = str_replace( '{{f.name}}', esc( $n ), $li );
					$li   = str_replace( '{{f.pid}}', "pw-$i", $li );
					$li   = str_replace( 'aria-expanded="{{f.expanded}}" onClick="{{f.toggle}}">{{f.label}}', 'aria-expanded="false" aria-controls="fund-form-' . $i . '" data-fund-toggle="' . $i . '" data-label-open="' . ( 'de' === $lang ? 'Zugang' : 'Access' ) . '" data-label-close="' . ( 'de' === $lang ? 'Schließen' : 'Close' ) . '">' . ( 'de' === $lang ? 'Zugang' : 'Access' ), $li );
					$li   = str_replace( '<form aria-label=', '<form id="fund-form-' . $i . '" novalidate aria-label=', $li );
					$li   = preg_replace( '#<button type="button" class="btn btn-fill" style="min-height: 48px">([^<]+)</button></form>#', '<button type="submit" class="btn btn-fill" style="min-height: 48px">$1</button><p class="az-msg" role="alert" hidden></p><ul class="az-docs" hidden></ul></form>', $li, 1, $c );
					$out .= $li;
					if ( 1 !== $c ) {
						fail( 'Anlegerzugang: Schaltfläche im Passwortformular nicht gefunden' );
					}
				}
				return $out;
			}, $file );
			$body = rep( $body, '<ul class="list">' . "\n" . '<li style="padding: 22px 0">' . "\n" . '<div style="display: flex; justify-content: space-between',
				'<ul class="list" data-funds data-endpoint="" data-empty="' . esc( $t['nodocs'] ) . '">' . "\n" . '<li style="padding: 22px 0">' . "\n" . '<div style="display: flex; justify-content: space-between', $file );
		}

		if ( str_contains( $body, '{{setAnliegen}}' ) ) {   // Kontakt
			$body = rep_re( $body, '#(<(?:input|select|textarea)\b[^>]*) value="\{\{\w+\}\}"#', '$1', $file, 6 );
			$body = rep_re( $body, '# onChange="\{\{set\w+\}\}"#', '', $file, 7 );
			$body = rep_re( $body, '#aria-invalid="\{\{inv\w+\}\}" aria-describedby="\{\{db\w+\}\}"#', 'aria-invalid="false"', $file, 4 );
			$empty = 'de' === $lang ? 'Bitte geben Sie Ihre E-Mail-Adresse an.' : 'Please enter your email address.';
			$fmt   = 'de' === $lang ? 'Bitte prüfen Sie die E-Mail-Adresse, zum Beispiel name@unternehmen.de.' : 'Please check the email address, for example name@company.com.';
			$body  = rep( $body, '<p id="e-email" class="err">{{errEmailText}}</p>', '<p id="e-email" class="err" data-empty="' . esc( $empty ) . '" data-format="' . esc( $fmt ) . '">' . esc( $empty ) . '</p>', $file );
			$body  = rep( $body, ' onSubmit="{{submit}}"', ' id="k-form" method="post" action="" data-endpoint="" novalidate', $file );
			$body  = rep( $body, ' onClick="{{reset}}"', ' data-reset', $file );
			$body  = rep_re( $body, '#(\n<div>\n<button type="submit" class="btn btn-fill">)#', "\n<p class=\"err\" role=\"alert\" data-send-error" . send_error_attrs( $t ) . ' hidden>' . $t['senderror'] . '</p>$1', $file );
		}

		if ( str_contains( $body, 'hausverwaltung' ) && str_contains( $body, ' onSubmit="{{submit}}"' ) ) {   // Mieter
			$body = rep( $body, ' onSubmit="{{submit}}"', ' id="m-form" method="post" action="" data-endpoint=""', $file );
			$body = rep( $body, ' onClick="{{reset}}"', ' data-reset', $file );
			$body = rep_re( $body, '#(\n<div><button type="submit" class="btn btn-fill">)#', "\n<p role=\"alert\" data-send-error" . send_error_attrs( $t ) . " hidden style=\"margin: 0; padding: 16px; border: 1px solid #FFFFFF; font-size: 16px; line-height: 1.5\">" . $t['msenderror'] . '</p>$1', $file );
		}

		$body = resolve_scif( $body, $file );
		foreach ( array( '{{', '}}', '<sc-', 'onClick=', 'onChange=', 'onSubmit=', '<x-dc', '<helmet' ) as $left ) {
			if ( str_contains( $body, $left ) ) {
				fail( "$file: Vorlagen-Rest '$left' im Ergebnis" );
			}
		}

		// --- Adressen ---
		$body = str_replace( array_keys( pai_pdfs() ), array_values( pai_pdfs() ), $body );
		$body = preg_replace_callback( '#\b(href|src)="([^"]*)"#', fn( $m ) => $m[1] . '="' . map_url( $m[2], $file, $urls ) . '"', $body );

		// --- Bilder als <picture> (HA 9); erstes Bild im Hero/Kopfbereich mit hoher Priorität, alle übrigen lazy ---
		$first   = true;
		$preload = '';
		$body    = preg_replace_callback( '#<img ([^>]*?)src="/assets/img/([\w-]+)\.jpg"([^>]*)>#', function ( $m ) use ( &$first, &$preload, $dst ) {
			$base = $m[2];
			$v    = variants( $dst, $base );
			$max  = max( array_keys( $v ) );
			$wh   = ' width="' . $v[ $max ][0] . '" height="' . $v[ $max ][1] . '"';
			$load = $first ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"';
			if ( $first ) {
				$preload = '<link rel="preload" as="image" type="image/avif" imagesrcset="' . srcset( $base, $v, 'avif' ) . '" imagesizes="100vw" fetchpriority="high">';
			}
			$first = false;
			$fallback = in_array( 1600, array_keys( $v ), true ) ? 1600 : $max;
			return '<picture><source type="image/avif" srcset="' . srcset( $base, $v, 'avif' ) . '" sizes="100vw"><source type="image/webp" srcset="' . srcset( $base, $v, 'webp' ) . '" sizes="100vw">'
				. '<img ' . $m[1] . 'src="/assets/img/v/' . $base . '-' . $fallback . '.jpg" srcset="' . srcset( $base, $v, 'jpg' ) . '" sizes="100vw"' . $wh . $load . $m[3] . '></picture>';
		}, $body );
		if ( preg_match( '#<img [^>]*src="/assets/img/[^"]*\.jpg"(?![^>]*srcset)#', $body ) ) {
			fail( "$file: Bild ohne Varianten" );
		}

		// --- Externe Links: neuer Tab, Hinweis für Screenreader per aria-describedby auf einen verborgenen Text (HA 8).
		// Ein Text im Link selbst änderte die Zeilenumbrüche langer URLs. ---
		$body = preg_replace_callback( '#<a ([^>]*\bhref="https?://(?!www\.serviceinvest\.de)[^"]*"[^>]*)>#', function ( $m ) {
			$attrs = $m[1];
			if ( ! str_contains( $attrs, 'target=' ) ) {
				$attrs .= ' target="_blank" rel="noopener"';
			}
			return '<a ' . $attrs . ' aria-describedby="newwin">';
		}, $body, -1, $ext_links );
		if ( $ext_links ) {
			$body = rtrim( $body ) . "\n" . '<span id="newwin" hidden>' . esc( $t['newwin'] ) . "</span>\n";
		}

		// --- Skip-Link vor dem Inhalt; Ziel ist die Hauptüberschrift (HA 11) ---
		$target = str_contains( $body, 'id="hero-title"' ) ? 'hero-title' : 'page-title';
		$body   = rep_re( $body, '#^(\s*<div lang="' . $lang . '"[^>]*>\n)#', '$1<a class="skip" href="#' . $target . '">' . esc( $t['skip'] ) . "</a>\n", $file );
		$body   = rep( $body, 'id="' . $target . '"', 'id="' . $target . '" tabindex="-1"', $file );

		// --- Kopf ---
		$other  = $pair[ $file ];
		$self   = SITE . $url;
		$de_url = SITE . ( 'de' === $lang ? $url : $urls[ $other ] );
		$en_url = SITE . ( 'en' === $lang ? $url : $urls[ $other ] );
		$h      = array(
			'<meta charset="utf-8">',
			'<meta name="viewport" content="width=device-width, initial-scale=1">',
			'<title>' . $title . '</title>',
			'<meta name="description" content="' . esc( $desc ) . '">',
			'<link rel="canonical" href="' . $self . '">',
			'<link rel="alternate" hreflang="de" href="' . $de_url . '">',
			'<link rel="alternate" hreflang="en" href="' . $en_url . '">',
			'<link rel="alternate" hreflang="x-default" href="' . $de_url . '">',
			'<meta property="og:type" content="website">',
			'<meta property="og:site_name" content="ServiceInvest">',
			'<meta property="og:locale" content="' . ( 'de' === $lang ? 'de_DE' : 'en_GB' ) . '">',
			'<meta property="og:locale:alternate" content="' . ( 'de' === $lang ? 'en_GB' : 'de_DE' ) . '">',
			'<meta property="og:title" content="' . $title . '">',
			'<meta property="og:description" content="' . esc( $desc ) . '">',
			'<meta property="og:url" content="' . $self . '">',
			'<meta property="og:image" content="' . SITE . '/assets/og/serviceinvest.jpg">',
			'<meta property="og:image:width" content="1200">',
			'<meta property="og:image:height" content="630">',
			'<meta property="og:image:alt" content="ServiceInvest">',
			'<meta name="twitter:card" content="summary_large_image">',
			'<link rel="icon" type="image/svg+xml" href="/assets/logo/serviceinvest-bildmarke-farbe.svg">',
			'<link rel="icon" href="/favicon.ico" sizes="32x32">',
			'<link rel="apple-touch-icon" href="/apple-touch-icon.png">',
			'<link rel="preload" href="/assets/fonts/source-sans-3-variable.woff2" as="font" type="font/woff2" crossorigin>',
		);
		if ( $preload ) {
			$h[] = $preload;
		}
		$h[] = "<style>\n" . trim( $css ) . "\n</style>";
		if ( str_contains( $body, 'data-if="isPriv"' ) && str_contains( $body, 'role="tabpanel"' ) ) {
			// ohne JavaScript beide Anlegergruppen zeigen (HA 4.2)
			$h[] = '<noscript><style>[role="tabpanel"][hidden]{display:block !important}</style></noscript>';
		}
		$h[] = '<script src="/assets/js/site.js?v=' . $js_ver . '" defer></script>';
		if ( '/' === $url || '/en/' === $url ) {
			$h[] = '<script type="application/ld+json">' . json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
		}

		$out = "<!doctype html>\n<html lang=\"$lang\">\n<head>\n" . implode( "\n", $h ) . "\n</head>\n<body>\n" . trim( $body ) . "\n</body>\n</html>\n";
		@mkdir( dirname( "$dst/$file" ), 0777, true );
		file_put_contents( "$dst/$file", $out );
		$sitemap[] = array( $self, $de_url, $en_url );
		printf( "%-28s -> %s\n", $file, $url );
	}
}

/*
 * Fehlerseiten de/en (HA 5.2): Kopf und Fuß des Impressums, schwarzer Titelbereich mit Hinweis und Schaltflächen
 * (Startseite, Kontakt). Ohne canonical/hreflang/Open Graph, noindex. Der Sprachumschalter führt auf die Startseiten.
 */
foreach ( array( 'de' => array( 'impressum.html', '/', '/kontakt/', 'Kontakt' ), 'en' => array( 'en/legal-notice.html', '/en/', '/en/contact/', 'Contact' ) ) as $lang => list( $file, $home, $contact, $clabel ) ) {
	$t    = texts( $lang );
	$html = (string) file_get_contents( "$dst/$file" );
	$html = preg_replace( '#<title>[^<]*</title>#', '<title>' . esc( $t['404title'] ) . ' – ServiceInvest</title>', $html );
	$html = preg_replace( '#\n<(?:link rel="(?:canonical|alternate)"|meta (?:property="og:|name="twitter:|name="description"))[^>]*>#', '', $html );
	$html = str_replace( '<meta name="viewport" content="width=device-width, initial-scale=1">', "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<meta name=\"robots\" content=\"noindex\">", $html );
	$html = str_replace( array( 'href="/en/legal-notice/"', 'href="/impressum/"' ), array( 'href="/en/"', 'href="/"' ), $html );
	$html = str_replace( ' aria-current="page"', '', $html );
	$main = '<section aria-labelledby="page-title" style="background: #000000; padding: clamp(72px, 11vh, 128px) 0 clamp(56px, 8vh, 96px)">' . "\n<div class=\"wrap intro\">\n"
		. '<div class="kicker">' . esc( $t['404kicker'] ) . '</div><h1 id="page-title" tabindex="-1" class="h1" style="font-size: clamp(40px, 5.2vw, 80px); max-width: 1100px">' . esc( $t['404h1'] ) . "</h1>\n"
		. '<p style="margin: 28px 0 0; font-size: 20px; line-height: 1.55; color: #E0E0E0; max-width: 860px">' . esc( $t['404text'] ) . "</p>\n"
		. '<div style="display: flex; flex-wrap: wrap; gap: 16px; margin-top: 40px"><a href="' . $home . '" class="btn btn-fill">' . esc( $t['404home'] ) . '</a><a href="' . $contact . '" class="btn">' . $clabel . "</a></div>\n</div>\n</section>\n";
	$html = rep_re( $html, '#<section aria-labelledby="page-title".*?</section>\n<section class="light".*?</section>\n#s', $main, "404-$lang" );
	@mkdir( "$dst/_fehler", 0777, true );
	file_put_contents( "$dst/_fehler/404-$lang.html", $html );
	echo "_fehler/404-$lang.html\n";
}

// sitemap.xml mit beiden Sprachen (hreflang), robots.txt mit Verweis (HA 10). Geschützte Dokumente sind nicht verlinkt.
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
foreach ( $sitemap as list( $loc, $de, $en ) ) {
	$xml .= "<url><loc>$loc</loc><xhtml:link rel=\"alternate\" hreflang=\"de\" href=\"$de\"/><xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"$en\"/><xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"$de\"/></url>\n";
}
file_put_contents( "$dst/sitemap.xml", $xml . "</urlset>\n" );
file_put_contents( "$dst/robots.txt", "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . SITE . "/sitemap.xml\n" );

echo count( $sitemap ) . " Seiten, 2 Fehlerseiten, sitemap.xml, robots.txt -> content/v1-build/\n";
