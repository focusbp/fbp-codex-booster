#!/usr/bin/env python3
"""Install isolated feature sample definitions in an explicit web-side test app.
Never resets existing notes; a receipt records each completed operation for recovery.
"""
import argparse,json,pathlib,subprocess
p=argparse.ArgumentParser();p.add_argument('--test-root',required=True);p.add_argument('--receipt',required=True);p.add_argument('--with-sample-data',action='store_true');a=p.parse_args()
root=pathlib.Path(a.test_root).resolve();cli=root/'fbp/cli.php'
if not cli.is_file() or not (root/'classes/app/feature_samples/feature_samples.php').is_file():raise SystemExit('Sync sample code to a test app first')
receipt=pathlib.Path(a.receipt).resolve();receipt.parent.mkdir(parents=True,exist_ok=True)
if receipt.exists():raise SystemExit('Receipt exists; inspect before restarting')
def call(command,data=None):
 result=subprocess.run(['php',str(cli),command,'--json='+json.dumps(data or {},ensure_ascii=False)],cwd=root,text=True,capture_output=True)
 if result.returncode:raise RuntimeError(command+': '+result.stderr[:1000]+result.stdout[:1000])
 obj=json.loads(result.stdout)
 if obj.get('ok') is False:raise RuntimeError(str(obj))
 return obj
setting=call('setting_get');v=setting.get('setting',setting.get('item',setting))
# Do not dump setting values: may contain API credentials.
if not isinstance(v,dict):raise SystemExit('Invalid settings result')
if int(v.get('force_testmode',0))!=1:raise SystemExit('Developer test mode required')
notes=call('db_tables_list')['items']
owned=['feature_sample_products','feature_sample_orders']
if any(n['tb_name'] in owned for n in notes):raise SystemExit('Sample note exists. Inspect receipt; do not rerun or overwrite.')
operations=[]
def apply(cmd,data):
 receipt.write_text(json.dumps({'operations':operations,'pending':{'command':cmd,'data':data}},ensure_ascii=False,indent=2))
 obj=call(cmd,data)
 if not obj.get('ok'):raise RuntimeError(str(obj))
 operations.append({'command':cmd,'id':obj.get('id')})
 receipt.write_text(json.dumps({'operations':operations,'pending':None},ensure_ascii=False,indent=2))
 return obj
specs=[('feature_sample_products','サンプル：商品・7機能',[
 ('checkout_key','申込識別キー','text',64),('code','商品コード','text',60),('name','商品名','text',255),('price','価格（円）','number',24),('memo','補足','textarea',1500)],['code','name','price'],['code','name']),
 ('feature_sample_orders','サンプル：注文受付',[
 ('note_row_id','保存先ノートの行ID','number',24),('input_values','入力内容','textarea',20000),('product_name','商品名','text',255),('unit_price','単価（円）','number',24),('quantity','数量','number',24),('total','合計（円）','number',24),('name','お名前','text',300),('email','メールアドレス','text',762),('address','お届け先住所','textarea',900),('received_at','受付日時','datetime',15),('request_key','受付キー','text',64),('payment_state','テスト決済状態','text',100),('payment_id','Square決済ID','text',100)],['product_name','input_values','total','received_at','payment_state','payment_id'],['product_name','name'])]
for tb,title,fields,listing,search in specs:
 dbid=apply('db_tables_add',{'tb_name':tb,'menu_name':('ショップ決済管理（内部）' if tb=='feature_sample_orders' else title),'show_menu':(0 if tb=='feature_sample_orders' else 1),'screen_build_type':0,'list_type':0,'list_width':800,'edit_width':800,'sortkey':'id','sort_order':4})['id']
 for key,label,typ,length in fields:
  d={'db_id':dbid,'parameter_name':key,'parameter_title':label,'type':typ,'length':length}
  if key=='email':d['format_check']='email'
  apply('db_fields_add',d)
 screens={'list':listing,'search':search,'delete':[listing[0]]}
 if tb==owned[0]:screens.update({'add':['code','name','price','memo'],'edit':['code','name','price','memo']})
 for screen,keys in screens.items():
  for i,key in enumerate(keys):apply('screen_fields_add',{'tb_name':tb,'screen_name':screen,'parameter_name':key,'sort':i+1})
for i,(title,fn) in enumerate([('CSVアップロード','upload_form'),('CSVダウンロード','download_form'),('PDF一覧印刷','pdf_form'),('公開フォーム','form_link'),('単品ショップ','shop_link'),('MCP連携','mcp_link'),('AI-OCR読み込み','ocr_form')]):
 apply('db_additionals_add',{'tb_name':owned[0],'place':0,'button_title':title,'button_type':0,'class_name':'feature_samples','function_name':fn,'dialog_width':800,'sort':100-i})
for code,name,price,memo in ([('SAMPLE-A','サンプルノート',1200,'架空の商品です。'),('SAMPLE-B','サンプルペン',300,'CSV・PDF・MCPの共通データ'),('SAMPLE-C','サンプルファイル',500,'公開ショップの受注確認用')] if a.with_sample_data else []):
 apply('data_add',{'table':owned[0],'data':{'code':code,'name':name,'price':price,'memo':memo}})
print(json.dumps({'ok':True,'notes':owned,'operations':len(operations)},ensure_ascii=False))
