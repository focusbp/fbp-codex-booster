<?php
/** Public intake pattern adapted from the canonical public-intake sample. */
require_once __DIR__.'/../feature_sample_payment/feature_sample_payment.php';
require_once __DIR__.'/../feature_samples/feature_samples.php';
class public_pages {
    private const SHOP_PRODUCT = ['name'=>'サンプルノート','price'=>1200];
    function __construct(Controller $ctl){$ctl->set_check_login(false);$ctl->assign('viewport_public','width=device-width, initial-scale=1');}
    private function start(Controller $ctl,string $kind,array $context=[]):void {
        $p=['token'=>bin2hex(random_bytes(24)),'expires'=>time()+1800,'row'=>null]+$context;
        $ctl->set_session('sample_public_'.$kind,$p);$ctl->assign('token',$p['token']);$ctl->assign('kind',$kind);
    }
    private function pending(Controller $ctl,string $kind,bool $confirmation=false):?array {
        $p=$ctl->get_session('sample_public_'.$kind);$token=$ctl->POST('token');$key=$confirmation?'confirmation_token':'token';
        return is_array($p)&&is_string($token)&&is_string($p[$key]??null)&&$p['expires']>=time()&&hash_equals($p[$key],$token)?$p:null;
    }
    function sample_form(Controller $ctl):void {
        $this->start($ctl,'form',['fingerprint'=>feature_product_schema::fingerprint($ctl)]);$ctl->assign('fields',feature_product_schema::fields($ctl,true));$ctl->assign('page_title','公開フォームサンプル');
        $ctl->show_public_pages('sample_form.tpl','sample_head.tpl',null,null,['css_mode'=>'minimal']);
    }
    function sample_shop(Controller $ctl):void {
        $this->start($ctl,'shop',['fingerprint'=>feature_product_schema::fingerprint($ctl)]);$ctl->assign('fields',feature_product_schema::fields($ctl,true));$ctl->assign('product',self::SHOP_PRODUCT);$ctl->assign('page_title','単品ショップサンプル');
        $ctl->show_public_pages('sample_shop.tpl','sample_head.tpl',null,null,['css_mode'=>'minimal']);
    }
    private function confirm(Controller $ctl,string $kind):void {
        $p=$this->pending($ctl,$kind);if(!$p||!empty($p['completed'])){$ctl->res_error_message('token','画面を開き直してください。');return;}
        $row=[];$invalid=false;
            if(($p['fingerprint']??'')!==feature_product_schema::fingerprint($ctl)){$ctl->res_error_message('token','項目設定が変わりました。画面を開き直してください。');return;}
            $values=[];$display=[];
            foreach(feature_product_schema::fields($ctl,true) as $key=>$field){
                try{$column=ExcelGrid::column($key,['type'=>$field['type'],'length'=>$field['length'],'options'=>$field['options']??[]]);$values[$key]=feature_excel_ocr::normalize($column,$ctl->POST('note_'.$key)??'');$display[]=['title'=>$field['parameter_title'],'value'=>feature_product_schema::text($ctl,$field,$values[$key])];}
                catch(InvalidArgumentException $e){$ctl->res_error_message('note_'.$key,$e->getMessage());$invalid=true;}
            }
            if(!$invalid){$row=['name'=>'','email'=>'','address'=>'','quantity'=>1,'product_name'=>self::SHOP_PRODUCT['name'],'unit_price'=>self::SHOP_PRODUCT['price'],'total'=>self::SHOP_PRODUCT['price'],'input_values'=>json_encode($display,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
                // Customer metadata is optional and derived only from matching note fields.
                foreach(feature_product_schema::fields($ctl,true) as $key=>$field){$title=$field['parameter_title'];foreach(['name'=>['氏名','お名前','名前'],'email'=>['メールアドレス','メール'],'address'=>['住所','お届け先住所']] as $role=>$labels)if(in_array($title,$labels,true)||$key===$role)$row[$role]=(string)$values[$key];}
                if(strlen($row['input_values'])>20000){$ctl->res_error_message('token','入力内容が長すぎます。');return;}
                if($kind==='form'){$row=['name'=>$row['name'],'email'=>$row['email'],'message'=>'','input_values'=>$row['input_values']];}
                $ctl->assign('input_display',$display);
            }
        if($invalid)return;
        $p['row']=$row;$p['note_values']=$values;$p['confirmation_token']=bin2hex(random_bytes(24));$ctl->set_session('sample_public_'.$kind,$p);
        $ctl->assign('row',$row);$ctl->assign('kind',$kind);$ctl->assign('token',$p['confirmation_token']);$ctl->show_multi_dialog('sample-confirm','sample_confirm.tpl','内容の確認',600);
    }
    function sample_form_confirm(Controller $ctl):void {$this->confirm($ctl,'form');}
    function sample_shop_confirm(Controller $ctl):void {$this->confirm($ctl,'shop');}
    private function save(Controller $ctl,string $kind):void {
        $p=$this->pending($ctl,$kind,true);if(!$p||!is_array($p['row'])||!is_array($p['note_values']??null)){$ctl->res_error_message('token','入力内容を確認し直してください。');return;}
        $ctl->db('setting','setting');$ctl->db('settings','excel_grid');$ctl->db('states','excel_grid');$ctl->db(feature_product_schema::TABLE);
        $db=$kind==='shop'?$ctl->db('feature_sample_orders'):$ctl->db(feature_product_schema::TABLE);
        if(($p['fingerprint']??'')!==feature_product_schema::fingerprint($ctl)){$ctl->res_error_message('token','項目設定が変わりました。画面を開き直してください。');return;}
        $key=hash('sha256',$p['token']);
        if($kind==='form'){
            try{$saved=feature_product_schema::saveSubmission($ctl,$key,$p['note_values']??[]);if($saved['errors']){$ctl->res_error_message('token','入力はノートに保存済みです。計算エラーを確認してください。');return;}}
            catch(Throwable $e){$ctl->res_error_message('token','保存できませんでした。入力内容と項目設定を確認してください。');return;}
            $p['completed']=true;$p['note_row_id']=$saved['id'];$ctl->set_session('sample_public_form',$p);$ctl->set_session('sample_completed',true);$ctl->close_multi_dialog('sample-confirm');$ctl->res_redirect($ctl->get_APP_URL('public_pages','sample_complete'));return;
        }
        $existing=$db->select('request_key',$key,true);
        if($kind==='shop'){try{feature_sample_payment::configure($ctl);}catch(Throwable $e){$ctl->res_error_message('token',$e->getMessage());return;}}
        if(!$existing){
            if($kind==='shop'){
                $product=self::SHOP_PRODUCT;
                if(($p['fingerprint']??'')!==feature_product_schema::fingerprint($ctl)){$ctl->res_error_message('token','項目設定が変わりました。画面を開き直してください。');return;}
                if(!$product||$product['name']!==$p['row']['product_name']||(int)$product['price']!==$p['row']['unit_price']){$ctl->res_error_message('token','商品が変更されました。画面を開き直してください。');return;}
            }
            $row=$p['row']+['request_key'=>$key,'received_at'=>time()];if($kind==='shop')$row['payment_state']='pending';$id=$db->insert($row);
        }
        if($kind==='shop'){
            $saved=feature_product_schema::saveSubmission($ctl,$key,$p['note_values']??[]);
            $p['note_row_id']=$saved['id'];$db->update(['id'=>(int)($existing[0]['id']??$id),'note_row_id'=>$saved['id']]);
            if($saved['errors']){$ctl->res_error_message('token','入力はノートに保存済みです。計算エラーを確認してください。');return;}
            $p['order_id']=(int)($existing[0]['id']??$id);$ctl->set_session('sample_public_shop',$p);$ctl->close_multi_dialog('sample-confirm');$ctl->invoke('sample_pay',[],'public_pages');return;}
        $p['completed']=true;$ctl->set_session('sample_public_'.$kind,$p);$ctl->set_session('sample_completed',true);
        $ctl->close_multi_dialog('sample-confirm');$ctl->res_redirect($ctl->get_APP_URL('public_pages','sample_complete'));
    }
    function sample_form_save(Controller $ctl):void {$this->save($ctl,'form');}
    function sample_shop_save(Controller $ctl):void {$this->save($ctl,'shop');}
    function sample_pay(Controller $ctl):void {
        try{
            feature_sample_payment::configure($ctl);$p=$ctl->get_session('sample_public_shop');
            if(!is_array($p)||empty($p['order_id'])||$p['expires']<time())throw new RuntimeException('購入画面を開き直してください。');
            $order=$ctl->db('feature_sample_orders')->get($p['order_id']);
            if(!$order||$order['request_key']!==hash('sha256',$p['token']))throw new RuntimeException('注文を確認できません。');
            if($order['payment_state']==='paid'){$ctl->set_session('sample_completed',true);$ctl->res_redirect($ctl->get_APP_URL('public_pages','sample_complete'));return;}
            if($order['payment_state']!=='pending')throw new RuntimeException('前回の決済結果を管理側で確認してください。');
            $param=['grant'=>$p['confirmation_token'],'name'=>$order['name'],'email'=>$order['email'],'address'=>$order['address'],'square_button_label'=>'Sandboxでテスト決済する'];
            $ctl->show_square_dialog('public_pages','sample_pay_callback',$param,'',(string)(int)$order['total']);
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function sample_pay_callback(Controller $ctl):void {
        $param=$ctl->get_square_callback_parameter_array()??[];$configured=false;
        try{
            feature_sample_payment::configure($ctl);$configured=true;$p=$ctl->get_session('sample_public_shop');
            if(!is_array($p)||empty($p['order_id'])||$p['expires']<time()||!is_string($param['grant']??null)||!hash_equals($p['confirmation_token'],$param['grant']))throw new RuntimeException('購入セッションを確認できません。');
            $db=$ctl->db('feature_sample_orders');$order=$db->get($p['order_id']);
            if(!$order||$order['request_key']!==hash('sha256',$p['token']))throw new RuntimeException('注文を確認できません。');
            feature_sample_payment::pay($ctl,$db,(int)$p['order_id']);$p['completed']=true;$ctl->set_session('sample_public_shop',$p);$ctl->set_session('sample_completed',true);
            $ctl->close_square_dialog();$ctl->res_redirect($ctl->get_APP_URL('public_pages','sample_complete'));
        }catch(Throwable $e){
            // Keep the card dialog; processing/unknown states block a second charge server-side.
            if($configured)$ctl->show_square_dialog('public_pages','sample_pay_callback',$param,htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));else $ctl->show_notification_text($e->getMessage());
        }
    }
    function sample_complete(Controller $ctl):void {
        $ctl->assign('completed',(bool)$ctl->get_session('sample_completed'));$ctl->assign('page_title','受付結果');
        $ctl->show_public_pages('sample_complete.tpl','sample_head.tpl',null,null,['css_mode'=>'minimal']);
    }
}
