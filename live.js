/* Ham Shack live widgets: WSJT-X decodes and DX cluster. Needs map.js (window.M6). */
(function () {
	'use strict';
	var L = window.M6LIVE || {}, T = L.i18n || {}, M6 = window.M6 || {};

	function h(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (text !== undefined && text !== null) { n.textContent = text; }
		return n;
	}
	function td(text, cls) { return h('td', cls || '', text); }
	function getJSON(url) { return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); }); }
	function bandColor(b) { return (window.M6MAP && M6MAP.colors && M6MAP.colors[b]) || '#ffb020'; }
	function onVisible(fn, ms) {
		fn();
		setInterval(function () { if (!document.hidden) { fn(); } }, ms);
		document.addEventListener('visibilitychange', function () { if (!document.hidden) { fn(); } });
	}

	/* ---------------- FT8 ---------------- */
	function ft8(app) {
		var body = app.querySelector('[data-body]'), dot = app.querySelector('[data-dot]'), meta = app.querySelector('[data-meta]');
		var rows = parseInt(app.getAttribute('data-rows'), 10) || 18;
		var mapHost = app.querySelector('[data-hs-map]'), map = null, seen = null;
		if (mapHost && M6.WorldMap) { map = new M6.WorldMap(mapHost, { label: 'Stations heard' }); map.fit(-30, 25, 60, 70); }
		function load() {
			getJSON(L.rest + 'live').then(function (res) {
				dot.classList.toggle('is-live', !!res.active);
				var st = res.status || {};
				meta.textContent = res.active ? [st.dial ? (st.dial / 1e6).toFixed(3) + ' MHz' : '', st.band || '', st.mode || '', st.tx ? '● ' + T.tx : T.rx].filter(Boolean).join(' · ') : '';
				body.textContent = '';
				var list = (res.decodes || []);
				if (mapHost) { mapHost.hidden = !list.length; }
				if (!list.length) {
					var tr = h('tr'); var c = td(T.idle, 'muted'); c.colSpan = 7; tr.appendChild(c); body.appendChild(tr);
				}
				list.slice(0, rows).forEach(function (d) {
					var tr = h('tr', (d.me ? 'is-me' : '') + (seen && !seen[d.t + d.msg] ? ' is-new' : ''));
					var t = String(d.t || ''); t = t.length >= 4 ? t.substr(0, 2) + ':' + t.substr(2, 2) + (t.length >= 6 ? ':' + t.substr(4, 2) : '') : t;
					tr.appendChild(td(t, 'mono'));
					tr.appendChild(td((d.snr > 0 ? '+' : '') + d.snr, 'mono'));
					tr.appendChild(td(Number(d.dt).toFixed(1), 'mono muted'));
					tr.appendChild(td(d.df, 'mono muted'));
					var m = td('', 'mono ft8-msg');
					d.msg.split(' ').forEach(function (w, i) {
						var s = h('span', w === d.call ? 'call' : (w === 'CQ' ? 'cq' : (w === L.call ? 'me' : '')), (i ? ' ' : '') + w);
						m.appendChild(s);
					});
					if (d.me) { m.appendChild(h('span', 'tag tag-alt', T.calling)); }
					tr.appendChild(m);
					tr.appendChild(td(d.country || '', 'hide-sm'));
					tr.appendChild(td(d.km ? Number(d.km).toLocaleString() : '', 'mono hide-sm muted'));
					body.appendChild(tr);
				});
				var now = {}; list.forEach(function (d) { now[d.t + d.msg] = 1; }); seen = now;
				if (map) {
					map.clear();
					var calls = {};
					list.forEach(function (d) {
						if (!d.call || calls[d.call] || d.lat === undefined) { return; }
						calls[d.call] = 1;
						var col = d.me ? '#39ff88' : (d.cq ? '#ffb020' : '#22d3ee');
						if (!d.approx) { map.line(M6MAP.home.lat, M6MAP.home.lon, d.lat, d.lon, 'wm-path', col); }
						var c = map.dot(d.lat, d.lon, 3.6, 'wm-qso' + (d.approx ? ' is-approx' : ''));
						c.setAttribute('fill', col);
						map.bindTip(c, function () { return M6.tipLines(d.call, [d.country || '', (d.grid || '') + (d.km ? ' · ' + Number(d.km).toLocaleString() + ' km' : ''), d.snr + ' dB · ' + d.msg]); });
					});
					map.apply();
				}
			}).catch(function () { dot.classList.remove('is-live'); });
		}
		onVisible(load, 15000);
	}

	/* ---------------- DX cluster ---------------- */
	function dx(app) {
		var body = app.querySelector('[data-body]'), meta = app.querySelector('[data-meta]'), chips = app.querySelector('[data-bands]');
		var limit = parseInt(app.getAttribute('data-limit'), 10) || 25, data = [];
		var store = {}; try { store = JSON.parse(localStorage.getItem('m6dx') || '{}'); } catch (e) { store = {}; }
		var sel = store.band || 'my';
		var bands = ['160m', '80m', '60m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m', '2m'];
		[['my', T.my], ['', T.all]].concat(bands.map(function (b) { return [b, b]; })).forEach(function (b) {
			var bt = h('button', 'chip-btn' + (sel === b[0] ? ' is-on' : ''), b[1]); bt.type = 'button';
			bt.addEventListener('click', function () {
				sel = b[0]; chips.querySelectorAll('.chip-btn').forEach(function (x) { x.classList.toggle('is-on', x === bt); });
				save(); render();
			});
			chips.appendChild(bt);
		});
		var modeBoxes = { cw: app.querySelector('[data-cw]'), ssb: app.querySelector('[data-ssb]'), digi: app.querySelector('[data-digi]') };
		Object.keys(modeBoxes).forEach(function (k) {
			modeBoxes[k].checked = store[k] !== false;
			modeBoxes[k].addEventListener('change', function () { save(); render(); });
		});
		function save() { try { localStorage.setItem('m6dx', JSON.stringify({ band: sel, cw: modeBoxes.cw.checked, ssb: modeBoxes.ssb.checked, digi: modeBoxes.digi.checked })); } catch (e) {} }
		function kind(s) {
			var info = (s.info || '').toUpperCase(), k = s.khz;
			if (/FT8|FT4|JS8|RTTY|PSK|DIGI|WSPR|MSK|Q65|JT65/.test(info)) { return 'digi'; }
			if (/\bCW\b/.test(info)) { return 'cw'; }
			if (/SSB|USB|LSB|\bFM\b|\bAM\b/.test(info)) { return 'ssb'; }
			var cw = [[1800, 1840], [3500, 3570], [7000, 7040], [10100, 10130], [14000, 14070], [18068, 18095], [21000, 21070], [24890, 24915], [28000, 28070], [50000, 50100]];
			var dg = [[1840, 1843], [3570, 3600], [7040, 7080], [10130, 10150], [14070, 14112], [18095, 18110], [21070, 21150], [24915, 24930], [28070, 28190], [50300, 50330]];
			for (var i = 0; i < cw.length; i++) { if (k >= cw[i][0] && k < cw[i][1]) { return 'cw'; } }
			for (var j = 0; j < dg.length; j++) { if (k >= dg[j][0] && k < dg[j][1]) { return 'digi'; } }
			return 'ssb';
		}
		function render() {
			body.textContent = '';
			var my = (L.bands || []).map(function (b) { return String(b).toLowerCase(); });
			var rows = data.filter(function (s) {
				if (sel === 'my' && my.length && my.indexOf(s.band) < 0 && !s.me) { return false; }
				if (sel && sel !== 'my' && s.band !== sel) { return false; }
				return s.me || modeBoxes[kind(s)].checked;
			}).slice(0, limit);
			if (!rows.length) { var tr0 = h('tr'), c0 = td(T.none, 'muted'); c0.colSpan = 6; tr0.appendChild(c0); body.appendChild(tr0); return; }
			rows.forEach(function (s) {
				var tr = h('tr', s.me ? 'is-me' : '');
				tr.appendChild(td(s.time, 'mono'));
				var f = td(Number(s.khz).toFixed(1), 'mono'); f.style.color = bandColor(s.band); tr.appendChild(f);
				var c = td('', 'mono call'); var a = h('a', '', s.dx); a.href = 'https://www.qrz.com/db/' + encodeURIComponent(s.dx); a.target = '_blank'; a.rel = 'noopener nofollow'; c.appendChild(a);
				if (s.me) { c.appendChild(h('span', 'tag tag-alt', T.spotted)); }
				tr.appendChild(c);
				tr.appendChild(td((s.country || '') + (s.km ? ' · ' + Number(s.km).toLocaleString() + ' km' : ''), 'hide-sm'));
				tr.appendChild(td(s.info || '', 'hide-sm muted small'));
				tr.appendChild(td(s.de, 'mono muted'));
				body.appendChild(tr);
			});
		}
		function load() {
			getJSON(L.rest + 'dx').then(function (res) {
				data = res.spots || [];
				meta.textContent = res.source ? res.source + ' · ' + new Date((res.updated || 0) * 1000).toISOString().substr(11, 5) + 'Z' : '';
				render();
			}).catch(function () { meta.textContent = ''; });
		}
		onVisible(load, 60000);
	}

	function boot() {
		document.querySelectorAll('[data-hs-ft8]').forEach(ft8);
		document.querySelectorAll('[data-hs-dx]').forEach(dx);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
