<?php
require_once __DIR__.'/../simple_ec/Store.php';
class simple_ec_shops {
    private function fields(Controller $ctl): array {
        $all=$ctl->get_field_list('sec_shops');usort($all,fn($a,$b)=>(int)$a['sort']<=>(int)$b['sort']);
        return array_values(array_filter($all,fn($f)=>!in_array($f['parameter_name'],['id','login_id','owner_account','record_key','metadata_json','update_time'],true)));
    }
    function add(Controller $ctl){$this->form($ctl,0);}
    function edit(Controller $ctl){$this->form($ctl,(int)$ctl->decrypt($ctl->POST('id')));}
    private function form(Controller $ctl,int $id): void {
        if(!SimpleEcStore::administrator($ctl))throw new RuntimeException('管理者のみ操作できます。');
        $fields=$this->fields($ctl);$db=SimpleEcStore::open($ctl);
        $row=$id?$db['shops']->get($id):['status'=>2,'soryo'=>0,'paytype'=>0,'color_fg'=>'2D2D2D','color_title_bg'=>'132351','color_button_bg'=>'0468E4','color_button_fg'=>'FFFFFF','color_button_hover'=>'132351','color_shohin_border'=>'CCCCCC','color_h'=>'293761'];
        if(!$row)throw new RuntimeException('ショップが見つかりません。');
        $ctl->assign('row',$row);$ctl->assign('shop_token',$id?$ctl->encrypt($id):'');$ctl->assign('shop_field_names',array_column($fields,'parameter_name'));
        $ctl->show_multi_dialog('sec_shop_edit','edit.tpl',$id?'ショップ編集':'ショップ追加',1000);
    }
    function save(Controller $ctl){
        if(!SimpleEcStore::administrator($ctl))throw new RuntimeException('管理者のみ操作できます。');
        $post=$ctl->POST();$code=$post['shop_code']??null;
        if(!SimpleEcStore::validShopCode($code)){$ctl->res_error_message('shop_code','半角英小文字・数字と「-」を使い、英字から始まる64文字以内のコードにしてください。');return;}
        $fields=$this->fields($ctl);$names=array_column($fields,'parameter_name');$id=!empty($post['id'])?(int)$ctl->decrypt($post['id']):0;
        $ctl->validate('sec_shops',$names,$post,false);if($ctl->count_res_error_message())return;
        $ctl->db('file','upload');$db=SimpleEcStore::open($ctl);$row=$id?$db['shops']->get($id):[];
        if($id&&!$row)throw new RuntimeException('ショップが見つかりません。');
        foreach($db['shops']->select('shop_code',$code) as $duplicate)if((int)$duplicate['id']!==$id){$ctl->res_error_message('shop_code','このショップコードは既に使用されています。');return;}
        foreach($fields as $field){$name=$field['parameter_name'];if(in_array($field['type'],['image','file'],true))continue;$value=$post[$name]??'';
            if(!is_scalar($value) || strlen((string)$value)>(int)$field['length']){$ctl->res_error_message($name,'入力の形式または長さを確認してください。');return;}$row[$name]=$value;
        }
        $row['shop_code']=$code;$row['update_time']=time();
        if($id)$db['shops']->update($row);else{$row['login_id']='sec-shop-'.bin2hex(random_bytes(12));$row['record_key']='';$db['shops']->insert($row);}
        $ctl->save_files('sec_shops',$row);$db['shops']->update($row);
        SimpleEcStore::refreshProductAvailability($db);
        $ctl->close_multi_dialog('sec_shop_edit');$ctl->reload_work_area();
    }
}
