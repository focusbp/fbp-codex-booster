<?php
// Isolated release-source/target checks; no actual deployment or production writes.
if(PHP_SAPI!=='cli'||$argc!==2||file_exists($argv[1]))exit(2);
interface Controller {public function cron_set();public function t($key,$values=[]);}
class ExcelReleaseController implements Controller {function cron_set(){}function t($key,$values=[]){return $key;}}
require_once dirname(__DIR__).'/ReleaseManager.php';
require_once dirname(__DIR__,3).'/lib/fixed_file_manager/fixed_file_manager.php';
$root=$argv[1];$checks=0;
function check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);++$checks;}
foreach(['publish','preserve','legacy','clear'] as $case){
 foreach(['source','target'] as $side){mkdir("$root/$case/$side/classes/app/example",0770,true);mkdir("$root/$case/$side/classes/data/excel_grid/fmt",0770,true);mkdir("$root/$case/$side/classes/data/_common/fmt",0770,true);mkdir("$root/$case/$side/classes/data/common",0770,true);}
 $source="$root/$case/source";$target="$root/$case/target";
 file_put_contents("$source/classes/app/example/example.php",'<?php class example {}');
 if($case!=='clear'){file_put_contents("$source/classes/data/excel_grid/settings.dat",'corrected test definitions');file_put_contents("$source/classes/data/excel_grid/fmt/notes.fmt","id,24,N\na,255,T\nb,255,T\n");}
 file_put_contents("$source/classes/data/excel_grid/states.dat",'test errors must not deploy');
 file_put_contents("$source/classes/data/common/notes.dat",'test business rows must not deploy');
 file_put_contents("$target/classes/data/excel_grid/settings.dat",'production definitions');
 file_put_contents("$target/classes/data/excel_grid/fmt/notes.fmt","id,24,N\na,24,T\n");
 file_put_contents("$target/classes/data/excel_grid/states.dat",'production errors');
 $db=new fixed_file_manager('notes',"$target/classes/data/common","$target/classes/data/excel_grid/fmt");$business=['a'=>'production row'];$db->insert($business);$db->close();$businessBytes=file_get_contents("$target/classes/data/common/notes.dat");
 file_put_contents("$source/classes/data/_common/fmt/notes.fmt","id,24,N\n");
 $manager=new ReleaseManager($source);$zip=$manager->create_release_zip_from_info($case==='preserve'?['deploy_db_definitions'=>0]:[]);
 $archive=new ZipArchive;$archive->open($zip);
 check($archive->locateName('data/excel_grid/states.dat')===false,'Calculation states included');
 check($archive->locateName('data/common/notes.dat')===false,'Test rows included');
 if($case==='publish')check($archive->locateName('data/excel_grid/settings.dat')!==false,'Corrected definitions missing');
 if($case==='preserve')check($archive->locateName('data/excel_grid/settings.dat')===false,'Definitions included despite skip flag');
 if($case==='legacy'){$info=json_decode($archive->getFromName('info.json'),true);unset($info['deploy_excel_definitions']);$archive->addFromString('info.json',json_encode($info));}
 $archive->close();
 (new ReleaseManager($target))->apply_release_zip(new ExcelReleaseController,$zip);
 if($case==='publish'){
  check(file_get_contents("$target/classes/data/excel_grid/settings.dat")==='corrected test definitions','Test definitions did not reach production');
  check(str_contains(file_get_contents("$target/classes/data/excel_grid/fmt/notes.fmt"),'b,255,T'),'Runtime format did not deploy');
 }elseif($case==='clear')check(!is_file("$target/classes/data/excel_grid/settings.dat"),'Obsolete definitions not cleared');
 else{
  check(file_get_contents("$target/classes/data/excel_grid/settings.dat")==='production definitions','Preserved definitions replaced');
  check(!str_contains(file_get_contents("$target/classes/data/excel_grid/fmt/notes.fmt"),'b,255,T'),'Preserved format replaced');
 }
 check(file_get_contents("$target/classes/data/common/notes.dat")===$businessBytes,'Business data overwritten');
 check(file_get_contents("$target/classes/data/excel_grid/states.dat")==='production errors','Calculation state overwritten');
 if($case==='publish'){$db=new fixed_file_manager('notes',"$target/classes/data/common","$target/classes/data/excel_grid/fmt");$rows=$db->getall();check(count($rows)===1 && $rows[0]['a']==='production row','Format update lost existing data');check(($rows[0]['b']??null)==='','New field missing after format update');$db->close();}
}
echo "PASS $checks release checks: test definitions publish, data/states preserved, skip policy, legacy compatibility, obsolete definitions\n";
