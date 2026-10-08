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
            if($command!=='notes'&&(!in_array($command,['list','get'],true)||is_file($path)))
                $this->definitions=$ctl->db('policies','dsp');
            $this->store=new DspDefinitionStore($this->notes,$this->definitions);return;
        }
        $this->definitions=$ctl->db('policies','dsp');
        $this->store=new DspDefinitionStore($this->notes,$this->definitions);
        $ctl->assign('dsp_operations',['add'=>'Add','read'=>'Read','update'=>'Update','delete'=>'Delete']);
        $ctl->assign('dsp_modes',['allow'=>'Allow','deny'=>'Deny','custom'=>'Custom']);
        $opts=[''=>$ctl->t('dsp.select_note')];
        foreach($this->notes->getall('tb_name') as $row) $opts[$row['id']]=($row['menu_name']?:$row['tb_name']).' ('.$row['tb_name'].')';
        $ctl->assign('dsp_notes',$opts);
        $opts['']=$ctl->t('dsp.all_notes');$ctl->assign('dsp_note_filters',$opts);
        $ctl->assign('dsp_operation_filters',[''=>$ctl->t('dsp.all_operations'),'add'=>'Add','read'=>'Read','update'=>'Update','delete'=>'Delete']);
        $ctl->assign('dsp_mode_filters',[''=>$ctl->t('dsp.all_modes'),'allow'=>'Allow','deny'=>'Deny','custom'=>'Custom']);
    }
    public function page(Controller $ctl): void {
        $filter=$ctl->POST(); $names=array_column($this->notes->getall(),'menu_name','id');
        $tables=array_column($this->notes->getall(),'tb_name','id'); $items=[];
        foreach($this->definitions->getall('id',SORT_DESC) as $row) {
            $row['note_name']=($names[$row['note_id']]??'')?:($tables[$row['note_id']]??$ctl->t('dsp.note_deleted'));
            $row['table_name']=$tables[$row['note_id']]??'';
            if (!empty($filter['note_id']) && (int)$filter['note_id']!==$row['note_id']) continue;
            if (!empty($filter['operation']) && $filter['operation']!==$row['operation']) continue;
            if (!empty($filter['mode']) && $filter['mode']!==$row['mode']) continue;
            $keyword=is_string($filter['keyword']??null)?trim($filter['keyword']):'';
            if($keyword!=='' && mb_stripos($row['note_name'].' '.$row['table_name'].' '.$row['conditions'],$keyword)===false)continue;
            $items[]=$row;
        }
        $ctl->assign('items',$items);$ctl->assign('filter',$filter);$ctl->reload_area('#tabs-dsp','index.tpl');
    }
    public function add(Controller $ctl): void { $this->form($ctl,[]); }
    public function edit(Controller $ctl): void {
        $row=$this->definitions->get((int)$ctl->POST('id'));if(!$row)throw new RuntimeException('DSP definition not found');
        $this->form($ctl,$row);
    }
    private function form(Controller $ctl,array $row): void {
        $ctl->assign('data',$row+['note_id'=>'','operation'=>'read','mode'=>'allow','conditions'=>'','id'=>0]);
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
        $id=(int)$ctl->POST('note_id');$note=$this->notes->get($id);if(!$note)throw new RuntimeException('Note not found');
        $rules=array_column($this->definitions->select('note_id',$id,true),null,'operation');
        $name='Note'.$id.'Dsp';$result=DspPolicyTemplate::generate($name,$rules);
        $result['note']=['id'=>$note['id'],'tb_name'=>$note['tb_name']];$result['rules']=$rules;
        $result['registry']=['common/'.$note['tb_name']=>['file'=>$name.'.php','class'=>$name]];
        $ctl->res_json($result);
    }
}
