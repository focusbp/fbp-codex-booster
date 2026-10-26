<?php
require_once __DIR__.'/Store.php';
class SimpleEcVisibility {
    public function can_show_menu(Controller $ctl,string $table,array $setting): bool {
        if(in_array($table,['sec_secrets'],true) || !SimpleEcStore::administrator($ctl))return false;
        if($table==='sec_contracts' && !SimpleEcTypes::allows($ctl,4))return false;
        return true;
    }
    public function can_access(Controller $ctl,string $fn,string $table,array $setting): bool {
        SimpleEcStore::configureTypes(SimpleEcTypes::labels($ctl));
        $who=(string)$ctl->get_login_id();if(!SimpleEcStore::administrator($ctl))return false;if($fn==='menu')return $this->can_show_menu($ctl,$table,$setting);if($who==='')return false;
        $admin=SimpleEcStore::administrator($ctl);
        if(in_array($table,['sec_secrets'],true))return false;
        if($table==='sec_contracts' && !SimpleEcTypes::allows($ctl,4))return false;
        if($table==='sec_runtime')return in_array($fn,['menu','page','save_single_exe'],true);
        if(in_array($table,['sec_payments','sec_outbox'],true) && !$admin)return false;
        if(in_array($table,['sec_shops','sec_products'],true) && in_array($fn,['add','add_exe','edit','edit_exe','duplicate','add_child','add_child_exe','edit_child','edit_child_exe'],true))return false;
        $readonly=['menu','page','rows','search','reload','edit','rows_child','search_child'];
        if(in_array($table,['sec_items','sec_payments','sec_outbox','sec_contracts'],true) && !in_array($fn,$readonly,true))return false;
        $accounts=$ctl->db('sec_shops','common');$categories=$ctl->db('sec_categories','common');$ffm=$ctl->db($table,'common');
        if(in_array($table,['sec_products','sec_categories'],true)&&$ctl->POST('parent_id')){
            $parent=$accounts->get((int)$ctl->POST('parent_id'));if(!$parent)return false;
        }
        $raw=$ctl->POST('id')?:$ctl->GET('id');
        if($raw){$id=$ctl->decrypt($raw);if(!is_numeric($id))$id=$raw;$r=$ffm->get((int)$id);if(!$r || !SimpleEcStore::owns($ctl,$r))return false;
            if($table==='sec_orders'&&!SimpleEcStore::orderVisible($r))return false;
            if(isset($parent)&&(int)($r['parent_id']??0)!==(int)$parent['id'])return false;
            if($table==='sec_products'){ $shop=SimpleEcStore::shop(['shops'=>$accounts],$r['login_id']);if(!SimpleEcStore::productAllowed($shop,$r))return false;}}
        if($ctl->POST('owner_account')) {
            $account=$accounts->get((int)$ctl->POST('owner_account'));
            if(!$account || !SimpleEcStore::owns($ctl,$account))return false;
        }
        if($fn==='manual_sort'){
            $log=$ctl->POST('log');if(!is_string($log))return false;
            foreach(explode(',',$log) as $sortId){if(!ctype_digit($sortId))return false;$item=$ffm->get((int)$sortId);if(!$item || !SimpleEcStore::owns($ctl,$item))return false;}
        }
        if($table==='sec_products' && $ctl->POST('category_id')){
            $category=$categories->get((int)$ctl->POST('category_id'));
            $desiredOwner=$ctl->POST('owner_account')?($account['login_id']??''):($r['login_id']??'');
            if(!$category || $category['login_id']!==$desiredOwner)return false;
        }
        $owner=$ctl->POST('login_id');
        if($owner && !$admin && $owner!==$who)return false;
        foreach(['record_key','metadata_json','card_token'] as $key)if($ctl->POST($key)!==null)return false;
        if($table==='sec_orders' && !in_array($fn,$readonly,true)) {
            if($fn!=='edit_exe')return false;
            if($ctl->POST('status')!==null&&!in_array((string)$ctl->POST('status'),['0','1','2','3','4'],true))return false;
            $allowed=['status','name','furigana','zipcode','address','tel','mail','from_name','from_furigana','from_zipcode','from_address','from_tel','memo'];
            foreach(['login_id','owner_account','order_code','order_date','order_time','shokei','soryo','zei','gokei','paytype','type'] as $k)if($ctl->POST($k)!==null && (string)$ctl->POST($k)!==(string)($r[$k]??''))return false;
        }
        return true;
    }
    public function get_filter_conditions(Controller $ctl,string $context): array {
        return SimpleEcStore::administrator($ctl)?[]:[['field'=>'id','value'=>0,'match_pattern'=>'=']];
    }
}
