<?php
/** The UI and Codex use the same versioned feature contract. No app data or credentials. */
final class NoteFeatureCatalog {
    static function all():array {return json_decode(file_get_contents(__DIR__.'/../config/note_feature_catalog.json'),true,32,JSON_THROW_ON_ERROR)['features'];}
    static function get(string $id):array {foreach(self::all() as $f)if($f['id']===$id)return $f;throw new InvalidArgumentException('機能を選択してください。');}
    static function missing(array $feature,array $setting):array {
        $labels=['square_application_id'=>'Square Application ID','square_access_token'=>'Square Access Token','square_location_id'=>'Square Location ID','chatgpt_api_key'=>'OpenAI APIキー','chatgpt_api_url'=>'OpenAI API URL','chatgpt_api_model'=>'OpenAIモデル','smtp_server'=>'SMTPサーバー','smtp_port'=>'SMTPポート','smtp_from'=>'送信元メールアドレス','receive_server'=>'受信サーバー','receive_port'=>'受信ポート','receive_user'=>'受信ユーザー名','receive_password'=>'受信パスワード'];$missing=[];
        foreach($feature['required_settings'] as $key)if(trim((string)($setting[$key]??''))==='')$missing[]=$labels[$key]??$key;
        if($feature['id']==='mail_receive'&&!in_array($setting['receive_protocol']??'',['pop','imap'],true))$missing[]='受信方式（POP/IMAP）';
        if($feature['id']==='mail_send'&&!filter_var($setting['smtp_from']??'',FILTER_VALIDATE_EMAIL)&&!in_array('送信元メールアドレス',$missing,true))$missing[]='有効な送信元メールアドレス';
        if($feature['id']==='ai_ocr'&&!filter_var($setting['chatgpt_api_url']??'',FILTER_VALIDATE_URL)&&!in_array('OpenAI API URL',$missing,true))$missing[]='有効なOpenAI API URL';
        if($feature['id']==='mail_send'&&trim((string)($setting['smtp_user']??''))!==''&&trim((string)($setting['smtp_password']??''))==='')$missing[]='SMTPパスワード';
        if($feature['id']==='single_shop'&&trim((string)($setting['square_application_id']??''))!==''){
            $sandbox=str_starts_with((string)$setting['square_application_id'],'sandbox-');$test=(int)($setting['force_testmode']??0)===1;
            if($test&&!$sandbox)$missing[]='テスト環境用のSquare Sandbox設定';if(!$test&&$sandbox)$missing[]='本番環境用のSquare設定';
        }
        return $missing;
    }
    static function options(array $feature,array $post):array {
        if($feature['id']!=='single_shop')return [];
        $name=$post['product_name']??'';$price=$post['price_tax_included']??'';
        if(!is_string($name)||trim($name)===''||strlen(trim($name))>255)throw new InvalidArgumentException('商品名を255バイト以内で入力してください。');
        if(!is_scalar($price)||!preg_match('/^[1-9][0-9]{0,6}$/D',(string)$price))throw new InvalidArgumentException('税込価格を1〜9,999,999円の整数で入力してください。');
        return ['product_name'=>trim($name),'price_tax_included'=>(int)$price,'currency'=>'JPY'];
    }
    static function draft(array $feature,string $noteTitle,array $options):string {
        if($feature['id']==='other')return $noteTitle.'に下記の機能を追加してください。'."\n\n";
        $text='「'.$noteTitle.'」に'.$feature['title'].'を追加してください。' . "\n\n".$feature['prompt'];
        if($options)$text.="\n商品名：".$options['product_name']."\n価格（税込）：".$options['price_tax_included'].'円';
        return $text."\n\n実装・テスト後、本番リリースまでお願いします。";
    }
}
