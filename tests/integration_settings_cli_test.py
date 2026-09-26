"""Exercise the real test-runtime storage without changing existing credentials."""
import base64
import json
from pathlib import Path
import subprocess
import sys
import uuid

root=Path(sys.argv[1]).resolve()
assert root.parent==Path('/home/nakama/web'), 'Test runtime only'
cli=root/'fbp/cli.php'
def run(cmd, data):
    r=subprocess.run(['php',str(cli),cmd,'--json='+json.dumps(data)],cwd=root,capture_output=True,check=True)
    return json.loads(r.stdout)
def endpoint(p):return run('app_call',{'class':'integration_settings','function':'execute','post':p})
name='codex_integration_'+uuid.uuid4().hex[:12]
secret='NOT-A-REAL-CREDENTIAL-'+uuid.uuid4().hex
row=run('data_add',{'table':'external_keys','data':{'key':name,'title':'外部キー / 検証','value':''}})['id']
try:
    catalog=endpoint({'operation':'catalog'})['catalog']
    assert any(r=={'key':name,'title':'外部キー / 検証'} for r in catalog)
    s=endpoint({'operation':'status','service':'external_keys','catalog':catalog})
    assert not s['fields'][name]['registered']
    p={'operation':'apply','service':'external_keys','environment':'test','request_id':uuid.uuid4().hex,'revision':s['revision'],'catalog':catalog,'catalog_revision':s['catalog_revision'],'changes':{name:{'op':'set','value':secret}}}
    applied=endpoint(p)
    assert applied['ok'] and applied['fields'][name]['registered']
    assert secret not in json.dumps(applied) and base64.b64encode(secret.encode()).decode() not in json.dumps(applied)
    assert endpoint(p)==applied, 'Retry must be idempotent'
    stale=dict(p,request_id=uuid.uuid4().hex)
    assert not endpoint(stale)['ok']
    invalid=dict(p,request_id=uuid.uuid4().hex,revision=applied['revision'],changes={'unknown_key':{'op':'set','value':'bad'}})
    assert not endpoint(invalid)['ok']
    delete=dict(p,request_id=uuid.uuid4().hex,revision=applied['revision'],changes={name:{'op':'delete'}})
    deleted=endpoint(delete)
    assert deleted['ok'] and not deleted['fields'][name]['registered']
    assert any(r['key']==name for r in endpoint({'operation':'catalog'})['catalog'])
    for log in (root/'classes/log/ffm').rglob('*.jsonl'):
        text=log.read_text(errors='replace')
        assert secret not in text and base64.b64encode(secret.encode()).decode() not in text, 'Secret leaked to operation log'
    print('PASS: actual FFM catalog, invalid credential storage, secret-free response/log, retry, conflict, scoped update, explicit clear')
finally:
    run('data_delete',{'table':'external_keys','id':row})
