<?php
/**
 * Konfiguration der Übersetzung DE -> EN (ServiceInvest).
 *
 * pages:    deutscher Slug => englischer Slug, englischer Titel (null = DeepL), Status der englischen Seite.
 *           Nur hier eingetragene Seiten werden übersetzt. Noch leer: die Inhalte werden zuerst auf Deutsch fertiggestellt.
 * glossary: feste Übersetzungen für DeepL (Quelle => Ziel), z. B. Fachbegriffe und Namen.
 */
return array(
	'source'   => 'DE',
	'target'   => 'EN-GB',

	// Startseite: DeepL. Alle übrigen Seiten: offizielle englische Texte (tools/build/build_sin_en.py + tools/i18n/official.php).
	// Die alten englischen Adressen (/sustainability/, /home-en/ …) leiten per mu-plugins/sin-redirects.php hierher um.
	'pages'    => array(
		'startseite'                    => array( 'slug' => 'home',                          'title' => 'Home',                        'status' => 'publish' ),
		'nachhaltigkeit-2'              => array( 'slug' => 'sustainability',                'title' => 'Sustainability',              'status' => 'publish' ),
		'erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2'
		                                => array( 'slug' => 'statement-on-principal-adverse-impacts', 'title' => 'PAI Statement',  'status' => 'publish' ),
		'debeka-immobilien-invest-i'    => array( 'slug' => 'debeka-immobilien-invest-i-en', 'title' => 'Debeka Immobilien Invest I',  'status' => 'publish' ),
		'tas-wohnen-1'                  => array( 'slug' => 'tas-wohnen-1-en',               'title' => 'TAS Wohnen I',                'status' => 'publish' ),
		'serviceinvest-impressum'       => array( 'slug' => 'imprint',                       'title' => 'Imprint',                     'status' => 'publish' ),
		'datenschutzerklaerung'         => array( 'slug' => 'privacy-policy',                'title' => 'Privacy Policy',              'status' => 'publish' ),
		'datenschutzhinweisefuermieter' => array( 'slug' => 'data-protection-information',   'title' => 'Data Protection Information', 'status' => 'publish' ),
		'beschwerdestelle'              => array( 'slug' => 'complaints-office',             'title' => 'Complaints Office',           'status' => 'publish' ),
		'verguetungspolitik'            => array( 'slug' => 'remuneration-policy',           'title' => 'Remuneration Policy',         'status' => 'publish' ),
	),

	// Menüs, an deren Ende der Sprachumschalter steht (Slug des deutschen Menüs).
	'switcher_menus' => array( 'menu' ),

	// Englische Bezeichnungen der Menüeinträge (deutscher Titel => englischer Titel).
	'menu_labels' => array(
		'Nachhaltigkeit'                 => 'Sustainability',
		'Karriere'                       => 'Careers',
		'Kontakt'                        => 'Contact',
		'Impressum'                      => 'Imprint',
		'Datenschutzerklärung'           => 'Privacy policy',
		'Datenschutzhinweise für Mieter' => 'Data protection information',
		'Fonds'                          => 'Funds',
		'Beschwerdestelle'               => 'Complaints office',
		'Vergütungspolitik'              => 'Remuneration policy',
	),

	'glossary' => array(
		// Firmennamen bleiben unverändert
		'ServiceInvest Kapitalverwaltungsgesellschaft mbH' => 'ServiceInvest Kapitalverwaltungsgesellschaft mbH',
		'ServiceInvest Kapitalverwaltungsgesellschaft'     => 'ServiceInvest Kapitalverwaltungsgesellschaft',
		'KVG-Insourcing'                 => 'KVG insourcing',
		'Asset Servicing Division'       => 'Asset Servicing Division',
		'Partner-Fonds'                  => 'partner funds',
		'Real Assets'                    => 'Real Assets',
		'Real Asset-Vermögen'            => 'real assets',
		'Real Asset-Investments'         => 'real asset investments',
		'Real Asset-Fonds'               => 'real asset funds',
		'Software-Lösung'                => 'software solution',
		'Asset Manager'                  => 'Asset Manager',
		'AIFMControl'                    => 'AIFMControl',
		'Middle und Back Office'         => 'middle and back office',
		'Meldewesen'                     => 'regulatory reporting',
		'Investorenberichtswesen'        => 'investor reporting',
	),
);
