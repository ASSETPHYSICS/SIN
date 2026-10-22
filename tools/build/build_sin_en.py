#!/usr/bin/env python3
"""ServiceInvest: englische Fassungen (offizielle Texte, keine Maschinenübersetzung).

Aufruf:  IP_CONTENT_DIR="SIN Content" python3 tools/build/build_sin_en.py <Ausgabeverzeichnis>
Schreibt je deutscher Seite <deutscher-slug>.json mit {"title": …, "segments": [HTML …]}. Die Abschnitte stehen in
derselben Reihenfolge und Struktur wie die Textmodule der deutschen Seite; tools/i18n/official.php übernimmt sie
in den Übersetzungsspeicher. Prüft, dass jedes Wort der Quelldatei im Ergebnis vorkommt.

Abweichungen vom Quelltext (bewusst):
  - "Principle Adverse Impacts" -> "Principal Adverse Impacts" (Vorgabe, wie auf der 2IP-Seite)
  - "The 6 Priciples" -> "The 6 Principles" (Tippfehler in der Überschrift)
  - Fußzeilen-Reste der Altseite und Bildquellen-Hinweise zu dort verwendeten Fotos entfallen
"""
import html, json, os, re, sys

sys.path.insert(0, os.path.dirname(__file__))
from build_legal import render  # noqa: E402

SRC = os.environ.get("IP_CONTENT_DIR", "SIN Content") + "/"
IMG = "/wp-content/themes/Divi-SIN/images/nachhaltigkeit"
UP = "/wp-content/uploads/"
PDF = {
    "pai_de": UP + "2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf",
    "pai_en": UP + "2026/06/2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_ENG-clean.pdf",
    "debeka_de": UP + "2025/08/Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.-10-SFDR_Art_8-c-final.pdf",
    "debeka_en": UP + "2025/08/Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.10-SFDR-english.pdf",
    "tas_de": UP + "2025/04/2024-06-19-VOe_Internetseite-Art.-10-SFDR-TAS-Wohnen-I-c.pdf",
    "tas_en": UP + "2025/04/2024-04-19-website-disclosure-Art.-10-SFDR-TAS-Wohnen-I-c.pdf",
    "stewardship": UP + "2024/12/2023-10-31-2IG-Stewardship-Policy.pdf",
}
EN = {"remuneration": "/en/remuneration-policy/", "pai": "/en/statement-on-principal-adverse-impacts/"}
BOILER = ("Impressum / Datenschutzerkl", "©20", "Bildquelle:")


def src(fn):
    raw = open(SRC + fn, encoding="utf-8-sig").read().replace("\r\n", "\n")
    return "\n".join(l for l in raw.split("\n") if not l.strip().startswith(BOILER))


def e(t):
    return html.escape(re.sub(r"\s+", " ", t.strip()), quote=False)


def p(t, cls=""):
    return '<p%s>%s</p>' % (' class="%s"' % cls if cls else "", e(t))


def dls(items):
    return '<ul class="ip-dls">%s</ul>' % "".join(
        '<li><a class="ip-dl" href="%s" target="_blank" rel="noopener">%s</a></li>' % (href, e(label)) for label, href in items)


def hero(h1, lead=""):
    return '<div class="ip-wrap ip-hero__inner"><h1>%s</h1>%s</div>' % (e(h1), p(lead, "ip-lead") if lead else "")


def legal(inner):
    return '<div class="ip-wrap ip-legal__inner">%s</div>' % inner


def lines(raw):
    return [l.strip() for l in raw.split("\n") if l.strip()]


def check(name, raw, segments, ignore=(), fixes=()):
    plain = html.unescape(re.sub(r"<[^>]+>", " ", " ".join(segments)))
    for a, b in fixes:
        raw = raw.replace(a, b)
    norm = lambda s: re.sub(r"\s+", "", s)
    miss = [w for w in re.split(r"\s+", raw) if w and norm(w) not in norm(plain) and w not in ignore]
    print("%-34s %6d Zeichen | im Original, aber nicht im Ergebnis: %s" % (name, sum(map(len, segments)), miss[:12]))


out = sys.argv[1]
pages = {}
PRINCIPAL = (("Principle Adverse Impacts", "Principal Adverse Impacts"),)

# ------------------------------------------------------------------ Imprint
raw = src("Imprint.txt")
body = render("impressum", "Imprint", raw.replace("Imprint\n", "", 1))
body, n = re.subn(r"under this link", 'under <a href="%s">this link</a>' % EN["remuneration"], body)
assert n == 1
for h in ("Image rights:", "Information on remuneration policy"):
    body = body.replace("<p>%s<br>" % e(h), "<h3>%s</h3>\n<p>" % e(h))
pages["serviceinvest-impressum"] = ("Imprint", [legal(body)])
check("Imprint", raw, pages["serviceinvest-impressum"][1])

# ------------------------------------------------------------------ Privacy Policy (ohne Google Fonts / Google Maps, wie die deutsche Fassung)
raw = src("Privacy Policy.txt")
raw2, n = re.subn(r"Google Fonts\n.*?(?=III\. Data processing on our career site)", "", raw, flags=re.S)
assert n == 1 and "Google" not in raw2
body = render("web", "Privacy Policy", raw2.replace("Privacy Policy\n", "", 1))
pages["datenschutzerklaerung"] = ("Privacy Policy", [legal(body)])
check("Privacy Policy", raw2, pages["datenschutzerklaerung"][1])

# ------------------------------------------------------------------ Data Protection Information (Mieter)
raw = src("Data Protection Information.txt")
body = render("mieter", "Data Protection Information", raw.replace("Data Protection Information\n", "", 1))
# Verweis auf eine frühere temporäre Server-Adresse nicht verlinken (nur Text, siehe Hinweis an die Redaktion)
body = re.sub(r'<a href="(http://s1085564301\.online\.de/[^"]*)"[^>]*>[^<]*</a>', r"\1", body)
pages["datenschutzhinweisefuermieter"] = ("Data Protection Information", [legal(body)])
check("Data Protection Information", raw, pages["datenschutzhinweisefuermieter"][1])

# ------------------------------------------------------------------ Complaints Office
raw = src("Complaints Office for Fund Investor.txt")
B = [b.strip() for b in re.split(r"\n\s*\n", raw) if b.strip()]
first, intro = B[0].split("\n", 1)
mail = "beschwerdestelle@serviceinvest.de"
head, _, sub = first.partition(" of ")
tel = B[5].split("under the number")
inner = ("<h1>%s</h1>" % e(head) + p("of " + sub, "ip-lead") + p(intro) + p(B[1])
         + '<p>%s <a href="mailto:%s">%s</a></p>' % (e(B[2].replace(mail, "")), mail, mail)
         + p(B[3]) + "<p>%s</p>" % "<br>".join(e(x) for x in B[4].split("\n"))
         + '<p>%s <a href="tel:+49895443440">%s</a>.</p>' % (e(tel[0] + "under the number"), e(tel[1].rstrip("."))))
pages["beschwerdestelle"] = ("Complaints Office", [legal(inner)])
check("Complaints Office", raw, pages["beschwerdestelle"][1])

# ------------------------------------------------------------------ Remuneration Policy
raw = src("Remuneration Policy of ServiceInves.txt")
L = lines(raw)
h = ["<h1>Remuneration Policy</h1>", p("ServiceInvest Kapitalverwaltungsgesellschaft mbH", "ip-lead")]
i = 1
while i < len(L):
    l = re.sub(r"\s{2,}", " ", L[i])
    if l in ("1. Remuneration system", "Governance and Compensation Committee"):
        h.append("<h2>%s</h2>" % e(l))
    elif re.match(r"^\d+\.\d+\.\d+\.?\s", l):
        h.append("<h4>%s</h4>" % e(l))
    elif re.match(r"^\d+\.\d+\.?\s", l):
        h.append("<h3>%s</h3>" % e(l))
    elif l.startswith("Members of the Management Board"):
        h.append('<ul class="ip-bullets"><li>%s</li></ul>' % e(l))
    elif l == "Individual goals and":
        h.append('<ul class="ip-bullets"><li>%s</li><li>%s</li></ul>' % (e(l), e(L[i + 1])))
        i += 1
    else:
        h.append(p(l))
    i += 1
pages["verguetungspolitik"] = ("Remuneration Policy", [legal("".join(h))])
check("Remuneration Policy", raw, pages["verguetungspolitik"][1], ignore=("of",))

# ------------------------------------------------------------------ PAI Statement
raw = src("Statement on principal adverse impacts (en).txt")
L = lines(raw)
fix = lambda t: t.replace("Principle Adverse Impacts", "Principal Adverse Impacts")
pages["erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2"] = ("PAI Statement", [
    hero(L[0]),
    legal(p(fix(L[1]), "ip-lead") + p(fix(L[2])) + dls([(L[3], PDF["pai_de"]), (L[4], PDF["pai_en"])])),
])
check("PAI Statement", raw, pages["erklaerung-zu-den-wichtigsten-nachteiligen-auswirkungen-auf-die-nachhaltigkeitsfaktoren-2"][1], fixes=PRINCIPAL)

# ------------------------------------------------------------------ Debeka Immobilien Invest I
raw = src("Debeka Immobilien Invest I (en).txt")
L = lines(raw)
# 0 Titel, 1 Disclosure…, 2 regarding…, 3 Name, 4 ISIN, 5 Stand, 6 a) Summary, 7-8 Absätze, 9-10 Downloads
inner = (p(L[1], "ip-strong") + p(L[2]) + p(L[3], "ip-strong") + "<h2>%s</h2>" % e(L[6]) + p(L[7]) + p(L[8])
         + dls([(L[9], PDF["debeka_de"]), (L[10], PDF["debeka_en"])]))
pages["debeka-immobilien-invest-i"] = ("Debeka Immobilien Invest I", [hero(L[0], "%s · %s" % (L[4], L[5])), legal(inner)])
check("Debeka Immobilien Invest I", raw, pages["debeka-immobilien-invest-i"][1])

# ------------------------------------------------------------------ TAS Wohnen I
raw = src("TAS Wohnen I (en).txt")
L = lines(raw)
# 0 Titel, 1-2 Downloads, 3 Disclosure…regarding…, 4 Name, 5 ISIN, 6 Stand, danach a)…l), Hinweise
intro, _, regarding = L[3].partition(" regarding ")
h = [p(intro, "ip-strong"), p("regarding " + regarding), p(L[4], "ip-strong")]
rest = L[7:]
i = 0
while i < len(rest):
    l = rest[i]
    if re.match(r"^[a-l]\)\s", l):
        h.append("<h2>%s</h2>" % e(l))
    elif l.endswith(",") or l.endswith(", and"):
        items = []
        while i < len(rest) and not re.match(r"^[a-l]\)\s", rest[i]) and len(rest[i]) < 90:
            items.append(rest[i]); i += 1
            if items[-1].endswith("."):
                break
        h.append('<ul class="ip-bullets">%s</ul>' % "".join("<li>%s</li>" % e(x) for x in items))
        continue
    elif l == "History of changes:":
        h.append("<h3>%s</h3>" % e(l))
    else:
        h.append(p(l))
    i += 1
inner = "".join(h) + dls([(L[1], PDF["tas_de"]), (L[2], PDF["tas_en"])])
pages["tas-wohnen-1"] = ("TAS Wohnen I", [hero(L[0], "%s · %s" % (L[5], L[6])), legal(inner)])
check("TAS Wohnen I", raw, pages["tas-wohnen-1"][1])

# ------------------------------------------------------------------ Acting sustainably (Struktur wie build_nach.py)
raw = src("Acting sustainably.txt")
T = lines(raw)
ix = lambda s: T.index(s)


def split(title, body):
    return '<div class="ip-wrap ip-nh-split"><div class="ip-nh-head"><h2>%s</h2></div><div class="ip-nh-body">%s</div></div>' % (title, body)


seg = []
seg.append('<div class="ip-wrap ip-hero__inner">\n<h1>%s</h1>\n<p class="ip-lead">%s</p>\n</div>' % (e(T[0]), e(T[1])))
a = ix("Our convictions")
seg.append('<div class="ip-wrap"><div class="ip-nh-card">\n<h2>Our convictions</h2>\n%s\n</div></div>' % "\n".join(p(x) for x in T[a + 1:a + 4]))
a = ix("Central cornerstones for our sustainable actions")
c = T[a + 1:ix("Principles and codes of conduct")]
eck = ('<p>%s</p>\n<ul class="ip-check">\n<li>%s</li>\n<li>%s\n<ul class="ip-sub"><li>%s</li><li>%s</li></ul></li>\n<li>%s</li>\n'
       '<li>%s\n<ul class="ip-sub"><li>%s</li><li>%s</li></ul>\n</li>\n<li>%s</li>\n</ul>') % tuple(e(x).replace("CO2", "CO<sub>2</sub>") for x in c)
seg.append(split(e(T[a]), eck))
a = ix("Principles and codes of conduct")
prin = [T[ix("PRINCIPLE %d" % k) + 1] for k in range(1, 7)]
seg.append('<div class="ip-wrap ip-nh-split"><div class="ip-nh-head"><h2>Principles and codes of conduct</h2>'
           '<p class="ip-signatory"><img src="%s/pri-signatory.png" alt="Principles for Responsible Investment (PRI) – Signatory" width="300" height="111"></p>'
           '%s%s</div><div class="ip-nh-body"><h3>%s</h3><ol class="ip-principles">%s</ol></div></div>'
           % (IMG, p(T[a + 1]), p(T[a + 2]), e(T[a + 3].replace("Priciples", "Principles")),
              "".join('<li><span class="ip-kicker">Principle %d:</span><p>%s</p></li>' % (k + 1, e(t)) for k, t in enumerate(prin))))
a = ix("UN Global Compact")
sdg = "".join('<li><img src="%s/sdg/sdg-%02d.jpg" alt="Sustainable Development Goal %d" loading="lazy" width="120" height="120"></li>' % (IMG, k, k) for k in range(1, 18))
sdg += '<li><img src="%s/sdg/sdg-wheel.png" alt="SDG colour wheel" loading="lazy" width="120" height="120"></li>' % IMG
seg.append('<div class="ip-wrap ip-nh-split"><div class="ip-nh-head"><h2>UN Global Compact</h2>%s<ul class="ip-bullets">%s</ul>%s</div>'
           '<div class="ip-nh-body"><h3>%s</h3><ul class="ip-sdg">%s</ul></div></div>'
           % (p(T[a + 1]), "".join("<li>%s</li>" % e(x) for x in T[a + 2:a + 6]), p(T[a + 6]), e(T[a + 7]), sdg))
a = ix("Incorporating Sustainability Risks into Investment Decisions")
seg.append(split(e(T[a]), "%s\n%s" % (p(T[a + 1]), p(T[a + 2]))))
a = ix("Systematic consideration of adverse sustainability impacts")
icons = ("Pollution", "Carbon", "Bomb", "ShakeHands", "Money", "Money")
crit = T[a + 2:a + 14]
seg.append(split(e(T[a]), p(T[a + 1]) + '<ul class="ip-criteria">%s</ul>' % "".join(
    '<li><img src="%s/icon-%s.svg" alt="" width="56" height="56"><div><span class="ip-kicker">%s</span><p><strong>%s</strong></p></div></li>'
    % (IMG, icons[k], e(crit[2 * k]), e(crit[2 * k + 1])) for k in range(6)) + p(T[a + 14])))
a = ix("Stewardship")
seg.append(split("Stewardship", "%s\n%s\n%s\n<p><a href=\"%s\" target=\"_blank\" rel=\"noopener\">%s</a></p>"
                 % (p(T[a + 1]), p(T[a + 2]), p(T[a + 3]), PDF["stewardship"], e(T[a + 4]))))
a = ix("Sustainability in the remuneration policy")
seg.append('<div class="ip-wrap"><div class="ip-nh-card">\n<h2>%s</h2>\n%s\n</div></div>' % (e(T[a]), p(T[a + 1])))
a = ix("Revision History")
seg.append('<div class="ip-wrap ip-nh-hist"><details open><summary>Revision History</summary>\n%s\n<h3>%s</h3>\n%s\n<h3>%s</h3>\n%s\n</details></div>'
           % (p(T[a + 1]), e(T[a + 2]), p(T[a + 3]), e(T[a + 4]), p(T[a + 5])))
pages["nachhaltigkeit-2"] = ("Sustainability", seg)
check("Acting sustainably", raw, seg, ignore=tuple(re.findall(r"E-WEB-Goal\S*|SDG|cirlcle_RGB", raw)) + ("PRINCIPLE", "1", "2", "3", "4", "5", "6"),
      fixes=(("Priciples", "Principles"),))

for slug, (title, segments) in pages.items():
    with open(os.path.join(out, slug + ".json"), "w", encoding="utf-8") as f:
        json.dump({"title": title, "segments": segments}, f, ensure_ascii=False, indent=1)
