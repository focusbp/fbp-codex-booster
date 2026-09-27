<?php
/** Code placement and test DB configuration are intentionally separate. No data insertion. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    $sample=$argv[1]??'';
    if(!in_array($sample,['parent-child-history','status-transition','public-intake'],true))throw new RuntimeException('Choose parent-child-history, status-transition or public-intake.');
    $options=[];
    foreach(array_slice($argv,2) as $arg){
        if(!preg_match('/^--(code-root|configure-test-root)=(.+)$/D',$arg,$m)||$options)throw new RuntimeException('Specify exactly one --code-root=PATH or --configure-test-root=PATH.');
        $options[$m[1]]=$m[2];
    }
    if(count($options)!==1)throw new RuntimeException('An explicit destination is required.');
    $mode=array_key_first($options);$root=realpath($options[$mode]);
    if(!$root||!is_dir($root.'/classes/app'))throw new RuntimeException('Destination must contain classes/app.');
    $assets=dirname(__DIR__).'/assets/'.$sample;
    $manifest=json_decode(file_get_contents($assets.'/'.$sample.'.json'),true,512,JSON_THROW_ON_ERROR);
    foreach($manifest['files'] as $file){
        if(str_contains($file,'..')||!preg_match('~^classes/app/[a-z_]+/[A-Za-z0-9_./-]+$~D',$file)||!is_file($assets.'/'.$file))throw new RuntimeException('Invalid/missing asset: '.$file);
    }
    if($mode==='code-root'){
        // Check every class before copying anything; do not mix an existing implementation with this sample.
        foreach($manifest['files'] as $file){
            $class=explode('/',$file)[2];
            if(file_exists($root.'/classes/app/'.$class)||is_link($root.'/classes/app/'.$class))throw new RuntimeException('Refusing existing class: '.$class);
        }
        foreach($manifest['files'] as $file){
            $dest=$root.'/'.$file;
            if(!is_dir(dirname($dest))&&!mkdir(dirname($dest),0775,true))throw new RuntimeException('Cannot create destination.');
            if(!copy($assets.'/'.$file,$dest))throw new RuntimeException('Cannot copy: '.$file);
        }
        echo json_encode(['ok'=>true,'sample'=>$sample,'mode'=>$mode,'files'=>count($manifest['files']),'data_inserted'=>0,'next'=>'Sync source to the test runtime, then run --configure-test-root.'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
        exit;
    }
    if(!is_file($root.'/fbp/cli.php')||!is_dir($root.'/classes/data'))throw new RuntimeException('Configuration requires the test runtime with fbp/cli.php and classes/data.');
    foreach($manifest['files'] as $file)if(!is_file($root.'/'.$file)||hash_file('sha256',$root.'/'.$file)!==hash_file('sha256',$assets.'/'.$file))throw new RuntimeException('Sync sample code before DB configuration: '.$file);
    $run=static function(string $command,array $data=[])use($root):array {
        $process=proc_open([PHP_BINARY,$root.'/fbp/cli.php',$command,'--json='.json_encode($data,JSON_THROW_ON_ERROR)], [1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        if(!is_resource($process))throw new RuntimeException('Cannot start CLI.');
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
        $result=json_decode($out,true);
        if($exit!==0||!is_array($result)||($result['ok']??true)===false)throw new RuntimeException('CLI failed: '.$command.' '.trim($err).' '.$out);
        return $result;
    };
    // Fresh names only. A partial failure is reported for inspection, never reset or blindly resumed.
    foreach($manifest['options'] as $values)foreach($values as $value)if(!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$value['key']))throw new RuntimeException('Option keys must be non-negative integers.');
    $tables=$run('db_tables_list')['items']??[];$arrays=$run('constant_array_list')['items']??[];
    foreach($manifest['notes'] as $note)foreach($tables as $table)if($table['tb_name']===$note['table']['tb_name'])throw new RuntimeException('Note already exists; inspect before adapting: '.$table['tb_name']);
    foreach($manifest['options'] as $name=>$values)foreach($arrays as $array)if($array['array_name']===$name)throw new RuntimeException('Options already exist: '.$name);
    foreach($manifest['options'] as $name=>$values){
        $id=$run('constant_array_add',['array_name'=>$name])['id'];
        foreach($values as $i=>$value)$run('constant_values_add',$value+['constant_array_id'=>$id,'sort'=>$i+1]);
    }
    $ids=[];
    foreach($manifest['notes'] as $note){
        $table=$note['table'];$name=$table['tb_name'];
        if(isset($note['parent']))$table['parent_tb_id']=$ids[$note['parent']];
        $id=(int)$run('db_tables_add',$table)['id'];$ids[$name]=$id;
        foreach($note['fields'] as $field)$run('db_fields_add',$field+['db_id'=>$id]);
        // CLI ignores unknown metadata keys. Read back fields so misspelled flags cannot pass silently.
        $savedFields=[];
        foreach($run('db_fields_list',['db_id'=>$id])['items'] as $saved)$savedFields[$saved['parameter_name']]=$saved;
        foreach($note['fields'] as $field)foreach($field as $key=>$value){
            $saved=$savedFields[$field['parameter_name']]??[];
            if(!array_key_exists($key,$saved)||(string)$saved[$key] !== (string)$value)throw new RuntimeException('Field definition mismatch: '.$name.'.'.$field['parameter_name'].'.'.$key);
        }
        foreach($note['screens'] as $screen=>$fields)foreach($fields as $i=>$field)$run('screen_fields_add',['tb_name'=>$name,'screen_name'=>$screen,'parameter_name'=>$field,'sort'=>$i+1]);
        foreach($note['buttons']??[] as $button)$run('db_additionals_add',$button+['tb_name'=>$name,'databases'=>[$id],'code_type'=>[0],'reload'=>0,'close_button'=>2,'sort'=>100,'ui_mode'=>0,'show_button'=>0,'dialog_width'=>600,'button_type'=>0]);
    }
    echo json_encode(['ok'=>true,'sample'=>$sample,'mode'=>$mode,'db_ids'=>$ids,'data_inserted'=>0,'next'=>'Verify Standard Screen and workflow; create test fixtures separately if needed.'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\nNo data reset or automatic rollback was performed.\n"); exit(1); }
