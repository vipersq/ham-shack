/* Ham Shack learning tools: Morse trainer (Koch, callsigns, text) and practice quiz. */
(function () {
	'use strict';
	var D = window.M6LEARN || {}, T = D.i18n || {};
	var CODE = {
		A: '.-', B: '-...', C: '-.-.', D: '-..', E: '.', F: '..-.', G: '--.', H: '....', I: '..', J: '.---', K: '-.-', L: '.-..', M: '--',
		N: '-.', O: '---', P: '.--.', Q: '--.-', R: '.-.', S: '...', T: '-', U: '..-', V: '...-', W: '.--', X: '-..-', Y: '-.--', Z: '--..',
		'0': '-----', '1': '.----', '2': '..---', '3': '...--', '4': '....-', '5': '.....', '6': '-....', '7': '--...', '8': '---..', '9': '----.',
		'.': '.-.-.-', ',': '--..--', '?': '..--..', '/': '-..-.', '=': '-...-', '+': '.-.-.'
	};
	var KOCH = 'KMRSUAPTLOWI.NJEF0Y,VG5/Q9ZH38B?427C1D6X';
	function fmt(s) { var a = Array.prototype.slice.call(arguments, 1), i = 0; return String(s).replace(/%(\d\$)?s/g, function () { return a[i++]; }).replace(/%%/g, '%'); }
	function h(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text !== undefined && text !== null) { n.textContent = text; } return n; }
	function store(k, v) { try { if (v === undefined) { return JSON.parse(localStorage.getItem('m6learn_' + k)); } localStorage.setItem('m6learn_' + k, JSON.stringify(v)); } catch (e) { return null; } return v; }

	/* ---------------- audio ---------------- */
	var ctx = null, playing = [], stopTimer = null;
	function audio() {
		if (!ctx) { var AC = window.AudioContext || window.webkitAudioContext; ctx = new AC(); }
		if (ctx.state === 'suspended') { ctx.resume(); }
		return ctx;
	}
	function stop() {
		playing.forEach(function (o) { try { o.stop(); } catch (e) {} });
		playing = [];
		clearTimeout(stopTimer);
	}
	/* Play text; returns duration in seconds. Farnsworth spacing when eff < wpm. */
	function playText(text, s, onDone) {
		stop();
		var ac = audio(), dot = 1.2 / s.wpm, t = ac.currentTime + 0.15, start = t;
		var fw = s.eff < s.wpm ? ((60 * s.wpm - 37.2 * s.eff) / (s.eff * s.wpm)) / 19 : dot; // Farnsworth unit
		var gain = ac.createGain(); gain.gain.value = s.vol / 100 * 0.6; gain.connect(ac.destination);
		var osc = ac.createOscillator(); osc.frequency.value = s.tone; osc.type = 'sine';
		var env = ac.createGain(); env.gain.value = 0; osc.connect(env); env.connect(gain);
		String(text).toUpperCase().split('').forEach(function (ch) {
			if (ch === ' ' || ch === '\n') { t += 7 * fw - 3 * fw; return; }
			var c = CODE[ch]; if (!c) { return; }
			c.split('').forEach(function (sym, i) {
				var len = sym === '.' ? dot : 3 * dot;
				env.gain.setValueAtTime(0, t);
				env.gain.linearRampToValueAtTime(1, t + 0.005);
				env.gain.setValueAtTime(1, t + len - 0.005);
				env.gain.linearRampToValueAtTime(0, t + len);
				t += len + (i < c.length - 1 ? dot : 0);
			});
			t += 3 * fw;
		});
		osc.start(start); osc.stop(t + 0.05);
		playing.push(osc);
		stopTimer = setTimeout(function () { if (onDone) { onDone(); } }, (t - ac.currentTime) * 1000 + 100);
		return t - start;
	}

	/* ---------------- Morse trainer ---------------- */
	function morse(root) {
		var s = Object.assign({ wpm: 20, eff: 10, tone: 600, vol: 50 }, store('set') || {});
		root.querySelectorAll('[data-set]').forEach(function (inp) {
			var k = inp.getAttribute('data-set'); inp.value = s[k];
			var out = root.querySelector('[data-out="' + k + '"]'); out.textContent = s[k];
			inp.addEventListener('input', function () {
				s[k] = +inp.value;
				if (k === 'wpm' && s.eff > s.wpm) { s.eff = s.wpm; root.querySelector('[data-set="eff"]').value = s.eff; root.querySelector('[data-out="eff"]').textContent = s.eff; }
				if (k === 'eff' && s.eff > s.wpm) { s.eff = s.wpm; inp.value = s.eff; }
				out.textContent = s[k]; store('set', s);
			});
		});
		root.querySelectorAll('[data-tab]').forEach(function (b) {
			b.addEventListener('click', function () {
				stop();
				root.querySelectorAll('[data-tab]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				root.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-tab'); });
			});
		});
		root.querySelectorAll('[data-stop]').forEach(function (b) { b.addEventListener('click', stop); });

		/* chart */
		var chart = root.querySelector('[data-chart]');
		Object.keys(CODE).forEach(function (k) {
			var c = h('button', 'mc'); c.type = 'button';
			c.appendChild(h('b', '', k)); c.appendChild(h('span', '', CODE[k].replace(/\./g, '·').replace(/-/g, '–')));
			c.addEventListener('click', function () { playText(k, s); });
			chart.appendChild(c);
		});

		/* Koch */
		var sel = root.querySelector('[data-lesson]'), chars = root.querySelector('[data-chars]'), sent = '';
		for (var i = 2; i <= KOCH.length; i++) { var o = h('option', '', (i - 1) + ' – ' + KOCH.substr(0, i).split('').join(' ').substr(-5)); o.value = i; sel.appendChild(o); }
		sel.value = store('lesson') || 2;
		function showChars() {
			var n = +sel.value; chars.textContent = '';
			chars.appendChild(h('span', 'muted', T.newchars + ': '));
			KOCH.substr(0, n).split('').forEach(function (c, j) { var b = h('b', j === n - 1 ? 'is-new' : '', c); chars.appendChild(b); });
		}
		sel.addEventListener('change', function () { store('lesson', +sel.value); showChars(); });
		showChars();
		root.querySelector('[data-play-koch]').addEventListener('click', function () {
			var set = KOCH.substr(0, +sel.value), groups = +root.querySelector('[data-groups]').value, out = [];
			for (var g = 0; g < groups; g++) {
				var w = ''; for (var j = 0; j < 5; j++) { w += set[Math.floor(Math.random() * set.length)]; }
				out.push(w);
			}
			sent = out.join(' ');
			root.querySelector('[data-koch-input]').value = '';
			root.querySelector('[data-koch-result]').textContent = T.listening;
			playText(sent, s);
			root.querySelector('[data-koch-input]').focus();
		});
		root.querySelector('[data-check-koch]').addEventListener('click', function () {
			var res = root.querySelector('[data-koch-result]'); res.textContent = '';
			if (!sent) { return; }
			var got = root.querySelector('[data-koch-input]').value.toUpperCase().replace(/\s+/g, ' ').trim().split(' ');
			var exp = sent.split(' '), ok = 0, total = 0;
			var cmp = h('div', 'koch-compare mono');
			exp.forEach(function (w, gi) {
				var g = got[gi] || '', line = h('div');
				for (var k = 0; k < w.length; k++) { total++; var good = g[k] === w[k]; if (good) { ok++; } line.appendChild(h('span', good ? 'ok' : 'bad', w[k])); }
				line.appendChild(h('span', 'muted', '  ' + T.you + ': ' + (g || '—')));
				cmp.appendChild(line);
			});
			var pct = Math.round(100 * ok / Math.max(1, total));
			res.appendChild(h('p', pct >= 90 ? 'notice notice-ok' : 'notice', fmt(T.accuracy, pct) + ' – ' + (pct >= 90 && +sel.value < KOCH.length ? fmt(T.next, +sel.value) : T.again)));
			res.appendChild(cmp);
			if (pct >= 90 && +sel.value < KOCH.length) { sel.value = +sel.value + 1; store('lesson', +sel.value); showChars(); }
		});

		/* Callsigns */
		var pre = ['G', 'M', '2E0', 'M0', 'G4', 'GW', 'GM', 'EI', 'F', 'DL', 'ON', 'PA', 'EA', 'I', 'SP', 'OK', 'K', 'W', 'N', 'VE', 'JA', 'VK', 'ZL', 'LU', 'PY', 'OH', 'SM', 'LA', 'OZ', 'HB9', 'OE', 'S5', '9A', 'YU', 'LZ', 'SV', 'CT', 'EA8', 'VU', 'ZS'];
		var cur = '', score = 0, tries = 0, streak = 0, cwpm = s.wpm;
		function rndCall() {
			var p = pre[Math.floor(Math.random() * pre.length)], L = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			var d = /\d$/.test(p) ? '' : String(Math.floor(Math.random() * 10));
			var n = 1 + Math.floor(Math.random() * 3), suf = '';
			for (var i2 = 0; i2 < n; i2++) { suf += L[Math.floor(Math.random() * 26)]; }
			if (Math.random() < 0.08) { return D.call || 'N0CALL'; }
			return p + d + suf;
		}
		var inp = root.querySelector('[data-call-input]'), fb = root.querySelector('[data-call-feedback]'), sc = root.querySelector('[data-call-score]');
		function sendCall() { playText(cur, Object.assign({}, s, { wpm: cwpm, eff: cwpm })); inp.focus(); }
		root.querySelector('[data-play-call]').addEventListener('click', function () { cur = rndCall(); inp.value = ''; fb.textContent = T.listening; fb.className = 'call-feedback'; sendCall(); });
		root.querySelector('[data-repeat-call]').addEventListener('click', function () { if (cur) { sendCall(); } });
		inp.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' || !cur) { return; }
			e.preventDefault(); tries++;
			var ok = inp.value.trim().toUpperCase() === cur;
			if (ok) { score++; streak++; cwpm = Math.min(40, cwpm + 1); } else { streak = 0; cwpm = Math.max(10, cwpm - 2); }
			fb.textContent = ok ? '✔ ' + T.correct + ' ' + cur : '✘ ' + fmt(T.wrong, cur);
			fb.className = 'call-feedback ' + (ok ? 'is-ok' : 'is-bad');
			sc.textContent = fmt(T.score, score, tries, streak) + ' · ' + cwpm + ' WPM';
			cur = '';
			setTimeout(function () { root.querySelector('[data-play-call]').click(); }, 900);
		});

		/* Text */
		var vis = root.querySelector('[data-visual]');
		root.querySelector('[data-play-text]').addEventListener('click', function () {
			var txt = root.querySelector('[data-text-input]').value;
			vis.textContent = txt.toUpperCase().split('').map(function (c) { return c === ' ' ? ' / ' : (CODE[c] || ''); }).join(' ').replace(/\./g, '·').replace(/-/g, '–');
			playText(txt, s);
		});
	}

	/* ---------------- Quiz ---------------- */
	function quiz(root) {
		var bank = D.quiz || [], count = Math.min(parseInt(root.getAttribute('data-count'), 10) || 10, bank.length);
		var stage = root.querySelector('[data-stage]'), prog = root.querySelector('[data-progress]'), bar = root.querySelector('[data-bar]');
		var qs, idx, score, answers;
		function shuffle(a) { for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
		function start() {
			qs = shuffle(bank.slice()).slice(0, count).map(function (q) {
				var order = shuffle(q.a.map(function (_, i) { return i; }));
				return { q: q.q, a: order.map(function (i) { return q.a[i]; }), c: order.indexOf(q.c), e: q.e };
			});
			idx = 0; score = 0; answers = []; show();
		}
		function show() {
			stage.textContent = '';
			prog.textContent = fmt(T.qof, idx + 1, qs.length);
			bar.style.width = (100 * idx / qs.length) + '%';
			var q = qs[idx];
			stage.appendChild(h('h3', 'quiz-q', q.q));
			var list = h('div', 'quiz-answers');
			q.a.forEach(function (txt, i) {
				var b = h('button', 'quiz-a', txt); b.type = 'button';
				b.addEventListener('click', function () { answer(i, list); });
				list.appendChild(b);
			});
			stage.appendChild(list);
		}
		function answer(i, list) {
			var q = qs[idx], ok = i === q.c;
			if (ok) { score++; }
			answers.push(ok);
			list.querySelectorAll('button').forEach(function (b, j) {
				b.disabled = true;
				if (j === q.c) { b.classList.add('is-right'); } else if (j === i) { b.classList.add('is-wrong'); }
			});
			stage.appendChild(h('p', 'quiz-exp ' + (ok ? 'ok' : 'bad'), (ok ? '✔ ' : '✘ ') + q.e));
			var nb = h('button', 'btn btn-primary', idx + 1 < qs.length ? T.nextq : T.finish); nb.type = 'button';
			nb.addEventListener('click', function () { idx++; if (idx < qs.length) { show(); } else { done(); } });
			stage.appendChild(nb);
			nb.focus();
		}
		function done() {
			stage.textContent = '';
			bar.style.width = '100%';
			prog.textContent = '';
			var pass = score / qs.length >= 0.73;
			var box = h('div', 'quiz-done');
			box.appendChild(h('div', 'quiz-score mono', score + ' / ' + qs.length));
			box.appendChild(h('p', '', fmt(T.result, score, qs.length) + ' ' + (pass ? T.pass : T.fail)));
			var dots = h('div', 'quiz-dots'); answers.forEach(function (a) { dots.appendChild(h('span', a ? 'ok' : 'bad')); }); box.appendChild(dots);
			var rb = h('button', 'btn btn-primary', T.restart); rb.type = 'button'; rb.addEventListener('click', start);
			box.appendChild(rb);
			stage.appendChild(box);
		}
		start();
	}

	function boot() {
		document.querySelectorAll('[data-hs-morse]').forEach(morse);
		document.querySelectorAll('[data-hs-quiz]').forEach(quiz);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
