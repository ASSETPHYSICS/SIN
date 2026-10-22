#!/usr/bin/env python3
"""Wandelt die Rechtstexte (.txt) in Divi-5-Blockinhalt. Der Wortlaut bleibt unverändert."""
import html, json, re, sys

SRC = __import__("os").environ.get("IP_CONTENT_DIR", "SIN Content") + "/"
VER = "5.0.0-public-beta.2"
PAGES = {
    14195: ("Impressum", "SI-Impressum.txt", "impressum"),
    14200: ("Datenschutzerklärung", "SI-Datenschutzerklärung.txt", "web"),
    14296: ("Datenschutzhinweise für Mieter", "SI-Datenschutzhinweise für Mieter.txt", "mieter"),
}
KV = ("Zweck der Verarbeitung", "Rechtsgrundlage", "Datenkategorien", "Empfänger der Daten",
      "Beabsichtigte Drittlandübermittlung", "Betroffenenrechte", "Domainname", "Ablauf", "Dienst")
ROMAN = re.compile(r"^(I|II|III|IV|V|VI|VII|VIII)\.\s+\S")


def j(obj):
    s = json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
    return s.replace("<", "\\u003c").replace(">", "\\u003e").replace("&", "\\u0026").replace("--", "\\u002d\\u002d")


def esc(t):
    t = re.sub(r"[ \t]+", " ", t.strip())
    t = html.escape(t, quote=False)
    t = re.sub(r"(https?://[^\s<]+?)([.,;)]*)(?=\s|$|<)", lambda m: '<a href="%s" target="_blank" rel="noopener">%s</a>%s' % (m.group(1), m.group(1), m.group(2)), t)
    t = re.sub(r"\b([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})\b", r'<a href="mailto:\1">\1</a>', t)
    return t


def slug(t):
    t = t.lower()
    for a, b in (("ä", "ae"), ("ö", "oe"), ("ü", "ue"), ("ß", "ss")):
        t = t.replace(a, b)
    return re.sub(r"[^a-z0-9]+", "-", t).strip("-")


def is_item(l):
    return len(l) < 190 and l[-1:] in ";."


def render(kind, title, text):
    text = text.replace("\r\n", "\n").lstrip("﻿")
    blocks = [[l.rstrip() for l in b.split("\n") if l.strip()] for b in re.split(r"\n[ \t]*\n", text)]
    blocks = [b for b in blocks if b]
    # Fußzeilen-Boilerplate der Altseite entfernen
    blocks = [b for b in blocks if not b[0].strip().startswith(("Impressum / Datenschutzerkl", "©2025"))]
    out = []
    if kind == "impressum" and blocks and blocks[0][0].strip() == "Impressum":
        blocks[0] = blocks[0][1:]
    out.append("<h1>%s</h1>" % esc(title))
    h2_ids = {}
    first = True
    for bi, b in enumerate(blocks):
        if not b:
            continue
        lines = [l.strip() for l in b]
        # Inhaltsverzeichnis (Website-Erklärung)
        if kind == "web" and any(re.match(r"^\s{3,}\d+\.", l) for l in b):   # eingerückte Zeilen = Inhaltsverzeichnis
            items = []
            if not b[0][:1].isspace() and not ROMAN.match(b[0].strip()):
                out.append('<p class="ip-lead">%s</p>' % esc(b[0]))
                first = False
            for l in b:
                s = l.strip()
                if ROMAN.match(s):
                    items.append([s, []])
                elif items:
                    items[-1][1].append(s)
            h = '<nav class="ip-toc" aria-label="Inhaltsverzeichnis"><ul>'
            for s, subs in items:
                name = re.sub(r"^[IVX]+\.\s+", "", s)
                h += '<li><a href="#%s">%s</a>' % (slug(name), esc(s))
                if subs:
                    h += "<ul>" + "".join("<li>%s</li>" % esc(x) for x in subs) + "</ul>"
                h += "</li>"
            out.append(h + "</ul></nav>")
            continue
        # Fortsetzung eines Eintrags (Kleinbuchstabe am Anfang)
        if out and lines[0][:1].islower() and out[-1].startswith('<p class="ip-kv">'):
            out[-1] = out[-1][:-4] + " " + esc(lines[0]) + "</p>"
            lines = lines[1:]
            if not lines:
                continue
        # Block aus lauter kurzen Aufzählungspunkten
        if len(lines) >= 2 and all(is_item(l) for l in lines) and kind == "mieter":
            out.append('<ul class="ip-bullets">%s</ul>' % "".join("<li>%s</li>" % esc(l) for l in lines))
            continue
        i = 0
        group = []

        def flush():
            nonlocal group
            if group:
                out.append("<p>%s</p>" % "<br>".join(esc(x) for x in group))
                group = []

        while i < len(lines):
            l = lines[i]
            single = len(lines) == 1
            if first and kind in ("web", "mieter") and i == 0:
                flush(); out.append('<p class="ip-lead">%s</p>' % esc(l)); first = False; i += 1; continue
            first = False
            if kind == "web" and ROMAN.match(l):
                flush(); name = re.sub(r"^[IVX]+\.\s+", "", l)
                out.append('<h2 id="%s">%s</h2>' % (slug(name), esc(l))); i += 1; continue
            if kind == "mieter" and re.match(r"^\d+\.\s+\S", l):
                flush(); out.append("<h2>%s</h2>" % esc(l)); i += 1; continue
            if l.startswith("Disclaimer / "):
                flush(); out.append("<h2>%s</h2>" % esc(l)); i += 1; continue
            if re.match(r"^§ \d", l):
                flush(); out.append("<h3>%s</h3>" % esc(l)); i += 1; continue
            if l in ("Bildrechte:", "Bildnachweis:", "Informationen zur Vergütungspolitik") or l.startswith("Beschwerdestelle für Fondsanleger der "):
                flush(); out.append("<h3>%s</h3>" % esc(l)); i += 1; continue
            if re.search(r"\(Art\. [^)]*\)[^)]*\):$", l):
                flush(); out.append("<h3>%s</h3>" % esc(l)); i += 1; continue
            if kind == "web":
                if any(l.startswith(k + ":") for k in KV) or l.startswith("Speichern wir auf Ihrem Endger"):
                    flush()
                    if l.startswith("Speichern wir"):
                        q, _, rest = l.partition("?")
                        out.append('<p class="ip-kv"><strong>%s?</strong>%s</p>' % (esc(q), (" " + esc(rest)) if rest.strip() else ""))
                    else:
                        k, _, rest = l.partition(":")
                        out.append('<p class="ip-kv"><strong>%s:</strong> %s</p>' % (esc(k), esc(rest)))
                    i += 1; continue
                def cand(k):
                    return (k < len(lines) and len(lines[k]) < 70 and lines[k][-1] not in ".:;?!," and "_" not in lines[k]
                            and ":" not in lines[k] and "@" not in lines[k] and not lines[k].startswith(("Stand", "Dienst")) and not lines[k][0].isdigit())
                nxt_long = i + 1 < len(lines) and len(lines[i + 1]) >= 60
                nxt_cand_then_long = cand(i + 1) and i + 2 < len(lines) and len(lines[i + 2]) >= 60
                if cand(i) and (single or nxt_long or nxt_cand_then_long):
                    flush()
                    prev_h3 = bool(out) and out[-1].startswith("<h3>")
                    out.append("<%s>%s</%s>" % ("h4" if prev_h3 else "h3", esc(l), "h4" if prev_h3 else "h3")); i += 1; continue
                if single and ("_" in l or l.endswith("*")) and len(l) < 40:
                    out.append('<p class="ip-kv"><strong>%s</strong></p>' % esc(l)); i += 1; continue
            if kind == "mieter" and l.endswith(":") and i + 1 < len(lines) and is_item(lines[i + 1]) and len(lines[i + 1]) < 190:
                flush(); out.append("<p>%s</p>" % esc(l)); i += 1
                items = []
                while i < len(lines) and is_item(lines[i]):
                    items.append(lines[i]); i += 1
                out.append('<ul class="ip-bullets">%s</ul>' % "".join("<li>%s</li>" % esc(x) for x in items))
                continue
            if len(l) >= 150:
                flush(); out.append("<p>%s</p>" % esc(l)); i += 1; continue
            if l.startswith("Quelle:") or l.startswith("Stand:"):
                flush(); out.append('<p class="ip-note-line">%s</p>' % esc(l)); i += 1; continue
            group.append(l); i += 1
        flush()
    return "\n".join(out)


def divi(inner):
    zero = {"top": "0px", "bottom": "0px", "left": "0px", "right": "0px", "syncVertical": "on", "syncHorizontal": "on"}
    sec = {"module": {"meta": {"adminLabel": {"desktop": {"value": "Rechtstext"}}},
                      "decoration": {"spacing": {"desktop": {"value": {"padding": zero}}}, "layout": {"desktop": {"value": {"display": "block"}}},
                                     "attributes": {"desktop": {"value": {"attributes": [{"id": "ipl-1", "name": "class", "value": "ip-divi-sec ip-sec ip-sec--white ip-legal", "adminLabel": "class", "targetElement": "main"}]}}}}},
           "builderVersion": VER}
    row = {"module": {"advanced": {"columnStructure": {"desktop": {"value": "4_4"}}, "flexColumnStructure": {"desktop": {"value": "equal-columns_1"}}},
                      "decoration": {"sizing": {"desktop": {"value": {"width": "100%", "maxWidth": "none", "alignment": "center"}}}, "spacing": {"desktop": {"value": {"padding": zero}}},
                                     "layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    col = {"module": {"advanced": {"type": {"desktop": {"value": "4_4"}}}, "decoration": {"layout": {"desktop": {"value": {"display": "block"}}}}}, "builderVersion": VER}
    txt = {"content": {"innerContent": {"desktop": {"value": '<div class="ip-wrap ip-legal__inner">%s</div>' % inner}}}, "builderVersion": VER}
    return ("<!-- wp:divi/placeholder --><!-- wp:divi/section %s -->\n<!-- wp:divi/row %s -->\n<!-- wp:divi/column %s -->\n<!-- wp:divi/text %s /-->\n"
            "<!-- /wp:divi/column -->\n<!-- /wp:divi/row -->\n<!-- /wp:divi/section --><!-- /wp:divi/placeholder -->" % (j(sec), j(row), j(col), j(txt)))


outdir = sys.argv[1]
for pid, (title, fn, kind) in PAGES.items():
    raw = open(SRC + fn, encoding="utf-8-sig").read().replace("\r\n", "\n")
    src_raw = raw
    if pid == 14200:  # Google Fonts und Google Maps entfernen (die neue Seite nutzt beides nicht)
        raw, n1 = re.subn(r"Google Fonts\nZweck der Verarbeitung.*?(?=Videointerviews/-statements und Podcasts)", "", raw, flags=re.S)
        assert n1 == 1, n1
        assert not re.search(r"Google Fonts|Google Maps|Vimeo|vimeo", raw), re.findall(r".{40}(?:Google|imeo).{40}", raw)
    body = render(kind, title, raw)
    if pid == 14195:  # Verweise auf Beschwerdestelle und Vergütungspolitik verlinken
        body, a = re.subn(r"über diesen Link", 'über <a href="/beschwerdestelle/">diesen Link</a>', body)
        body, b = re.subn(r"unter diesem Link", 'unter <a href="/verguetungspolitik/">diesem Link</a>', body)
        assert a == 1 and b == 1, (a, b)
    open("%s/legal_%d.txt" % (outdir, pid), "w", encoding="utf-8").write(divi(body))
    # Textvergleich: sichtbarer Text muss dem Original entsprechen (ohne Boilerplate)
    plain = re.sub(r"<[^>]+>", " ", body)
    plain = html.unescape(plain)
    norm = lambda s: re.sub(r"\s+", "", s)
    miss = [w for w in re.split(r"\s+", raw) if w and norm(w) not in norm(plain) and not w.startswith(("©2025",))]
    print(pid, title, "| Zeichen:", len(body), "| im Original, aber nicht im Ergebnis:", [m for m in miss if m not in ("Impressum", "/", "Datenschutzerklärung", "All", "rights", "reserved", "by", "ServiceInvest", "Kapitalverwaltungsgesellschaft", "mbH")][:12])
