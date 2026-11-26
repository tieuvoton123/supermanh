/* SUPER MẠNH — customer page: balance, unlock Việt hóa patches with points, Tinfoil setup */
(function () {
	'use strict';
	var S = window.SM, esc = S.esc, num = S.num, api = S.api, toast = S.toast, I = S.icon;
	var app = document.getElementById('app');
	var me = null, tab = 'shop', filter = 'all', query = '';

	function fail(e) {
		if (e && e.status === 401) { me = null; login(); return; }
		toast(e && e.message ? e.message : 'Có lỗi', true);
	}

	/* ---------------- Login ---------------- */

	function login() {
		app.innerHTML =
			'<div class="login"><div class="logo"></div><h1>SUPER MẠNH Việt Hóa</h1><p class="sub">Nhập <b>mã kích hoạt</b> hiện trong app SUPER MẠNH Việt Hóa, hoặc tài khoản thành viên</p>' +
			'<form data-f><div class="field"><label>Mã kích hoạt hoặc tên đăng nhập</label><input class="input" name="u" autocomplete="username" autocapitalize="none" placeholder="ABCD 1234" required></div>' +
			'<div class="field"><label>Mật khẩu, hoặc <b>PIN web</b> hiện trong app nếu dùng mã kích hoạt</label><input class="input" name="p" type="password" autocomplete="current-password" required></div>' +
			'<button class="btn pri full" type="submit">Đăng nhập</button></form>' +
			'<p class="note" style="text-align:center;margin-top:18px">Chưa có tài khoản? Liên hệ <b>supermanh.com</b></p></div>';
		app.querySelector('[data-f]').addEventListener('submit', function (e) {
			e.preventDefault();
			var f = e.currentTarget;
			S.busy(f.querySelector('button'), api('/portal/login', { method: 'POST', body: { username: f.u.value.trim(), password: f.p.value } }))
				.then(load).catch(function (err) { toast(err.message, true); });
		});
	}

	function load() {
		return api('/portal/me').then(function (d) { me = d; render(); }).catch(fail);
	}

	/* ---------------- Main ---------------- */

	function render() {
		var a = me.account, P = me.point || 'điểm';
		var owned = me.items.filter(function (i) { return i.owned; }).length;
		app.innerHTML =
			'<header class="top"><div class="logo"></div><h1>' + esc(me.shop) + '<small>' + (a.kind === 'device' ? 'Máy ' : 'Xin chào ') + esc(a.username) + '</small></h1>' +
			'<button class="act" data-out>Thoát</button></header>' +
			'<main>' +
				'<div class="hero"><div class="lbl">Số dư của bạn</div><div class="big">' + num(a.points) + ' <small>' + esc(P) + '</small></div>' +
				'<div class="meta"><span class="pill ok">' + owned + ' bản đã có</span>' +
				(a.expired ? '<span class="pill bad">Hết hạn ' + S.day(a.expires_at) + '</span>' : (a.expires_at ? '<span class="pill">Hạn: ' + S.day(a.expires_at) + '</span>' : '')) +
				'</div><p class="note" style="margin:10px 0 0;color:#ffd0b8">Cần thêm ' + esc(P) + '? Liên hệ <b>' + esc(me.contact) + '</b></p></div>' +
				'<div id="pane"></div>' +
			'</main>' +
			'<nav class="tabs">' +
				'<button data-t="shop" class="' + (tab === 'shop' ? 'on' : '') + '">' + I.shop + 'Bản Việt hóa</button>' +
				'<button data-t="setup" class="' + (tab === 'setup' ? 'on' : '') + '">' + I.help + 'Cài app</button>' +
				'<button data-t="hist" class="' + (tab === 'hist' ? 'on' : '') + '">' + I.clock + 'Lịch sử</button>' +
			'</nav>';
		app.querySelector('[data-out]').addEventListener('click', function () {
			api('/portal/logout', { method: 'POST' }).finally(function () { me = null; login(); });
		});
		app.querySelectorAll('[data-t]').forEach(function (b) {
			b.addEventListener('click', function () { tab = b.dataset.t; render(); window.scrollTo(0, 0); });
		});
		({ shop: paneShop, setup: paneSetup, hist: paneHist })[tab]();
	}

	function paneShop() {
		var P = me.point || 'điểm';
		var pane = document.getElementById('pane');
		pane.innerHTML =
			'<div class="search">' + I.search + '<input class="input" type="search" placeholder="Tìm bản Việt hóa…" value="' + esc(query) + '"></div>' +
			'<div class="chips">' + [['all', 'Tất cả'], ['lock', 'Chưa có'], ['own', 'Đã có']].map(function (c) {
				return '<button class="chip ' + (filter === c[0] ? 'on' : '') + '" data-c="' + c[0] + '">' + c[1] + '</button>';
			}).join('') + '</div><div class="list" data-l></div>';
		var list = pane.querySelector('[data-l]');
		var draw = function () {
			var q = query.toLowerCase();
			var rows = me.items.filter(function (i) {
				if (filter === 'lock' && i.owned) return false;
				if (filter === 'own' && !i.owned) return false;
				return !q || i.name.toLowerCase().indexOf(q) >= 0 || (i.display || '').toLowerCase().indexOf(q) >= 0 || (i.title_id || '').toLowerCase().indexOf(q) >= 0;
			});
			list.innerHTML = rows.length ? rows.map(function (i) {
				var end = i.owned
					? '<span class="pill ok">' + (i.is_free ? 'Miễn phí' : 'Đã có') + '</span>'
					: '<button class="btn sm pri unlock-btn" data-u="' + i.id + '">Mở · ' + num(i.price) + '</button>';
				return '<div class="row">' + S.cover(i.icon) + '<div class="main"><div class="t">' + esc(i.display || i.name) + '</div><div class="s">' + esc(i.size_h) + '</div></div><div class="end">' + end + '</div></div>';
			}).join('') : '<div class="empty">Không có bản nào.</div>';
			list.querySelectorAll('[data-u]').forEach(function (b) { b.addEventListener('click', function () { unlock(Number(b.dataset.u), b); }); });
		};
		pane.querySelector('input').addEventListener('input', S.debounce(function (e) { query = e.target.value.trim(); draw(); }, 150));
		pane.querySelectorAll('[data-c]').forEach(function (b) {
			b.addEventListener('click', function () {
				filter = b.dataset.c;
				pane.querySelectorAll('[data-c]').forEach(function (x) { x.classList.toggle('on', x === b); });
				draw();
			});
		});
		draw();
	}

	function unlock(id, btn) {
		var P = me.point || 'điểm';
		var item = me.items.filter(function (i) { return i.id === id; })[0];
		if (!item) return;
		if (me.account.points < item.price) {
			S.sheet('<h3>Chưa đủ ' + esc(P) + '</h3><p>Bản này cần <b class="pts">' + num(item.price) + '</b>, bạn đang có <b>' + num(me.account.points) + '</b>.</p><p>Liên hệ <b>' + esc(me.contact) + '</b> để được cộng thêm điểm.</p><button class="btn full" data-close>Đóng</button>');
			return;
		}
		S.confirm('Mở khoá bản này?', esc(item.name) + '<br><br>Trừ <b class="pts">' + num(item.price) + ' ' + esc(P) + '</b>, còn lại ' + num(me.account.points - item.price) + '.', 'Mở khoá').then(function (ok) {
			if (!ok) return;
			S.busy(btn, api('/portal/unlock', { method: 'POST', body: { item_id: id } })).then(function (r) {
				me.account.points = r.balance;
				item.owned = true;
				render();
				S.sheet('<h3>Đã mở khoá 🎉</h3><p>' + esc(item.name) + '</p><p class="note" style="color:var(--text)">Mở app <b>' + esc(me.tinfoil.app) + '</b> trên Switch, chọn ô <b>Kho Việt hóa</b> là thấy và tải được ngay.</p><button class="btn pri full" data-close>OK</button>');
			}).catch(fail);
		});
	}

	function paneSetup() {
		var t = me.tinfoil;
		var kv = function (k, v) { return '<dt>' + esc(k) + '</dt><dd>' + esc(v) + '</dd><button class="copy" data-copy="' + esc(v) + '">Chép</button>'; };
		document.getElementById('pane').innerHTML =
			'<div class="card"><h2>Dùng app ' + esc(t.app) + '</h2>' +
			'<p class="note" style="color:var(--text);margin:0">1. ' + (t.app_url
				? 'Tải app tại <a href="' + esc(t.app_url) + '" target="_blank" rel="noopener">đây</a>, cài file NSP (hoặc chép bản NRO vào thẻ nhớ).'
				: 'Liên hệ <b>' + esc(me.contact) + '</b> để nhận app, cài file NSP (hoặc chép bản NRO vào thẻ nhớ).') + '<br>' +
			'2. Bật Wi-Fi có Internet, chỉnh đúng ngày giờ máy.<br>' +
			'3. Mở app, chọn <b>Kho Việt hóa</b>. Không cần điền địa chỉ.<br>' +
			'4. Lần đầu app hiện <b>mã kích hoạt</b> và <b>PIN web</b>. Gửi mã cho <b>' + esc(me.contact) + '</b>.<br>' +
			'5. Khi được kích hoạt: bấm <b>Kiểm tra lại</b> trong app là thấy danh sách, chọn bản rồi tải.<br>' +
			'Quên bước nào thì mở ô <b>Hướng dẫn kết nối</b> trong app.</p></div>' +
			'<div class="card"><h2>Hoặc dùng Tinfoil</h2>' +
			'<p>Trên Switch mở <b>Tinfoil → File Browser</b>, nhấn nút <b>(-)</b> rồi điền:</p><dl class="kv">' +
				kv('Protocol', t.protocol) + kv('Host', t.host) + kv('Port', String(t.port)) + kv('Path', t.path) +
				(t.code ? '<dt>Username</dt><dd style="color:var(--dim)">để trống</dd><span></span><dt>Password</dt><dd style="color:var(--dim)">để trống</dd><span></span>'
					: kv('Username', t.username) + '<dt>Password</dt><dd style="color:var(--dim)">mật khẩu của bạn</dd><span></span>') + kv('Title', t.title) +
			'</dl><p class="note" style="margin:12px 0 0">Nhấn <b>(X)</b> để lưu. Mở lại Tinfoil, các bản bạn đã mở khoá sẽ hiện trong mục <b>New Games</b> / kho <b>' + esc(t.title) + '</b>.</p></div>' +
			'<div class="card"><h2>Lưu ý</h2><p class="note" style="margin:0">• Mở khoá thêm bản mới: vào lại Kho Việt hóa trong app (hoặc kho trong Tinfoil) là thấy ngay.<br>• Tài khoản có thể bị giới hạn số máy Switch.<br>• Đừng chia sẻ mật khẩu cho người khác.</p></div>';
		document.getElementById('pane').addEventListener('click', function (e) {
			var c = e.target.closest('[data-copy]');
			if (c) S.copy(c.getAttribute('data-copy'));
		});
	}

	var TYPE = { topup: 'Cộng', unlock: 'Mở khoá', adjust: 'Điều chỉnh', refund: 'Hoàn' };

	function paneHist() {
		document.getElementById('pane').innerHTML = '<div class="list">' + (me.history.length ? me.history.map(function (h) {
			return '<div class="row"><div class="main"><div class="t">' + TYPE[h.type] + (h.note ? ' · ' + esc(h.note) : '') + '</div><div class="s">' + S.when(h.at) + ' · còn ' + num(h.balance) + '</div></div><div class="end">' + S.signed(h.delta) + '</div></div>';
		}).join('') : '<div class="empty">Chưa có giao dịch.</div>') + '</div>';
	}

	load();
})();
