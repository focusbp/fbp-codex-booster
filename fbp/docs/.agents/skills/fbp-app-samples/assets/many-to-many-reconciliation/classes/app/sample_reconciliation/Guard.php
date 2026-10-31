<?php
require_once __DIR__.'/Store.php';
class SampleReconciliationGuard {
    public function can_access(Controller $ctl,string $function,string $table):bool {
        if(!$ctl->get_session('login')||!$ctl->is_app_admin())return false;
        $internal=in_array($table,[SampleReconciliationStore::LINKS,SampleReconciliationStore::EVENTS],true);
        if($internal)return in_array($function,['page','rows','reload','search','search_exe'],true);
        if($function==='duplicate')return false;
        $store=new SampleReconciliationStore($ctl);
        if(in_array($function,['add_exe','edit_exe'],true)){
            $post=$ctl->POST();
            foreach(['group_recognition','group_receipt','group_difference','relation_state','balance_state','link_count','source_key','source_facts','source_file','source_row','import_account_key','external_transaction_id','withdrawal_amount','balance_amount'] as $field)if(array_key_exists($field,$post))return false;
            if(isset($post['amount'])){try{SampleReconciliationGraph::money($post['amount']);}catch(Throwable $e){$ctl->show_notification_text($e->getMessage());return false;}}
            if(isset($post['currency'])&&$post['currency']!=='JPY')return false;
        }
        if(in_array($function,['edit_exe','delete','delete_exe'],true)){
            $id=(int)$ctl->decrypt_post('id');$side=$table===SampleReconciliationStore::LEFT?'left':'right';$s=$store->snapshot();
            foreach($s['links'] as $link)if((int)$link[$side==='left'?'recognition_id':'receipt_id']===$id){
                if($function!=='edit_exe'){$ctl->show_notification_text('履歴のある明細は削除できません。解除後に対象外へ変更してください。');return false;}
                if((int)$link['active']===1){$old=$s[$side][$id]??[];foreach(['scope_key','currency'] as $f)if($ctl->POST($f)!==null&&$ctl->POST($f)!==($old[$f]??null))return false;
                    if($ctl->POST('eligibility')!==null&&(int)$ctl->POST('eligibility')!==0){$ctl->show_notification_text('先に紐付けを解除してください。');return false;}}
            }
        }
        // Standard list requests are read-only. Services and post-action update derived columns.
        return true;
    }
}
