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

## Zweisprachigkeit (Deutsch / Englisch)

Polylang (kostenlos, von wordpress.org) verknüpft die Sprachen; Deutsch ohne Präfix, Englisch unter `/en/`.
Übersetzt wird mit DeepL über eigene Skripte, damit das Divi-Markup unverändert bleibt.

| Datei | Zweck |
|---|---|
| `tools/i18n/config.php` | Welche Seiten übersetzt werden, englische Slugs, Menübezeichnungen, Glossar |
| `tools/i18n/translate.php` | Lokal mit DeepL: übersetzt neue/geänderte Abschnitte nach `content/i18n/en/<slug>.json` |
| `content/i18n/en/*.json` | Übersetzungsspeicher – **hier werden Korrekturen gemacht** (Feld `en`) |
| `tools/i18n/import.php` | Baut die englischen Seiten und das englische Menü (lokal und auf dem Server, ohne Schlüssel) |
| `tools/i18n/setup-polylang.php` | Einmalige Einrichtung der Sprachen |

```bash
set -a; . ~/.config/2ip/deepl.env; set +a                     # DeepL-Schlüssel, nur lokal
php wp-cli.phar eval-file tools/i18n/translate.php           # nur neue/geänderte Abschnitte gehen an DeepL
# Korrekturen: in content/i18n/en/<slug>.json das Feld "en" ändern
php wp-cli.phar eval-file tools/i18n/import.php              # englische Seiten aktualisieren
```

Ein korrigierter Abschnitt bleibt erhalten, solange sich der deutsche Text dieses Abschnitts nicht ändert.
Die Texte des Kontaktformulars stehen in `wp-content/plugins/appointment-booking/languages/appointment-booking-en_GB.l10n.php`.

## Bewusst nicht im Repository

- **Schriften** (Neue Haas Grotesk Display, lizenziert) – `wp-content/themes/Divi-2IP/fonts/` lokal ablegen.
- **Divi**, WordPress-Kern, `wp-content/uploads/` (Mediathek).
- **`wp-config.php`**, Datenbank-Dumps, Passwörter, der DeepL-API-Schlüssel (liegt lokal in `~/.config/2ip/deepl.env`).
- **Passwörter der Fondsseiten** – sie stehen nur in der Datenbank; `import.php` übernimmt sie von der deutschen Seite.
  Die Texte der Offenlegungen (SFDR, auch als PDF veröffentlicht) liegen im Übersetzungsspeicher `content/i18n/en/`.
  Das Repository ist privat; vor einer Veröffentlichung diese Dateien prüfen.
- Persönliche Daten (z. B. Terminanfragen, Benutzerkonten).
