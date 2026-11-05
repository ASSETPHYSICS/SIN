/*
 * Browserprüfung (Playwright/Chromium) einer ServiceInvest-v1-Installation (HA 4.1, 11, 13, soweit automatisch prüfbar).
 *
 *   NODE_PATH=~/v1-browsertest/node_modules:~/sin-imgtools/node_modules node tools/v1/browser-check.js <Basis-URL> [Paket-URL]
 *
 * Je Seite und Breite (360 … 1920 px): Status, keine Konsolenfehler, keine Anfragen an fremde Server, keine Cookies, kein
 * seitlicher Bildlauf. Mit Paket-URL (content/v1-vorlage über python3 -m http.server; das Paket rendert per support.js):
 *   - Text: sichtbarer Text jeder Seite = Text des Pakets im Anfangszustand (HA 3.1); ohne Skip-Link und Screenreader-Hinweise
 *   - Alternativtexte gleich, in gleicher Reihenfolge (HA 3.2)
 *   - Darstellung: Bildschirmfoto der ganzen Seite ohne Bewegung pixelgleich zum Paket. Die Bildvarianten (AVIF/WebP) werden
 *     dafür durch die Originalbilder ersetzt, sonst zählte die Kompression
 *     bzw. die Ergänzung als Abweichung.
 * Abläufe: Menü (Fokus, Escape), Slider, Reiter mit Pfeiltasten, Fonds-Zugang, Kontakt- und Mieterformular
 * (Prüfung, Hinweise, Danke-Ansicht, Fehlermeldung bei Ausfall), Sprachwechsel, ohne JavaScript, axe (WCAG 2.2 AA).
 *
 * Umgebung:
 *   SIN_NO_SUBMIT=1   Formulare nicht wirklich senden (Live: sonst geht eine Mail an info@serviceinvest.de)
 *   SIN_FUND_PW_0/1   Passwort Debeka/TAS für den Test des geschützten Bereichs (lokal: lokal-test-debeka, lokal-test-tas-1)
 *   SIN_WIDTHS        z. B. "390,1440" statt aller Breiten
 */
const { chromium } = require('playwright');
const { PNG } = require('playwright-core/lib/utilsBundle');
const fs = require('fs');
const path = require('path');
const AXE = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

const BASE = (process.argv[2] || 'http://localhost:8092').replace(/\/$/, '');
const REF = (process.argv[3] || '').replace(/\/$/, '');
const PKG = path.join(__dirname, '../../content/v1-vorlage');
const WIDTHS = (process.env.SIN_WIDTHS || '360,390,768,960,1024,1280,1440,1920').split(',').map(Number);
const PAGES = {
	'/': 'index.html', '/nachhaltigkeit/': 'nachhaltigkeit.html', '/anlegerzugang/': 'anlegerzugang.html', '/mieter/': 'mieter.html',
	'/kontakt/': 'kontakt.html', '/impressum/': 'impressum.html', '/datenschutz/': 'datenschutz.html', '/datenschutz-mieter/': 'datenschutz-mieter.html',
	'/pai-statement/': 'pai-statement.html', '/stewardship-policy/': 'stewardship-policy.html', '/verguetungspolitik/': 'verguetungspolitik.html',
	'/beschwerdestelle/': 'beschwerdestelle.html',
	'/en/': 'en/index.html', '/en/sustainability/': 'en/sustainability.html', '/en/investor-access/': 'en/investor-access.html', '/en/tenants/': 'en/tenants.html',
	'/en/contact/': 'en/contact.html', '/en/legal-notice/': 'en/legal-notice.html', '/en/privacy/': 'en/privacy.html', '/en/privacy-tenants/': 'en/privacy-tenants.html',
	'/en/pai-statement/': 'en/pai-statement.html', '/en/stewardship-policy/': 'en/stewardship-policy.html', '/en/remuneration-policy/': 'en/remuneration-policy.html',
	'/en/complaints/': 'en/complaints.html',
};
const PATHS = Object.keys(PAGES);
const PAIR = Object.fromEntries(PATHS.slice(0, 12).flatMap((de, i) => [[de, PATHS[12 + i]], [PATHS[12 + i], de]]));

/**
 * Abweichende Pixel: [leicht (> 8 je Farbkanal), stark (> 96)], null bei anderer Größe. Schrift über Fotos glättet Chromium
 * im Paket und in WordPress minimal anders (Abweichungen bis etwa 90); eine Verschiebung um 1 px erzeugt dagegen viele
 * starke Abweichungen (Tausende). Bestanden: höchstens 8 % leicht (Startseiten mit viel Text auf Fotos: bis etwa 6 %) und
 * 0,05 % stark abweichende Pixel (weiße Schrift auf hellem Bildteil und native Auswahllisten: bis etwa 0,03 %).
 */
const pixdiff = (bufA, bufB) => {
	const a = PNG.sync.read(bufA), b = PNG.sync.read(bufB);
	if (a.width !== b.width || a.height !== b.height) return null;
	let weak = 0, strong = 0;
	for (let i = 0; i < a.data.length; i += 4) {
		const m = Math.max(Math.abs(a.data[i] - b.data[i]), Math.abs(a.data[i + 1] - b.data[i + 1]), Math.abs(a.data[i + 2] - b.data[i + 2]));
		if (m > 8) weak++;
		if (m > 96) strong++;
	}
	return [weak, strong];
};
const pixOk = (d, total) => d && d[0] <= total * 0.08 && d[1] <= total * 0.0005;
let fails = 0;
const ok = (c, msg) => { if (!c) fails++; if (!c || process.env.VERBOSE) console.log((c ? '  ok    ' : '  FEHLER ') + msg); };
const scrollThrough = async (pg) => pg.evaluate(async () => {
	for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 40)); }
	window.scrollTo(0, 0);
	await Promise.all([...document.images].map(i => i.complete ? 0 : new Promise(r => { i.onload = i.onerror = r; })));
});
/** Sichtbarer Text (ohne Ergänzungen), Leerraum vereinheitlicht. */
const visibleText = async (pg) => pg.evaluate(() => {
	const s = document.createElement('style');
	s.textContent = '.skip{display:none!important}';
	document.head.appendChild(s);
	const t = document.body.innerText.replace(/­/g, '').replace(/\s+/g, ' ').trim();
	s.remove();
	return t;
});
const alts = async (pg) => pg.evaluate(() => [...document.images].map(i => i.getAttribute('alt')));
const firstDiff = (a, b) => { let i = 0; while (i < a.length && a[i] === b[i]) i++; return `ab Zeichen ${i}:\n     Paket: ${JSON.stringify(a.slice(Math.max(0, i - 60), i + 80))}\n     Seite: ${JSON.stringify(b.slice(Math.max(0, i - 60), i + 80))}`; };
/** Bildvarianten auf die Originale umleiten (nur für den Pixelvergleich). */
const originals = async (pg) => pg.route(/\/assets\/img\/v\/([\w-]+?)-\d+\.(avif|webp|jpg)$/, (route) => {
	const base = route.request().url().match(/\/assets\/img\/v\/([\w-]+?)-\d+\.(?:avif|webp|jpg)$/)[1];
	route.fulfill({ path: path.join(PKG, 'assets/img', base + '.jpg'), contentType: 'image/jpeg' });
});

(async () => {
	const browser = await chromium.launch();
	const host = new URL(BASE).host;

	for (const w of WIDTHS) {
		console.log(`== ${w} px`);
		const ctx = await browser.newContext({ viewport: { width: w, height: 900 } });
		const still = REF ? await browser.newContext({ viewport: { width: w, height: 900 }, reducedMotion: 'reduce' }) : null;
		for (const p of PATHS) {
			const page = await ctx.newPage();
			const errors = [], external = [];
			page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
			page.on('pageerror', e => errors.push(String(e)));
			page.on('request', r => { const u = new URL(r.url()); if (/^https?:$/.test(u.protocol) && u.host !== host) external.push(r.url()); });
			const res = await page.goto(BASE + p, { waitUntil: 'networkidle' });
			ok(res.status() === 200, `${p} Status ${res.status()}`);
			await scrollThrough(page);
			await page.waitForLoadState('networkidle');
			const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
			ok(overflow <= 0, `${p} seitlicher Bildlauf ${overflow}px`);
			ok(!errors.length, `${p} Konsolenfehler: ${errors.join(' | ')}`);
			ok(!external.length, `${p} fremde Anfragen: ${external.join(' ')}`);
			ok(!(await ctx.cookies()).length, `${p} Cookies: ${(await ctx.cookies()).map(c => c.name).join(',')}`);
			if (REF && (w === 390 || w === 1440)) {
				const ref = await ctx.newPage();
				await ref.goto(`${REF}/${PAGES[p]}`, { waitUntil: 'networkidle' });
				await ref.waitForTimeout(300);
				const a = await visibleText(ref), b = await visibleText(page);
				ok(a === b, `${p} Text weicht vom Paket ab ${a === b ? '' : firstDiff(a, b)}`);
				ok(JSON.stringify(await alts(ref)) === JSON.stringify(await alts(page)), `${p} Alternativtexte weichen ab`);
				await ref.close();
			}
			await page.close();

			if (REF) {
				const shot = async (url, wp) => {
					const pg = await still.newPage();
					if (wp) await originals(pg);
					await pg.goto(url, { waitUntil: 'networkidle' });
					await pg.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' });
					await scrollThrough(pg);
					await pg.waitForTimeout(300);
					const b = await pg.screenshot({ fullPage: true });
					await pg.close();
					return b;
				};
				// bis zu drei Versuche (Laden der Bilder, Zeitpunkt des Bildschirmfotos)
				const a = await shot(`${REF}/${PAGES[p]}`, false);
				const total = PNG.sync.read(a).width * PNG.sync.read(a).height;
				let d = null;
				for (let i = 0; i < 3 && !pixOk(d, total); i++) {
					const di = pixdiff(a, await shot(BASE + p, true));
					if (!d || (di && di[1] < d[1])) d = di;
				}
				ok(pixOk(d, total), `${p} Darstellung weicht vom Paket ab: ${d ? `${d[0]} leicht, ${d[1]} stark abweichende Pixel von ${total}` : 'andere Seitengröße'}`);
			}
		}
		await ctx.close();
		if (still) await still.close();
	}

	console.log('== Abläufe');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
	page.on('pageerror', e => errors.push(String(e)));

	// Sprachwechsel: EN/DE im Kopf und "English"/"Deutsch" in der Fußzeile führen auf die passende Seite (HA 5.1)
	for (const p of PATHS) {
		await page.goto(BASE + p);
		const hrefs = await page.locator('header a.util, footer a.flink').evaluateAll(as => as.filter(a => /^(EN|DE|English|Deutsch)$/.test(a.textContent.trim())).map(a => a.getAttribute('href')));
		ok(hrefs.length >= 2 && hrefs.every(h => h === PAIR[p]), `${p} Sprachwechsel -> ${hrefs.join(', ')} (erwartet ${PAIR[p]})`);
	}

	// Slider: Klick auf den dritten Abschnitt, Halt bei Hover über der Steuerung, Weiterlauf danach
	await page.goto(BASE + '/', { waitUntil: 'networkidle' });
	await page.locator('.hero .seg').nth(2).click();
	ok(await page.locator('.hero .slide').nth(2).evaluate(e => e.classList.contains('on')), 'Slider springt per Klick');
	ok(await page.locator('[data-pause]').count() === 0, 'keine Pause-Schaltfläche');
	await page.waitForTimeout(8600);
	ok(await page.locator('.hero .slide').nth(2).evaluate(e => e.classList.contains('on')), 'Hover über der Steuerung: kein Bildwechsel nach 8 s');
	await page.mouse.move(10, 10);
	await page.waitForTimeout(8600);
	ok(await page.locator('.hero .slide').nth(3).evaluate(e => e.classList.contains('on')), 'fortgesetzt: Bildwechsel nach 8 s');

	// Mobiles Menü: Fokus ins Menü, Escape schließt, Fokus zurück
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto(BASE + '/kontakt/', { waitUntil: 'networkidle' });
	await page.locator('[data-menu-open]').click();
	ok(await page.locator('#mmenu').isVisible() && await page.evaluate(() => document.activeElement.hasAttribute('data-menu-close')), 'Menü öffnet, Fokus im Menü');
	await page.keyboard.press('Shift+Tab');
	ok(await page.evaluate(() => !!document.activeElement.closest('#mmenu')), 'Menü hält den Fokus');
	await page.keyboard.press('Escape');
	ok(!(await page.locator('#mmenu').isVisible()) && await page.evaluate(() => document.activeElement.hasAttribute('data-menu-open')), 'Escape schließt, Fokus zurück');
	await page.goto(BASE + '/', { waitUntil: 'networkidle' });
	await page.locator('a.skip').focus();
	ok(await page.locator('a.skip').isVisible(), 'Skip-Link bei Fokus sichtbar');
	await page.setViewportSize({ width: 1440, height: 900 });

	// Reiter Anlegergruppe (ARIA-Tabs)
	for (const [p, lang] of [['/anlegerzugang/', 'de'], ['/en/investor-access/', 'en']]) {
		await page.goto(BASE + p, { waitUntil: 'networkidle' });
		await page.locator('#tab-priv').click();
		ok(await page.locator('#panel-priv').isVisible() && !(await page.locator('#panel-inst').isVisible()), `${p} Reiter Privatanleger`);
		await page.keyboard.press('ArrowLeft');
		ok(await page.locator('#panel-inst').isVisible() && await page.evaluate(() => document.activeElement.id) === 'tab-inst', `${p} Pfeiltaste wechselt Reiter`);
		// Fonds-Zugang: aufklappen, falsches Passwort
		const t0 = page.locator('[data-fund-toggle="0"]');
		await t0.click();
		ok(await page.locator('#fund-form-0').isVisible() && await t0.getAttribute('aria-expanded') === 'true' && (await t0.textContent()) === (lang === 'de' ? 'Schließen' : 'Close'), `${p} Fonds aufklappen`);
		await page.fill('#pw-0', 'falsch-falsch');
		await page.locator('#fund-form-0 [type=submit]').click();
		await page.locator('#fund-form-0 .az-msg').waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
		ok(await page.locator('#fund-form-0 .az-msg').isVisible() && !(await page.locator('#fund-form-0 .az-docs').isVisible()), `${p} falsches Passwort abgewiesen`);
		ok(!(await ctx.cookies()).length, `${p} kein Cookie nach falschem Passwort`);
		await t0.click();
		ok(!(await page.locator('#fund-form-0').isVisible()) && (await t0.textContent()) === (lang === 'de' ? 'Zugang' : 'Access'), `${p} Fonds zuklappen`);
	}

	// Ohne JavaScript: alle Inhalte lesbar (beide Anlegergruppen)
	const nojs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1440, height: 900 } });
	const nj = await nojs.newPage();
	await nj.goto(BASE + '/anlegerzugang/');
	ok(await nj.locator('#panel-inst').isVisible() && await nj.locator('#panel-priv').isVisible(), 'ohne JavaScript: beide Anlegergruppen sichtbar');
	await nj.goto(BASE + '/');
	ok(await nj.locator('#anleger').isVisible() && await nj.locator('.cnt .nv').first().isVisible(), 'ohne JavaScript: Startseite mit Kennzahlen');
	await nojs.close();

	// Kontaktformular
	for (const p of ['/kontakt/', '/en/contact/']) {
		await page.goto(BASE + p, { waitUntil: 'networkidle' });
		await page.locator('#k-form [type=submit]').click();
		ok(await page.locator('#e-name').isVisible() && await page.locator('#e-email').isVisible() && await page.locator('#e-msg').isVisible() && await page.locator('#e-ds').isVisible(), `${p} Pflichtfelder gemeldet`);
		ok(await page.evaluate(() => document.activeElement.id) === 'f-name' && await page.locator('#f-name').getAttribute('aria-invalid') === 'true', `${p} Sprung zum ersten Fehler`);
		ok(await page.locator('[data-if="hasErr"]').isVisible(), `${p} Sammelhinweis (role=alert)`);
		await page.fill('#f-name', 'Browsertest');
		await page.fill('#f-email', 'kein-format');
		ok((await page.locator('#e-email').textContent()).includes(p === '/kontakt/' ? 'name@unternehmen.de' : 'name@company.com') && !(await page.locator('#e-name').isVisible()), `${p} Prüfung beim Tippen, Format-Hinweis`);
		for (const [v, k] of [['anleger-institutionell', 'isInst'], ['anleger-privat', 'isPriv'], ['mieter', 'isMieter']]) {
			await page.selectOption('#f-anliegen', v);
			ok(await page.locator(`[data-if="${k}"]`).isVisible(), `${p} Hinweis bei ${v}`);
		}
		await page.selectOption('#f-anliegen', 'sonstiges');
		await page.fill('#f-email', 'browsertest@example.org');
		await page.fill('#f-msg', `Automatischer Test der Seite ${p} (bitte ignorieren)`);
		await page.check('#f-ds');
		if (!process.env.SIN_NO_SUBMIT) {
			await page.waitForTimeout(3200);   // Mindestausfüllzeit
			await page.locator('#k-form [type=submit]').click();
			await page.locator('[data-if="sent"]').waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
			ok(await page.locator('[data-if="sent"]').isVisible() && await page.evaluate(() => document.activeElement.id) === 'thanks-title', `${p} Danke-Ansicht`);
			await page.locator('[data-reset]').click();
			ok(await page.locator('#k-form').isVisible() && (await page.inputValue('#f-name')) === '', `${p} neue Nachricht`);
		}
		await page.goto(BASE + p, { waitUntil: 'networkidle' });
		await page.route('**/admin-ajax.php**', r => r.fulfill({ status: 500, body: '{}' }));
		await page.fill('#f-name', 'x'); await page.fill('#f-email', 'x@example.org'); await page.fill('#f-msg', 'x'); await page.check('#f-ds');
		await page.locator('#k-form [type=submit]').click();
		const alert = page.locator('#k-form [data-send-error]');
		await alert.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
		ok((await alert.isVisible()) && (await alert.textContent()).includes('info@serviceinvest.de'), `${p} Fehlermeldung bei Ausfall`);
		await page.unroute('**/admin-ajax.php**');
	}

	// Mieterformular
	for (const p of ['/mieter/', '/en/tenants/']) {
		await page.goto(BASE + p, { waitUntil: 'networkidle' });
		const fill = async () => {
			await page.fill('#m-str', 'Teststraße 1'); await page.fill('#m-plz', '80686'); await page.fill('#m-ort', 'München');
			await page.fill('#m-hv', 'Test-Hausverwaltung'); await page.selectOption('#m-art', 'sonstiges');
			await page.fill('#m-text', `Automatischer Test der Seite ${p} (bitte ignorieren)`); await page.fill('#m-name', 'Browsertest');
			await page.fill('#m-mail', 'browsertest@example.org'); await page.check('#m-ok');
		};
		await fill();
		await page.fill('#m-hv', '   ');
		await page.locator('#m-form [type=submit]').click();
		ok(await page.locator('[data-if="error"]').isVisible(), `${p} Hinweis bei fehlender Hausverwaltung`);
		if (!process.env.SIN_NO_SUBMIT) {
			await page.fill('#m-hv', 'Test-Hausverwaltung');
			await page.waitForTimeout(3200);
			await page.locator('#m-form [type=submit]').click();
			await page.locator('[data-if="sent"]').waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
			ok(await page.locator('[data-if="sent"]').isVisible() && !(await page.locator('#m-form').isVisible()), `${p} Danke-Ansicht`);
		}
		await page.goto(BASE + p, { waitUntil: 'networkidle' });
		await page.route('**/admin-ajax.php**', r => r.fulfill({ status: 500, body: '{}' }));
		await fill();
		await page.locator('#m-form [type=submit]').click();
		const alert = page.locator('#m-form [data-send-error]');
		await alert.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
		ok((await alert.isVisible()) && (await alert.textContent()).includes('info@serviceinvest.de'), `${p} Fehlermeldung bei Ausfall`);
		await page.unroute('**/admin-ajax.php**');
	}
	ok(!errors.filter(e => !/500/.test(e)).length, `Konsolenfehler in Abläufen: ${errors.join(' | ')}`);
	ok(!(await ctx.cookies()).length, 'keine Cookies nach den Abläufen');

	// axe (WCAG 2.2 AA) je Seite im Anfangszustand, Anlegerzugang zusätzlich aufgeklappt
	console.log('== axe');
	// ohne Bewegung: sonst misst axe Kontraste mitten in Einblend- und Hover-Übergängen
	const axeCtx = await browser.newContext({ viewport: { width: 1440, height: 900 }, bypassCSP: true, reducedMotion: 'reduce' });
	const axePage = await axeCtx.newPage();
	for (const p of PATHS) {
		const page = axePage;
		await page.goto(BASE + p, { waitUntil: 'networkidle' });
		if (/anlegerzugang|investor-access/.test(p)) await page.locator('[data-fund-toggle="0"]').click();
		await page.mouse.move(1, 1);
		await page.waitForTimeout(600);
		await page.addScriptTag({ content: AXE });
		const v = await page.evaluate(async () => (await window.axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] })).violations
			.map(x => `${x.id} (${x.nodes.length}): ${x.nodes.slice(0, 2).map(n => n.target.join(' ')).join(' | ')}`));
		ok(!v.length, `${p} axe: ${v.join(' ; ')}`);
	}

	// Geschützter Bereich mit echtem Passwort (nur wenn angegeben)
	for (const [i, env] of [[0, 'SIN_FUND_PW_0'], [1, 'SIN_FUND_PW_1']]) {
		if (!process.env[env]) continue;
		const c = await browser.newContext();
		const pg = await c.newPage();
		await pg.goto(BASE + '/anlegerzugang/', { waitUntil: 'networkidle' });
		await pg.locator(`[data-fund-toggle="${i}"]`).click();
		await pg.fill(`#pw-${i}`, process.env[env]);
		await pg.locator(`#fund-form-${i} [type=submit]`).click();
		const docs = pg.locator(`#fund-form-${i} .az-docs a`);
		await docs.first().waitFor({ timeout: 5000 }).catch(() => {});
		const n = await docs.count();
		ok(n > 0, `Fonds ${i}: ${n} Dokumente nach richtigem Passwort`);
		if (n) {
			const href = await docs.first().getAttribute('href');
			const r = await c.request.get(new URL(href, BASE).href);
			ok(r.status() === 200 && r.headers()['content-type'] === 'application/pdf' && /noindex/.test(r.headers()['x-robots-tag'] || ''), `Fonds ${i}: Dokument mit Sitzung ${r.status()}`);
			const other = await browser.newContext();
			const r2 = await other.request.get(new URL(href, BASE).href);
			ok(r2.status() === 404, `Fonds ${i}: Dokument ohne Sitzung ${r2.status()}`);
			const otherFund = href.replace(/sin_doc=[^%]+/, i ? 'sin_doc=debeka-immobilien-invest-i' : 'sin_doc=tas-wohnen-1');
			const r3 = await c.request.get(new URL(otherFund, BASE).href);
			ok(r3.status() === 404, `Fonds ${i}: Sitzung öffnet nur den eigenen Fonds (${r3.status()})`);
			await other.close();
			const cookie = (await c.cookies()).find(k => k.name === 'sin_fonds');
			ok(cookie && cookie.httpOnly && cookie.sameSite === 'Strict' && cookie.expires === -1, 'Sitzungs-Cookie HttpOnly, SameSite=Strict, ohne Ablaufdatum');
		}
		await c.close();
	}

	await browser.close();
	console.log(fails ? `\n${fails} Fehler` : '\nalles in Ordnung');
	process.exit(fails ? 1 : 0);
})();
