// Bildvarianten für die Website (HA 9, 10): je Bild aus assets/img/ AVIF, WebP und JPEG in 640, 1024, 1600 und 2400 px
// Breite (nicht breiter als das Original), dazu das Vorschaubild für Open Graph (1.200 × 630 px aus Hero-Bild 1 mit weißem
// Logo) und Favicon/Touch-Icon aus der Bildmarke. Schreibt nach <Ziel>/assets/img/v/, <Ziel>/assets/og/ und <Ziel>/.
// Vorhandene, neuere Dateien bleiben (mehrfach ausführbar).
//
//   NODE_PATH=~/sin-imgtools/node_modules node tools/v1/images.mjs content/v1-vorlage content/v1-build
//
// Qualität: AVIF 52, WebP 78, JPEG 80 (mozjpeg) – bei den Fotos ohne sichtbare Artefakte.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
const sharp = require('sharp');

const [src, dst] = process.argv.slice(2);
if (!src || !dst) {
	console.error('Aufruf: node tools/v1/images.mjs <Paketordner> <Zielordner>');
	process.exit(1);
}
const WIDTHS = [640, 1024, 1600, 2400];
const imgDir = path.join(src, 'assets/img');
const outDir = path.join(dst, 'assets/img/v');
fs.mkdirSync(outDir, { recursive: true });

const fresh = (out, input) => fs.existsSync(out) && fs.statSync(out).mtimeMs >= fs.statSync(input).mtimeMs;
let made = 0;

for (const file of fs.readdirSync(imgDir).filter((f) => f.endsWith('.jpg')).sort()) {
	const input = path.join(imgDir, file);
	const base = file.replace(/\.jpg$/, '');
	const { width } = await sharp(input).metadata();
	const widths = WIDTHS.filter((w) => w < width);
	if (!widths.includes(Math.min(width, 2400))) widths.push(Math.min(width, 2400));
	for (const w of widths) {
		const img = () => sharp(input).resize({ width: w, withoutEnlargement: true });
		const jobs = [
			[`${base}-${w}.avif`, (s) => s.avif({ quality: 52, effort: 6 })],
			[`${base}-${w}.webp`, (s) => s.webp({ quality: 78, effort: 6 })],
			[`${base}-${w}.jpg`, (s) => s.jpeg({ quality: 80, mozjpeg: true, progressive: true })],
		];
		for (const [name, enc] of jobs) {
			const out = path.join(outDir, name);
			if (fresh(out, input)) continue;
			await enc(img()).toFile(out);
			made++;
		}
	}
}

// Vorschaubild (HA 10): Hero-Bild 1, Ausschnitt wie auf der Startseite (object-position 60 % 50 %), Verlauf links wie im
// Hero, weißes Logo.
const ogDir = path.join(dst, 'assets/og');
fs.mkdirSync(ogDir, { recursive: true });
const og = path.join(ogDir, 'serviceinvest.jpg');
const hero = path.join(imgDir, 'hero-1-quartier.jpg');
if (!fresh(og, hero)) {
	const meta = await sharp(hero).metadata();
	const scale = Math.max(1200 / meta.width, 630 / meta.height);
	const w = Math.round(meta.width * scale);
	const h = Math.round(meta.height * scale);
	const left = Math.round((w - 1200) * 0.6);
	const top = Math.round((h - 630) * 0.5);
	const shade = Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630"><defs>'
		+ '<linearGradient id="a" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#000" stop-opacity=".62"/><stop offset=".42" stop-color="#000" stop-opacity=".28"/><stop offset=".72" stop-color="#000" stop-opacity="0"/></linearGradient>'
		+ '<linearGradient id="b" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#000" stop-opacity=".42"/><stop offset=".18" stop-color="#000" stop-opacity="0"/><stop offset=".52" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".7"/></linearGradient>'
		+ '</defs><rect width="1200" height="630" fill="url(#a)"/><rect width="1200" height="630" fill="url(#b)"/></svg>');
	const logo = await sharp(path.join(src, 'assets/logo/serviceinvest-logo-weiss.svg'), { density: 300 }).resize({ width: 420 }).png().toBuffer();
	await sharp(hero).resize(w, h).extract({ left, top, width: 1200, height: 630 })
		.composite([{ input: shade }, { input: logo, left: 64, top: 64 }])
		.jpeg({ quality: 82, mozjpeg: true }).toFile(og);
	made++;
}

// Touch-Icon 180 × 180 und Favicon-Vorlage 64 × 64: Bildmarke in Farbe auf Weiß (die Bildmarke ist für hellen Grund gemacht).
const mark = path.join(src, 'assets/logo/serviceinvest-bildmarke-farbe.svg');
for (const [name, size, pad] of [['apple-touch-icon.png', 180, 22], ['favicon-64.png', 64, 4]]) {
	const out = path.join(dst, name);
	if (fresh(out, mark)) continue;
	const inner = await sharp(mark, { density: 600 }).resize({ width: size - 2 * pad, height: size - 2 * pad, fit: 'contain', background: '#ffffff00' }).png().toBuffer();
	await sharp({ create: { width: size, height: size, channels: 4, background: '#ffffff' } })
		.composite([{ input: inner, gravity: 'center' }]).png().toFile(out);
	made++;
}

console.log(`Bilder: ${made} Dateien erzeugt, übrige aktuell.`);
