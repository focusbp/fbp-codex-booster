<?php
if(PHP_SAPI!=='cli')exit(1);
try {
    $opts=[];foreach(array_slice($argv,1) as $arg){if(!preg_match('/^--(temp-root|framework-root)=(.+)$/D',$arg,$m))throw new RuntimeException('Use --temp-root=PATH --framework-root=PATH.');$opts[$m[1]]=$m[2];}
    $tmp=realpath($opts['temp-root']??'');$fw=realpath($opts['framework-root']??dirname(__DIR__,5));
    if(!$tmp||!is_dir($tmp)||!$fw||!is_file($fw.'/cli.php'))throw new RuntimeException('Explicit scratch root and valid framework root required.');
    $asset=dirname(__DIR__).'/assets/many-to-many-reconciliation';$m=json_decode(file_get_contents($asset.'/many-to-many-reconciliation.json'),true,512,JSON_THROW_ON_ERROR);
    $run=function(array $cmd,int $expected=0):string{$p=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$o=stream_get_contents($pipes[1]);$e=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);if(proc_close($p)!==$expected)throw new RuntimeException($o.$e);return $o;};
    foreach($m['files'] as $f){if(!is_file($asset.'/'.$f))throw new RuntimeException('Missing asset '.$f);if(str_ends_with($f,'.php'))$run([PHP_BINARY,'-l',$asset.'/'.$f]);}
    $root=$tmp.'/reconciliation-install-'.bin2hex(random_bytes(4));mkdir($root.'/classes/app',0700,true);
    try{
        $installer=__DIR__.'/install_many_to_many_reconciliation.php';$result=json_decode($run([PHP_BINARY,$installer,'--code-root='.$root]),true,512,JSON_THROW_ON_ERROR);if(!$result['ok']||$result['data_inserted']!==0)throw new RuntimeException('Installer inserted data');
        foreach($m['files'] as $f)if(hash_file('sha256',$asset.'/'.$f)!==hash_file('sha256',$root.'/'.$f))throw new RuntimeException('Copy mismatch');
        $run([PHP_BINARY,$installer,'--code-root='.$root],1);
        foreach($m['files'] as $f)if(hash_file('sha256',$asset.'/'.$f)!==hash_file('sha256',$root.'/'.$f))throw new RuntimeException('Refusal changed files');
        if(is_dir($root.'/classes/data'))throw new RuntimeException('Source install created data');
        echo "PASS: lint/manifest/exact installer/no fixture insertion/overwrite refusal\n";
    }finally{$remove=function($p)use(&$remove){if(is_file($p)||is_link($p)){unlink($p);return;}foreach(array_diff(scandir($p),['.','..']) as $n)$remove($p.'/'.$n);rmdir($p);};$remove($root);}
    echo $run([PHP_BINARY,$asset.'/tests/domain.php']);
    echo $run([PHP_BINARY,$asset.'/tests/csv.php']);
    putenv('RECONCILIATION_FRAMEWORK_ROOT='.$fw);putenv('RECONCILIATION_TEMP_ROOT='.$tmp);
    echo $run([PHP_BINARY,$asset.'/tests/concurrency.php']);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
