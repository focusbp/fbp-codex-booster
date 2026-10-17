#!/usr/bin/env python3
"""Integration checks on an explicit test runtime and disposable Excel note.
Usage: python3 excel_grid_test.py /path/to/web/app note_id
The note must have a name beginning excel_grid_. Retains the note for browser review.
"""
import json,re,subprocess,sys
from pathlib import Path
root=Path(sys.argv[1]).resolve(); note=int(sys.argv[2]); assert '/web/' in str(root)
checks=0

def cli(cmd,data):
    p=subprocess.run(['php','fbp/cli.php',cmd,'--json='+json.dumps(data)],cwd=root,capture_output=True,text=True,timeout=30)
    assert p.returncode==0,(p.stdout,p.stderr)
    return json.loads(p.stdout)

def call(fn,post=None):
    result=cli('app_call',{'class':'db_exe','function':fn,'windowcode':'excel_grid_integration','post':{'db_id':note,**(post or {})}})
    assert result.get('ok'),result
    return result['response_json']

def token(r):
    return re.search(r'name="excel_token" value="([a-f0-9]+)"',r['multi_dialog'][0]['html']).group(1)

def check(ok,desc):
    global checks
    assert ok,desc
    checks+=1;print('PASS',desc,flush=True)

def col(name,title,type='float',mode='input',formula='',error=False):
    t=token(call('excel_column',{'column':name}))
    r=call('excel_column_save',{'excel_token':t,'title':title,'type':type,'mode':mode,'formula':formula,'digits':1,'rounding':'round'})
    if not error:check(not r.get('errormessage'),f'column {name} saves')
    return r

def save(name,value,id=0):
    t=token(call('excel_cell',{'column':name,'row_id':id}))
    return call('excel_save',{'excel_token':t,name:value})

def rows():return cli('data_list',{'table':table,'max':1000})['items']

tables=cli('db_tables_list',{}).get('items',[])
entry=next(x for x in tables if int(x['id'])==note);table=entry['tb_name'];assert table.startswith('excel_grid_')
call('excel_page')
col('a','数量');col('b','単価');col('c','金額',mode='calculation',formula='(A * B) * 1.1')
check(bool(col('d','型エラー',type='text',mode='calculation',formula='A + B',error=True).get('errormessage')),'non-numeric calculation rejected')
check(bool(col('d','循環',mode='calculation',formula='D + 1',error=True).get('errormessage')),'self-reference rejected')
col('az','末尾','text')
r=save('a','3');check(not r.get('errormessage'),'new row saves');id=rows()[0]['id']
check(rows()[0]['c']=='','blank input leaves calculation blank')
save('b','100',id);check(float(rows()[0]['c'])==330.0,'formula calculates and persists after second cell')
save('az','末尾の値',id);check(rows()[0]['az']=='末尾の値','AZ persists')
t=token(call('excel_cell',{'column':'a','row_id':id}));r=call('excel_save',{'excel_token':t,'a':'bad'});check(bool(r.get('errormessage')),'invalid input stays in popup')
r=call('excel_save',{'excel_token':t,'a':'4'});check(not r.get('errormessage'),'same popup can correct validation error')
r=call('excel_save',{'excel_token':t,'a':'8'});check(bool(r.get('errormessage')),'duplicate input operation rejected')
check(rows()[0]['a']=='4','duplicate did not overwrite value')
col('c','金額',mode='calculation',formula='A / B')
save('b','0',id);r=call('excel_page');html=r['work_area']['html'];check('>ERROR<' in html,'division by zero displayed as ERROR')
check(rows()[0]['c']=='','ERROR not stored in numeric value')
save('b','2',id);check(float(rows()[0]['c'])==2.0,'successful recalculation clears ERROR')
t=token(call('excel_row_menu',{'row_id':id}));r=call('excel_row_action',{'excel_token':t,'action':'copy'});check(not r.get('errormessage'),'session row copy')
t=token(call('excel_row_menu',{'row_id':0}));r=call('excel_row_action',{'excel_token':t,'action':'paste'});check(not r.get('errormessage'),'session row paste auto saves')
newid=rows()[0]['id'];check(newid!=id and rows()[0]['a']=='4' and float(rows()[0]['c'])==2.0,'paste creates row and recomputes')
t=token(call('excel_row_menu',{'row_id':newid}));r=call('excel_row_action',{'excel_token':t,'action':'delete'});check(bool(r.get('errormessage')),'delete requires confirmation')
r=call('excel_row_action',{'excel_token':t,'action':'delete','confirmed':'1'});check(not r.get('errormessage'),'confirmed delete saves')
col('c','金額',mode='calculation',formula='A * B')
print(f'PASS {checks} integration checks')
