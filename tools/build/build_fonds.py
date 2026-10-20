#!/usr/bin/env python3
"""Fondsseiten und PAI-Statement als Divi-5-Blockinhalt. Der Wortlaut der Textdateien bleibt unverändert."""
import html, json, re, sys

SRC = __import__("os").environ.get("IP_CONTENT_DIR", "2IP Content") + "/"
VER = "5.0.0-public-beta.2"
DL = "/wp-content/uploads/offenlegung/"
HEADS = ["Zusammenfassung", "Kein nachhaltiges Investitionsziel", "Ökologische Merkmale des Finanzprodukts",
         "Ökologische oder soziale Merkmale des Finanzprodukts", "Anlagestrategie", "Aufteilung der Investitionen",
         "Überwachung der ökologischen Merkmale", "Überwachung der ökologischen oder sozialen Merkmale", "Methoden",
         "Datenquellen und ‑verarbeitung", "Beschränkungen hinsichtlich der Methoden und Daten", "Sorgfaltspflicht",
         "Mitwirkungspolitik", "Bestimmter Referenzwert"]

# id: (Datei, Klasse, [(Label-Anfang, PDF-Datei)])
PAGES = {
    14289: ("2IP_German Living Impact Fund.txt", "gerlif", {"Vollständige Fondsbeschreibung": "2026-06-22-German-Living-Impact-VOe_Internetseite-Art.-10-SFDR-_Art_9-c-v2.1-clean.pdf"}),
    14290: ("2IP_GalCap – Austria Immobilien I.txt", "galcap", {"Fondsbeschreibung": "2022-08-31-GalCap-Austria_Internetseite-Art.-10-SFDR_v2.0.pdf"}),
    14291: ("2IP_ICF GLoG.txt", "glog", {"Offenlegung Produktinformationen": "2023-11-20-ICF-GLoC_VOe_Internetseite-Art.-10-SFDR-v1.1.pdf",
                                         "Disclosure of product information": "2023-11-20-ICF-GLoC_VOe_Internetseite-Art.-10-SFDR_EN-v1.1.pdf"}),
    14292: ("2IP_Invesco Real GE 2014.txt", "invesco", {"Offenlegung Produktinformationen": "2023-09-30-IRGE_VOe_Internetseite-Art.-10-SFDR-v1.1.pdf",
                                                        "Disclosure of product information": "2023-09-30-IRGE_VOe_Internetseite-Art.-10-SFDR-english-v1.1.pdf"}),
    14293: ("2IP_W&W Real Estate International 1.txt", "ww", {"Offenlegung Produktinformationen": "2024-06-19-WW-REI-1_VOe_Internetseite-Art.-10-SFDR-v1.0_.pdf",
                                                              "Disclosure of product information": "2024-06-19-ICF-WW-REI1_Internetseite-Art.-10-SFDR_EN-v1.0.pdf"}),
    14287: ("2IP_PAI.txt", "pai", {"2IP Deutschland": "2026-06-22_2IP_DE_Annex_I-PAI-Statement_v2.2_DE-clean-1.pdf",
                                      "2IP Germany": "2026-06-22_2IP_DE_Annex_I-PAI-Statement_v2.2_ENG-clean-1-1.pdf",
                                      "2IP Luxemburg": "2025-12-29_2IP_LU_Annex_I-PAI-Statement_v2.1_DE.pdf",
                                      "2IP Luxembourg": "2025-12-29_2IP_LU_Annex_I-PAI-Statement_v2.1_ENG.pdf"}),
}


def j(obj):
    s = json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
    return s.replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026").replace("--", "\\u002d\\u002d")


def esc(t):
    t = re.sub(r"[ \t]+", " ", t.strip())
    t = html.escape(t, quote=False)
    t = re.sub(r"(https?://[^\s<]+?)([.,;)]*)(?=\s|$|<)", lambda m: '<a href="%s" target="_blank" rel="noopener">%s</a>%s' % (m.group(1), m.group(1), m.group(2)), t)
    return t


def section(css, label, inner):
    zero = {"top": "0px", "bottom": "0px", "left": "0px", "right": "0px", "syncVertical": "on", "syncHorizontal": "on"}
    sec = {"module": {"meta": {"adminLabel": {"desktop": {"value": label}}},
                      "decoration": {"spacing": {"desktop": {"value": {"padding": zero}}}, "layout": {"desktop": {"value": {"display": "block"}}},
                                     "attributes": {"desktop": {"value": {"attributes": [{"id": "ipf-" + label[:5], "name": "class", "value": "ip-divi-sec " + css, "adminLabel": "class", "targetElement": "main"}]}}}}},
           "builderVersion": VER}
    row = {"module": {"advanced": {"columnStructure": {"desktop": {"value": "4_4"}}, "flexColumnStructure": {"desktop": {"value": "equal-columns_1"}}},
                      "decoration": {"sizing": {"desktop": {"value": {"width": "100%", "maxWidth": "none", "alignment": "center"}}}, "spacing": {"desktop": {"value": {"padding": zero}}},
                                     "layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    col = {"module": {"advanced": {"type": {"desktop": {"value": "4_4"}}}, "decoration": {"layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    txt = {"content": {"innerContent": {"desktop": {"value": inner}}}, "builderVersion": VER}
    return ("<!-- wp:divi/section %s -->\n<!-- wp:divi/row %s -->\n<!-- wp:divi/column %s -->\n<!-- wp:divi/text %s /-->\n"
            "<!-- /wp:divi/column -->\n<!-- /wp:divi/row -->\n<!-- /wp:divi/section -->" % (j(sec), j(row), j(col), j(txt)))


def downloads(items, files):
    out = []
    for lab in items:
        fn = next(f for k, f in files.items() if lab.startswith(k))
        out.append('<li><a class="ip-dl" href="%s%s" target="_blank" rel="noopener">%s</a></li>' % (DL, fn, esc(lab)))
    return '<ul class="ip-dls">%s</ul>' % "".join(out)


def clean_lines(raw):
    ls = [l.strip() for l in raw.replace("\r\n", "\n").lstrip("﻿").split("\n")]
    ls = [l for l in ls if l]
    return [l for l in ls if not l.startswith(("Impressum / Datenschutzerkl", "©2025"))]


def build_pai(raw, cls, files):
    ls = clean_lines(raw)
    title = ls[0]
    labels = [l for l in ls if any(l.startswith(k) for k in files)]
    body = [l for l in ls[1:] if l not in labels]
    hero = '<div class="ip-wrap ip-hero__inner"><h1>%s</h1></div>' % esc(title)
    inner = '<div class="ip-wrap ip-legal__inner">%s%s</div>' % ("".join('<p>%s</p>' % esc(p) for i, p in enumerate(body)), downloads(labels, files))
    return [section("ip-hero ip-fund-hero ip-fund-hero--%s" % cls, "Hero", hero), section("ip-sec ip-sec--white ip-legal ip-fund", "Inhalt", inner)]


def build_fund(raw, cls, files):
    ls = clean_lines(raw)
    title = ls[0]
    labels = [l for l in ls if l.endswith("(PDF)")]
    ls = [l for l in ls if l not in labels]
    numbered = any(re.match(r"^1\. Zusammenfassung$", l) for l in ls)
    # Kopf bis zur ersten Überschrift
    k = next(i for i, l in enumerate(ls) if i > 0 and (l in HEADS or re.match(r"^\d{1,2}\. [A-ZÄÖÜ]", l)))
    head, rest = ls[1:k], ls[k:]
    meta = [l for l in head if "ISIN" in l or l.startswith(("Stand", "(Stand"))]
    intro = [l for l in head if l not in meta]
    toc = []
    if numbered:
        k2 = next(i for i, l in enumerate(rest) if l == "1. Zusammenfassung")
        toc, rest = rest[:k2], rest[k2:]
    hero = '<div class="ip-wrap ip-hero__inner"><h1>%s</h1><p class="ip-lead">%s</p></div>' % (esc(title), " · ".join(esc(m) for m in meta))
    out = []
    for l in intro:
        cls_ = ' class="ip-strong"' if l.startswith(("Offenlegung", "“", '"')) else ""
        out.append("<p%s>%s</p>" % (cls_, esc(l)))
    if toc:
        out.append('<nav class="ip-toc" aria-label="Inhaltsverzeichnis"><ul>%s</ul></nav>' % "".join(
            '<li><a href="#abschnitt-%d">%d. %s</a></li>' % (i + 1, i + 1, esc(t)) for i, t in enumerate(toc)))
    letter = 0
    i = 0
    bullets = []
    def flush_b():
        nonlocal bullets
        if bullets:
            out.append('<ul class="ip-bullets">%s</ul>' % "".join("<li>%s</li>" % esc(b) for b in bullets)); bullets = []
    while i < len(rest):
        l = rest[i]
        m = re.match(r"^(\d{1,2})\. ([A-ZÄÖÜ].*)$", l) if numbered else None
        if l in HEADS and not numbered:
            flush_b(); out.append('<h2>%s. %s</h2>' % (chr(97 + letter), esc(l))); letter += 1
        elif m and len(l) < 80:
            flush_b(); out.append('<h2 id="abschnitt-%s">%s</h2>' % (m.group(1), esc(l)))
        elif l.startswith("Änderungshistorie"):
            flush_b(); out.append("<h3>%s</h3>" % esc(l))
        elif re.match(r"^o\s{2,}", l):
            bullets.append(re.sub(r"^o\s+", "", l))
        else:
            flush_b(); out.append("<p>%s</p>" % esc(l))
        i += 1
    flush_b()
    if labels:
        out.append(downloads(labels, files))
    inner = '<div class="ip-wrap ip-legal__inner">%s</div>' % "\n".join(out)
    return [section("ip-hero ip-fund-hero ip-fund-hero--%s" % cls, "Hero", hero), section("ip-sec ip-sec--white ip-legal ip-fund", "Inhalt", inner)]


outdir = sys.argv[1]
for pid, (fn, cls, files) in PAGES.items():
    raw = open(SRC + fn, encoding="utf-8-sig").read()
    secs = (build_pai if pid == 14287 else build_fund)(raw, cls, files)
    content = "<!-- wp:divi/placeholder -->" + "\n".join(secs) + "<!-- /wp:divi/placeholder -->"
    open("%s/fonds_%d.txt" % (outdir, pid), "w", encoding="utf-8").write(content)
    plain = html.unescape(re.sub(r"<[^>]+>", " ", content.replace("\\u003c", "<").replace("\\u003e", ">").replace("\\u0026", "&")))
    norm = lambda s: re.sub(r"\s+", "", s)
    skip = ("©2025", "Impressum", "/", "Datenschutzerklärung", "All", "rights", "reserved", "by", "Institutional", "Investment", "Partners", "GmbH", "und", "S.à", "r.l")
    miss = [w for w in re.split(r"\s+", raw) if w and w not in skip and norm(w) not in norm(plain)]
    print(pid, fn, "| Zeichen:", len(content), "| fehlend:", miss[:10])
