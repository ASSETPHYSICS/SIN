/*
 * ServiceInvest – Verhalten der Seiten ohne Framework (ersetzt support.js des Pakets, HA 4.2).
 * Der Server liefert jede Seite im Anfangszustand des Pakets aus; dieses Skript schaltet nur Klassen und [hidden] um.
 * Ohne JavaScript bleiben alle Inhalte lesbar (HA 4.2).
 */
(function () {
	'use strict';
	var doc = document;
	var lang = doc.documentElement.lang === 'en' ? 'en' : 'de';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function $(sel, root) { return (root || doc).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }
	/** Formular senden; bei Fehler den Code des Servers (expired, limit, mail …) weiterreichen. */
	function send(form) {
		return fetch(form.getAttribute('data-endpoint'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
			.then(function (r) {
				return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) throw (j && j.code) || 'error'; });
			});
	}
	/** Fehlermeldung zeigen: eigene Meldung für expired/limit (data-expired, data-limit), sonst die allgemeine mit E-Mail-Adresse. */
	function showSendError(el, code) {
		if (el._text === undefined) el._text = el.innerHTML;
		var own = typeof code === 'string' ? el.getAttribute('data-' + code) : null;
		if (own) el.textContent = own; else el.innerHTML = el._text;
		el.hidden = false;
	}
	function focusables(root) {
		return $$('a[href], button:not([disabled]), input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])', root)
			.filter(function (el) { return !el.closest('[hidden]'); });
	}

	/* --- Mobiles Menü: Fokus ins Menü, Fokus halten, Escape schließt, Fokus zurück (HA 4.2, 11) --- */
	var menu = doc.getElementById('mmenu');
	var opener = $('[data-menu-open]');
	if (menu && opener) {
		var closeMenu = function (restore) {
			if (menu.hidden) return;
			menu.hidden = true;
			opener.setAttribute('aria-expanded', 'false');
			if (restore) opener.focus();
		};
		opener.addEventListener('click', function () {
			menu.hidden = false;
			opener.setAttribute('aria-expanded', 'true');
			var first = $('[data-menu-close]', menu);
			if (first) first.focus();
		});
		$$('[data-menu-close]', menu).forEach(function (b) { b.addEventListener('click', function () { closeMenu(true); }); });
		// Sprungmarken auf derselben Seite: Menü schließen, damit das Ziel sichtbar wird
		$$('a[href]', menu).forEach(function (a) { a.addEventListener('click', function () { closeMenu(false); }); });
		doc.addEventListener('keydown', function (e) {
			if (menu.hidden) return;
			if (e.key === 'Escape') { e.preventDefault(); closeMenu(true); return; }
			if (e.key !== 'Tab') return;
			var f = focusables(menu);
			if (!f.length) return;
			if (e.shiftKey && doc.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && doc.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		});
		// Breites Fenster: Menü-Symbol verschwindet, offene Ebene schließen
		window.addEventListener('resize', function () { if (window.innerWidth > 1359) closeMenu(false); });
	}

	/* --- Hero-Slider der Startseite: 5 Bilder, Wechsel alle 8 s, Halt bei Hover/Tastaturfokus der Steuerung --- */
	var hero = $('.hero');
	var slides = hero ? $$('.slide', hero) : [];
	if (slides.length) {
		var segs = $$('[data-go]', hero);
		var controls = segs.length ? segs[0].parentNode : null;
		var cur = 0, prev = -1, timer = null, paused = reduce, hold = false; // "Bewegung reduzieren": kein automatischer Wechsel
		var render = function () {
			slides.forEach(function (s, k) { s.className = k === cur ? 'slide on' : (k === prev ? 'slide was' : 'slide'); });
			segs.forEach(function (s, k) {
				s.className = k === cur ? 'seg on' : (k < cur ? 'seg done' : 'seg');
				if (k === cur) s.setAttribute('aria-current', 'true'); else s.removeAttribute('aria-current');
			});
		};
		var restartBar = function () {
			var on = segs[cur];
			if (!on) return;
			on.classList.remove('on');
			void on.offsetWidth; // Animation des Fortschrittsbalkens neu starten
			on.classList.add('on');
		};
		var schedule = function () {
			clearInterval(timer);
			timer = null;
			if (!paused && !hold) timer = setInterval(function () { go((cur + 1) % slides.length); }, 8000);
		};
		var go = function (k) {
			if (k === cur) return;
			prev = cur;
			cur = k;
			render();
		};
		segs.forEach(function (s) {
			s.addEventListener('click', function () { go(+s.getAttribute('data-go')); restartBar(); schedule(); });
		});
		if (controls) {
			var setHold = function (h) {
				if (h === hold) return;
				hold = h;
				hero.classList.toggle('paused', paused || hold);
				if (!hold && !paused) restartBar();
				schedule();
			};
			controls.addEventListener('mouseenter', function () { setHold(true); });
			controls.addEventListener('mouseleave', function () { setHold(!!controls.querySelector(':focus-visible')); });
			// nur Tastaturfokus hält an; nach einem Mausklick auf einen Abschnitt soll es weiterlaufen
			controls.addEventListener('focusin', function (e) { setHold(e.target.matches(':focus-visible') || controls.matches(':hover')); });
			controls.addEventListener('focusout', function (e) { if (!controls.contains(e.relatedTarget)) setHold(false); });
		}
		doc.addEventListener('visibilitychange', function () { if (doc.hidden) { clearInterval(timer); timer = null; } else { schedule(); } });
		render();
		hero.classList.toggle('paused', paused);
		schedule();
	}

	/* --- Effekte beim Erscheinen (Startseite): einmalig per IntersectionObserver --- */
	var fx = $('.fx');
	if (fx && 'IntersectionObserver' in window) {
		var targets = [
			['section[aria-labelledby="lage-title"]', function () { $$('.approach').forEach(function (el) { el.classList.add('go'); }); }],
			['#rolle .seq', null], ['#leistungen .seq', null], ['#technologie .seq', null], ['#einstieg .seq', null],
			['#anleger .stack2', function () { $$('.cnt').forEach(function (el) { el.classList.add('go'); }); }]
		];
		var actions = new Map();
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (!e.isIntersecting) return;
				var act = actions.get(e.target);
				if (act) act(); else e.target.classList.add('go');
				io.unobserve(e.target);
			});
		}, { rootMargin: '0px 0px -12% 0px', threshold: 0.12 });
		targets.forEach(function (t) {
			var el = $(t[0]);
			if (!el) return;
			if (t[1]) actions.set(el, t[1]);
			io.observe(el);
		});
		fx.classList.add('io');
	}

	/* --- Reiter Institutionelle Anleger / Privatanleger (ARIA-Tabs mit Pfeiltasten) --- */
	var tabs = $$('[role="tab"][data-tab]');
	if (tabs.length) {
		var select = function (tab, focus) {
			tabs.forEach(function (t) {
				var on = t === tab;
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
				var panel = doc.getElementById(t.getAttribute('aria-controls'));
				if (panel) panel.hidden = !on;
			});
			if (focus) tab.focus();
		};
		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { select(t, false); });
			t.addEventListener('keydown', function (e) {
				var n = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
				if (n === undefined) return;
				e.preventDefault();
				select(tabs[(n + tabs.length) % tabs.length], true);
			});
		});
		var initial = location.hash === '#panel-priv' ? tabs[1] : tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0];
		select(initial || tabs[0], false);
	}

	/* --- Fonds-Zugang: Passwortfeld aufklappen, Prüfung nur auf dem Server (HA 7) --- */
	var funds = $('[data-funds]');
	if (funds) {
		var endpoint = funds.getAttribute('data-endpoint');
		var texts = { de: { error: 'Ein Fehler ist aufgetreten. Bitte laden Sie die Seite neu und versuchen Sie es erneut.' }, en: { error: 'An error has occurred. Please reload the page and try again.' } }[lang];
		var known = {};
		var post = function (data) {
			var body = new FormData();
			Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
			return fetch(endpoint, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
		};
		var showDocs = function (form, docs) {
			$$('.field, button[type="submit"]', form).forEach(function (el) { el.hidden = true; });
			var msg = $('.az-msg', form);
			msg.hidden = true;
			var list = $('.az-docs', form);
			list.innerHTML = '';
			docs.forEach(function (d) {
				var li = doc.createElement('li');
				var a = doc.createElement('a');
				a.className = 'doc';
				a.href = d.url;
				a.textContent = d.title;
				li.appendChild(a);
				list.appendChild(li);
			});
			if (!docs.length) { msg.textContent = funds.getAttribute('data-empty'); msg.hidden = false; }
			list.hidden = !docs.length;
		};
		$$('[data-fund-toggle]', funds).forEach(function (btn) {
			var i = btn.getAttribute('data-fund-toggle');
			var form = doc.getElementById('fund-form-' + i);
			var input = $('input[type="password"]', form);
			var msg = $('.az-msg', form);
			btn.addEventListener('click', function () {
				var open = form.hidden;
				form.hidden = !open;
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
				btn.textContent = btn.getAttribute(open ? 'data-label-close' : 'data-label-open');
				if (open && known[i]) showDocs(form, known[i]);
				else if (open) input.focus();
			});
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				msg.hidden = true;
				post({ action: 'sin_fonds', fund: i, password: input.value, lang: lang }).then(function (r) {
					if (r && r.ok) { known[i] = r.docs || []; input.value = ''; showDocs(form, known[i]); return; }
					msg.textContent = (r && r.message) || texts.error;
					msg.hidden = false;
					input.focus();
				}).catch(function () { msg.textContent = texts.error; msg.hidden = false; });
			});
		});
		// Gültige Sitzung (2 Stunden): Dokumente ohne erneute Eingabe zeigen
		post({ action: 'sin_fonds_status', lang: lang }).then(function (r) { if (r && r.ok && r.open) known = r.open; }).catch(function () {});
	}

	/* --- Kontaktformular: Prüfung wie im Paket, Fehlermeldungen am Feld, Sprung zum ersten Fehler, Versand an den Server --- */
	var kform = doc.getElementById('k-form');
	if (kform) {
		var fields = { name: $('#f-name'), email: $('#f-email'), msg: $('#f-msg'), ds: $('#f-ds') };
		var anliegen = $('#f-anliegen');
		var tried = false;
		var validate = function () {
			var errs = {};
			if (!fields.name.value.trim()) errs.name = 'empty';
			var mail = fields.email.value.trim();
			if (!mail) errs.email = 'empty';
			else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(mail)) errs.email = 'format';
			if (!fields.msg.value.trim()) errs.msg = 'empty';
			if (!fields.ds.checked) errs.ds = 'unchecked';
			return errs;
		};
		var showErrors = function (errs) {
			Object.keys(fields).forEach(function (k) {
				var el = fields[k];
				var p = doc.getElementById('e-' + k);
				var bad = !!errs[k];
				el.setAttribute('aria-invalid', bad ? 'true' : 'false');
				if (bad) el.setAttribute('aria-describedby', 'e-' + k); else el.removeAttribute('aria-describedby');
				p.hidden = !bad;
				if (k === 'email' && bad) p.textContent = p.getAttribute(errs.email === 'format' ? 'data-format' : 'data-empty');
			});
			$('[data-if="hasErr"]', kform).hidden = !(tried && Object.keys(errs).length);
		};
		var hints = { 'anleger-institutionell': 'isInst', 'anleger-privat': 'isPriv', mieter: 'isMieter' };
		var showHints = function () {
			Object.keys(hints).forEach(function (v) { $('[data-if="' + hints[v] + '"]', kform).hidden = anliegen.value !== v; });
		};
		anliegen.addEventListener('change', showHints);
		Object.keys(fields).forEach(function (k) {
			fields[k].addEventListener(k === 'ds' ? 'change' : 'input', function () { if (tried) showErrors(validate()); });
		});
		var sendError = $('[data-send-error]', kform);
		var sentView = $('[data-if="sent"]');
		var formViews = $$('[data-if="notSent"]');
		kform.addEventListener('submit', function (e) {
			e.preventDefault();
			sendError.hidden = true;
			var errs = validate();
			var first = ['name', 'email', 'msg', 'ds'].filter(function (k) { return errs[k]; })[0];
			if (first) { tried = true; showErrors(errs); fields[first].focus(); return; }
			var btn = $('button[type="submit"]', kform);
			btn.disabled = true;
			send(kform)
				.then(function () {
					tried = false;
					showErrors({});
					formViews.forEach(function (v) { v.hidden = true; });
					sentView.hidden = false;
					$('#thanks-title').focus();
				})
				.catch(function (code) { showSendError(sendError, code); })
				.then(function () { btn.disabled = false; });
		});
		$('[data-reset]', sentView).addEventListener('click', function () {
			kform.reset();
			tried = false;
			showErrors({});
			showHints();
			sentView.hidden = true;
			formViews.forEach(function (v) { v.hidden = false; });
			anliegen.focus();
		});
	}

	/* --- Mieterformular: Pflichtfelder (Browser-Prüfung wie im Paket), Weiterleitung über den Server --- */
	var mform = doc.getElementById('m-form');
	if (mform) {
		var mErr = $('[data-if="error"]', mform);
		var mSendErr = $('[data-send-error]', mform);
		var mSent = $('[data-if="sent"]');
		mform.addEventListener('submit', function (e) {
			e.preventDefault();
			mSendErr.hidden = true;
			var need = ['strasse', 'plz', 'ort', 'hausverwaltung'];
			var ok = need.every(function (n) { return mform.elements[n] && String(mform.elements[n].value).trim().length > 0; });
			mErr.hidden = ok;
			if (!ok) return;
			var btn = $('button[type="submit"]', mform);
			btn.disabled = true;
			send(mform)
				.then(function () {
					mform.hidden = true;
					mSent.hidden = false;
					var h = $('h3', mSent);
					h.setAttribute('tabindex', '-1');
					h.focus();
				})
				.catch(function (code) { showSendError(mSendErr, code); })
				.then(function () { btn.disabled = false; });
		});
		$('[data-reset]', mSent).addEventListener('click', function () {
			mform.reset();
			mErr.hidden = true;
			mSent.hidden = true;
			mform.hidden = false;
			$('input, select, textarea', mform).focus();
		});
	}
})();
