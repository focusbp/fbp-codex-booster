<?php
require __DIR__.'/../classes/app/sample_statement_csv/Parser.php';
function checkCsv($ok,$message){if(!$ok)throw new RuntimeException($message);}
$csv="入出金日,摘要,入金額,出金額,残高,取引ID\r\n2026/11/01,架空入金,40000,0,40000,bank-1\r\n2026/11/02,架空出金,0,1000,39000,bank-2\r\n2026/11/03,利息,10,0,39010,bank-3\r\n";
$r=SampleStatementCsvParser::parse($csv,'sample','main',true);
checkCsv(count($r)===3&&$r[0]['amount']===40000&&$r[1]['withdrawal_amount']===1000&&$r[1]['eligibility']===2&&$r[2]['eligibility']===2,'deposit/withdrawal/interest classification');
$sjis=SampleStatementCsvParser::parse(mb_convert_encoding($csv,'SJIS-win','UTF-8'),'sample','main',true);
checkCsv($r===$sjis,'encoding-independent identity');
$p=SampleStatementCsvParser::plan($r,[]);checkCsv($p['insert_count']===3,'initial plan');
$p=SampleStatementCsvParser::plan($r,$r);checkCsv($p['duplicate_count']===3&&$p['insert_count']===0,'same statement no duplicates');
$changed=$csv;$changed=str_replace('40000,0,40000,bank-1','41000,0,41000,bank-1',$changed);
$p=SampleStatementCsvParser::plan(SampleStatementCsvParser::parse($changed,'sample','main',true),$r);checkCsv($p['conflict_count']===1,'changed external ID blocked');
$other=SampleStatementCsvParser::parse($csv,'sample','other',true);checkCsv(SampleStatementCsvParser::plan($other,$r)['insert_count']===3,'account scope separation');
$noId="入出金日,摘要,入金額,出金額,残高\n2026/11/01,架空入金,40000,0,40000\n";
$one=SampleStatementCsvParser::parse($noId,'sample','main',false);
$overlap=$noId."2026/11/02,架空入金2,10000,0,50000\n";
checkCsv(SampleStatementCsvParser::plan(SampleStatementCsvParser::parse($overlap,'sample','main',false),$one)['conflict_count']===1,'ambiguous overlap without bank ID blocked rather than silently dropped');
checkCsv(SampleStatementCsvParser::plan($one,$one)['duplicate_count']===1,'no ID exact-file repeat');
foreach([str_replace('2026/11/01','2026/11/31',$noId),str_replace('40000,0','40000,100',$noId),str_replace('40000,0','40000.5,0',$noId),"日付,摘要\n2026/11/01,x\n"] as $bad){try{SampleStatementCsvParser::parse($bad,'sample','main',false);throw new RuntimeException('Invalid CSV accepted');}catch(InvalidArgumentException $e){}}
checkCsv(SampleStatementCsvParser::plan([$r[0],$r[0]],[])['conflict_count']===1,'duplicate ID in file blocked');
echo "PASS: CSV UTF-8/SJIS/deposit/withdrawal/interest/idempotency/ID conflict/overlap/account separation/date/amount validation\n";
interface CodegenActionInterface {function run(Controller $ctl);}
class Controller {
 public array $notices=[];
 function get_session($key){return false;}
 function is_app_admin(){return false;}
 function show_notification_text($message){$this->notices[]=$message;}
 function db($table){throw new RuntimeException('Anonymous CSV touched DB');}
}
require __DIR__.'/../classes/app/sample_statement_csv/sample_statement_csv.php';
$ctl=new Controller();$handler=new sample_statement_csv();$handler->run($ctl);$handler->upload_exe($ctl);$handler->save($ctl);
checkCsv(count($ctl->notices)===3,'anonymous CSV entrypoints denied');
echo "PASS: anonymous CSV open/upload/save denied before DB access\n";
