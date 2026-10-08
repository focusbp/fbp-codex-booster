<?php
require_once __DIR__.'/../../lib/DspPolicyTemplate.php';
class dsp {
    private FFM $definitions;
    private FFM $notes;
    public function __construct(Controller $ctl) {
        $this->notes=$ctl->db('db','db'); $this->definitions=$ctl->db('policies','dsp');
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
        $post=$ctl->POST();$row=[];$errors=[];
        foreach(['note_id','operation','mode','conditions','id'] as $key) {
            if(isset($post[$key])&&!is_scalar($post[$key]))$errors[$key]=$ctl->t('dsp.invalid');
            $row[$key]=is_scalar($post[$key]??null)?trim((string)$post[$key]):'';
        }
        if($row['id']!==''&&!ctype_digit($row['id']))$errors['id']=$ctl->t('dsp.invalid');
        if(!ctype_digit($row['note_id']))$errors['note_id']=$ctl->t('dsp.note_required');
        $row['id']=(int)$row['id'];$row['note_id']=(int)$row['note_id'];
        if(!$this->notes->get($row['note_id']))$errors['note_id']=$ctl->t('dsp.note_required');
        if(!in_array($row['operation'],['add','read','update','delete'],true))$errors['operation']=$ctl->t('dsp.invalid');
        if(!in_array($row['mode'],['allow','deny','custom'],true))$errors['mode']=$ctl->t('dsp.invalid');
        if($row['mode']==='custom'&&$row['conditions']==='')$errors['conditions']=$ctl->t('dsp.conditions_required');
        if(strlen($row['conditions'])>6000)$errors['conditions']=$ctl->t('dsp.conditions_long');
        if($row['mode']!=='custom')$row['conditions']='';
        foreach($this->definitions->select(['note_id','operation'],[$row['note_id'],$row['operation']],true)as $existing)
            if($existing['id']!==$row['id'])$errors['operation']=$ctl->t('dsp.duplicate');
        $before=$row['id']>0?$this->definitions->get($row['id']):null;
        if($row['id']>0&&!$before)$errors['id']=$ctl->t('dsp.invalid');
        if($errors){$ctl->clear_error_message();foreach($errors as $key=>$message)$ctl->res_error_message($key,$message);return;}
        if($before)$this->definitions->update($row);else $this->definitions->insert($row);
        $ctl->close_multi_dialog('dsp_form');$this->page($ctl);
    }
    public function delete(Controller $ctl): void {
        $row=$this->definitions->get((int)$ctl->POST('id'));if(!$row)throw new RuntimeException('DSP definition not found');
        $ctl->assign('data',$row);$ctl->show_multi_dialog('dsp_delete','delete.tpl',$ctl->t('common.delete'),600,true,true);
    }
    public function delete_exe(Controller $ctl): void {
        $id=(int)$ctl->POST('id');$before=$this->definitions->get($id);if(!$before)throw new RuntimeException('DSP definition not found');
        $this->definitions->delete($id);
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
