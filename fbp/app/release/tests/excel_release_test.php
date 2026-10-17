<?php
// Isolated deployment check. Workspace must be absent before the test starts.
if (PHP_SAPI!=='cli' || $argc!==2 || file_exists($argv[1])) exit(2);
interface Controller {public function cron_set();public function t($key,$values=[]);}
class ExcelReleaseController implements Controller {function cron_set(){}function t($key,$values=[]){return $key;}}
require_once dirname(__DIR__).'/ReleaseManager.php';
$root=$argv[1];
foreach (['source','target'] as $side) {
    mkdir("$root/$side/classes/app/example",0770,true);
    mkdir("$root/$side/classes/data/excel_grid/fmt",0770,true);
    mkdir("$root/$side/classes/data/_common/fmt",0770,true);
}
file_put_contents("$root/source/classes/app/example/example.php",'<?php class example {}');
file_put_contents("$root/source/classes/data/excel_grid/settings.dat",'old test definitions');
file_put_contents("$root/source/classes/data/excel_grid/fmt/notes.fmt","id,24,N\na,24,T\n");
file_put_contents("$root/target/classes/data/excel_grid/settings.dat",'new production definitions');
file_put_contents("$root/target/classes/data/excel_grid/fmt/notes.fmt","id,24,N\na,24,T\nb,255,T\n");
file_put_contents("$root/source/classes/data/_common/fmt/notes.fmt","id,24,N\n");
$manager=new ReleaseManager("$root/source");$zip=$manager->create_release_zip_from_info([]);
$archive=new ZipArchive;$archive->open($zip);
for($i=0;$i<$archive->numFiles;$i++)if(strpos($archive->getNameIndex($i),'data/excel_grid/')===0)throw new RuntimeException('Runtime definitions in release');
$archive->close();
(new ReleaseManager("$root/target"))->apply_release_zip(new ExcelReleaseController,$zip);
if(file_get_contents("$root/target/classes/data/excel_grid/settings.dat")!=='new production definitions')throw new RuntimeException('Production definitions replaced');
if(strpos(file_get_contents("$root/target/classes/data/excel_grid/fmt/notes.fmt"),'b,255,T')===false)throw new RuntimeException('Runtime format lost');
echo "PASS: changed production Excel definitions and runtime format survive release\n";
