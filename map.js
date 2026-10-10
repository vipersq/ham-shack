/* Ham Shack world-map engine + QSO map, greyline, PSK Reporter, FT8 and activation maps.
 * Equirectangular 1000×500 SVG; no external libraries. */
(function () {
	'use strict';
	var CFG = window.M6MAP || {};
	var M6 = window.M6 = window.M6 || {};
	var T = CFG.i18n || {};
	var NS = 'http://www.w3.org/2000/svg';
	var W = 1000, H = 500;
	var rad = Math.PI / 180;

	/* ---------- helpers ---------- */
	function el(name, attrs, parent) {
		var n = document.createElementNS(NS, name);
		if (attrs) { for (var k in attrs) { if (attrs[k] !== undefined && attrs[k] !== null) { n.setAttribute(k, attrs[k]); } } }
		if (parent) { parent.appendChild(n); }
		return n;
	}
	function h(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (text !== undefined && text !== null) { n.textContent = text; }
		return n;
	}
	function proj(lat, lon) { return [(lon + 180) / 360 * W, (90 - lat) / 180 * H]; }
	function fmt(n) { return Number(n).toLocaleString(); }
	function bandColor(b) { return (CFG.colors && CFG.colors[b]) || '#ffb020'; }
	function getJSON(url) {
		return fetch(url, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); });
	}
	M6.getJSON = getJSON;

	/* Great-circle path between two points, split at the antimeridian. */
	function gcPath(lat1, lon1, lat2, lon2, n) {
		n = n || 64;
		var p1 = [Math.cos(lat1 * rad) * Math.cos(lon1 * rad), Math.cos(lat1 * rad) * Math.sin(lon1 * rad), Math.sin(lat1 * rad)];
		var p2 = [Math.cos(lat2 * rad) * Math.cos(lon2 * rad), Math.cos(lat2 * rad) * Math.sin(lon2 * rad), Math.sin(lat2 * rad)];
		var dot = Math.max(-1, Math.min(1, p1[0] * p2[0] + p1[1] * p2[1] + p1[2] * p2[2]));
		var om = Math.acos(dot);
		if (om < 1e-6) { return ''; }
		var so = Math.sin(om), d = '', prevX = null;
		for (var i = 0; i <= n; i++) {
			var t = i / n, a = Math.sin((1 - t) * om) / so, b = Math.sin(t * om) / so;
			var x = a * p1[0] + b * p2[0], y = a * p1[1] + b * p2[1], z = a * p1[2] + b * p2[2];
			var la = Math.atan2(z, Math.sqrt(x * x + y * y)) / rad, lo = Math.atan2(y, x) / rad;
			var pt = proj(la, lo);
			d += (prevX === null || Math.abs(pt[0] - prevX) > W / 2 ? 'M' : 'L') + pt[0].toFixed(1) + ' ' + pt[1].toFixed(1);
			prevX = pt[0];
		}
		return d;
	}
	function distKm(lat1, lon1, lat2, lon2) {
		var dp = (lat2 - lat1) * rad, dl = (lon2 - lon1) * rad;
		var a = Math.sin(dp / 2) * Math.sin(dp / 2) + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dl / 2) * Math.sin(dl / 2);
		return 2 * 6371 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
	}
	function gridToLatLon(g) {
		g = String(g || '').toUpperCase();
		if (!/^[A-R]{2}[0-9]{2}([A-X]{2})?/.test(g)) { return null; }
		var lon = (g.charCodeAt(0) - 65) * 20 - 180 + (+g[2]) * 2, lat = (g.charCodeAt(1) - 65) * 10 - 90 + (+g[3]);
		if (g.length >= 6 && /[A-X]/.test(g[4])) { lon += (g.charCodeAt(4) - 65) / 12 + 1 / 24; lat += (g.charCodeAt(5) - 65) / 24 + 1 / 48; }
		else { lon += 1; lat += 0.5; }
		return [lat, lon];
	}
	function latLonToGrid4(lat, lon) {
		var x = lon + 180, y = Math.min(179.999, Math.max(0, lat + 90));
		return String.fromCharCode(65 + Math.floor(x / 20)) + String.fromCharCode(65 + Math.floor(y / 10)) + Math.floor((x % 20) / 2) + Math.floor(y % 10);
	}
	M6.gridToLatLon = gridToLatLon;
	M6.distKm = distKm;

	/* ---------- sun ---------- */
	function sun(date) {
		var d = date.getTime() / 86400000 + 2440587.5 - 2451545.0;
		var g = (357.529 + 0.98560028 * d) * rad;
		var q = 280.459 + 0.98564736 * d;
		var L = (q + 1.915 * Math.sin(g) + 0.020 * Math.sin(2 * g)) * rad;
		var e = (23.439 - 0.00000036 * d) * rad;
		var ra = Math.atan2(Math.cos(e) * Math.sin(L), Math.cos(L)) / rad / 15;
		var dec = Math.asin(Math.sin(e) * Math.sin(L)) / rad;
		var gmst = (18.697374558 + 24.06570982441908 * d) % 24;
		var lon = ((ra - gmst) * 15) % 360;
		if (lon > 180) { lon -= 360; } if (lon < -180) { lon += 360; }
		return { lat: dec, lon: lon };
	}
	function sunElev(s, lat, lon) {
		var v = Math.sin(lat * rad) * Math.sin(s.lat * rad) + Math.cos(lat * rad) * Math.cos(s.lat * rad) * Math.cos((lon - s.lon) * rad);
		return Math.asin(Math.max(-1, Math.min(1, v))) / rad;
	}
	function sunTimes(lat, lon, day) {
		var start = new Date(day); start.setUTCHours(0, 0, 0, 0);
		var res = { rise: null, set: null }, prev = null;
		for (var m = 0; m <= 1440; m += 2) {
			var t = new Date(start.getTime() + m * 60000), e = sunElev(sun(t), lat, lon) + 0.833;
			if (prev !== null) {
				if (prev < 0 && e >= 0 && !res.rise) { res.rise = t; }
				if (prev >= 0 && e < 0 && !res.set) { res.set = t; }
			}
			prev = e;
		}
		return res;
	}
	M6.sun = sun; M6.sunElev = sunElev;

	var nightCanvas = null;
	function nightImage(date) {
		var cw = 360, ch = 180;
		if (!nightCanvas) { nightCanvas = document.createElement('canvas'); nightCanvas.width = cw; nightCanvas.height = ch; }
		var ctx = nightCanvas.getContext('2d'), img = ctx.createImageData(cw, ch), s = sun(date), p = 0;
		for (var y = 0; y < ch; y++) {
			var lat = 90 - (y + 0.5) * 180 / ch;
			for (var x = 0; x < cw; x++) {
				var lon = (x + 0.5) * 360 / cw - 180, e = sunElev(s, lat, lon);
				var a = e > 0 ? 0 : Math.min(1, -e / 12) * 0.8;
				var t = (e < 1 && e > -9) ? Math.max(0, 1 - Math.abs(e + 4) / 5) * 0.28 : 0;
				var k = a + t > 0 ? t / (a + t) : 0;
				img.data[p++] = Math.round(2 + 253 * k); img.data[p++] = Math.round(5 + 171 * k); img.data[p++] = Math.round(12 + 20 * k); img.data[p++] = Math.round(Math.min(1, a + t) * 255);
			}
		}
		ctx.putImageData(img, 0, 0);
		return { url: nightCanvas.toDataURL(), sun: s };
	}

	/* ---------- world shape (shared) ---------- */
	var worldPromise = null;
	function loadWorld() {
		if (!worldPromise) {
			worldPromise = fetch(CFG.world).then(function (r) { return r.text(); }).then(function (txt) {
				return new DOMParser().parseFromString(txt, 'image/svg+xml').documentElement;
			}).catch(function () { return null; });
		}
		return worldPromise;
	}

	var worldHiPromise = null;
	function loadWorldHi() {
		if (!worldHiPromise) {
			worldHiPromise = fetch(String(CFG.world).replace('world.svg', 'world-hi.svg')).then(function (r) { return r.text(); }).then(function (txt) {
				return new DOMParser().parseFromString(txt, 'image/svg+xml').documentElement;
			}).catch(function () { return null; });
		}
		return worldHiPromise;
	}

	/* ---------- the map component ---------- */
	function WorldMap(host, opt) {
		opt = opt || {};
		var self = this;
		this.host = host;
		this.view = { x: 0, y: 0, w: W, h: H };
		host.classList.add('hs-map');
		var wrap = h('div', 'hs-map-frame');
		host.appendChild(wrap);
		var svg = this.svg = el('svg', { viewBox: '0 0 1000 500', 'class': 'hs-map-svg', role: 'img', 'aria-label': opt.label || 'World map', preserveAspectRatio: 'xMidYMid meet' }, wrap);
		el('rect', { x: -2000, y: -1000, width: 5000, height: 2500, 'class': 'wm-sea' }, svg);
		this.gGrat = el('g', { 'class': 'wm-grat' }, svg);
		for (var lo = -150; lo <= 150; lo += 30) { el('line', { x1: proj(0, lo)[0], y1: 0, x2: proj(0, lo)[0], y2: H }, this.gGrat); }
		for (var la = -60; la <= 60; la += 30) { el('line', { x1: 0, y1: proj(la, 0)[1], x2: W, y2: proj(la, 0)[1] }, this.gGrat); }
		this.gWorld = el('g', {}, svg);
		this.gNight = el('g', { 'class': 'wm-night' }, svg);
		this.gUnder = el('g', {}, svg);
		this.gLines = el('g', { 'class': 'wm-lines' }, svg);
		this.gPoints = el('g', { 'class': 'wm-points' }, svg);
		this.gTop = el('g', {}, svg);
		this.tip = h('div', 'hs-tip');
		this.tip.hidden = true;
		wrap.appendChild(this.tip);
		loadWorld().then(function (root) {
			if (!root || self.hiRes) { return; }
			Array.prototype.forEach.call(root.children, function (c) { self.gWorld.appendChild(document.importNode(c, true)); });
		});

		var ctr = h('div', 'hs-map-ctrl');
		[['+', T.zoomIn || 'Zoom in', function () { self.zoomBy(0.6); }], ['−', T.zoomOut || 'Zoom out', function () { self.zoomBy(1 / 0.6); }],
			['EU', T.europe || 'Europe', function () { self.fit(-25, 33, 45, 72); }], ['⟲', T.reset || 'World', function () { self.reset(); }]].forEach(function (b) {
			var bt = h('button', '', b[0]); bt.type = 'button'; bt.title = b[1]; bt.setAttribute('aria-label', b[1]);
			bt.addEventListener('click', b[2]); ctr.appendChild(bt);
		});
		wrap.appendChild(ctr);

		/* wheel zoom / drag / pinch */
		var pointers = {}, last = null, lastDist = 0;
		svg.addEventListener('wheel', function (e) {
			if (!e.ctrlKey && !self.hasFocus) { return; }
			e.preventDefault();
			var p = self.toSvg(e.clientX, e.clientY);
			self.zoomBy(e.deltaY > 0 ? 1.25 : 0.8, p);
		}, { passive: false });
		svg.addEventListener('pointerdown', function (e) {
			self.hasFocus = true;
			pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
			last = { x: e.clientX, y: e.clientY };
		});
		svg.addEventListener('pointermove', function (e) {
			if (!pointers[e.pointerId]) { return; }
			pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
			var ids = Object.keys(pointers);
			var r = svg.getBoundingClientRect(), k = self.view.w / r.width;
			if (ids.length === 2) {
				var a = pointers[ids[0]], b = pointers[ids[1]];
				var d = Math.hypot(a.x - b.x, a.y - b.y);
				if (lastDist) { self.zoomBy(lastDist / d, self.toSvg((a.x + b.x) / 2, (a.y + b.y) / 2)); }
				lastDist = d;
				return;
			}
			if (last && (Math.abs(e.clientX - last.x) + Math.abs(e.clientY - last.y) > 0)) {
				if (self.view.w < W) {
					self.pan(-(e.clientX - last.x) * k, -(e.clientY - last.y) * k);
					self.dragged = true;
				}
				last = { x: e.clientX, y: e.clientY };
			}
		});
		function up(e) { delete pointers[e.pointerId]; lastDist = 0; last = null; setTimeout(function () { self.dragged = false; }, 0); }
		svg.addEventListener('pointerup', up); svg.addEventListener('pointercancel', up); svg.addEventListener('pointerleave', up);
		svg.addEventListener('dblclick', function (e) { self.zoomBy(0.5, self.toSvg(e.clientX, e.clientY)); });
		document.addEventListener('pointerdown', function (e) { if (!host.contains(e.target)) { self.hasFocus = false; self.hideTip(); } });

		if (opt.home !== false && CFG.home) { this.addHome(); }
	}
	WorldMap.prototype.toSvg = function (cx, cy) {
		var r = this.svg.getBoundingClientRect();
		var scale = Math.min(r.width / this.view.w, r.height / this.view.h);
		var ox = (r.width - this.view.w * scale) / 2, oy = (r.height - this.view.h * scale) / 2;
		return [this.view.x + (cx - r.left - ox) / scale, this.view.y + (cy - r.top - oy) / scale];
	};
	WorldMap.prototype.apply = function () {
		var v = this.view;
		v.w = Math.max(30, Math.min(W, v.w)); v.h = v.w / 2;
		v.x = Math.max(0, Math.min(W - v.w, v.x)); v.y = Math.max(0, Math.min(H - v.h, v.y));
		this.svg.setAttribute('viewBox', v.x.toFixed(2) + ' ' + v.y.toFixed(2) + ' ' + v.w.toFixed(2) + ' ' + v.h.toFixed(2));
		var k = v.w / W, self = this;
		this.svg.style.setProperty('--k', k);
		var pts = this.svg.querySelectorAll('[data-r]');
		for (var i = 0; i < pts.length; i++) { pts[i].setAttribute('r', (+pts[i].getAttribute('data-r') * Math.max(k, 0.03)).toFixed(3)); }
		var txt = this.svg.querySelectorAll('[data-fs]');
		for (var j = 0; j < txt.length; j++) { txt[j].setAttribute('font-size', (+txt[j].getAttribute('data-fs') * Math.max(k, 0.03)).toFixed(3)); }
		var offs = this.svg.querySelectorAll('[data-ox]');
		for (var o = 0; o < offs.length; o++) {
			offs[o].setAttribute('x', (+offs[o].getAttribute('data-x0') + offs[o].getAttribute('data-ox') * k).toFixed(3));
			offs[o].setAttribute('y', (+offs[o].getAttribute('data-y0') + offs[o].getAttribute('data-oy') * k).toFixed(3));
		}
		if (v.w < 320 && !this.hiRes) {
			this.hiRes = true;
			loadWorldHi().then(function (root) {
				if (!root) { return; }
				self.gWorld.textContent = '';
				Array.prototype.forEach.call(root.children, function (c) { self.gWorld.appendChild(document.importNode(c, true)); });
			});
		}
	};
	WorldMap.prototype.zoomBy = function (f, at) {
		var v = this.view;
		at = at || [v.x + v.w / 2, v.y + v.h / 2];
		var nw = Math.max(30, Math.min(W, v.w * f)), r = nw / v.w;
		v.x = at[0] - (at[0] - v.x) * r; v.y = at[1] - (at[1] - v.y) * r; v.w = nw;
		this.hideTip(); this.apply();
	};
	WorldMap.prototype.pan = function (dx, dy) { this.view.x += dx; this.view.y += dy; this.hideTip(); this.apply(); };
	WorldMap.prototype.reset = function () { this.view = { x: 0, y: 0, w: W, h: H }; this.hideTip(); this.apply(); };
	WorldMap.prototype.fit = function (lon1, lat1, lon2, lat2) {
		var a = proj(lat2, lon1), b = proj(lat1, lon2);
		var w = Math.min(W, Math.max(30, Math.max(b[0] - a[0], (b[1] - a[1]) * 2) * 1.15));
		this.view = { w: w, h: w / 2, x: (a[0] + b[0]) / 2 - w / 2, y: (a[1] + b[1]) / 2 - w / 4 };
		this.apply();
	};
	WorldMap.prototype.centerOn = function (lat, lon, w) {
		var p = proj(lat, lon); w = Math.max(30, w || 120);
		this.view = { w: w, h: w / 2, x: p[0] - w / 2, y: p[1] - w / 4 }; this.apply();
	};
	WorldMap.prototype.dot = function (lat, lon, r, cls, parent) {
		var p = proj(lat, lon);
		var c = el('circle', { cx: p[0].toFixed(2), cy: p[1].toFixed(2), r: r, 'data-r': r, 'class': cls || '' }, parent || this.gPoints);
		return c;
	};
	WorldMap.prototype.line = function (lat1, lon1, lat2, lon2, cls, color) {
		var d = gcPath(lat1, lon1, lat2, lon2);
		if (!d) { return null; }
		return el('path', { d: d, 'class': cls || '', stroke: color || null }, this.gLines);
	};
	WorldMap.prototype.addHome = function () {
		var hm = CFG.home;
		var g = el('g', { 'class': 'wm-home' }, this.gTop);
		this.dot(hm.lat, hm.lon, 9, 'wm-home-ring', g);
		this.dot(hm.lat, hm.lon, 4.2, 'wm-home-dot', g);
		var p = proj(hm.lat, hm.lon);
		var t = el('text', { x: (p[0] + 7).toFixed(1), y: (p[1] - 7).toFixed(1), 'data-x0': p[0], 'data-y0': p[1], 'data-ox': 7, 'data-oy': -7, 'data-fs': 11, 'font-size': 11, 'class': 'wm-home-label' }, g);
		t.textContent = hm.call;
	};
	WorldMap.prototype.clear = function () { this.gLines.textContent = ''; this.gPoints.textContent = ''; this.gUnder.textContent = ''; this.hideTip(); };
	WorldMap.prototype.night = function (on) {
		var self = this;
		clearInterval(this.nightTimer);
		this.gNight.textContent = '';
		if (!on) { return; }
		function draw() {
			var n = nightImage(new Date());
			self.gNight.textContent = '';
			el('image', { href: n.url, x: 0, y: 0, width: W, height: H, preserveAspectRatio: 'none' }, self.gNight);
			var sp = proj(n.sun.lat, n.sun.lon);
			var g = el('g', { 'class': 'wm-sun' }, self.gNight);
			el('circle', { cx: sp[0], cy: sp[1], r: 7, 'data-r': 7 }, g);
			self.sunPos = n.sun;
			if (self.onNight) { self.onNight(n.sun); }
			self.apply();
		}
		draw();
		this.nightTimer = setInterval(draw, 60000);
	};
	WorldMap.prototype.tipAt = function (target, nodes) {
		var tip = this.tip;
		tip.textContent = '';
		nodes.forEach(function (n) { tip.appendChild(n); });
		tip.hidden = false;
		var fr = this.host.querySelector('.hs-map-frame').getBoundingClientRect(), r = target.getBoundingClientRect();
		var x = r.left + r.width / 2 - fr.left, y = r.top - fr.top;
		tip.style.left = Math.max(8, Math.min(fr.width - tip.offsetWidth - 8, x - tip.offsetWidth / 2)) + 'px';
		tip.style.top = (y - tip.offsetHeight - 10 < 4 ? y + r.height + 10 : y - tip.offsetHeight - 10) + 'px';
	};
	WorldMap.prototype.hideTip = function () { if (this.tip) { this.tip.hidden = true; } };
	WorldMap.prototype.bindTip = function (node, fn) {
		var self = this;
		node.addEventListener('mouseenter', function () { self.tipAt(node, fn()); });
		node.addEventListener('mouseleave', function () { self.hideTip(); });
		node.addEventListener('click', function (e) { e.stopPropagation(); if (!self.dragged) { self.tipAt(node, fn()); } });
	};
	M6.WorldMap = WorldMap;
	M6.proj = proj;
	M6.el = el;

	function tipLines(title, lines) {
		var out = [h('strong', 'mono', title)];
		lines.forEach(function (l) { if (l) { out.push(h('span', '', l)); } });
		return out;
	}
	M6.tipLines = tipLines;

	/* =========================================================
	 * QSO map
	 * ======================================================= */
	function qsoMap(app) {
		var map = new WorldMap(app.querySelector('[data-hs-map]'), { label: 'QSO map' });
		var state = { band: '', mode: '', lines: true, view: 'points', call: '' }, data = [];
		var summary = app.querySelector('[data-summary]'), legend = app.querySelector('[data-legend]');
		map.host.classList.add('is-loading');
		getJSON(CFG.rest + 'map').then(function (res) { data = res.qsos || []; map.host.classList.remove('is-loading'); render(); }).catch(function () { map.host.classList.remove('is-loading'); summary.textContent = T.empty; });

		function visible() {
			return data.filter(function (q) {
				return (!state.band || q.b.indexOf(state.band) > -1) && (!state.mode || q.m.indexOf(state.mode) > -1);
			});
		}
		function render() {
			map.clear();
			var hm = CFG.home, rows = visible(), countries = {}, far = null, bands = {};
			if (state.view === 'grids') { renderGrids(rows); }
			rows.sort(function (a, b) { return a.km - b.km; });
			rows.forEach(function (q) {
				countries[q.co] = 1;
				var band = state.band || q.b[q.b.length - 1] || '';
				q.b.forEach(function (b) { bands[b] = (bands[b] || 0) + 1; });
				if (q.p && (!far || q.km > far.km)) { far = q; }
				if (state.view !== 'points') { return; }
				var col = bandColor(band);
				if (state.lines && q.km > 30) {
					var ln = map.line(hm.lat, hm.lon, q.la, q.lo, 'wm-path', col);
					if (ln) { ln.setAttribute('data-call', q.c); }
				}
				var c = map.dot(q.la, q.lo, q.p ? 3.6 : 3, 'wm-qso' + (q.p ? '' : ' is-approx') + (q.q ? ' is-cfm' : ''));
				c.setAttribute('fill', col);
				c.setAttribute('data-call', q.c);
				map.bindTip(c, function () {
					return tipLines(q.c, [
						q.co + (q.g ? ' · ' + q.g : ''),
						fmt(q.km) + ' ' + T.km + ' · ' + q.n + ' ' + T.qsos,
						q.b.join(', ') + ' · ' + q.m.join(', '),
						T.last + ': ' + q.d + (q.q ? ' · ✔ ' + T.confirmed : ''),
						q.p ? '' : '≈ ' + T.approx
					]);
				});
			});
			map.apply();
			summary.textContent = fmt(rows.length) + ' ' + (T.stations || 'stations') + ' · ' + Object.keys(countries).length + ' DXCC' + (far ? ' · ' + (T.farthest || 'farthest') + ': ' + far.c + ' ' + fmt(far.km) + ' ' + T.km : '');
			legend.textContent = '';
			Object.keys(bands).sort(function (a, b) { return CFG.bands.indexOf(a) - CFG.bands.indexOf(b); }).forEach(function (b) {
				var s = h('span', 'lg'); var i = h('i'); i.style.background = bandColor(b); s.appendChild(i); s.appendChild(document.createTextNode(b));
				legend.appendChild(s);
			});
			var top = app.querySelector('[data-top]'), list = app.querySelector('[data-dx]');
			if (top && list) {
				list.textContent = '';
				rows.filter(function (q) { return q.p; }).slice(-10).reverse().forEach(function (q) {
					var li = h('li'); li.appendChild(h('span', 'mono call', q.c)); li.appendChild(h('span', 'muted', q.co)); li.appendChild(h('span', 'mono', fmt(q.km) + ' ' + T.km));
					li.addEventListener('click', function () { map.centerOn(q.la, q.lo, 160); });
					list.appendChild(li);
				});
				top.hidden = !list.children.length;
			}
			if (state.call) { findCall(state.call); }
		}
		function renderGrids(rows) {
			var grids = {};
			rows.forEach(function (q) {
				if (!q.p) { return; }
				var g4 = q.g && q.g.length >= 4 ? q.g.substr(0, 4).toUpperCase() : latLonToGrid4(q.la, q.lo);
				grids[g4] = (grids[g4] || 0) + 1;
			});
			var max = 1; for (var k in grids) { max = Math.max(max, grids[k]); }
			var fields = {};
			Object.keys(grids).forEach(function (g) {
				var lon = (g.charCodeAt(0) - 65) * 20 - 180 + (+g[2]) * 2, lat = (g.charCodeAt(1) - 65) * 10 - 90 + (+g[3]);
				fields[g.substr(0, 2)] = 1;
				var a = proj(lat + 1, lon);
				var r = el('rect', { x: a[0].toFixed(2), y: a[1].toFixed(2), width: (2 / 360 * W).toFixed(3), height: (1 / 180 * H).toFixed(3), 'class': 'wm-grid' }, map.gUnder);
				r.style.opacity = (0.45 + 0.55 * Math.sqrt(grids[g] / max)).toFixed(2);
				map.bindTip(r, function () { return tipLines(g, [grids[g] + ' ' + T.stations]); });
			});
			Object.keys(fields).forEach(function (f) {
				var lon = (f.charCodeAt(0) - 65) * 20 - 180, lat = (f.charCodeAt(1) - 65) * 10 - 90, a = proj(lat + 10, lon);
				el('rect', { x: a[0], y: a[1], width: 20 / 360 * W, height: 10 / 180 * H, 'class': 'wm-field' }, map.gUnder);
				var t = el('text', { x: a[0] + 2, y: a[1] + 8, 'data-x0': a[0], 'data-y0': a[1], 'data-ox': 2, 'data-oy': 8, 'data-fs': 7, 'font-size': 7, 'class': 'wm-field-label' }, map.gUnder);
				t.textContent = f;
			});
			summary.setAttribute('data-grids', Object.keys(grids).length);
		}
		function findCall(c) {
			c = c.toUpperCase();
			var hit = null;
			map.svg.querySelectorAll('.is-hit').forEach(function (n) { n.classList.remove('is-hit'); });
			data.forEach(function (q) { if (!hit && q.c === c) { hit = q; } });
			if (!hit) { data.forEach(function (q) { if (!hit && q.c.indexOf(c) === 0) { hit = q; } }); }
			if (hit) {
				map.centerOn(hit.la, hit.lo, 140);
				map.svg.querySelectorAll('[data-call="' + hit.c.replace(/"/g, '') + '"]').forEach(function (n) { n.classList.add('is-hit'); });
				var dot = map.svg.querySelector('circle[data-call="' + hit.c.replace(/"/g, '') + '"]');
				if (dot) { dot.dispatchEvent(new Event('click')); }
			}
		}
		app.querySelectorAll('[data-view]').forEach(function (b) {
			b.addEventListener('click', function () {
				app.querySelectorAll('[data-view]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				state.view = b.getAttribute('data-view'); render();
			});
		});
		app.querySelectorAll('[data-filter]').forEach(function (f) {
			var key = f.getAttribute('data-filter');
			f.addEventListener(f.type === 'search' ? 'input' : 'change', function () {
				if (key === 'lines') { state.lines = f.checked; render(); }
				else if (key === 'night') { map.night(f.checked); }
				else if (key === 'call') { state.call = f.value.trim(); if (state.call.length >= 3) { findCall(state.call); } }
				else { state[key] = f.value; render(); }
			});
		});
	}

	/* =========================================================
	 * Greyline / clocks
	 * ======================================================= */
	function greyline(app) {
		var map = new WorldMap(app.querySelector('[data-hs-map]'), { label: 'Day and night map' });
		var clockU = app.querySelector('[data-clock-utc]'), clockL = app.querySelector('[data-clock-local]');
		var rise = app.querySelector('[data-sunrise]'), set = app.querySelector('[data-sunset]'), sunEl = app.querySelector('[data-sun]');
		var tz = (window.HAMSHACK && HAMSHACK.tz) || undefined;
		function hm(d, utc) {
			try { return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: utc ? 'UTC' : tz }); } catch (e) { return d.toISOString().substr(11, 5); }
		}
		function tick() {
			var now = new Date();
			if (clockU) { clockU.textContent = hm(now, true) + ':' + String(now.getUTCSeconds()).padStart(2, '0'); }
			if (clockL) { clockL.textContent = hm(now, false); }
		}
		map.onNight = function (s) {
			var t = sunTimes(CFG.home.lat, CFG.home.lon, new Date());
			if (rise) { rise.textContent = t.rise ? hm(t.rise, false) + ' (' + hm(t.rise, true) + 'Z)' : '—'; }
			if (set) { set.textContent = t.set ? hm(t.set, false) + ' (' + hm(t.set, true) + 'Z)' : '—'; }
			if (sunEl) { sunEl.textContent = s.lat.toFixed(1) + '°, ' + s.lon.toFixed(1) + '°'; }
		};
		map.night(true);
		tick(); setInterval(tick, 1000);
	}

	/* =========================================================
	 * PSK Reporter
	 * ======================================================= */
	function psk(app) {
		var map = new WorldMap(app.querySelector('[data-hs-map]'), { label: 'PSK Reporter map' });
		var list = app.querySelector('[data-psk-list]'), sum = app.querySelector('[data-summary]');
		map.night(true);
		function load() {
			getJSON(CFG.rest + 'psk').then(function (res) {
				map.clear();
				var spots = res.spots || [], hm = CFG.home;
				if (sum) { sum.textContent = res.message || (spots.length + ' ' + T.stations + (res.updated ? ' · ' + new Date(res.updated * 1000).toISOString().substr(11, 5) + 'Z' : '')); }
				spots.forEach(function (s) {
					var ll = gridToLatLon(s.grid); if (!ll) { return; }
					var col = bandColor(s.band);
					map.line(hm.lat, hm.lon, ll[0], ll[1], 'wm-path', col);
					var c = map.dot(ll[0], ll[1], 3.4, 'wm-qso'); c.setAttribute('fill', col);
					map.bindTip(c, function () {
						return tipLines(s.call, [s.grid + ' · ' + fmt(s.km) + ' ' + T.km, s.band + ' ' + s.mode + ' · ' + T.snr + ' ' + s.snr + ' dB', s.time + 'Z']);
					});
				});
				map.apply();
				if (list) {
					list.textContent = '';
					spots.slice().sort(function (a, b) { return b.km - a.km; }).slice(0, 12).forEach(function (s) {
						var li = h('li'); li.appendChild(h('span', 'mono call', s.call)); li.appendChild(h('span', 'muted', s.band + ' · ' + s.snr + ' dB')); li.appendChild(h('span', 'mono', fmt(s.km) + ' ' + T.km)); list.appendChild(li);
					});
				}
			}).catch(function () { if (sum) { sum.textContent = T.empty; } });
		}
		load(); setInterval(load, 5 * 60000);
	}

	/* =========================================================
	 * Activations map
	 * ======================================================= */
	function activations(app) {
		var map = new WorldMap(app.querySelector('[data-hs-map]'), { label: 'Activations map' });
		var items = [];
		try { items = JSON.parse(app.getAttribute('data-items') || '[]'); } catch (e) { items = []; }
		items.forEach(function (a) {
			var c = map.dot(a.lat, a.lon, 5, 'wm-act wm-act-' + String(a.prog || '').toLowerCase());
			map.bindTip(c, function () { return tipLines(a.ref, [a.name, a.prog + ' · ' + a.date, a.qsos ? a.qsos + ' ' + T.qsos : '']); });
			if (a.url) { c.addEventListener('dblclick', function () { location.href = a.url; }); }
		});
		if (items.length) {
			var la = items.map(function (a) { return a.lat; }), lo = items.map(function (a) { return a.lon; });
			var pad = 1.5;
			map.fit(Math.min.apply(null, lo) - pad, Math.min.apply(null, la) - pad, Math.max.apply(null, lo) + pad, Math.max.apply(null, la) + pad);
		} else { map.fit(-12, 49, 4, 60); }
	}

	M6.registerMap = function (sel, fn) { document.querySelectorAll(sel).forEach(fn); };
	function boot() {
		M6.registerMap('[data-hs-qsomap]', qsoMap);
		M6.registerMap('[data-hs-greyline]', greyline);
		M6.registerMap('[data-hs-psk]', psk);
		M6.registerMap('[data-hs-activations]', activations);
		document.dispatchEvent(new CustomEvent('m6map:ready'));
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
