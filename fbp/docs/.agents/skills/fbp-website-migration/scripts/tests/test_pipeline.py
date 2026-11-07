import argparse
import base64
from contextlib import contextmanager
import gzip
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import threading
import unittest
from unittest.mock import patch
import zipfile

SCRIPTS=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(SCRIPTS))
import migration_common as common
import website_migration_collect as collect
import website_migration_prepare as prepare
import website_migration_verify as verify
import website_migration_status as progress

PNG=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=')


@contextmanager
def site(control=None):
    control=control if control is not None else {}
    requests=[]
    class Handler(BaseHTTPRequestHandler):
        def log_message(self,*args):pass
        def do_POST(self):requests.append(('POST',self.path));self.send_error(500)
        def do_GET(self):
            requests.append(('GET',self.path));path=self.path.split('?')[0]
            base='http://127.0.0.1:'+str(self.server.server_port)
            status=200;mime='text/html';body=b''
            if path=='/robots.txt':mime='text/plain';body=b'User-agent: *\nDisallow: /blocked\n'
            elif path=='/':body=b'<html><head><title>Fixture</title><link rel="stylesheet" href="/site.css"><link rel="icon" href="/logo.png"></head><body><a href="/post/">post</a><a href="/secret?token=do-not-log-this">skip</a><img src="/logo.png" srcset="/small.png 1x"><script src="/app.js"></script><form method="post" action="/forbidden-write"></form></body></html>'
            elif path=='/post/':body=b'<html><title>Post One</title><p>Public post</p><img src="/logo.png"></html>'
            elif path in ('/logo.png','/small.png'):mime='image/png';body=PNG
            elif path=='/absent.png' and control.get('available'):mime='image/png';body=PNG
            elif path=='/broken/':body=b'<html><title>Broken</title><img src="/absent.png"></html>'
            elif path=='/site.css':mime='text/css';body=b'@import "/theme.css";body{background:url(/logo.png)}'
            elif path=='/theme.css':mime='text/css';body=b'body{color:#123}'
            elif path=='/app.js':mime='text/javascript';body=b'fetch("/public-data.json").then(r=>r.json()).then(r=>{document.body.insertAdjacentHTML("beforeend","<p id=rendered>"+r.text+"</p>")});fetch("/forbidden-write",{method:"POST",body:"no"}).catch(()=>{});'
            elif path=='/public-data.json':mime='application/json';body=b'{"text":"Rendered content"}'
            elif path=='/sitemap.xml':mime='application/xml';body=f'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>{base}/post/</loc></url></urlset>'.encode()
            elif path=='/feed/':mime='application/rss+xml';body=f'<rss><channel><item><link>{base}/post/</link></item></channel></rss>'.encode()
            elif path.startswith('/wp-json/wp/v2/'):
                name=path.rsplit('/',1)[1];mime='application/json'
                items={'posts':[dict(id=7,type='post',link=base+'/post/',status='publish',date_gmt='2024-01-02T03:04:05',title={'rendered':'Post One'},content={'rendered':'<p>Hello 日本語😀\u2028line<img src="'+base+'/logo.png"></p>'},categories=[3],featured_media=5)],
                    'pages':[dict(id=2,type='page',link=base+'/',status='publish',title={'rendered':'Fixture'},content={'rendered':'<p>Home</p>'},categories=[])],
                    'categories':[dict(id=3,name='News')],'media':[dict(id=5,source_url=base+'/logo.png',media_details={'sizes':{'small':{'source_url':base+'/small.png'}}})]}.get(name,[])
                body=json.dumps(items).encode()
            elif path=='/redirect-other':self.send_response(302);self.send_header('Location','http://localhost:'+str(self.server.server_port)+'/post/');self.end_headers();return
            elif path=='/bomb':mime='text/plain';body=gzip.compress(b'x'*100000)
            elif path=='/blocked':body=b'do not fetch'
            elif path=='/limited':status=429;mime='text/plain';body=b'retry later'
            elif path=='/public_pages*top':body=f'<html><head><title>Fixture</title><link rel="canonical" href="{base}/public_pages*top"></head><img src="/logo.png"></html>'.encode()
            elif path=='/public_pages*article_detail':body=f'<html><head><title>Post One</title><link rel="canonical" href="{base}/public_pages*article_detail?id=1"></head><img src="/logo.png"></html>'.encode()
            else:status=404;body=b'not found'
            self.send_response(status);self.send_header('Content-Type',mime)
            if path=='/bomb':self.send_header('Content-Encoding','gzip')
            self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
    server=ThreadingHTTPServer(('127.0.0.1',0),Handler)
    thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
    try:yield 'http://127.0.0.1:'+str(server.server_port)+'/',requests
    finally:server.shutdown();server.server_close();thread.join()


def collect_args(url,folder,**extra):
    values=dict(url=url,output=str(folder),resume=False,retry_failed=False,allow_host=[],allow_asset_host=[],
        wordpress='auto',wp_type=[],render_js=False,allow_private=True,delay=0,timeout=5,max_bytes=1024*1024,
        max_total_bytes=10*1024*1024,max_requests=200,max_pages=20,max_assets=30,max_records=100,
        browser_command=None,browser_executable='/usr/bin/google-chrome')
    return argparse.Namespace(**(values|extra))


def prepare_args(folder,**extra):
    return argparse.Namespace(**(dict(output=str(folder),capture=None,wxr=None,uploads=None,uploads_zip=None,
        uploads_base_url=None,post_type=[],max_bytes=1024*1024,max_total_bytes=10*1024*1024)|extra))


class PipelineTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory(dir=os.environ.get('MIGRATION_TEST_TMP'))
        self.addCleanup(self.temp.cleanup);self.root=Path(self.temp.name)

    def capture(self,**extra):
        with site() as (url,requests):
            result=collect.Collector(collect_args(url,self.root/'capture',**extra)).run()
            return common.read_json(self.root/'capture/capture.json'),result,requests

    def test_public_rest_sitemap_assets_and_readonly(self):
        capture,result,requests=self.capture()
        self.assertTrue(result['ok'],capture['errors'])
        self.assertTrue(any(d['kind']=='api' and d.get('wp_items') for d in capture['documents']))
        self.assertTrue(any(d['final_url'].endswith('/theme.css') for d in capture['documents']))
        self.assertFalse(any('secret' in p or method!='GET' for method,p in requests))
        self.assertNotIn('do-not-log-this',json.dumps(capture))
        prepare.prepare(prepare_args(self.root/'prepared',capture=str(self.root/'capture')))
        manifest,issues=verify.validate_manifest(self.root/'prepared/migration.json')
        self.assertEqual(issues,[])
        self.assertEqual(len(manifest['articles']),2)
        post=next(a for a in manifest['articles'] if a['kind']=='post')
        self.assertEqual(post['categories'],['News'])
        self.assertTrue(post['featured_media_url'].endswith('/logo.png'))
        self.assertTrue(manifest['supporting_assets'])

    def test_resume_limits_and_tamper_detection(self):
        with site() as (url,requests):
            first=collect.Collector(collect_args(url,self.root/'capture',max_pages=1)).run()
            self.assertFalse(first['ok']);self.assertGreater(first['pending'],0)
            second=collect.Collector(collect_args(url,self.root/'capture',resume=True)).run()
            self.assertTrue(second['ok'])
            state=common.read_json(self.root/'capture/capture.json')
            common.inside(self.root/'capture',state['documents'][0]['object']).write_bytes(b'tampered')
            with self.assertRaises(ValueError):collect.Collector(collect_args(url,self.root/'capture',resume=True))

    def test_scope_robots_private_address_and_gzip_limit(self):
        with site() as (url,requests):
            http=common.PublicHTTP(['127.0.0.1'],delay=0,allow_private=True,max_bytes=1024)
            with self.assertRaises(common.FetchError):http.fetch(url+'blocked')
            self.assertFalse(any(p=='/blocked' for _,p in requests))
            with self.assertRaises(common.FetchError):http.fetch(url+'redirect-other')
            with self.assertRaises(common.BudgetError):http.fetch(url+'bomb')
            protected=common.PublicHTTP(['127.0.0.1'],delay=0)
            with self.assertRaises(common.FetchError):protected.fetch(url,robots=False)
            with self.assertRaises(common.BudgetError):http.fetch(url+'limited')
            count=len(requests)
            with self.assertRaises(common.BudgetError):http.fetch(url+'post/')
            self.assertEqual(len(requests),count)

    def test_html_fallback_and_missing_media(self):
        state,result,_=self.capture(wordpress='off')
        prepare.prepare(prepare_args(self.root/'prepared',capture=str(self.root/'capture')))
        manifest,_=verify.validate_manifest(self.root/'prepared/migration.json')
        self.assertTrue(all(r['snapshot'] for r in manifest['articles']))
        self.assertTrue(manifest['review']['required'])
        file=common.inside(self.root/'prepared',manifest['media'][0]['file']);file.write_bytes(b'bad')
        _,issues=verify.validate_manifest(self.root/'prepared/migration.json')
        self.assertTrue(any('changed file' in i for i in issues))

    def test_wxr_zip_privacy_custom_types_and_archive_safety(self):
        wxr=self.root/'source.xml'
        wxr.write_text('''<rss xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><link>https://example.org/</link>
<item><title>Draft</title><link>https://example.org/draft/</link><wp:post_type>page</wp:post_type><wp:post_id>1</wp:post_id><wp:status>draft</wp:status><content:encoded><![CDATA[<img src="https://example.org/wp-content/uploads/a.png">]]></content:encoded></item>
<item><title>Event</title><link>https://example.org/event/</link><wp:post_type>works</wp:post_type><wp:post_id>2</wp:post_id><wp:status>future</wp:status></item>
<item><title>Protected</title><link>https://example.org/protected/</link><wp:post_type>page</wp:post_type><wp:post_id>3</wp:post_id><wp:status>publish</wp:status><wp:post_password>fixture-password-not-real</wp:post_password><content:encoded><![CDATA[<p>Private body</p>]]></content:encoded></item>
<item><wp:post_type>attachment</wp:post_type><wp:post_id>5</wp:post_id><wp:attachment_url>https://example.org/wp-content/uploads/a.png</wp:attachment_url></item></channel></rss>''')
        archive=self.root/'uploads.zip'
        with zipfile.ZipFile(archive,'w') as z:z.writestr('uploads/a.png',PNG);z.writestr('uploads/size/a-small.png',PNG)
        prepare.prepare(prepare_args(self.root/'prepared',wxr=str(wxr),uploads_zip=str(archive),post_type=['works=post']))
        manifest,issues=verify.validate_manifest(self.root/'prepared/migration.json')
        self.assertEqual(issues,[]);self.assertEqual(len(manifest['media']),2)
        self.assertTrue(all(r['status']==0 for r in manifest['articles']+manifest['media']))
        self.assertTrue(any(r['source_status']=='protected' for r in manifest['articles']))
        self.assertNotIn('fixture-password-not-real',json.dumps(manifest))
        bad=self.root/'bad.zip'
        with zipfile.ZipFile(bad,'w') as z:z.writestr('../escape',b'bad')
        with self.assertRaises(ValueError):prepare.prepare(prepare_args(self.root/'bad-output',wxr=str(wxr),uploads_zip=str(bad)))

    def test_progress_requires_evidence_and_separates_publication(self):
        self.capture();prepare.prepare(prepare_args(self.root/'prepared',capture=str(self.root/'capture')))
        workspace=str(self.root/'progress');plan=str(self.root/'prepared/migration.json')
        args=dict(workspace=workspace,action='init',appcode='app-fixture',plan=plan)
        self.assertFalse(progress.run(argparse.Namespace(**args))['published'])
        evidence=self.root/'evidence.json';evidence.write_text('{"ok":true}')
        for stage in progress.PUBLISHED:
            result=progress.run(argparse.Namespace(workspace=workspace,action='record',stage=stage,state='verified',note='fixture verified',evidence=str(evidence)))
        self.assertTrue(result['published']);self.assertFalse(result['migration_complete'])
        state=common.read_json(self.root/'progress/progress.json');count=len(state['events'])
        progress.run(argparse.Namespace(workspace=workspace,action='record',stage=stage,state='verified',note='fixture verified',evidence=str(evidence)))
        self.assertEqual(len(common.read_json(self.root/'progress/progress.json')['events']),count)
        evidence.write_text('{"ok":false}')
        changed=progress.run(argparse.Namespace(workspace=workspace,action='show'))
        self.assertFalse(changed['published']);self.assertTrue(changed['stale_evidence'])
        Path(plan).write_text(Path(plan).read_text()+'\n')
        self.assertTrue(progress.run(argparse.Namespace(workspace=workspace,action='show'))['stale_plan'])

    def test_unicode_urls_and_safe_local_paths(self):
        self.assertIn('%E3%83%AD',common.normalize('https://example.org/ロゴ.png'))
        self.assertIn('my%20image',common.normalize('https://example.org/my image.png'))
        for value in ('file:///etc/passwd','https://u:password@example.org/','https://example.org/?token=secret'):
            with self.assertRaises(ValueError):common.normalize(value)
        with self.assertRaises(ValueError):common.inside(self.root,'../escape')

    def test_legacy_japanese_encoding(self):
        body='<meta charset="shift_jis"><title>日本語のページ</title>'.encode('shift_jis')
        response=dict(headers={'content-type':'text/html'},body=body)
        self.assertIn('日本語のページ',common.decode(response))

    def test_query_rest_discovery_and_retry_failed_missing_asset(self):
        control={}
        with site(control) as (base,requests):
            args=collect_args(base+'broken/',self.root/'capture',wordpress='off')
            owner=collect.Collector(args);owner.api(base+'?rest_route=/')
            rest=[q['url'] for q in owner.queue if 'rest_route=' in q['url']]
            self.assertTrue(rest)
            from urllib.parse import parse_qs,urlsplit
            self.assertEqual(parse_qs(urlsplit(rest[0]).query)['rest_route'],['/wp/v2/posts'])
            first=owner.run();self.assertFalse(first['ok'])
            prepare.prepare(prepare_args(self.root/'partial',capture=str(self.root/'capture')))
            partial,issues=verify.validate_manifest(self.root/'partial/migration.json')
            self.assertTrue(partial['missing_assets']);self.assertTrue(issues)
            control['available']=True
            done=collect.Collector(collect_args(base+'broken/',self.root/'capture',wordpress='off',resume=True,retry_failed=True)).run()
            self.assertTrue(done['ok'])

    def test_http_verification_requires_reviewed_routes(self):
        self.capture();prepare.prepare(prepare_args(self.root/'prepared',capture=str(self.root/'capture')))
        args=argparse.Namespace(manifest=str(self.root/'prepared/migration.json'),inventory=None,
            base_url='https://example.org/',routes=str(self.root/'prepared/routes.json'),import_receipt=None,
            allow_private=False,allow_asset_host=[],delay=0,max_requests=10,output=None)
        with self.assertRaises(ValueError):verify.verify(args)

    def test_cli_entrypoints_end_to_end(self):
        capture=self.root/'cli-capture';prepared=self.root/'cli-prepared'
        with site() as (base,_):
            result=subprocess.run([sys.executable,str(SCRIPTS/'website_migration_collect.py'),base,
                '--output',str(capture),'--allow-private','--delay','0'],capture_output=True,text=True)
            self.assertEqual(result.returncode,0,result.stdout+result.stderr)
        result=subprocess.run([sys.executable,str(SCRIPTS/'website_migration_prepare.py'),
            '--capture',str(capture),'--output',str(prepared)],capture_output=True,text=True)
        self.assertEqual(result.returncode,0,result.stdout+result.stderr)
        result=subprocess.run([sys.executable,str(SCRIPTS/'website_migration_verify.py'),
            '--manifest',str(prepared/'migration.json')],capture_output=True,text=True)
        self.assertEqual(result.returncode,0,result.stdout+result.stderr)
        self.assertTrue(json.loads(result.stdout)['review_required'])

    def test_php_import_dry_run_idempotency_conflicts_inventory_and_http_verification(self):
        state,result,_=self.capture()
        prepare.prepare(prepare_args(self.root/'prepared',capture=str(self.root/'capture')))
        plan=self.root/'prepared/migration.json'
        importer=SCRIPTS.parent/'assets/website_import/website_import.php'
        raw=subprocess.check_output(['php',str(Path(__file__).with_name('importer_fixture.php')),str(importer),str(plan),str(self.root/'php-upload')],text=True)
        result=json.loads(raw)
        self.assertEqual(result['receipt']['articles_created'],2)
        self.assertEqual(result['again']['media_created'],0)
        manifest,_=verify.validate_manifest(plan)
        self.assertEqual(verify.compare_inventory(manifest,result['inventory']),[])
        self.assertTrue(verify.compare_inventory(manifest,result['edited_inventory']))
        routes=common.read_json(self.root/'prepared/routes.json');routes['review_required']=False
        common.atomic_json(self.root/'prepared/routes.json',routes)
        common.atomic_json(self.root/'receipt.json',result['receipt'])
        common.atomic_json(self.root/'inventory.json',result['inventory'])
        with site() as (base,_):
            args=argparse.Namespace(manifest=str(plan),inventory=str(self.root/'inventory.json'),base_url=base,
                routes=str(self.root/'prepared/routes.json'),import_receipt=str(self.root/'receipt.json'),
                allow_private=True,allow_asset_host=[],delay=0,max_requests=50,output=None)
            verified=verify.verify(args)
            self.assertTrue(verified['ok'],verified['issues'])
            self.assertEqual(set(verified['checks']),{'integrity','inventory','public_routes'})

    @unittest.skipUnless(os.environ.get('MIGRATION_BROWSER_COMMAND'),'Set MIGRATION_BROWSER_COMMAND for the optional browser integration test')
    def test_rendered_dom_uses_scoped_get_bridge_and_blocks_post(self):
        state,result,requests=self.capture(wordpress='off',render_js=True,browser_command=os.environ['MIGRATION_BROWSER_COMMAND'])
        rendered=[d for d in state['documents'] if d.get('rendered') and d['final_url'].endswith('/')]
        self.assertTrue(any(b'id="rendered"' in common.load_object(self.root/'capture',d) for d in rendered))
        self.assertFalse(any(method=='POST' for method,_ in requests))


if __name__=='__main__':unittest.main()
