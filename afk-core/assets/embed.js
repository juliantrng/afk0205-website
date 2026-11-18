/* AFK Core – eingebettete Inhalte erst nach Klick laden */
(function () {
	'use strict';
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.afk-embed__btn') : null;
		if (!btn) { return; }
		var box = btn.closest('.afk-embed');
		var tpl = box && box.querySelector('template.afk-embed__inhalt');
		if (!tpl) { return; }
		var html = tpl.innerHTML.replace(/<afk-script/g, '<script').replace(/<\/afk-script>/g, '</script>');
		var wrap = document.createElement('div');
		wrap.className = 'afk-embed__geladen';
		wrap.innerHTML = html;
		// Skripte aktiv machen (innerHTML führt sie nicht aus)
		Array.prototype.forEach.call(wrap.querySelectorAll('script'), function (old) {
			var s = document.createElement('script');
			Array.prototype.forEach.call(old.attributes, function (a) { s.setAttribute(a.name, a.value); });
			s.text = old.text;
			old.parentNode.replaceChild(s, old);
		});
		box.replaceWith(wrap);
	});
})();
