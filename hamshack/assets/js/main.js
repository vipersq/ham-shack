/* Ham Shack theme scripts – no dependencies. */
(function () {
	'use strict';

	var cfg = window.HAMSHACK || {};
	var pad = function (n) { return (n < 10 ? '0' : '') + n; };

	/* Clocks */
	var localFmt = null;
	try {
		localFmt = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: cfg.tz || undefined });
	} catch (e) {
		localFmt = null;
	}
	var clocks = document.querySelectorAll('[data-clock]');
	function tick() {
		var d = new Date();
		for (var i = 0; i < clocks.length; i++) {
			var el = clocks[i], type = el.getAttribute('data-clock');
			if (type === 'utc') {
				el.textContent = pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes());
			} else if (type === 'utc-sec') {
				el.textContent = pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds());
			} else if (type === 'local' && localFmt) {
				el.textContent = localFmt.format(d);
			}
		}
	}
	if (clocks.length) {
		tick();
		setInterval(tick, 1000);
	}

	/* Mobile navigation */
	var toggle = document.querySelector('.nav-toggle');
	var nav = document.getElementById('site-nav');
	if (toggle && nav) {
		toggle.addEventListener('click', function () {
			var open = toggle.getAttribute('aria-expanded') === 'true';
			toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
			document.body.classList.toggle('nav-open', !open);
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && document.body.classList.contains('nav-open')) {
				toggle.setAttribute('aria-expanded', 'false');
				document.body.classList.remove('nav-open');
				toggle.focus();
			}
		});
	}

	/* Sub-menu toggles on touch */
	var parents = document.querySelectorAll('.site-nav .menu-item-has-children > a');
	Array.prototype.forEach.call(parents, function (a) {
		var btn = document.createElement('button');
		btn.className = 'sub-toggle';
		btn.setAttribute('aria-expanded', 'false');
		btn.setAttribute('aria-label', 'Open sub-menu');
		btn.innerHTML = '<span aria-hidden="true">▾</span>';
		a.parentNode.insertBefore(btn, a.nextSibling);
		btn.addEventListener('click', function () {
			var li = btn.parentNode, open = li.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	});

	/* Header shadow on scroll */
	var header = document.querySelector('.site-header');
	if (header) {
		var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	/* Logbook search: force uppercase callsign */
	var callInput = document.getElementById('qso_call');
	if (callInput) {
		callInput.addEventListener('input', function () {
			var p = callInput.selectionStart;
			callInput.value = callInput.value.toUpperCase();
			callInput.setSelectionRange(p, p);
		});
	}
	/* N0NBH banners: retry once, then show a friendly fallback if hamqsl.com is unreachable */
	Array.prototype.forEach.call(document.querySelectorAll('.solar img'), function (img) {
		var tries = 0;
		function fail() {
			if (tries === 0) {
				tries++;
				setTimeout(function () { img.src = img.src.split('?')[0] + '?r=' + Date.now(); }, 4000);
				return;
			}
			var box = img.closest('.solar');
			if (box) { box.classList.add('is-offline'); }
		}
		img.addEventListener('error', fail);
		if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) { fail(); }
	});
	/* Live rig: refresh status pill + status card from the website every few seconds */
	var live = window.HAMSHACK_LIVE;
	if (live && live.url && window.fetch) {
		var busy = false;
		var poll = function () {
			if (busy || document.hidden) { return; }
			busy = true;
			fetch(live.url + (live.url.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now(), { credentials: 'omit', cache: 'no-store' })
				.then(function (r) { return r.ok ? r.json() : null; })
				.then(function (d) {
					if (!d) { return; }
					var swap = function (sel, html) {
						Array.prototype.forEach.call(document.querySelectorAll(sel), function (el) {
							if (el.outerHTML === html) { return; }
							var t = document.createElement('div');
							t.innerHTML = html.trim();
							var n = t.firstElementChild;
							if (!n) { return; }
							el.parentNode.replaceChild(n, el);
						});
					};
					swap('[data-hs-live="card"]', d.card);
					swap('.topbar [data-hs-live="pill"]', d.pill);
					clocks = document.querySelectorAll('[data-clock]');
					tick();
				})
				['catch'](function () {})
				.then(function () { busy = false; });
		};
		setInterval(poll, Math.max(5, live.interval || 10) * 1000);
		document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });
	}
})();

/* eQSL: turn the SVG card into a PNG download. */
(function () {
	'use strict';
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-qsl-png]') : null;
		if (!btn) { return; }
		e.preventDefault();
		var url = btn.getAttribute('data-qsl-png'), name = (btn.getAttribute('data-name') || 'qsl') + '.png';
		btn.disabled = true;
		fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (svg) {
			return new Promise(function (resolve, reject) {
				var img = new Image();
				img.onload = function () { resolve(img); };
				img.onerror = reject;
				img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
			});
		}).then(function (img) {
			var c = document.createElement('canvas');
			c.width = 1800; c.height = 1160;
			c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
			c.toBlob(function (blob) {
				var a = document.createElement('a');
				a.href = URL.createObjectURL(blob); a.download = name;
				document.body.appendChild(a); a.click(); a.remove();
				setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
				btn.disabled = false;
			}, 'image/png');
		}).catch(function () { btn.disabled = false; window.open(url, '_blank'); });
	});
}());

/* Lightbox for gallery photos and linked images in posts. */
(function () {
	'use strict';
	var box = null, items = [], idx = 0;
	function build() {
		box = document.createElement('div');
		box.className = 'hs-lightbox';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.innerHTML = '<button type="button" class="lb-close" aria-label="Close">×</button><button type="button" class="lb-prev" aria-label="Previous">‹</button><figure><img alt=""><figcaption></figcaption></figure><button type="button" class="lb-next" aria-label="Next">›</button>';
		document.body.appendChild(box);
		box.addEventListener('click', function (e) {
			if (e.target === box || e.target.classList.contains('lb-close')) { close(); }
			if (e.target.classList.contains('lb-prev')) { show(idx - 1); }
			if (e.target.classList.contains('lb-next')) { show(idx + 1); }
		});
		document.addEventListener('keydown', function (e) {
			if (!box.classList.contains('is-open')) { return; }
			if (e.key === 'Escape') { close(); } else if (e.key === 'ArrowLeft') { show(idx - 1); } else if (e.key === 'ArrowRight') { show(idx + 1); }
		});
	}
	function show(i) {
		idx = (i + items.length) % items.length;
		var a = items[idx], img = box.querySelector('img');
		img.src = a.href;
		img.alt = (a.querySelector('img') || {}).alt || '';
		box.querySelector('figcaption').textContent = a.getAttribute('data-caption') || img.alt || '';
		box.querySelector('.lb-prev').hidden = box.querySelector('.lb-next').hidden = items.length < 2;
	}
	function close() { box.classList.remove('is-open'); document.documentElement.style.overflow = ''; }
	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('a') : null;
		if (!a || !a.querySelector('img')) { return; }
		var isImg = /\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i.test(a.getAttribute('href') || '');
		if (!isImg || !(a.hasAttribute('data-lightbox') || a.closest('.entry-content'))) { return; }
		e.preventDefault();
		if (!box) { build(); }
		var group = a.closest('[data-hs-gallery], .wp-block-gallery, .gallery, .entry-content') || document;
		items = Array.prototype.filter.call(group.querySelectorAll('a'), function (x) {
			return x.querySelector('img') && /\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i.test(x.getAttribute('href') || '') && x.offsetParent !== null;
		});
		show(Math.max(0, items.indexOf(a)));
		box.classList.add('is-open');
		document.documentElement.style.overflow = 'hidden';
	});
	/* Gallery album filter */
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-hs-gallery] [data-album]') : null;
		if (!b || b.tagName !== 'BUTTON') { return; }
		var g = b.closest('[data-hs-gallery]'), al = b.getAttribute('data-album');
		g.querySelectorAll('.gallery-filter .chip-btn').forEach(function (x) { x.classList.toggle('is-on', x === b); });
		g.querySelectorAll('.g-item').forEach(function (f) { f.hidden = !!al && f.getAttribute('data-album') !== al; });
	});
}());

/* Contest scoreboard: refresh every minute without reloading the page. */
(function () {
	'use strict';
	if (!document.querySelector('[data-hs-contest]')) { return; }
	setInterval(function () {
		if (document.hidden) { return; }
		fetch(location.href, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.text(); }).then(function (html) {
			var doc = new DOMParser().parseFromString(html, 'text/html');
			var fresh = doc.querySelectorAll('[data-hs-contest]'), old = document.querySelectorAll('[data-hs-contest]');
			old.forEach(function (o, i) { if (fresh[i]) { o.replaceWith(document.importNode(fresh[i], true)); } });
		}).catch(function () {});
	}, 60000);
}());

/* Web SDR: load the receiver only when asked (it starts audio and uses bandwidth). */
(function () {
	'use strict';
	document.querySelectorAll('[data-hs-sdr]').forEach(function (app) {
		var btn = app.querySelector('[data-sdr-load]'), box = app.querySelector('[data-sdr-frame]'), src = app.getAttribute('data-src') || '';
		if (!btn || !box || !/^https?:\/\//i.test(src)) { return; }
		btn.addEventListener('click', function () {
			// Most receivers are plain http – a secure page cannot embed them, so open a window instead.
			if (location.protocol === 'https:' && /^http:/i.test(src)) { window.open(src, '_blank', 'noopener'); return; }
			if (!box.firstChild) {
				var f = document.createElement('iframe');
				f.src = src; f.title = 'Web SDR'; f.loading = 'lazy';
				f.setAttribute('allow', 'autoplay; microphone \'none\'');
				f.setAttribute('referrerpolicy', 'no-referrer');
				box.appendChild(f);
			}
			box.hidden = false; btn.hidden = true;
		});
	});
}());

/* Light / dark switch (the choice is remembered in this browser). */
(function () {
	'use strict';
	var root = document.documentElement;
	document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
		b.setAttribute('aria-pressed', root.getAttribute('data-theme') === 'light' ? 'true' : 'false');
		b.addEventListener('click', function () {
			var light = root.getAttribute('data-theme') !== 'light';
			if (light) { root.setAttribute('data-theme', 'light'); } else { root.removeAttribute('data-theme'); }
			b.setAttribute('aria-pressed', light ? 'true' : 'false');
			try { localStorage.setItem('hs-theme', light ? 'light' : 'dark'); } catch (e) {}
			document.dispatchEvent(new CustomEvent('m6:theme', { detail: { light: light } }));
		});
	});
}());
