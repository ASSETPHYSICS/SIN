/* 2IP – Navigation als Sidebar: Hintergrund-Klick und ESC schließen, Scroll-Sperre, Gruppeneinträge klappbar */
(function () {
	'use strict';
	function init() {
		var nav = document.querySelector('.dd-hamburger-menu .mobile_nav');
		if (!nav) { return; }
		var bar = nav.querySelector('.mobile_menu_bar');

		// Gruppeneinträge (ohne eigene Seite): offen, wenn einer ihrer Unterpunkte aktiv ist.
		// Divi baut das mobile Menü erst nach dem Laden auf – daher bei Bedarf (idempotent).
		function setupGroups() {
			var groups = nav.querySelectorAll('.et_mobile_menu li.ip-nolink');
			for (var g = 0; g < groups.length; g++) {
				if (groups[g].getAttribute('data-ip-init')) { continue; }
				groups[g].setAttribute('data-ip-init', '1');
				var ga = groups[g].querySelector(':scope > a');
				var active = groups[g].classList.contains('current-menu-ancestor') || !!groups[g].querySelector('.current-menu-item');
				groups[g].classList.toggle('ip-open', active);
				if (ga) {
					ga.setAttribute('role', 'button');
					ga.setAttribute('aria-expanded', active ? 'true' : 'false');
				}
			}
		}

		function sync() {
			var open = nav.classList.contains('opened');
			document.documentElement.classList.toggle('ip-nav-open', open);
			if (bar) { bar.setAttribute('aria-expanded', open ? 'true' : 'false'); }
			setupGroups();
		}
		if (bar) {
			bar.setAttribute('role', 'button');
			bar.setAttribute('tabindex', '0');
			bar.setAttribute('aria-label', 'Navigation öffnen oder schließen');
			bar.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); bar.click(); }
			});
		}
		new MutationObserver(sync).observe(nav, { attributes: true, attributeFilter: ['class'] });
		sync();
		window.addEventListener('load', setupGroups);

		// Gruppeneintrag: Untermenü ein-/ausklappen (Capture-Phase, damit Divi das Menü dabei nicht schließt)
		nav.addEventListener('click', function (e) {
			var a = e.target.closest ? e.target.closest('.et_mobile_menu li.ip-nolink > a') : null;
			if (!a) { return; }
			e.preventDefault();
			e.stopPropagation();
			var li = a.parentNode;
			var open = li.classList.toggle('ip-open');
			a.setAttribute('aria-expanded', open ? 'true' : 'false');
		}, true);

		// Klick auf den abgedunkelten Hintergrund (Pseudo-Element von .mobile_nav) schließt.
		nav.addEventListener('click', function (e) {
			if (e.target === nav && nav.classList.contains('opened') && bar) { bar.click(); }
		});
		// Anker-Links auf derselben Seite schließen das Menü.
		nav.addEventListener('click', function (e) {
			var a = e.target.closest ? e.target.closest('.et_mobile_menu a') : null;
			var h = a && a.getAttribute('href');
			if (h && h !== '#' && h.charAt(0) === '#' && bar) { bar.click(); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && nav.classList.contains('opened') && bar) { bar.click(); }
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
