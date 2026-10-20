#!/usr/bin/env python3
import json, sys

VER = "5.0.0-public-beta.2"


def j(obj):
    s = json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
    return s.replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026").replace("--", "\\u002d\\u002d")


def section(css, label, inner, sid=None):
    zero = {"top": "0px", "bottom": "0px", "left": "0px", "right": "0px", "syncVertical": "on", "syncHorizontal": "on"}
    sec = {"module": {"meta": {"adminLabel": {"desktop": {"value": label}}},
                      "decoration": {"spacing": {"desktop": {"value": {"padding": zero}}}, "layout": {"desktop": {"value": {"display": "block"}}},
                                     "attributes": {"desktop": {"value": {"attributes": [{"id": "ips-" + label[:5], "name": "class", "value": "ip-divi-sec " + css, "adminLabel": "class", "targetElement": "main"}] + ([{"id": "ipi-" + sid, "name": "id", "value": sid, "adminLabel": "id", "targetElement": "main"}] if sid else [])}}}}},
           "builderVersion": VER}
    row = {"module": {"advanced": {"columnStructure": {"desktop": {"value": "4_4"}}, "flexColumnStructure": {"desktop": {"value": "equal-columns_1"}}},
                      "decoration": {"sizing": {"desktop": {"value": {"width": "100%", "maxWidth": "none", "alignment": "center"}}}, "spacing": {"desktop": {"value": {"padding": zero}}},
                                     "layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    col = {"module": {"advanced": {"type": {"desktop": {"value": "4_4"}}}, "decoration": {"layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    txt = {"content": {"innerContent": {"desktop": {"value": inner}}}, "builderVersion": VER}
    return ("<!-- wp:divi/section %s -->\n<!-- wp:divi/row %s -->\n<!-- wp:divi/column %s -->\n<!-- wp:divi/text %s /-->\n"
            "<!-- /wp:divi/column -->\n<!-- /wp:divi/row -->\n<!-- /wp:divi/section -->" % (j(sec), j(row), j(col), j(txt)))


CONTACT = "#anfrage"

DISCLAIMER = """<div class="ip-disclaimer" id="ip-disclaimer" role="dialog" aria-modal="true" aria-labelledby="ip-disclaimer-title" hidden>
<div class="ip-disclaimer__box">
<h2 id="ip-disclaimer-title">Disclaimer</h2>
<p><strong>Die auf dieser Seite angebotenen Informationen richten sich ausschließlich an institutionelle Investoren in Deutschland, Österreich und der Schweiz.</strong></p>
<p><strong>Bei den dargestellten Analysen handelt es sich weder um Anlageberatung noch Vermittlung von Anlageprodukten.</strong></p>
<p><strong>Mit dem Klick auf den Bestätigungsbutton erkläre ich, dass ich diesen Hinweis zur Kenntnis genommen habe und institutioneller Investor bin.</strong></p>
<p><button type="button" class="ip-btn" id="ip-disclaimer-ok">Bestätigen</button></p>
</div></div>"""

SLIDES = ["01-rechenzentrum", "02-energiespeicher", "03-pipeline", "04-solar", "05-wind", "06-verkehr"]
SLIDER = '<div class="ip-slider" aria-hidden="true">' + "".join(
    '<img class="ip-slide" src="/wp-content/themes/Divi-2IP/images/secop/%s.jpg" alt="" width="2200" height="924" %s style="--i:%d">' % (s, 'fetchpriority="high"' if i == 0 else 'loading="lazy"', i)
    for i, s in enumerate(SLIDES)) + "</div>"

HERO = SLIDER + """<div class="ip-wrap ip-hero__inner">
<h1>Zweite Meinung zu Infrastruktur-Fonds gefragt?</h1>
<p class="ip-lead">Mit unserer „Second Opinion“ erkennen Sie Chancen und Risiken institutioneller Anlageprodukte.</p>
</div>"""

ANGEBOT = """<div class="ip-wrap ip-nh-split">
<div class="ip-nh-head"><h2>Unser Angebot für institutionelle Infrastruktur-Anleger</h2></div>
<div class="ip-nh-body">
<p class="ip-strong">Holen Sie sich eine unabhängige zweite Meinung zu Ihrem Infrastrukturfonds-Angebot!</p>
<p>Institutional Investment Consulting-Partners (2ICP) unterzieht regelmäßig Infrastrukturfonds aus allen wesentlichen Sektoren einer fundierten Investitionsprüfung.</p>
<p>In unserer wirtschaftlichen Due Diligence identifizieren wir präzise die Stärken und Schwächen solcher Produkte und bewerten diese für unsere Kunden.</p>
<p>Auf dieser Seite listen wir bereits erstellte Analysen zu derzeit verfügbaren Produkten. Sollten Sie sich für eine Second Opinion zu einem anderen Investmentprodukt interessieren, <a href="%s">sprechen Sie uns gern an!</a></p>
<p class="ip-strong">Auszug unserer Analysen von aktuell verfügbaren Infrastruktur-Produkten:</p>
<p class="ip-product">Blackstone Infrastructure Partners Europe (LUX) SCSp</p>
<p class="ip-strong">Wir haben diese Produkte bereits geprüft und Sie können daran partizipieren – zu einem attraktiven Preis!</p>
</div></div>""" % CONTACT

PREISE = """<div class="ip-wrap ip-price-wrap">
<div class="ip-price-head">
<h2>Preise</h2>
<div class="ip-price-intro">
<p>Unser Preismodell ist – entsprechend des Anlegerbedarfs – flexibel gestaltet.</p>
<p class="ip-strong">Wir bieten zwei Basis-Versionen an:</p>
</div>
</div>
<div class="ip-price-cards">
<article class="ip-card ip-price ip-price--summary">
<h3>Management Summary</h3><p class="ip-price__sub">für Entscheider</p>
<ul class="ip-check">
<li>SWOT-Analyse mit den allen wesentlichen Ergebnissen aus der Vollversion</li>
<li>Komprimierte Herausarbeitung der den Anlageerfolg beeinflussenden Fondsmerkmale</li>
<li>Zusammenfassung der Risiken und Chancen</li>
</ul>
<p class="ip-price__cta"><a class="ip-btn" href="%s">Jetzt anfragen</a></p>
</article>
<article class="ip-card ip-price ip-price--full">
<h3>Vollversion</h3><p class="ip-price__sub">für den Deep Dive</p>
<ul class="ip-check">
<li>SWOT-Analyse mit allen wesentlichen Ergebnissen</li>
<li>Detaillierte Herausarbeitung der den Anlagerfolg beeinflussenden Fondsmerkmale</li>
<li>Detaillierte Bewertung der Ertragsprognosen und Key Performance Indicators</li>
<li>Differenziertes Scoringsystem bis auf Ebene einzelner Infrastrukturanlagen /-gesellschaften</li>
</ul>
<p class="ip-price__cta"><a class="ip-btn" href="%s">Jetzt anfragen</a></p>
</article>
</div></div>""" % (CONTACT, CONTACT)

CTA = """<div class="ip-wrap ip-nh-split">
<div class="ip-nh-head"><h2>Sie möchten eine Analyse bestellen oder haben Fragen zu unseren Second Opinion-Angeboten?</h2></div>
<div class="ip-nh-body">
[appointment_booking tab="anfrage"]
</div></div>"""

secs = [
    section("ip-hero ip-fund-hero ip-fund-hero--secop", "Hero", HERO + DISCLAIMER),
    section("ip-sec ip-sec--white ip-secop-offer", "Angebot", ANGEBOT),
    section("ip-sec ip-sec--mist ip-secop-prices", "Preise", PREISE),
    section("ip-sec ip-sec--forest ip-secop-form", "Anfrage", CTA, "anfrage"),
]
open(sys.argv[1] + "/secop.txt", "w", encoding="utf-8").write("<!-- wp:divi/placeholder -->" + "\n".join(secs) + "<!-- /wp:divi/placeholder -->")
print("ok")
