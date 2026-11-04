# ServiceInvest – neue Website (Paket serviceinvest-website auf WordPress)

Branch `v1-website`: Die abgenommene Website der ServiceInvest Kapitalverwaltungsgesellschaft mbH (Deutsch, Englisch, Stand
3. November 2026) als WordPress-Seiten. Grundlage: `content/Handlungsanweisung_Live-Schaltung_serviceinvest.pdf` v1.0 (im Folgenden
„HA“). Der Branch `main` enthält die bisherige Divi-Website.

| Pfad | Zweck |
|---|---|
| `content/v1-vorlage/` | Das Paket unverändert (24 Seiten, `assets/`, `support.js`, `README.md`) |
| `content/v1-zusatz/` | Ergänzungen, die nicht im Paket stehen: `site.js` (ersetzt `support.js`), `site.css` |
| `content/v1-build/` | Ergebnis von `tools/v1/build.sh`: statisches HTML ohne `support.js`, Bildvarianten, Favicons, Sitemap (versioniert) |
| `wp-content/themes/sin-v1/` | Theme: gibt Head und Body jeder Seite unverändert aus; Formulare, Fonds-Offenlegungen, Weiterleitungen, Header |
| `wp-content/mu-plugins/` | `sin-hardening.php`, `site-mail.php` (wie auf allen Seiten), `local-mail-capture.php` (nur lokal) |
| `tools/v1/` | `build.sh`/`build.php`/`images.mjs` (Paket → Build), `import.php` (Build → WordPress), `fonds-*.php`, Prüfungen |
| `deploy/v1/` | Deploy auf IONOS (`~/public/_sin_v1`), siehe `deploy/v1/README.md` |

## Lokal (WSL)

Datenbank `sin_v1` (Präfix `v1_`), Zugangsdaten nur in `wp-config.php`. WordPress-Kern ist nicht versioniert.
Bildwerkzeuge einmalig: `mkdir ~/sin-imgtools && cd ~/sin-imgtools && npm init -y && npm install sharp@0.34.4 axe-core@4.10.3`.

```bash
bash tools/v1/build.sh                                         # Paket -> content/v1-build/ (Bilder nur, wenn neu)
bash tools/v1/local-setup.sh                                   # Build-Dateien, Theme, Import
PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8092 router.php        # http://localhost:8092
php tools/v1/check.php http://localhost:8092
(cd content/v1-vorlage && python3 -m http.server 8093 --bind 127.0.0.1)   # Paket zum Vergleich
NODE_PATH=~/v1-browsertest/node_modules:~/sin-imgtools/node_modules SIN_FUND_PW_0=… SIN_FUND_PW_1=… \
  node tools/v1/browser-check.js http://localhost:8092 http://127.0.0.1:8093
```

Lokal gehen Mails nicht hinaus, sondern nach `wp-content/mail-capture.log`. Dokumente lokal (nicht im Repository):
`../_sin-dokumente/` (PAI-PDFs) und `../_sin-geschuetzt/<fonds>/` (Offenlegungen); Testpasswörter setzt man mit
`printf '%s' '<passwort>' | php ~/wp-cli.phar eval-file tools/v1/fonds-passwort.php <fonds>`.

## Inhalte ändern

Nur über eine neue Fassung des Pakets von ServiceInvest: `content/v1-vorlage/` ersetzen, `build.sh`, `local-setup.sh`, Prüfungen,
`deploy/v1/deploy.sh`. Kein Bearbeiten im WordPress-Editor (HA 1, 3.1). Jede Änderung in beiden Sprachen (HA 14).

## Umsetzung der HA

- **4 support.js abgelöst:** `build.php` löst die Vorlagen-Elemente im Anfangszustand des Pakets auf; was das Paket erst nach einer
  Aktion zeigt (Menü, Hinweise, Fehlermeldungen, Danke-Ansicht, Reiter, Passwortfelder), steht mit `hidden` im HTML. `site.js`
  (Vanilla, 14 KB) baut das Verhalten aus den DCLogic-Skripten nach. CSS des Pakets je Seite inline im Kopf (kritisches CSS,
  kein Render-Blocker), dahinter `site.css`. Ohne JavaScript ist alles lesbar (beide Anlegergruppen per `<noscript>`).
- **5 URLs, Weiterleitungen:** Adressen nach HA 5.1, 301 nach HA 5.2 inklusive der englischen Polylang-Adressen der bisherigen
  Website und der bisher öffentlichen PDF-Adressen (`inc/redirects.php`); 404-Seiten de/en im Stil der Rechtstexte.
- **6 Formulare:** Prüfung im Browser wie im Paket und gleich auf dem Server; Honeypot, Mindestzeit, 5 Anfragen/Stunde/IP; Versand
  über `site-mail.php` (Amazon SES, EU) mit Absender „ServiceInvest“ &lt;contact@2ig.de&gt;, Reply-To = Absender; nichts gespeichert.
- **7 Fonds-Offenlegungen:** Passwort je Fonds nur als Hash, Prüfung auf dem Server, Rate-Limit 5/15 min, Dokumente außerhalb des
  Webroots (`~/geschuetzt/sin/<fonds>/`), Auslieferung nur mit Sitzung (2 h) für genau diesen Fonds, `X-Robots-Tag: noindex`.
  Die bisherigen Passwörter der Fondsseiten werden übernommen; ändern unter Einstellungen → Fonds-Offenlegungen.
- **8 PAI-PDFs:** unter `/dokumente/` (Dateinamen unverändert), alte Adressen leiten weiter. Externe Links in neuem Tab mit Hinweis
  für Screenreader.
- **9 Performance:** Bilder als `<picture>` mit AVIF/WebP/JPEG in 640/1024/1600/2400 px, `sizes`, Breite/Höhe, erstes Bild mit
  `fetchpriority="high"` und Preload, übrige `loading="lazy"`; Schrift-Preload; Kompression und Cache-Header (`.htaccess`).
- **10 Suchmaschinen:** Beschreibungen aus HA 10, canonical, hreflang de/en/x-default, Open Graph/Twitter mit eigenem Vorschaubild
  (Hero 1 mit weißem Logo), JSON-LD Organization auf den Startseiten, `sitemap.xml` mit hreflang, `robots.txt`.
- **11 Barrierefreiheit:** Skip-Link, Pause-Schaltfläche für den Slider (auch mobil), Menü mit Fokusführung/Escape, Reiter mit
  Pfeiltasten, Fehlermeldungen per `role="alert"`, sichtbare Fokusrahmen auf Schwarz und Weiß.
- **12 Sicherheit:** HTTPS mit HSTS, eine Hauptadresse (www), CSP nur eigene Quellen, keine Cookies außer der Sitzung des
  geschützten Bereichs, keine Drittanbieter.

## Abweichungen vom Export (mit Begründung)

| Abweichung | Begründung |
|---|---|
| Pause-Schaltfläche im Hero; mobil steht sie allein unter den Schaltflächen | HA 4.2/11 (WCAG 2.2.2). Halten auch bei Hover/Tastaturfokus der Bildsteuerung; bei „Bewegung reduzieren“ kein automatischer Wechsel |
| Skip-Link („Zum Inhalt springen“), nur bei Fokus sichtbar | HA 11 |
| Fokusrahmen auf allen Seiten (im Export nur auf Kontakt) | HA 11 |
| Passwortfeld: Schaltfläche „Öffnen“ ist Absende-Schaltfläche (Enter funktioniert), darunter Meldung bzw. Dokumentliste | HA 7; Inhalt nach dem Öffnen liefert ServiceInvest |
| Fehlermeldung bei technischem Fehler in beiden Formularen | HA 6 |
| Hinweis „(öffnet in neuem Fenster)“ nur für Screenreader, externe Links in neuem Tab | HA 8 |
| Bilder in AVIF/WebP statt JPEG | HA 9; Ausschnitte (object-position) unverändert |
| CSS inline statt `<style>` im Body | HA 4.1/9; Regeln unverändert |

## Änderungen am Paket (Entscheidungen vom 4. November 2026)

In `content/v1-vorlage/` geändert (der Rest ist unverändert):
- `datenschutz.html`, `en/privacy.html`: Abschnitt „Anlegerzugang und passwortgeschützte Fondsbereiche“ mit dem Sitzungs-Cookie
  `sin_fonds`, Wortlaut wie auf 2ip.de.
- `impressum.html`, `en/legal-notice.html`: Zwischenüberschrift „Rechtliche Hinweise“/„Legal information“ #4D4D4D statt #E0E0E0
  (auf weißem Grund kaum lesbar).

Weitere Festlegungen: Texte, die nicht im Paket stehen (404, Meldungen, Dokumenttitel), im Wortlaut wie auf 2ip.de; Mieteranliegen
an info@serviceinvest.de; überarbeitete Debeka-Offenlegungen (Stand 04.08.2025). Die bisherigen öffentlichen Adressen der
Fonds-PDFs unter `/wp-content/uploads/2025/…` liefern wie auf 2ip.de die aktuelle Fassung aus (noindex, nicht verlinkt).
Fondsdokumente liegen lokal in `../_sin-geschuetzt/<fonds>/` (nicht im Repository); das Deploy überträgt diesen Stand.

## Offene Punkte für ServiceInvest (HA 15)

- **Externe Links prüfen** (HA 8): www.ust-xxiv.com, US-Treuhand-Fondsseite, www.2ig.de/jobs/.
- Termin der Umstellung, englische Rechtstexte (Compliance), Reichweitenmessung (derzeit keine).
- Kontraste Text auf Bild (HA 11) und Screenreader-Test (NVDA/VoiceOver) von Hand; Lighthouse auf Mobilgerät nach dem Livegang.
