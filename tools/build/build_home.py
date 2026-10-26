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


# Hintergrundvideo: Zeitlupe und nahtlose Schleife (vorwärts/rückwärts) sind in die Datei eingerechnet;
# das Vorschaubild ist ihr erstes Bild und steht bis zum Start bzw. bei „Bewegung reduzieren“.
HERO_VIDEO = ('<div class="ip-slider ip-hero-video" aria-hidden="true">'
              '<video class="ip-hero-video__media" src="/wp-content/themes/Divi-SIN/video/hero-loop.mp4" '
              'poster="/wp-content/themes/Divi-SIN/images/hero/hero-poster.jpg" '
              'autoplay muted loop playsinline preload="auto"></video></div>')

HERO = HERO_VIDEO + """<div class="ip-wrap ip-hero__inner">
%s
<h1>Unleash<br>Capacities.</h1>
<p class="ip-lead">Asset Servicing und Partner-Fonds für Asset Manager und KVGen<br class="ip-br-wide"> von Real Asset-Vermögen.</p>
<p><a class="ip-btn" href="#vision">Mehr erfahren</a></p>
</div>""" % kicker("ServiceInvest Kapitalverwaltungsgesellschaft")

GRUPPE = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Exzellenz in der Fondsadministration.</h2>
<p class="ip-lead">Die ServiceInvest Kapitalverwaltungsgesellschaft mbH ist Teil der Institutional Investment Group (2IG), einer der führenden unabhängigen Administrationsplattformen für Real Asset-Investments in Deutschland und Europa. Die 2IG hat sich auf Dienstleistungen für institutionelle Real Asset-Fonds nach deutschem und luxemburgischem Recht spezialisiert. Mit mehr als 350 Mitarbeitern verwaltet 2IG über 50 Milliarden Euro Real Asset-Vermögen in ca. 25 Ländern und nimmt damit eine marktführende Position in der Administration von Real Asset-Vermögen ein.</p>
</div>
<div class="ip-col">
<h3>KVG-Insourcing durch die ServiceInvest</h3>
<p>Die ServiceInvest übernimmt für Asset Manager und Kapitalverwaltungsgesellschaften aufwendige Leistungen des Middle- und Back-Office wie Risikomanagement/-controlling, Fondsrechnungswesen, Investorenberichtswesen oder behördliches Meldewesen.</p>
<p><strong>Wir sind 100%% unabhängig von Banken, Versicherungen, anderen professionellen Eigentümern von Real Assets und auch Asset Managern.</strong></p>
<p>Durch unsere hohe Spezialisierung und eine eigens für Kapitalverwaltungsgesellschaften entwickelte Software-Lösung als Rückgrat einer leistungsstarken, verschränkten IT-Plattform ist das Leistungsangebot der ServiceInvest in höchstem Maße skalierbar und schafft bei unseren Kunden Raum für Wachstum.</p>
<p class="ip-group-link"><a href="#leistungen">→ Unsere Leistungen</a></p>
</div>
</div>""" % kicker("Die Institutional Investment Group")

VISION = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Freiräume schaffen</h2>
<p class="ip-lead"><strong>Aufgaben abgeben. Kontrolle behalten.</strong></p>
<p class="ip-lead">Die Anforderungen von Aufsicht, Bundesbank und Investoren wachsen jedes Jahr. Fondsbuchhaltung, Meldewesen, Reporting und Risikocontrolling binden Fachleute, die Sie eigentlich für Asset Management und Investorenbetreuung brauchen.</p>
</div>
<div class="ip-col">
<p>Im Wertpapiergeschäft ist die Arbeitsteilung zwischen Asset Managern und spezialisierten Verwaltungsdienstleistern längst Standard. Bei Real Assets war das bisher schwieriger, denn jedes Objekt, jede Gesellschaftsstruktur und jede Finanzierung bringt eigene Komplexität mit. Genau darauf haben wir uns spezialisiert.</p>
<p>Sie entscheiden, welche Aufgaben Sie abgeben. Wir übernehmen sie als zugelassene Kapitalverwaltungsgesellschaft in einem vollständig regulierten Rahmen, mit einem festen Ansprechpartner und regelmäßigen SLA-Berichten für Ihr Auslagerungscontrolling. Die Verantwortung bleibt bei Ihnen. Die Arbeit und die Nachweise liefern wir.</p>
<p>So gewinnen Sie Kapazität für das, was Ihren Erfolg ausmacht: Ihre Investments.</p>
<h3>Unleash Capacities.</h3>
</div>
</div>""" % kicker("Unsere Vision")

# Meldewesen, bis auf Weiteres ausgeblendet: <p>Weitere Informationen: <a href="https://fund-reporting.com" target="_blank" rel="noopener">fund-reporting.com</a></p>
LEISTUNGEN = """<div class="ip-wrap">
%s
<h2>Partner-Fonds und Insourcing durch zugelassene Kapitalverwaltungsgesellschaft (KVG)</h2>
<p class="ip-lead">Neben der Auflage von Partner-Fonds bietet die ServiceInvest Asset Managern und KVGen die Übernahme spezifischer Leistungspakete im Middle und Back Office an – das KVG-Insourcing.</p>
<p class="ip-intro">Durch die Zulassung als Kapitalverwaltungsgesellschaft nach § 22 KAGB können wir unseren Kunden das Insourcing von Dienstleistungen in einem vollständig regulierten Rahmen bieten, was für unsere Kunden mit einer signifikanten Erleichterung des Auslagerungscontrollings einhergeht.</p>
<div class="ip-values ip-values--4">
<article><h3>Partner-Fonds</h3><p>Unseren Fondspartnern ohne eigene KVG bieten wir die Konzeption, Auflage und Verwaltung von Real Asset-AIF. Gemeinsam mit unseren Kunden finden wir die adäquate Struktur und sorgen in enger Abstimmung mit den Aufsichtsbehörden für eine zügige Auflage des Fonds.</p></article>
<article><h3>Fonds- und Gesellschaftsbuchhaltung</h3><p>Asset Managern und ihren KVGen bieten wir eine vollständig digitale Fonds- und Gesellschaftsbuchhaltung auf Basis eines zentralen Datenhaushalts. Dabei übersetzen wir internationale Rechnungsvorschriften (local GAAP) in deutsches Investmentrecht. Durch die vollständige Generierung des Buchungsstoffes im System erhalten unsere Kunden die Möglichkeit eines lückenlosen Data-Tracings und die Voll-Durchschau in ihren Fonds.</p></article>
<article><h3>Meldewesen</h3><p>Über unsere einzigartige Software-Lösung generieren wir die obligatorischen XML-Meldungen an Bundesbank und BaFin sowie jegliche weitere Meldungen an andere Behörden und Verbände auf Knopfdruck. Auch die mittelbaren Meldungsanforderungen institutioneller Investoren z.B. nach Solvency II, GroMiKV oder CRR erfüllen wir termingerecht und komfortabel.</p></article>
<article><h3>Investorenberichtswesen</h3><p>Den quantitativen Teil der Monats-, Quartals-, Halbjahres-, und Jahresberichte liefern wir automatisiert und im Corporate Design unserer Kunden, die nur noch qualitative Teile wie Marktberichte oder Lagebeschreibung in unserem Berichtssystem zu ergänzen brauchen. Auf Wunsch produzieren wir auch individuelle Berichte nach den Anforderungen bestimmter Zielgruppen.</p></article>
</div>
</div>""" % kicker("Unsere Leistungen")

UNTERSCHIED = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Was uns unterscheidet.</h2>
</div>
<div class="ip-col">
<ul class="ip-list">
<li><h3>Erfahrenes Onboarding-Team</h3><p>Als einziger Insourcing-Anbieter verfügt die ServiceInvest über ein Onboarding-Team mit der Erfahrung von rund 20 KVG-Gründungen und der entsprechend nachweislichen Prozess- und Organisationskompetenz – sowohl mit Blick auf wirtschaftliche Effizienz als auch auf aktuelle regulatorische Anforderungen. So wird auch die Migration großer Datenbestände sicher durchgeführt und eine optimale Abstimmung interner wie externer Prozesse erreicht.</p></li>
<li><h3>Insourcing Management als SPOC</h3><p>Für eine reibungslose Zusammenarbeit sorgt der Insourcing-Manager als Single-Point-of-Contact (SPOC).</p><p>Er überwacht und begleitet die vereinbarten Service-Level-Agreements (SLA) anhand definierter Key Performance Indicators (KPI) und vereinfacht das Auslagerungscontrolling unserer Kunden mit regelmäßigen SLA/KPI-Berichten.</p></li>
<li><h3>Verschränkte IT-Plattform</h3><p>Mit unserer bewährten Software-Lösung steht eine einzigartige IT-Plattform zur Verfügung, die speziell für das Management von Real Asset-Fonds entwickelt wurde. Kunde und ServiceInvest greifen über diese Plattform auf eine zentrale Datenhaltung zu. Durch diese Verschränkung der IT wird eine medienbruchfreie Kommunikation zwischen allen Beteiligten hergestellt, so dass ein aufsichtsrechtlich wertvolles, vollständiges Data Tracing und eine Voll-Durchschau für den Kunden möglich sind.</p></li>
</ul>
</div>
</div>""" % kicker("ServiceInvest")

NUTZEN = """<div class="ip-wrap">
%s
<h2>Ihr Nutzen als unser Kunde.</h2>
<div class="ip-figures">
<article class="ip-figure"><span class="ip-figure__value">100%%</span><h3>Fokus auf Kernkompetenzen</h3><p>Unsere Kunden erhalten den Raum und die Sicherheit, sich voll auf ihre Kernkompetenz konzentrieren zu können: das Asset Management.</p></article>
<article class="ip-figure"><span class="ip-figure__value">100%%</span><h3>Zentralisierte<br class="ip-br-wide"> Plattform</h3><p>Unsere Kunden erhalten eine einzigartige zentralisierte sowie revisionssichere Daten-, Prozess-, Berichts- und Meldeplattform zur Abbildung nationaler und internationaler Anforderungen.</p></article>
<article class="ip-figure"><span class="ip-figure__value">100%%</span><h3>Transparenz &amp; Skalierbarkeit</h3><p>Unsere Kunden behalten mit der eigenen Kapitalverwaltungsgesellschaft die Hoheit über ihre Fonds und Daten, wir sorgen für Transparenz und Skalierbarkeit.</p></article>
</div>
</div>""" % kicker("Ihr Nutzen")

KONTAKT = """<div class="ip-wrap ip-grid ip-grid--split">
<div class="ip-col">
%s
<h2>Wir freuen uns auf den Austausch.</h2>
<div class="ip-locations">
<div><p class="ip-kicker ip-kicker--light">Hauptsitz München</p><p>ServiceInvest Kapitalverwaltungsgesellschaft mbH<br>Fürstenrieder Straße 61<br>80686 München</p></div>
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
    section("ip-sec ip-sec--white", "Unsere Vision", VISION.replace('<div class="ip-wrap ip-grid', '<div id="vision" class="ip-wrap ip-grid', 1)),
    section("ip-sec ip-sec--mist", "Die Gruppe", GRUPPE.replace('<div class="ip-wrap ip-grid', '<div id="gruppe" class="ip-wrap ip-grid', 1)),
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
