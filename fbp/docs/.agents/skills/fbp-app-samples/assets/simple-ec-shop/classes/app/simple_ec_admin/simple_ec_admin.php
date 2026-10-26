<?php
require_once __DIR__.'/../simple_ec/Store.php';
class simple_ec_admin {
    private function db(Controller $ctl): array {
        if(!SimpleEcStore::administrator($ctl))throw new RuntimeException('ログインしてください。');return SimpleEcStore::open($ctl);
    }
    private function order(Controller $ctl,array $db,bool $allStatuses=false): array {
        $raw=$ctl->POST('id');$id=$ctl->decrypt($raw);$r=$db['orders']->get((int)$id);
        if(!$r || (!$allStatuses&&!SimpleEcStore::orderVisible($r)) || !SimpleEcStore::owns($ctl,$r))throw new RuntimeException('受注の権限がありません。');return $r;
    }
    function public_link(Controller $ctl){
        $db=$this->db($ctl);$shop=$db['shops']->get((int)$ctl->decrypt($ctl->POST('id')));if(!$shop || !SimpleEcStore::owns($ctl,$shop))throw new RuntimeException('ショップの権限がありません。');
        $ctl->assign('shop_url',$ctl->get_APP_URL('simple_ec_public','page',['shop'=>$shop['shop_code']]));$ctl->show_multi_dialog('sec_public_link','public_link.tpl','公開ショップの確認',800);
    }
    function details(Controller $ctl){
        try{$db=$this->db($ctl);$r=$this->order($ctl,$db);$items=$db['items']->select('order_id',$r['id']);$ctl->assign('order',$r);$ctl->assign('items',$items);$ctl->assign('order_token',$ctl->encrypt($r['id']));$ctl->show_multi_dialog('sec_order_details','details.tpl','受注内容',1000);}catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function pdf_dialog(Controller $ctl){
        try{$db=$this->db($ctl);$r=$this->order($ctl,$db,true);$ctl->assign('order',$r);$ctl->assign('order_token',$ctl->encrypt($r['id']));$ctl->show_multi_dialog('sec_pdf','download.tpl','受注帳票',700);}catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function pdf(Controller $ctl){
        try{$db=$this->db($ctl);$r=$this->order($ctl,$db,true);$shop=SimpleEcStore::shop($db,$r['login_id']);$items=$db['items']->select('order_id',$r['id']);}
        catch(Throwable $e){http_response_code(403);header('Cache-Control: private, no-store');$ctl->show_public_pages('error.tpl');return;}
        require_once __DIR__.'/../simple_ec/OrderReport.php';
        $pdf=SimpleEcOrderReport::build($ctl,$r,$shop,$items);
        // Explicit inline-preview requirement: retain the sample's authenticated POST
        // and reauthorization, but use the framework's inline output instead of attachment.
        $data=$pdf->get_pdf_data();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="order.pdf"; filename*=UTF-8\'\''.rawurlencode('納品書・領収書.pdf'));
        header('Cache-Control: private, no-store');
        header('Pragma: no-cache');
        echo $data;
        exit;
    }

    function csv_dialog(Controller $ctl){
        $this->db($ctl);
        $zone=new DateTimeZone($ctl->get_setting()['timezone']?:'Asia/Tokyo');
        $ctl->assign('csv_today',(new DateTimeImmutable('today',$zone))->getTimestamp());
        $ctl->show_multi_dialog('sec_csv','csv.tpl','受注CSV',700);
    }
    function csv(Controller $ctl){
        $db=$this->db($ctl);
        require_once __DIR__.'/../simple_ec/OrderCsvRange.php';
        $zone=new DateTimeZone($ctl->get_setting()['timezone']?:'Asia/Tokyo');
        try{$range=SimpleEcOrderCsvRange::bounds($ctl->POST('date_start'),$ctl->POST('date_end'),$zone);}
        catch(InvalidArgumentException $e){
            http_response_code(400);header('X-FBP-Download-Error: 1');
            $ctl->res_json(['title'=>'受注CSV','message'=>$e->getMessage()]);return;
        }
        header('Content-Type: text/csv; charset=UTF-8');
        $formatter=$ctl->create_ValueFormatter();
        $ctl->res_csv(['受注番号','ショップ','申込者氏名','送付先名称','メール','注文日','合計','商品名','種類','個数','単価'],'UTF-8');
        foreach($db['orders']->getall() as $r){
            if(!SimpleEcStore::orderVisible($r)||!SimpleEcStore::owns($ctl,$r)||!SimpleEcOrderCsvRange::contains($r['order_date'],$range,$zone))continue;
            foreach($db['items']->select('order_id',$r['id']) as $i){
                $line=[$r['order_code'],$r['login_id'],$r['from_name'],$r['name'],$r['mail'],$formatter->format_date($r['order_date']),$r['gokei'],$i['name'],$i['code'],$i['count'],$i['price']];
                $line=array_map(static fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v)?"'".$v:(string)$v,$line);$ctl->res_csv($line,'UTF-8');
            }
        }
    }
    private function payment(Controller $ctl,array $db): array {
        $raw=$ctl->POST('id');
        if(!is_string($raw) || !ctype_digit((string)($id=$ctl->decrypt($raw))))throw new RuntimeException('決済履歴を確認してください。');
        $p=$db['payments']->get((int)$id);
        if(!$p || !SimpleEcStore::owns($ctl,$p))throw new RuntimeException('決済履歴の権限がありません。');
        return $p;
    }
    function refund_dialog(Controller $ctl){
        try{
            require_once __DIR__.'/../simple_ec/Refund.php';
            $db=$this->db($ctl);$p=$this->payment($ctl,$db);SimpleEcRefund::completeOrder($db,$p);SimpleEcRefund::assertPaid($p);
            if(!$ctl->get_session('sec_refund_csrf'))$ctl->set_session('sec_refund_csrf',bin2hex(random_bytes(32)));
            $ctl->assign('payment',$p);$ctl->assign('payment_token',$ctl->encrypt($p['id']));
            $ctl->assign('refund_csrf',$ctl->get_session('sec_refund_csrf'));
            $ctl->show_multi_dialog('sec_refund','refund.tpl','決済の返金',700);
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function refund(Controller $ctl){
        try{
            require_once __DIR__.'/../simple_ec/Refund.php';
            $db=$this->db($ctl);$p=$this->payment($ctl,$db);
            $csrf=$ctl->POST('refund_csrf');$expected=$ctl->get_session('sec_refund_csrf');
            if(!is_string($csrf)||!is_string($expected)||$expected===''||!hash_equals($expected,$csrf))throw new RuntimeException('確認画面を開き直してください。');
            SimpleEcRefund::completeOrder($db,$p);SimpleEcRefund::assertPaid($p);
            $p=SimpleEcRefund::execute($db,(int)$p['id'],SimpleEcRefund::squareProvider($ctl,$p,$db));
            if(in_array($p['refund_state'],['FAILED','REJECTED'],true))throw new RuntimeException($p['refund_error']);
            $ctl->close_multi_dialog('sec_refund');$ctl->reload_work_area();
            $ctl->show_notification_text((int)$p['status']===2?'返金が完了しました。':'Squareで返金を処理中です。返金ボタンから状態を再確認できます。');
        }catch(Throwable $e){$ctl->res_error_message('refund',$e->getMessage());}
    }
    private function contract(Controller $ctl,array $db): array {
        SimpleEcTypes::requireType($ctl,4);
        $token=$ctl->POST('id');if(!is_string($token)||$token==='')throw new RuntimeException('サブスクリプションの新規作成は商品申込みから行ってください。');
        $r=$db['contracts']->get((int)$ctl->decrypt($token));
        if(!$r||!SimpleEcStore::owns($ctl,$r))throw new RuntimeException('契約が見つかりません。');return $r;
    }
    function contract_dialog(Controller $ctl){
        try{$db=$this->db($ctl);$r=$this->contract($ctl,$db);
            $r['getugaku']=(int)$r['getugaku'];$ctl->assign('contract',$r);$ctl->assign('contract_token',$ctl->encrypt($r['id']));$ctl->show_multi_dialog('sec_contract','contract.tpl','サブスクリプション編集',900);
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function contract_save(Controller $ctl){
        try{$db=$this->db($ctl);$r=$this->contract($ctl,$db);
            $r['getugaku']=SimpleEcStore::positiveInt($ctl->POST('getugaku'),100000000);
            foreach(['name','mail','item_name'] as $key){$v=$ctl->POST($key);if(!is_string($v)||strlen($v)>255)throw new RuntimeException('入力を確認してください。');$r[$key]=trim($v);}
            if(!$r['name']||!filter_var($r['mail'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('氏名・メールを入力してください。');
            $state=$ctl->POST('status');if(!is_scalar($state)||!in_array((string)$state,['0','1','2','3','9'],true))throw new RuntimeException('状態を確認してください。');
            if((int)$state===9&&(int)$r['status']!==9)$r['end_date']=time();
            if((int)$r['status']===9&&(int)$state!==9)throw new RuntimeException('終了した契約は再開できません。新しくお申し込みください。');
            $r['status']=(int)$state;$db['contracts']->update($r);
            $ctl->close_multi_dialog('sec_contract');$ctl->reload_work_area();
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function contract_history(Controller $ctl){
        try{$db=$this->db($ctl);$contract=$this->contract($ctl,$db);$rows=$db['payments']->select('contract_id',$contract['id']);
            usort($rows,fn($a,$b)=>(int)$b['created_at']<=>(int)$a['created_at']);
            foreach($rows as &$row){$row['result']=($ctl->get_constant_array('sec_payments_status',false))[$row['status']]??'';}unset($row);
            $ctl->assign('contract',$contract);$ctl->assign('payments',$rows);$ctl->show_multi_dialog('sec_contract_history','contract_history.tpl','決済履歴',1000);
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function card_link(Controller $ctl){
        SimpleEcTypes::requireType($ctl,4);
        $db=$this->db($ctl);$r=$db['contracts']->get((int)$ctl->decrypt($ctl->POST('id')));if(!$r||!SimpleEcStore::owns($ctl,$r))throw new RuntimeException('契約の権限がありません。');
        $ctl->assign('card_url',$ctl->get_APP_URL('simple_ec_public','card',['code'=>$r['card_token']]));$ctl->show_multi_dialog('sec_card_link','card_link.tpl','カード登録・変更の確認リンク',800);
    }
}
