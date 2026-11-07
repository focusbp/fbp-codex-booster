#!/usr/bin/env python3
"""Prepare reviewable FBP-neutral content and media from public captures or WordPress WXR."""
import argparse
from datetime import datetime, timezone
import html
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
from urllib.parse import urlsplit, unquote, urljoin, quote, parse_qsl
import xml.etree.ElementTree as ET
import zipfile

from migration_common import (atomic_json, read_json, digest, now, normalize, load_object,
                              inside, html_inventory, safe_label)


def record_hash(record):
    return digest(json.dumps({k:v for k,v in record.items() if k!='record_hash'},
                 ensure_ascii=False,sort_keys=True,separators=(',',':')).encode())


def timestamp(value, utc=False):
    if not value or str(value).startswith('0000-00-00'): return 0
    try:
        date=datetime.fromisoformat(str(value).strip().replace('Z','+00:00'))
        if date.tzinfo is None:
            if not utc:return 0
            date=date.replace(tzinfo=timezone.utc)
        return int(date.timestamp())
    except (ValueError,OverflowError): return 0


def text(value):
    return html.unescape(re.sub(r'<[^>]*>','',str(value or ''))).strip()


def article(key,url,title,kind,status,date,body,categories=(),source_type='',snapshot=False):
    slug=unquote(urlsplit(url).path).strip('/') or 'top'
    public=int(status in ('publish','published','future'))
    if status=='future' and not date:public=0
    assets=html_inventory(body,url)['assets']
    row=dict(external_key=key,original_url=url,title=text(title),kind=kind,source_type=source_type or kind,
             source_status=status,status=public,published_at=date,slug=slug,
             categories=sorted(set(map(str,categories))),body_html=body,asset_urls=assets,
             featured_media_url='',snapshot=snapshot)
    row['record_hash']=record_hash(row)
    return row


def source_wxr(path, extra_types):
    data=Path(path).read_bytes()
    if len(data)>100*1024*1024:raise ValueError('WXR exceeds 100 MiB')
    root=ET.fromstring(data)
    wp={node.tag.split('}')[0][1:] for node in root.iter() if '}post_type' in node.tag}
    if not wp:raise ValueError('Not a WordPress WXR export')
    namespace=next(iter(wp));ns={'wp':namespace,'content':'http://purl.org/rss/1.0/modules/content/'}
    channel=root.find('channel')
    if channel is None:raise ValueError('Missing WXR channel')
    base=channel.findtext('link') or ''
    normalize(base)
    mapping={'post':'post','page':'page'}|extra_types
    articles=[];attachments=[];excluded=[];featured={}
    for item in channel.findall('item'):
        kind=item.findtext('wp:post_type','',ns);source_id=item.findtext('wp:post_id','',ns)
        if kind=='attachment':
            attachments.append(dict(url=item.findtext('wp:attachment_url','',ns),parent=item.findtext('wp:post_parent','',ns),source_id=source_id))
            continue
        if kind not in mapping:
            excluded.append(dict(type=kind,source_id=source_id,reason='post type requires an explicit mapping'));continue
        url=item.findtext('link') or base
        normalize(url)
        categories=[c.text for c in item.findall('category') if c.text and c.get('domain') not in ('post_tag',)]
        key='wp:'+kind+':'+source_id
        for meta in item.findall('wp:postmeta',ns):
            if meta.findtext('wp:meta_key','',ns)=='_thumbnail_id':featured[key]=meta.findtext('wp:meta_value','',ns)
        source_status='protected' if item.findtext('wp:post_password','',ns) else item.findtext('wp:status','draft',ns)
        articles.append(article(key,url,item.findtext('title'),mapping[kind],
            source_status,timestamp(item.findtext('wp:post_date_gmt','',ns),True),
            item.findtext('content:encoded','',ns),categories,kind))
    attachment_map={a['source_id']:a['url'] for a in attachments}
    for row in articles:
        row['featured_media_url']=attachment_map.get(featured.get(row['external_key']), '')
        if row['featured_media_url']:row['asset_urls']=list(dict.fromkeys(row['asset_urls']+[row['featured_media_url']]))
        row['record_hash']=record_hash(row)
    return base,articles,attachments,excluded


def source_capture(root):
    capture=read_json(Path(root)/'capture.json','fbp.website.capture')
    documents=capture['documents'];categories={};articles={};attachments=[];featured={}
    for doc in documents:
        load_object(root,doc)
        if doc['kind']=='api' and doc['status']==200:
            try: items=json.loads(load_object(root,doc))
            except ValueError:continue
            if not isinstance(items,list):continue
            parsed=urlsplit(doc['final_url']);endpoint=dict(parse_qsl(parsed.query)).get('rest_route',parsed.path)
            if endpoint.rstrip('/').endswith('/categories'):
                categories.update({r['id']:text(r.get('name')) for r in items if isinstance(r,dict) and 'id' in r})
    for doc in documents:
        if doc['kind']!='api' or doc['status']!=200:continue
        try: items=json.loads(load_object(root,doc))
        except ValueError:continue
        if not isinstance(items,list):continue
        for row in items:
            if not isinstance(row,dict):continue
            if row.get('source_url'):attachments.append(dict(url=row['source_url'],source_id=str(row.get('id','')),parent=''))
            if not isinstance(row.get('content'),dict) or not isinstance(row.get('title'),dict) or not row.get('link') or not row.get('id'):continue
            try:url=normalize(row['link'])
            except ValueError:continue
            source_type=str(row.get('type','post'))
            key='wp:'+source_type+':'+str(row['id'])
            source_status='protected' if row['content'].get('protected') else str(row.get('status','publish'))
            articles[key]=article(key,url,(row.get('title') or {}).get('rendered',''),
                'page' if source_type=='page' else 'post',source_status,
                timestamp(row.get('date_gmt'),True),(row.get('content') or {}).get('rendered',''),
                [categories.get(c,'taxonomy-id:'+str(c)) for c in row.get('categories',[])],source_type)
            featured[key]=str(row.get('featured_media',0))
    attachment_map={a['source_id']:a['url'] for a in attachments}
    for key,row in articles.items():
        row['featured_media_url']=attachment_map.get(featured.get(key),'')
        if row['featured_media_url']:row['asset_urls']=list(dict.fromkeys(row['asset_urls']+[row['featured_media_url']]))
        row['record_hash']=record_hash(row)
    if not articles:
        # Keep full public snapshots as review material; never claim main-content extraction.
        for doc in documents:
            if doc['kind']!='page' or doc['status']!=200 or 'html' not in doc['mime']:continue
            url=doc['final_url'];body=load_object(root,doc).decode(doc.get('encoding','utf-8'),errors='replace')
            info=html_inventory(body,url);key='html:'+digest(url.encode())
            if key in articles and not doc.get('rendered') and articles[key].get('_rendered'):continue
            articles[key]=article(key,url,info['title'],'page','publish',0,body,snapshot=True)
            articles[key]['_rendered']=bool(doc.get('rendered'))
        for row in articles.values():row.pop('_rendered',None)
    return capture['config']['source_url'],list(articles.values()),attachments,capture


def media_type(data):
    if data.startswith(b'\x89PNG\r\n\x1a\n'):return 'image/png','png'
    if data.startswith(b'\xff\xd8\xff'):return 'image/jpeg','jpg'
    if data[:6] in (b'GIF87a',b'GIF89a'):return 'image/gif','gif'
    if data[:4]==b'RIFF' and data[8:12]==b'WEBP':return 'image/webp','webp'
    if data.startswith(b'%PDF-'):return 'application/pdf','pdf'
    return None


def prepare(args):
    output=Path(args.output).resolve()
    if Path(args.output).is_symlink():raise ValueError('Output cannot be a symlink')
    output.mkdir(parents=True,exist_ok=True,mode=0o700)
    if (output/'migration.json').exists():raise ValueError('Use a new output directory; manifests are immutable')
    extra={}
    for spec in args.post_type:
        name,kind=spec.split('=',1)
        if kind not in ('post','page') or not re.fullmatch(r'[a-z0-9_-]+',name):raise ValueError('Use TYPE=post or TYPE=page')
        extra[name]=kind
    capture=None;excluded=[]
    if args.capture:
        source,articles,attachments,capture=source_capture(args.capture)
    else:
        source,articles,attachments,excluded=source_wxr(args.wxr,extra)
    if not articles:raise ValueError('No supported articles/pages found')
    keys=[r['external_key'] for r in articles]
    if len(keys)!=len(set(keys)):raise ValueError('Duplicate article source identifiers')
    documents={}
    if capture:
        for doc in capture['documents']:
            if doc['status']==200:
                documents[doc['url']]=doc;documents[doc['final_url']]=doc
    uploaded={};archive=None
    if args.uploads:
        folder=Path(args.uploads).resolve()
        if not folder.is_dir():raise ValueError('Uploads directory is missing')
        for file in folder.rglob('*'):
            if file.is_symlink():raise ValueError('Uploads must not contain symlinks')
            if file.is_file():uploaded[file.relative_to(folder).as_posix()]=file
            if len(uploaded)>10000:raise ValueError('Uploads entry limit reached')
    if args.uploads_zip:
        archive=zipfile.ZipFile(args.uploads_zip)
        if len(archive.infolist())>10000:raise ValueError('ZIP entry limit reached')
        total=0
        for item in archive.infolist():
            name=item.filename
            if name.startswith('/') or '\\' in name or '..' in PurePosixPath(name).parts or stat.S_ISLNK(item.external_attr>>16):
                raise ValueError('Unsafe ZIP member')
            total+=item.file_size
            if item.file_size>args.max_bytes or total>args.max_total_bytes:raise ValueError('ZIP size limit reached')
            if item.is_dir():continue
            relative=name.split('uploads/',1)[-1] if 'uploads/' in name else name
            if relative in uploaded:raise ValueError('Duplicate ZIP/upload path')
            uploaded[relative]=(archive,item)
    media=[];support=[];missing=[];saved_bytes=0
    public_assets={u for row in articles if row['status']==1 for u in row['asset_urls']}
    expected=set(u for r in articles for u in r['asset_urls'])|{r['url'] for r in attachments if r.get('url')}
    if uploaded:
        uploads_base=args.uploads_base_url
        if not uploads_base:
            first=next((a['url'] for a in attachments if '/uploads/' in a.get('url','')),None)
            if first:uploads_base=first.split('/uploads/',1)[0]+'/uploads/'
        if not uploads_base:raise ValueError('Cannot infer uploads URL; specify --uploads-base-url')
        uploads_base=normalize(uploads_base).rstrip('/')+'/'
        expected|={urljoin(uploads_base,quote(relative,safe='/')) for relative in uploaded}
    if capture:
        expected |= {d['url'] for d in capture['documents'] if d['kind']=='asset' and d['status']==200}
        expected |= {u for d in capture['documents'] for u in d.get('assets',[])}
    try:
        for url in sorted(expected):
            try:url=normalize(url)
            except ValueError:continue
            data=None
            if url in documents:data=load_object(args.capture,documents[url])
            else:
                path=unquote(urlsplit(url).path)
                relative=path.split('/uploads/',1)[-1] if '/uploads/' in path else path.lstrip('/')
                file=uploaded.get(relative)
                if isinstance(file,Path):
                    if file.stat().st_size>args.max_bytes:raise ValueError('Upload file size limit reached')
                    data=file.read_bytes()
                elif file is not None:data=file[0].read(file[1])
            if data is None:
                missing.append(dict(url=url,reason='not captured/provided'));continue
            saved_bytes+=len(data)
            if len(data)>args.max_bytes or saved_bytes>args.max_total_bytes:raise ValueError('Prepared asset size limit reached')
            kind=media_type(data)
            sha=digest(data)
            ext=kind[1] if kind else 'bin';relative=('media/' if kind else 'supporting-assets/')+sha+'.'+ext
            target=inside(output,relative);target.parent.mkdir(exist_ok=True)
            if target.is_symlink():raise ValueError('Refusing output symlink')
            if target.exists() and digest(target.read_bytes())!=sha:raise ValueError('Prepared output file differs')
            if not target.exists():
                fd=os.open(target,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
                with os.fdopen(fd,'wb') as stream:stream.write(data)
            entry=dict(external_key='asset:'+digest(url.encode()),original_url=url,file=relative,sha256=sha,size=len(data),
                       mime=kind[0] if kind else documents.get(url,{}).get('mime','application/octet-stream'),
                       status=1 if capture or url in public_assets else 0)
            entry['record_hash']=record_hash(entry)
            (media if kind else support).append(entry)
    finally:
        if archive:archive.close()
    routes=[]
    for row in articles:
        slug=row['slug'];params={}
        if row['kind']=='post':function='article_detail';params={'id':{'article_key':row['external_key']}}
        elif re.fullmatch(r'[a-zA-Z][a-zA-Z0-9_-]{0,60}',slug) and '?' not in row['original_url']:
            function=slug.replace('-','_').lower()
        else:function='page_detail';params={'id':{'article_key':row['external_key']}}
        routes.append(dict(key=row['external_key'],old_url=row['original_url'],function=function,params=params,
                           expected_title=row['title'],expected_status=200 if row['status']==1 and (not row['published_at'] or row['published_at']<=int(datetime.now(timezone.utc).timestamp())) else 404))
    reasons=[]
    if any(r['snapshot'] for r in articles):reasons.append('HTML snapshots include headers/scripts: extract and sanitize main content before rendering in FBP')
    if any(r['source_status']=='protected' for r in articles):reasons.append('Password-protected content remains non-public; design access control separately without copying the source password')
    if missing:reasons.append('Missing referenced assets must be resolved or explicitly excluded')
    if support:reasons.append('CSS/JS/SVG/fonts are supporting code/assets: review before copying; never import as public uploads')
    if uploaded and any(r['status']==0 for r in media):reasons.append('Unreferenced export media is retained as non-public; review intended visibility and gallery/shortcode mappings')
    if capture and not capture['complete']:reasons.append('Public capture is partial; inspect errors, exclusions and pending URLs')
    if capture and any(d.get('decoding_replacements') for d in capture['documents']):reasons.append('Some text decoding used replacement characters; inspect source encodings')
    if excluded:reasons.append('Unmapped WordPress post types remain in the original export')
    reasons.append('Review named-route collisions, dynamic ID mappings, forms/search/plugins, publication dates and target schema')
    manifest=dict(schema='fbp.website.migration',version=1,created_at=now(),source=dict(url=source,mode='public' if capture else 'wxr',public_only=bool(capture)),
                  articles=articles,media=media,supporting_assets=support,missing_assets=missing,excluded_post_types=excluded,
                  source_attachments=attachments,
                  review=dict(required=True,reasons=reasons),
                  counts=dict(articles=len(articles),media=len(media),supporting_assets=len(support)))
    atomic_json(output/'migration.json',manifest)
    atomic_json(output/'routes.json',dict(schema='fbp.website.routes',version=1,review_required=True,routes=routes))
    capacities={}
    for section,rows in [('articles',articles),('media',media)]:
        fields={}
        for row in rows:
            for key,value in row.items():
                encoded=value if isinstance(value,str) else json.dumps(value,ensure_ascii=False,separators=(',',':'))
                fields[key]=max(fields.get(key,0),len(encoded.encode()))
        capacities[section]=fields
    atomic_json(output/'field-capacities.json',dict(schema='fbp.website.field-capacities',version=1,
        maximum_observed_utf8_bytes=capacities,note='Observed minimums only; adapt names/types and leave room for future edits.'))
    return dict(ok=True,manifest=str(output/'migration.json'),counts=manifest['counts'],missing_assets=len(missing),review_required=True)


def main():
    p=argparse.ArgumentParser(description=__doc__)
    source=p.add_mutually_exclusive_group(required=True);source.add_argument('--capture');source.add_argument('--wxr')
    assets=p.add_mutually_exclusive_group();assets.add_argument('--uploads');assets.add_argument('--uploads-zip')
    p.add_argument('--output',required=True);p.add_argument('--post-type',action='append',default=[])
    p.add_argument('--uploads-base-url',help='Public uploads URL prefix when it cannot be inferred from attachments')
    p.add_argument('--max-bytes',type=int,default=25*1024*1024);p.add_argument('--max-total-bytes',type=int,default=500*1024*1024)
    a=p.parse_args()
    try:print(json.dumps(prepare(a),ensure_ascii=False));return 0
    except Exception as error:print(json.dumps({'ok':False,'error':type(error).__name__+': '+str(error)[:180]}));return 2


if __name__=='__main__':raise SystemExit(main())
