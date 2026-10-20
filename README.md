# 2IP – Website (WordPress, Divi 5)

Lokale Entwicklungsumgebung der Website von Institutional Investment Partners (2IP).
Versioniert wird nur Eigenes; WordPress-Kern, Divi, Uploads und Zugangsdaten gehören nicht ins Repository.

## Inhalt

| Pfad | Zweck |
|---|---|
| `wp-content/themes/Divi-2IP/` | Child-Theme (CSS, JS, Bilder, Downloads, `functions.php`) |
| `wp-content/plugins/appointment-booking/` | Terminbuchung / Kontaktanfrage (Rate-Limit, Validierung, konfigurierbare Adressen) |
| `wp-content/mu-plugins/2ip-hardening.php` | Härtung (REST-Benutzerliste, XML-RPC, Header) |
| `router.php` | Router für den lokalen PHP-Server (`php -S 127.0.0.1:8080 router.php`) |
| `content/` | Export der Seiteninhalte (Divi-5-Markup), Menüs und Theme-Builder-Layouts |
| `tools/build/` | Python-Skripte, die aus den Textquellen das Divi-Markup erzeugen |
| `tools/export-content.sh` | Exportiert Seiten, Menüs und Layouts aus WordPress nach `content/` |

## Arbeitsablauf

```bash
WP="php ~/wp-cli.phar" tools/export-content.sh   # Inhalte aus WordPress nach content/
git add -A && git commit -m "…" && git push
```

Die Build-Skripte lesen die Textquellen aus `IP_CONTENT_DIR` (Standard: `2IP Content/`) und schreiben in das als erstes Argument übergebene Verzeichnis.

## Bewusst nicht im Repository

- **Schriften** (Neue Haas Grotesk Display, lizenziert) – `wp-content/themes/Divi-2IP/fonts/` lokal ablegen.
- **Divi**, WordPress-Kern, `wp-content/uploads/` (Mediathek).
- **`wp-config.php`**, Datenbank-Dumps, Passwörter.
- **Passwortgeschützte Fondsseiten** (Inhalte und Passwörter) und Entwürfe – das Repository ist öffentlich.
- Persönliche Daten (z. B. Terminanfragen, Benutzerkonten).
