/* ServiceInvest – Kennzahlen („100 %“): beim Hineinscrollen versetzt einblenden und hochzählen */
(function () {
	'use strict';
	var figs = document.querySelectorAll('.ip-figure');
	if (!figs.length || !('IntersectionObserver' in window)) { return; }
	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

	var STAGGER = 150, DURATION = 1400;

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

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (!entry.isIntersecting) { return; }
			var fig = entry.target, delay = parseInt(fig.getAttribute('data-ip-delay'), 10) || 0;
			observer.unobserve(fig);
			fig.classList.add('ip-in');
			var value = fig.querySelector('.ip-figure__value');
			if (value) { setTimeout(function () { countUp(value); }, delay); }
		});
	}, { threshold: 0.35 });

	Array.prototype.forEach.call(figs, function (fig, i) {
		var delay = (i % 3) * STAGGER;
		fig.setAttribute('data-ip-delay', delay);
		fig.style.transitionDelay = delay + 'ms';
		fig.classList.add('ip-reveal');
		observer.observe(fig);
	});
})();
