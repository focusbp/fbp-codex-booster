#!/usr/bin/env python3
"""Isolated real HTTP checks. Requires php, ffmpeg and Python stdlib only."""
import base64
import http.client
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time

source = Path(os.environ.get('FBP_SOURCE', Path(__file__).resolve().parents[1]))
checks = 0

def check(condition, label):
    global checks
    assert condition, label
    checks += 1

with tempfile.TemporaryDirectory(prefix='saved-media-', dir=os.environ['FBP_TEST_TMP']) as tmp:
    root = Path(tmp)
    upload = root / 'data/upload'
    upload.mkdir(parents=True)
    (root / 'legacy').mkdir()
    (root / 'router.php').write_text('''<?php
interface Controller {}
require getenv('FBP_SOURCE') . '/fbp/lib/Controller_class.php';
class MediaTestController extends Controller_class { function __construct() {} }
$ctl = new MediaTestController();
$ctl->dirs = (object) ['datadir' => __DIR__ . '/data',
 'appdir_user' => __DIR__ . '/legacy'];
// App-owned policy before invoking the response helper.
if (isset($_GET['private']) && ($_GET['owner'] ?? '') !== '1') {
 http_response_code(403); header('Cache-Control: private, no-store'); exit;
}
header('ETag: "stale"'); header('Cache-Control: public, max-age=9999');
$ctl->res_saved_media($_GET['file'] ?? 'clip', ['cache' => isset($_GET['cache']), 'max_age' => 120]);
echo 'must not appear';
''')
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jGZkAAAAASUVORK5CYII=')
    (upload / 'picture').write_bytes(png)
    subprocess.run(['ffmpeg', '-v', 'error', '-f', 'lavfi', '-i', 'color=c=blue:s=32x32:d=1',
                    '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
                    '-f', 'mp4', str(upload / 'clip')], check=True)
    video = (upload / 'clip').read_bytes()
    (upload / 'fake.mp4').write_text('<html>not video</html>')
    (upload / 'active.svg').write_text('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')
    (root / 'outside').write_bytes(png)
    (upload / 'escape').symlink_to(root / 'outside')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = dict(os.environ, FBP_SOURCE=str(source))
    with (root / 'server.log').open('w') as log:
        proc = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', str(root / 'router.php')], env=env, stdout=log, stderr=log)
        try:
            for _ in range(100):
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.1): break
                except OSError: time.sleep(.03)
            def request(query='', method='GET', headers=None):
                conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
                conn.request(method, '/?' + query, headers=headers or {})
                response = conn.getresponse()
                result = response.status, dict(response.getheaders()), response.read()
                conn.close()
                return result
            status, h, body = request()
            check(status == 200 and body == video, 'full video')
            check(h['Content-Type'] == 'video/mp4' and h['Content-Disposition'] == 'inline', 'inline MIME')
            check('no-store' in h['Cache-Control'] and 'ETag' not in h, 'private default')
            check(int(h['Content-Length']) == len(video), 'length')
            status, h, body = request('file=picture')
            check(status == 200 and body == png and h['Content-Type'] == 'image/png', 'extensionless PNG')
            for value, expected in [('bytes=0-9', video[:10]), ('bytes=10-19', video[10:20]),
                                    ('bytes=10-', video[10:]), ('bytes=-10', video[-10:]),
                                    ('bytes=0-9999999999999999999999', video)]:
                status, h, body = request(headers={'Range': value})
                check(status == 206 and body == expected, value)
                check(int(h['Content-Length']) == len(body) and 'Content-Range' in h, value + ' headers')
            for value in ['bytes=999999999999999999999-', 'bytes=10-1', 'bytes=-0']:
                status, h, body = request(headers={'Range': value})
                check(status == 416 and body == b'' and h['Content-Range'] == f'bytes */{len(video)}', value)
            for headers in [{'Range': 'bytes=0-1,5-6'}, {'Range': 'invalid'},
                            {'Range': 'bytes=0-1', 'If-Range': '"stale"'}]:
                status, h, body = request(headers=headers)
                check(status == 200 and body == video, 'range ignored')
            status, h, body = request(method='HEAD', headers={'Range': 'bytes=0-1'})
            check(status == 200 and body == b'' and int(h['Content-Length']) == len(video), 'HEAD')
            for name, expected in [('missing', 404), ('../outside', 404), ('escape', 404),
                                   ('%2Fetc%2Fpasswd', 404), ('fake.mp4', 415), ('active.svg', 415)]:
                status, h, body = request('file=' + name)
                check(status == expected and body == b'' and 'no-store' in h['Cache-Control'], name)
            check(request(method='POST')[0] == 405, 'method rejected')
            status, h, body = request('cache=1')
            check(status == 200 and h['Cache-Control'] == 'public, max-age=120', 'explicit public cache')
            for headers in [{}, {'Range': 'bytes=0-9'}]:
                status, h, body = request('private=1', headers=headers)
                check(status == 403 and video[:10] not in body and 'no-store' in h['Cache-Control'], 'app denies')
            status, h, body = request('private=1&owner=1')
            check(status == 200 and body == video and 'no-store' in h['Cache-Control'], 'app authorizes')
            print(f'{checks} HTTP checks passed')
        finally:
            proc.terminate()
            proc.wait(timeout=5)
