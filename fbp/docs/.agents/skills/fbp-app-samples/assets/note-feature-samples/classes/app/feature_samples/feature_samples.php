<?php
/** Seven feature examples. All management endpoints retain the default FBP login. */
require_once __DIR__.'/../feature_product_schema/feature_product_schema.php';
require_once __DIR__.'/../feature_excel_ocr/feature_excel_ocr.php';
class feature_samples {
    const TABLE = 'feature_sample_products';
    static function product(array $input): array {
        $row=[];
        foreach (['code'=>60,'name'=>255,'memo'=>1500] as $key=>$max) {
            $v=$input[$key]??'';
            if(!is_scalar($v)||!mb_check_encoding((string)$v,'UTF-8')) throw new InvalidArgumentException('文字列を確認してください。');
            $row[$key]=trim((string)$v);
            if(strlen($row[$key])>$max) throw new InvalidArgumentException($key.'が長すぎます。');
        }
        if(!preg_match('/^[A-Za-z0-9_-]{1,60}$/D',$row['code'])||$row['name']==='') throw new InvalidArgumentException('商品コードは半角英数字・ハイフン・下線、商品名は必須です。');
        $price=$input['price']??'';
        if(!is_scalar($price)||!preg_match('/^(0|[1-9][0-9]{0,6})$/D',(string)$price)) throw new InvalidArgumentException('価格は0〜9999999の整数で指定してください。');
        $row['price']=(int)$price;
        return $row;
    }
    static function products(Controller $ctl): array { return feature_product_schema::rows($ctl); }
    private function token(Controller $ctl,string $kind): string {
        $token=bin2hex(random_bytes(24));$ctl->set_session('feature_'.$kind,['token'=>$token,'expires'=>time()+1800,'fingerprint'=>feature_product_schema::fingerprint($ctl)]);return $token;
    }
    private function valid(Controller $ctl,string $kind): bool {
        $p=$ctl->get_session('feature_'.$kind);$token=$ctl->POST('token');
        return is_array($p)&&is_string($token)&&$p['expires']>=time()&&($p['fingerprint']??'')===feature_product_schema::fingerprint($ctl)&&hash_equals($p['token'],$token);
    }
    function upload_form(Controller $ctl):void {
        $ctl->assign('token',$this->token($ctl,'csv'));$ctl->assign('csv_headers',implode(' / ',array_column(feature_product_schema::fields($ctl,true),'parameter_title')));
        $ctl->show_multi_dialog('feature-csv','upload.tpl','CSVアップロード',700);
    }
    function upload_exe(Controller $ctl):void {
        if(!$this->valid($ctl,'csv')){$ctl->res_error_message('token','画面を開き直してください。');return;}
        $file=$_FILES['file']??[];
        $path=$file['tmp_name']??'';
        if(($file['error']??1)!==UPLOAD_ERR_OK||!is_string($path)||!is_file($path)||filesize($path)>1048576||(!(defined('CLI_APP_CALL')&&CLI_APP_CALL)&&!is_uploaded_file($path))){$ctl->res_error_message('file','1MB以下のCSVを選択してください。');return;}
        try {
            $fields=feature_product_schema::fields($ctl,true);$keys=array_keys($fields);$headers=array_column($fields,'parameter_title');$roles=feature_product_schema::roles($ctl);
            $ctl->db('states','excel_grid');$db=$ctl->db(self::TABLE);
            $handle=fopen($path,'rb');if(!$handle)throw new InvalidArgumentException('CSVを開けません。');
            try {
                $header=fgetcsv($handle,0,',','"','');
                if(is_array($header))$header[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$header[0]);
                if($header!==$headers)throw new InvalidArgumentException('現在の列順のヘッダーは '.implode(' / ',$headers).' です。UTF-8で保存してください。');
                $rows=[];$codes=[];$line=1;
                while(($data=fgetcsv($handle,0,',','"',''))!==false){
                    ++$line;if($data===[null])continue;
                    if(count($data)!==count($keys))throw new InvalidArgumentException($line.'行目の列数が違います。');
                    $row=array_combine($keys,$data);
                    foreach($fields as $key=>$field){$c=ExcelGrid::column($key,['type'=>$field['type'],'length'=>$field['length'],'options'=>$field['options']??[]]);$row[$key]=feature_excel_ocr::normalize($c,$row[$key]);}
                    $business=self::product(feature_product_schema::product($ctl,$row));
                    if(isset($codes[$business['code']]))throw new InvalidArgumentException('商品コードがCSV内で重複しています。');
                    $codes[$business['code']]=true;$rows[]=$row;
                    if(count($rows)>500)throw new InvalidArgumentException('500行以内で指定してください。');
                }
                if(!$rows)throw new InvalidArgumentException('データ行がありません。');
            } finally {fclose($handle);}
            // Validate every row before writing. One FFM holds the check/save lock.
            $existing=[];$max=0;
            foreach($db->getall() as $r){$code=(string)$r[$roles['code']];if(isset($existing[$code]))throw new InvalidArgumentException('保存済みの商品コードに重複があります。');$existing[$code]=$r;$max=max($max,(int)($r['excel_sort']??0));}
            $calculationErrors=[];
            foreach($rows as $row){$code=$row[$roles['code']];$from=isset($existing[$code])?'edit':'add';if($from==='edit'){$row['id']=$existing[$code]['id'];$db->update($row);}else{$row['excel_sort']=++$max;$db->insert($row);}if(feature_product_schema::recalculate($ctl,$db->get($row['id']),$from))$calculationErrors[]=$code;}
            if($calculationErrors){$ctl->res_error_message('file','入力は保存済みです。計算エラーの商品: '.implode(', ',$calculationErrors));return;}
        }catch(InvalidArgumentException $e){$ctl->res_error_message('file',$e->getMessage());return;}
        $ctl->close_multi_dialog('feature-csv');$ctl->show_notification_text(count($rows).'件を取り込みました。');
        $ctl->reload_work_area();
    }
    function download(Controller $ctl):void {
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="sample-products.csv"');header('Cache-Control: private, no-store');
        $fields=feature_product_schema::fields($ctl);$ctl->res_csv(array_column($fields,'parameter_title'),'UTF-8');
        foreach(self::products($ctl) as $r){$line=[];foreach($fields as $key=>$field){$v=feature_product_schema::text($ctl,$field,$r[$key]??'');if(preg_match('/^[\x00-\x20]*[=+@-]/',$v))$v="'".$v;$line[]=$v;}$ctl->res_csv($line,'UTF-8');}
    }
    function download_form(Controller $ctl):void {$ctl->show_multi_dialog('feature-download','download.tpl','CSVダウンロード',600);}
    function pdf_form(Controller $ctl):void {
        $ctl->assign('token',$this->token($ctl,'pdf'));$ctl->assign('row_count',count(self::products($ctl)));
        $ctl->show_multi_dialog('feature-pdf','pdf.tpl','PDF一覧印刷',600);
    }
    function pdf(Controller $ctl):void {
        if(!$this->valid($ctl,'pdf')){http_response_code(400);$ctl->show_public_pages('error.tpl');return;}
        // Explicit inline preview: keep the authenticated POST/token flow, return PDF inline in the new tab.
        $rows=self::products($ctl);$fields=feature_product_schema::fields($ctl);$table=[array_column($fields,'parameter_title')];
        foreach($rows as $row){$line=[];foreach($fields as $key=>$field)$line[]=feature_product_schema::text($ctl,$field,$row[$key]??'');$table[]=$line;}
        $pdf=$ctl->create_pdfmaker();$pdf->setPageLayout(['pagesize'=>'A4','orientation'=>'L','font'=>'gothic','pagenumber'=>'on']);
        $pdf->addPage();$pdf->addText('商品サンプル一覧',['fontsize'=>18]);
        $n=count($fields);$widths=array_fill(0,$n,floor(100/$n));$widths[$n-1]+=100-array_sum($widths);$align=[];foreach($fields as $field)$align[]=in_array($field['type'],['number','float'],true)?'R':'L';$pdf->addTable($table,['columnsize'=>$widths,'columnalign'=>$align]);header('Cache-Control: private, no-store');$ctl->stop_res=true;$ctl->display_flg=true;$pdf->create_pdf();
    }
    function form_link(Controller $ctl):void {
        $ctl->assign('form_url',$ctl->get_APP_URL('public_pages','sample_form'));
        $ctl->show_multi_dialog('feature-form-link','form_link.tpl','公開フォーム',700);
    }
    function shop_link(Controller $ctl):void {
        $ctl->assign('shop_url',$ctl->get_APP_URL('public_pages','sample_shop'));
        $ctl->show_multi_dialog('feature-shop-link','shop_link.tpl','単品ショップ',700);
    }
    function mcp_link(Controller $ctl):void {
        $ctl->assign('share_url',$ctl->get_APP_URL('mcp_server','rpc'));
        $ctl->assign('share_url_help','このURLをMCPクライアントに登録してください。接続にはOAuth認証が必要です。');
        $ctl->assign('share_url_open_label','MCPサーバーURLを開く');
        $ctl->show_multi_dialog('feature-mcp-link','mcp_link.tpl','MCP連携',700);
    }
    function ocr_form(Controller $ctl):void { $ctl->invoke('run',[],'feature_products_ocr'); }
}
