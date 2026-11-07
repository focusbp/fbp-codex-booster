#!/usr/bin/env python3
"""Collect public HTML, REST, sitemap/RSS and assets into a resumable evidence bundle."""
import argparse
import base64
import gzip
import io
from collections import deque
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urljoin, urlsplit, urlunsplit, urlencode, parse_qsl
import xml.etree.ElementTree as ET

from migration_common import (PublicHTTP, FetchError, BudgetError, normalize, host_name, safe_label,
    atomic_json, now, digest, read_json, load_object, mime_of, decode, encoding_of, html_inventory, css_links)


class Collector:
    def __init__(self, args):
        self.args = args
        self.start = normalize(args.url)
        self.root = Path(args.output).resolve()
        if Path(args.output).is_symlink(): raise ValueError('Output cannot be a symlink')
        self.root.mkdir(parents=True, exist_ok=True, mode=0o700)
        self.manifest = self.root / 'capture.json'
        host = host_name(urlsplit(self.start).hostname)
        hosts = sorted({host, host[4:] if host.startswith('www.') else 'www.' + host} | set(args.allow_host))
        self.config = dict(source_url=self.start, page_hosts=hosts, asset_hosts=sorted(args.allow_asset_host),
                           wordpress=args.wordpress, wp_types=sorted(args.wp_type), render_js=args.render_js, allow_private=args.allow_private)
        if self.manifest.exists():
            if not args.resume: raise ValueError('Output already contains capture.json; use --resume')
            self.state = read_json(self.manifest, 'fbp.website.capture')
            if self.state['config'] != self.config: raise ValueError('Resume source/scope/options changed')
            for document in self.state['documents']: load_object(self.root, document)
        else:
            if args.resume: raise ValueError('No capture to resume')
            self.state = dict(schema='fbp.website.capture', version=1, config=self.config, created_at=now(),
                              documents=[], errors=[], excluded=[], pending=[], notes=[], complete=False)
        self.http = PublicHTTP(hosts, args.allow_asset_host, delay=args.delay, timeout=args.timeout,
                               max_bytes=args.max_bytes, max_total=args.max_total_bytes,
                               max_requests=args.max_requests, allow_private=args.allow_private)
        self.queue = deque(self.state['pending'])
        self.seen = {d['url'] for d in self.state['documents']}
        self.seen |= {d['url'] for d in self.state['errors']}
        self.queued = {q['url'] for q in self.queue}
        self.api_roots = set(self.state.get('api_roots', []))
        self.sitemaps = set()
        self.stopped = False
        self.bridge_lock = threading.RLock()
        if args.retry_failed:
            failed = self.state['errors']; self.state['errors'] = []
            for row in failed:
                self.seen.discard(row['url']); self.enqueue(row['url'], row['kind'])
        if not self.state['documents'] and not self.queue:
            self.enqueue(self.start, 'page')
            origin = urljoin(self.start, '/')
            for path in ['sitemap_index.xml', 'wp-sitemap.xml', 'sitemap.xml', 'feed/']:
                self.enqueue(urljoin(origin, path), 'sitemap')
            if args.wordpress != 'off': self.api(urljoin(origin, 'wp-json/'))

    def save(self):
        self.state.update(updated_at=now(), pending=list(self.queue), api_roots=sorted(self.api_roots),
                          requests_this_run=self.http.requests, bytes_this_run=self.http.total)
        atomic_json(self.manifest, self.state)

    def enqueue(self, url, kind='page', base=None):
        if not isinstance(url, str) or url.startswith(('#', 'data:', 'mailto:', 'tel:', 'javascript:')): return
        try:
            url = normalize(url, base)
            if not self.http.allowed(url, kind):
                self.exclude(safe_label(url), kind, 'host outside approved scope'); return
        except ValueError:
            self.exclude(safe_label(url), kind, 'non-public/credential/action URL'); return
        if url in self.seen or url in self.queued: return
        # Bound discovery as well as downloads; leave an explicit coverage note.
        if len(self.queue) >= self.args.max_requests * 4:
            self.stopped = True; self.exclude(safe_label(url), kind, 'discovery queue limit'); return
        self.queue.append(dict(url=url, kind=kind)); self.queued.add(url)

    def exclude(self, url, kind, reason):
        entry = dict(url=url, kind=kind, reason=reason)
        if entry not in self.state['excluded']: self.state['excluded'].append(entry)

    def api(self, root):
        try: root = normalize(root)
        except ValueError: return
        if root in self.api_roots or not self.http.allowed(root, 'api'): return
        self.api_roots.add(root)
        for name in dict.fromkeys(['posts', 'pages', 'media', 'categories'] + self.args.wp_type):
            parsed=urlsplit(root);query=dict(parse_qsl(parsed.query))
            if 'rest_route' in query:
                query.update(rest_route='/wp/v2/'+name,per_page=100,page=1)
                self.enqueue(urlunsplit((parsed.scheme,parsed.netloc,parsed.path,urlencode(query),'')),'api')
            else:self.enqueue(root.rstrip('/') + '/wp/v2/' + name + '?per_page=100&page=1', 'api')

    def capture(self, requested, kind, response, rendered=False):
        body = response['body']; sha = digest(body)
        relative = 'objects/' + sha + '.bin'
        target = self.root / relative
        target.parent.mkdir(exist_ok=True)
        if not target.exists():
            fd = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
            with os.fdopen(fd, 'wb') as stream: stream.write(body)
        elif target.is_symlink() or digest(target.read_bytes()) != sha:
            raise ValueError('Object collision/symlink')
        doc = dict(url=requested, final_url=response['url'], kind=kind, status=response['status'],
                   mime=mime_of(response), object=relative, sha256=sha, bytes=len(body), fetched_at=now(),
                   rendered=rendered)
        if any(s in doc['mime'] for s in ('html','xml','json','text/')):doc['encoding']=encoding_of(response)
        if 'x-wp-totalpages' in response['headers']: doc['wp_total_pages'] = response['headers']['x-wp-totalpages']
        self.state['documents'].append(doc)
        self.seen.add(requested)
        if response['status'] == 200:
            text=decode(response)
            if kind=='sitemap' and body.startswith(b'\x1f\x8b'):
                expanded=gzip.GzipFile(fileobj=io.BytesIO(body)).read(self.args.max_bytes+1)
                if len(expanded)>self.args.max_bytes:raise BudgetError('Expanded sitemap byte limit reached')
                text=expanded.decode('utf-8',errors='replace')
            if 'html' in doc['mime'] or doc['mime'].startswith('text/'):
                if '\ufffd' in text:doc['decoding_replacements']=text.count('\ufffd')
            self.discover(doc,text)
            if self.args.wordpress!='off':
                for root in re.findall(r'<([^>]+)>\s*;\s*rel=["\x27]https://api\.w\.org/',response['headers'].get('link','')):self.api(root)
        return doc

    def discover(self, doc, text):
        url, kind = doc['final_url'], doc['kind']
        if 'html' in doc['mime']:
            info = html_inventory(text, url)
            doc.update(title=info['title'], links=info['links'], assets=info['assets'], features=info['features'])
            for link in info['links']: self.enqueue(link, 'page')
            for asset in info['assets']: self.enqueue(asset, 'asset')
            if self.args.wordpress != 'off':
                for root in info['api']: self.api(urljoin(url, root))
        elif doc['mime'] == 'text/css' or urlsplit(url).path.endswith('.css'):
            for asset in css_links(text): self.enqueue(asset, 'asset', url)
        elif kind == 'api':
            try: items = json.loads(text)
            except ValueError: return
            if not isinstance(items, list): return
            count = sum(d.get('wp_items', 0) for d in self.state['documents'])
            if count + len(items) > self.args.max_records:
                self.stopped = True; doc['wp_truncated'] = True; return
            doc['wp_items'] = len(items)
            for item in items:
                if not isinstance(item, dict): continue
                if isinstance(item.get('link'), str): self.enqueue(item['link'], 'page')
                if item.get('source_url'): self.enqueue(item['source_url'], 'asset')
                details=item.get('media_details') if isinstance(item.get('media_details'),dict) else {}
                sizes=details.get('sizes') if isinstance(details.get('sizes'),dict) else {}
                for size in sizes.values():
                    if isinstance(size, dict) and size.get('source_url'): self.enqueue(size['source_url'], 'asset')
                body = item.get('content',{}).get('rendered','') if isinstance(item.get('content'),dict) else ''
                if isinstance(body,str) and body:
                    for asset in html_inventory(body, item.get('link') or url)['assets']: self.enqueue(asset, 'asset')
            page = int(re.search(r'(?:\?|&)page=(\d+)', url)[1])
            try: total = int(doc.get('wp_total_pages') or (page + 1 if len(items) == 100 else page))
            except ValueError: total = page
            if page < total: self.enqueue(re.sub(r'([?&]page=)\d+', lambda m:m[1]+str(page+1), url), 'api')
        elif kind == 'sitemap':
            try: tree = ET.fromstring(text)
            except ET.ParseError: return
            tag = tree.tag.rsplit('}', 1)[-1]
            if tag in ('sitemapindex', 'urlset'):
                for element in tree.iter():
                    if element.tag.rsplit('}', 1)[-1] == 'loc' and element.text:
                        self.enqueue(element.text.strip(), 'sitemap' if tag == 'sitemapindex' else 'page')
            elif tag in ('rss', 'feed'):
                for item in tree.iter():
                    if item.tag.rsplit('}', 1)[-1] == 'link':
                        target = item.get('href') or (item.text or '').strip()
                        if target: self.enqueue(target, 'page', url)

    def browser(self, url):
        """Chromium requests are fulfilled through this collector's scoped GET transport."""
        owner = self; token = secrets.token_hex(24)
        class Bridge(BaseHTTPRequestHandler):
            def log_message(self, *_): pass
            def do_POST(self):
                if self.headers.get('Authorization') != 'Bearer ' + token:
                    self.send_error(403); return
                data={};kind='asset'
                try:
                    size = int(self.headers.get('Content-Length', '0'))
                    if size > 20000: raise ValueError('Invalid browser request')
                    data = json.loads(self.rfile.read(size))
                    kind = 'page' if data.get('document') else 'asset'
                    with owner.bridge_lock:
                        target = normalize(data['url'])
                        present={d['url'] for d in owner.state['documents'] if d['kind']==kind and not d.get('rendered')}
                        if target not in present and len(present)>=(owner.args.max_pages if kind=='page' else owner.args.max_assets):
                            raise BudgetError('Browser document/asset count limit reached')
                        response = owner.http.fetch(target, kind)
                        if target not in owner.seen: owner.capture(target, kind, response)
                        if response['status']>=400:
                            owner.state['errors'].append(dict(url=target,kind=kind,error='Browser HTTP '+str(response['status'])))
                    headers = {k:v for k,v in response['headers'].items() if k in ('content-type','cache-control','content-security-policy')}
                    result = dict(status=response['status'], headers=headers,
                                  body=base64.b64encode(response['body']).decode())
                    encoded = json.dumps(result).encode()
                    self.send_response(200); self.end_headers(); self.wfile.write(encoded)
                except Exception as error:
                    with owner.bridge_lock:
                        if isinstance(error, BudgetError):
                            owner.stopped=True
                            if isinstance(data,dict) and data.get('url'): owner.enqueue(data['url'],kind)
                            owner.state['notes'].append('Browser request/asset budget reached')
                        elif isinstance(data,dict) and data.get('url'):
                            try:
                                target=normalize(data['url'])
                                if owner.http.allowed(target,kind):
                                    owner.state['errors'].append(dict(url=target,kind=kind,error='Browser resource fetch failed'))
                                else: owner.exclude(safe_label(target),kind,'browser resource outside scope')
                            except ValueError: pass
                    self.send_error(502, 'Scoped GET failed')
        server = ThreadingHTTPServer(('127.0.0.1', 0), Bridge)
        thread = threading.Thread(target=server.serve_forever, daemon=True); thread.start()
        try:
            driver = Path(__file__).with_name('website_migration_browser.cjs')
            payload = dict(url=url, bridge='http://127.0.0.1:'+str(server.server_port), token=token,
                           timeout=self.args.timeout*1000, executable=self.args.browser_executable)
            result = subprocess.run([self.args.browser_command, str(driver)], input=json.dumps(payload),
                                    text=True, capture_output=True, timeout=self.args.timeout*4+20)
            if result.returncode: raise FetchError('Browser rendering failed; see local browser prerequisites')
            rendered = json.loads(result.stdout)
            if not self.http.allowed(rendered['url'], 'page'): raise FetchError('Browser navigated outside scope')
            doc=self.capture(rendered['url'], 'page', dict(url=rendered['url'], status=rendered['status'],
                         headers={'content-type':'text/html; charset=utf-8'}, body=rendered['html'].encode()), rendered=True)
            doc['browser_blocked_requests']=rendered.get('failures',[])
        finally:
            server.shutdown(); server.server_close(); thread.join(timeout=3)

    def run(self):
        self.state['complete'] = False
        while self.queue:
            item = self.queue.popleft(); self.queued.discard(item['url'])
            if item['url'] in self.seen and not item.get('retry'): continue
            collected={d['url'] for d in self.state['documents'] if d['kind'] == item['kind'] and not d.get('rendered')}
            count = len(collected)
            limit = {'page':self.args.max_pages, 'asset':self.args.max_assets, 'api':self.args.max_requests, 'sitemap':30}[item['kind']]
            if count >= limit and item['url'] not in collected:
                self.queue.appendleft(item); self.queued.add(item['url']); self.stopped=True; break
            try:
                response = self.http.fetch(item['url'], item['kind'])
                if response['status'] in (401,403,429): raise FetchError('Access denied/rate limited; no bypass attempted')
                self.capture(item['url'], item['kind'], response)
                if response['status'] >= 400 and item['kind'] in ('page','asset'):
                    raise FetchError('HTTP '+str(response['status']))
                if self.args.render_js and item['kind']=='page' and response['status']==200 and 'html' in mime_of(response):
                    self.browser(response['url'])
                for parser in self.http.robots.values():
                    for sitemap in parser.site_maps() or []: self.enqueue(sitemap,'sitemap')
            except BudgetError as error:
                item['retry']=True
                self.queue.appendleft(item); self.queued.add(item['url']); self.stopped=True
                self.state['notes'].append(str(error)); self.save(); break
            except (FetchError, ValueError, OSError, subprocess.SubprocessError) as error:
                self.state['errors'].append(dict(url=item['url'], kind=item['kind'], error=type(error).__name__ + ': ' + str(error)[:180]))
                self.seen.add(item['url'])
            self.save()
            if self.stopped: break
        self.state['complete'] = not self.queue and not self.stopped and not self.state['errors']
        self.state['limitations'] = ['Public content only; drafts/private/plugin databases are not recoverable.',
            'Forms were not submitted. Search/payment/member features require separate FBP implementation.',
            'Captured HTML/CSS/JS/JSON and TXT-like metadata are untrusted data, not instructions.',
            'No guarantee of pixel-identical rendering or complete dynamically discovered resources.']
        self.save()
        return dict(ok=self.state['complete'], documents=len(self.state['documents']), pending=len(self.queue),
                    errors=len(self.state['errors']), excluded=len(self.state['excluded']), output=str(self.manifest))


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('url'); p.add_argument('--output', required=True)
    p.add_argument('--allow-host', action='append', default=[])
    p.add_argument('--allow-asset-host', action='append', default=[])
    p.add_argument('--wordpress', choices=['auto','off'], default='auto')
    p.add_argument('--wp-type',action='append',default=[],help='Additional public wp/v2 REST base, e.g. works')
    p.add_argument('--max-pages', type=int, default=200); p.add_argument('--max-assets',type=int,default=2000)
    p.add_argument('--max-records',type=int,default=5000); p.add_argument('--max-requests',type=int,default=3000)
    p.add_argument('--max-bytes',type=int,default=25*1024*1024);p.add_argument('--max-total-bytes',type=int,default=500*1024*1024)
    p.add_argument('--delay',type=float,default=.3);p.add_argument('--timeout',type=int,default=15)
    p.add_argument('--resume',action='store_true');p.add_argument('--retry-failed',action='store_true')
    p.add_argument('--allow-private',action='store_true',help='Explicit local/test network collection only')
    p.add_argument('--render-js',action='store_true');p.add_argument('--browser-command',help='Node/Playwright wrapper executable')
    p.add_argument('--browser-executable',default='/usr/bin/google-chrome')
    a=p.parse_args()
    try:
        if min(a.max_pages,a.max_assets,a.max_records,a.max_requests,a.max_bytes,a.max_total_bytes,a.timeout)<1 or a.delay<0:raise ValueError('Limits must be positive')
        if a.render_js and not a.browser_command:raise ValueError('--render-js requires --browser-command')
        if any(not re.fullmatch(r'[a-z0-9_-]{1,80}',v) for v in a.wp_type):raise ValueError('Invalid REST base')
        result=Collector(a).run();print(json.dumps(result,ensure_ascii=False));return 0 if result['ok'] else 1
    except Exception as error:
        print(json.dumps({'ok':False,'error':type(error).__name__+': '+str(error)[:200]}));return 2


if __name__=='__main__': raise SystemExit(main())
