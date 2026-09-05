<?php
if (($argv[1]??'')==='child') {
    class Controller {
        public $dirs;
        function set_check_login($value) {}
    }
    class TestDirs {
        public $datadir;
        function get_class_dir($class) {return $this->datadir.'/'.$class;}
    }
    require_once __DIR__.'/../fbp/app/db_api/db_api.php';
    $ctl=new Controller();$ctl->dirs=new TestDirs();$ctl->dirs->datadir=$argv[2];
    $api=new db_api($ctl);$method=new ReflectionMethod($api,'resolve_table');$method->setAccessible(true);
    echo json_encode($method->invoke($api,$ctl,'setting',$argv[3]==='default'?null:$argv[3]));exit;
}
$root=__DIR__.'/tmp-setting-scope-'.bin2hex(random_bytes(5));
try {
 foreach(['chapter_setting','setting','common'] as $owner){mkdir($root.'/'.$owner.'/fmt',0777,true);file_put_contents($root.'/'.$owner.'/fmt/setting.fmt','id,24,N');file_put_contents($root.'/'.$owner.'/setting.dat','fixture');}
 foreach(['chapter_setting','setting','default'] as $owner){
  $p=proc_open([PHP_BINARY,__FILE__,'child',$root,$owner],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
  $result=json_decode(stream_get_contents($pipes[1]),true);fclose($pipes[1]);fclose($pipes[2]);proc_close($p);
  if($owner==='chapter_setting') {if(($result['resolved_class']??'')!==$owner)throw new RuntimeException('App-owned setting is blocked');}
  elseif(($result['error_code']??'')!=='setting_api_required')throw new RuntimeException('System settings guard lost');
 }
 echo "DB API setting scope: app-owned table allowed, system settings protected\n";
} finally {
 foreach(['chapter_setting','setting','common'] as $owner){unlink($root.'/'.$owner.'/fmt/setting.fmt');unlink($root.'/'.$owner.'/setting.dat');rmdir($root.'/'.$owner.'/fmt');rmdir($root.'/'.$owner);}rmdir($root);
}
