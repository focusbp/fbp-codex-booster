<?php
/** Offline suite: no network access, SMTP delivery, business data or live credentials. */
if(PHP_SAPI!=='cli')exit(1);
try {
    $opts=[];foreach(array_slice($argv,1) as $arg){if(!preg_match('/^--(temp-root|framework-root)=(.+)$/D',$arg,$m))throw new RuntimeException('Use --temp-root=PATH [--framework-root=PATH].');$opts[$m[1]]=$m[2];}
    $tmp=realpath($opts['temp-root']??'');$fw=realpath($opts['framework-root']??dirname(__DIR__,5));
    if(!$tmp || !is_dir($tmp) || !$fw || !is_file($fw.'/cli.php'))throw new RuntimeException('Explicit existing temp root and valid framework root required.');
    $asset=dirname(__DIR__).'/assets/simple-ec-shop';$m=json_decode(file_get_contents($asset.'/simple-ec-shop.json'),true,512,JSON_THROW_ON_ERROR);
    foreach($m['files'] as $file){if(!is_file($asset.'/'.$file)||is_link($asset.'/'.$file))throw new RuntimeException('Missing sample file: '.$file);}
    $run=static function(array $cmd,?int $expected=0):string {
        $p=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Cannot start test process.');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);if($expected!==null&&$exit!==$expected)throw new RuntimeException($out.$err);return $out;
    };
    foreach($m['files'] as $file)if(str_ends_with($file,'.php'))$run([PHP_BINARY,'-l',$asset.'/'.$file]);
    $dir=$tmp.'/simple-ec-install-'.bin2hex(random_bytes(5));mkdir($dir.'/classes/app',0700,true);
    try {
        $installer=__DIR__.'/install_simple_ec_shop.php';$r=json_decode($run([PHP_BINARY,$installer,'--code-root='.$dir]),true,512,JSON_THROW_ON_ERROR);if(!$r['ok']||$r['business_rows_inserted']!==0)throw new RuntimeException('Installer result mismatch.');
        foreach($m['files'] as $file)if(hash_file('sha256',$asset.'/'.$file)!==hash_file('sha256',$dir.'/'.$file))throw new RuntimeException('Installed file mismatch.');
        $run([PHP_BINARY,$installer,'--code-root='.$dir],1);
        $run([PHP_BINARY,$installer,'--configure-test-root='.$dir,'--types=unknown'],1);
        echo "PASS: manifest inventory, PHP lint, exact temporary install, conflict refusal and invalid type refusal\n";
    } finally {
        $remove=function($p)use(&$remove){if(is_link($p)||is_file($p)){unlink($p);return;}foreach(array_diff(scandir($p),['.','..']) as $n)$remove($p.'/'.$n);rmdir($p);};$remove($dir);
    }
    putenv('SIMPLE_EC_FRAMEWORK_ROOT='.$fw);putenv('SIMPLE_EC_TEST_TEMP='.$tmp);
    foreach(['domain','checkout','refunds','recurring','concurrency'] as $test)echo $run([PHP_BINARY,$asset.'/tests/'.$test.'.php']);
    echo "PASS: Simple EC Shop sample offline verification complete; no external services used\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
