<?php
/** Place source and configure an empty test app separately; never copy business data. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try {
    $options=[];
    foreach(array_slice($argv,1) as $arg){
        if(!preg_match('/^--(code-root|configure-test-root|types)=(.+)$/D',$arg,$m) || isset($options[$m[1]]))throw new RuntimeException('Use --code-root=PATH or --configure-test-root=PATH [--types=physical,event,inquiry,quote,subscription].');
        $options[$m[1]]=$m[2];
    }
    $modes=array_intersect_key($options,array_flip(['code-root','configure-test-root']));
    if(count($modes)!==1)throw new RuntimeException('Specify exactly one explicit destination.');
    $mode=array_key_first($modes);$root=realpath($modes[$mode]);
    if(!$root || !is_dir($root.'/classes/app') || is_link($root.'/classes') || is_link($root.'/classes/app'))throw new RuntimeException('Target must contain classes/app.');
    $assets=dirname(__DIR__).'/assets/simple-ec-shop';
    $manifest=json_decode(file_get_contents($assets.'/simple-ec-shop.json'),true,512,JSON_THROW_ON_ERROR);
    $types=explode(',',$options['types']??'physical');
    if(count($types)!==count(array_unique($types)) || array_diff($types,array_keys($manifest['types'])))throw new RuntimeException('Choose a nonempty subset of physical,event,inquiry,quote,subscription.');
    if($mode==='code-root' && isset($options['types']))throw new RuntimeException('--types belongs to --configure-test-root; code is shared by all variants.');
    foreach($manifest['files'] as $file){
        if(!preg_match('~^classes/app/(?:simple_ec[a-z_]*|sec_[a-z_]+)/[A-Za-z0-9_./-]+$~D',$file) || str_contains($file,'..') || !is_file($assets.'/'.$file) || is_link($assets.'/'.$file))throw new RuntimeException('Invalid sample file.');
    }
    if($mode==='code-root'){
        foreach($manifest['files'] as $file){$class=explode('/',$file)[2];if(file_exists($root.'/classes/app/'.$class)||is_link($root.'/classes/app/'.$class))throw new RuntimeException('Refusing existing sample class: '.$class);}
        foreach($manifest['files'] as $file){$dest=$root.'/'.$file;if(!is_dir(dirname($dest))&&!mkdir(dirname($dest),0775,true))throw new RuntimeException('Cannot create destination.');if(!copy($assets.'/'.$file,$dest))throw new RuntimeException('Cannot copy sample.');}
        echo json_encode(['ok'=>true,'mode'=>$mode,'files'=>count($manifest['files']),'business_rows_inserted'=>0,'next'=>'Sync source to the test runtime, then configure with the desired --types subset.'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";exit;
    }
    if(!is_file($root.'/fbp/cli.php') || !is_dir($root.'/classes/data'))throw new RuntimeException('Configuration requires a test runtime.');
    foreach($manifest['files'] as $file)if(!is_file($root.'/'.$file) || is_link($root.'/'.$file) || hash_file('sha256',$root.'/'.$file)!==hash_file('sha256',$assets.'/'.$file))throw new RuntimeException('Sync sample source before configuration: '.$file);
    $run=static function(string $cmd,array $data=[])use($root):array {
        $proc=proc_open([PHP_BINARY,$root.'/fbp/cli.php',$cmd,'--json='.json_encode($data,JSON_THROW_ON_ERROR)], [1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        if(!is_resource($proc))throw new RuntimeException('Cannot start FBP CLI.');
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);$r=json_decode($out,true);
        if($code!==0 || !is_array($r) || ($r['ok']??true)===false)throw new RuntimeException('FBP definition operation failed: '.$cmd.' '.substr($err,0,200));
        return $r;
    };
    $setting=$run('setting_get')['setting']??[];
    if((int)($setting['force_testmode']??0)!==1)throw new RuntimeException('Refusing configuration outside Developer Mode.');
    $tables=$run('db_tables_list')['items'];$arrays=$run('constant_array_list')['items'];$formats=$run('email_format_list')['items'];
    foreach($manifest['notes'] as $note)foreach($tables as $table)if($table['tb_name']===$note['table']['tb_name'])throw new RuntimeException('Sample note already exists; inspect instead of overwriting: '.$table['tb_name']);
    $optionSets=$manifest['options'];$optionSets['sec_product_types']=[];
    foreach($types as $i=>$type){$v=$manifest['types'][$type];$optionSets['sec_product_types'][]=['key'=>$v['key'],'value'=>$v['label'],'sort'=>$i];}
    foreach($manifest['notes'] as $note)foreach(array_keys($note['screens']) as $screen){
        if($run('screen_fields_list',['tb_name'=>$note['table']['tb_name'],'screen_name'=>$screen])['items'])throw new RuntimeException('Existing screen definition found; inspect before configuring: '.$note['table']['tb_name'].'/'.$screen);
    }
    foreach($optionSets as $name=>$values)foreach($arrays as $array)if($array['array_name']===$name)throw new RuntimeException('Sample option set already exists: '.$name);
    foreach($manifest['email_formats'] as $format)foreach($formats as $current)if($current['key']===$format['key'])throw new RuntimeException('Sample email format already exists.');
    foreach($optionSets as $name=>$values){$aid=$run('constant_array_add',['array_name'=>$name])['id'];foreach($values as $value)$run('constant_values_add',$value+['constant_array_id'=>$aid]);}
    $ids=[];
    foreach($manifest['notes'] as $note){
        $table=$note['table'];$name=$table['tb_name'];if(isset($note['parent']))$table['parent_tb_id']=$ids[$note['parent']];
        $id=$run('db_tables_add',$table)['id'];$ids[$name]=$id;
        $have=[];foreach($run('db_fields_list',['db_id'=>$id])['items'] as $f)$have[$f['parameter_name']]=$f;
        foreach($note['fields'] as $field){
            if(isset($have[$field['parameter_name']]))$run('db_fields_edit',$field+['id'=>$have[$field['parameter_name']]['id']]);
            else $run('db_fields_add',$field+['db_id'=>$id]);
        }
        foreach($note['screens'] as $screen=>$fields)foreach($fields as $field)$run('screen_fields_add',$field+['tb_name'=>$name,'screen_name'=>$screen]);
        foreach($note['buttons'] as $button)$run('db_additionals_add',$button+['tb_name'=>$name,'databases'=>[$id]]);
    }
    foreach($manifest['email_formats'] as $format)$run('email_format_add',$format);
    $typeArrayId=array_values(array_filter($run('constant_array_list')['items'],fn($r)=>$r['array_name']==='sec_product_types'))[0]['id'];
    $registered=array_values(array_filter($run('constant_values_list',['constant_array_id'=>$typeArrayId])['items'],fn($r)=>(int)$r['constant_array_id']===(int)$typeArrayId));
    $keys=array_map('strval',array_column($registered,'key'));$expected=array_map(fn($t)=>(string)$manifest['types'][$t]['key'],$types);sort($keys);sort($expected);if($keys!==$expected)throw new RuntimeException('Type registration mismatch.');
    echo json_encode(['ok'=>true,'mode'=>$mode,'types'=>$types,'db_ids'=>$ids,'business_rows_inserted'=>0,'cron_registered'=>false,'next'=>'Create shop/products, configure Square/mail, then explicitly enable shop runtime settings. Register the documented cron only when subscriptions are used.'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\nNo existing data was reset; inspect any partial installation before retrying.\n");exit(1);}
