/**
 * AFK Nachtschicht – eigene Lightbox für Fotos in Berichten.
 *
 * Erfasst verlinkte Bilder (Link auf die Bilddatei) und Galerie-Bilder ohne Link im Text eines
 * Beitrags. Alle Fotos eines Beitrags bilden eine Reihe: Pfeile, Tastatur (← → Esc),
 * Wischen am Handy, Zähler, Bildunterschrift. Keine Abhängigkeiten, keine Verbindungen zu Dritten.
 */
(function () {
	'use strict';

	var BILD = /\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i;

	function groesstesBild(img) {
		var set = img.getAttribute('srcset');
		if (!set) { return img.currentSrc || img.src; }
		var best = null, bestW = 0;
		set.split(',').forEach(function (teil) {
			var t = teil.trim().split(/\s+/);
			var w = parseInt(t[1], 10) || 0;
			if (w > bestW) { bestW = w; best = t[0]; }
		});
		return best || img.src;
	}

	function unterschrift(el) {
		var fig = el.closest('figure, .gallery-item');
		var cap = fig && fig.querySelector('figcaption, .gallery-caption');
		var text = cap ? cap.textContent.trim() : '';
		if (!text) {
			var img = el.tagName === 'IMG' ? el : el.querySelector('img');
			text = img ? (img.getAttribute('alt') || '').trim() : '';
		}
		return text;
	}

	function sammeln(entry) {
		var liste = [];
		entry.querySelectorAll('a[href] img, .wp-block-gallery img, .gallery img').forEach(function (img) {
			var a = img.closest('a[href]');
			var src;
			if (a) {
				if (!BILD.test(a.getAttribute('href'))) { return; } // Link auf Anhangseite o. Ä. – nicht anfassen
				src = a.href;
			} else {
				src = groesstesBild(img);
			}
			var ausloeser = a || img;
			if (liste.some(function (x) { return x.el === ausloeser; })) { return; }
			liste.push({ el: ausloeser, src: src, text: unterschrift(ausloeser), thumb: img.currentSrc || img.src });
		});
		return liste;
	}

	var box, bildEl, zaehler, textEl, liste = [], pos = 0, vorher = null, startX = null, startY = null;

	function bauen() {
		box = document.createElement('div');
		box.className = 'lb';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.setAttribute('aria-label', 'Fotos');
		box.hidden = true;
		box.innerHTML =
			'<div class="lb__kopf"><span class="lb__zaehler" aria-live="polite"></span>' +
			'<button class="lb__zu" type="button" aria-label="Schließen"><svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></button></div>' +
			'<button class="lb__pfeil lb__pfeil--zurueck" type="button" aria-label="Vorheriges Foto"><svg width="26" height="26" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4l-8 8 8 8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
			'<figure class="lb__buehne"><img class="lb__bild" alt=""><figcaption class="lb__text"></figcaption></figure>' +
			'<button class="lb__pfeil lb__pfeil--weiter" type="button" aria-label="Nächstes Foto"><svg width="26" height="26" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4l8 8-8 8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
		document.body.appendChild(box);
		bildEl = box.querySelector('.lb__bild');
		zaehler = box.querySelector('.lb__zaehler');
		textEl = box.querySelector('.lb__text');

		box.querySelector('.lb__zu').addEventListener('click', schliessen);
		box.querySelector('.lb__pfeil--zurueck').addEventListener('click', function () { zeigen(pos - 1); });
		box.querySelector('.lb__pfeil--weiter').addEventListener('click', function () { zeigen(pos + 1); });
		box.addEventListener('click', function (e) {
			if (e.target === box || e.target.classList.contains('lb__buehne')) { schliessen(); }
		});
		box.addEventListener('touchstart', function (e) {
			if (e.touches.length === 1) { startX = e.touches[0].clientX; startY = e.touches[0].clientY; }
		}, { passive: true });
		box.addEventListener('touchend', function (e) {
			if (startX === null) { return; }
			var dx = e.changedTouches[0].clientX - startX, dy = e.changedTouches[0].clientY - startY;
			startX = null;
			if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) { zeigen(pos + (dx < 0 ? 1 : -1)); }
			else if (dy > 90 && Math.abs(dy) > Math.abs(dx)) { schliessen(); }
		});
		document.addEventListener('keydown', function (e) {
			if (box.hidden) { return; }
			if (e.key === 'Escape') { schliessen(); }
			else if (e.key === 'ArrowLeft') { zeigen(pos - 1); }
			else if (e.key === 'ArrowRight') { zeigen(pos + 1); }
			else if (e.key === 'Tab') {
				// Fokus im Dialog halten.
				var f = box.querySelectorAll('button:not([hidden])');
				if (!f.length) { return; }
				var erst = f[0], letzt = f[f.length - 1];
				if (e.shiftKey && document.activeElement === erst) { letzt.focus(); e.preventDefault(); }
				else if (!e.shiftKey && document.activeElement === letzt) { erst.focus(); e.preventDefault(); }
			}
		});
	}

	function vorladen(i) {
		if (liste[i]) { var im = new Image(); im.src = liste[i].src; }
	}

	function zeigen(i) {
		if (!liste.length) { return; }
		pos = (i + liste.length) % liste.length;
		var it = liste[pos];
		box.classList.add('lb--laedt');
		bildEl.onload = function () { box.classList.remove('lb--laedt'); };
		bildEl.src = it.thumb; // sofort etwas zeigen …
		var gross = new Image();
		gross.onload = function () { if (liste[pos] === it) { bildEl.src = it.src; box.classList.remove('lb--laedt'); } };
		gross.src = it.src; // … dann in voller Größe
		bildEl.alt = it.text;
		textEl.textContent = it.text;
		textEl.hidden = !it.text;
		zaehler.textContent = liste.length > 1 ? (pos + 1) + ' / ' + liste.length : '';
		box.querySelectorAll('.lb__pfeil').forEach(function (b) { b.hidden = liste.length < 2; });
		vorladen(pos + 1);
		vorladen(pos - 1);
	}

	function oeffnen(l, i) {
		if (!box) { bauen(); }
		liste = l;
		vorher = document.activeElement;
		box.hidden = false;
		document.documentElement.classList.add('lb-offen');
		zeigen(i);
		box.querySelector('.lb__zu').focus();
	}

	function schliessen() {
		box.hidden = true;
		bildEl.removeAttribute('src');
		document.documentElement.classList.remove('lb-offen');
		if (vorher && vorher.focus) { vorher.focus(); }
	}

	document.querySelectorAll('.entry').forEach(function (entry) {
		var l = sammeln(entry);
		l.forEach(function (it, i) {
			var el = it.el;
			if (el.tagName === 'IMG') {
				el.setAttribute('tabindex', '0');
				el.setAttribute('role', 'button');
				el.setAttribute('aria-label', 'Foto vergrößern' + (it.text ? ': ' + it.text : ''));
			}
			el.classList.add('lb-ausloeser');
			el.addEventListener('click', function (e) { e.preventDefault(); oeffnen(l, i); });
			el.addEventListener('keydown', function (e) {
				if (el.tagName === 'IMG' && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); oeffnen(l, i); }
			});
		});
	});
})();
