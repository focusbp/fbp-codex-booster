#!/usr/bin/env python3
"""Create new Excel mail sample notes in the explicit test runtime; never reset existing notes."""
import argparse,json,pathlib,subprocess
p=argparse.ArgumentParser();p.add_argument('--test-root',required=True);p.add_argument('--receipt',required=True);p.add_argument('--resume',action='store_true');a=p.parse_args();root=pathlib.Path(a.test_root).resolve();receipt=pathlib.Path(a.receipt).resolve()
assert (root/'fbp/cli.php').is_file() and (root/'classes/app/feature_mail_samples/feature_mail_samples.php').is_file()
if receipt.exists() and not a.resume:raise SystemExit('Receipt already exists. Inspect completed operations before resuming.')
def cli(command,data={}):
 r=subprocess.run(['php','fbp/cli.php',command,'--json='+json.dumps(data,ensure_ascii=False)],cwd=root,capture_output=True,text=True,check=True);v=json.loads(r.stdout)
 if v.get('ok') is False:raise RuntimeError(v)
 return v
notes=cli('db_tables_list')['items'];tables=['feature_sample_mail_send','feature_sample_mail_receive','feature_sample_mail_send_log']
if not a.resume and any(n['tb_name'] in tables for n in notes):raise SystemExit('Mail sample notes already exist; do not overwrite.')
assert int(cli('setting_get')['setting']['force_testmode'])==1
ops=json.loads(receipt.read_text())['operations'] if a.resume else [];step=0;receipt.parent.mkdir(parents=True,exist_ok=True)
def apply(cmd,data):
 global step
 index=step;step+=1
 if index<len(ops):
  assert ops[index]['command']==cmd
  return {'ok':True,'id':ops[index]['id']}
 receipt.write_text(json.dumps({'operations':ops,'pending':{'command':cmd,'data':data}},ensure_ascii=False,indent=2));v=cli(cmd,data);ops.append({'command':cmd,'id':v.get('id')});receipt.write_text(json.dumps({'operations':ops,'pending':None},ensure_ascii=False,indent=2));return v
for table,title,columns in [(tables[0],'サンプル：メール送信',[('a','氏名','text'),('b','メールアドレス','text')]),(tables[1],'サンプル：メール受信',[('a','日付','date'),('b','タイトル','text'),('c','内容','textarea')])]:
 n=apply('db_tables_add',{'tb_name':table,'menu_name':title,'show_menu':1,'screen_build_type':0,'list_type':4,'list_width':800,'edit_width':800,'sortkey':'excel_sort','sort_order':3})
 if table==tables[1]:apply('db_fields_add',{'db_id':n['id'],'parameter_name':'receipt_key','parameter_title':'受信識別キー','type':'text','length':64})
 config={'schema_version':1,'tb_name':table,'list_type':4,'columns':{key:{'title':label,'type':typ,'length':3000 if typ=='textarea' else (15 if typ=='date' else 255),'mode':'input','generated':True,'column_width':350 if typ=='textarea' else 200} for key,label,typ in columns},'column_order':[chr(97+i) for i in range(26)]+['a'+chr(97+i) for i in range(26)]}
 path=receipt.parent/(table+'.json');path.write_text(json.dumps(config,ensure_ascii=False));apply('excel_settings_import',{'tb_name':table,'file':str(path)})
 fn='send_form' if table==tables[0] else 'receive_form';label='メール送信' if table==tables[0] else 'メール受信'
 apply('db_additionals_add',{'tb_name':table,'place':0,'button_title':label,'class_name':'feature_mail_samples','function_name':fn,'button_type':0,'dialog_width':700,'sort':100})
n=apply('db_tables_add',{'tb_name':tables[2],'menu_name':'メール送信処理ログ','show_menu':0,'screen_build_type':0,'list_type':0})
for key,label,typ,length in [('request_key','操作キー','text',64),('status','状態','text',20),('to_email','送信先','text',255),('created_at','日時','datetime',15)]:apply('db_fields_add',{'db_id':n['id'],'parameter_name':key,'parameter_title':label,'type':typ,'length':length})
apply('email_format_add',{'key':'feature_sample_excel_mail','template_name':'Excelメール送信サンプル','subject':'{$mail_subject}','body':'{$mail_name} 様\n\n{$mail_body}'})
print(json.dumps({'ok':True,'notes':tables,'operations':len(ops)},ensure_ascii=False))
