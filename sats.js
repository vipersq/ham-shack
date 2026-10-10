/* Ham Shack satellite passes – SGP4 via satellite.js, drawn on the M6 world map. */
(function () {
	'use strict';
	var S = window.M6SATS || {}, T = S.i18n || {}, M6 = window.M6 || {}, sat = window.satellite;
	var rad = Math.PI / 180, deg = 180 / Math.PI, RE = 6371;
	var COLORS = ['#22d3ee', '#ffb020', '#39ff88', '#f472b6', '#a78bfa', '#60a5fa', '#fb923c', '#facc15', '#2dd4bf', '#f87171'];

	function h(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text !== undefined && text !== null) { n.textContent = text; } return n; }
	function fmtTime(d, opts) {
		try { return d.toLocaleString([], Object.assign({ timeZone: S.tz || undefined, hour12: false }, opts)); } catch (e) { return d.toISOString(); }
	}
	function compass(az) { return ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'][Math.round(((az % 360) + 360) % 360 / 45) % 8]; }
	function dur(sec) { var m = Math.floor(sec / 60), s = Math.round(sec % 60); return m + ':' + String(s).padStart(2, '0'); }

	function app(root) {
		if (!sat || !M6.WorldMap) { return; }
		var home = M6MAP.home, obs = { latitude: home.lat * rad, longitude: home.lon * rad, height: 0.08 };
		var list = (S.sats || []).map(function (s, i) {
			var rec = null; try { rec = sat.twoline2satrec(s.l1, s.l2); } catch (e) { rec = null; }
			return { id: s.id, name: s.name, info: s.info, rec: rec, color: COLORS[i % COLORS.length], on: true };
		}).filter(function (s) { return s.rec; });
		var body = root.querySelector('[data-body]'), next = root.querySelector('[data-next]'), chips = root.querySelector('[data-sats]');
		var minEl = parseFloat(root.querySelector('[data-minel]').value) || 10;
		if (!list.length) { next.textContent = T.nodata; return; }
		var store = {}; try { store = JSON.parse(localStorage.getItem('m6sats') || '{}'); } catch (e) { store = {}; }
		list.forEach(function (s) { if (store[s.id] === false) { s.on = false; } });

		var map = new M6.WorldMap(root.querySelector('[data-hs-map]'), { label: 'Satellites' });
		map.night(true);
		var gTrack = M6.el('g', {}, map.gUnder), gSat = M6.el('g', {}, map.gTop);

		function look(s, date) {
			var pv = sat.propagate(s.rec, date);
			if (!pv || !pv.position) { return null; }
			var gmst = sat.gstime(date);
			var gd = sat.eciToGeodetic(pv.position, gmst);
			var la = sat.ecfToLookAngles(obs, sat.eciToEcf(pv.position, gmst));
			return { lat: gd.latitude * deg, lon: sat.degreesLong(gd.longitude), alt: gd.height, el: la.elevation * deg, az: (la.azimuth * deg + 360) % 360 };
		}
		function passes(s, from, hours) {
			var out = [], step = 60, t = from.getTime(), end = t + hours * 3600000, prev = look(s, new Date(t)), cur = null;
			if (!prev) { return out; }
			if (prev.el > 0) { cur = { s: s, aos: new Date(t), max: prev.el, maxT: new Date(t), azA: prev.az, path: [[prev.az, prev.el]], already: true }; }
			for (t += step * 1000; t <= end; t += step * 1000) {
				var p = look(s, new Date(t)); if (!p) { break; }
				if (prev.el <= 0 && p.el > 0) {
					var lo = t - step * 1000, hi = t;
					for (var k = 0; k < 8; k++) { var mid = (lo + hi) / 2, pm = look(s, new Date(mid)); if (pm && pm.el > 0) { hi = mid; } else { lo = mid; } }
					var pa = look(s, new Date(hi));
					cur = { s: s, aos: new Date(hi), max: p.el, maxT: new Date(t), azA: pa ? pa.az : p.az, path: [[pa ? pa.az : p.az, 0]] };
				}
				if (cur) {
					cur.path.push([p.az, Math.max(0, p.el)]);
					if (p.el > cur.max) { cur.max = p.el; cur.maxT = new Date(t); }
				}
				if (prev.el > 0 && p.el <= 0 && cur) {
					var lo2 = t - step * 1000, hi2 = t;
					for (var j = 0; j < 8; j++) { var m2 = (lo2 + hi2) / 2, q = look(s, new Date(m2)); if (q && q.el > 0) { lo2 = m2; } else { hi2 = m2; } }
					var pl = look(s, new Date(lo2));
					cur.los = new Date(lo2); cur.azL = pl ? pl.az : p.az; cur.path.push([cur.azL, 0]);
					out.push(cur); cur = null;
				}
				prev = p;
			}
			return out;
		}

		var all = [];
		function compute() {
			var now = new Date();
			all = [];
			list.forEach(function (s) { all = all.concat(passes(s, now, 48)); });
			all.sort(function (a, b) { return a.aos - b.aos; });
			renderTable();
		}
		function renderTable() {
			body.textContent = '';
			var rows = all.filter(function (p) { return p.s.on && p.max >= minEl; });
			if (!rows.length) { var tr0 = h('tr'), c0 = h('td', 'muted', T.none); c0.colSpan = 5; tr0.appendChild(c0); body.appendChild(tr0); }
			var lastDay = '';
			rows.forEach(function (p) {
				var day = fmtTime(p.aos, { weekday: 'short', day: 'numeric', month: 'short' });
				if (day !== lastDay) { var trd = h('tr', 'day-row'), cd = h('td', 'muted', day); cd.colSpan = 5; trd.appendChild(cd); body.appendChild(trd); lastDay = day; }
				var tr = h('tr', p.max >= 45 ? 'is-good' : '');
				var n = h('td', 'mono call'); var dot = h('i', 'sat-dot'); dot.style.background = p.s.color; n.appendChild(dot); n.appendChild(document.createTextNode(p.s.name)); tr.appendChild(n);
				tr.appendChild(h('td', 'mono', fmtTime(p.aos, { hour: '2-digit', minute: '2-digit' }) + ' – ' + fmtTime(p.los, { hour: '2-digit', minute: '2-digit' })));
				tr.appendChild(h('td', 'mono', Math.round(p.max) + '°'));
				tr.appendChild(h('td', 'mono', dur((p.los - p.aos) / 1000)));
				tr.appendChild(h('td', 'mono hide-sm muted', compass(p.azA) + ' → ' + compass(p.azL)));
				tr.addEventListener('click', function () { showNext(p); });
				body.appendChild(tr);
			});
		}
		function polar(p, live) {
			var size = 180, c = size / 2, r = c - 14, svg = M6.el('svg', { viewBox: '0 0 ' + size + ' ' + size, 'class': 'polar' });
			[0, 30, 60].forEach(function (e) { M6.el('circle', { cx: c, cy: c, r: r * (90 - e) / 90, 'class': 'pg' }, svg); });
			M6.el('path', { d: 'M' + c + ' ' + (c - r) + 'V' + (c + r) + 'M' + (c - r) + ' ' + c + 'H' + (c + r), 'class': 'pg' }, svg);
			[['N', c, 10], ['E', size - 6, c + 4], ['S', c, size - 2], ['W', 6, c + 4]].forEach(function (l) { var t = M6.el('text', { x: l[1], y: l[2], 'class': 'pl', 'text-anchor': 'middle' }, svg); t.textContent = l[0]; });
			function xy(az, el) { var rr = r * (90 - el) / 90; return [c + rr * Math.sin(az * rad), c - rr * Math.cos(az * rad)]; }
			var d = ''; p.path.forEach(function (pt, i) { var q = xy(pt[0], pt[1]); d += (i ? 'L' : 'M') + q[0].toFixed(1) + ' ' + q[1].toFixed(1); });
			M6.el('path', { d: d, 'class': 'pt', stroke: p.s.color }, svg);
			var a = xy(p.path[0][0], 0); M6.el('circle', { cx: a[0], cy: a[1], r: 3.5, fill: p.s.color }, svg);
			if (live) { var q = xy(live.az, Math.max(0, live.el)); M6.el('circle', { cx: q[0], cy: q[1], r: 5, 'class': 'pnow' }, svg); }
			return svg;
		}
		var pinned = null, focus = null;
		function showNext(p) { pinned = p; tick(); }
		function tick() {
			var now = new Date();
			gSat.textContent = '';
			var overhead = null;
			list.forEach(function (s) {
				if (!s.on) { return; }
				var l = look(s, now); if (!l) { return; }
				var pt = M6.proj(l.lat, l.lon);
				// footprint
				var ang = Math.acos(RE / (RE + l.alt)), d = '', px = null, ring = [];
				for (var i = 0; i <= 72; i++) {
					var b = i * 5 * rad, la1 = l.lat * rad, lo1 = l.lon * rad;
					var la2 = Math.asin(Math.sin(la1) * Math.cos(ang) + Math.cos(la1) * Math.sin(ang) * Math.cos(b));
					var lo2 = lo1 + Math.atan2(Math.sin(b) * Math.sin(ang) * Math.cos(la1), Math.cos(ang) - Math.sin(la1) * Math.sin(la2));
					var lon2 = ((lo2 * deg + 540) % 360) - 180;
					ring.push(M6.proj(la2 * deg, lon2));
				}
				var polar = Math.abs(l.lat) + ang * deg > 90;
				if (polar) {
					// The footprint covers a pole: draw it as a band from the edge of the map.
					ring.sort(function (a1, b1) { return a1[0] - b1[0]; });
					var edge = l.lat > 0 ? 0 : 500;
					d = 'M0 ' + edge + ' L0 ' + ring[0][1].toFixed(1);
					ring.forEach(function (q) { d += 'L' + q[0].toFixed(1) + ' ' + q[1].toFixed(1); });
					d += 'L1000 ' + ring[ring.length - 1][1].toFixed(1) + ' L1000 ' + edge + 'Z';
				} else {
					ring.forEach(function (q) { d += (px === null || Math.abs(q[0] - px) > 500 ? 'M' : 'L') + q[0].toFixed(1) + ' ' + q[1].toFixed(1); px = q[0]; });
				}
				var fp = M6.el('path', { d: d, 'class': 'wm-sat-fp' }, gSat); fp.style.stroke = s.color;
				var c = M6.el('circle', { cx: pt[0], cy: pt[1], r: 4.5, 'data-r': 4.5, 'class': 'wm-sat', fill: s.color }, gSat);
				var t = M6.el('text', { x: pt[0] + 6, y: pt[1] - 6, 'data-x0': pt[0], 'data-y0': pt[1], 'data-ox': 6, 'data-oy': -6, 'data-fs': 10, 'font-size': 10, 'class': 'wm-sat-label' }, gSat); t.textContent = s.name;
				map.bindTip(c, function () { return M6.tipLines(s.name, [s.info, T.alt + ' ' + Math.round(l.alt) + ' km', T.el + ' ' + l.el.toFixed(0) + '° · ' + T.az + ' ' + l.az.toFixed(0) + '°']); });
				if (l.el > 0 && (!overhead || l.el > overhead.l.el)) { overhead = { s: s, l: l }; }
			});
			map.apply();
			// next-pass panel
			next.textContent = '';
			var p = pinned && pinned.los > now ? pinned : null;
			if (!p) { p = all.filter(function (x) { return x.s.on && x.max >= minEl && x.los > now; })[0]; }
			if (overhead && (!p || p.s !== overhead.s || p.aos > now)) {
				p = all.filter(function (x) { return x.s === overhead.s && x.aos <= now && x.los > now && x.max >= minEl; })[0] || p;
			}
			if (!p) { next.appendChild(h('p', 'muted', T.none)); return; }
			if (focus !== p.s) { focus = p.s; track(); }
			var live = p.aos <= now ? look(p.s, now) : null;
			var head = h('div', 'sat-head'); var dot = h('i', 'sat-dot'); dot.style.background = p.s.color; head.appendChild(dot); head.appendChild(h('strong', 'mono', p.s.name));
			var secs = Math.round((p.aos - now) / 1000);
			var when = live ? T.now : T['in'].replace('%s', secs >= 3600 ? Math.floor(secs / 3600) + ' ' + T.h + ' ' + Math.floor(secs % 3600 / 60) + ' ' + T.m : Math.floor(secs / 60) + ':' + String(secs % 60).padStart(2, '0'));
			head.appendChild(h('span', live ? 'tag sat-live' : 'tag', when));
			next.appendChild(head);
			next.appendChild(h('p', 'small muted', p.s.info));
			var facts = h('ul', 'sat-facts');
			[[fmtTime(p.aos, { weekday: 'short', hour: '2-digit', minute: '2-digit' }) + ' → ' + fmtTime(p.los, { hour: '2-digit', minute: '2-digit' })],
				[T.max + ' ' + Math.round(p.max) + '° · ' + dur((p.los - p.aos) / 1000)],
				[compass(p.azA) + ' (' + Math.round(p.azA) + '°) → ' + compass(p.azL) + ' (' + Math.round(p.azL) + '°)']].forEach(function (f) { facts.appendChild(h('li', 'mono', f[0])); });
			if (live) { facts.appendChild(h('li', 'mono sat-now', T.az + ' ' + live.az.toFixed(0) + '° · ' + T.el + ' ' + live.el.toFixed(0) + '°')); }
			next.appendChild(facts);
			next.appendChild(polar(p, live));
		}
		function track() {
			gTrack.textContent = '';
			var now = Date.now();
			list.forEach(function (s) {
				if (!s.on || s !== focus) { return; }
				var d = '', px = null;
				for (var m = -20; m <= 70; m += 1) {
					var l = look(s, new Date(now + m * 60000)); if (!l) { continue; }
					var q = M6.proj(l.lat, l.lon);
					d += (px === null || Math.abs(q[0] - px) > 500 ? 'M' : 'L') + q[0].toFixed(1) + ' ' + q[1].toFixed(1); px = q[0];
				}
				var tp = M6.el('path', { d: d, 'class': 'wm-sat-trk' }, gTrack); tp.style.stroke = s.color;
			});
		}
		list.forEach(function (s) {
			var b = h('button', 'chip-btn sat-chip' + (s.on ? ' is-on' : ''), s.name); b.type = 'button';
			b.style.setProperty('--c', s.color);
			b.addEventListener('click', function () {
				s.on = !s.on; b.classList.toggle('is-on', s.on);
				store[s.id] = s.on; try { localStorage.setItem('m6sats', JSON.stringify(store)); } catch (e) {}
				pinned = null; renderTable(); track(); tick();
			});
			chips.appendChild(b);
		});
		root.querySelector('[data-minel]').addEventListener('change', function (e) { minEl = parseFloat(e.target.value) || 0; pinned = null; renderTable(); tick(); });
		compute(); track(); tick();
		setInterval(function () { if (!document.hidden) { tick(); } }, 2000);
		setInterval(function () { if (!document.hidden) { track(); } }, 60000);
		setInterval(compute, 30 * 60000);
	}
	function boot() { document.querySelectorAll('[data-hs-sats]').forEach(app); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
