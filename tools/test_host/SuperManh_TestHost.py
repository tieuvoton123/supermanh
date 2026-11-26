#!/usr/bin/env python3
"""SUPER MANH Test Host - may chu thu nghiem chay tren PC trong mang LAN.

Phat danh sach (dinh dang Tinfoil) cho app SUPER MANH Viet Hoa / Tinfoil / CyberFoil:
  - File trong thu muc "files" (nsp/nsz/xci/xcz) -> tai truc tiep tu PC, co ho tro tai tiep (Range).
  - Link tai truc tiep ghi trong "links.txt" (http/https hoac gdrive:ID).
  - Gia lap kich hoat theo may: may moi hien MA KICH HOAT, bat trong trang /quanly.

Chi dung thu vien chuan Python 3.8+. Chay: python SuperManh_TestHost.py
"""
import base64
import secrets
import hashlib
import html
import ipaddress
import json
import mimetypes
import os
import socket
import sys
import threading
import time
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

BASE = os.path.dirname(os.path.abspath(sys.executable if getattr(sys, 'frozen', False) else __file__))
CONFIG_PATH = os.path.join(BASE, 'config.json')
DEVICES_PATH = os.path.join(BASE, 'devices.json')
LINKS_PATH = os.path.join(BASE, 'links.txt')
EXTS = ('.nsp', '.nsz', '.xci', '.xcz')
ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'  # no 0/O/1/I

DEFAULT_CONFIG = {
    'port': 8000,
    'path': 'tinfoil',
    'files_dir': 'files',
    'title': 'SUPER MANH TEST',
    'activation': True,
    'contact': 'SUPER MANH',
    # Public address in front of this PC (Cloudflare Tunnel / domain / port forward),
    # e.g. https://files.supermanh.com . Empty = LAN address of this PC.
    'public_url': '',
    # Secret part of every download link: /f/<link_key>/<file>. Generated on first run.
    'link_key': '',
    # Wrong download keys from one IP before it is blocked (TinfoilWebServer-style blacklist). 0 = off.
    'max_bad_requests': 20,
}

LINKS_SAMPLE = """# Moi dong mot link tai truc tiep. Dong bat dau bang # la ghi chu.
# Cach ghi:   link | Ten file [TitleID][v0].nsp | dung luong (byte, khong bat buoc)
# Vi du:
# https://example.com/files/Game Viet hoa [0100ABCD12340000][v0].nsp | Game Viet hoa [0100ABCD12340000][v0].nsp | 123456789
# gdrive:1AbCdEfGhIjKlMnOp | Game Viet hoa [0100ABCD12340000][v0].nsz
"""

lock = threading.Lock()
bad_hits = {}      # ip -> count of wrong download keys
blocked = set()


def load_json(path, default):
    try:
        with open(path, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception:
        return default


def save_json(path, data):
    tmp = path + '.tmp'
    with open(tmp, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    os.replace(tmp, path)


def config():
    cfg = dict(DEFAULT_CONFIG)
    cfg.update(load_json(CONFIG_PATH, {}))
    cfg['path'] = str(cfg.get('path') or '').strip('/')
    return cfg


def first_run():
    cfg = dict(DEFAULT_CONFIG)
    cfg.update(load_json(CONFIG_PATH, {}))
    if not cfg.get('link_key'):
        cfg['link_key'] = secrets.token_urlsafe(12).replace('-', 'x').replace('_', 'y')
    if cfg != load_json(CONFIG_PATH, None):
        save_json(CONFIG_PATH, cfg)
    os.makedirs(os.path.join(BASE, DEFAULT_CONFIG['files_dir']), exist_ok=True)
    if not os.path.exists(LINKS_PATH):
        with open(LINKS_PATH, 'w', encoding='utf-8') as f:
            f.write(LINKS_SAMPLE)


def lan_ips():
    ips = set()
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(('10.255.255.255', 1))
        ips.add(s.getsockname()[0])
        s.close()
    except Exception:
        pass
    try:
        for info in socket.getaddrinfo(socket.gethostname(), None, socket.AF_INET):
            ips.add(info[4][0])
    except Exception:
        pass
    return sorted(ip for ip in ips if not ip.startswith('127.')) or ['127.0.0.1']


def device_code(uid):
    digest = hashlib.sha256(('supermanh-test:' + uid).encode()).digest()
    n = int.from_bytes(digest[:5], 'big')
    chars = ''.join(ALPHABET[(n >> (5 * i)) & 31] for i in range(8))
    return chars[:4] + ' ' + chars[4:]


def human(n):
    for unit in ('B', 'KB', 'MB', 'GB'):
        if n < 1024 or unit == 'GB':
            return ('%.1f %s' % (n, unit)) if unit != 'B' else '%d B' % n
        n /= 1024.0


_scan = {'key': None, 'at': 0.0, 'files': []}


def _tree_stamp(root):
    stamp = []
    for dirpath, dirs, _ in os.walk(root):
        try:
            stamp.append((dirpath, os.stat(dirpath).st_mtime_ns))
        except OSError:
            pass
    return tuple(stamp)


def local_files(cfg):
    """File list, rescanned only when a folder changed or after 60 s (big libraries stay fast)."""
    root = os.path.join(BASE, cfg['files_dir'])
    with lock:
        stamp = (root, _tree_stamp(root))
        if _scan['key'] == stamp and time.time() - _scan['at'] < 60:
            return _scan['files']
        files = _scan_files(root)
        _scan.update(key=stamp, at=time.time(), files=files)
        return files


def _scan_files(root):
    out = []
    for dirpath, _, names in os.walk(root):
        for name in sorted(names):
            if name.lower().endswith(EXTS):
                full = os.path.join(dirpath, name)
                rel = os.path.relpath(full, root).replace(os.sep, '/')
                out.append((rel, name, os.path.getsize(full)))
    return out


def link_entries():
    out = []
    try:
        with open(LINKS_PATH, 'r', encoding='utf-8') as f:
            lines = f.read().splitlines()
    except Exception:
        return out
    for line in lines:
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        parts = [p.strip() for p in line.split('|')]
        url = parts[0]
        name = parts[1] if len(parts) > 1 and parts[1] else urllib.parse.unquote(url.split('#')[0].rstrip('/').split('/')[-1])
        size = int(parts[2]) if len(parts) > 2 and parts[2].isdigit() else 0
        if url.startswith(('http://', 'https://')):
            url = urllib.parse.quote(url.split('#')[0], safe=':/?&=%~+@!$,;')
        out.append({'url': url + '#' + urllib.parse.quote(name), 'size': size})
    return out


def is_private(ip):
    try:
        a = ipaddress.ip_address(ip)
        return a.is_private or a.is_loopback
    except ValueError:
        return False


class Handler(BaseHTTPRequestHandler):
    server_version = 'SuperManhTestHost/1.0'

    def client_ip(self):
        # Behind Cloudflare Tunnel / a proxy the real address is in these headers.
        for h in ('CF-Connecting-IP', 'X-Forwarded-For'):
            v = self.headers.get(h) if hasattr(self, 'headers') and self.headers else None
            if v:
                return v.split(',')[0].strip()
        return self.client_address[0]

    def log_message(self, fmt, *args):
        sys.stdout.write('%s  %s  %s\n' % (time.strftime('%H:%M:%S'), self.client_ip(), fmt % args))

    def note_bad(self, cfg):
        limit = int(cfg.get('max_bad_requests') or 0)
        if limit <= 0 or self.is_local():
            return
        ip = self.client_ip()
        with lock:
            bad_hits[ip] = bad_hits.get(ip, 0) + 1
            if bad_hits[ip] >= limit and ip not in blocked:
                blocked.add(ip)
                print('>>> CHAN IP %s (do sai link %d lan)' % (ip, bad_hits[ip]))

    # ---- helpers -------------------------------------------------------
    def send_json(self, code, obj):
        body = json.dumps(obj, ensure_ascii=False).encode('utf-8')
        self.send_response(code)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.end_headers()
        if self.command != 'HEAD':
            self.wfile.write(body)

    def send_html(self, body, code=200):
        data = body.encode('utf-8')
        self.send_response(code)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        if self.command != 'HEAD':
            self.wfile.write(data)

    def is_local(self):
        # Requests through Cloudflare Tunnel arrive from 127.0.0.1 but carry CF headers.
        if self.headers.get('CF-Connecting-IP') or self.headers.get('X-Forwarded-For'):
            return False
        return is_private(self.client_address[0])

    def download_base(self, cfg):
        pub = str(cfg.get('public_url') or '').strip().rstrip('/')
        return pub if pub else self.base_url()

    def file_url(self, cfg, rel, name):
        return (self.download_base(cfg) + '/f/' + cfg['link_key'] + '/' + urllib.parse.quote(rel) +
                '#' + urllib.parse.quote(name))

    def plugin_lines(self, cfg):
        return ''.join('%s | %s | %d\n' % (self.file_url(cfg, rel, name), name, size) for rel, name, size in local_files(cfg))

    def base_url(self):
        host = self.headers.get('Host') or '%s:%d' % (lan_ips()[0], self.server.server_port)
        return 'http://' + host

    # ---- routes --------------------------------------------------------
    def do_HEAD(self):
        self.do_GET()

    def do_GET(self):
        cfg = config()
        if self.client_ip() in blocked:
            return self.send_json(403, {'error': 'Bi chan.'})
        path = urllib.parse.urlparse(self.path).path
        clean = urllib.parse.unquote(path).strip('/')
        prefix = 'f/' + cfg['link_key'] + '/'
        if clean.startswith(prefix):
            return self.serve_file(cfg, clean[len(prefix):])
        if clean.startswith('f/'):
            self.note_bad(cfg)
            return self.send_json(404, {'error': 'Khong tim thay.'})
        if clean in ('quanly', 'links', 'links.txt', '') and not self.is_local():
            return self.send_json(404, {'error': 'Khong tim thay.'})
        if clean == 'quanly':
            return self.page_admin(cfg)
        if clean == 'links':
            return self.send_html(self.page_links(cfg))
        if clean == 'links.txt':
            body = self.plugin_lines(cfg).encode('utf-8')
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain; charset=utf-8')
            self.send_header('Content-Disposition', 'attachment; filename="links_cho_plugin.txt"')
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            if self.command != 'HEAD':
                self.wfile.write(body)
            return
        if clean == cfg['path'] or (clean == '' and cfg['path'] == ''):
            return self.index(cfg)
        if clean == '':
            return self.send_html(self.home(cfg))
        self.send_json(404, {'error': 'Khong tim thay.'})

    def do_POST(self):
        if urllib.parse.urlparse(self.path).path.strip('/') != 'quanly':
            return self.send_json(404, {'error': 'Khong tim thay.'})
        if not self.is_local():
            return self.send_json(403, {'error': 'Chi dung trong mang LAN.'})
        length = int(self.headers.get('Content-Length') or 0)
        form = urllib.parse.parse_qs(self.rfile.read(length).decode('utf-8'))
        key, act = form.get('key', [''])[0], form.get('act', [''])[0]
        with lock:
            devices = load_json(DEVICES_PATH, {})
            if key in devices:
                if act == 'on':
                    devices[key]['active'] = True
                elif act == 'off':
                    devices[key]['active'] = False
                elif act == 'del':
                    devices.pop(key)
                save_json(DEVICES_PATH, devices)
        self.send_response(303)
        self.send_header('Location', '/quanly')
        self.end_headers()

    def index(self, cfg):
        uid = (self.headers.get('UID') or '').strip()
        files = [{'url': self.file_url(cfg, rel, name), 'size': size}
                 for rel, name, size in local_files(cfg)] + link_entries()
        motd = cfg['title']
        if cfg.get('activation'):
            if not uid or set(uid) <= {'0'}:
                return self.send_json(200, {'files': [], 'success': motd + '\nKhong doc duoc ma may cua Switch nay (UID trong).\nTat "activation" trong config.json de test khong can kich hoat.'})
            key = hashlib.sha256(uid.encode()).hexdigest()[:24]
            with lock:
                devices = load_json(DEVICES_PATH, {})
                dev = devices.get(key)
                if not dev:
                    dev = {'code': device_code(uid), 'active': False, 'first_seen': time.strftime('%Y-%m-%d %H:%M:%S')}
                    devices[key] = dev
                    print('\n>>> MAY MOI: ma kich hoat %s  (mo http://localhost:%d/quanly de bat)\n' % (dev['code'], self.server.server_port))
                dev['last_seen'] = time.strftime('%Y-%m-%d %H:%M:%S')
                dev['ip'] = self.client_address[0]
                dev['client'] = self.headers.get('Version') or self.headers.get('User-Agent') or ''
                save_json(DEVICES_PATH, devices)
            if not dev['active']:
                return self.send_json(200, {'files': [], 'success': motd + '\nMa kich hoat: ' + dev['code'] +
                                            '\nGui ma nay cho ' + cfg['contact'] + ' de kich hoat, roi tat han app va mo lai.'})
            motd += '\nMay ' + dev['code'] + ' da kich hoat.'
        if not files:
            motd += '\nChua co file nao. Chep nsp/nsz/xci vao thu muc "' + cfg['files_dir'] + '" hoac ghi link vao links.txt.'
        self.send_json(200, {'files': files, 'success': motd})

    def serve_file(self, cfg, rel):
        root = os.path.realpath(os.path.join(BASE, cfg['files_dir']))
        full = os.path.realpath(os.path.join(root, rel))
        if not full.startswith(root + os.sep) or not os.path.isfile(full):
            return self.send_json(404, {'error': 'Khong tim thay file.'})
        size = os.path.getsize(full)
        start, end = 0, size - 1
        rng = self.headers.get('Range')
        partial = False
        if rng and rng.startswith('bytes='):
            a, _, b = rng[6:].split(',')[0].partition('-')
            try:
                if a:
                    start = int(a)
                    end = int(b) if b else size - 1
                else:
                    start = max(0, size - int(b))
                end = min(end, size - 1)
                partial = True
            except ValueError:
                pass
            if start > end or start >= size:
                self.send_response(416)
                self.send_header('Content-Range', 'bytes */%d' % size)
                self.end_headers()
                return
        self.send_response(206 if partial else 200)
        self.send_header('Content-Type', mimetypes.guess_type(full)[0] or 'application/octet-stream')
        self.send_header('Accept-Ranges', 'bytes')
        self.send_header('Content-Length', str(end - start + 1))
        if partial:
            self.send_header('Content-Range', 'bytes %d-%d/%d' % (start, end, size))
        self.end_headers()
        if self.command == 'HEAD':
            return
        with open(full, 'rb') as f:
            try:
                self.wfile.flush()
                # Kernel copy where the OS supports it (Linux/macOS); Windows falls back to 4 MB reads.
                self.connection.sendfile(f, offset=start, count=end - start + 1)
            except (ConnectionResetError, BrokenPipeError, ConnectionAbortedError):
                pass
            except Exception:
                f.seek(start)
                left = end - start + 1
                try:
                    while left > 0:
                        chunk = f.read(min(4 * 1024 * 1024, left))
                        if not chunk:
                            break
                        self.wfile.write(chunk)
                        left -= len(chunk)
                except (ConnectionResetError, BrokenPipeError, ConnectionAbortedError):
                    pass

    # ---- pages ---------------------------------------------------------
    CSS = ('<meta name="viewport" content="width=device-width,initial-scale=1"><style>'
           'body{font-family:Segoe UI,Arial,sans-serif;background:#121218;color:#eee;margin:0;padding:16px}'
           'h1{color:#ff8a00;font-size:22px}table{border-collapse:collapse;width:100%}td,th{padding:8px;border-bottom:1px solid #333;text-align:left}'
           'code{background:#222;padding:2px 6px;border-radius:4px}button{padding:6px 12px;border:0;border-radius:6px;cursor:pointer;margin:2px}'
           '.on{background:#22a35a;color:#fff}.off{background:#555;color:#fff}.del{background:#a33;color:#fff}.ok{color:#4ade80}.no{color:#fbbf24}'
           '.card{background:#1c1c26;border-radius:10px;padding:14px;margin-bottom:14px}</style>')

    def home(self, cfg):
        return ('<!doctype html><meta charset="utf-8"><title>SUPER MANH Test Host</title>' + self.CSS +
                '<h1>SUPER MANH Test Host</h1><div class="card">Dia chi kho: <code>' + html.escape(self.base_url() + '/' + cfg['path']) +
                '</code><br><a style="color:#ff8a00" href="/quanly">Quan ly may / kich hoat</a> &middot; <a style="color:#ff8a00" href="/links">Link cho plugin</a></div>')

    def page_links(self, cfg):
        lines = self.plugin_lines(cfg)
        pub = str(cfg.get('public_url') or '').strip()
        warn = ('' if pub else '<p class="no">Chua dat "public_url" trong config.json: link duoi day chi tai duoc trong mang nha. '
                'Switch o xa can dia chi cong khai (Cloudflare Tunnel / ten mien / mo cong).</p>')
        return ('<!doctype html><meta charset="utf-8"><title>Link cho plugin</title>' + self.CSS +
                '<h1>Link tai truc tiep cho plugin</h1><div class="card">' + warn +
                '<p>Chep het o duoi, dan vao plugin: <b>Ban Viet hoa &rarr; Them link</b> (moi dong: link | ten file | dung luong).</p>'
                '<textarea id="t" readonly style="width:100%;height:50vh;background:#0d0d12;color:#ddd;border:1px solid #333;border-radius:8px;padding:8px;font:13px Consolas,monospace">' +
                html.escape(lines or '# Chua co file trong thu muc "' + cfg['files_dir'] + '"') + '</textarea>'
                '<p><button class="on" onclick="var t=document.getElementById(\'t\');t.select();navigator.clipboard&&navigator.clipboard.writeText(t.value);this.textContent=\'Da chep\'">Chep tat ca</button> '
                '<a href="/links.txt"><button class="off">Tai file links_cho_plugin.txt</button></a></p>'
                '<p><small>Doi "link_key" trong config.json se lam moi tat ca link (link cu het dung).</small></p></div>')

    def page_admin(self, cfg):
        devices = load_json(DEVICES_PATH, {})
        rows = ''
        for key, d in sorted(devices.items(), key=lambda kv: kv[1].get('last_seen', ''), reverse=True):
            state = '<span class="ok">Da kich hoat</span>' if d.get('active') else '<span class="no">Cho kich hoat</span>'
            btn = ('<button class="off" name="act" value="off">Khoa</button>' if d.get('active')
                   else '<button class="on" name="act" value="on">Kich hoat</button>')
            rows += ('<tr><td><b>%s</b></td><td>%s</td><td>%s<br><small>%s</small></td><td><form method="post">'
                     '<input type="hidden" name="key" value="%s">%s<button class="del" name="act" value="del">Xoa</button></form></td></tr>') % (
                html.escape(d.get('code', '')), state, html.escape(d.get('last_seen', '')), html.escape(d.get('ip', '') + ' ' + d.get('client', '')),
                html.escape(key), btn)
        files = local_files(cfg)
        flist = ''.join('<li>%s <small>(%s)</small></li>' % (html.escape(n), human(s)) for _, n, s in files) or '<li>(chua co)</li>'
        links = link_entries()
        return ('<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="10"><title>Quan ly - Test Host</title>' + self.CSS +
                '<h1>SUPER MANH Test Host</h1>'
                '<div class="card">Dia chi kho de nhap vao app: <code>' + html.escape(self.base_url() + '/' + cfg['path']) + '</code><br>'
                'Kich hoat theo may: <b>' + ('BAT' if cfg.get('activation') else 'TAT') + '</b> (doi trong config.json)</div>'
                '<div class="card"><b>May da ket noi</b> (trang tu tai lai moi 10 giay)<table><tr><th>Ma</th><th>Trang thai</th><th>Lan cuoi</th><th></th></tr>' +
                (rows or '<tr><td colspan="4">Chua co may nao. Mo kho tren Switch la may hien o day.</td></tr>') + '</table></div>'
                '<div class="card"><b>File trong thu muc "' + html.escape(cfg['files_dir']) + '"</b><ul>' + flist + '</ul>'
                '<b>Link trong links.txt:</b> ' + str(len(links)) + '<br><a style="color:#ff8a00" href="/links">Lay link tai truc tiep cho plugin &rarr;</a></div>')


def export_loop():
    """Like bitfoil: keep shop.json + links_cho_plugin.txt next to the tool up to date."""
    last = None
    while True:
        try:
            cfg = config()
            base = str(cfg.get('public_url') or '').strip().rstrip('/') or 'http://%s:%d' % (lan_ips()[0], int(cfg['port']))
            files = local_files(cfg)
            if (files, base, cfg['link_key']) != last:
                url = lambda rel, name: base + '/f/' + cfg['link_key'] + '/' + urllib.parse.quote(rel) + '#' + urllib.parse.quote(name)
                with open(os.path.join(BASE, 'links_cho_plugin.txt'), 'w', encoding='utf-8') as f:
                    f.write(''.join('%s | %s | %d\n' % (url(rel, name), name, size) for rel, name, size in files))
                save_json(os.path.join(BASE, 'shop.json'), {'files': [{'url': url(rel, name), 'size': size} for rel, name, size in files] + link_entries(),
                                                            'success': cfg['title']})
                if last is not None:
                    print('>>> Danh sach file thay doi: %d file, da ghi lai links_cho_plugin.txt' % len(files))
                last = (files, base, cfg['link_key'])
        except Exception as e:
            print('Loi ghi danh sach:', e)
        time.sleep(15)


def main():
    first_run()
    cfg = config()
    port = int(cfg['port'])
    try:
        httpd = ThreadingHTTPServer(('0.0.0.0', port), Handler)
    except OSError as e:
        print('Khong mo duoc cong %d (%s). Doi "port" trong config.json roi chay lai.' % (port, e))
        input('Nhan Enter de thoat...')
        return
    httpd.daemon_threads = True
    threading.Thread(target=export_loop, daemon=True).start()
    ips = lan_ips()
    print('=' * 64)
    print(' SUPER MANH Test Host dang chay')
    print('=' * 64)
    for ip in ips:
        print(' Dia chi kho (nhap vao Switch):  http://%s:%d/%s' % (ip, port, cfg['path']))
    print(' Trang quan ly / kich hoat:      http://localhost:%d/quanly' % port)
    print(' Link tai truc tiep cho plugin:  http://localhost:%d/links' % port)
    print(' Dia chi cong khai (public_url): %s' % (cfg.get('public_url') or '(chua dat - chi tai trong mang nha)'))
    print(' Tu ghi links_cho_plugin.txt + shop.json canh tool moi khi thu muc doi.')
    print(' File de tai:  thu muc "%s"  (%d file)' % (cfg['files_dir'], len(local_files(cfg))))
    print(' Link truc tiep: links.txt     (%d link)' % len(link_entries()))
    print(' Kich hoat theo may: %s' % ('BAT' if cfg.get('activation') else 'TAT'))
    print('-' * 64)
    print(' Windows hoi tuong lua thi chon "Allow" (mang Private).')
    print(' Dong cua so nay hoac Ctrl+C de tat.')
    print('=' * 64)
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        pass


def selftest():
    """CI check: start on a free port, ask like a Switch would, expect an activation code."""
    import tempfile
    import urllib.request
    global BASE, CONFIG_PATH, DEVICES_PATH, LINKS_PATH
    BASE = tempfile.mkdtemp()
    CONFIG_PATH, DEVICES_PATH, LINKS_PATH = (os.path.join(BASE, n) for n in ('config.json', 'devices.json', 'links.txt'))
    first_run()
    httpd = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    threading.Thread(target=httpd.serve_forever, daemon=True).start()
    req = urllib.request.Request('http://127.0.0.1:%d/tinfoil' % httpd.server_port, headers={'UID': 'ab' * 32})
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    data = json.loads(opener.open(req, timeout=10).read().decode('utf-8'))
    assert data['files'] == [] and 'Ma kich hoat: ' in data['success'], data
    httpd.shutdown()
    print('selftest ok:', data['success'].splitlines()[1])


if __name__ == '__main__':
    if '--selftest' in sys.argv:
        selftest()
    else:
        main()
