<?php
final class DspDefinitionValidationException extends RuntimeException {
    public function __construct(public readonly array $errors) { parent::__construct('Invalid DSP definition'); }
}
/** Shared developer-panel/CLI validation. Both handles must be opened before use. */
final class DspDefinitionStore {
    public function __construct(private FFM $notes, private ?FFM $definitions = null) {}
    public function notes(): array {
        return array_map(static fn($r)=>['id'=>$r['id'],'tb_name'=>$r['tb_name'],'note_name'=>($r['menu_name']??'')?:$r['tb_name']],$this->notes->getall('tb_name'));
    }
    public function resolveNote(mixed $selector): int {
        if (!is_scalar($selector)) throw new DspDefinitionValidationException(['note_id'=>'dsp.note_required']);
        $value=trim((string)$selector);
        if(ctype_digit($value) && (int)$value>0 && $this->notes->get((int)$value))return (int)$value;
        $rows=array_values(array_filter($this->notes->getall(),static fn($r)=>$r['tb_name']===$value));
        if(count($rows)===1)return $rows[0]['id'];
        throw new DspDefinitionValidationException(['note_id'=>'dsp.note_required']);
    }
    private function decorate(array $row): array {
        $note=$this->notes->get($row['note_id']);
        return $row+['tb_name'=>$note['tb_name']??'','note_name'=>($note['menu_name']??'')?:($note['tb_name']??''),'note_exists'=>(bool)$note];
    }
    public function get(int $id): array {
        $row=$this->definitions?->get($id);
        if(!$row)throw new DspDefinitionValidationException(['id'=>'dsp.not_found']);
        return $this->decorate($row);
    }
    public function listing(array $filter=[]): array {
        if(isset($filter['note']))$filter['note_id']=$this->resolveNote($filter['note']);
        if($this->definitions===null)return [];
        $items=[];
        foreach($this->definitions->getall('id',SORT_DESC) as $row){
            if(!empty($filter['note_id'])&&(int)$filter['note_id']!==$row['note_id'])continue;
            if(!empty($filter['operation'])&&strtolower((string)$filter['operation'])!==$row['operation'])continue;
            if(!empty($filter['mode'])&&strtolower((string)$filter['mode'])!==$row['mode'])continue;
            $row=$this->decorate($row);$keyword=trim((string)($filter['keyword']??''));
            if($keyword!==''&&mb_stripos($row['note_name'].' '.$row['tb_name'].' '.$row['conditions'],$keyword)===false)continue;
            $items[]=$row;
        }
        return $items;
    }
    public function save(array $input, ?int $updateId=null): array {
        if($this->definitions===null)throw new LogicException('DSP definitions are not open for writing');
        $before=$updateId!==null?$this->get($updateId):null;
        $row=$before??['id'=>0,'note_id'=>'','operation'=>'','mode'=>'','conditions'=>''];$errors=[];
        if(isset($input['note']))$input['note_id']=$this->resolveNote($input['note']);
        foreach(['note_id','operation','mode','conditions']as $key)if(array_key_exists($key,$input)){
            if(!is_scalar($input[$key]))$errors[$key]='dsp.invalid';
            else $row[$key]=trim((string)$input[$key]);
        }
        if(!ctype_digit((string)$row['note_id'])||!$this->notes->get((int)$row['note_id']))$errors['note_id']='dsp.note_required';
        $row['note_id']=(int)$row['note_id'];$row['operation']=strtolower($row['operation']);$row['mode']=strtolower($row['mode']);
        if(!in_array($row['operation'],['add','read','update','delete'],true))$errors['operation']='dsp.invalid';
        if(!in_array($row['mode'],['allow','deny','custom'],true))$errors['mode']='dsp.invalid';
        if($row['mode']==='custom'&&$row['conditions']==='')$errors['conditions']='dsp.conditions_required';
        if(strlen($row['conditions'])>6000)$errors['conditions']='dsp.conditions_long';
        if($row['mode']!=='custom')$row['conditions']='';
        foreach($this->definitions->select(['note_id','operation'],[$row['note_id'],$row['operation']],true)as $existing)
            if($existing['id']!==$row['id'])$errors['operation']='dsp.duplicate';
        if($errors)throw new DspDefinitionValidationException($errors);
        $row=array_intersect_key($row,array_flip(['id','note_id','operation','mode','conditions']));
        if($before)$this->definitions->update($row);else $this->definitions->insert($row);
        return $this->get($row['id']);
    }
    public function delete(int $id): array { $before=$this->get($id);$this->definitions->delete($id);return $before; }
}
