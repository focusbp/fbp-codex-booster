<?php
require_once __DIR__.'/PaymentGuard.php';
require_once __DIR__.'/Types.php';
final class SimpleEcStore {
    private static array $types=[];
    public static function configureTypes(array $labels): void {self::$types=array_fill_keys(array_keys($labels),true);}
    public static function open(Controller $ctl): array {
        self::configureTypes(SimpleEcTypes::labels($ctl));
        // Pre-open the mail template DB before reading transaction state.
        $ctl->db('email_format','email_format');
        $db=[];foreach(['categories','shops','products','orders','items','contracts','payments','runtime','outbox','secrets'] as $t)$db[$t]=$ctl->db('sec_'.$t,'common');return $db;
    }
    public static function administrator(Controller $ctl): bool {
        return (string)$ctl->get_login_id()!=='' && (int)$ctl->get_login_type()===0;
    }
    public static function owns(Controller $ctl,array $row): bool {return self::administrator($ctl);}
    public static function orderVisible(array $order): bool {return in_array((string)($order['status']??''),['0','1','2','3','4'],true);}
    public static function validShopCode($code): bool {return is_string($code) && strlen($code)<=64 && preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D',$code)===1;}
    public static function shopByCode(array $db,string $code): array {
        if(!self::validShopCode($code))throw new RuntimeException('ショップが見つかりません。');
        $rows=$db['shops']->select('shop_code',$code);if(count($rows)!==1 || (int)$rows[0]['status']!==1)throw new RuntimeException('ショップが見つかりません。');return $rows[0];
    }
    public static function shop(array $db,string $login): array {
        foreach($db['shops']->select('login_id',$login) as $r)return $r;
        throw new RuntimeException('ショップが設定されていません。');
    }
    public static function theme(array $shop): array {
        $shop['color_bg']=$shop['color_title_fg']=$shop['color_shohin_bg']='FFFFFF';
        $fields=['bg'=>['color_bg','FFFFFF'],'fg'=>['color_fg','2D2D2D'],'title-bg'=>['color_title_bg','132351'],'title-fg'=>['color_title_fg','FFFFFF'],'primary'=>['color_button_bg','0468E4'],'primary-fg'=>['color_button_fg','FFFFFF'],'hover'=>['color_button_hover','132351'],'border'=>['color_shohin_border','CCCCCC'],'surface'=>['color_shohin_bg','FFFFFF'],'heading'=>['color_h','293761']];
        $out=[];foreach($fields as $name=>[$field,$fallback]){$value=ltrim(trim((string)($shop[$field]??'')),'#');if(!preg_match('/^(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/D',$value))$value=$fallback;if(strlen($value)===3)$value=$value[0].$value[0].$value[1].$value[1].$value[2].$value[2];$out[$name]='#'.strtoupper($value);}
        $rgb=sscanf($out['primary'],'#%02x%02x%02x');$brightness=(299*$rgb[0]+587*$rgb[1]+114*$rgb[2])/1000;
        $out['outline-text']=$brightness>160?$out['primary-fg']:$out['primary'];
        return $out;
    }
    public static function richText(string $html): string {
        if(!class_exists('DOMDocument'))return nl2br(htmlspecialchars(strip_tags($html),ENT_QUOTES,'UTF-8'));
        $dom=new DOMDocument('1.0','UTF-8');$before=libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="sec-sanitized">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);libxml_clear_errors();libxml_use_internal_errors($before);
        $root=$dom->getElementById('sec-sanitized');if(!$root)return '';
        $clean=function($node)use(&$clean){foreach(iterator_to_array($node->childNodes) as $child){
            if($child instanceof DOMElement){$tag=strtolower($child->tagName);
                if(in_array($tag,['script','style','iframe','object','embed','form','input','button','svg','math'],true)){$node->removeChild($child);continue;}
                $clean($child);
                if(!in_array($tag,['p','br','div','span','strong','em','b','i','ul','ol','li','h2','h3','h4','table','thead','tbody','tr','td','th','hr','a'],true)){
                    while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;
                }
                $href=$child->getAttribute('href');foreach(iterator_to_array($child->attributes) as $attr)$child->removeAttribute($attr->name);
                if($tag==='a' && preg_match('#^https?://#i',$href) && filter_var($href,FILTER_VALIDATE_URL)){$child->setAttribute('href',$href);$child->setAttribute('target','_blank');$child->setAttribute('rel','noopener noreferrer');}
            }elseif($child instanceof DOMComment || $child instanceof DOMProcessingInstruction)$node->removeChild($child);
        }};
        $clean($root);$out='';foreach($root->childNodes as $child)$out.=$dom->saveHTML($child);return $out;
    }
    public static function positiveInt($v,int $max=9999): int {
        if(!is_scalar($v) || !preg_match('/^[1-9][0-9]*$/D',(string)$v) || (int)$v>$max)throw new InvalidArgumentException('数量を正しく入力してください。');return (int)$v;
    }
    public static function checkoutContact(array $post,int $type): array {
        $contact=[];foreach(['from_name','mail','from_tel','from_zipcode','from_address','name','tel','zipcode','address','memo'] as $key){
            $value=$post[$key]??'';if(!is_string($value))throw new InvalidArgumentException('入力を確認してください。');
            $value=trim($value);if(strlen($value)>($key==='memo'?1000:255))throw new InvalidArgumentException('入力が長すぎます。');$contact[$key]=$value;
        }
        $flag=$post['same_as_applicant']??'0';if(!in_array($flag,['0','1'],true))throw new InvalidArgumentException('送付先の選択を確認してください。');$same=$flag==='1';
        if(!$contact['from_name'] || !filter_var($contact['mail'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('申込者の氏名とメールアドレスを入力してください。');
        if($same)foreach(['name','tel','zipcode','address'] as $field)$contact[$field]=$contact['from_'.$field];
        if($type===0){
            foreach(['from_tel','from_zipcode','from_address'] as $field)if(!$contact[$field])throw new InvalidArgumentException('申込者の電話番号・郵便番号・住所を入力してください。');
            foreach(['name','tel','zipcode','address'] as $field)if(!$contact[$field])throw new InvalidArgumentException('送付先の氏名・電話番号・郵便番号・住所を入力してください。');
        }
        return ['contact'=>$contact,'same_as_applicant'=>$same];
    }
    public static function quoteSignature(array $quote): string {
        $lines=array_map(fn($line)=>[(int)$line['product']['id'],(int)$line['variant'],(int)$line['count'],(int)$line['price'],(int)$line['tax']],$quote['lines']);
        return hash('sha256',json_encode([$quote['type'],$quote['subtotal'],$quote['tax'],$quote['shipping'],$quote['total'],$lines],JSON_THROW_ON_ERROR));
    }
    public static function productAllowed(array $shop,array $product): bool {
        $type=(int)($product['type']??-1);
        if(!isset(self::$types[$type]))return false;
        // 0 is bank transfer/manual payment; it is a valid shop payment method.
        return in_array($type,[2,3],true) || (in_array((string)($shop['paytype']??''),['0','2'],true) && in_array($type,[0,1,4],true));
    }
    public static function refreshProductAvailability(array $db): void {
        $shops=[];foreach($db['shops']->getall() as $shop)$shops[$shop['login_id']]=$shop;
        foreach($db['products']->getall() as $product){$flag=self::productAllowed($shops[$product['login_id']]??[],$product)?1:0;
            if((int)($product['payment_available']??0)!==$flag){$product['payment_available']=$flag;$db['products']->update($product);}
        }
    }
    public static function quote(array $shop,array $products,array $cart): array {
        if(!$cart || count($cart)>100)throw new InvalidArgumentException('カートが空です。');
        $rows=[];$subtotal=0;$tax=0;$physical=false;$type=null;
        foreach($cart as $key=>$entry){
            $p=$products[(int)$entry['id']]??null;$variant=self::positiveInt($entry['variant'],5);$count=self::positiveInt($entry['count'],20);
            if(!$p || !self::productAllowed($shop,$p) || $p['login_id']!==$shop['login_id'] || (int)$p['hyoji']!==0)throw new RuntimeException('商品を購入できません。');
            if($type!==null && $type!==(int)$p['type'])throw new RuntimeException('異なる受付種別は分けてお申し込みください。');
            $type=(int)$p['type'];
            if((int)($p['purchase_limit']??0)>0 && $count>(int)$p['purchase_limit'])throw new RuntimeException('購入上限を超えています。');
            if($type===4 && ($count!==1 || count($cart)!==1))throw new RuntimeException('定期購入は1件ずつお申し込みください。');
            $code=(string)($p['code'.$variant]??'');
            if(in_array($type,[0,1],true) && $code==='')throw new RuntimeException('商品種別を選択してください。');
            if(in_array($type,[0,1],true) && (int)$p['zaiko_check']===1 && $count>(int)$p['zaiko'.$variant])throw new RuntimeException('在庫・参加枠が不足しています。');
            $price=$type===4?(int)$p['teiki_getugaku']:(in_array($type,[2,3],true)?0:(int)$p['price'.$variant]);
            if($type===4 && $price<=0)throw new RuntimeException('月額料金を確認してください。');
            if($price<0)throw new RuntimeException('価格を確認してください。');
            $line=$price*$count;$lineTax=(int)floor($line*(int)$p['zeiritu']/100);
            $subtotal+=$line;$tax+=$lineTax;$physical=$physical||$type===0;
            $rows[]=['product'=>$p,'variant'=>$variant,'count'=>$count,'price'=>$price,'code'=>$code,'line'=>$line,'tax'=>$lineTax];
        }
        $shipping=$physical?max(0,(int)$shop['soryo']):0;
        return ['lines'=>$rows,'subtotal'=>$subtotal,'tax'=>$tax,'shipping'=>$shipping,'total'=>$subtotal+$tax+$shipping,'type'=>$type];
    }
    public static function due(array $contracts,string $month): array {
        if(!preg_match('/^\d{4}-\d{2}$/D',$month))throw new InvalidArgumentException('Invalid period');
        return array_values(array_filter($contracts,static function($r)use($month){
            if((int)$r['status']!==1 || (int)$r['getugaku']<=0)return false;
            $first=$r['date_shoki_kessai']??0;$first=is_numeric($first)?(int)$first:strtotime($first);if($first && date('Y-m',$first)>$month)return false;
            $end=$r['end_date']??0;$end=is_numeric($end)?(int)$end:strtotime($end);if($end && $end<strtotime($month.'-01'))return false;
            $last=$r['date_last_kessai']??'';$ts=is_numeric($last)?(int)$last:strtotime($last);
            return !$ts || date('Y-m',$ts)<$month;
        }));
    }
    public static function paymentKey(string $contract,string $month): string {return substr(hash('sha256','simple_ec:'.$contract.':'.$month),0,40);}
    public static function paymentLabel(string $state): string {
        return ['succeeded'=>'決済成功','unknown'=>'結果確認中','sandbox_succeeded'=>'Sandbox成功','sandbox_unknown'=>'Sandbox結果確認中','sandbox_failed'=>'Sandbox失敗','sandbox_preparing'=>'Sandbox準備中'][$state]??$state;
    }
    public static function queue(array $db,string $key,string $owner,string $to,string $subject,string $body,string $state='テスト送信待ち'): void {
        if($db['outbox']->select('event_key',$key))return;
        $r=['login_id'=>$owner,'event_key'=>$key,'recipient'=>$to,'subject'=>$subject,'body'=>$body,'state'=>$state];$db['outbox']->insert($r);
    }
}
