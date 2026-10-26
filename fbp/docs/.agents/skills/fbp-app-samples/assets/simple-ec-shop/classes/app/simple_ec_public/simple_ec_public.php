<?php
require_once __DIR__.'/../simple_ec/Store.php';
require_once __DIR__.'/../simple_ec/SandboxPayment.php';
require_once __DIR__.'/../simple_ec/ProductionPayment.php';
require_once __DIR__.'/../simple_ec/MailContent.php';
require_once __DIR__.'/../simple_ec/Mail.php';
class simple_ec_public {
    function __construct(Controller $ctl){$ctl->set_check_login(false);}
    private function stage(Controller $ctl,array $db): void {
        if(!SimpleEcPaymentGuard::enabled($db))throw new RuntimeException('現在、ショップの受付は停止しています。');
        if(!SimpleEcPaymentGuard::isSandbox($ctl))SimpleEcPaymentGuard::assertProduction($ctl,$db);
    }
    private function configurePayment(Controller $ctl,array $db,array $shop): void {
        if(SimpleEcPaymentGuard::isSandbox($ctl))SimpleEcSandboxPayment::configure($ctl);
        else SimpleEcProductionPayment::configure($ctl,$db,$shop);
    }
    private function paymentError(Controller $ctl,string $function,array $param,string $message,?array $db=null,?array $shop=null,string $amount=''): void {
        try{
            if(!$db || !$shop)throw new RuntimeException($message);
            $this->configurePayment($ctl,$db,$shop);
            $ctl->show_square_dialog('simple_ec_public',$function,$param,$message,$amount);
        }catch(Throwable $e){$ctl->show_notification_text($message);}
    }
    private function context(Controller $ctl): array {
        $db=SimpleEcStore::open($ctl);$shop=$db['shops']->get((int)$ctl->get_session('sec_shop_id'));if(!$shop || (int)$shop['status']!==1)throw new RuntimeException('ショップが見つかりません。');$login=$shop['login_id'];
        $products=[];foreach($db['products']->select('login_id',$login) as $p)if(SimpleEcStore::productAllowed($shop,$p))$products[(int)$p['id']]=$p;
        return [$db,$shop,$products];
    }
    private function display(Controller $ctl,string $tpl): void {
        if(!$ctl->get_session('sec_csrf'))$ctl->set_session('sec_csrf',bin2hex(random_bytes(32)));
        $ctl->assign('sec_csrf',$ctl->get_session('sec_csrf'));
        $themeShop=$ctl->db('sec_shops','common')->get((int)$ctl->get_session('sec_shop_id'))?:[];
        $ctl->assign('sec_theme',SimpleEcStore::theme($themeShop));
        $ctl->assign('sec_style',$ctl->get_APP_URL('simple_ec_public','stylesheet'));
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') { $ctl->reload_area('#sec-content',$ctl->fetch('theme.tpl').$ctl->fetch($tpl)); return; }
        $ctl->assign('sec_template',$tpl);$ctl->show_public_pages('shell.tpl','head.tpl',null,null,['css_mode'=>'minimal']);
    }
    function stylesheet(Controller $ctl){header('Content-Type:text/css; charset=UTF-8');readfile(__DIR__.'/style.css');}
    function page(Controller $ctl){
        try {
            $db=SimpleEcStore::open($ctl);$requested=$ctl->GET('shop');
            if($requested!==null){
                if(!SimpleEcStore::validShopCode($requested))throw new RuntimeException('ショップが見つかりません。');
                $selectedShop=SimpleEcStore::shopByCode($db,$requested);
                if((int)$ctl->get_session('sec_shop_id')!==(int)$selectedShop['id']){$ctl->set_session('sec_cart',[]);$ctl->set_session('sec_checkout_key',null);$ctl->set_session('sec_checkout_confirmed',null);}
                $ctl->set_session('sec_shop_id',(int)$selectedShop['id']);
            }
            if(!$ctl->get_session('sec_shop_id')){
                $shops=[];foreach($db['shops']->getall() as $s){if((int)$s['status']!==1)continue;$s['url']=$ctl->get_APP_URL('simple_ec_public','page',['shop'=>$s['shop_code']]);$shops[]=$s;}
                $ctl->assign('shops',$shops);$this->display($ctl,'shops.tpl');return;
            }
            $this->catalog($ctl);
        }catch(Throwable $e){$ctl->assign('error',$e->getMessage());$this->display($ctl,'error.tpl');}
    }
    function catalog(Controller $ctl){
        try {
            [$db,$shop,$products]=$this->context($ctl);
            uasort($products,fn($a,$b)=>(int)$a['sort']<=>(int)$b['sort']);
            $categories=$db['categories']->select('login_id',$shop['login_id']);usort($categories,fn($a,$b)=>(int)$a['sort']<=>(int)$b['sort']);
            $ctl->assign('shop_intro_html',SimpleEcStore::richText((string)$shop['text0']));$ctl->assign('shop_footer_html',SimpleEcStore::richText((string)$shop['text1']));
            $legalHtml=[];foreach(['company','daihyo','address','tel','mail','oshiharai','soryo_txt','hikiwatashi_jiki','hikiwatashi_houhou','henpin'] as $field)$legalHtml[$field]=SimpleEcStore::richText((string)($shop[$field]??''));$ctl->assign('legal_html',$legalHtml);
            $names=[];foreach($categories as $c)$names[$c['id']]=$c['category'];
            foreach($products as &$p){$prices=[];for($v=1;$v<=5;$v++)if((string)$p['code'.$v]!=='')$prices[]=(int)$p['price'.$v];$p['min_price']=$prices?min($prices):0;$p['max_price']=$prices?max($prices):0;$p['monthly_price_including_tax']=(int)$p['teiki_getugaku']*(1+(int)$p['zeiritu']/100);$p['category_name']=$names[$p['category_id']]??'';$p['image_url']=$p['photo']?$ctl->get_APP_URL('simple_ec_public','image',['product'=>$p['id']]):'';}
            unset($p);$banners=[];for($i=1;$i<=4;$i++)if(!empty($shop['photo'.$i]) && (int)$shop['flg_photo'.$i]===1)$banners[]=$ctl->get_APP_URL('simple_ec_public','shop_image',['shop'=>$shop['shop_code'],'index'=>$i]);$ctl->assign('banners',$banners);$ctl->assign('shop',$shop);$ctl->assign('types',$ctl->get_constant_array('sec_product_types'));$groups=[];foreach($categories as $c){$groupProducts=array_values(array_filter($products,fn($p)=>(int)$p['hyoji']===0 && (int)$p['category_id']===(int)$c['id']));if($groupProducts)$groups[]=['id'=>$c['id'],'name'=>$c['category'],'products'=>$groupProducts];}
            $uncategorized=array_values(array_filter($products,fn($p)=>(int)$p['hyoji']===0 && !isset($names[$p['category_id']])));if($uncategorized)$groups[]=['id'=>0,'name'=>'','products'=>$uncategorized];
            $ctl->assign('product_groups',$groups);
            $this->display($ctl,'catalog.tpl');
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function image(Controller $ctl){
        try {
            $db=SimpleEcStore::open($ctl);$p=$db['products']->get((int)$ctl->GET('product'));
            if(!$p || (int)$p['hyoji']!==0 || !$p['photo'])throw new RuntimeException('Not found');
            $shop=SimpleEcStore::shop($db,$p['login_id']);if(!SimpleEcStore::productAllowed($shop,$p))throw new RuntimeException('Not found');
            $file=$ctl->get_file_info($p['photo'],false);if(!$file || !$ctl->is_saved_file($file['path']))throw new RuntimeException('Not found');
            $ctl->res_saved_image($file['path'],false);
        }catch(Throwable $e){http_response_code(404);}
    }
    function shop_image(Controller $ctl){
        try{$db=SimpleEcStore::open($ctl);$shop=SimpleEcStore::shopByCode($db,(string)$ctl->GET('shop'));$index=SimpleEcStore::positiveInt($ctl->GET('index'),4);
            if((int)$shop['flg_photo'.$index]!==1 || empty($shop['photo'.$index]))throw new RuntimeException('Not found');
            $file=$ctl->get_file_info($shop['photo'.$index],false);if(!$file || !$ctl->is_saved_file($file['path']))throw new RuntimeException('Not found');$ctl->res_saved_image($file['path'],false);
        }catch(Throwable $e){http_response_code(404);}
    }
    private function csrf(Controller $ctl): void {
        $token=$ctl->POST('sec_csrf');if(!is_string($token)||!$token||!hash_equals((string)$ctl->get_session('sec_csrf'),$token))throw new RuntimeException('画面を再読み込みしてください。');
    }
    function add(Controller $ctl){
        try {
            $this->csrf($ctl);[$db,$shop,$products]=$this->context($ctl);$id=SimpleEcStore::positiveInt($ctl->POST('product'),PHP_INT_MAX);$variant=SimpleEcStore::positiveInt($ctl->POST('variant')?:1,5);$count=SimpleEcStore::positiveInt($ctl->POST('quantity')?:1);
            $cart=$ctl->get_session('sec_cart')?:[];$key=$id.':'.$variant;
            $selectedProduct=$products[$id]??null;if(!$selectedProduct)throw new RuntimeException('商品を購入できません。');
            foreach($cart as $entry){$previous=$products[(int)$entry['id']]??null;if(!$previous || (int)$previous['type']!==(int)$selectedProduct['type']){$cart=[];break;}}
            $cart[$key]=['id'=>$id,'variant'=>$variant,'count'=>$count+(int)($cart[$key]['count']??0)];
            SimpleEcStore::quote($shop,$products,$cart);$ctl->set_session('sec_cart',$cart);$ctl->set_session('sec_checkout_key',null);$ctl->set_session('sec_checkout_confirmed',null);
            $ctl->invoke('cart',[],'simple_ec_public');
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function change(Controller $ctl){
        try {
            $this->csrf($ctl);[$db,$shop,$products]=$this->context($ctl);$cart=$ctl->get_session('sec_cart')?:[];$key=(string)$ctl->POST('key');
            if(!isset($cart[$key]))throw new RuntimeException('商品が見つかりません。');
            if($ctl->POST('remove'))unset($cart[$key]);else $cart[$key]['count']=SimpleEcStore::positiveInt($ctl->POST('quantity'),20);
            if($cart)SimpleEcStore::quote($shop,$products,$cart);
            $draftRaw=$ctl->POST('checkout_draft');
            if(is_string($draftRaw) && strlen($draftRaw)<=20000){
                $draft=json_decode($draftRaw,true);if(is_array($draft)){
                    $contact=[];foreach(['from_name','mail','from_tel','from_zipcode','from_address','name','tel','zipcode','address','memo'] as $field){$value=$draft[$field]??'';if(!is_string($value)||strlen($value)>($field==='memo'?1000:255))throw new RuntimeException('入力を確認してください。');$contact[$field]=$value;}
                    $ctl->set_session('sec_checkout_draft',['contact'=>$contact,'same_as_applicant'=>($draft['same_as_applicant']??'0')==='1']);
                }
            }
            $ctl->set_session('sec_cart',$cart);$ctl->set_session('sec_checkout_key',null);$ctl->set_session('sec_checkout_confirmed',null);$ctl->invoke('cart',[],'simple_ec_public');
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function cart(Controller $ctl){
        try{
            [$db,$shop,$products]=$this->context($ctl);$cart=$ctl->get_session('sec_cart')?:[];
            $ctl->assign('quote',$cart?SimpleEcStore::quote($shop,$products,$cart):null);$ctl->assign('shop',$shop);
            if(!$ctl->get_session('sec_checkout_key'))$ctl->set_session('sec_checkout_key',bin2hex(random_bytes(24)));
            $ctl->assign('sec_change_url',$ctl->get_APP_URL('simple_ec_public','change'));
            $ctl->assign('checkout_key',$ctl->get_session('sec_checkout_key'));
            $draft=$ctl->get_session('sec_checkout_draft')?:[];$ctl->assign('contact',$draft['contact']??[]);$ctl->assign('same_as_applicant',$draft['same_as_applicant']??true);
            $this->checkoutDialog($ctl,'cart.tpl','カート・お申込み');
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    private function checkoutDialog(Controller $ctl,string $template,string $title): void {
        if(!$ctl->get_session('sec_csrf'))$ctl->set_session('sec_csrf',bin2hex(random_bytes(32)));
        $ctl->assign('sec_csrf',$ctl->get_session('sec_csrf'));
        $ctl->show_multi_dialog('sec_checkout',$template,$title,900,null,['modal'=>true]);
    }
    function close_checkout(Controller $ctl){$ctl->close_multi_dialog('sec_checkout');}
    function confirm(Controller $ctl){
        try{
            $this->csrf($ctl);[$db,$shop,$products]=$this->context($ctl);$token=$ctl->POST('checkout_key');
            if(!is_string($token)||!$token||!hash_equals((string)$ctl->get_session('sec_checkout_key'),$token))throw new RuntimeException('カートからやり直してください。');
            $quote=SimpleEcStore::quote($shop,$products,$ctl->get_session('sec_cart')?:[]);
            $draft=SimpleEcStore::checkoutContact($ctl->POST(),$quote['type']);
            $ctl->set_session('sec_checkout_draft',$draft);$ctl->set_session('sec_checkout_confirmed',['token'=>$token,'signature'=>SimpleEcStore::quoteSignature($quote)]+$draft);
            $ctl->assign('quote',$quote);$ctl->assign('contact',$draft['contact']);$ctl->assign('checkout_key',$token);
            $this->checkoutDialog($ctl,'confirm.tpl','お申込み内容の確認');
        }catch(Throwable $e){$ctl->res_error_message('checkout',$e->getMessage());}
    }
    function submit(Controller $ctl){
        try{
            [$db,$shop,$products]=$this->context($ctl);$this->stage($ctl,$db);$this->csrf($ctl);
            $sandbox=SimpleEcPaymentGuard::isSandbox($ctl);
            $token=(string)$ctl->POST('checkout_key');
            if(!$token || !hash_equals((string)$ctl->get_session('sec_checkout_key'),$token))throw new RuntimeException('カートからやり直してください。');
            $key=($sandbox?'preview:':'live:').$token;$existing=$db['orders']->select('record_key',$key);
            if($existing){$this->checkoutPayment($ctl,$db,$existing[0]);return;}
            $quote=SimpleEcStore::quote($shop,$products,$ctl->get_session('sec_cart')?:[]);
            $confirmed=$ctl->get_session('sec_checkout_confirmed');
            if(!is_array($confirmed)||!hash_equals((string)($confirmed['token']??''),$token))throw new RuntimeException('入力内容の確認画面からお申し込みください。');
            if(!hash_equals($confirmed['signature'],SimpleEcStore::quoteSignature($quote)))throw new RuntimeException('商品・金額が変更されました。入力画面に戻り、内容を確認してください。');
            $contact=$confirmed['contact'];
            if(!$sandbox && (int)$quote['type']===4)SimpleEcPaymentGuard::recurringStartMonth($db);
            $order=$contact+['owner_account'=>$shop['id'],'login_id'=>$shop['login_id'],'status'=>$quote['total']===0?1:0,'order_code'=>'TEST-'.strtoupper(substr($token,0,10)),'order_date'=>date('Y-m-d'),'order_time'=>date('H:i:s'),'shokei'=>$quote['subtotal'],'zei'=>$quote['tax'],'soryo'=>$quote['shipping'],'gokei'=>$quote['total'],'type'=>$quote['type'],'paytype'=>(int)$shop['paytype']===0?'銀行振込（手動決済）':'Square Sandbox','record_key'=>$key];
            if(!$sandbox){$order['metadata_json']=json_encode(['checkout_state'=>'building']);$order['order_code']='';$order['paytype']=(int)$shop['paytype']===0?'銀行振込（手動決済）':'Square';}
            $id=$db['orders']->insert($order);
            if(!$sandbox){$order['order_code']='ORDER-'.$id;$db['orders']->update($order);}
            foreach($quote['lines'] as $line){$p=$line['product'];$item=['login_id'=>$shop['login_id'],'order_id'=>$id,'shohin_id'=>$p['id'],'name'=>$p['name'],'code'=>$line['code'],'count'=>$line['count'],'price'=>$line['price'],'record_key'=>$key.':'.$p['id'].':'.$line['variant']];$db['items']->insert($item);}
            if($quote['type']===4){
                $p=$quote['lines'][0]['product'];$contract=['login_id'=>$shop['login_id'],'owner_account'=>$p['owner_account']??0,'name'=>$contact['from_name'],'mail'=>$contact['mail'],'tel'=>$contact['from_tel'],'address'=>$contact['from_address'],'zipcode'=>$contact['from_zipcode'],'item_name'=>$p['name'],'status'=>3,'getugaku'=>$quote['total'],'regist_date'=>time(),'product_id'=>$p['id'],'card_token'=>bin2hex(random_bytes(32)),'record_key'=>$key.':contract','date_shoki_kessai'=>(int)$p['teiki_shokai']===0?time():strtotime('first day of next month 00:00:00')];
                if(!$sandbox){$contract['date_shoki_kessai']=max((int)$contract['date_shoki_kessai'],strtotime(SimpleEcPaymentGuard::recurringStartMonth($db).'-01 00:00:00'));if((int)$shop['paytype']===0)$contract['status']=0;}
                if(!$db['contracts']->select('record_key',$contract['record_key']))$db['contracts']->insert($contract);
            }
            $messages=SimpleEcMailContent::order($ctl,$shop,$order,$quote['lines']);
            SimpleEcStore::queue($db,$key.':customer',$shop['login_id'],$contact['mail'],$messages['customer']['subject'],$messages['customer']['body'],$sandbox?'テスト送信待ち':'送信待ち');
            SimpleEcStore::queue($db,$key.':shop',$shop['login_id'],$shop['mail'],$messages['shop']['subject'],$messages['shop']['body'],$sandbox?'テスト送信待ち':'送信待ち');
            if(!$sandbox){$order['metadata_json']=json_encode(['checkout_state'=>'ready']);$db['orders']->update($order);}
            $this->checkoutPayment($ctl,$db,$order);
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    private function checkoutPayment(Controller $ctl,array $db,array $order): void {
        $shop=SimpleEcStore::shop($db,$order['login_id']);$this->stage($ctl,$db);
        if(str_starts_with((string)$order['record_key'],'live:') && (json_decode($order['metadata_json']??'{}',true)['checkout_state']??'')!=='ready')throw new RuntimeException('受付処理の確認が必要です。管理者へお問い合わせください。');
        $ctl->set_session('sec_last_order',(int)$order['id']);
        if((int)$order['status']===1 || (int)$order['gokei']===0 || ((int)$shop['paytype']===0)){$this->finish($ctl,$db,$order);return;}
        $this->configurePayment($ctl,$db,$shop);
        $ctl->close_multi_dialog('sec_checkout');
        if((int)$order['type']===4){
            $rows=$db['contracts']->select('record_key',$order['record_key'].':contract');$contract=$rows[0]??null;
            if(!$contract)throw new RuntimeException('契約が見つかりません。');
            $ctl->set_session('sec_card_contract',(int)$contract['id']);$ctl->set_session('sec_card_checkout_order',(int)$order['id']);
            $ctl->invoke('register_card',[],'simple_ec_public');
        }else{$ctl->invoke('pay',[],'simple_ec_public');}
    }
    private function finish(Controller $ctl,array $db,array $order): void {SimpleEcMail::send($ctl,$db,[$order['record_key'].':customer',$order['record_key'].':shop']);$ctl->set_session('sec_checkout_draft',null);$ctl->set_session('sec_checkout_confirmed',null);$ctl->set_session('sec_last_order',(int)$order['id']);$ctl->set_session('sec_cart',[]);$ctl->assign('order',$order);$this->checkoutDialog($ctl,'finish.tpl','受付完了');}
    function pay(Controller $ctl){
        try{[$db,$shop,$products]=$this->context($ctl);$this->configurePayment($ctl,$db,$shop);$order=$db['orders']->get((int)$ctl->get_session('sec_last_order'));
            if($order && (int)$order['type']===4)throw new RuntimeException('定期契約はカード登録画面からお進みください。');
            if(!$order || $order['login_id']!==$shop['login_id'] || !str_starts_with($order['record_key'],SimpleEcPaymentGuard::isSandbox($ctl)?'preview:':'live:'))throw new RuntimeException('新規注文から進んでください。');
            $param=['order_token'=>$ctl->encrypt($order['id']),'name'=>($order['from_name']??'')?:$order['name'],'email'=>$order['mail'],'address'=>($order['from_address']??'')?:$order['address'],'square_button_label'=>'決済する'];
            $ctl->show_square_dialog('simple_ec_public','pay_callback',$param,'',(string)(int)$order['gokei']);
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function pay_callback(Controller $ctl){
        $param=$ctl->get_square_callback_parameter_array()??[];
        try{[$db,$shop,$products]=$this->context($ctl);$this->configurePayment($ctl,$db,$shop);
            $id=(int)$ctl->decrypt($param['order_token']??'');if($id!==(int)$ctl->get_session('sec_last_order'))throw new RuntimeException('注文セッションを確認してください。');
            $order=$db['orders']->get($id);if(!$order || (int)$order['type']===4 || $order['login_id']!==$shop['login_id'])throw new RuntimeException('注文を確認してください。');
            if(SimpleEcPaymentGuard::isSandbox($ctl))SimpleEcSandboxPayment::pay($ctl,$db,$order);else SimpleEcProductionPayment::pay($ctl,$db,$order);$paid=$db['orders']->get($id);$paid['owner_account']=$shop['id'];$db['orders']->update($paid);$ctl->close_square_dialog();$this->finish($ctl,$db,$paid);
        }catch(Throwable $e){
            $this->paymentError($ctl,'pay_callback',$param,$e->getMessage(),$db??null,$shop??null,isset($order)?(string)(int)$order['gokei']:'');
        }
    }
    function card(Controller $ctl){
        if(!SimpleEcTypes::allows($ctl,4)){$ctl->show_notification_text('定期購入は利用していません。');return;}
        try{$db=SimpleEcStore::open($ctl);$this->stage($ctl,$db);$code=$ctl->GET('code');
            if(!is_string($code)||!preg_match('/^[a-f0-9]{64}$/D',$code))throw new RuntimeException('リンクを確認してください。');
            $rows=$db['contracts']->select('card_token',$code);$r=$rows[0]??null;if(!$r)throw new RuntimeException('リンクが見つかりません。');
            $ctl->set_session('sec_card_contract',(int)$r['id']);$ctl->set_session('sec_card_checkout_order',null);$ctl->assign('contract',$r);$ctl->assign('card_available',(int)$r['status']!==9);$this->display($ctl,'card.tpl');
        }catch(Throwable $e){$ctl->assign('error',$e->getMessage());$this->display($ctl,'error.tpl');}
    }
    function register_card(Controller $ctl){
        if(!SimpleEcTypes::allows($ctl,4)){$ctl->show_notification_text('定期購入は利用していません。');return;}
        try{$db=SimpleEcStore::open($ctl);$this->stage($ctl,$db);$r=$db['contracts']->get((int)$ctl->get_session('sec_card_contract'));
            if(!$r||(int)$r['status']===9)throw new RuntimeException('この契約ではカード登録・変更を受け付けていません。');
            $shop=SimpleEcStore::shop($db,$r['login_id']);$this->configurePayment($ctl,$db,$shop);
            $param=['contract_token'=>$ctl->encrypt($r['id']),'name'=>$r['name'],'email'=>$r['mail'],'address'=>$r['address']??'','square_button_label'=>'カードを登録する'];$ctl->show_square_dialog('simple_ec_public','card_callback',$param);
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function card_callback(Controller $ctl){
        if(!SimpleEcTypes::allows($ctl,4)){$ctl->show_notification_text('定期購入は利用していません。');return;}
        $param=$ctl->get_square_callback_parameter_array()??[];
        try{$db=SimpleEcStore::open($ctl);$this->stage($ctl,$db);$id=(int)$ctl->decrypt($param['contract_token']??'');
            if($id!==(int)$ctl->get_session('sec_card_contract'))throw new RuntimeException('契約セッションを確認してください。');$r=$db['contracts']->get($id);
            if(!$r||(int)$r['status']===9)throw new RuntimeException('この契約ではカード登録・変更を受け付けていません。');
            $checkoutId=(int)$ctl->get_session('sec_card_checkout_order');$order=$checkoutId?$db['orders']->get($checkoutId):null;
            if($checkoutId&&(!$order || $r['record_key']!==$order['record_key'].':contract' || $r['login_id']!==$order['login_id']))throw new RuntimeException('申込みを確認してください。');
            if($order&&(int)$order['status']===1){$ctl->close_square_dialog();$this->finish($ctl,$db,$order);return;}
            if($order&&(int)$r['status']===2)throw new RuntimeException('前回の決済結果を確認中です。管理者に確認してください。');
            $shop=SimpleEcStore::shop($db,$r['login_id']);$this->configurePayment($ctl,$db,$shop);$sandbox=SimpleEcPaymentGuard::isSandbox($ctl);
            if(!$sandbox){
                SimpleEcProductionPayment::registerCard($ctl,$db,SimpleEcProductionPayment::contractKey($r),$r['login_id'],$r);
                $r['status']=1;$db['contracts']->update($r);
                if($order){
                    if((int)$r['date_shoki_kessai']<=time() && date('Y-m')>=SimpleEcPaymentGuard::recurringStartMonth($db)){
                        SimpleEcProductionPayment::chargeSubscriptions($ctl,$db,$id);
                        $payments=$db['payments']->select('idempotency_key',SimpleEcStore::paymentKey((string)$r['record_key'],date('Y-m')));$payment=$payments[0]??null;
                        if(!$payment || $payment['state']!=='succeeded')throw new RuntimeException('初回決済が完了していません。管理者に確認してください。');
                        $payment['order_id']=$order['id'];$db['payments']->update($payment);
                    }
                    $order['status']=1;$order['paytype']='Square';$db['orders']->update($order);$ctl->close_square_dialog();$this->finish($ctl,$db,$order);
                }else{$ctl->close_square_dialog();$ctl->show_notification_text('カードを登録しました。');}
                return;
            }
            $key='sandbox-contract:'.$id;$secrets=$db['secrets']->select('record_key',$key);$secret=$secrets[0]??['record_key'=>$key,'login_id'=>$r['login_id'],'source_table'=>'sandbox-contract','source_id'=>$id];
            $saved=json_decode($secret['payload']??'{}',true);$customer=$saved['customer_id']??'';
            if(!$customer){$customer=$ctl->square_regist_customer($r['name'],$r['mail'],$r['address']??'');if(!$customer)throw new RuntimeException($ctl->square_get_error()?:'Sandbox顧客登録に失敗しました。');}
            $card=$ctl->square_regist_card($customer);if(!$card)throw new RuntimeException($ctl->square_get_error()?:'Sandboxカード登録に失敗しました。');
            $secret['payload']=json_encode(['customer_id'=>$customer,'card_id'=>$card]);
            if(isset($secret['id']))$db['secrets']->update($secret);else $db['secrets']->insert($secret);
            if(str_starts_with($r['record_key'],'preview:')){$r['status']=1;$db['contracts']->update($r);}
            if($order){
                if((int)$r['date_shoki_kessai']<=time()){
                    SimpleEcSandboxPayment::chargeSubscriptions($ctl,$db,$id);
                    $payments=$db['payments']->select('idempotency_key',SimpleEcStore::paymentKey((string)$r['record_key'],date('Y-m')));$payment=$payments[0]??null;
                    if(!$payment || $payment['state']!=='succeeded')throw new RuntimeException('初回決済が完了していません。管理者に確認してください。');
                    $payment['order_id']=$order['id'];$db['payments']->update($payment);
                }
                $order['owner_account']=SimpleEcStore::shop($db,$r['login_id'])['id'];$order['status']=1;$order['paytype']='Square Sandbox';$db['orders']->update($order);
                $ctl->close_square_dialog();$this->finish($ctl,$db,$order);
            }else{$ctl->close_square_dialog();$ctl->show_notification_text('カードを登録しました。');}

        }catch(Throwable $e){$this->paymentError($ctl,'card_callback',$param,$e->getMessage(),$db??null,$shop??null);}
    }
}
