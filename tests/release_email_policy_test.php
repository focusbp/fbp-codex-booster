<?php
class Controller {
    function t($key, $params=[]) { return $key; }
    function cron_set() {}
    function get_setting() { return ['project_release_code'=>'app-target']; }
}
require_once __DIR__.'/../fbp/app/release/ReleaseManager.php';
function check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
function removeTree($path) { if(!is_dir($path))return; foreach(new FilesystemIterator($path) as $f) {if($f->isDir())removeTree($f->getPathname());else unlink($f->getPathname());}rmdir($path); }
$base=__DIR__.'/tmp-release-email-'.bin2hex(random_bytes(5));
try {
 foreach (['legacy','off','on','off_included'] as $mode) {
  $source=$base.'/'.$mode.'/source';$target=$base.'/'.$mode.'/target';
  foreach([$source,$target] as $p) {
   mkdir($p.'/classes/app',0777,true);mkdir($p.'/classes/data/email_format',0777,true);mkdir($p.'/classes/data/chapter_setting',0777,true);
   file_put_contents($p.'/classes/app/current.php','current');
  }
  file_put_contents($source.'/classes/data/email_format/email_format.dat','COMMON');
  file_put_contents($target.'/classes/data/email_format/email_format.dat','CHAPTER');
  file_put_contents($target.'/classes/data/email_format/local.dat','LOCAL');
  file_put_contents($target.'/classes/data/chapter_setting/setting.dat','PRIVATE');
  $info=['project_release_code'=>'app-target','type'=>'release'];
  if($mode!=='legacy') $info['deploy_email_templates']=!str_starts_with($mode,'off');
  $zipPath=$base.'/'.$mode.'/payload.zip';
  $sender=new ReleaseManager($source,$zipPath);$sender->create_release_zip_from_info($info);
  $z=new ZipArchive();$z->open($zipPath);
  check(($z->locateName('data/email_format/email_format.dat')!==false)===!str_starts_with($mode,'off'),'sender policy');
  if($mode==='off_included') $z->addFromString('data/email_format/email_format.dat','MUST_NOT_DEPLOY');
  if($mode==='legacy') $z->addFromString('info.json',json_encode(['project_release_code'=>'app-target','type'=>'release']));
  $z->close();
  $receiver=new ReleaseManager($target);$receiver->validate_release_zip(new Controller(),$zipPath);
  // UI confirmation and execution use separate instances/requests.
  (new ReleaseManager($target))->apply_release_zip(new Controller(),$zipPath);
  $off=str_starts_with($mode,'off');
  check(file_get_contents($target.'/classes/data/email_format/email_format.dat')===($off?'CHAPTER':'COMMON'),'email changed incorrectly');
  check(file_exists($target.'/classes/data/email_format/local.dat')===$off,'email deletion policy');
  check(file_get_contents($target.'/classes/data/chapter_setting/setting.dat')==='PRIVATE','business data changed');
 }
 // Invalid policy must be rejected before packaging.
 try { (new ReleaseManager($source,$base.'/bad.zip'))->create_release_zip_from_info(['deploy_email_templates'=>'invalid']); throw new LogicException('accepted invalid flag'); }
 catch(RuntimeException $expected) {}
 echo "Release email policy: 4 modes, separate-request preservation, business data and invalid flag passed\n";
} finally {removeTree($base);}
