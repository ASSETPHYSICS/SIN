/* Disclaimer „Second Opinions“: einmal pro Browser-Sitzung bestätigen */
(function () {
	'use strict';
	var box = document.getElementById('ip-disclaimer');
	if (!box) { return; }
	var KEY = 'ip-secop-disclaimer', ok = false;
	try { ok = sessionStorage.getItem(KEY) === '1'; } catch (e) {}
	if (ok) { return; }
	var btn = document.getElementById('ip-disclaimer-ok');
	box.hidden = false;
	document.documentElement.classList.add('ip-disclaimer-open');
	if (btn) { btn.focus(); }
	function close() {
		try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
		box.hidden = true;
		document.documentElement.classList.remove('ip-disclaimer-open');
	}
	if (btn) { btn.addEventListener('click', close); }
})();
