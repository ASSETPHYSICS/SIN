#!/usr/bin/env python3
"""Erzeugt den Divi-5-Blockinhalt der Startseite und des Footers."""
import json, sys, html

VER = "5.0.0-public-beta.2"


def j(obj):
    s = json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
    return s.replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026").replace("--", "\\u002d\\u002d")


def text_block(inner_html):
    data = {"content": {"innerContent": {"desktop": {"value": inner_html}}}, "builderVersion": VER}
    return "<!-- wp:divi/text %s /-->" % j(data)


def section(css_class, label, inner_html, extra_class=""):
    cls = ("ip-divi-sec " + css_class + " " + extra_class).strip()
    sec = {
        "module": {
            "meta": {"adminLabel": {"desktop": {"value": label}}},
            "decoration": {
                "spacing": {"desktop": {"value": {"padding": {"top": "0px", "bottom": "0px", "left": "0px", "right": "0px", "syncVertical": "on", "syncHorizontal": "on"}}}},
                "layout": {"desktop": {"value": {"display": "block"}}},
                "attributes": {"desktop": {"value": {"attributes": [
                    {"id": "ipc-" + css_class, "name": "class", "value": cls, "adminLabel": "class", "targetElement": "main"}]}}},
            },
        },
        "builderVersion": VER,
    }
    row = {
        "module": {
            "advanced": {"columnStructure": {"desktop": {"value": "4_4"}}, "flexColumnStructure": {"desktop": {"value": "equal-columns_1"}}},
            "decoration": {
                "sizing": {"desktop": {"value": {"width": "100%", "maxWidth": "none", "alignment": "center"}}},
                "spacing": {"desktop": {"value": {"padding": {"top": "0px", "bottom": "0px", "left": "0px", "right": "0px", "syncVertical": "on", "syncHorizontal": "on"}}}},
                "layout": {"desktop": {"value": {"display": "block"}}},
            },
        },
        "builderVersion": VER,
    }
    col = {
        "module": {
            "advanced": {"type": {"desktop": {"value": "4_4"}}},
            "decoration": {"layout": {"desktop": {"value": {"display": "block"}}}},
        },
        "builderVersion": VER,
    }
    return (
        "<!-- wp:divi/section %s -->\n<!-- wp:divi/row %s -->\n<!-- wp:divi/column %s -->\n%s\n<!-- /wp:divi/column -->\n<!-- /wp:divi/row -->\n<!-- /wp:divi/section -->"
        % (j(sec), j(row), j(col), text_block(inner_html))
    )


def e(s):
    return html.escape(s, quote=False)


# ---------------------------------------------------------------- Inhalte
def kicker(t, cls=""):
    return '<p class="ip-kicker %s">%s</p>' % (cls, e(t))


SLIDES = [("01-immobilien", 2200, 1228), ("02-erneuerbare-energien", 2200, 924), ("03-infrastruktur", 2200, 924), ("04-private-debt", 2200, 924), ("05-logistik", 2200, 924)]
SLIDER = '<div class="ip-slider ip-slider--5" aria-hidden="true">' + "".join(
    '<img class="ip-slide" src="/wp-content/themes/Divi-2IP/images/hero/%s.jpg" alt="" width="%d" height="%d" %s style="--i:%d">' % (s, w, h, 'fetchpriority="high"' if i == 0 else 'loading="lazy"', i)
    for i, (s, w, h) in enumerate(SLIDES)) + "</div>"

HERO = SLIDER + """<div class="ip-wrap ip-hero__inner">
%s
<h1>Real Assets.<br>Klar strukturiert.<br>Wirkungsvoll verwaltet.</h1>
<p class="ip-lead">Institutionelle Anleger stehen vor wachsenden Anforderungen an Governance, Reporting und Regulierung. Wir schaffen klare Strukturen, verlässliche Prozesse und transparente Informationen, damit Anlageentscheidungen auf einer belastbaren Grundlage getroffen werden können.</p>
<p><a class="ip-btn" href="#leistungen">Unsere Leistungen</a></p>
</div>""" % kicker("Investoren-Master-KVG · AIFM · Investment Consulting")

UEBERZEUGUNG = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Transparenz, Kontrolle und Effizienz in der Real Asset-Anlage.</h2>
<p class="ip-lead">Real Assets erfordern langfristiges Denken, belastbare Strukturen und einen klaren Überblick über Risiken, Verantwortlichkeiten und Entwicklungen. Gleichzeitig steigen die Anforderungen an Administration, Risikomanagement, Reporting und Regulierung. Wir unterstützen institutionelle Anleger dabei, sich auf das Wesentliche zu konzentrieren, klare Entscheidungen zu treffen und jederzeit den Überblick zu behalten.</p>
</div>
<div class="ip-col">
%s
<ul class="ip-list">
<li><h3>Erfahrung aus erster Hand</h3><p>Wir kennen die Perspektiven von Investoren, Asset Managern und Gremien. Dadurch entstehen Lösungen, die in der Praxis funktionieren und nachvollziehbare Entscheidungen ermöglichen.</p></li>
<li><h3>Technologie als Hebel</h3><p>Digitale Prozesse reduzieren manuellen Aufwand, verbessern die Datenqualität und schaffen Transparenz für Anleger, Ausschüsse und Aufsichtsgremien.</p></li>
<li><h3>Ganzheitliche Plattform</h3><p>Verwaltung, Regulierung und Beratung greifen ineinander. Das schafft klare Zuständigkeiten, reduziert Abstimmungsaufwand und erhöht die Steuerungsfähigkeit komplexer Real Asset-Strukturen.</p></li>
</ul>
</div>
</div>""" % (kicker("Unser Versprechen"), kicker("Was uns unterscheidet"))

ASSETKLASSEN = """<div class="ip-wrap">
%s
<h2>Kompetenz für die Real Assets institutioneller Anleger.</h2>
<ul class="ip-tiles">
<li class="ip-tile ip-tile--immobilien"><img src="/wp-content/themes/Divi-2IP/images/assetklassen/immobilien.jpg" alt="" loading="lazy" width="1280" height="714"><h3>Immobilien</h3></li>
<li class="ip-tile ip-tile--energie"><img src="/wp-content/themes/Divi-2IP/images/assetklassen/erneuerbare-energien.jpg" alt="" loading="lazy" width="1714" height="720"><h3>Erneuerbare Energien</h3></li>
<li class="ip-tile ip-tile--infrastruktur"><img src="/wp-content/themes/Divi-2IP/images/assetklassen/infrastruktur.jpg" alt="" loading="lazy" width="1714" height="720"><h3>Infrastruktur</h3></li>
<li class="ip-tile ip-tile--debt"><img src="/wp-content/themes/Divi-2IP/images/assetklassen/private-debt.jpg" alt="" loading="lazy" width="1714" height="720"><h3>Private Debt</h3></li>
</ul>
</div>""" % kicker("Assetklassen")

LEISTUNGEN = """<div class="ip-wrap">
%s
<h2>Drei spezialisierte Einheiten. Eine gemeinsame Mission.</h2>
<div class="ip-cards">
<article class="ip-card"><h3>Investoren-Master-KVG</h3><p>Professionelle Administration, Risikomanagement und Reporting für Spezial-AIF in Real Asset-Assetklassen – reguliert, effizient, skalierbar.</p><p class="ip-conclusion">Institutional Investment Partners GmbH</p></article>
<article class="ip-card"><h3>AIFM Luxemburg</h3><p>Zugelassener Alternative Investment Fund Manager für internationale Fondsstrukturen aus einem der führenden Fondsstandorte Europas.</p><p class="ip-conclusion">Institutional Investment Partners S.à.r.l.</p></article>
<article class="ip-card"><h3>Investment Consulting</h3><p>Unabhängige Expertise für institutionelle Anleger – von der Strategie über Strukturierung bis zur Manager Selection und Second Opinions.</p><p class="ip-conclusion">Institutional Investment Consulting Partners GmbH</p></article>
</div>
</div>""" % kicker("Leistungen")

ANSATZ = """<div class="ip-wrap">
%s
<h2>Die Grundlage für fundierte Investitionsentscheidungen.</h2>
<div class="ip-values">
<article><h3>Wissen</h3><p>Komplexe Zusammenhänge werden auf die Informationen reduziert, die für Anleger, Anlageausschüsse und Entscheidungsträger tatsächlich relevant sind.</p></article>
<article><h3>Transparenz</h3><p>Sie erhalten die Informationen, die Sie für Steuerung, Kontrolle und Berichterstattung benötigen – nachvollziehbar, verständlich und zum richtigen Zeitpunkt.</p></article>
<article><h3>Innovation</h3><p>Innovative Technologie setzen wir dort ein, wo sie Prozesse vereinfacht, Datenqualität verbessert und administrativen Aufwand reduziert.</p></article>
<article><h3>Verantwortung</h3><p>Klare Verantwortlichkeiten und langfristiges Denken bilden die Grundlage nachhaltiger Investitionsentscheidungen und vertrauensvoller Zusammenarbeit.</p></article>
</div>
</div>""" % kicker("Unser Ansatz")

GRUPPE = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Institutional Solutions Division der Institutional Investment Group</h2>
<p class="ip-lead">Institutional Investment Partners GmbH, Institutional Investment Partners S.à.r.l. und Institutional Investment Consulting Partners GmbH bilden die Investor Solutions Division der Institutional Investment Group (2IG). Sie vereint alle Leistungen der 2IG, die sich direkt an institutionelle Investoren richten.</p>
</div>
<div class="ip-col">
<p>Die Institutional Investment Group (2IG) ist eine der führenden unabhängigen Investoren-Plattformen für Real Asset-Investments in Deutschland und Europa. 2IG hat sich mit ihren Geschäftsbereichen Investor Solutions Division und Asset Servicing Division auf Dienstleistungen für institutionelle Real Asset-Fonds nach deutschem und luxemburgischem Recht spezialisiert.</p>
<p>Mit mehr als 350 Mitarbeitern verwaltet 2IG über 50 Milliarden Euro Immobilienvermögen in ca. 25 Ländern und nimmt damit eine marktführende Position in der Administration von Real Assets ein.</p>
<p><strong>100% unabhängig von Banken, Versicherungen, anderen professionellen Immobilieneignern und auch Asset Managern.</strong></p>
<p class="ip-group-link"><a href="https://www.2ig.de" target="_blank" rel="noopener">→ www.2ig.de</a></p>
</div>
</div>""" % kicker("Die Gruppe")

KONTAKT = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Sprechen wir über Ihre Anforderungen.</h2>
<p class="ip-lead">Ob bestehende Fondsstrukturen, neue Real Asset-Investitionen oder regulatorische Fragestellungen: Wir freuen uns auf den fachlichen Austausch.</p>
<address class="ip-address">
<a href="tel:+496948448550 0">+49 69 484 485 500</a><br>
Brüsseler Str. 1–3<br>
60327 Frankfurt am Main
</address>
<p class="ip-toggle-wrap"><a href="#kontaktanfrage" class="ip-link-toggle" id="ip-contact-toggle" aria-controls="ip-contact-form" aria-expanded="true">Kontaktanfrage</a></p>
</div>
<div class="ip-col">
<div id="ip-contact-form" class="ip-contact-panel">
[appointment_booking tab="anfrage" variant="kontakt"]
</div>
</div>
</div>""".replace("tel:+496948448550 0", "tel:+4969484485500") % kicker("Kontakt", "ip-kicker--light")

FOOTER = """<div class="ip-wrap ip-footer__inner">
<p class="ip-footer__name">Institutional Investment Partners GmbH</p>
<p class="ip-footer__links">[ip_legal_links]</p>
<p class="ip-footer__copy">© 2026 Institutional Investment Partners GmbH</p>
</div>"""

home_sections = [
    section("ip-hero ip-fund-hero ip-fund-hero--home", "Hero", HERO),
    section("ip-sec ip-sec--white", "Überzeugung", UEBERZEUGUNG.replace('<div class="ip-wrap ip-grid', '<div id="ueberzeugung" class="ip-wrap ip-grid', 1)),
    section("ip-sec ip-sec--mist", "Assetklassen", ASSETKLASSEN.replace('<div class="ip-wrap">', '<div id="assetklassen" class="ip-wrap">', 1)),
    section("ip-sec ip-sec--white", "Leistungen", LEISTUNGEN.replace('<div class="ip-wrap">', '<div id="leistungen" class="ip-wrap">', 1)),
    section("ip-sec ip-sec--white", "Unser Ansatz", ANSATZ.replace('<div class="ip-wrap">', '<div id="ansatz" class="ip-wrap">', 1)),
    section("ip-sec ip-sec--mist", "Die Gruppe", GRUPPE.replace('<div class="ip-wrap ip-grid', '<div id="gruppe" class="ip-wrap ip-grid', 1)),
    section("ip-sec ip-sec--forest", "Kontakt", KONTAKT.replace('<div class="ip-wrap ip-grid', '<div id="kontakt" class="ip-wrap ip-grid', 1)),
]
home = "<!-- wp:divi/placeholder -->" + "\n".join(home_sections) + "<!-- /wp:divi/placeholder -->"
footer = "<!-- wp:divi/placeholder -->" + section("ip-footer", "Footer", FOOTER) + "<!-- /wp:divi/placeholder -->"

out = sys.argv[1]
open(out + "/home.txt", "w", encoding="utf-8").write(home)
open(out + "/footer.txt", "w", encoding="utf-8").write(footer)
print(len(home), len(footer))
