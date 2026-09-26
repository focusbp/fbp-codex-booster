<?php

/** Scoped settings storage. Never contacts providers or returns secret values. */
final class IntegrationSettings {
    public static function definitions(): array {
        return [
            'mail'=>['title'=>'メールサーバー設定','fields'=>[
                'smtp_server'=>['SMTPサーバー',false,255], 'smtp_port'=>['ポート',false,3],
                'smtp_secure'=>['暗号化方式（0:なし / 1:TLS / 2:SSL）',false,1],
                'smtp_user'=>['ユーザー名',false,255], 'smtp_password'=>['パスワード',true,255], 'smtp_from'=>['送信元メール',false,255]]],
            'openai'=>['title'=>'OpenAI設定','fields'=>['chatgpt_api_key'=>['APIキー',true,255], 'chatgpt_api_url'=>['API URL',false,255], 'chatgpt_api_model'=>['モデル',false,255]]],
            'line'=>['title'=>'LINE Bot設定','fields'=>['line_channel_secret'=>['チャネルシークレット',true,50], 'line_accesstoken'=>['アクセストークン',true,255]]],
            'square'=>['title'=>'Square設定','fields'=>['square_application_id'=>['Application ID',false,100], 'square_access_token'=>['アクセストークン',true,100], 'square_location_id'=>['Location ID',false,100], 'square_application_secret'=>['Application Secret（必要な場合）',true,150], 'currency'=>['通貨',false,255]]],
            'google'=>['title'=>'Google設定','fields'=>['api_key_map'=>['APIキー',true,255]]],
            'vimeo'=>['title'=>'Vimeo設定','fields'=>['vimeo_access_token'=>['アクセストークン',true,50], 'vimeo_client_id'=>['Client ID（必要な場合）',false,50], 'vimeo_client_secret'=>['Client Secret（必要な場合）',true,150]]],
            'external_keys'=>['title'=>'外部連携キー設定','fields'=>[]],
        ];
    }
    public static function catalog(array $rows): array {
        $out=[];
        foreach($rows as $r){
            if(!is_array($r)||!is_string($r['key']??null)||!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,254}$/D',$r['key'])||!is_string($r['title']??null)||strlen($r['title'])>255||isset($out[$r['key']]))throw new DomainException('キー一覧を確認できません。');
            $out[$r['key']]=['key'=>$r['key'],'title'=>$r['title']];
        }
        if(count($out)>100)throw new DomainException('キー一覧が上限を超えています。');
        ksort($out);return array_values($out);
    }
    public static function catalogVersion(array $catalog): string {return hash('sha256',json_encode(self::catalog($catalog),JSON_THROW_ON_ERROR));}
    public static function fields(string $service,array $catalog=[]): array {
        $d=self::definitions();if(!isset($d[$service]))throw new DomainException('設定項目が見つかりません。');
        if($service!=='external_keys')return $d[$service]['fields'];
        $out=[];foreach(self::catalog($catalog) as $r)$out[$r['key']]=[$r['title'],true,8192];return $out;
    }
    public static function validateStorage(string $service,array $catalog,array $changes,array $displayFields): void {
        $fields=self::fields($service,$catalog);
        foreach($changes as $key=>$op){
            if(!isset($fields[$key])||!is_array($op)||!in_array($op['op']??'', ['set','delete'],true)||!is_string($op['value']??'')||strlen($op['value']??'')>$fields[$key][2])throw new DomainException('保存できない項目があります。');
            if($service!=='external_keys'&&in_array($key,['smtp_port','smtp_secure'],true)&&($op['op']==='set')&&($op['value']??'')!==''&&!ctype_digit($op['value']))throw new DomainException('数値項目を確認してください。');
            // Connection destinations are a scope boundary, not a credential test.
            if($service==='openai'&&$key==='chatgpt_api_url'&&$op['op']==='set'&&!in_array($op['value']??'',['','https://api.openai.com/v1/chat/completions',(string)($displayFields[$key]['value']??'')],true))throw new DomainException('この接続先の変更は運用者にご依頼ください。');
        }
    }
    private static function stores(Controller $ctl): array {
        // Open in a stable order before reading mutable rows.
        return [$ctl->db('external_keys','external_keys'),$ctl->db('receipts','integration_settings'),$ctl->db('setting','setting')];
    }
    private static function raw($ext,$setting,string $service,array $catalog): array {
        if($service!=='external_keys')return (array)$setting->get(1);
        $raw=[];foreach($ext->getall() as $r){if(array_key_exists($r['key'],$raw))throw new DomainException('キー一覧が重複しています。');$v=base64_decode($r['value'],true);if($v===false)throw new DomainException('保存データを確認できません。');$raw[$r['key']]=$v;}return $raw;
    }
    public static function snapshot(Controller $ctl,string $service,array $catalog=[]): array {
        [$ext,$receipts,$setting]=self::stores($ctl);
        $catalog=self::catalog($catalog);$fields=self::fields($service,$catalog);$raw=self::raw($ext,$setting,$service,$catalog);
        $view=[];$version=[];
        foreach($fields as $key=>[$title,$secret,$limit]){
            $value=(string)($raw[$key]??'');$version[$key]=$value;
            $view[$key]=['title'=>$title,'secret'=>$secret,'limit'=>$limit,'registered'=>$value!==''];
            if(!$secret)$view[$key]['value']=$value;
        }
        // HMAC prevents the public revision from becoming an offline secret guessing oracle.
        $key=(string)(($setting->get(1)['secret']??'') ?: ($setting->get(1)['api_secret']??''));
        if($key==='')throw new DomainException('設定管理の初期化が必要です。');
        return ['service'=>$service,'fields'=>$view,'revision'=>hash_hmac('sha256',json_encode([$service,$version],JSON_THROW_ON_ERROR),$key),'catalog_revision'=>self::catalogVersion($catalog)];
    }
    public static function localCatalog(Controller $ctl): array {return self::catalog($ctl->db('external_keys','external_keys')->getall('key',SORT_ASC));}
    public static function apply(Controller $ctl,array $p): array {
        [$ext,$receipts,$setting]=self::stores($ctl);
        $service=(string)($p['service']??'');$catalog=self::catalog($p['catalog']??[]);$fields=self::fields($service,$catalog);
        $id=(string)($p['request_id']??'');if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new DomainException('保存要求が不正です。');
        $hash=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));
        foreach($receipts->select('request_id',$id,true) as $r){if(!hash_equals($r['payload_hash'],$hash))throw new DomainException('保存要求が変更されています。');return self::snapshot($ctl,$service,$catalog);}
        $current=self::snapshot($ctl,$service,$catalog);
        if(!hash_equals($current['revision'],(string)($p['revision']??'')))throw new DomainException('設定が更新されました。開き直してください。');
        if(($p['environment']??'')==='test'&&!hash_equals(self::catalogVersion(self::localCatalog($ctl)),(string)($p['catalog_revision']??'')))throw new DomainException('キー一覧が更新されました。');
        $changes=$p['changes']??null;if(!is_array($changes))throw new DomainException('保存要求が不正です。');
        self::validateStorage($service,$catalog,$changes,$current['fields']);
        if($service==='external_keys'){
            $rows=array_column($ext->getall(),null,'key');
            foreach($changes as $key=>$op){$row=$rows[$key]??['key'=>$key,'title'=>$fields[$key][0]];$v=$op['value']??'';if($op['op']==='set'&&$v==='')continue;$row['value']=base64_encode($op['op']==='delete'?'':$v);if(isset($row['id']))$ext->update($row);else $ext->insert($row);}
        }else{
            $row=$setting->get(1);if(!$row)throw new DomainException('設定管理の初期化が必要です。');
            foreach($changes as $key=>$op){$v=$op['value']??'';if($op['op']==='set'&&$fields[$key][1]&&$v==='')continue;$row[$key]=$op['op']==='delete'?'':$v;}
            $ctl->save_setting($row);
        }
        $receipt=['request_id'=>$id,'payload_hash'=>$hash];$receipts->insert($receipt);
        return self::snapshot($ctl,$service,$catalog);
    }
}
