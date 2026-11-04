# www.serviceinvest.de – neue Website (Paket serviceinvest-website) auf IONOS

Ersetzt die bisherige Divi-Website (`~/public/_sin`) durch die 24 Seiten des Pakets `serviceinvest-website` (Deutsch, Englisch)
auf WordPress. Grundlage: `content/Handlungsanweisung_Live-Schaltung_serviceinvest.pdf` (im Folgenden „HA“).

| | |
|---|---|
| Webspace | `su172769@access-5019820309.webspace-host.com` (Schlüssel `~/.ssh/ionos_2ip`) |
| Neuer Ordner | `~/public/_sin_v1` (bisheriger Ordner `~/public/_sin` bleibt unverändert) |
| Datenbank | dieselbe wie bisher, eigene Tabellen mit Präfix `v1_` (bisherige `wp_*` bleiben) |
| Adresse | `https://www.serviceinvest.de` (wie bisher mit www; `serviceinvest.de` und `http` leiten in einem Schritt per 301 weiter) |
| Formulare | Kontakt an `info@serviceinvest.de`, Mieter an `SIN_MIETER_TO` (vorerst ebenfalls info@); Absender `ServiceInvest <contact@2ig.de>` über Amazon SES (eu-central-1) |
| Fonds-Offenlegungen | `~/geschuetzt/sin/<fonds>/` (außerhalb des Webroots), Passwörter als Hash in der Datenbank, Protokoll `~/logs/sin-fonds.log` |
| Admin | `sin-v1-admin`, Passwort in `~/config/sin-v1-admin.txt` |

## Ablauf

1. **Bauen (lokal, WSL):** `bash tools/v1/build.sh`, Prüfungen nach `README.md`.
2. **Deploy:** `deploy/v1/deploy.sh` (lokal, Git Bash). Mehrfach ausführbar.
3. **Prüfen vor dem Umschalten:** `deploy/v1/deploy.sh check` (PHP-Server auf dem Webspace mit Host www.serviceinvest.de, ohne
   .htaccess). Eine Stage-Domain gibt es nicht (DNS von serviceinvest.de bei AWS Route 53).
4. **Umschalten (IONOS-Panel):** Domains & SSL → `serviceinvest.de` und `www.serviceinvest.de` → Zielordner von `/_sin` auf `/_sin_v1`.
5. **Nachher:**
   - `php tools/v1/check.php https://www.serviceinvest.de` (Seiten, Header, Weiterleitungen, 404, Sperren, Cache, Kompression)
   - `SIN_NO_SUBMIT=1 NODE_PATH=… node tools/v1/browser-check.js https://www.serviceinvest.de http://127.0.0.1:8093`
   - Formulare von Hand (DE/EN): Eingang bei info@serviceinvest.de, Absendername „ServiceInvest“
   - Anlegerzugang mit den echten Passwörtern (nur über HTTPS): jeder Fonds öffnet nur seine Dokumente
   - HA 13 von Hand: Browser/Geräte, Lighthouse mobil, Screenreader, securityheaders.com, Vorschau in LinkedIn/Teams
   - Sitemap `https://www.serviceinvest.de/sitemap.xml` in der Google Search Console und den Bing Webmaster Tools einreichen

**Zurück zur bisherigen Seite:** im Panel den Zielordner wieder auf `/_sin` stellen. Die bisherige Installation ist unverändert.

## Was das Deploy tut

- sichert vorher die gesamte Datenbank nach `~/backups/sin-vor-v1-<zeit>.sql`
- kopiert den WordPress-Kern aus `_sin` (gleiche Version), Theme `sin-v1`, mu-Plugins `sin-hardening` und `site-mail`
- kopiert den Build ins Stammverzeichnis (`/assets/`, Favicons, `sitemap.xml`, `robots.txt`)
- kopiert die PAI-PDFs aus `_sin` nach `/dokumente/` und die vier Offenlegungen der Fonds Debeka Immobilien Invest I und TAS Wohnen I
  nach `~/geschuetzt/sin/` (vorhandene Dateien bleiben)
- importiert die 24 Seiten (`tools/v1/import.php`) und übernimmt die Passwörter der bisherigen Fondsseiten als Hash, solange für
  einen Fonds noch keines gesetzt ist (`tools/v1/fonds-migration.php`)

## Passwort eines Fonds ändern

Backend: Einstellungen → Fonds-Offenlegungen. Oder auf dem Server:
`printf '%s' '<passwort>' | php ~/wp-cli.phar --path=$HOME/public/_sin_v1 eval-file ~/sin-v1-check/tools/v1/fonds-passwort.php tas-wohnen-1`
