<?php
/**
 * Konfiguration der Übersetzung DE -> EN.
 *
 * pages:    deutscher Slug => englischer Slug, englischer Titel (null = DeepL), Status der englischen Seite.
 *           Nur hier eingetragene Seiten werden übersetzt. Rechtstexte und Fondsseiten erst nach Freigabe.
 * glossary: feste Übersetzungen für DeepL (Quelle => Ziel), z. B. Fachbegriffe und Namen.
 */
return array(
	'source'   => 'DE',
	'target'   => 'EN-GB',

	'pages'    => array(
		'startseite'      => array( 'slug' => 'home',            'title' => 'Home',            'status' => 'publish' ),
		'nachhaltigkeit'  => array( 'slug' => 'sustainability',  'title' => 'Sustainability',  'status' => 'publish' ),
		// Polylang (kostenlos) erlaubt denselben Slug nicht in zwei Sprachen.
		'second-opinions' => array( 'slug' => 'second-opinion',  'title' => 'Second Opinions', 'status' => 'publish' ),
	),

	// Menüs, an deren Ende der Sprachumschalter steht (Slug des deutschen Menüs).
	'switcher_menus' => array( 'menu' ),

	// Englische Bezeichnungen der Menüeinträge (deutscher Titel => englischer Titel).
	// Seiten ohne englische Fassung verlinken weiter auf die deutsche Seite.
	'menu_labels' => array(
		'Nachhaltigkeit'                                => 'Sustainability',
		'Ausgewählte Fonds'                             => 'Selected funds',
		'Karriere'                                      => 'Careers',
		'Kontakt'                                       => 'Contact',
		'Impressum'                                     => 'Legal notice',
		'Datenschutzerklärung'                          => 'Privacy policy',
		'Datenschutzhinweise für Mieter – 2IP GmbH'     => 'Privacy notice for tenants – 2IP GmbH',
		'Datenschutzhinweise für Mieter – 2IP S.à.r.l.' => 'Privacy notice for tenants – 2IP S.à.r.l.',
	),

	'glossary' => array(
		'Investoren-Master-KVG'   => 'Investor Master KVG',
		'Master-KVG'              => 'Master KVG',
		'Spezial-AIF'             => 'special AIF',
		'Real Assets'             => 'Real Assets',
		'Real Asset-Investments'  => 'Real Asset investments',
		'Investment Consulting'   => 'Investment Consulting',
		'Second Opinion'          => 'Second Opinion',
		'Second Opinions'         => 'Second Opinions',
		'Manager Selection'       => 'Manager Selection',
		'Asset Manager'           => 'Asset Manager',
		'Property Manager'        => 'Property Manager',
		'Hausverwaltung'          => 'property management',
		'Anlageausschüsse'        => 'investment committees',
		'Investor Solutions Division' => 'Investor Solutions Division',
		'Asset Servicing Division'    => 'Asset Servicing Division',
	),
);
