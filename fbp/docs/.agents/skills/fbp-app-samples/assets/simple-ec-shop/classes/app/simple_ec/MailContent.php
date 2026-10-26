<?php
/** Plain-text notification wording; recipient selection is handled separately. */
final class SimpleEcMailContent {
    public static function order(Controller $ctl,array $shop,array $order,array $lines): array {
        $type=(int)$order['type'];
        foreach(['shokei','zei','soryo','gokei'] as $field)$order[$field]=(int)($order[$field]??0);
        $order['order_date']=$ctl->create_ValueFormatter()->format_date($order['order_date']);
        $items=[];foreach($lines as $line)$items[]=['name'=>$line['product']['name'],'code'=>$line['code'],'count'=>(int)$line['count'],'unit'=>($line['product']['unit']??'')?:'個','price'=>(int)$line['price']];
        $ctl->assign('type',$type);$ctl->assign('shop',$shop);$ctl->assign('order',$order);$ctl->assign('shohin_list',$items);$ctl->assign('management_url',$ctl->get_APP_URL());
        $labels=[0=>['購入','ご購入'],1=>['申し込み','お申し込み'],2=>['お問い合わせ','お問い合わせ受付'],3=>['見積依頼','見積依頼受付']];
        if($type===4){$shopSubject='ショップからのサブスクリプション購入のお知らせ';$customerSubject='【'.$shop['shopname'].'】サブスクリプションお申込みのお知らせ';}
        else{$pair=$labels[$type]??$labels[0];$shopSubject='【'.$order['order_code'].'】 ショップからの'.$pair[0].'のお知らせ';$customerSubject='【'.$shop['shopname'].'】'.$pair[1].'のお知らせ';}
        return ['shop'=>['subject'=>$shopSubject,'body'=>$ctl->fetch_string(file_get_contents(__DIR__.'/mail_templates/order_shop.tpl'))],
            'customer'=>['subject'=>$customerSubject,'body'=>$ctl->fetch_string(file_get_contents(__DIR__.'/mail_templates/order_customer.tpl'))]];
    }
    public static function recurringError(Controller $ctl,array $shop,array $contract): array {
        $token=(string)($contract['card_token']??'');
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('カード登録リンクが設定されていません。');
        $ctl->assign('teiki',$contract);$ctl->assign('shop',$shop);$ctl->assign('card_url',$ctl->get_APP_URL('simple_ec_public','card',['code'=>$token]));
        return ['subject'=>'【'.$shop['shopname'].'】カード変更のお願い','body'=>$ctl->fetch_string(file_get_contents(__DIR__.'/mail_templates/recurring_error.tpl'))];
    }
}
