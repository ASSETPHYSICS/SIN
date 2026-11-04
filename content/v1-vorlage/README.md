# serviceinvest.de – Website-Export

Stand: 3. November 2026 · Deutsch und Englisch

## Ansehen

`index.html` im Browser öffnen. Alle Seiten funktionieren auch ohne Webserver, inklusive Menü, Bild-Slider,
Formularprüfung und Reiter im Anlegerzugang. Für die Veröffentlichung den gesamten Ordner auf einen Webserver legen.

## Struktur

| Ordner / Datei | Inhalt |
|---|---|
| `*.html` | Deutsche Seiten (Startseite `index.html`) |
| `en/*.html` | Englische Seiten (Startseite `en/index.html`) |
| `assets/img/` | Bilder (KI-generiert, Nutzungsrechte: Institutional Investment Group GmbH) |
| `assets/fonts/` | Source Sans 3 (Variable Font, SIL Open Font License) |
| `assets/logo/` | ServiceInvest-Logo in Weiß (Website), Farbe und Schwarz sowie die Bildmarke, jeweils SVG und PNG |
| `support.js` | Laufzeitbibliothek des Entwurfswerkzeugs, rendert die Seiten im Browser |

## Seiten

| Deutsch | Englisch |
|---|---|
| index.html – Startseite | en/index.html – Home |
| nachhaltigkeit.html | en/sustainability.html |
| anlegerzugang.html | en/investor-access.html |
| mieter.html | en/tenants.html |
| kontakt.html | en/contact.html |
| impressum.html | en/legal-notice.html |
| datenschutz.html | en/privacy.html |
| datenschutz-mieter.html | en/privacy-tenants.html |
| pai-statement.html | en/pai-statement.html |
| stewardship-policy.html | en/stewardship-policy.html |
| verguetungspolitik.html | en/remuneration-policy.html |
| beschwerdestelle.html | en/complaints.html |

Die Rechtstexte der englischen Fassung enthalten den Hinweis, dass nur die deutsche Fassung verbindlich ist.

## Hinweise für die Umsetzung

Die ausführliche Anleitung für Live-Schaltung und Web-Optimierung steht in
`Handlungsanweisung_Live-Schaltung_serviceinvest.pdf`. Die folgende Liste fasst die wichtigsten Punkte zusammen.

Der Export ist ein vollständiger, funktionsfähiger Entwurf. Für den Livebetrieb sind noch folgende Punkte umzusetzen:

1. **Formulare:** Kontakt- und Mieterformular prüfen die Eingaben, versenden aber noch nichts. Sie müssen an einen
   Mailversand oder ein CRM angebunden werden.
2. **Fondspasswörter im Anlegerzugang:** Die Passwortfelder für Debeka Immobilien Invest I und TAS Wohnen I sind nur
   gestaltet. Die Prüfung muss serverseitig erfolgen; die Offenlegungen dürfen erst nach erfolgreicher Prüfung
   ausgeliefert werden.
3. **PAI Statement:** Die beiden PDFs (deutsch und englisch) liegen noch unter `serviceinvest.de/wp-content/…` und
   müssen auf den neuen Server übernommen und die Links angepasst werden.
4. **Externer Link UST XXIV:** Den Link auf www.ust-xxiv.com vor dem Livegang prüfen.
5. **Technik:** Die Seiten werden von `support.js` im Browser aufgebaut. Für den Dauerbetrieb, Suchmaschinen und
   Barrierefreiheit empfiehlt es sich, Markup, CSS und Verhalten in das Zielsystem der Agentur (CMS oder statischer
   Seitengenerator) zu übernehmen. Gestaltung, Texte, Bilder und Effekte sind dafür vollständig in den Dateien enthalten.
6. **Cookies und Tracking:** Die Seiten setzen keine Cookies und binden keine Dienste Dritter ein. Kommt Tracking hinzu,
   müssen Datenschutzerklärung und Einwilligungsabfrage angepasst werden.
