<?php
/** Copy code only. Definitions, credentials and external services are never changed. */
$options=getopt('',['code-root:','ocr-table:']);
$root=isset($options['code-root'])?realpath($options['code-root']):false;$table=$options['ocr-table']??'';
if(!$root||!is_dir($root.'/classes/app')||!preg_match('/^[a-z][a-z0-9_]{0,47}$/D',$table))throw new RuntimeException('Specify --code-root=<app source> --ocr-table=<existing Excel note>');
$assets=dirname(__DIR__).'/assets/note-feature-samples';$manifest=json_decode(file_get_contents($assets.'/note-feature-samples.json'),true,512,JSON_THROW_ON_ERROR);
$classes=[];
foreach($manifest['files'] as $file){
    if(!str_starts_with($file,'classes/app/'))continue;
    if(str_contains($file,'..')||!is_file($assets.'/'.$file))throw new RuntimeException('Invalid sample asset');
    $class=explode('/',$file)[2];$classes[$class]=true;
}
// Refuse all collisions before copying anything. Integrate existing public_pages manually.
foreach(array_keys($classes) as $class)if(file_exists($root.'/classes/app/'.$class))throw new RuntimeException('Class exists; do not overwrite: '.$class);
$count=0;
foreach($manifest['files'] as $file){
    if(!str_starts_with($file,'classes/app/'))continue;
    $destination=$root.'/'.$file;$directory=dirname($destination);if(!is_dir($directory)&&!mkdir($directory,0775,true))throw new RuntimeException('Cannot create sample directory');
    $contents=file_get_contents($assets.'/'.$file);
    if(str_ends_with($file,'feature_excel_ocr.php'))$contents=str_replace('__OCR_TABLE__',$table,$contents);
    if(file_put_contents($destination,$contents)===false)throw new RuntimeException('Cannot copy sample');++$count;
}
echo json_encode(['ok'=>true,'files'=>$count,'next'=>'Sync, configure test definitions, register MCP, and use the verification reference.'],JSON_UNESCAPED_UNICODE)."\n";
