"""Entfernt stehende Bilder (Stillstand mitten in einer Bewegung) und schreibt die verbleibenden Originalbilder neu durchnummeriert.
Aufruf (WSL): python3 -I tools/build/hero_dedup.py <arbeitsordner mit f/ und m-all.txt>  (aus build_hero_video.sh)
m-all.txt: Zeile "n wert" = mittlere Differenz zwischen Bild n-1 und n (n ab 1).
"""
import os, shutil, statistics, sys

W = sys.argv[1]
SRC, OUT = os.path.join(W, "f"), os.path.join(W, "d")
mad = {0: 99.0}
for line in open(os.path.join(W, "m-all.txt")):
    n, v = line.split()
    mad[int(n)] = float(v)
N = len(mad)

drop = set()
for i in range(1, N):
    around = [mad[k] for k in range(max(1, i - 12), min(N, i + 13)) if k != i]
    med = statistics.median(around)
    if med > 1.0 and mad[i] < 0.3 * med:     # steht still, während sich die Umgebung bewegt
        drop.add(i)

shutil.rmtree(OUT, ignore_errors=True)
os.makedirs(OUT)
o = 0
for i in range(N):
    if i in drop:
        continue
    o += 1
    os.link(os.path.join(SRC, "%05d.png" % (i + 1)), os.path.join(OUT, "%05d.png" % o))
print("Bilder: %d -> %d (entfernt %d)" % (N, o, len(drop)))
runs, prev = [], None
for i in sorted(drop):
    if prev is not None and i == prev + 1:
        runs[-1][1] = i
    else:
        runs.append([i, i])
    prev = i
print(" ".join("%d-%d" % (a, b) if a != b else str(a) for a, b in runs))
