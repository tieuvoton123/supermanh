/* SUPER MẠNH — management app (admin) */
(function () {
	'use strict';
	var S = window.SM, esc = S.esc, num = S.num, api = S.api, toast = S.toast, I = S.icon;
	var app = document.getElementById('app');
	var POINT = 'điểm';

	/* ---------------- Shell ---------------- */

	var TABS = [
		{ id: 'home', label: 'Tổng quan', icon: I.home },
		{ id: 'accounts', label: 'Khách', icon: I.users },
		{ id: 'items', label: 'Bản Việt hóa', icon: I.game },
		{ id: 'menu', label: 'Khác', icon: I.menu }
	];

	function shell(tab, title, sub, body, opts) {
		opts = opts || {};
		app.innerHTML =
			'<header class="top">' +
				(opts.back ? '<button class="back" data-back aria-label="Quay lại">‹</button>' : '<div class="logo"></div>') +
				'<h1>' + esc(title) + (sub ? '<small>' + sub + '</small>' : '') + '</h1>' + (opts.right || '') +
			'</header>' +
			'<main>' + body + '</main>' +
			(opts.fab ? '<button class="fab" data-fab aria-label="Thêm">+</button>' : '') +
			'<nav class="tabs">' + TABS.map(function (t) {
				return '<button class="' + (t.id === tab ? 'on' : '') + '" data-tab="' + t.id + '">' + t.icon + t.label + '</button>';
			}).join('') + '</nav>';
		var back = app.querySelector('[data-back]');
		if (back) back.addEventListener('click', function () { history.length > 1 ? history.back() : go('#/' + tab); });
	}

	function go(hash) { if (location.hash === hash) route(); else location.hash = hash; }

	app.addEventListener('click', function (e) {
		var t = e.target.closest('[data-tab]');
		if (t) go('#/' + t.getAttribute('data-tab'));
		var c = e.target.closest('[data-copy]');
		if (c && !e.target.closest('.sheet')) S.copy(c.getAttribute('data-copy'));
	});

	function loading() { app.querySelector('main') ? app.querySelector('main').innerHTML = '<div class="empty"><span class="spin" style="display:inline-block"></span></div>' : null; }

	function fail(e) {
		if (e && e.status === 401) { location.reload(); return; }
		toast(e && e.message ? e.message : 'Có lỗi', true);
	}

	/* ---------------- Home ---------------- */

	var TYPE = { topup: 'Nạp', unlock: 'Mở khoá', adjust: 'Trừ', refund: 'Hoàn' };

	function viewHome() {
		shell('home', 'SM Quản lý', 'SUPER MẠNH Switch Shop', '');
		loading();
		api('/admin/home').then(function (d) {
			POINT = d.point || POINT;
			var t = d.totals, sh = d.shop;
			var html =
				'<div class="stats">' +
					'<div class="stat"><b>' + num(d.items) + '</b><span>bản Việt hóa · ' + esc(d.size_h) + '</span></div>' +
					'<div class="stat"><b>' + num(d.accounts) + '</b><span>khách · ' + num(d.active7) + ' vào 7 ngày qua</span></div>' +
					'<div class="stat"><b>' + num(t.topup_month) + '</b><span>' + esc(POINT) + ' nạp tháng này</span></div>' +
					'<div class="stat"><b>' + num(t.unlock_month) + '</b><span>lượt mở khoá tháng này</span></div>' +
				'</div>' +
				'<button class="btn pri full" data-activate style="margin-bottom:8px">Kích hoạt mã máy</button>' +
				'<div class="grid2" style="margin-bottom:12px">' +
					'<button class="btn" data-new>+ Khách có TK</button>' +
					'<button class="btn" data-topup>Nạp ' + esc(POINT) + '</button>' +
				'</div>' +
				'<div class="sec"><h2>Hoạt động gần đây</h2></div>' +
				'<div class="list">' + (d.recent.length ? d.recent.map(function (r) {
					return '<button class="row" data-acc="' + r.account_id + '">' +
						'<div class="av' + (r.type === 'unlock' ? ' gray' : '') + '">' + (/^\w{4} \w{4}$/.test(r.username || '') ? '🎮' : esc((r.username || '?').charAt(0))) + '</div>' +
						'<div class="main"><div class="t">' + esc(r.username || '(đã xoá)') + ' · ' + TYPE[r.type] + '</div>' +
						'<div class="s">' + esc(r.note || '') + (r.note ? ' · ' : '') + S.when(r.at) + '</div></div>' +
						'<div class="end">' + S.signed(r.delta) + '</div></button>';
				}).join('') : '<div class="empty">Chưa có hoạt động.</div>') + '</div>' +
				'<div class="card"><h2>Shop trong Tinfoil</h2><dl class="kv">' +
					kv('Protocol', sh.protocol) + kv('Host', sh.host) + kv('Port', sh.port) + kv('Path', sh.path) +
				'</dl><p class="note" style="margin:10px 0 0">Trang khách: <a href="' + esc(d.portal) + '" target="_blank">' + esc(d.portal) + '</a></p></div>';
			app.querySelector('main').innerHTML = html;
			app.querySelector('[data-new]').addEventListener('click', newAccount);
			app.querySelector('[data-activate]').addEventListener('click', activateCode);
			app.querySelector('[data-topup]').addEventListener('click', function () { pickAccount(function (a) { topup(a.id, a.username); }); });
			app.querySelectorAll('[data-acc]').forEach(function (b) {
				b.addEventListener('click', function () { if (Number(b.dataset.acc)) go('#/account/' + b.dataset.acc); });
			});
		}).catch(fail);
	}

	/** Find a console by the code Tinfoil shows, then top it up / extend it. */
	function activateCode() {
		S.sheet('<h3>Kích hoạt mã máy</h3><p class="note">Nhập mã khách gửi (hiện trong Tinfoil, dạng XXXX XXXX).</p>' +
			'<div class="field"><input class="input" name="code" autocomplete="off" autocapitalize="characters" placeholder="ABCD 1234" style="font-size:22px;letter-spacing:.15em;text-transform:uppercase;text-align:center" autofocus></div>' +
			'<button class="btn pri full" data-ok>Tìm máy</button>', function (s) {
			var input = s.el.querySelector('[name=code]');
			var find = function (e) {
				var code = input.value.replace(/[\s-]+/g, '').toUpperCase();
				if (!/^[A-Z0-9]{8}$/.test(code)) { toast('Mã gồm 8 ký tự', true); return; }
				S.busy(e && e.currentTarget, api('/admin/accounts', { query: { s: code } })).then(function (list) {
					var hit = list.filter(function (a) { return a.kind === 'device' && a.username === code; })[0];
					if (!hit) { toast('Không thấy mã này. Khách đã mở shop trong Tinfoil chưa?', true); return; }
					s.close();
					go('#/account/' + hit.id);
					setTimeout(function () { quickActivate(hit); }, 350);
				}).catch(fail);
			};
			s.el.querySelector('[data-ok]').addEventListener('click', find);
			input.addEventListener('keydown', function (e) { if (e.key === 'Enter') find(); });
		});
	}

	function quickActivate(a) {
		S.sheet('<h3>Kích hoạt ' + esc(a.code) + '</h3><p class="note">Chọn cách kích hoạt cho máy này:</p>' +
			'<div class="list">' +
			'<button class="row" data-k="points"><div class="main"><div class="t">Nạp ' + esc(POINT) + '</div><div class="s">Khách tự mở khoá từng bản bằng ' + esc(POINT) + '</div></div><span class="chev">›</span></button>' +
			'<button class="row" data-k="gift"><div class="main"><div class="t">Tặng bản Việt hóa</div><div class="s">Chọn bản cụ thể, không trừ ' + esc(POINT) + '</div></div><span class="chev">›</span></button>' +
			'<button class="row" data-k="all"><div class="main"><div class="t">Gói thời hạn: xem tất cả</div><div class="s">Thấy mọi bản trong thời hạn bạn chọn</div></div><span class="chev">›</span></button>' +
			'</div>', function (s) {
			s.el.querySelectorAll('[data-k]').forEach(function (b) {
				b.addEventListener('click', function () {
					s.close();
					var k = b.dataset.k;
					if (k === 'points') topup(a.id, a.code, renderAccount);
					else if (k === 'gift') gift(a.id, renderAccount);
					else planSheet(a);
				});
			});
		});
	}

	function planSheet(a) {
		S.sheet('<h3>Gói xem tất cả · ' + esc(a.code || a.username) + '</h3>' +
			'<div class="grid3" style="margin-bottom:12px">' + [[30, '1 tháng'], [90, '3 tháng'], [180, '6 tháng'], [365, '1 năm'], [0, 'Vĩnh viễn']].map(function (x) {
				return '<button class="btn" data-d="' + x[0] + '">' + x[1] + '</button>';
			}).join('') + '</div><p class="note">Gia hạn cộng dồn từ ngày hết hạn hiện tại.</p>', function (s) {
			s.el.querySelectorAll('[data-d]').forEach(function (b) {
				b.addEventListener('click', function () {
					var d = Number(b.dataset.d);
					var steps = [];
					if (!a.all_access) steps.push({ action: 'all_access' });
					steps.push(d ? { action: 'extend', days: d } : { action: 'forever' });
					var run = steps.reduce(function (p, body) {
						return p.then(function () { return api('/admin/accounts/' + a.id, { method: 'POST', body: body }); });
					}, Promise.resolve());
					S.busy(b, run).then(function (nd) { s.close(); toast('Đã kích hoạt ' + (a.code || a.username)); renderAccount(nd); }).catch(fail);
				});
			});
		});
	}

	function kv(k, v) {
		return '<dt>' + esc(k) + '</dt><dd>' + esc(v) + '</dd><button class="copy" data-copy="' + esc(v) + '">Chép</button>';
	}

	/* ---------------- Accounts ---------------- */

	var accQuery = '';

	function viewAccounts() {
		shell('accounts', 'Khách hàng', '', '<div class="search">' + I.search + '<input class="input" type="search" placeholder="Tìm tên, ghi chú…" value="' + esc(accQuery) + '"></div><div id="acc-list"></div>', { fab: true });
		var input = app.querySelector('.search input');
		var load = function () {
			api('/admin/accounts', { query: { s: accQuery } }).then(function (list) {
				var box = document.getElementById('acc-list');
				if (!box) return;
				box.innerHTML = '<div class="list">' + (list.length ? list.map(accRow).join('') : '<div class="empty">Chưa có khách nào.<br>Bấm + để tạo.</div>') + '</div>';
				box.querySelectorAll('[data-acc]').forEach(function (b) { b.addEventListener('click', function () { go('#/account/' + b.dataset.acc); }); });
			}).catch(fail);
		};
		input.addEventListener('input', S.debounce(function () { accQuery = input.value.trim(); load(); }, 250));
		app.querySelector('[data-fab]').addEventListener('click', newAccount);
		load();
	}

	function accRow(a) {
		var tags = [];
		if (!a.enabled) tags.push('<span class="pill bad">Khoá</span>');
		if (a.expired) tags.push('<span class="pill bad">Hết hạn</span>');
		if (a.all_access) tags.push('<span class="pill hot">Xem tất cả</span>');
		if (a.kind === 'device') tags.unshift('<span class="pill">Máy</span>');
		return '<button class="row" data-acc="' + a.id + '">' +
			'<div class="av' + (a.enabled && !a.expired ? '' : ' gray') + '">' + (a.kind === 'device' ? '🎮' : esc(a.username.charAt(0))) + '</div>' +
			'<div class="main"><div class="t">' + esc(a.code || a.username) + ' ' + tags.join(' ') + '</div>' +
			'<div class="s">' + (a.note ? esc(a.note) + ' · ' : '') + num(a.owned_count) + ' bản · ' + (a.last_seen ? 'vào ' + S.when(a.last_seen) : 'chưa vào') + '</div></div>' +
			'<div class="end"><div class="pts">' + num(a.points) + '</div><div class="s" style="font-size:11px;color:var(--mute)">' + esc(POINT) + '</div></div></button>';
	}

	function newAccount() {
		var pass = Math.random().toString(36).slice(2, 7) + Math.random().toString(36).slice(2, 7);
		S.sheet(
			'<h3>Khách mới</h3>' +
			'<div class="field"><label>Tên đăng nhập</label><input class="input" name="username" autocomplete="off" autocapitalize="none" placeholder="vd: tuan.zalo" autofocus></div>' +
			'<div class="field"><label>Mật khẩu</label><input class="input" name="password" value="' + pass + '" autocapitalize="none"></div>' +
			'<div class="field"><label>Nạp sẵn ' + esc(POINT) + '</label><input class="input" name="points" type="number" inputmode="numeric" min="0" placeholder="0"></div>' +
			'<div class="field"><label>Ghi chú (tên, Zalo, SĐT…)</label><input class="input" name="note"></div>' +
			'<div class="grid2"><div class="field"><label>Hết hạn</label><input class="input" name="expires" type="date"></div>' +
			'<div class="field"><label>Số máy tối đa</label><input class="input" name="max_devices" type="number" inputmode="numeric" min="0" value="1"></div></div>' +
			'<label class="switch"><span>Xem tất cả bản (không cần mở khoá)</span><input type="checkbox" name="all_access"></label>' +
			'<p class="note">Để trống "Hết hạn" là dùng vĩnh viễn. Số máy = 0 là không giới hạn.</p>' +
			'<button class="btn pri full" data-save>Tạo tài khoản</button>',
			function (s) {
				var f = function (n) { return s.el.querySelector('[name=' + n + ']'); };
				s.el.querySelector('[data-save]').addEventListener('click', function (e) {
					var body = {
						username: f('username').value.trim(), password: f('password').value.trim(), points: Number(f('points').value || 0),
						note: f('note').value.trim(), expires: f('expires').value, max_devices: Number(f('max_devices').value || 0), all_access: f('all_access').checked
					};
					S.busy(e.currentTarget, api('/admin/accounts', { method: 'POST', body: body })).then(function (d) {
						s.close();
						showShare(d, 'Đã tạo tài khoản');
						if (location.hash.indexOf('#/accounts') === 0) viewAccounts();
					}).catch(fail);
				});
			});
	}

	function showShare(d, title) {
		var msg = d.account && d.account.kind === 'device'
			? [(d.tinfoil.title || 'SUPER MẠNH Shop') + ' — đã kích hoạt máy ' + d.account.code,
				'Tắt hẳn Tinfoil rồi mở lại là thấy các bản Việt hóa của bạn.',
				'', 'Xem ' + POINT + ' và mở khoá thêm bản:', d.portal].join('\n')
			: S.accountMessage({ tinfoil: d.tinfoil, password: d.password, portal: d.portal });
		S.sheet('<h3>' + esc(title) + '</h3><p class="note">Gửi tin này cho khách qua Zalo/Messenger:</p><div class="share">' + esc(msg) + '</div>' +
			'<div class="grid2"><button class="btn" data-c>Chép</button><button class="btn pri" data-s>Gửi…</button></div>' +
			(d.account ? '<button class="btn ghost full" style="margin-top:8px" data-open>Mở tài khoản</button>' : ''),
			function (s) {
				s.el.querySelector('[data-c]').addEventListener('click', function () { S.copy(msg); });
				s.el.querySelector('[data-s]').addEventListener('click', function () { S.share(msg); });
				var o = s.el.querySelector('[data-open]');
				if (o) o.addEventListener('click', function () { s.close(); go('#/account/' + d.account.id); });
			});
	}

	function pickAccount(cb) {
		var s = S.sheet('<h3>Chọn khách</h3><div class="search">' + I.search + '<input class="input" type="search" placeholder="Tìm…" autofocus></div><div class="list" data-l style="max-height:55vh;overflow:auto"></div>');
		var input = s.el.querySelector('input');
		var load = function () {
			api('/admin/accounts', { query: { s: input.value.trim() } }).then(function (list) {
				var l = s.el.querySelector('[data-l]');
				l.innerHTML = list.length ? list.map(accRow).join('') : '<div class="empty">Không thấy khách.</div>';
				l.querySelectorAll('[data-acc]').forEach(function (b) {
					b.addEventListener('click', function () {
						var a = list.filter(function (x) { return String(x.id) === b.dataset.acc; })[0];
						s.close(); cb(a);
					});
				});
			}).catch(fail);
		};
		input.addEventListener('input', S.debounce(load, 250));
		load();
	}

	/* ---------------- Account detail ---------------- */

	function viewAccount(id) {
		shell('accounts', 'Khách hàng', '', '', { back: true });
		loading();
		api('/admin/accounts/' + id).then(function (d) { renderAccount(d); }).catch(fail);
	}

	function renderAccount(d) {
		var a = d.account;
		var tags = [];
		tags.push(a.enabled ? '<span class="pill ok">Đang hoạt động</span>' : '<span class="pill bad">Đã khoá</span>');
		tags.push(a.expired ? '<span class="pill bad">Hết hạn ' + S.day(a.expires_at) + '</span>' : '<span class="pill">Hạn: ' + S.day(a.expires_at) + '</span>');
		if (a.kind !== 'device') tags.push('<span class="pill">Máy ' + a.devices + '/' + (a.max_devices || '∞') + '</span>');
		if (a.all_access) tags.push('<span class="pill hot">Xem tất cả</span>');

		shell('accounts', a.code || a.username, esc(a.note || (a.kind === 'device' ? 'Kích hoạt theo máy' : '')), '', { back: true, right: '<button class="act" data-more>Tuỳ chọn</button>' });
		var main = app.querySelector('main');
		main.innerHTML =
			'<div class="hero"><div class="lbl">Số dư</div><div class="big">' + num(a.points) + ' <small>' + esc(POINT) + '</small></div><div class="meta">' + tags.join('') + '</div></div>' +
			'<div class="grid3" style="margin-bottom:12px">' +
				'<button class="btn pri" data-topup>Nạp</button>' +
				'<button class="btn" data-gift>' + (a.kind === 'device' ? 'Kích hoạt' : 'Tặng bản') + '</button>' +
				'<button class="btn" data-share>Gửi TK</button>' +
			'</div>' +
			'<div class="sec"><h2>Đã mở khoá (' + d.owned.length + ')</h2></div>' +
			'<div class="list">' + (d.owned.length ? d.owned.map(function (i) {
				return '<div class="row">' + S.cover(i.icon) + '<div class="main"><div class="t">' + esc(i.name) + '</div><div class="s">' + esc(i.size_h) + ' · ' + S.when(i.granted_at) + (i.enabled ? '' : ' · <span class="pill warn">Đang ẩn khỏi shop</span>') + '</div></div>' +
					'<button class="btn sm ghost" data-revoke="' + i.id + '">Gỡ</button></div>';
			}).join('') : '<div class="empty">' + (a.all_access ? 'Tài khoản "Xem tất cả": thấy mọi bản trong Tinfoil.' : 'Chưa mở khoá bản nào.') + '</div>') + '</div>' +
			'<div class="sec"><h2>Lịch sử ' + esc(POINT) + '</h2></div>' +
			'<div class="list">' + (d.history.length ? d.history.map(function (h) {
				return '<div class="row"><div class="main"><div class="t">' + TYPE[h.type] + (h.note ? ' · ' + esc(h.note) : '') + '</div><div class="s">' + S.when(h.at) + ' · còn ' + num(h.balance) + '</div></div>' +
					'<div class="end">' + S.signed(h.delta) + '</div></div>';
			}).join('') : '<div class="empty">Chưa có giao dịch.</div>') + '</div>' +
			'<div class="card"><h2>Lần cuối vào shop</h2><div>' + (a.last_seen ? S.when(a.last_seen) + ' · IP ' + esc(a.last_ip) + ' · ' + num(a.hits) + ' lượt' + (a.client_ver ? ' · Tinfoil ' + esc(a.client_ver) : '') : 'Chưa vào lần nào') + '</div>' +
'</div>';

		main.querySelector('[data-topup]').addEventListener('click', function () { topup(a.id, a.username, renderAccount); });
		main.querySelector('[data-gift]').addEventListener('click', function () { if (a.kind === 'device') quickActivate(a); else gift(a.id, renderAccount); });
		main.querySelector('[data-share]').addEventListener('click', function () { showShare({ tinfoil: d.tinfoil, portal: d.portal, account: a }, 'Thông tin tài khoản'); });
		main.querySelectorAll('[data-revoke]').forEach(function (b) {
			b.addEventListener('click', function () {
				S.sheet('<h3>Gỡ bản này khỏi ' + esc(a.username) + '?</h3><p class="note">Bản sẽ biến mất khỏi Tinfoil của khách.</p>' +
					'<button class="btn bad full" data-r="1">Gỡ và hoàn ' + esc(POINT) + '</button><div style="height:8px"></div>' +
					'<button class="btn ghost full" data-r="0">Gỡ, không hoàn</button>',
					function (s) {
						s.el.querySelectorAll('[data-r]').forEach(function (x) {
							x.addEventListener('click', function () {
								S.busy(x, api('/admin/accounts/' + a.id + '/revoke', { method: 'POST', body: { item_id: Number(b.dataset.revoke), refund: x.dataset.r === '1' } }))
									.then(function (nd) { s.close(); toast('Đã gỡ'); renderAccount(nd); }).catch(fail);
							});
						});
					});
			});
		});
		app.querySelector('[data-more]').addEventListener('click', function () { moreActions(d); });
	}

	function topup(id, username, after) {
		S.sheet(
			'<h3>Nạp ' + esc(POINT) + ' cho ' + esc(username) + '</h3>' +
			'<div class="grid3" style="margin-bottom:12px">' + [10, 20, 50, 100, 200, 500].map(function (n) {
				return '<button class="btn" data-q="' + n + '">+' + n + '</button>';
			}).join('') + '</div>' +
			'<div class="field"><label>Số ' + esc(POINT) + ' (số âm để trừ)</label><input class="input" name="amount" type="number" inputmode="numeric" autofocus></div>' +
			'<div class="field"><label>Ghi chú</label><input class="input" name="note" placeholder="vd: CK 100k MB 26/11"></div>' +
			'<button class="btn pri full" data-ok>Xác nhận</button>',
			function (s) {
				var amt = s.el.querySelector('[name=amount]');
				s.el.querySelectorAll('[data-q]').forEach(function (b) {
					b.addEventListener('click', function () { amt.value = Number(amt.value || 0) + Number(b.dataset.q); });
				});
				s.el.querySelector('[data-ok]').addEventListener('click', function (e) {
					var n = Number(amt.value || 0);
					if (!n) { toast('Nhập số ' + POINT, true); return; }
					S.busy(e.currentTarget, api('/admin/accounts/' + id + '/points', { method: 'POST', body: { amount: n, note: s.el.querySelector('[name=note]').value.trim() } }))
						.then(function (d) {
							s.close();
							toast((n > 0 ? 'Đã nạp ' : 'Đã trừ ') + num(Math.abs(n)) + ' · còn ' + num(d.account.points));
							if (after) after(d); else if (location.hash === '#/home' || location.hash === '') viewHome();
						}).catch(fail);
				});
			});
	}

	function gift(id, after) {
		var chosen = {};
		var s = S.sheet('<h3>Tặng bản (không trừ ' + esc(POINT) + ')</h3><div class="search">' + I.search + '<input class="input" type="search" placeholder="Tìm bản Việt hóa…"></div>' +
			'<div class="list" data-l style="max-height:50vh;overflow:auto"></div><button class="btn pri full" data-ok>Tặng</button>');
		var input = s.el.querySelector('input');
		var load = function () {
			api('/admin/items', { query: { s: input.value.trim() } }).then(function (d) {
				var l = s.el.querySelector('[data-l]');
				l.innerHTML = d.items.length ? d.items.map(function (i) {
					return '<label class="row"><div class="main"><div class="t">' + esc(i.name) + '</div><div class="s">' + esc(i.size_h) + ' · ' + (i.is_free ? 'miễn phí' : num(i.price) + ' ' + esc(POINT)) + (i.enabled ? '' : ' · <span class="pill warn">Đang ẩn</span>') + '</div></div>' +
						'<input type="checkbox" value="' + i.id + '" ' + (chosen[i.id] ? 'checked' : '') + ' style="width:22px;height:22px;accent-color:#ff5a1f"></label>';
				}).join('') : '<div class="empty">Không có bản nào.</div>';
				l.querySelectorAll('input').forEach(function (c) { c.addEventListener('change', function () { if (c.checked) chosen[c.value] = 1; else delete chosen[c.value]; }); });
			}).catch(fail);
		};
		input.addEventListener('input', S.debounce(load, 250));
		load();
		s.el.querySelector('[data-ok]').addEventListener('click', function (e) {
			var ids = Object.keys(chosen).map(Number);
			if (!ids.length) { toast('Chọn ít nhất 1 bản', true); return; }
			S.busy(e.currentTarget, api('/admin/accounts/' + id + '/grant', { method: 'POST', body: { item_ids: ids } }))
				.then(function (d) { s.close(); toast('Đã tặng ' + ids.length + ' bản'); after(d); }).catch(fail);
		});
	}

	function moreActions(d) {
		var a = d.account;
		var acts = [
			['plan', 'Gói thời hạn: xem tất cả'],
			['extend', 'Gia hạn +30 ngày'],
			['forever', 'Dùng vĩnh viễn'],
			['all_access', a.all_access ? 'Tắt "Xem tất cả"' : 'Bật "Xem tất cả bản"'],
			['reset_devices', 'Xoá danh sách máy (' + a.devices + ')'],
			['new_password', 'Tạo mật khẩu mới'],
			['new_key', 'Đổi link riêng'],
			['note', a.kind === 'device' ? 'Sửa ghi chú' : 'Sửa ghi chú / số máy'],
			['toggle', a.enabled ? 'Khoá tài khoản' : 'Mở khoá tài khoản'],
			['delete', 'Xoá tài khoản']
		];
		if (a.kind === 'device') {
			acts = acts.filter(function (x) { return ['reset_devices', 'new_password'].indexOf(x[0]) < 0; });
		}
		S.sheet('<h3>' + esc(a.code || a.username) + '</h3><div class="list">' + acts.map(function (x) {
			return '<button class="row" data-a="' + x[0] + '"><div class="main"><div class="t" style="' + (x[0] === 'delete' || (x[0] === 'toggle' && a.enabled) ? 'color:#f87171' : '') + '">' + esc(x[1]) + '</div></div><span class="chev">›</span></button>';
		}).join('') + '</div>', function (s) {
			s.el.querySelectorAll('[data-a]').forEach(function (b) {
				b.addEventListener('click', function () {
					var act = b.dataset.a;
					s.close();
					if (act === 'delete') {
						S.confirm('Xoá ' + a.username + '?', 'Mất toàn bộ ' + POINT + ', các bản đã mở khoá và lịch sử. Không hoàn tác được.', 'Xoá', true).then(function (ok) {
							if (!ok) return;
							api('/admin/accounts/' + a.id, { method: 'DELETE' }).then(function () { toast('Đã xoá'); go('#/accounts'); }).catch(fail);
						});
						return;
					}
					if (act === 'note') { editNote(a); return; }
					if (act === 'plan') { planSheet(a); return; }
					var body = { action: act };
					if (act === 'extend') body.days = 30;
					api('/admin/accounts/' + a.id, { method: 'POST', body: body }).then(function (nd) {
						renderAccount(nd);
						if (nd.password) {
							showShare({ tinfoil: nd.tinfoil, portal: nd.portal, password: nd.password }, 'Mật khẩu mới');
						} else if (act === 'new_key') {
							showShare({ tinfoil: nd.tinfoil, portal: nd.portal }, 'Link mới (link cũ đã hết hiệu lực)');
						} else {
							toast('Đã cập nhật');
						}
					}).catch(fail);
				});
			});
		});
	}

	function editNote(a) {
		S.sheet('<h3>Sửa ' + esc(a.code || a.username) + '</h3>' +
			'<div class="field"><label>Ghi chú (tên khách, Zalo…)</label><input class="input" name="note" value="' + esc(a.note) + '"></div>' +
			'<div class="field"' + (a.kind === 'device' ? ' style="display:none"' : '') + '><label>Số máy tối đa (0 = không giới hạn)</label><input class="input" name="max" type="number" inputmode="numeric" min="0" value="' + a.max_devices + '"></div>' +
			'<button class="btn pri full" data-ok>Lưu</button>', function (s) {
			s.el.querySelector('[data-ok]').addEventListener('click', function (e) {
				S.busy(e.currentTarget, api('/admin/accounts/' + a.id, { method: 'POST', body: { action: 'update', note: s.el.querySelector('[name=note]').value, max_devices: Number(s.el.querySelector('[name=max]').value || 0) } }))
					.then(function (nd) { s.close(); toast('Đã lưu'); renderAccount(nd); }).catch(fail);
			});
		});
	}

	/* ---------------- Items ---------------- */

	var itemQuery = '', itemPage = 1;

	function viewItems() {
		shell('items', 'Bản Việt hóa', '', '<div class="search">' + I.search + '<input class="input" type="search" placeholder="Tìm tên hoặc Title ID…" value="' + esc(itemQuery) + '"></div>' +
			'<div class="grid3" style="margin-bottom:12px"><button class="btn pri" data-add>+ Link</button><button class="btn" data-import>Nhập DS</button><button class="btn" data-sync>Quét Drive</button></div><div id="item-list"></div>');
		app.querySelector('[data-import]').addEventListener('click', importList);
		var input = app.querySelector('.search input');
		input.addEventListener('input', S.debounce(function () { itemQuery = input.value.trim(); itemPage = 1; loadItems(); }, 250));
		app.querySelector('[data-add]').addEventListener('click', addLinks);
		app.querySelector('[data-sync]').addEventListener('click', function (e) {
			S.busy(e.currentTarget, api('/admin/folders/sync', { method: 'POST' })).then(function (d) {
				var errs = d.folders.filter(function (f) { return f.error; });
				toast(d.folders.length ? (errs.length ? errs[0].error : 'Đã quét ' + d.folders.length + ' thư mục') : 'Chưa có thư mục Drive', !!errs.length);
				loadItems();
			}).catch(fail);
		});
		loadItems();
	}

	function loadItems() {
		api('/admin/items', { query: { s: itemQuery, page: itemPage } }).then(function (d) {
			var box = document.getElementById('item-list');
			if (!box) return;
			box.innerHTML = '<p class="note">' + num(d.total) + ' bản' + (d.pages > 1 ? ' · trang ' + d.page + '/' + d.pages : '') + '</p>' +
				'<div class="list">' + (d.items.length ? d.items.map(function (i) {
					var price = i.is_free ? '<span class="pill ok">Miễn phí</span>' : (i.price ? '<span class="pts">' + num(i.price) + '</span>' : '<span class="pill warn">Chưa giá</span>');
					return '<button class="row" data-item=\'' + esc(JSON.stringify(i)) + '\'>' + S.cover(i.icon) + '<div class="main"><div class="t" style="' + (i.enabled ? '' : 'opacity:.5') + '">' + esc(i.display || i.name) + '</div>' +
						'<div class="s">' + esc(i.size_h) + (i.title_id ? ' · ' + esc(i.title_id) : '') + (i.enabled ? '' : ' · <span class="pill">Ẩn</span>') + '</div></div><div class="end">' + price + '</div></button>';
				}).join('') : '<div class="empty">Chưa có bản nào. Bấm "+ Thêm link".</div>') + '</div>' +
				(d.pages > 1 ? '<div class="grid2"><button class="btn" data-p="-1" ' + (d.page <= 1 ? 'disabled' : '') + '>‹ Trước</button><button class="btn" data-p="1" ' + (d.page >= d.pages ? 'disabled' : '') + '>Sau ›</button></div>' : '');
			box.querySelectorAll('[data-item]').forEach(function (b) { b.addEventListener('click', function () { editItem(JSON.parse(b.dataset.item)); }); });
			box.querySelectorAll('[data-p]').forEach(function (b) { b.addEventListener('click', function () { itemPage += Number(b.dataset.p); loadItems(); window.scrollTo(0, 0); }); });
		}).catch(fail);
	}

	function editItem(i) {
		S.sheet('<h3 style="word-break:break-word">' + esc(i.name) + '</h3><p class="note">' + (i.game ? 'Game: <b>' + esc(i.game) + '</b> · ' : '') + esc(i.size_h) + (i.title_id ? ' · ' + esc(i.title_id) : '') + ' · ' + esc(i.source || '') + '</p>' +
			'<div class="field"><label>Giá (' + esc(POINT) + ')</label><input class="input" name="price" type="number" inputmode="numeric" min="0" value="' + i.price + '"></div>' +
			'<div class="grid3" style="margin-bottom:6px">' + [10, 20, 30, 50, 80, 100].map(function (n) { return '<button class="btn sm" data-q="' + n + '">' + n + '</button>'; }).join('') + '</div>' +
			'<label class="switch"><span>Miễn phí (ai cũng thấy)</span><input type="checkbox" name="is_free" ' + (i.is_free ? 'checked' : '') + '></label>' +
			'<label class="switch"><span>Đang bán</span><input type="checkbox" name="enabled" ' + (i.enabled ? 'checked' : '') + '></label>' +
			'<div class="field"><label>Tên hiện trong Tinfoil (không bắt buộc)</label><input class="input" name="display" value="' + esc(i.display || '') + '" placeholder="vd: Tên game (Việt hóa SUPER MẠNH)"></div>' +
			'<div class="field"><label>Tên file (Tinfoil đọc Title ID trong [ ])</label><input class="input" name="name" value="' + esc(i.name) + '"></div>' +
			'<button class="btn pri full" data-ok>Lưu</button>', function (s) {
			var price = s.el.querySelector('[name=price]');
			s.el.querySelectorAll('[data-q]').forEach(function (b) { b.addEventListener('click', function () { price.value = b.dataset.q; }); });
			s.el.querySelector('[data-ok]').addEventListener('click', function (e) {
				var body = {
					price: Number(price.value || 0), is_free: s.el.querySelector('[name=is_free]').checked,
					enabled: s.el.querySelector('[name=enabled]').checked, display: s.el.querySelector('[name=display]').value, name: s.el.querySelector('[name=name]').value
				};
				S.busy(e.currentTarget, api('/admin/items/' + i.id, { method: 'POST', body: body })).then(function () { s.close(); toast('Đã lưu'); loadItems(); }).catch(fail);
			});
		});
	}

	var SAMPLE = [
		{ name: 'Ten game Viet hoa.nsp', title_id: '0100ABCD12340000', link: 'https://drive.google.com/file/d/ID_FILE_TREN_DRIVE/view', price: 30, free: 0, display: '' },
		{ link: 'https://drive.google.com/drive/folders/ID_THU_MUC', price: 50 }
	];

	function importList() {
		S.sheet('<h3>Nhập danh sách</h3>' +
			'<p class="note">File <b>JSON</b> hoặc <b>CSV</b> (có dòng tiêu đề). Cột: <b>link</b> (bắt buộc), name, title_id, price, free, display. Tên game và ảnh bìa tự tra theo Title ID.</p>' +
			'<label class="file">Chọn file .json / .csv<input type="file" accept=".json,.csv,.txt,application/json,text/csv"></label>' +
			'<div class="field"><label>…hoặc dán nội dung</label><textarea class="input" name="text" placeholder=\'[{"name":"...","title_id":"0100...","link":"https://drive.google.com/file/d/...","price":30}]\'></textarea></div>' +
			'<div class="field"><label>Giá mặc định cho bản mới chưa có giá (' + esc(POINT) + ')</label><input class="input" name="price" type="number" inputmode="numeric" min="0" placeholder="0"></div>' +
			'<button class="btn pri full" data-ok>Nhập</button>' +
			'<div class="grid3" style="margin-top:8px"><button class="btn sm ghost" data-sample>File mẫu</button><button class="btn sm ghost" data-export>Xuất JSON</button><button class="btn sm ghost" data-titles>Tra lại tên</button></div>',
			function (s) {
				var ta = s.el.querySelector('[name=text]');
				s.el.querySelector('input[type=file]').addEventListener('change', function (e) {
					var f = e.target.files[0];
					if (!f) return;
					var r = new FileReader();
					r.onload = function () { ta.value = r.result; toast('Đã đọc ' + f.name); };
					r.readAsText(f);
				});
				s.el.querySelector('[data-sample]').addEventListener('click', function () { S.download('mau_nhap_supermanh.json', JSON.stringify(SAMPLE, null, 2)); });
				s.el.querySelector('[data-export]').addEventListener('click', function (e) {
					S.busy(e.currentTarget, api('/admin/export')).then(function (d) { S.download('supermanh_shop.json', JSON.stringify(d.items, null, 2)); }).catch(fail);
				});
				s.el.querySelector('[data-titles]').addEventListener('click', function (e) {
					S.busy(e.currentTarget, api('/admin/titles', { method: 'POST' })).then(function (d) { toast('Đã tra được tên cho ' + d.named + ' bản'); loadItems(); }).catch(fail);
				});
				s.el.querySelector('[data-ok]').addEventListener('click', function (e) {
					if (!ta.value.trim()) { toast('Chọn file hoặc dán nội dung', true); return; }
					S.busy(e.currentTarget, api('/admin/import', { method: 'POST', body: { text: ta.value, price: Number(s.el.querySelector('[name=price]').value || 0) } })).then(function (r) {
						s.close();
						var msg = 'Thêm ' + r.added + ', cập nhật ' + r.updated + (r.folders ? ', ' + r.folders + ' thư mục' : '') + ' · tra được tên ' + r.named + ' bản';
						if (r.errors.length) {
							S.sheet('<h3>' + esc(msg) + '</h3><p class="note">Có ' + r.errors.length + ' dòng lỗi:</p><div class="share">' + esc(r.errors.slice(0, 50).join('\n')) + '</div><button class="btn full" data-close>Đóng</button>');
						} else { toast(msg); }
						loadItems();
					}).catch(fail);
				});
			});
	}

	function addLinks() {
		S.sheet('<h3>Thêm link</h3><div class="field"><label>Mỗi dòng một link (file hoặc thư mục Google Drive, link tải trực tiếp)</label>' +
			'<textarea class="input" name="lines" placeholder="https://drive.google.com/file/d/…\nhttps://drive.google.com/drive/folders/…\nhttps://host.com/patch.nsp | Tên [TitleID][v0].nsp | 200MB"></textarea></div>' +
			'<div class="field"><label>Giá mặc định cho bản mới (' + esc(POINT) + ')</label><input class="input" name="price" type="number" inputmode="numeric" min="0" placeholder="0"></div>' +
			'<button class="btn pri full" data-ok>Thêm</button>', function (s) {
			s.el.querySelector('[data-ok]').addEventListener('click', function (e) {
				var lines = s.el.querySelector('[name=lines]').value;
				if (!lines.trim()) { toast('Dán link vào', true); return; }
				S.busy(e.currentTarget, api('/admin/items', { method: 'POST', body: { lines: lines, price: Number(s.el.querySelector('[name=price]').value || 0) } })).then(function (r) {
					s.close();
					var msg = 'Thêm ' + r.added + ', cập nhật ' + r.updated + (r.folders ? ', ' + r.folders + ' thư mục' : '');
					if (r.errors.length) {
						S.sheet('<h3>' + esc(msg) + '</h3><p class="note">Có ' + r.errors.length + ' dòng lỗi:</p><div class="share">' + esc(r.errors.join('\n')) + '</div><button class="btn full" data-close>Đóng</button>');
					} else { toast(msg); }
					loadItems();
				}).catch(fail);
			});
		});
	}

	/* ---------------- Menu ---------------- */

	function viewMenu() {
		shell('menu', 'Khác', '', '<div class="list">' +
			'<a class="row" href="' + esc(S.C.wpAdmin) + '"><div class="main"><div class="t">Cài đặt shop</div><div class="s">Tên shop, API key, tên miền, ẩn shop…</div></div><span class="chev">›</span></a>' +
			'<a class="row" href="' + esc(S.C.wpAdmin.replace('page=smss', 'page=smss-folders')) + '"><div class="main"><div class="t">Thư mục Google Drive</div><div class="s">Thêm, quét lại, gỡ thư mục</div></div><span class="chev">›</span></a>' +
			'<button class="row" data-install><div class="main"><div class="t">Cài app lên màn hình chính</div><div class="s">Mở nhanh như app thật</div></div><span class="chev">›</span></button>' +
			'<a class="row" href="' + esc(S.C.logout) + '"><div class="main"><div class="t" style="color:#f87171">Đăng xuất</div></div></a>' +
			'</div><p class="note" style="text-align:center">SUPER MẠNH Switch Shop · supermanh.com</p>');
		app.querySelector('[data-install]').addEventListener('click', install);
	}

	var deferredPrompt = null;
	window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferredPrompt = e; });
	function install() {
		if (deferredPrompt) { deferredPrompt.prompt(); deferredPrompt = null; return; }
		S.sheet('<h3>Cài lên màn hình chính</h3><p><b>iPhone (Safari):</b> bấm nút Chia sẻ <b>⬆︎</b> → <b>Thêm vào MH chính</b>.</p><p><b>Android (Chrome):</b> bấm <b>⋮</b> → <b>Thêm vào màn hình chính</b> / <b>Cài đặt ứng dụng</b>.</p><button class="btn full" data-close>Đã hiểu</button>');
	}

	/* ---------------- Router ---------------- */

	function route() {
		var h = location.hash || '#/home';
		var m;
		window.scrollTo(0, 0);
		if ((m = h.match(/^#\/account\/(\d+)/))) return viewAccount(m[1]);
		if (h.indexOf('#/accounts') === 0) return viewAccounts();
		if (h.indexOf('#/items') === 0) return viewItems();
		if (h.indexOf('#/menu') === 0) return viewMenu();
		return viewHome();
	}
	window.addEventListener('hashchange', route);
	route();
})();
