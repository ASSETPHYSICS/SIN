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
# Platzhalter: Motive der 2IP-Seite, bis eigene ServiceInvest-Bilder vorliegen.
SLIDER = '<div class="ip-slider ip-slider--5" aria-hidden="true">' + "".join(
    '<img class="ip-slide" src="/wp-content/themes/Divi-SIN/images/hero/%s.jpg" alt="" width="%d" height="%d" %s style="--i:%d">' % (s, w, h, 'fetchpriority="high"' if i == 0 else 'loading="lazy"', i)
    for i, (s, w, h) in enumerate(SLIDES)) + "</div>"

HERO = SLIDER + """<div class="ip-wrap ip-hero__inner">
%s
<h1>Unleash<br>Capacities.</h1>
<p class="ip-lead">Asset Servicing und Partner-Fonds für Asset Manager und KVGen von Immobilienvermögen.</p>
<p><a class="ip-btn" href="#gruppe">Mehr erfahren</a></p>
</div>""" % kicker("ServiceInvest Kapitalverwaltungsgesellschaft")

GRUPPE = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Exzellenz in der Fondsadministration.</h2>
<p class="ip-lead">Die ServiceInvest Kapitalverwaltungsgesellschaft mbH ist Teil der Institutional Investment Group (2IG), einer der führenden unabhängigen Administrationsplattformen für Immobilieninvestments in Deutschland und Europa. Die 2IG hat sich auf Dienstleistungen für institutionelle Immobilienfonds nach deutschem und luxemburgischem Recht spezialisiert. Mit mehr als 350 Mitarbeitern verwaltet 2IG über 50 Milliarden Euro Immobilienvermögen in ca. 25 Ländern und nimmt damit eine marktführende Position in der Administration von Immobilienvermögen ein.</p>
</div>
<div class="ip-col">
<h3>Die Asset Servicing Division</h3>
<p>Die Asset Servicing Division vereint alle KVG-Insourcing-Leistungen der 2IG. Die drei Einheiten ServiceInvest Kapitalverwaltungsgesellschaft, acb und CONNOS der Division übernehmen für Immobilienmanager und Kapitalverwaltungsgesellschaften aufwendige Leistungen des Middle- und Back-Office wie Risikomanagement/-controlling, Fondsrechnungswesen, Investorenberichtswesen oder behördliches Meldewesen.</p>
<p><strong>Wir sind 100%% unabhängig von Banken, Versicherungen, anderen professionellen Immobilieneignern und auch Asset Managern.</strong></p>
<p>Durch die hohe Spezialisierung der Einheiten und die für Immobilien-Kapitalverwaltungsgesellschaften entwickelte Software-Suite AIFMControl als Rückgrat einer leistungsstarken, verschränkten IT-Plattform ist das Leistungsangebot der Asset Servicing Division der 2IG in höchstem Maße skalierbar und schafft bei unseren Kunden Raum für Wachstum.</p>
<p class="ip-group-link"><a href="#leistungen">→ Unsere Leistungen</a></p>
</div>
</div>""" % kicker("Die Institutional Investment Group")

VISION = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Unleash Capacities</h2>
<p class="ip-lead">Die Digitalisierung der Kapitalmärkte schreitet immer schneller voran, gleichzeitig nehmen die regulatorischen Anforderungen kontinuierlich zu. Wir sind überzeugt, dass diesen wachsenden Herausforderungen nur durch eine zunehmende Arbeitsteilung zwischen Asset-Managern und spezialisierten Verwaltungsdienstleistern zu begegnen ist.</p>
</div>
<div class="ip-col">
<p>Das Segment der Real Assets folgt dabei dem Wertpapierbereich, in dem sich schon längst eine hohe Arbeitsteilung etabliert und bewährt hat. Allerdings bringen gerade Immobilieninvestments ihre ganz eigene Komplexität und hohe Anforderungen mit sich.</p>
<p>Wir begleiten unsere Kunden Schritt für Schritt in diese digitale Zukunft des Real Asset-Marktes.</p>
<p>Unsere Kunden sollen sich ganz auf ihre Kernkompetenz, das Asset Management, konzentrieren können. Die durch unsere skalierbaren Services für sie frei werdenden Kapazitäten sollen für weiteres Wachstum in ihren Kernleistungsbereichen zur Verfügung stehen und nicht durch administrative Aufgaben gebunden werden.</p>
</div>
</div>""" % kicker("Unsere Vision")

LEISTUNGEN = """<div class="ip-wrap">
%s
<h2>Insourcing durch zugelassene KVG</h2>
<p class="ip-lead">Gemeinsam mit weiteren hoch spezialisierten Unternehmen der Asset Servicing Division der 2IG bietet die ServiceInvest neben der Auflage von Partner-Fonds Asset Managern und KVGen die Übernahme spezifischer Leistungspakete im Middle und Back Office an – das KVG-Insourcing.</p>
<p class="ip-intro">Durch die Zulassung als Kapitalverwaltungsgesellschaft nach § 22 KAGB können wir unseren Kunden das Insourcing von Dienstleistungen in einem vollständig regulierten Rahmen bieten, was für unsere Kunden mit einer signifikanten Erleichterung des Auslagerungscontrollings einhergeht.</p>
<div class="ip-values ip-values--3">
<article><h3>Fonds- und Gesellschaftsbuchhaltung</h3><p>Asset Managern und ihren KVGen bieten wir eine vollständig digitale Fonds- und Gesellschaftsbuchhaltung auf Basis eines zentralen Datenhaushalts. Dabei übersetzen wir internationale Rechnungsvorschriften (local GAAP) in deutsches Investmentrecht. Durch die vollständige Generierung des Buchungsstoffes im System erhalten unsere Kunden die Möglichkeit eines lückenlosen Data-Tracings und die Voll-Durchschau in ihren Fonds.</p></article>
<article><h3>Meldewesen</h3><p>Über unsere einzigartige Management Software AIFMControl generieren wir die obligatorischen XML-Meldungen an Bundesbank und BaFin sowie jegliche weitere Meldungen an andere Behörden und Verbände auf Knopfdruck. Auch die mittelbaren Meldungsanforderungen institutioneller Investoren z.B. nach Solvency II, GroMiKV oder CRR erfüllen wir termingerecht und komfortabel.</p><p>Weitere Informationen: <a href="https://fund-reporting.com" target="_blank" rel="noopener">fund-reporting.com</a></p></article>
<article><h3>Investorenberichtswesen</h3><p>Den quantitativen Teil der Monats-, Quartals-, Halbjahres-, und Jahresberichte liefern wir automatisiert und im Corporate Design unserer Kunden, die nur noch qualitative Teile wie Marktberichte oder Lagebeschreibung im Berichtssystem (AIFMC) zu ergänzen brauchen. Auf Wunsch produzieren wir auch individuelle Berichte nach den Anforderungen bestimmter Zielgruppen.</p></article>
<article><h3>Risikomanagement</h3><p>Auf Basis unserer Management Software AIFMControl bieten wir unseren Kunden umfassende Unterstützung im Risikomanagement und -controlling. Das gilt für die Entwicklung und Formulierung der Risikostrategie ebenso wie bei der Risikoinventur (Erstellung Risikoatlas und -kataloge je AIF) sowie Festlegung von Schwellenwerten und des Risikotragfähigkeitskonzeptes.</p><p>Wir liefern die quantitative Grundlage für ein effektives Risikomanagement und unterstützen mit unserer Software das entsprechende Maßnahmenmanagement. Eine umfassende Schadenfalldatenbank und die automatische Erstellung von Risikoberichten und aufsichtsrechtlich konformer Dokumentation runden unsere Services ab.</p></article>
<article><h3>Hedging Services</h3><p>Über das quantitative Risikomanagement hinaus bietet die Asset Servicing Division auch Leistungen des Absicherungsmanagements. Dazu zählen insbesondere die Set-up-Beratung bei Absicherungsgeschäften, Handelsunterstützung von OTC-Derivaten und Grundgeschäften (inkl. Vertragsmanagement) sowie Bewertungsleistungen in einer ganzheitlich aufeinander abgestimmten Prozesslandschaft. Ergänzt werden diese Services um das entsprechende Risikomanagement, Liquiditätsmanagement sowie Reporting dieser Vermögensgegenstände.</p></article>
<article><h3>Partner-Fonds</h3><p>Unseren Fondspartnern ohne eigene KVG bieten wir darüber hinaus die Konzeption, Auflage und Verwaltung von Immobilien-AIF. Gemeinsam mit unseren Kunden finden wir die adäquate Struktur und sorgen in enger Abstimmung mit den Aufsichtsbehörden für eine zügige Auflage des Fonds.</p></article>
</div>
</div>""" % kicker("Unsere Leistungen")

UNTERSCHIED = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Was uns unterscheidet.</h2>
</div>
<div class="ip-col">
<ul class="ip-list">
<li><h3>Erfahrenes Onboarding-Team</h3><p>Als einziger Insourcing-Anbieter verfügt die Asset Servicing Division der 2IG über ein Onboarding-Team mit der Erfahrung von rund 20 KVG-Gründungen und der entsprechend nachweislichen Prozess- und Organisationskompetenz – sowohl mit Blick auf wirtschaftliche Effizienz als auch auf aktuelle regulatorische Anforderungen. So wird auch die Migration großer Datenbestände sicher durchgeführt und eine optimale Abstimmung interner wie externer Prozesse erreicht.</p></li>
<li><h3>Insourcing Management als SPOC</h3><p>Für eine reibungslose Zusammenarbeit sorgt der Insourcing-Manager als Single-Point-of-Contact (SPOC).</p><p>Er überwacht und begleitet die vereinbarten Service-Level-Agreements (SLA) anhand definierter Key Performance Indicators (KPI) und vereinfacht das Auslagerungscontrolling unserer Kunden mit regelmäßigen SLA/KPI-Berichten.</p></li>
<li><h3>Verschränkte IT-Plattform</h3><p>Mit der bewährten AIFMControl-Suite steht eine einzigartige IT-Plattform zur Verfügung, die speziell für das Management von Immobilienfonds entwickelt wurde. Kunde und ServiceInvest greifen über AIFMControl auf eine zentrale Datenhaltung zu. Durch diese Verschränkung der IT wird eine medienbruchfreie Kommunikation zwischen allen Beteiligten hergestellt, so dass ein aufsichtsrechtlich wertvolles, vollständiges Data Tracing und eine Voll-Durchschau für den Kunden möglich sind.</p></li>
</ul>
</div>
</div>""" % kicker("Asset Servicing Division")

NUTZEN = """<div class="ip-wrap">
%s
<h2>Ihr Nutzen als unser Kunde.</h2>
<div class="ip-figures">
<article class="ip-figure"><span class="ip-figure__value">100%%</span><h3>Transparenz &amp; Skalierbarkeit</h3><p>Unsere Kunden behalten mit der eigenen Kapitalverwaltungsgesellschaft die Hoheit über ihre Fonds und Daten, wir sorgen für Transparenz und Skalierbarkeit.</p></article>
<article class="ip-figure"><span class="ip-figure__value">100%%</span><h3>Zentralisierte Plattform</h3><p>Unsere Kunden erhalten eine einzigartige zentralisierte sowie revisionssichere Daten-, Prozess-, Berichts- und Meldeplattform zur Abbildung nationaler und internationaler Anforderungen.</p></article>
<article class="ip-figure"><span class="ip-figure__value">100%%</span><h3>Fokus auf Kernkompetenzen</h3><p>Unsere Kunden erhalten den Raum und die Sicherheit, sich voll auf ihre Kernkompetenz konzentrieren zu können: das Immobilienmanagement.</p></article>
</div>
</div>""" % kicker("Ihr Nutzen")

KONTAKT = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Wir freuen uns auf den Austausch.</h2>
<address class="ip-address">
<a href="tel:+494022636430">+49 (0) 40 22 63 64 30</a>
</address>
<div class="ip-locations">
<div><p class="ip-kicker ip-kicker--light">Hauptsitz München</p><p>ServiceInvest Kapitalverwaltungsgesellschaft mbH<br>Fürstenrieder Straße 61<br>80686 München</p></div>
<div><p class="ip-kicker ip-kicker--light">Standort Hamburg</p><p>ServiceInvest Kapitalverwaltungsgesellschaft mbH<br>Am Kaiserkai 13<br>20457 Hamburg</p></div>
</div>
<p class="ip-toggle-wrap"><a href="#kontaktanfrage" class="ip-link-toggle" id="ip-contact-toggle" aria-controls="ip-contact-form" aria-expanded="true">Kontaktanfrage</a></p>
</div>
<div class="ip-col">
<div id="ip-contact-form" class="ip-contact-panel">
[appointment_booking tab="anfrage" variant="kontakt"]
</div>
</div>
</div>""" % kicker("Kontakt", "ip-kicker--light")

FOOTER = """<div class="ip-wrap ip-footer__inner">
<p class="ip-footer__name">ServiceInvest Kapitalverwaltungsgesellschaft mbH</p>
<p class="ip-footer__links">[ip_legal_links]</p>
<p class="ip-footer__copy">© 2026 ServiceInvest Kapitalverwaltungsgesellschaft mbH</p>
</div>"""

home_sections = [
    section("ip-hero ip-fund-hero ip-fund-hero--home", "Hero", HERO),
    section("ip-sec ip-sec--white", "Die Gruppe", GRUPPE.replace('<div class="ip-wrap ip-grid', '<div id="gruppe" class="ip-wrap ip-grid', 1)),
    section("ip-sec ip-sec--mist", "Unsere Vision", VISION.replace('<div class="ip-wrap ip-grid', '<div id="vision" class="ip-wrap ip-grid', 1)),
    section("ip-sec ip-sec--white", "Unsere Leistungen", LEISTUNGEN.replace('<div class="ip-wrap">', '<div id="leistungen" class="ip-wrap">', 1)),
    section("ip-sec ip-sec--mist", "Was uns unterscheidet", UNTERSCHIED.replace('<div class="ip-wrap ip-grid', '<div id="unterschied" class="ip-wrap ip-grid', 1)),
    section("ip-sec ip-sec--brand", "Ihr Nutzen", NUTZEN.replace('<div class="ip-wrap">', '<div id="nutzen" class="ip-wrap">', 1)),
    section("ip-sec ip-sec--forest", "Kontakt", KONTAKT.replace('<div class="ip-wrap ip-grid', '<div id="kontakt" class="ip-wrap ip-grid', 1)),
]
home = "<!-- wp:divi/placeholder -->" + "\n".join(home_sections) + "<!-- /wp:divi/placeholder -->"
footer = "<!-- wp:divi/placeholder -->" + section("ip-footer", "Footer", FOOTER) + "<!-- /wp:divi/placeholder -->"

out = sys.argv[1]
open(out + "/home.txt", "w", encoding="utf-8").write(home)
open(out + "/footer.txt", "w", encoding="utf-8").write(footer)
print(len(home), len(footer))
