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

	'pages'    => array(
		// 'startseite'     => array( 'slug' => 'home',           'title' => 'Home',           'status' => 'publish' ),
		// 'nachhaltigkeit' => array( 'slug' => 'sustainability', 'title' => 'Sustainability', 'status' => 'publish' ),
	),

	// Menüs, an deren Ende der Sprachumschalter steht (Slug des deutschen Menüs).
	'switcher_menus' => array( 'menu' ),

	// Englische Bezeichnungen der Menüeinträge (deutscher Titel => englischer Titel).
	'menu_labels' => array(
		'Nachhaltigkeit'                 => 'Sustainability',
		'Karriere'                       => 'Careers',
		'Kontakt'                        => 'Contact',
		'Impressum'                      => 'Legal notice',
		'Datenschutzerklärung'           => 'Privacy policy',
		'Datenschutzhinweise für Mieter' => 'Privacy notice for tenants',
	),

	'glossary' => array(
		'Kapitalverwaltungsgesellschaft' => 'KVG (German capital management company)',
		'KVG-Insourcing'                 => 'KVG insourcing',
		'Asset Servicing Division'       => 'Asset Servicing Division',
		'Partner-Fonds'                  => 'partner funds',
		'Real Assets'                    => 'Real Assets',
		'Asset Manager'                  => 'Asset Manager',
		'AIFMControl'                    => 'AIFMControl',
		'Middle und Back Office'         => 'middle and back office',
		'Meldewesen'                     => 'regulatory reporting',
		'Investorenberichtswesen'        => 'investor reporting',
	),
);
