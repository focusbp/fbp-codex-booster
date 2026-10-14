<?php
require_once __DIR__.'/DspTargetCatalog.php';
require_once __DIR__.'/DspPolicyTemplate.php';
final class DspDefinitionValidationException extends RuntimeException {
    public function __construct(public readonly array $errors) { parent::__construct('Invalid DSP definition'); }
}
/** Shared developer-panel/CLI validation. Both handles must be opened before use. */
final class DspDefinitionStore {
    private ?array $targetCache=null;
    public function __construct(private FFM $notes, private ?FFM $definitions = null, private ?string $appDir = null, private ?string $frameworkDir = null) {}
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
    public function targets(): array {
        if($this->targetCache!==null)return $this->targetCache;
        $targets=[];
        foreach($this->notes() as $note)$targets[]=['target'=>'note:'.$note['id'],'note_id'=>$note['id'],
            'db_class'=>'common','db_table'=>$note['tb_name'],'note_name'=>$note['note_name']];
        return $this->targetCache=array_merge($targets,DspTargetCatalog::customTargets($this->appDir,$this->frameworkDir));
    }
    public function resolveTarget(array $input): array {
        $selectorCount=(int)array_key_exists('target',$input)
            +(int)(array_key_exists('note',$input)||array_key_exists('note_id',$input))
            +(int)(array_key_exists('db_class',$input)||array_key_exists('db_table',$input));
        if($selectorCount>1)throw new DspDefinitionValidationException(['target'=>'dsp.invalid']);
        if(array_key_exists('target',$input)) {
            if(!is_string($input['target']))throw new DspDefinitionValidationException(['target'=>'dsp.target_required']);
            $key=$input['target'];
        } elseif(array_key_exists('db_class',$input)||array_key_exists('db_table',$input)) {
            if(!is_string($input['db_class']??null)||!is_string($input['db_table']??null))
                throw new DspDefinitionValidationException(['target'=>'dsp.target_required']);
            $class=trim($input['db_class']);$table=trim($input['db_table']);$key='db:'.$class.'/'.$table;
            if($class==='common')foreach($this->targets() as $target)
                if($target['db_class']==='common'&&$target['db_table']===$table){$key=$target['target'];break;}
        } else $key='note:'.$this->resolveNote($input['note']??$input['note_id']??'');
        foreach($this->targets() as $target)if($target['target']===$key)return $target;
        throw new DspDefinitionValidationException(['target'=>'dsp.target_required']);
    }
    private function decorate(array $row): array {
        if((int)($row['note_id']??0)>0) {
            $note=$this->notes->get($row['note_id']);$class='common';$table=$note['tb_name']??'';
            $name=($note['menu_name']??'')?:$table;$target='note:'.$row['note_id'];$exists=(bool)$note;
        } else {
            $class=$row['db_class']??'';$table=$row['db_table']??'';$name=$class.'/'.$table;$target='db:'.$name;
            $exists=false;foreach($this->targets() as $candidate)if($candidate['target']===$target){$exists=true;break;}
        }
        return array_replace($row,['target'=>$target,'db_class'=>$class,'db_table'=>$table,'tb_name'=>$table,
            'table_name'=>$class.'/'.$table,'note_name'=>$name,'note_exists'=>$exists,'target_exists'=>$exists]);
    }
    public function get(int $id): array {
        $row=$this->definitions?->get($id);
        if(!$row)throw new DspDefinitionValidationException(['id'=>'dsp.not_found']);
        return $this->decorate($row);
    }
    public function listing(array $filter=[]): array {
        $target=null;
        if(!empty($filter['target'])||isset($filter['note'])||!empty($filter['note_id'])||isset($filter['db_class'])||isset($filter['db_table']))
            $target=$this->resolveTarget($filter)['target'];
        if($this->definitions===null)return [];
        $items=[];
        foreach($this->definitions->getall('id',SORT_DESC) as $row){
            if(!empty($filter['operation'])&&strtolower((string)$filter['operation'])!==$row['operation'])continue;
            if(!empty($filter['mode'])&&strtolower((string)$filter['mode'])!==$row['mode'])continue;
            $row=$this->decorate($row);
            if($target!==null&&$row['target']!==$target)continue;
            $keyword=is_string($filter['keyword']??null)?trim($filter['keyword']):'';
            if($keyword!==''&&mb_stripos($row['note_name'].' '.$row['table_name'].' '.$row['conditions'],$keyword)===false)continue;
            $items[]=$row;
        }
        return $items;
    }
    public function save(array $input, ?int $updateId=null): array {
        if($this->definitions===null)throw new LogicException('DSP definitions are not open for writing');
        $before=$updateId!==null?$this->get($updateId):null;
        $row=$before??['id'=>0,'operation'=>'','mode'=>'','conditions'=>''];$errors=[];
        $hasTarget=false;foreach(['target','note','note_id','db_class','db_table'] as $key)if(array_key_exists($key,$input))$hasTarget=true;
        $row=array_replace($row,$this->resolveTarget($hasTarget?$input:($before!==null?['target'=>$before['target']]:[])));
        foreach(['operation','mode','conditions']as $key)if(array_key_exists($key,$input)){
            if(!is_scalar($input[$key]))$errors[$key]='dsp.invalid';
            else $row[$key]=trim((string)$input[$key]);
        }
        $row['note_id']=(int)$row['note_id'];$row['operation']=strtolower($row['operation']);$row['mode']=strtolower($row['mode']);
        if(!in_array($row['operation'],['add','read','update','delete'],true))$errors['operation']='dsp.invalid';
        if(!in_array($row['mode'],['allow','deny','custom'],true))$errors['mode']='dsp.invalid';
        if($row['mode']==='custom'&&$row['conditions']==='')$errors['conditions']='dsp.conditions_required';
        if(strlen($row['conditions'])>6000)$errors['conditions']='dsp.conditions_long';
        if($row['mode']!=='custom')$row['conditions']='';
        foreach($this->listing(['operation'=>$row['operation']]) as $existing)
            if($existing['target']===$row['target']&&(int)$existing['id']!==(int)$row['id'])$errors['operation']='dsp.duplicate';
        if($errors)throw new DspDefinitionValidationException($errors);
        if($row['note_id']>0){$row['db_class']='';$row['db_table']='';}
        $row=array_intersect_key($row,array_flip(['id','note_id','db_class','db_table','operation','mode','conditions']));
        if($before)$this->definitions->update($row);else $this->definitions->insert($row);
        return $this->get($row['id']);
    }
    public function template(array $input): array {
        $target=$this->resolveTarget($input);
        $rules=array_column($this->listing(['target'=>$target['target']]),null,'operation');
        $name=$target['note_id']>0?'Note'.$target['note_id'].'Dsp':'Db'.substr(hash('sha256',$target['db_class'].'/'.$target['db_table']),0,24).'Dsp';
        $result=DspPolicyTemplate::generate($name,$rules);
        $result['note']=['id'=>$target['note_id'],'tb_name'=>$target['db_table']];$result['target']=$target;$result['rules']=$rules;
        $result['registry']=[$target['db_class'].'/'.$target['db_table']=>['file'=>$name.'.php','class'=>$name]];
        return $result;
    }
    public function delete(int $id): array { $before=$this->get($id);$this->definitions->delete($id);return $before; }
}
