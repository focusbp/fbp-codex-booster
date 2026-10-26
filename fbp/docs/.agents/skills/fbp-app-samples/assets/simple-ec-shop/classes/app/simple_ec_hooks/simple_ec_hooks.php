<?php
require_once __DIR__.'/../simple_ec/Store.php';
class simple_ec_hooks {
    function run(Controller $ctl){
        $table=(string)$ctl->POST('_post_action_table');
        if(!in_array($table,['sec_products','sec_categories'],true))throw new RuntimeException('Invalid hook target');
        $accounts=$ctl->db('sec_shops','common');$db=$ctl->db($table,'common');
        $id=(int)$ctl->decrypt($ctl->POST('id'));$r=$db->get($id);
        if(!$r)return;
        $a=$accounts->get((int)($r['parent_id']??$r['owner_account']));
        if(!$a || !SimpleEcStore::owns($ctl,$a))throw new RuntimeException('ショップの権限がありません。');
        $r['parent_id']=$a['id'];$r['owner_account']=$a['id'];$r['login_id']=$a['login_id'];
        $db->update($r);
    }
}
