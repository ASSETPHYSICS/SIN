#!/usr/bin/env bash
# Header-Video: Zeitlupe mit berechneten Zwischenbildern, nahtlose Vorwaerts-/Rueckwaerts-Schleife, Vorschaubild.
# Aufruf (WSL, ffmpeg noetig):  SRC=<quellvideo.mp4> bash tools/build/build_hero_video.sh
set -euo pipefail
SRC=${SRC:?SRC fehlt (Quellvideo)}
THEME="$(cd "$(dirname "$0")/../.." && pwd)/wp-content/themes/Divi-SIN"
WORK=$(mktemp -d)
SLOW=${SLOW:-2.857}   # Faktor: 1/0,35 -> gut ein Drittel der Originalgeschwindigkeit
F=${F:-2}            # Überblendung am Schleifenende in Sekunden

# 1) Zeitlupe mit berechneten Zwischenbildern (30 fps), ohne Ton, verlustarm als Zwischenstufe
ffmpeg -v error -y -i "$SRC" -an -vf "setpts=${SLOW}*PTS,minterpolate=fps=30:mi_mode=mci:mc_mode=aobmc:me_mode=bidir:vsbmc=1" \
  -c:v libx264 -crf 12 -preset medium -pix_fmt yuv420p "$WORK/slow.mp4"
L=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$WORK/slow.mp4")
echo "Zeitlupe: ${L}s"

# 2) Nahtlose Schleife als „Bumerang“: vorwärts, dann rückwärts (Kamerafahrt kehrt um, kein Sprung).
#    Das Umkehrbild wird nicht doppelt gezeigt; das letzte Bild schließt an das erste an.
N=$(ffprobe -v error -count_frames -select_streams v -show_entries stream=nb_read_frames -of csv=p=0 "$WORK/slow.mp4")
# vorwärts 0..N-1, rückwärts N-2..1, danach beginnt die Schleife wieder bei 0
ffmpeg -v error -y -i "$WORK/slow.mp4" -filter_complex \
  "[0:v]split[f][r];[r]reverse,trim=start_frame=1:end_frame=$((N-1)),setpts=PTS-STARTPTS[rv];[f]setpts=PTS-STARTPTS[fw];[fw][rv]concat=n=2:v=1:a=0,format=yuv420p[v]" \
  -map "[v]" -an -c:v libx264 -crf 26 -preset slow -profile:v high -movflags +faststart -r 30 "$THEME/video/hero-loop.mp4"

# 3) Vorschaubild = erstes Bild der Schleife (kein Sprung zwischen Vorschaubild und Video)
ffmpeg -v error -y -i "$THEME/video/hero-loop.mp4" -frames:v 1 -q:v 3 "$THEME/images/hero/hero-poster.jpg"

ffprobe -v error -show_entries "stream=width,height,r_frame_rate,nb_frames:format=duration,size" -of compact "$THEME/video/hero-loop.mp4"
ls -la "$THEME/images/hero/hero-poster.jpg"
rm -rf "$WORK"
