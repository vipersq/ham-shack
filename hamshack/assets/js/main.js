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
