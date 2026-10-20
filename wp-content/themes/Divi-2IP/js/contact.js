/* Startseite: Kontaktformular hinter dem Link „Kontaktanfrage“ ein-/ausklappen.
   Ohne JavaScript bleibt das Formular sichtbar. */
(function () {
	'use strict';
	var link = document.getElementById('ip-contact-toggle');
	var panel = document.getElementById('ip-contact-form');
	if (!link || !panel) { return; }
	function set(open) {
		panel.hidden = !open;
		link.setAttribute('aria-expanded', open ? 'true' : 'false');
		link.classList.toggle('is-open', open);
	}
	link.addEventListener('click', function (e) {
		e.preventDefault();
		set(panel.hidden);
	});
	set(location.hash === '#kontaktanfrage');
	window.addEventListener('hashchange', function () { if (location.hash === '#kontaktanfrage') { set(true); } });
})();
