<?php
interface CodegenActionInterface {function run(Controller $ctl);}
class MemoryDb {
    public array $rows=[];public bool $failInsert=false;
    function get($id){return $this->rows[$id]??null;}
    function getall(...$a){return array_values($this->rows);}
    function insert(&$row){if($this->failInsert){$this->failInsert=false;throw new RuntimeException('simulated interruption');}$row['id']=$this->rows?max(array_keys($this->rows))+1:1;$this->rows[$row['id']]=$row;return $row['id'];}
    function update($row){$this->rows[$row['id']]=array_replace($this->rows[$row['id']],$row);}
}
class Controller {
    public array $dbs=[],$post=[],$session=['login'=>true],$assigned=[],$errors=[],$notices=[];public bool $admin=true;
    function db($t){return $this->dbs[$t]??=new MemoryDb();}
    function POST($k=null){return $k===null?$this->post:($this->post[$k]??null);}
    function get_session($k){return $this->session[$k]??null;}
    function set_session($k,$v){$this->session[$k]=$v;}
    function is_app_admin(){return $this->admin;}
    function show_notification_text($t){$this->notices[]=$t;}
    function encrypt($s){return 'enc:'.$s;}
    function decrypt($s){return is_string($s)&&str_starts_with($s,'enc:')?substr($s,4):'';}
    function decrypt_post($k){return $this->decrypt($this->POST($k));}
    function assign($k,$v){$this->assigned[$k]=$v;}
    function show_multi_dialog(...$a){}
    function res_error_message($k,$v){$this->errors[$k]=$v;}
    function close_multi_dialog(...$a){}
    function reload_work_area(){}
    function invoke(...$a){}
    function get_login_user_id(){return 1;}
}
require __DIR__.'/../classes/app/sample_reconciliation/sample_reconciliation.php';
require __DIR__.'/../classes/app/sample_reconciliation/Guard.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function fixture(Controller $c,string $t,int $amount,string $scope='A'):int {$r=['scope_key'=>$scope,'currency'=>'JPY','occurred_on'=>1,'counterparty'=>'架空','description'=>'検証','amount'=>$amount,'eligibility'=>0];return $c->db($t)->insert($r);}
$c=new Controller();$s=new SampleReconciliationStore($c);
$l=fixture($c,$s::LEFT,72000);$l2=fixture($c,$s::LEFT,10000);$r=fixture($c,$s::RIGHT,40000);$r2=fixture($c,$s::RIGHT,32000);
$s->add('left',$l,[$r],1,'first',$s->fingerprint());
check($c->db($s::LEFT)->get($l)['group_difference']===-32000,'mismatch must save');
$s->add('right',$r2,[$l],1,'second',$s->fingerprint());
check($c->db($s::LEFT)->get($l)['group_difference']===0&&$c->db($s::RIGHT)->get($r2)['group_recognition']===72000,'both lists show connected totals');
$s->add('right',$r2,[$l2],1,'third',$s->fingerprint());
check($c->db($s::RIGHT)->get($r)['group_recognition']===82000,'merge whole component');
$s->remove('left',$l,2,1,'unlink',$s->fingerprint());
check($c->db($s::LEFT)->get($l)['group_difference']===-32000&&$c->db($s::LEFT)->get($l2)['group_difference']===22000,'split and preserve other relations');
$links=count($c->db($s::LINKS)->rows);$events=count($c->db($s::EVENTS)->rows);
$s->add('left',$l,[$r],1,'repeat',$s->fingerprint());check(count($c->db($s::LINKS)->rows)===$links&&count($c->db($s::EVENTS)->rows)===$events,'duplicate add no event');
$s->add('left',$l,[$r2],1,'reactivate',$s->fingerprint());check(count($c->db($s::LINKS)->rows)===$links&&count($c->db($s::EVENTS)->rows)===$events+1,'re-add uses same pair');
$stale=$s->fingerprint();$row=$c->db($s::LEFT)->get($l);$row['amount']=73000;$c->db($s::LEFT)->update($row);
try{$s->add('left',$l,[$r2],1,'stale',$stale);throw new RuntimeException('accepted stale snapshot');}catch(InvalidArgumentException $e){}
$s->refresh();check($c->db($s::RIGHT)->get($r)['group_difference']===-11000,'source amount update reflected');
$foreign=fixture($c,$s::RIGHT,1,'B');try{$s->add('left',$l,[$foreign],1,'foreign',$s->fingerprint());throw new RuntimeException('cross scope');}catch(InvalidArgumentException $e){}
foreach(['1.5','-1','1e3','1000000000000'] as $invalid){try{SampleReconciliationGraph::money($invalid);throw new RuntimeException('invalid money');}catch(InvalidArgumentException $e){}}
$usd=['scope_key'=>'A','currency'=>'USD','amount'=>1,'eligibility'=>0];try{SampleReconciliationGraph::validatePair($usd,$usd);throw new RuntimeException('currency');}catch(InvalidArgumentException $e){}
$hold=['scope_key'=>'A','currency'=>'JPY','amount'=>1,'eligibility'=>1];try{SampleReconciliationGraph::validatePair($hold,$hold);throw new RuntimeException('held');}catch(InvalidArgumentException $e){}
try{$s->remove('right',$r,3,1,'indirect',$s->fingerprint());throw new RuntimeException('indirect removal');}catch(InvalidArgumentException $e){}
// The first event write fails AFTER relation update. Recovery inserts history once.
$c->db($s::EVENTS)->failInsert=true;$new=fixture($c,$s::RIGHT,2);
try{$s->add('left',$l,[$new],1,'interrupted',$s->fingerprint());throw new RuntimeException('failure not simulated');}catch(RuntimeException $e){check($e->getMessage()==='simulated interruption','unexpected error');}
$before=count($c->db($s::EVENTS)->rows);$s->recover();$s->recover();check(count($c->db($s::EVENTS)->rows)===$before+1,'recover missing audit without duplicates');
$ui=new sample_reconciliation();$c->post=['id'=>'enc:'.$l];$ui->left($c);$token=$c->assigned['token'];
$c->post=['token'=>$token,'target_id'=>(string)$new];$ui->save($c);check(!$c->errors,'UI add failed');$before=count($c->db($s::EVENTS)->rows);$ui->save($c);check(count($c->db($s::EVENTS)->rows)===$before,'UI replay');
$c->errors=[];$c->post=['token'=>'bad','target_id'=>'1'];$ui->save($c);check(isset($c->errors['token']),'bad grant');
$c->assigned=[];$c->admin=false;$c->post=['id'=>'enc:1'];$ui->left($c);check(!$c->assigned,'nonadmin UI');
$f=new SampleReconciliationGuard();check(!$f->can_access($c,'rows',$s::LEFT),'nonadmin read');$c->admin=true;$c->post=['group_difference'=>'0'];check(!$f->can_access($c,'edit_exe',$s::LEFT),'forged derived values');
$before=serialize($s->snapshot());$c->post=[];$f->can_access($c,'rows',$s::LEFT);check(serialize($s->snapshot())===$before,'read-only list must not update source or caches');
$c->post=['id'=>'enc:'.$l,'scope_key'=>'B'];check(!$f->can_access($c,'edit_exe',$s::LEFT),'linked scope change');
check(!$f->can_access($c,'delete_exe',$s::LEFT),'history row delete');check(!$f->can_access($c,'add_exe',$s::LINKS),'raw edge CRUD');
// Pagination and date ordering beyond the old 100-row cutoff.
for($i=0;$i<105;$i++)fixture($c,$s::RIGHT,$i);
$c->post=['id'=>'enc:'.$l,'page'=>2];$ui->left($c);check($c->assigned['page_no']===2&&count($c->assigned['candidates'])>0,'second page reachable');
$c->post=['id'=>'enc:'.$l,'keyword'=>'999999'];$ui->left($c);check($c->assigned['candidate_count']===0,'search result count');
echo "PASS: mismatch/add both sides/merge/split/no duplicate/stale/permissions/money/audit recovery/pagination\n";
