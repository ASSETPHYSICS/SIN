#!/usr/bin/env python3
"""ServiceInvest: Divi-5-Blockinhalt für PAI-Statement, Beschwerdestelle, Vergütungspolitik und die Fondsseiten.

Aufruf:  IP_CONTENT_DIR="SIN Content" python3 tools/build/build_sin_pages.py <Ausgabeverzeichnis>
Schreibt je Seite <slug>.txt und prüft, dass jedes Wort der Quelldatei im Ergebnis vorkommt.
Der Wortlaut der Offenlegungen bleibt unverändert; PDFs liegen unter /wp-content/uploads/offenlegung/.
"""
import html, json, os, re, sys

SRC = os.environ.get("IP_CONTENT_DIR", "SIN Content") + "/"
VER = "5.0.0-public-beta.2"
DL = "/wp-content/uploads/offenlegung/"


def j(obj):
    s = json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
    return s.replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026").replace("--", "\\u002d\\u002d")


def section(css, label, inner):
    zero = {"top": "0px", "bottom": "0px", "left": "0px", "right": "0px", "syncVertical": "on", "syncHorizontal": "on"}
    sec = {"module": {"meta": {"adminLabel": {"desktop": {"value": label}}},
                      "decoration": {"spacing": {"desktop": {"value": {"padding": zero}}}, "layout": {"desktop": {"value": {"display": "block"}}},
                                     "attributes": {"desktop": {"value": {"attributes": [{"id": "ips-" + label[:6], "name": "class", "value": "ip-divi-sec " + css, "adminLabel": "class", "targetElement": "main"}]}}}}},
           "builderVersion": VER}
    row = {"module": {"advanced": {"columnStructure": {"desktop": {"value": "4_4"}}, "flexColumnStructure": {"desktop": {"value": "equal-columns_1"}}},
                      "decoration": {"sizing": {"desktop": {"value": {"width": "100%", "maxWidth": "none", "alignment": "center"}}}, "spacing": {"desktop": {"value": {"padding": zero}}},
                                     "layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    col = {"module": {"advanced": {"type": {"desktop": {"value": "4_4"}}}, "decoration": {"layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    txt = {"content": {"innerContent": {"desktop": {"value": inner}}}, "builderVersion": VER}
    return ("<!-- wp:divi/section %s -->\n<!-- wp:divi/row %s -->\n<!-- wp:divi/column %s -->\n<!-- wp:divi/text %s /-->\n"
            "<!-- /wp:divi/column -->\n<!-- /wp:divi/row -->\n<!-- /wp:divi/section -->" % (j(sec), j(row), j(col), j(txt)))


def page(*secs):
    return "<!-- wp:divi/placeholder -->" + "\n".join(secs) + "<!-- /wp:divi/placeholder -->"


def e(t):
    return html.escape(re.sub(r"\s+", " ", t.strip()), quote=False)


def p(t, cls=""):
    return '<p%s>%s</p>' % (' class="%s"' % cls if cls else "", e(t))


def dls(items):
    return '<ul class="ip-dls">%s</ul>' % "".join(
        '<li><a class="ip-dl" href="%s%s" target="_blank" rel="noopener">%s</a></li>' % (DL, f, e(label)) for label, f in items)


def hero(mod, h1, lead=""):
    inner = '<div class="ip-wrap ip-hero__inner"><h1>%s</h1>%s</div>' % (e(h1), p(lead, "ip-lead") if lead else "")
    return section("ip-hero ip-fund-hero ip-fund-hero--" + mod, "Hero", inner)


def legal(inner, extra=""):
    return section("ip-sec ip-sec--white ip-legal" + (" " + extra if extra else ""), "Text", '<div class="ip-wrap ip-legal__inner">%s</div>' % inner)


def src(fn):
    return open(SRC + fn, encoding="utf-8-sig").read().replace("\r\n", "\n")


def check(name, raw, built, ignore=()):
    # Text steckt JSON-maskiert in den Divi-Blockkommentaren: Werte der Textmodule auslesen
    values = [json.loads('"%s"' % v) for v in re.findall(r'"innerContent":\{"desktop":\{"value":"((?:[^"\\]|\\.)*)"', built)]
    plain = html.unescape(re.sub(r"<[^>]+>", " ", " ".join(values)))
    norm = lambda s: re.sub(r"\s+", "", s)
    words = [w for w in re.split(r"\s+", raw) if w and not w.startswith("(https://")]
    miss = [w for w in words if norm(w) not in norm(plain) and w not in ignore]
    print("%-24s %6d Zeichen | im Original, aber nicht im Ergebnis: %s" % (name, len(built), miss[:12]))


out = sys.argv[1]
pages = {}

# ------------------------------------------------------------------ PAI-Statement
raw = src("SI-PAI Statement.txt")
L = [l.strip() for l in raw.split("\n") if l.strip()]
inner = (p(L[2], "ip-lead").replace("ip-lead", "ip-lead") + p(L[3])
         + dls([(L[4], "2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_DE-clean.pdf"),
                (L[6], "2026-06-22_SIN_Annex_I-PAI-Statement_v2.2_ENG-clean.pdf")]))
pages["pai-statement"] = page(hero("pai", L[1]), legal(inner))
check("pai-statement", raw, pages["pai-statement"], ignore=("PAI", "Statement"))

# ------------------------------------------------------------------ Beschwerdestelle
raw = src("SI-Beschwerdestelle für Fondsanleger d.txt")
B = [b.strip() for b in re.split(r"\n\s*\n", raw) if b.strip()]
# B[0] "Beschwerdestelle für Fondsanleger der ServiceInvest", B[1] "Kapitalverwaltungsgesellschaft mbH („ServiceInvest“)\nDer Zufriedenheit …"
first, intro = B[1].split("\n", 1)
mail = "beschwerdestelle@serviceinvest.de"
inner = ("<h1>Beschwerdestelle für Fondsanleger</h1>"
         + p("der ServiceInvest " + first, "ip-lead")
         + p(intro) + p(B[2])
         + "<p>%s <a href=\"mailto:%s\">%s</a></p>" % (e(B[3].replace(mail, "")), mail, mail)
         + p(B[4])
         + "<p>%s</p>" % "<br>".join(e(x) for x in B[5].split("\n"))
         + "<p>%s <a href=\"tel:+49895443440\">%s</a>.</p>" % (e(B[6].split("unter der Nummer")[0] + "unter der Nummer"), e(B[6].split("unter der Nummer")[1].rstrip("."))))
pages["beschwerdestelle"] = page(legal(inner))
check("beschwerdestelle", raw, pages["beschwerdestelle"])

# ------------------------------------------------------------------ Vergütungspolitik
raw = src("SI-ServiceInvest Kapitalverwaltungsges.txt")
foot_m = re.search(r"\n\[1\] (.+)$", raw.strip())
body_raw = raw[: raw.rindex("[1] Der in diesem Text")]
blocks = [b for b in re.split(r"\n\s*\n", body_raw) if b.strip()]
h = ["<h1>Vergütungspolitik</h1>", p("ServiceInvest Kapitalverwaltungsgesellschaft mbH", "ip-lead")]
for bi, b in enumerate(blocks):
    lines = [l.strip() for l in b.split("\n") if l.strip()]
    if bi == 0:
        continue  # Titelzeile
    i = 0
    while i < len(lines):
        l = re.sub(r"\s{2,}", " ", lines[i])
        if l in ("Vergütungssystematik", "Governance und Vergütungsausschuss"):
            h.append("<h2>%s</h2>" % e(l))
        elif re.match(r"^\d+\.\d+\.\d+\.?\s", l):
            h.append("<h4>%s</h4>" % e(l))
        elif re.match(r"^\d+\.\d+\.?\s", l):
            h.append("<h3>%s</h3>" % e(l))
        elif l.startswith("Mitglieder der Geschäftsführung"):
            h.append('<ul class="ip-bullets"><li>%s</li></ul>' % e(l))
        elif l == "Individuelle Ziele und":
            h.append('<ul class="ip-bullets"><li>%s</li><li>%s</li></ul>' % (e(l), e(lines[i + 1])))
            i += 1
        else:
            t = e(l).replace("Mitarbeiter[1]", 'Mitarbeiter<sup><a href="#fn1" id="fnref1">[1]</a></sup>')
            h.append("<p>%s</p>" % t)
        i += 1
h.append('<p class="ip-note-line" id="fn1"><a href="#fnref1">[1]</a> %s</p>' % e(foot_m.group(1)))
pages["verguetungspolitik"] = page(legal("".join(h)))
check("verguetungspolitik", raw, pages["verguetungspolitik"], ignore=("ServiceInvest", "Kapitalverwaltungsgesellschaft", "mbH", "–"))

# ------------------------------------------------------------------ Debeka Immobilien Invest I
raw = src("SI-Debeka Immobilien Invest I.txt")
L = [l.strip() for l in raw.split("\n") if l.strip() and not l.strip().startswith("(https://")]
# 0 Titel, 1 Offenlegung…, 2 betreffend…, 3 Name, 4 ISIN, 5 Stand, 6 Zusammenfassung, 7-8 Absätze, 9-10 Downloads
inner = (p(L[1], "ip-strong") + p(L[2]) + p(L[3], "ip-strong") + "<h2>%s</h2>" % e(L[6]) + p(L[7]) + p(L[8])
         + dls([(L[9], "Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.-10-SFDR_Art_8-c-final.pdf"),
                (L[10], "Debeka-Immo-Invest-Entwurf-VOe_Internetseite-Art.10-SFDR-english.pdf")]))
pages["debeka-immobilien-invest-i"] = page(hero("debeka", L[0], "(%s) · %s" % (L[4], L[5])), legal(inner, "ip-fund"))
check("debeka-immobilien-invest-i", raw, pages["debeka-immobilien-invest-i"])

# ------------------------------------------------------------------ TAS Wohnen I
raw = src("SI-TAS Wohnen I.txt")
L = [l.strip() for l in raw.split("\n") if l.strip() and not l.strip().startswith("(https://")]
# 0 Titel, 1-2 Downloads, 3 Offenlegung…, 4 betreffend…, 5 Name, 6 ISIN, 7 Stand, danach a)…l), Hinweise
downloads = dls([(L[1], "2024-06-19-VOe_Internetseite-Art.-10-SFDR-TAS-Wohnen-I-c.pdf"),
                 (L[2], "2024-04-19-website-disclosure-Art.-10-SFDR-TAS-Wohnen-I-c.pdf")])
h = [p(L[3], "ip-strong"), p(L[4]), p(L[5], "ip-strong")]
rest = L[8:]
i = 0
while i < len(rest):
    l = rest[i]
    if re.match(r"^[a-l]\)\s", l):
        h.append("<h2>%s</h2>" % e(l))
    elif l.endswith(",") or (i > 0 and rest[i - 1].endswith(",") and l.endswith(".") and len(l) < 80):
        items = []
        while i < len(rest) and (rest[i].endswith(",") or (items and len(rest[i]) < 80 and rest[i].endswith("."))):
            items.append(rest[i]); i += 1
            if items[-1].endswith("."):
                break
        h.append('<ul class="ip-bullets">%s</ul>' % "".join("<li>%s</li>" % e(x) for x in items))
        continue
    elif l == "Änderungshistorie:":
        h.append("<h3>%s</h3>" % e(l))
    else:
        h.append(p(l))
    i += 1
inner = "".join(h) + downloads
pages["tas-wohnen-i"] = page(hero("tas", L[0], "%s · %s" % (L[6], L[7])), legal(inner, "ip-fund"))
check("tas-wohnen-i", raw, pages["tas-wohnen-i"])

for slug, content in pages.items():
    open(os.path.join(out, slug + ".txt"), "w", encoding="utf-8").write(content)
