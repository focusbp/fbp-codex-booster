"""Portable website-migration primitives; no FBP bootstrap or production writes."""
from datetime import datetime, timezone
import gzip
import codecs
import hashlib
from html.parser import HTMLParser
import http.client
import io
import ipaddress
import json
import os
from pathlib import Path
import re
import socket
import ssl
import time
from urllib.parse import parse_qsl, urlencode, urljoin, urlsplit, urlunsplit, quote
import urllib.robotparser

USER_AGENT = 'FBPWebsiteMigration/1.0'
PRIVATE_QUERY = {'password', 'passwd', 'token', 'access_token', 'api_key', 'apikey', 'signature', '_wpnonce', 'nonce'}
ACTION_QUERY = {'add-to-cart', 'remove_item', 'logout', 'action', 'delete', 'remove', 'unsubscribe', 'subscribe', 'confirm', 'submit'}
ASSET_EXT = re.compile(r'\.(?:css|js|mjs|png|jpe?g|gif|webp|svg|ico|pdf|woff2?|ttf|otf|eot|mp4|webm|mp3|zip)(?:$|\?)', re.I)


def now():
    return datetime.now(timezone.utc).isoformat()


def digest(data):
    return hashlib.sha256(data).hexdigest()


def atomic_json(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.is_symlink():
        raise ValueError('Refusing a symlink output')
    temporary = path.with_name(path.name + '.writing')
    fd = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_TRUNC | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
        stream.write('\n')
    os.replace(temporary, path)


def read_json(path, schema=None):
    data = json.loads(Path(path).read_text())
    if schema and (data.get('schema') != schema or data.get('version') != 1):
        raise ValueError('Unsupported manifest schema/version')
    return data


def inside(root, relative):
    root = Path(root).resolve()
    candidate = root / relative
    if Path(relative).is_absolute() or '..' in Path(relative).parts or not candidate.resolve().is_relative_to(root):
        raise ValueError('Path escapes the workspace')
    return candidate


def host_name(value):
    value = value.lower().rstrip('.')
    try:
        return str(ipaddress.ip_address(value))
    except ValueError:
        value = value.encode('idna').decode('ascii')
        if not re.fullmatch(r'[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?', value):
            raise ValueError('Invalid hostname')
        return value


def normalize(url, base=None):
    value = urljoin(base, url) if base else url
    p = urlsplit(value)
    if p.scheme not in ('http', 'https') or not p.hostname or p.username or p.password:
        raise ValueError('Only credential-free HTTP/HTTPS URLs are accepted')
    if len(value)>8192 or any(ord(c)<32 or ord(c)==127 for c in value) or '\\' in value:
        raise ValueError('Invalid URL characters')
    host = host_name(p.hostname)
    if any(k.lower() in PRIVATE_QUERY | ACTION_QUERY for k, _ in parse_qsl(p.query, keep_blank_values=True)):
        raise ValueError('Credential/action URL excluded')
    if re.search(r'/(?:wp-admin|wp-login\.php)(?:/|$)', p.path, re.I):
        raise ValueError('Administrative URL excluded')
    if re.search(r'/(?:logout|signout|unsubscribe|delete)(?:/|$)',p.path,re.I):
        raise ValueError('Action URL excluded')
    port = p.port
    authority = '[' + host + ']' if ':' in host else host
    if port and port != (443 if p.scheme == 'https' else 80):
        authority += ':' + str(port)
    return urlunsplit((p.scheme, authority, quote(p.path or '/',safe="/%:@!$&'()*+,;=-._~"),
                       quote(p.query,safe="=&%/:@!$'()*+,;?-._~"), ''))


def safe_label(url):
    """Diagnostics omit query values, credentials and fragments."""
    try:
        p = urlsplit(url)
        return urlunsplit((p.scheme, p.hostname or '', p.path, '', ''))
    except ValueError:
        return '(invalid URL)'


def css_links(text):
    return re.findall(r'url\(\s*[\'"]?([^\)\'"\s]+)', text, re.I) + re.findall(r'@import\s+[\'"]([^\'"]+)', text, re.I)


class HTMLInventory(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.links, self.assets, self.api, self.features = [], [], [], []
        self.base, self.title, self.description, self.canonical = None, '', '', ''
        self._title = False
        self._style = False

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'title': self._title = True
        if tag == 'style': self._style = True
        if tag == 'base' and a.get('href'): self.base = a['href']
        if tag == 'a' and a.get('href'): self.links.append(a['href'])
        if tag == 'link' and a.get('href'):
            rel = a.get('rel', '').split()
            if 'https://api.w.org/' in rel: self.api.append(a['href'])
            if 'canonical' in rel: self.canonical = a['href']
            if set(rel) & {'stylesheet', 'icon', 'preload', 'apple-touch-icon'}: self.assets.append(a['href'])
        if tag == 'meta' and a.get('name') == 'description': self.description = a.get('content', '')
        if tag in {'img', 'script', 'source', 'video', 'audio'}:
            for key in ('src', 'poster', 'data-src', 'data-lazy-src'):
                if a.get(key): self.assets.append(a[key])
            for key in ('srcset', 'data-srcset'):
                if a.get(key) and not a[key].startswith('data:'):
                    self.assets += [x.strip().split()[0] for x in a[key].split(',') if x.strip()]
        if a.get('style'): self.assets += css_links(a['style'])
        if tag in {'form', 'iframe'}:
            self.features.append(dict(type=tag, target=safe_label(a.get('action', a.get('src', ''))),
                                      method=a.get('method', 'get').lower()))

    def handle_endtag(self, tag):
        if tag == 'title': self._title = False
        if tag == 'style': self._style = False

    def handle_data(self, data):
        if self._title: self.title += data
        if self._style: self.assets += css_links(data)


def html_inventory(text, url):
    parser = HTMLInventory()
    parser.feed(text)
    base = urljoin(url, parser.base) if parser.base else url
    links, assets = [], []
    for target, output in [(parser.links, links), (parser.assets, assets)]:
        for raw in target:
            if raw.startswith(('#', 'data:', 'mailto:', 'tel:', 'javascript:')):
                continue
            try:
                normalized = normalize(raw, base)
                (assets if output is links and ASSET_EXT.search(normalized) else output).append(normalized)
            except ValueError:
                continue
    return dict(title=parser.title.strip(), description=parser.description, canonical=parser.canonical,
                links=list(dict.fromkeys(links)), assets=list(dict.fromkeys(assets)), api=parser.api,
                features=parser.features)


class FetchError(Exception):
    pass


class BudgetError(FetchError):
    pass


class PublicHTTP:
    """GET only. Pin validated DNS addresses; ignore proxy credentials and cookies."""
    def __init__(self, page_hosts, asset_hosts=(), delay=.3, timeout=15, max_bytes=25*1024*1024,
                 max_total=500*1024*1024, max_requests=3000, allow_private=False):
        self.page_hosts = {host_name(h) for h in page_hosts}
        self.asset_hosts = self.page_hosts | {host_name(h) for h in asset_hosts}
        self.delay, self.timeout, self.max_bytes = max(.0, delay), timeout, max_bytes
        self.max_total, self.max_requests = max_total, max_requests
        self.allow_private = allow_private
        self.requests = self.total = 0
        self.last_request = 0
        self.robots = {}
        self.cache = {}
        self.paused = False

    def allowed(self, url, kind):
        return host_name(urlsplit(normalize(url)).hostname) in (self.page_hosts if kind in ('page', 'api', 'sitemap') else self.asset_hosts)

    def _once(self, url):
        if self.requests >= self.max_requests or self.total >= self.max_total:
            raise BudgetError('Request/byte budget reached')
        p = urlsplit(url)
        port = p.port or (443 if p.scheme == 'https' else 80)
        try:
            addresses = list(dict.fromkeys(x[4][0] for x in socket.getaddrinfo(p.hostname, port, type=socket.SOCK_STREAM)))
        except OSError as error:
            raise FetchError('DNS lookup failed') from error
        if not addresses or (not self.allow_private and any(not ipaddress.ip_address(x).is_global for x in addresses)):
            raise FetchError('Non-public DNS address rejected')
        time.sleep(max(0, self.delay - (time.monotonic() - self.last_request)))
        self.requests += 1
        self.last_request = time.monotonic()
        connection = http.client.HTTPConnection(p.hostname, port, timeout=self.timeout)
        raw = None
        try:
            raw = socket.create_connection((addresses[0], port), timeout=self.timeout)
            connection.sock = ssl.create_default_context().wrap_socket(raw, server_hostname=p.hostname) if p.scheme == 'https' else raw
            connection.request('GET', (p.path or '/') + ('?' + p.query if p.query else ''),
                               headers={'User-Agent':USER_AGENT, 'Accept-Encoding':'identity', 'Connection':'close'})
            response = connection.getresponse()
            headers = {k.lower():v for k,v in response.getheaders()}
            limit = min(self.max_bytes, self.max_total - self.total)
            body = response.read(limit + 1)
            wire_size=len(body);self.total+=wire_size
            if len(body) > limit: raise BudgetError('Response byte limit reached')
            if headers.get('content-encoding', '').lower() == 'gzip':
                body = gzip.GzipFile(fileobj=io.BytesIO(body)).read(limit + 1)
                if len(body) > limit: raise BudgetError('Decoded response byte limit reached')
                headers.pop('content-encoding', None)
            elif headers.get('content-encoding', '').lower() not in ('', 'identity'):
                raise FetchError('Unsupported Content-Encoding')
            self.total += max(0,len(body)-wire_size)
            return dict(url=url, status=response.status, headers=headers, body=body)
        except (OSError, http.client.HTTPException) as error:
            raise FetchError('HTTP/TLS transport failed') from error
        finally:
            connection.close()
            if raw: raw.close()

    def fetch(self, url, kind='page', robots=True):
        if self.paused:raise BudgetError('HTTP 429 paused this run; retry later without bypass')
        url = normalize(url)
        for _ in range(8):
            if not self.allowed(url, kind): raise FetchError('Host outside the approved scope')
            if robots: self._check_robots(url)
            if url in self.cache: return self.cache[url]
            response = self._once(url)
            if response['status']==429:
                self.paused=True
                raise BudgetError('HTTP 429 paused this run; retry later without bypass')
            if response['status'] in (301, 302, 303, 307, 308):
                if not response['headers'].get('location'): raise FetchError('Redirect without Location')
                url = normalize(response['headers']['location'], url)
                continue
            self.cache[url] = response
            return response
        raise FetchError('Redirect limit reached')

    def _check_robots(self, url):
        p = urlsplit(url)
        origin = urlunsplit((p.scheme, p.netloc, '', '', ''))
        if origin not in self.robots:
            response = self.fetch(origin + '/robots.txt', 'asset', robots=False)
            status = response['status']
            if status >= 500 or status in (401, 403, 429):
                raise FetchError('robots.txt unavailable or access denied')
            parser = urllib.robotparser.RobotFileParser()
            parser.parse(response['body'].decode('utf-8', errors='replace').splitlines() if status == 200 else [])
            self.robots[origin] = parser
        parser = self.robots[origin]
        if not parser.can_fetch(USER_AGENT, url): raise FetchError('robots.txt disallows this URL')
        self.delay = max(self.delay, parser.crawl_delay(USER_AGENT) or 0)


def mime_of(response):
    return response['headers'].get('content-type', 'application/octet-stream').split(';')[0].strip().lower()


def encoding_of(response):
    match = re.search(r'charset=([^;\s]+)', response['headers'].get('content-type', ''), re.I)
    encoding=match[1].strip('"\'') if match else None
    if not encoding:
        if response['body'].startswith(b'\xef\xbb\xbf'):encoding='utf-8-sig'
        else:
            found=re.search(br'(?:charset\s*=\s*["\x27]?|encoding\s*=\s*["\x27]|@charset\s+["\x27])([a-zA-Z0-9._-]+)',response['body'][:4096],re.I)
            if found:encoding=found[1].decode('ascii')
    try:
        return codecs.lookup(encoding or 'utf-8').name
    except LookupError:
        return 'utf-8'


def decode(response):
    return response['body'].decode(encoding_of(response),errors='replace')


def load_object(root, document):
    path = inside(root, document['object'])
    data = path.read_bytes()
    if digest(data) != document['sha256']:
        raise ValueError('Captured object hash mismatch')
    return data
