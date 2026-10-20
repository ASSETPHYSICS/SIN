#!/usr/bin/env python3
"""Statische Prüfung von PDFs/Bildern auf aktive Inhalte und angehängte Daten. Führt nichts aus."""
import os, re, sys, zlib

d = sys.argv[1]
KEYS = [b"/JavaScript", b"/JS", b"/Launch", b"/OpenAction", b"/AA", b"/EmbeddedFile", b"/EmbeddedFiles", b"/URI",
        b"/SubmitForm", b"/ImportData", b"/RichMedia", b"/XFA", b"/GoToR", b"/GoToE", b"/Movie", b"/Sound", b"/Encrypt",
        b"/ObjStm", b"/AcroForm", b"/FileAttachment"]
BAD = re.compile(rb"<\?php|<script|eval\(|base64_decode|shell_exec|system\(|passthru|exec\(|powershell|cmd\.exe|/bin/sh|WScript|ActiveX|app\.launchURL|this\.exportDataObject|getURL|submitForm|javascript:", re.I)


def streams(data):
    out = []
    for m in re.finditer(rb"stream\r?\n(.*?)\r?\nendstream", data, re.S):
        s = m.group(1)
        try:
            out.append(zlib.decompress(s))
        except Exception:
            out.append(s)
    return out


bad_total = 0
for fn in sorted(os.listdir(d)):
    p = os.path.join(d, fn)
    data = open(p, "rb").read()
    res = []
    if fn.lower().endswith(".pdf"):
        if not data.startswith(b"%PDF-"):
            res.append("KEIN PDF-HEADER")
        eof = data.rfind(b"%%EOF")
        tail = len(data) - (eof + 5)
        if eof < 0:
            res.append("kein %%EOF")
        elif tail > 8:
            res.append("DATEN NACH %%EOF: %d Bytes" % tail)
        blobs = [data] + streams(data)
        # Objektströme (ObjStm) enthalten Schlüssel komprimiert – bereits durch Dekompression abgedeckt
        for k in KEYS:
            c = sum(len(re.findall(re.escape(k) + rb"(?![A-Za-z])", b)) for b in blobs)
            if c:
                res.append("%s x%d" % (k.decode(), c))
        hits = set()
        for b in blobs:
            for m in BAD.finditer(b):
                hits.add(m.group(0).decode("latin1")[:30])
        if hits:
            res.append("VERDÄCHTIGE ZEICHENKETTEN: %s" % sorted(hits))
        uris = set()
        for b in blobs:
            uris |= set(x.decode("latin1")[:120] for x in re.findall(rb"/URI\s*\(([^)]{0,200})\)", b))
        if uris:
            res.append("URIs: %s" % sorted(uris))
        # eingebettete Nicht-PDF-Header in Streams (z.B. MZ, ELF, PK, #!)
        emb = []
        for b in streams(data):
            if b[:2] == b"MZ" or b[:4] == b"\x7fELF" or b[:2] == b"PK" or b[:2] == b"#!":
                emb.append(b[:4])
        if emb:
            res.append("EINGEBETTETE BINÄRSTRUKTUR: %s" % emb)
    else:
        # Bilder
        if fn.lower().endswith((".jpg", ".jpeg")):
            i = data.rfind(b"\xff\xd9")
            if data[:2] != b"\xff\xd8":
                res.append("KEIN JPEG-HEADER")
            if len(data) - (i + 2) > 16:
                res.append("DATEN NACH JPEG-ENDE: %d" % (len(data) - (i + 2)))
        if fn.lower().endswith(".png"):
            i = data.rfind(b"IEND")
            if data[:8] != b"\x89PNG\r\n\x1a\n":
                res.append("KEIN PNG-HEADER")
            if len(data) - (i + 8) > 0:
                res.append("DATEN NACH IEND: %d" % (len(data) - (i + 8)))
        m = BAD.search(data)
        if m:
            res.append("VERDÄCHTIG: %r" % m.group(0))
    bad = [r for r in res if r.split(" ")[0] in ("KEIN", "DATEN", "VERDÄCHTIGE", "EINGEBETTETE", "VERDÄCHTIG:", "kein") or r.startswith(("/JavaScript", "/JS", "/Launch", "/OpenAction", "/AA", "/Embedded", "/SubmitForm", "/RichMedia", "/XFA", "/GoTo", "/ImportData", "/Movie", "/Sound", "/FileAttachment"))]
    bad_total += len(bad)
    print(("!! " if bad else "ok ") + fn + " (%d KB)" % (len(data) // 1024))
    for r in res:
        print("     ", r)
print("\nAUFFÄLLIGE BEFUNDE GESAMT:", bad_total)
