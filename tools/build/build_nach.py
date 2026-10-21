#!/usr/bin/env python3
"""Divi-5-Blockinhalt der Seite 'Nachhaltigkeit'."""
import json, sys

VER = "5.0.0-public-beta.2"
IMG = "/wp-content/themes/Divi-SIN/images/nachhaltigkeit"


def j(obj):
    s = json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
    return s.replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026").replace("--", "\\u002d\\u002d")


def text_block(inner):
    return "<!-- wp:divi/text %s /-->" % j({"content": {"innerContent": {"desktop": {"value": inner}}}, "builderVersion": VER})


def section(css, label, inner):
    zero = {"top": "0px", "bottom": "0px", "left": "0px", "right": "0px", "syncVertical": "on", "syncHorizontal": "on"}
    sec = {"module": {"meta": {"adminLabel": {"desktop": {"value": label}}},
                      "decoration": {"spacing": {"desktop": {"value": {"padding": zero}}},
                                     "layout": {"desktop": {"value": {"display": "block"}}},
                                     "attributes": {"desktop": {"value": {"attributes": [
                                         {"id": "ipn-" + label[:6], "name": "class", "value": "ip-divi-sec " + css, "adminLabel": "class", "targetElement": "main"}]}}}}},
           "builderVersion": VER}
    row = {"module": {"advanced": {"columnStructure": {"desktop": {"value": "4_4"}}, "flexColumnStructure": {"desktop": {"value": "equal-columns_1"}}},
                      "decoration": {"sizing": {"desktop": {"value": {"width": "100%", "maxWidth": "none", "alignment": "center"}}},
                                     "spacing": {"desktop": {"value": {"padding": zero}}},
                                     "layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    col = {"module": {"advanced": {"type": {"desktop": {"value": "4_4"}}}, "decoration": {"layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    return ("<!-- wp:divi/section %s -->\n<!-- wp:divi/row %s -->\n<!-- wp:divi/column %s -->\n%s\n<!-- /wp:divi/column -->\n<!-- /wp:divi/row -->\n<!-- /wp:divi/section -->"
            % (j(sec), j(row), j(col), text_block(inner)))


def split(title, body):
    """Zweispaltig: links Titel, rechts Inhalt."""
    return ('<div class="ip-wrap ip-nh-split"><div class="ip-nh-head"><h2>%s</h2></div><div class="ip-nh-body">%s</div></div>' % (title, body))


# ------------------------------------------------------------------ Inhalte
HERO = """<div class="ip-wrap ip-hero__inner">
<h1>Nachhaltiges Handeln</h1>
<p class="ip-lead">bei ServiceInvest</p>
</div>"""

UEBERZEUGUNGEN = """<div class="ip-wrap"><div class="ip-nh-card">
<h2>Unsere Überzeugungen</h2>
<p>Als Teil der Institutional Investment Group basiert das zentrale Nutzen- und Werteversprechen für die Anleger und Mitarbeiter der ServiceInvest Kapitalverwaltungsgesellschaft mbH darauf, Nachhaltigkeitsfaktoren in sämtlichen Handlungen und Dienstleistungen einen wesentlichen Stellenwert einzuräumen.</p>
<p>Befördert durch unsere gewonnene Marktstellung und die damit gestiegene gesellschaftliche Verantwortung – aber auch durch die generelle gesellschaftliche Entwicklung – ist es unser wesentliches Bestreben den eigenen Wirkungskreis zu nutzen, um einen bedeutsamen Unternehmensbeitrag für eine nachhaltige Umwelt und Gesellschaft zu leisten.</p>
<p>Deswegen hat die ServiceInvest die allgemeinen Grundsätze zur nachhaltigen Unternehmensführung in eine eigene unternehmensbezogene Nachhaltigkeitsstrategie übersetzt, die nachhaltiges Handeln in und durch unser Unternehmen verbindlich macht und zudem etwaige nachteilige Auswirkungen bei Investitionsentscheidungen auf Nachhaltigkeitsfaktoren berücksichtigt.</p>
</div></div>"""

ECKPUNKTE = split("Zentrale Eckpunkte für unser nachhaltiges Handeln", """<p>Zentrale Inhalte und Eckpunkte unseres Handelns sind:</p>
<ul class="ip-check">
<li>Förderung von verantwortlichem und nachhaltigem Handeln unserer Immobilienmanagement-Partner durch die Verankerung von Verhaltensleitlinien in der Geschäftsbeziehung.</li>
<li>Förderung von Investitions- und Bewirtschaftungsinitiativen an Immobilien, die positiv darauf hinwirken,
<ul class="ip-sub"><li>die CO<sub>2</sub>-Emission zu reduzieren und</li><li>die Gesamtbelastung von Mietern durch reduzierte Heiz- und Nebenkosten zu senken.</li></ul></li>
<li>Förderung von verantwortungsvollem Umgang mit Ressourcen.</li>
<li>Förderung von Informationstechnologie und Prozessen, die darauf ausgerichtet sind,
<ul class="ip-sub"><li>den Verbrauch von Ressourcen durch Fondsimmobilien transparent sowie</li><li>nachhaltiges Handeln messbar</li></ul>
zu machen.</li>
<li>Sanktionierung von Verhalten, das darauf ausgerichtet ist, gegen wesentliche Nachhaltigkeitsgrundsätze zu verstoßen.</li>
</ul>""")

PRINZIPIEN = [
    "Wir werden ESG-Themen in die Analyse- und Entscheidungsprozesse im Investmentbereich einbeziehen.",
    "Wir werden aktive Anteilseigner sein und ESG-Themen in unserer Investitionspolitik und -praxis berücksichtigen.",
    "Wir werden Unternehmen und Körperschaften, in die wir investieren, zu einer angemessenen Offenlegung in Bezug auf ESG-Themen anhalten.",
    "Wir werden die Akzeptanz und die Umsetzung der Prinzipien in der Investmentbranche vorantreiben.",
    "Wir werden zusammenarbeiten, um unsere Wirksamkeit bei der Umsetzung der Prinzipien zu steigern.",
    "Wir werden über unsere Aktivitäten und Fortschritte bei der Umsetzung der Prinzipien Bericht erstatten.",
]
KODIZES = ('<div class="ip-wrap ip-nh-split"><div class="ip-nh-head"><h2>Prinzipien und Kodizes</h2>'
           '<p class="ip-signatory"><img src="%s/pri-signatory.png" alt="Principles for Responsible Investment (PRI) – Signatory" width="300" height="111"></p>'
           '<p>Die Institutional Investment Group mit all ihren Tochtergesellschaften ist Unterzeichnerin der von den Vereinten Nationen unterstützten Initiative Principles for Responsible Investment (PRI). Dieses Netzwerk internationaler Investoren hat sich der Etablierung und Umsetzung der Prinzipien verantwortlichen Investierens in die Praxis verschrieben. Die PRI wurden von der Gemeinschaft der Investoren entwickelt und spiegeln die Ansicht wider, dass Umwelt-, Sozial- und Governance-Themen (ESG) die Performance von Investmentportfolios beeinflussen können und daher von Investoren angemessen berücksichtigt werden müssen, wenn sie ihrer gesellschaftlichen Verantwortung nachkommen wollen. Durch die Umsetzung der Grundsätze tragen die Unterzeichner zur Entwicklung eines nachhaltigeren globalen Finanzsystems bei.</p>'
           '<p>Die 2006 von der UNEP Finance Initiative und dem UN Global Compact ins Leben gerufenen PRI bieten einen freiwilligen Rahmen, mit dem alle Investoren ESG-Aspekte in ihre Entscheidungsfindung und ihre Eigentumsverhältnisse einbeziehen und so ihre Ziele besser mit denen der Gesellschaft als Ganzes in Einklang bringen können.</p></div>'
           '<div class="ip-nh-body"><h3>Die 6 Prinzipien für verantwortliches Investieren</h3><ol class="ip-principles">%s</ol></div></div>'
           % (IMG, "".join('<li><span class="ip-kicker">Prinzip %d:</span><p>%s</p></li>' % (i + 1, t) for i, t in enumerate(PRINZIPIEN))))

SDG = "".join('<li><img src="%s/sdg/sdg-%02d.jpg" alt="Nachhaltiges Entwicklungsziel %d" loading="lazy" width="120" height="120"></li>' % (IMG, i, i) for i in range(1, 18))
SDG += '<li><img src="%s/sdg/sdg-wheel.png" alt="SDG-Farbrad" loading="lazy" width="120" height="120"></li>' % IMG
GLOBAL_COMPACT = ('<div class="ip-wrap ip-nh-split"><div class="ip-nh-head"><h2>UN Global Compact</h2>'
                  '<p>Der UN Global Compact ist die weltweit größte und wichtigste Initiative für verantwortungsvolle Unternehmensführung. Er basiert auf 10 universellen Prinzipien in den Bereichen</p>'
                  '<ul class="ip-bullets"><li>Menschenrechte,</li><li>Arbeitsnormen,</li><li>Umweltschutz und</li><li>Korruptionsprävention</li></ul>'
                  '<p>und verfolgt zudem über die 17 Nachhaltigen Entwicklungsziele (Sustainable Development Goals) die Vision einer inklusiven und nachhaltigen Weltwirtschaft zum Nutzen aller Menschen, Gemeinschaften und Märkte.</p></div>'
                  '<div class="ip-nh-body"><h3>Die 17 Nachhaltigen Entwicklungsziele lauten:</h3><ul class="ip-sdg">%s</ul></div></div>' % SDG)

RISIKEN = split("Berücksichtigung von Nachhaltigkeitsrisiken in Investitionsentscheidungen", """<p>Nachhaltigkeitsrisiken sind Ereignisse oder Bedingungen in den Bereichen Umwelt, Soziales oder Unternehmensführung, deren Eintreten tatsächlich oder potenziell wesentliche negative Auswirkungen auf den Wert einer Investition haben könnte.</p>
<p>Im Rahmen unseres Investitionsentscheidungsprozesses werden Nachhaltigkeitsrisiken im Zuge unseres Adverse Sustainable Screenings identifiziert und bewertet. Dabei prüfen wir eine Investition anhand definierter ESG-Kriterien auf Ereignisse oder Bedingungen, die sich wesentlich nachteilig auf ihren Wert auswirken können. Die Ergebnisse dieses Screenings fließen in die abschließende Investitionsentscheidung ein. Überschreiten die identifizierten Nachhaltigkeitsrisiken die von uns festgelegten Wesentlichkeitsschwellen, kann dies zur Ablehnung der Investition aus Nachhaltigkeitsgründen führen.</p>""")

KRITERIEN = [
    ("Pollution", "Umwelt &amp; Biodiversität", "Anzeichen für einen mangelhaften Umgang bei der Beachtung von Umweltstandards oder der Vermeidung oder Behebung von Kontaminationen"),
    ("Carbon", "Treibhauseffekt-fördernde Gase ohne Ausstiegsszenario", "Anzeichen für die direkte Unterstützung der Produktion oder des Vertriebs von ozonzerstörenden Gasen ohne Ausstiegsszenario"),
    ("Bomb", "Kontroverse Waffen / Rüstung", "Anzeichen für die direkte Unterstützung der Produktion oder des Vertriebs kontroverser Waffen (Streubomben, Anti-Personenminen) oder relevanter Systemkomponenten"),
    ("ShakeHands", "Menschen- &amp; Arbeitnehmerrechte", "Anzeichen für einen mangelhaften Umgang bei Verstößen gegen internationale Menschenrechtskonventionen oder die ILO-Kernarbeitsnormen (Kinderarbeit, Zwangsarbeit, Diskriminierung, Versammlungsfreiheit)"),
    ("Money", "Geldwäscheprävention", "Anzeichen für einen mangelhaften Umgang im Screening oder bei Vorfällen zu Geldwäsche"),
    ("Money", "Korruptionsprävention", "Anzeichen für einen mangelhaften Umgang im Screening oder mit Korruptions- und Bestechungsvorfällen"),
]
AUSWIRKUNGEN = split("Systematische Berücksichtigung von nachteiligen Nachhaltigkeitsauswirkungen",
                     "<p>Investitionsvorschläge werden auf wichtige negative Nachhaltigkeitsauswirkungen und Nachhaltigkeitsindikatoren im Rahmen unseres Adverse Sustainable Screenings besonders geprüft:</p>"
                     '<ul class="ip-criteria">%s</ul>' % "".join(
                         '<li><img src="%s/icon-%s.svg" alt="" width="56" height="56"><div><span class="ip-kicker">%s</span><p><strong>%s</strong></p></div></li>' % (IMG, ic, t, d)
                         for ic, t, d in KRITERIEN)
                     + '<p>Wobei wir uns in unserem <a href="/nachhaltigkeit/pai-statement/">Principal Adverse Impact Statement</a> auf folgende drei Indikatoren fokussieren: (i) fossile Energieträger, (ii) Energie-Effizienz und (iii) Intensität des Energieverbrauchs. Unsere Strategien zur Feststellung und Gewichtung dieser Nachhaltigkeitsindikatoren sind in dem Statement beschrieben.</p>'
                     "<p>Die Ergebnisse des Adverse Sustainable Screenings fließen in die abschließende Investitionsentscheidung ein und führen im Fall der Überschreitung von Wesentlichkeitsgrenzen zu unserem Veto aus Nachhaltigkeitsgründen.</p>")

STEWARDSHIP = split("Stewardship", """<p>Befördert durch unsere gewonnene Marktstellung und die damit gestiegene gesellschaftliche Verantwortung – aber auch durch die generelle gesellschaftliche Entwicklung – ist es unser wesentliches Bestreben den eigenen Wirkungskreis zu nutzen, um einen bedeutsamen Unternehmensbeitrag für eine nachhaltige Umwelt und Gesellschaft zu leisten.</p>
<p>Dazu zählt auch die Nutzung des eigenen Einflusses (Stewardship) auf Dritte zur Optimierung des langfristigen Gesamtwerts, einschließlich gemeinsamer wirtschaftlicher, sozialer und ökologischer Werte, von denen die Renditen und die Interessen der Anleger und Begünstigten abhängen.</p>
<p>Wie wir als Institutional Investment Group im Sinne der Nachhaltigkeit verantwortungsvoll Einfluss nehmen wollen beschreibt die folgende Leitlinie:</p>
<p><a href="/wp-content/themes/Divi-SIN/downloads/2023-10-31-2IG-Stewardship-Policy.pdf" target="_blank" rel="noopener">Stewardship-Policy</a>.</p>""")

VERGUETUNG = """<div class="ip-wrap"><div class="ip-nh-card">
<h2>Nachhaltigkeit in der Vergütungspolitik</h2>
<p>Unsere Nachhaltigkeitsüberzeugungen stellen seit Unternehmensgründung einen zentralen Baustein unserer Vergütungspolitik dar. Dies gilt vor allem für Mitarbeiter mit variablem Vergütungsanteil, da die Einhaltung unserer Nachhaltigkeitsüberzeugungen Bestandteil der jährlichen individuellen Ziele wird. Variable Vergütungsbestandteile werden bei Nachhaltigkeitsverstößen im Rahmen des arbeitsrechtlich Möglichen und in Abhängigkeit von der Bedeutung der Verstöße nicht gewährt; insoweit werden Nachhaltigkeitsverstöße monetär sanktioniert. Zudem werden teils auch individuelle Prämien für das Erreichen von positiv definierten Nachhaltigkeitszielen vereinbart. Damit möchten wir bedeutsames nachhaltiges Handeln auch monetär wertschätzen.</p>
</div></div>"""

HISTORIE = """<div class="ip-wrap ip-nh-hist"><details open><summary>Änderungshistorie</summary>
<p>Nachstehend erläutern wir inhaltliche Änderungen der auf dieser Internetseite veröffentlichten Angaben gemäß Art. 3, Art. 5 und Art. 10 der Verordnung (EU) 2019/2088. Rein redaktionelle Änderungen ohne Auswirkung auf den Inhalt werden nicht gesondert aufgeführt.</p>
<h3>14.08.2024 – Berücksichtigung nachteiliger Nachhaltigkeitsauswirkungen</h3>
<p>Ergänzt wurden die Angaben zu den im Principal Adverse Impact Statement genannten Nachhaltigkeitsindikatoren sowie zu den Strategien für deren Feststellung und Gewichtung.</p>
<h3>20.07.2026 – Strategien zur Einbeziehung von Nachhaltigkeitsrisiken</h3>
<p>Geändert wurde die Darstellung der Strategien zur Einbeziehung von Nachhaltigkeitsrisiken, indem sie als eigenständiger Abschnitt „Berücksichtigung von Nachhaltigkeitsrisiken in Investitionsentscheidungen“ aufgeführt wird.</p>
</details></div>"""

secs = [
    section("ip-hero ip-nh-hero", "Hero", HERO),
    section("ip-nh-photo ip-nh-photo--ueberzeugung", "Unsere Überzeugungen", UEBERZEUGUNGEN),
    section("ip-sec ip-sec--white", "Zentrale Eckpunkte", ECKPUNKTE),
    section("ip-sec ip-sec--mist", "Prinzipien und Kodizes", KODIZES),
    section("ip-sec ip-sec--white", "UN Global Compact", GLOBAL_COMPACT),
    section("ip-sec ip-sec--mist", "Nachhaltigkeitsrisiken", RISIKEN),
    section("ip-sec ip-sec--white", "Nachteilige Auswirkungen", AUSWIRKUNGEN),
    section("ip-sec ip-sec--mist", "Stewardship", STEWARDSHIP),
    section("ip-nh-photo ip-nh-photo--verguetung", "Vergütungspolitik", VERGUETUNG),
    section("ip-sec ip-sec--white", "Änderungshistorie", HISTORIE),
]
content = "<!-- wp:divi/placeholder -->" + "\n".join(secs) + "<!-- /wp:divi/placeholder -->"
open(sys.argv[1] + "/nach.txt", "w", encoding="utf-8").write(content)
print(len(content))
