/* SUPER MẠNH — shared helpers for the two mobile apps */
(function () {
	'use strict';
	var C = window.SMSS || {};

	var esc = function (s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	};

	var num = function (n) { return Number(n || 0).toLocaleString('vi-VN'); };

	var signed = function (n) {
		n = Number(n || 0);
		return '<span class="' + (n >= 0 ? 'plus' : 'minus') + '">' + (n >= 0 ? '+' : '−') + num(Math.abs(n)) + '</span>';
	};

	/** "2026-11-26 15:04:00" -> "15:04 26/11" (or with year when not this year). */
	var when = function (s) {
		if (!s) return '—';
		var m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
		if (!m) return s;
		var y = new Date().getFullYear() === Number(m[1]) ? '' : '/' + m[1];
		return m[4] + ':' + m[5] + ' ' + m[3] + '/' + m[2] + y;
	};
	var day = function (s) {
		if (!s) return 'Vĩnh viễn';
		var m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
		return m ? m[3] + '/' + m[2] + '/' + m[1] : s;
	};

	var api = function (path, opts) {
		opts = opts || {};
		var headers = { 'X-SMSS': '1' };
		if (C.nonce) headers['X-WP-Nonce'] = C.nonce;
		var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: headers };
		if (opts.body) {
			headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify(opts.body);
		}
		var url = C.api.replace(/\/$/, '') + path;
		if (opts.query) {
			var q = Object.keys(opts.query).filter(function (k) { return opts.query[k] !== '' && opts.query[k] != null; })
				.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(opts.query[k]); }).join('&');
			if (q) url += (url.indexOf('?') < 0 ? '?' : '&') + q;
		}
		return fetch(url, init).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (data) {
				if (!r.ok) {
					var e = new Error(data && data.message ? data.message : 'Lỗi ' + r.status);
					e.status = r.status;
					throw e;
				}
				return data;
			});
		});
	};

	var toastTimer;
	var toast = function (msg, bad) {
		var t = document.getElementById('toast');
		t.textContent = msg;
		t.className = 'show' + (bad ? ' bad' : '');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { t.className = ''; }, bad ? 3800 : 2200);
	};

	var copy = function (text) {
		var done = function () { toast('Đã chép'); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { fallback(); });
		} else { fallback(); }
		function fallback() {
			var ta = document.createElement('textarea');
			ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild(ta); ta.select();
			try { document.execCommand('copy'); done(); } catch (e) { toast('Không chép được', true); }
			ta.remove();
		}
	};

	var share = function (text) {
		if (navigator.share) {
			navigator.share({ text: text }).catch(function () {});
		} else {
			copy(text);
		}
	};

	/** Bottom sheet. Returns { el, close }. */
	var sheet = function (html, onMount, onClose) {
		var scrim = document.createElement('div');
		scrim.className = 'scrim';
		var el = document.createElement('div');
		el.className = 'sheet';
		el.innerHTML = '<div class="grab"></div>' + html;
		document.body.appendChild(scrim);
		document.body.appendChild(el);
		requestAnimationFrame(function () { scrim.classList.add('show'); el.classList.add('show'); });
		var closed = false;
		var close = function () {
			if (closed) return;
			closed = true;
			if (onClose) onClose();
			scrim.classList.remove('show'); el.classList.remove('show');
			setTimeout(function () { scrim.remove(); el.remove(); }, 250);
		};
		scrim.addEventListener('click', close);
		el.addEventListener('click', function (e) {
			var b = e.target.closest('[data-close]');
			if (b) close();
			var c = e.target.closest('[data-copy]');
			if (c) copy(c.getAttribute('data-copy'));
		});
		var api = { el: el, close: close };
		if (onMount) onMount(api);
		var first = el.querySelector('input:not([type=checkbox]),textarea');
		if (first && first.hasAttribute('autofocus')) setTimeout(function () { first.focus(); }, 260);
		return api;
	};

	var confirmSheet = function (title, body, okText, danger) {
		return new Promise(function (resolve) {
			var ok = false;
			var s = sheet('<h3>' + esc(title) + '</h3><p class="note" style="font-size:15px;color:var(--text)">' + body + '</p>' +
				'<div class="grid2"><button class="btn ghost" data-close>Huỷ</button><button class="btn ' + (danger ? 'bad' : 'pri') + '" data-ok>' + esc(okText || 'Đồng ý') + '</button></div>',
				null,
				function () { resolve(ok); });
			s.el.querySelector('[data-ok]').addEventListener('click', function () { ok = true; s.close(); });
		});
	};

	/** Disable a button and show a spinner while a promise runs. */
	var busy = function (btn, promise) {
		if (!btn) return promise;
		var html = btn.innerHTML;
		btn.disabled = true;
		btn.innerHTML = '<span class="spin"></span>';
		return promise.finally(function () { btn.disabled = false; btn.innerHTML = html; });
	};

	var icon = {
		home: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>',
		users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-3.9 3.1-7 7-7s7 3.1 7 7"/><path d="M16 4a4 4 0 0 1 0 8M22 21c0-3-1.8-5.6-4.5-6.6"/></svg>',
		game: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="4"/><path d="M6 12h4M8 10v4"/><circle cx="16" cy="11" r="1"/><circle cx="18" cy="13" r="1"/></svg>',
		menu: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
		shop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18l-1.5 12a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>',
		help: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5h.01"/></svg>',
		clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
		search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>'
	};

	/** Cover thumbnail: the image when there is one, a game glyph otherwise (also when it fails to load). */
	var cover = function (url) {
		var ph = '<span class="ic">' + icon.game + '</span>';
		if (!url) return ph;
		return '<img class="ic" loading="lazy" referrerpolicy="no-referrer" alt="" src="' + esc(url) + '" onerror="this.outerHTML=\'<span class=&quot;ic&quot;></span>\'">';
	};

	var download = function (name, text) {
		var a = document.createElement('a');
		a.href = URL.createObjectURL(new Blob([text], { type: 'application/json' }));
		a.download = name;
		document.body.appendChild(a); a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
	};

	var debounce = function (fn, ms) {
		var t;
		return function () { var a = arguments, me = this; clearTimeout(t); t = setTimeout(function () { fn.apply(me, a); }, ms); };
	};

	/** The text a customer needs, ready to paste into Zalo / Messenger. */
	var accountMessage = function (o) {
		var t = o.tinfoil;
		var lines = [
			(t.title || 'SUPER MẠNH Việt Hóa') + ' — thông tin tài khoản',
			'Tài khoản: ' + t.username
		];
		if (o.password) lines.push('Mật khẩu: ' + o.password);
		if (o.portal) lines.push('', 'Trang của bạn (xem điểm, mở khoá bản Việt hóa):', o.portal);
		if (t.app_url) lines.push('', 'Tải app ' + (t.app || 'SUPER MẠNH Việt Hóa') + ' cho Switch:', t.app_url);
		lines.push('',
			'Nếu dùng Tinfoil: File Browser → nhấn (-) → điền:',
			'• Protocol: ' + t.protocol,
			'• Host: ' + t.host,
			'• Port: ' + t.port,
			'• Path: ' + t.path,
			'• Username: ' + t.username,
			'• Password: ' + (o.password || '(mật khẩu của bạn)'),
			'• Title: ' + t.title,
			'Lưu lại rồi mở mục vừa tạo là thấy các bản đã mở khoá.');
		return lines.join('\n');
	};

	window.SM = {
		C: C, esc: esc, num: num, signed: signed, when: when, day: day, api: api, toast: toast, copy: copy, share: share,
		cover: cover, download: download, sheet: sheet, confirm: confirmSheet, busy: busy, icon: icon, debounce: debounce, accountMessage: accountMessage
	};
})();
