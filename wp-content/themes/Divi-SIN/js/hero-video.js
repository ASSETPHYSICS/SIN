/* ServiceInvest – Hintergrundvideo im Header: optionale Geschwindigkeit (data-rate, Standard 1),
   bei „Bewegung reduzieren“ nur das Vorschaubild */
(function () {
	'use strict';
	var videos = document.querySelectorAll('.ip-hero-video__media');
	if (!videos.length) { return; }
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	Array.prototype.forEach.call(videos, function (v) {
		if (reduce) {
			v.removeAttribute('autoplay');
			v.pause();
			return;
		}
		var rate = parseFloat(v.getAttribute('data-rate')) || 1;
		function apply() { v.defaultPlaybackRate = rate; v.playbackRate = rate; }
		apply();
		// Manche Browser setzen die Geschwindigkeit beim Laden bzw. bei jeder Wiederholung zurück
		v.addEventListener('loadedmetadata', apply);
		v.addEventListener('play', apply);
		v.addEventListener('ratechange', function () { if (Math.abs(v.playbackRate - rate) > 0.001) { apply(); } });
		var p = v.play();
		if (p && p.catch) { p.catch(function () {}); }   // Autoplay blockiert: Vorschaubild bleibt stehen
	});
})();
