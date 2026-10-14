<?php
require_once __DIR__.'/../../lib/DspPolicyTemplate.php';
require_once __DIR__.'/../../lib/DspDefinitionStore.php';
class dsp {
    private ?FFM $definitions=null;
    private FFM $notes;
    private DspDefinitionStore $store;
    public function __construct(Controller $ctl) {
        if($ctl->POST('function')==='cli_command'&&!$this->isCliTest($ctl)) {
            $ctl->res_json(['ok'=>false,'error'=>'test_only']);return;
        }
        $this->notes=$ctl->db('db','db');
        if($ctl->POST('function')==='cli_command') {
            $command=$ctl->POST('command');
            $path=dirname(dirname($this->notes->get_path_dat())).'/dsp/policies.dat';
            if(!in_array($command,['notes','targets'],true)&&(!in_array($command,['list','get','generate_template'],true)||is_file($path)))
                $this->definitions=$ctl->db('policies','dsp');
            $this->store=new DspDefinitionStore($this->notes,$this->definitions,dirname(dirname(dirname($this->notes->get_path_dat()))).'/app',dirname(__DIR__));return;
        }
        $this->definitions=$ctl->db('policies','dsp');
        $this->store=new DspDefinitionStore($this->notes,$this->definitions,dirname(dirname(dirname($this->notes->get_path_dat()))).'/app',dirname(__DIR__));
        $ctl->assign('dsp_operations',['add'=>'Add','read'=>'Read','update'=>'Update','delete'=>'Delete']);
        $ctl->assign('dsp_modes',['allow'=>'Allow','deny'=>'Deny','custom'=>'Custom']);
        $opts=[''=>$ctl->t('dsp.select_note')];
        foreach($this->store->targets() as $row) $opts[$row['target']]=$row['note_id']>0?$row['note_name'].' (common/'.$row['db_table'].')':$ctl->t('dsp.custom_db').' ('.$row['db_class'].'/'.$row['db_table'].')';
        $ctl->assign('dsp_notes',$opts);
        $opts['']=$ctl->t('dsp.all_notes');$ctl->assign('dsp_note_filters',$opts);
        $ctl->assign('dsp_operation_filters',[''=>$ctl->t('dsp.all_operations'),'add'=>'Add','read'=>'Read','update'=>'Update','delete'=>'Delete']);
        $ctl->assign('dsp_mode_filters',[''=>$ctl->t('dsp.all_modes'),'allow'=>'Allow','deny'=>'Deny','custom'=>'Custom']);
    }
    public function page(Controller $ctl): void {
        $filter=$ctl->POST();$items=$this->store->listing($filter);
        foreach($items as &$row)if(!$row['target_exists'])$row['note_name']=$ctl->t('dsp.note_deleted');
        unset($row);
        $ctl->assign('items',$items);$ctl->assign('filter',$filter);$ctl->reload_area('#tabs-dsp','index.tpl');
    }
    public function add(Controller $ctl): void { $this->form($ctl,[]); }
    public function edit(Controller $ctl): void {
        $row=$this->store->get($this->positiveId($ctl->POST('id')));
        $this->form($ctl,$row);
    }
    private function form(Controller $ctl,array $row): void {
        $ctl->assign('data',$row+['target'=>'','operation'=>'read','mode'=>'allow','conditions'=>'','id'=>0]);
        $ctl->show_multi_dialog('dsp_form','form.tpl','DSP',800,true,true);
    }
    public function save(Controller $ctl): void {
        $post=$ctl->POST();
        try {
            $id=$this->positiveId($post['id']??0,true);
            $this->store->save($post,$id>0?$id:null);
        } catch(DspDefinitionValidationException $e) {
            $ctl->clear_error_message();foreach($e->errors as $key=>$code)$ctl->res_error_message($key,$ctl->t($code));return;
        }
        $ctl->close_multi_dialog('dsp_form');$this->page($ctl);
    }
    private function positiveId(mixed $value,bool $allowZero=false): int {
        if(!is_scalar($value)||!ctype_digit((string)$value)||(!$allowZero&&(int)$value<=0))
            throw new DspDefinitionValidationException(['id'=>'dsp.invalid']);
        return (int)$value;
    }
    private function isCliTest(Controller $ctl): bool { return PHP_SAPI==='cli'&&$ctl->get_session('testserver')===true; }
    public function cli_command(Controller $ctl): void {
        if(!$this->isCliTest($ctl)) { $ctl->res_json(['ok'=>false,'error'=>'test_only']);return; }
        $post=$ctl->POST();
        try {
            $command=$post['command']??'';
            $result=match($command){
                'notes'=>['items'=>$this->store->notes()],
                'targets'=>['items'=>$this->store->targets()],
                'generate_template'=>['template'=>$this->store->template($post)],
                'list'=>['items'=>$this->store->listing($post)],
                'get'=>['item'=>$this->store->get($this->positiveId($post['id']??''))],
                'add'=>['item'=>$this->store->save($post)],
                'edit'=>['item'=>$this->store->save($post,$this->positiveId($post['id']??''))],
                'delete'=>['item'=>$this->store->delete($this->positiveId($post['id']??''))],
                default=>throw new DspDefinitionValidationException(['command'=>'dsp.invalid']),
            };
            $ctl->res_json(['ok'=>true,'command'=>$command]+$result);
        } catch(DspDefinitionValidationException $e) {
            $errors=[];foreach($e->errors as $key=>$code)$errors[$key]=$ctl->t($code);
            $ctl->res_json(['ok'=>false,'error'=>'validation','errors'=>$errors]);
        }
    }
    public function delete(Controller $ctl): void {
        $row=$this->definitions->get((int)$ctl->POST('id'));if(!$row)throw new RuntimeException('DSP definition not found');
        $ctl->assign('data',$row);$ctl->show_multi_dialog('dsp_delete','delete.tpl',$ctl->t('common.delete'),600,true,true);
    }
    public function delete_exe(Controller $ctl): void {
        $this->store->delete($this->positiveId($ctl->POST('id')));
        $ctl->close_multi_dialog('dsp_delete');$this->page($ctl);
    }
    public function generate_template(Controller $ctl): void {
        $ctl->res_json($this->store->template($ctl->POST()));
    }
}
