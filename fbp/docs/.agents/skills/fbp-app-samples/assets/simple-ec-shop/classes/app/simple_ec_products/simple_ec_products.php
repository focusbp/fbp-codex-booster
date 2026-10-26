<?php
require_once __DIR__.'/../simple_ec/Store.php';
class simple_ec_products {
    public static function groups(): array {
        $variants=[];for($i=1;$i<=5;$i++)foreach(['code','price','zaiko'] as $prefix)$variants[]=$prefix.$i;
        return ['common'=>['owner_account','type','name','category_id','detail','photo','hyoji'],'paid'=>['zeiritu'],'variants'=>array_merge(['unit','purchase_limit','zaiko_check'],$variants),'event'=>['date','time'],'recurring'=>['teiki_shohin','teiki_getugaku','teiki_shokai']];
    }
    public static function activeFields(int $type): array {
        $g=self::groups();$fields=$g['common'];
        if(in_array($type,[0,1,4],true))$fields=array_merge($fields,$g['paid']);
        if(in_array($type,[0,1],true))$fields=array_merge($fields,$g['variants']);
        if($type===1)$fields=array_merge($fields,$g['event']);
        if($type===4)$fields=array_merge($fields,$g['recurring']);
        return $fields;
    }
    private function authorize(Controller $ctl): void {if(!SimpleEcStore::administrator($ctl))throw new RuntimeException('管理者のみ操作できます。');}
    function add(Controller $ctl){$this->form($ctl,0);}
    function edit(Controller $ctl){$this->form($ctl,(int)$ctl->decrypt($ctl->POST('id')));}
    private function form(Controller $ctl,int $id): void {
        $this->authorize($ctl);$db=SimpleEcStore::open($ctl);
        $row=$id?$db['products']->get($id):['type'=>array_key_first(SimpleEcTypes::labels($ctl)),'hyoji'=>0,'zeiritu'=>10,'zaiko_check'=>0,'teiki_shokai'=>0];
        if(!$row)throw new RuntimeException('商品が見つかりません。');
        if($id){$shop=SimpleEcStore::shop($db,$row['login_id']);if(!SimpleEcStore::productAllowed($shop,$row))throw new RuntimeException('決済連携なしのショップでは、この商品は表示できません。');}
        $shopPayments=[];foreach($db['shops']->getall() as $shop)$shopPayments[(int)$shop['id']]=SimpleEcStore::productAllowed($shop,['type'=>0]);
        $ctl->assign('shop_payments_json',json_encode($shopPayments,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));
        $parent=(int)($id?($row['parent_id']??0):$ctl->POST('parent_id'));$shop=$db['shops']->get($parent);
        if(!$shop)throw new RuntimeException('ショップから商品を開いてください。');
        $row['parent_id']=$parent;$row['owner_account']=$parent;
        $ctl->assign('parent_shop',$shop);
        $ctl->assign('category_ids_json',json_encode(array_map(fn($c)=>(string)$c['id'],$db['categories']->select('login_id',$shop['login_id']))));
        $ctl->assign('selected_category',(int)($row['category_id']??0));
        $ctl->assign('row',$row);$ctl->assign('product_token',$id?$ctl->encrypt($id):'');$groups=self::groups();$groups['common']=array_values(array_diff($groups['common'],['owner_account']));$ctl->assign('groups',$groups);
        $ctl->show_multi_dialog('sec_product_edit','edit.tpl',$id?'商品編集':'商品追加',1000);
    }
    function save(Controller $ctl){
        $this->authorize($ctl);$post=$ctl->POST();$type=$post['type']??'';
        if(!is_scalar($type)||!preg_match('/^[0-4]$/D',(string)$type)){$ctl->res_error_message('type','受付種別を選択してください。');return;}
        if(!SimpleEcTypes::allows($ctl,(int)$type)){$ctl->res_error_message('type','この受付種別は利用していません。');return;}
        $names=self::activeFields((int)$type);$fields=$ctl->get_field_list('sec_products',$names);
        $ctl->validate('sec_products',$names,$post,false);if($ctl->count_res_error_message())return;
        $ctl->db('file','upload');$db=SimpleEcStore::open($ctl);
        $id=!empty($post['id'])?(int)$ctl->decrypt($post['id']):0;$before=$id?$db['products']->get($id):[];
        if($id&&!$before)throw new RuntimeException('商品が見つかりません。');
        $parent=(int)($post['parent_id']??0);$shop=$db['shops']->get($parent);
        if($id&&(int)$before['parent_id']!==$parent)throw new RuntimeException('商品のショップは変更できません。');
        if(!$shop){$ctl->res_error_message('owner_account','ショップを選択してください。');return;}
        if(!SimpleEcStore::productAllowed($shop,['type'=>$type])){$ctl->res_error_message('type','決済連携なしのショップでは、問い合わせ・見積依頼のみ設定できます。');return;}
        $category=(int)($post['category_id']??0);
        if($category){$r=$db['categories']->get($category);if(!$r||$r['login_id']!==$shop['login_id']){$ctl->res_error_message('category_id','選択したショップのカテゴリーを選んでください。');return;}}
        if(!is_string($post['name']??null)||trim($post['name'])===''){$ctl->res_error_message('name','商品名を入力してください。');return;}
        $row=$before;
        foreach($fields as $field){$name=$field['parameter_name'];if(!in_array($name,$names,true)||in_array($field['type'],['image','file'],true))continue;
            $value=$name==='owner_account'?$parent:($post[$name]??'');
            if(!is_scalar($value)||strlen((string)$value)>(int)$field['length']){$ctl->res_error_message($name,'入力の形式または長さを確認してください。');return;}
            if($field['type']==='number'&&$value!==''&&!preg_match('/^\d+$/D',(string)$value)){$ctl->res_error_message($name,'0以上の整数を入力してください。');return;}
            $row[$name]=$value;
        }
        if((int)$type===4&&(int)($row['teiki_getugaku']??0)<=0){$ctl->res_error_message('teiki_getugaku','月額料金を1円以上で入力してください。');return;}
        $row['parent_id']=$parent;$row['owner_account']=$parent;$row['login_id']=$shop['login_id'];$row['payment_available']=1;
        if(!$id){$row['sort']=count($db['products']->getall())+1;}
        if($id)$db['products']->update($row);else $db['products']->insert($row);
        $ctl->save_files('sec_products',$row);$db['products']->update($row);
        $ctl->invoke('run',['id'=>$ctl->encrypt($row['id']),'_post_action_table'=>'sec_products','_post_action_from'=>$id?'edit':'add'],'simple_ec_hooks');
        $ctl->close_multi_dialog('sec_product_edit');$ctl->reload_side_panel();
    }
}
