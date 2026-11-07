#!/usr/bin/env python3
"""Verify prepared integrity, a CLI inventory and explicitly mapped public routes."""
import argparse
import json
from pathlib import Path
from urllib.parse import urljoin, urlsplit, urlencode, parse_qsl
import re

from migration_common import (PublicHTTP, atomic_json, read_json, digest, inside, now,
                              normalize, html_inventory, decode, mime_of, FetchError)
from website_migration_prepare import record_hash


def unwrap(value, schema):
    if value.get('schema')==schema:return value
    for row in value.get('console_log',[]):
        try:data=json.loads(row.get('log',''))
        except (ValueError,TypeError):continue
        if data.get('schema')==schema:return data
    raise ValueError('Expected '+schema+' or its FBP CLI envelope')


def validate_manifest(path):
    manifest=read_json(path,'fbp.website.migration');root=Path(path).resolve().parent
    issues=[]
    for section in ('articles','media','supporting_assets'):
        rows=manifest.get(section,[]);keys=[r.get('external_key') for r in rows]
        if len(keys)!=len(set(keys)) or any(not k for k in keys):issues.append(section+': duplicate/missing source keys')
        if manifest.get('counts',{}).get(section)!=len(rows):issues.append(section+': count mismatch')
        for row in rows:
            if row.get('record_hash')!=record_hash(row):issues.append(section+': record hash mismatch: '+str(row.get('external_key')))
            if section!='articles':
                file=inside(root,row['file'])
                if not file.is_file() or file.stat().st_size!=row['size'] or digest(file.read_bytes())!=row['sha256']:
                    issues.append(section+': missing/changed file: '+row['external_key'])
    if manifest.get('missing_assets'):issues.append('Unresolved source assets: '+str(len(manifest['missing_assets'])))
    if not manifest.get('articles'):issues.append('No articles/pages')
    return manifest,issues


def compare_inventory(manifest, inventory):
    issues=[]
    for section in ('articles','media'):
        actual={r['external_key']:r for r in inventory.get(section,[])}
        if len(actual)!=len(inventory.get(section,[])):issues.append(section+': duplicate target identifiers')
        for row in manifest[section]:
            current=actual.get(row['external_key'])
            if not current:issues.append(section+': missing target record: '+row['external_key']);continue
            if current.get('record_hash')!=row['record_hash']:issues.append(section+': target content/metadata differs: '+row['external_key'])
            if section=='media' and current.get('actual_sha256')!=row['sha256']:
                issues.append('Target media bytes differ: '+row['external_key'])
    return issues


def resolve_route(route, receipt):
    params={}
    for key,value in route.get('params',{}).items():
        if isinstance(value,dict):
            if set(value)!= {'article_key'}:raise ValueError('Unknown route placeholder')
            value=receipt.get('article_ids',{}).get(value['article_key'])
            if value is None:raise ValueError('Route requires an import receipt ID')
        params[key]=value
    return params


def verify(args):
    manifest,issues=validate_manifest(args.manifest)
    manifest_sha=digest(Path(args.manifest).read_bytes());checks={'integrity':not issues}
    if args.inventory:
        inventory=unwrap(read_json(args.inventory),'fbp.website.inventory')
        comparison=compare_inventory(manifest,inventory);issues+=comparison;checks['inventory']=not comparison
    urls=[]
    if args.base_url:
        if not args.routes:raise ValueError('--base-url requires a reviewed --routes file')
        routes=read_json(args.routes,'fbp.website.routes')
        if routes.get('review_required') is not False:raise ValueError('Review and adapt routes before HTTP verification')
        base=normalize(args.base_url).rstrip('/')+'/'
        receipt={}
        if args.import_receipt:
            receipt=unwrap(read_json(args.import_receipt),'fbp.website.import')
            if receipt.get('manifest_sha256')!=manifest_sha:raise ValueError('Import receipt belongs to another manifest')
        client=PublicHTTP([urlsplit(base).hostname],args.allow_asset_host,delay=args.delay,
                          max_requests=args.max_requests,allow_private=args.allow_private)
        for route in routes['routes']:
            function=route['function']
            if not isinstance(function,str) or not re.fullmatch(r'[a-zA-Z_][a-zA-Z0-9_]*',function):raise ValueError('Invalid FBP function')
            params=resolve_route(route,receipt)
            url=urljoin(base,'public_pages*'+function)+('?' + urlencode(params) if params else '')
            expected=int(route.get('expected_status',200))
            try:
                response=client.fetch(url,'page',robots=False)
                ok=response['status']==expected
                if not ok:issues.append('Page response differs: '+function+' expected '+str(expected))
                info=html_inventory(decode(response),response['url']) if expected==200 and 'html' in mime_of(response) else None
                if info:
                    title=route.get('expected_title')
                    if title and title not in info['title']:issues.append('Page title differs: '+function);ok=False
                    if not info.get('canonical'):issues.append('Canonical missing: '+function);ok=False
                    if info.get('canonical'):
                        canonical=normalize(info['canonical'],response['url']);expected_canonical=normalize(route.get('canonical_url') or url,base)
                        a,b=urlsplit(canonical),urlsplit(expected_canonical)
                        if (a.scheme,a.netloc,a.path,sorted(parse_qsl(a.query)))!=(b.scheme,b.netloc,b.path,sorted(parse_qsl(b.query))):
                            issues.append('Canonical differs: '+function);ok=False
                    for link in info['links']:
                        if urlsplit(link).hostname!=urlsplit(base).hostname:continue
                        try:
                            linked=client.fetch(link,'page',robots=False)
                            if linked['status']>=400:raise FetchError('Broken internal link')
                        except (FetchError,ValueError):issues.append('Internal link failed: '+link.split('?')[0]);ok=False
                    for asset in info['assets']:
                        if not client.allowed(asset,'asset'):
                            issues.append('Unverified external asset host: '+urlsplit(asset).hostname);ok=False;continue
                        try:
                            fetched=client.fetch(asset,'asset',robots=False)
                            if fetched['status']!=200:raise FetchError('Asset HTTP '+str(fetched['status']))
                        except (FetchError,ValueError):issues.append('Page asset failed: '+asset.split('?')[0]);ok=False
                urls.append(dict(url=url,status=response['status'],ok=ok))
            except (FetchError,ValueError) as error:
                issues.append('Page fetch failed: '+function+' '+str(error));urls.append(dict(url=url,ok=False))
        checks['public_routes']=bool(urls) and all(r['ok'] for r in urls)
    result=dict(schema='fbp.website.verification',version=1,ok=not issues,checked_at=now(),
                manifest_sha256=manifest_sha,checks=checks,issues=issues,urls=urls,
                review_required=manifest.get('review',{}).get('required',True),
                limitations=['HTTP checks do not prove browser layout, JavaScript operation, DNS propagation or mail delivery.'])
    if args.output:atomic_json(args.output,result)
    return result


def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--manifest',required=True)
    p.add_argument('--inventory');p.add_argument('--base-url');p.add_argument('--routes');p.add_argument('--import-receipt')
    p.add_argument('--output');p.add_argument('--allow-asset-host',action='append',default=[])
    p.add_argument('--allow-private',action='store_true');p.add_argument('--delay',type=float,default=.2)
    p.add_argument('--max-requests',type=int,default=1000)
    a=p.parse_args()
    try:result=verify(a);print(json.dumps(result,ensure_ascii=False));return 0 if result['ok'] else 1
    except Exception as error:print(json.dumps({'ok':False,'error':type(error).__name__+': '+str(error)[:180]}));return 2


if __name__=='__main__':raise SystemExit(main())
