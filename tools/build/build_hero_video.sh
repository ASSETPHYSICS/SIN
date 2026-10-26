#!/usr/bin/env bash
# Header-Video: Zeitlupe mit berechneten Zwischenbildern, nahtlose Vorwaerts-/Rueckwaerts-Schleife, Vorschaubild.
# Aufruf (WSL, ffmpeg noetig):  SRC=<quellvideo.mp4> bash tools/build/build_hero_video.sh
#
# FIRST/LAST: verwendeter Bildbereich des Quellvideos. Voreinstellung = Kamerafahrt hinter der Analystin zu den
# Dashboards, 3,9–7,2 s des Gemini-Videos (gemini_generated_video_9dd5846f.mp4, 24 fps); dort springt die Kamera
# bzw. blendet über bei 1,5 / 2,5 / 3,5 / 7,3 s.
# Ruckler im Quellvideo finden: Bildänderung je Bild (scene_score) – Ausreißer über ca. 0,03 sind Sprünge.
set -euo pipefail
SRC=${SRC:?SRC fehlt (Quellvideo)}
THEME="$(cd "$(dirname "$0")/../.." && pwd)/wp-content/themes/Divi-SIN"
WORK=$(mktemp -d)
FIRST=${FIRST:-94}   # erstes verwendetes Bild
LAST=${LAST:-172}    # letztes verwendetes Bild
SLOW=${SLOW:-4}      # Verlangsamung: 4 = ein Viertel der Originalgeschwindigkeit

# 1) Abschnitt wählen und verlangsamen; Zwischenbilder per Bewegungsschätzung (überlappende Blöcke,
#    bilaterale Suche, keine Szenenerkennung) – deutlich weniger Verzerrungen an Gesicht/Brille als die Voreinstellung.
#    tmix glättet jedes Bild leicht mit seinen Nachbarn: Originalbilder sind schärfer als die berechneten,
#    ohne Glättung „pulsiert“ das Video im Takt der Originalbilder.
ffmpeg -v error -y -i "$SRC" -an -vf \
  "trim=start_frame=${FIRST}:end_frame=$((LAST + 1)),setpts=PTS-STARTPTS,setpts=${SLOW}*PTS,minterpolate=fps=30:mi_mode=mci:mc_mode=obmc:me_mode=bilat:me=epzs:mb_size=16:search_param=32:vsbmc=1:scd=none,tmix=frames=5:weights='1 2 3 2 1'" \
  -c:v libx264 -crf 12 -preset medium -pix_fmt yuv420p "$WORK/slow.mp4"
echo "Zeitlupe: $(ffprobe -v error -show_entries format=duration -of csv=p=0 "$WORK/slow.mp4") s"

# 2) Nahtlose Schleife als „Bumerang“: vorwärts 0..N-1, rückwärts N-2..1, danach wieder 0 (kein Sprung)
N=$(ffprobe -v error -count_frames -select_streams v -show_entries stream=nb_read_frames -of csv=p=0 "$WORK/slow.mp4")
ffmpeg -v error -y -i "$WORK/slow.mp4" -filter_complex \
  "[0:v]split[f][r];[r]reverse,trim=start_frame=1:end_frame=$((N-1)),setpts=PTS-STARTPTS[rv];[f]setpts=PTS-STARTPTS[fw];[fw][rv]concat=n=2:v=1:a=0,format=yuv420p[v]" \
  -map "[v]" -an -c:v libx264 -crf 24 -preset slow -profile:v high -movflags +faststart -r 30 "$THEME/video/hero-loop.mp4"

# 3) Vorschaubild = erstes Bild der Schleife (kein Sprung zwischen Vorschaubild und Video)
ffmpeg -v error -y -i "$THEME/video/hero-loop.mp4" -frames:v 1 -q:v 3 "$THEME/images/hero/hero-poster.jpg"

ffprobe -v error -show_entries "stream=width,height,r_frame_rate,nb_frames:format=duration,size" -of compact "$THEME/video/hero-loop.mp4"
rm -rf "$WORK"
