/* Ham Shack propagation: MUF map (KC2G ionosondes) and best-times heat map. Needs map.js. */
(function () {
	'use strict';
	var M6 = window.M6 || {}, T = (window.M6MAP && M6MAP.i18n) || {};
	function h(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text !== undefined && text !== null) { n.textContent = text; } return n; }
	var SCALE = [[0, '#6b7280'], [7, '#a78bfa'], [10, '#60a5fa'], [14, '#22d3ee'], [18, '#39ff88'], [21, '#a3e635'], [24, '#facc15'], [28, '#ffb020'], [35, '#ff3b3b']];
	function mufColor(m) { var c = SCALE[0][1]; SCALE.forEach(function (s) { if (m >= s[0]) { c = s[1]; } }); return c; }

	function muf(app) {
		if (!M6.WorldMap) { return; }
		var items = []; try { items = JSON.parse(app.getAttribute('data-items') || '[]'); } catch (e) { items = []; }
		var map = new M6.WorldMap(app.querySelector('[data-hs-map]'), { label: 'MUF map' });
		map.night(true);
		items.forEach(function (s) {
			var col = mufColor(s.muf);
			var c = map.dot(s.lat, s.lon, 7, 'wm-muf'); c.setAttribute('fill', col);
			var p = M6.proj(s.lat, s.lon);
			var t = M6.el('text', { x: p[0], y: p[1] + 2.6, 'data-x0': p[0], 'data-y0': p[1], 'data-ox': 0, 'data-oy': 2.6, 'data-fs': 7.5, 'font-size': 7.5, 'text-anchor': 'middle', 'class': 'wm-muf-label' }, map.gTop);
			t.textContent = Math.round(s.muf);
			map.bindTip(c, function () { return M6.tipLines(s.name, ['MUF(D) ' + s.muf + ' MHz', s.fof2 ? 'foF2 ' + s.fof2 + ' MHz' : '', s.age + ' ' + (T.min || 'min') + ' ago']); });
		});
		map.apply();
		var lg = app.querySelector('[data-legend]');
		if (lg) {
			SCALE.slice(1).forEach(function (s) { var x = h('span', 'lg'); var i = h('i'); i.style.background = s[1]; x.appendChild(i); x.appendChild(document.createTextNode('≥' + s[0])); lg.appendChild(x); });
			lg.appendChild(h('span', 'muted', 'MHz'));
		}
	}

	function best(app) {
		var data = {}, names = {};
		try { data = JSON.parse(app.getAttribute('data-items') || '{}'); names = JSON.parse(app.getAttribute('data-names') || '{}'); } catch (e) { return; }
		var grid = app.querySelector('[data-grid]');
		function draw(band) {
			var d = data[band] || data[''];
			grid.textContent = '';
			// Each continent is scaled on its own, so the best hours stand out even for rare paths.
			grid.appendChild(h('span', 'hc hh', 'UTC'));
			for (var i = 0; i < 24; i++) { grid.appendChild(h('span', 'hc hh mono', String(i).padStart(2, '0'))); }
			Object.keys(d).forEach(function (c) {
				grid.appendChild(h('span', 'hc hl', names[c] || c));
				var max = Math.max.apply(null, d[c].concat([1]));
				d[c].forEach(function (v, hr) {
					var cell = h('span', 'hc hv' + (v ? '' : ' is-zero'));
					var a = v ? 0.15 + 0.85 * Math.sqrt(v / max) : 0;
					if (v) { cell.style.background = 'rgba(255,176,32,' + a.toFixed(2) + ')'; }
					cell.title = (names[c] || c) + ' · ' + String(hr).padStart(2, '0') + 'Z · ' + v + ' QSO';
					if (v && a > 0.6) { cell.textContent = v; }
					grid.appendChild(cell);
				});
			});
		}
		app.querySelectorAll('[data-band]').forEach(function (b) {
			b.addEventListener('click', function () {
				app.querySelectorAll('[data-band]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				draw(b.getAttribute('data-band'));
			});
		});
		draw('');
	}

	function boot() {
		document.querySelectorAll('[data-hs-muf]').forEach(muf);
		document.querySelectorAll('[data-hs-best]').forEach(best);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
