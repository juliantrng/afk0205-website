/* AFK Core – Hinweisband ausblenden (gilt für die aktuelle Browser-Sitzung) */
(function () {
	'use strict';

	function store(key, value) {
		try {
			if (value === undefined) {
				return window.sessionStorage.getItem(key);
			}
			window.sessionStorage.setItem(key, value);
		} catch (e) {
			return null;
		}
		return null;
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-afk-hinweis]'), function (bar) {
			var id = bar.getAttribute('data-afk-hinweis');
			if (store(id) === 'zu') {
				bar.hidden = true;
				return;
			}
			var btn = bar.querySelector('.afk-hinweis__close');
			if (btn) {
				btn.addEventListener('click', function () {
					bar.hidden = true;
					store(id, 'zu');
				});
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
