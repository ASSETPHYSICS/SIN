/* 2IP – Hamburger-Symbol: Farbe (schwarz/weiß) je nach Hintergrund unter dem Symbol */
(function () {
	'use strict';
	var root = document.documentElement;
	var DARK = '.ip-hero, .ip-sec--black, .ip-sec--forest, .ip-sec--brand, .ip-nh-photo, .ip-fund-hero, .ip-footer, .ip-disclaimer';
	var LIGHT = '.ip-sec--white, .ip-sec--mist, .et-l--header';
	var ticking = false;

	function luminance(rgb) {
		var m = rgb.match(/rgba?\(([^)]+)\)/);
		if (!m) { return null; }
		var p = m[1].split(',').map(parseFloat);
		if (p.length > 3 && p[3] < 0.5) { return null; }
		return (0.299 * p[0] + 0.587 * p[1] + 0.114 * p[2]) / 255;
	}

	function isDarkAt(x, y) {
		var els = document.elementsFromPoint ? document.elementsFromPoint(x, y) : [];
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (el.closest('.mobile_nav')) { continue; }
			if (el.closest(DARK)) { return true; }
			if (el.closest(LIGHT)) { return false; }
			var l = luminance(getComputedStyle(el).backgroundColor);
			if (l !== null) { return l < 0.5; }
		}
		return false;
	}

	function update() {
		ticking = false;
		var bar = document.querySelector('.dd-hamburger-menu .mobile_menu_bar');
		if (!bar) { return; }
		if (root.classList.contains('ip-nav-open')) { return; }
		var r = bar.getBoundingClientRect();
		root.classList.toggle('ip-burger-white', isDarkAt(r.left + r.width / 2, r.top + r.height / 2));
	}

	function request() {
		if (!ticking) { ticking = true; requestAnimationFrame(update); }
	}

	// Vertikale Lage: Mitte des Symbols = Mitte des Logos bei Seitenanfang (berücksichtigt auch die Admin-Leiste).
	function align() {
		var logo = document.querySelector('.dd-hamburger-menu .et_pb_menu__logo img, .dd-hamburger-menu .et_pb_menu__logo-wrap img');
		var bar = document.querySelector('.dd-hamburger-menu .mobile_menu_bar');
		if (!logo || !bar || !logo.offsetHeight) { return; }
		var r = logo.getBoundingClientRect();
		var center = r.top + window.pageYOffset + r.height / 2;
		root.style.setProperty('--ip-burger-top', Math.round(center - bar.offsetHeight / 2) + 'px');
	}
	function alignAndUpdate() { align(); request(); }

	window.addEventListener('scroll', request, { passive: true });
	window.addEventListener('resize', alignAndUpdate);
	window.addEventListener('load', alignAndUpdate);
	document.addEventListener('DOMContentLoaded', alignAndUpdate);
	var logoImg = document.querySelector('.dd-hamburger-menu .et_pb_menu__logo img');
	if (logoImg && !logoImg.complete) { logoImg.addEventListener('load', alignAndUpdate); }
	new MutationObserver(request).observe(root, { attributes: true, attributeFilter: ['class'] });
	setTimeout(request, 300);
})();
