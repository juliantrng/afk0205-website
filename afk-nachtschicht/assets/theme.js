/* AFK Nachtschicht – Menü, Filter, Teilen. Keine externen Dienste. */
(function () {
	'use strict';

	// Mobiles Menü
	var toggle = document.querySelector('.nav-toggle');
	var nav = document.querySelector('.nav');
	if (toggle && nav) {
		toggle.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && nav.classList.contains('is-open')) {
				nav.classList.remove('is-open');
				toggle.setAttribute('aria-expanded', 'false');
				toggle.focus();
			}
		});
		nav.addEventListener('click', function (e) {
			if (e.target.closest('.nav__list a')) {
				nav.classList.remove('is-open');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	// Filter: Auswahllisten schicken sofort ab, leere Werte fliegen aus der Adresse
	document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
		sel.addEventListener('change', function () {
			if (sel.form) {
				if (sel.form.requestSubmit) { sel.form.requestSubmit(); } else { sel.form.submit(); }
			}
		});
	});
	document.querySelectorAll('form.filters').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			var params = new URLSearchParams();
			var data = new FormData(form);
			if (e.submitter && e.submitter.name) {
				data.set(e.submitter.name, e.submitter.value);
			}
			data.forEach(function (v, k) { if (v !== '') { params.set(k, v); } });
			e.preventDefault();
			var action = form.getAttribute('action') || location.pathname;
			var hash = '';
			var i = action.indexOf('#');
			if (i > -1) { hash = action.slice(i); action = action.slice(0, i); }
			var qs = params.toString();
			location.href = action + (qs ? (action.indexOf('?') > -1 ? '&' : '?') + qs : '') + hash;
		});
	});

	// Teilen: Systemdialog, sonst Link kopieren
	document.querySelectorAll('[data-share]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var data = { title: btn.getAttribute('data-title') || document.title, url: location.href.split('#')[0] };
			if (navigator.share) {
				navigator.share(data).catch(function () {});
				return;
			}
			if (navigator.clipboard) {
				navigator.clipboard.writeText(data.url).then(function () {
					var old = btn.textContent;
					btn.textContent = 'Link kopiert ✓';
					setTimeout(function () { btn.textContent = old; }, 2000);
				});
			}
		});
	});
})();
