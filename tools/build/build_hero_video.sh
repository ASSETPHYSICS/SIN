#!/usr/bin/env bash
# Header-Video: ganzes Quellvideo in Zeitlupe, am Ende Standbild, dann weiche Blende zum Anfang (nahtlose Schleife).
# Aufruf (WSL, ffmpeg noetig):  SRC=<quellvideo.mp4> bash tools/build/build_hero_video.sh
#
# Quelle: Gemini-Video gemini_generated_video_9dd5846f.mp4 (10 s, 24 fps, 1280x720).
# Zwischenbilder per Überblendung benachbarter Bilder (mi_mode=blend) statt Bewegungsschätzung: die Bewegungsschätzung
# verzerrt bei Kopfdrehungen Ohr und Haar; die Überblendung wirkt wie leichte Bewegungsunschärfe einer echten Zeitlupe.
set -euo pipefail
SRC=${SRC:?SRC fehlt (Quellvideo)}
THEME="$(cd "$(dirname "$0")/../.." && pwd)/wp-content/themes/Divi-SIN"
WORK=$(mktemp -d)
SLOW=${SLOW:-3}      # Verlangsamung: 3 = ein Drittel der Originalgeschwindigkeit
HOLD=${HOLD:-2.5}    # Standbild am Ende (s)
FADE=${FADE:-1.2}    # Blende vom Standbild zum ersten Bild (s)

# 1) Zeitlupe
ffmpeg -v error -y -i "$SRC" -an -vf "setpts=${SLOW}*PTS,minterpolate=fps=30:mi_mode=blend,format=yuv420p" \
  -c:v libx264 -crf 12 -preset medium "$WORK/slow.mp4"
D=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$WORK/slow.mp4")
echo "Zeitlupe: $D s"

# 2) Letztes Bild stehen lassen (HOLD + FADE), dann in das erste Bild überblenden. Das Video endet auf dem ersten Bild,
#    die Schleife beginnt mit demselben Bild – kein Sprung.
ffmpeg -v error -y -i "$WORK/slow.mp4" -filter_complex \
  "[0:v]split[a][b];[a]tpad=stop_mode=clone:stop_duration=$(echo "$HOLD + $FADE" | bc)[held];[b]trim=end_frame=1,setpts=PTS-STARTPTS,tpad=stop_mode=clone:stop_duration=${FADE}[first];[held][first]xfade=transition=fade:duration=${FADE}:offset=$(echo "$D + $HOLD" | bc),format=yuv420p[v]" \
  -map "[v]" -an -c:v libx264 -crf 24 -preset slow -profile:v high -movflags +faststart -r 30 "$THEME/video/hero-loop.mp4"

# 3) Vorschaubild = erstes Bild (kein Sprung zwischen Vorschaubild und Video)
ffmpeg -v error -y -i "$THEME/video/hero-loop.mp4" -frames:v 1 -q:v 3 "$THEME/images/hero/hero-poster.jpg"

ffprobe -v error -show_entries "stream=width,height,r_frame_rate,nb_frames:format=duration,size" -of compact "$THEME/video/hero-loop.mp4"
rm -rf "$WORK"
