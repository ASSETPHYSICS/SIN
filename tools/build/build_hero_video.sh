#!/usr/bin/env bash
# Header-Video aus RIFE-interpolierten Teilen (120 fps): zusammensetzen, Stillstände entfernen, 4-fache Zeitlupe (30 fps),
# am Ende Standbild, dann weiche Blende zum Anfang (nahtlose Schleife).
# Aufruf (WSL, ffmpeg und python3 nötig):
#   bash tools/build/build_hero_video.sh <teil1.mp4> [<teil2.mp4> ...]
#
# Quelle: Gemini-Video gemini_generated_video_9dd5846f.mp4 (10 s, 24 fps), mit dem Tool „Upsampler“ (RIFE) auf 120 fps
# interpoliert (SIN_Header_1.mp4, SIN_Header_2.mp4; 1080x608). Das Gemini-Video steht etwa alle vier Bilder kurz still;
# nach der Interpolation sind das je ~4 stehende Bilder, die als Ruckeln sichtbar werden. tools/build/hero_dedup.py
# entfernt sie (nur Originalbilder, nichts wird neu berechnet). Ruhige Szenen bleiben unverändert.
set -euo pipefail
[ $# -ge 1 ] || { echo "Aufruf: $0 <teil1.mp4> [<teil2.mp4> ...]"; exit 1; }
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
THEME="$ROOT/wp-content/themes/Divi-SIN"
WORK=$(mktemp -d -p "$HOME")
HOLD=${HOLD:-2.5}    # Standbild am Ende (s)
FADE=${FADE:-1.2}    # Blende vom Standbild zum ersten Bild (s)
FPS=${FPS:-30}       # Wiedergabe: 120 fps-Material mit 30 fps = 4-fache Zeitlupe

# 1) Teile zusammensetzen, Einzelbilder (Datei 00001.png = Bild 0)
inputs=(); for f in "$@"; do inputs+=(-i "$f"); done
ffmpeg -v error "${inputs[@]}" -filter_complex "concat=n=$#:v=1:a=0[v]" -map "[v]" -an -c:v libx264 -crf 10 -preset fast "$WORK/all.mp4"
mkdir -p "$WORK/f"
ffmpeg -v error -i "$WORK/all.mp4" "$WORK/f/%05d.png"

# 2) Bewegung je Bild (mittlere Differenz zum Vorgänger) und Stillstände entfernen
ffmpeg -v error -i "$WORK/all.mp4" -vf "tblend=all_mode=difference,signalstats,metadata=print:key=lavfi.signalstats.YAVG:file=-" \
  -an -f null - 2>/dev/null | awk '/YAVG/{split($0,b,"="); printf "%d %.3f\n", ++n, b[2]}' > "$WORK/m-all.txt"
python3 -I "$ROOT/tools/build/hero_dedup.py" "$WORK"
ffmpeg -v error -y -framerate "$FPS" -i "$WORK/d/%05d.png" -c:v libx264 -crf 12 -preset medium -pix_fmt yuv420p "$WORK/slow.mp4"
D=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$WORK/slow.mp4")
echo "Zeitlupe: $D s"

# 3) Letztes Bild stehen lassen (HOLD + FADE), dann in das erste Bild überblenden. Das Video endet auf dem ersten Bild,
#    die Schleife beginnt mit demselben Bild – kein Sprung.
ffmpeg -v error -y -i "$WORK/slow.mp4" -filter_complex \
  "[0:v]split[a][b];[a]tpad=stop_mode=clone:stop_duration=$(echo "$HOLD + $FADE" | bc)[held];[b]trim=end_frame=1,setpts=PTS-STARTPTS,tpad=stop_mode=clone:stop_duration=${FADE}[first];[held][first]xfade=transition=fade:duration=${FADE}:offset=$(echo "$D + $HOLD" | bc),format=yuv420p[v]" \
  -map "[v]" -an -c:v libx264 -crf 24 -preset slow -profile:v high -movflags +faststart -r "$FPS" "$THEME/video/hero-loop.mp4"

# 4) Vorschaubild = erstes Bild (kein Sprung zwischen Vorschaubild und Video)
ffmpeg -v error -y -i "$THEME/video/hero-loop.mp4" -frames:v 1 -q:v 3 "$THEME/images/hero/hero-poster.jpg"

ffprobe -v error -show_entries "stream=width,height,r_frame_rate,nb_frames:format=duration,size" -of compact "$THEME/video/hero-loop.mp4"
rm -rf "$WORK"
