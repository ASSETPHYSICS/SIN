/* ServiceInvest – Scroll-Animation: Inhalte der Sections blenden beim Hineinscrollen von unten ein;
   Kennzahlen („100 %“) zählen dabei hoch. Hero, Footer und Rechtstexte bleiben unverändert. */
(function () {
	'use strict';
	if (!('IntersectionObserver' in window)) { return; }
	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
	if (/[?&]et_fb=1/.test(window.location.search)) { return; }   // nicht im Divi-Builder

	var STAGGER = 110, DURATION = 1400;
	var SEL = [
		'.ip-sec:not(.ip-legal) .ip-wrap > :not(.ip-values):not(.ip-figures)',
		'.ip-sec:not(.ip-legal) .ip-values > article',
		'.ip-sec:not(.ip-legal) .ip-figure'
	].join(',');

	function countUp(el) {
		var m = el.textContent.match(/^(\D*)(\d+)(.*)$/);
		if (!m) { return; }
		var target = parseInt(m[2], 10), start = Date.now(), done = false;
		function show(n) { el.textContent = m[1] + n + m[3]; }
		function step() {
			if (done) { return; }
			var p = Math.min((Date.now() - start) / DURATION, 1);
			show(Math.round(target * (1 - Math.pow(1 - p, 3))));
			if (p < 1) { setTimeout(step, 30); } else { done = true; }
		}
		// Endwert in jedem Fall setzen (falls Timer gedrosselt werden, z. B. im Hintergrund-Tab)
		setTimeout(function () { done = true; show(target); }, DURATION + 300);
		show(0);
		step();
	}

	var all = Array.prototype.slice.call(document.querySelectorAll(SEL));
	var items = all.filter(function (el) {   // keine verschachtelten Animationen
		for (var p = el.parentElement; p; p = p.parentElement) { if (all.indexOf(p) !== -1) { return false; } }
		return true;
	});
	if (!items.length) { return; }

	var observer = new IntersectionObserver(function (entries) {
		var n = 0;
		entries.forEach(function (entry) {
			if (!entry.isIntersecting) { return; }
			var el = entry.target, delay = (n++ % 4) * STAGGER;
			observer.unobserve(el);
			el.style.transitionDelay = delay + 'ms';
			el.classList.add('ip-in');
			var value = el.classList.contains('ip-figure') ? el.querySelector('.ip-figure__value') : null;
			if (value) { setTimeout(function () { countUp(value); }, delay); }
		});
	}, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

	items.forEach(function (el) {
		el.classList.add('ip-reveal');
		observer.observe(el);
	});
})();
