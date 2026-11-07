<?php
/** Isolated contract fixture. No FBP runtime, network or operational databases. */
class FixtureStore {
    public array $rows=[];
    public function select($key,$value): array { return array_values(array_filter($this->rows,fn($r)=>($r[$key]??null)===$value)); }
    public function insert(array &$row): int { $row['id']=count($this->rows)+1;$this->rows[$row['id']]=$row;return $row['id']; }
    public function update(array &$row): void { $this->rows[$row['id']]=array_replace($this->rows[$row['id']],$row); }
    public function get($id): array { return $this->rows[$id]??[]; }
    public function getall(): array { return array_values($this->rows); }
    public function get_identifier(): string { return 'fixture'; }
}
class Controller {
    public array $post=[],$stores=[],$logs=[];
    public function __construct(public string $folder) { mkdir($folder,0700,true); }
    public function POST($key) { return $this->post[$key]??null; }
    public function db($table,$class=null): FixtureStore { return $this->stores[$table]??=new FixtureStore(); }
    public function save_file($name,$data): void { file_put_contents($this->folder.'/'.$name,$data); }
    public function get_file_info($id,$encrypted=true): array { $row=$this->db('file')->get($id);return ['fullpath'=>$this->folder.'/'.($row['path']??'missing')]; }
    public function console_log($value): void { $this->logs[]=json_decode($value,true,512,JSON_THROW_ON_ERROR); }
}
require $argv[1];
$ctl=new Controller($argv[3]);$import=new website_import();
$ctl->post=['manifest'=>$argv[2]];$import->run($ctl);$plan=end($ctl->logs);
if ($ctl->db('wm_article')->rows||$ctl->db('wm_media')->rows||$plan['applied']) throw new RuntimeException('Dry run wrote data');
$ctl->post=['manifest'=>$argv[2],'apply'=>true,'reviewed'=>true,'confirm_manifest_sha256'=>hash_file('sha256',$argv[2])];
$import->run($ctl);$first=end($ctl->logs);$import->run($ctl);$again=end($ctl->logs);
if ($again['articles_created']||$again['media_created']) throw new RuntimeException('Duplicate import');
$import->inventory($ctl);$inventory=end($ctl->logs);
$id=array_key_first($ctl->db('wm_article')->rows);$before=$ctl->db('wm_article')->rows[$id]['body'];
$ctl->db('wm_article')->rows[$id]['body'].='edited';$rejected=false;
try {$import->run($ctl);} catch (RuntimeException $error) {$rejected=true;}
if (!$rejected) throw new RuntimeException('Edited target was not protected');
$import->inventory($ctl);$edited=end($ctl->logs);
$ctl->db('wm_article')->rows[$id]['body']=$before;
$bad=new Controller($argv[3].'-invalid');$bad->post=['manifest'=>$argv[2],'apply'=>true,'reviewed'=>true,'confirm_manifest_sha256'=>str_repeat('0',64)];
$rejected=false;try {$import->run($bad);} catch (RuntimeException $error) {$rejected=true;}
if (!$rejected||$bad->stores) throw new RuntimeException('Stale manifest consent reached stores');
echo json_encode(['plan'=>$plan,'receipt'=>$first,'again'=>$again,'inventory'=>$inventory,'edited_inventory'=>$edited],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
