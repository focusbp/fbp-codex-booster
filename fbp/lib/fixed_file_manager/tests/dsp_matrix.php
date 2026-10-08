<?php
/** Isolated, deterministic 1,000-case suite. Run from the synchronized test environment. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc < 3) { fwrite(STDERR, "Usage: php dsp_matrix.php <workspace> <baseline-ffm> [compat]\n"); exit(2); }

interface Controller {}
class Controller_class implements Controller {
    public bool $guard = false;
    public int $actor = 1;
    public int $organization = 1;
    public bool $admin = false;
    public string $channel = 'cli';
    public array $pool = [];
    public static function getInstance() { return null; }
    public function encrypt($id): string { return 'fixture-' . $id; }
    public function get_dsp_channel(): string { return $this->channel; }
    public function freeze_dsp_channel(): void {}
    public function get_prohibit_new_db(): bool { return $this->guard; }
    public function set_prohibit_new_db(bool $flag): void { $this->guard = $flag; }
    public function db($name, $class = null, $partition = null) {
        if (isset($this->pool[$name])) return $this->pool[$name];
        if ($this->guard) throw new RuntimeException('New DB prohibited');
        throw new RuntimeException('No fixture DB');
    }
}

$workspace = $argv[1];
$compatOnly = ($argv[3] ?? '') === 'compat';
require_once $compatOnly ? $argv[2] : dirname(__DIR__) . '/fixed_file_manager.php';
putenv('FBP_FFM_LOG_DISABLE=1');
if (!is_dir($workspace)) mkdir($workspace, 0770, true);

function check($value, string $reason): void { if (!$value) throw new RuntimeException($reason); }
function same($expected, $actual): void { check($expected === $actual, 'Expected ' . json_encode($expected) . ', got ' . json_encode($actual)); }
function fixture(string $name, string $fmt, array $rows, array $options = []): fixed_file_manager {
    global $workspace;
    $root = $workspace . '/' . $name;
    mkdir($root . '/data', 0770, true); mkdir($root . '/fmt', 0770, true);
    file_put_contents($root . '/fmt/sample.fmt', $fmt);
    $ffm = new fixed_file_manager('sample', $root . '/data', $root . '/fmt');
    foreach ($rows as $row) $ffm->insert($row);
    $ffm->close();
    return new fixed_file_manager('sample', $root . '/data', $root . '/fmt', $options);
}
function removeTree(string $root): void {
    foreach (array_diff(scandir($root), ['.', '..']) as $name) {
        $path = $root . '/' . $name;
        is_dir($path) ? removeTree($path) : unlink($path);
    }
    rmdir($root);
}
function attempt(callable $action): bool {
    try { $action(); return true; } catch (DspException $e) { return false; }
}

// 250 legacy comparisons: schema/type/size x input shape x operation sequence.
function compatibility(): array {
    $out = [];
    $formats = [
        "id,12,N\nowner,12,N\nname,12,T\nscore,12,F\n",
        "id,24,N\nowner,24,N,IDX\nname,24,T\nscore,24,F\n",
        "id,12,N\nowner,12,N\nname,60,T,IDX\nscore,12,F\n",
        "id,24,N\nowner,24,N,IDX\nname,60,T\nscore,24,F\ntags,80,A\n",
        "id,24,N\nowner,24,N\nname,90,T\nscore,24,F\ntags,120,A\n",
    ];
    $names = ['alpha', '日本語の予約', ' long name ', '0123456789abcdefghijklmnopqrstuvwxyz', ''];
    foreach ($formats as $f => $fmt) foreach ($names as $v => $name) for ($sequence = 0; $sequence < 10; $sequence++) {
        $rows = [];
        for ($n = 1; $n <= 5; $n++) $rows[] = ['owner' => $n % 2 + 1, 'name' => $name . $n, 'score' => $n * .25, 'tags' => ['s' => $sequence, 'n' => $n]];
        $db = fixture("compat-$f-$v-$sequence", $fmt, $rows);
        $db->set_flg_filter_zero((bool) ($sequence % 2));
        $db->update(['id' => 2, 'name' => $name . '-updated', 'score' => $sequence * 1.5]);
        if ($sequence % 3 === 0) $db->delete(4);
        $last = null;
        $selected = $db->select('owner', $sequence % 2 + 1, true, 'AND', $sequence % 2 ? 'score' : null, SORT_ASC, $sequence % 4 + 1, $last);
        $filtered = $db->filter('name', $v % 2 ? '予約' : '1', false, 'AND', 'id', SORT_DESC, 3);
        $many = $db->get_many([5, 1, '2', 2, 0, -1]);
        $db->seek(1); $forward = []; while (($r = $db->next()) !== null) $forward[] = $r;
        $db->seek_end(); $backward = []; while (($r = $db->before()) !== null) $backward[] = $r;
        $out[] = [$db->getall('id'), $selected, $last, $filtered, $many, $forward, $backward, $db->get(99), $db->get_header_info(), hash_file('sha256', $db->get_path_dat())];
        $db->close();
    }
    return $out;
}

if ($compatOnly) { echo json_encode(compatibility(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); exit; }

class MatrixPolicy implements DspInterface {
    public string $error = '';
    public bool $hideId = false;
    public function __construct(public Controller_class $ctl, public string $channel) {}
    private function fault(): void {
        if ($this->error === 'throw') throw new RuntimeException('private failure');
        if ($this->error === 'new_db') $this->ctl->db('new');
        if ($this->error === 'nested') {
            $previous = $this->ctl->get_prohibit_new_db();
            $this->ctl->set_prohibit_new_db(true);
            try { throw new DspException('nested'); } finally { $this->ctl->set_prohibit_new_db($previous); }
        }
    }
    private function allowed(array $row): bool {
        return $this->ctl->actor > 0 && $row['organization'] === $this->ctl->organization && ($row['owner'] === $this->ctl->actor || $this->ctl->admin);
    }
    public function authorizeInsert(array $newRow): void {
        $this->fault();
        if ($this->ctl->actor <= 0 || $newRow['owner'] !== $this->ctl->actor || $newRow['organization'] !== $this->ctl->organization) throw new DspException('insert_owner');
    }
    public function authorizeRead(array $request): void {
        $this->fault();
        if ($this->ctl->actor <= 0) throw new DspException('login');
        $fields = array_merge((array) ($request['itemname'] ?? []), [$request['sortitem'] ?? null]);
        if (in_array('secret', $fields, true)) throw new DspException('query_secret');
    }
    public function inspectRead(array $row): DspReadDecision {
        $this->fault();
        $fields = ['id', 'owner', 'organization', 'name', 'score', 'from_node_id', 'to_node_id', 'relation_type', 'enabled'];
        if ($this->hideId) $fields = array_values(array_diff($fields, ['id']));
        return new DspReadDecision($this->allowed($row), $fields);
    }
    public function authorizeUpdate(array $before, array $after, array $submittedFields): void {
        $this->fault();
        if (!$this->allowed($before)) throw new DspException('update_owner');
        if ($before['owner'] !== $after['owner'] || $before['organization'] !== $after['organization'] || in_array('secret', $submittedFields, true)) throw new DspException('immutable');
    }
    public function authorizeDelete(array $before): void {
        $this->fault();
        if (!$this->allowed($before) || $before['enabled'] === 0) throw new DspException('delete_owner');
    }
}

$report = fopen($workspace . '/cases.jsonl', 'wb');
$passed = 0; $failed = 0; $counts = [];
function testCase(string $group, array $conditions, callable $body): void {
    global $report, $passed, $failed, $counts;
    $id = array_sum($counts) + 1; $counts[$group] = ($counts[$group] ?? 0) + 1;
    try { $body(); $passed++; $result = ['passed' => true]; }
    catch (Throwable $e) { $failed++; $result = ['passed' => false, 'error' => $e->getMessage()]; }
    fwrite($report, json_encode(['id' => $id, 'group' => $group, 'conditions' => $conditions] + $result, JSON_UNESCAPED_UNICODE) . "\n");
}

$baselineRoot = $workspace . '/baseline'; mkdir($baselineRoot);
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($baselineRoot) . ' ' . escapeshellarg($argv[2]) . ' compat';
$reference = json_decode(shell_exec($command), true, 512, JSON_THROW_ON_ERROR);
$actual = compatibility();
foreach ($actual as $i => $result) testCase('compatibility', ['schema' => intdiv($i, 50), 'input' => intdiv($i % 50, 10), 'sequence' => $i % 10], fn() => same($reference[$i], $result));

$fmt = "id,24,N\nowner,24,N,IDX\norganization,24,N,IDX\nname,30,T\nsecret,30,T\nscore,12,F\nfrom_node_id,24,N,IDX\nto_node_id,24,N,IDX\nrelation_type,12,T\nenabled,2,N\n";
$actors = [0, 1, 2, 3, 4];
function context(int $actor, int $variant): Controller_class {
    $ctl = new Controller_class; $ctl->actor = $actor; $ctl->admin = $actor === 4;
    $ctl->channel = $variant % 2 ? 'public' : 'mcp'; return $ctl;
}
function row(int $owner, int $organization, int $enabled = 1): array {
    return ['owner' => $owner, 'organization' => $organization, 'name' => '予約', 'secret' => 'hidden', 'score' => 1.25, 'from_node_id' => 1, 'to_node_id' => 2, 'relation_type' => 'book', 'enabled' => $enabled];
}
function policyFixture(string $prefix, array $rows, Controller_class $ctl, MatrixPolicy $policy, bool $index = true): fixed_file_manager {
    global $fmt, $counts;
    return fixture($prefix . '-' . array_sum($counts), $fmt, $rows, ['controller' => $ctl, 'dsp' => $policy, 'index_disabled' => !$index]);
}

foreach ($actors as $actor) foreach (range(1, 5) as $owner) foreach ([1,2] as $org) foreach ([0,1] as $channel) {
    testCase('insert', compact('actor','owner','org','channel'), function() use ($actor,$owner,$org,$channel) {
        $ctl = context($actor,$channel); $policy = new MatrixPolicy($ctl,$ctl->channel); $db = policyFixture('insert', [], $ctl,$policy);
        try {
            $r = row($owner,$org); $hash = hash_file('sha256',$db->get_path_dat());
            $allowed = attempt(function() use ($db,&$r) { $db->insert($r); });
            same($actor > 0 && $actor === $owner && $org === 1, $allowed);
            if ($allowed) { same(1, $r['id']); same('予約', $db->get(1)['name']); }
            else { same($hash, hash_file('sha256',$db->get_path_dat())); check(!isset($r['id']), 'Denied insert assigned id'); }
            same(false,$ctl->guard);
        } finally { $db->close(); }
    });
}

foreach ($actors as $actor) foreach (range(1,5) as $owner) foreach ([1,2] as $org) foreach (range(0,3) as $change) {
    testCase('update', compact('actor','owner','org','change'), function() use ($actor,$owner,$org,$change) {
        $ctl = context($actor,$change); $policy = new MatrixPolicy($ctl,$ctl->channel); $db = policyFixture('update', [row($owner,$org)],$ctl,$policy,(bool)($change%2));
        try {
            $patch = ['id'=>1] + match($change) {0=>['name'=>str_repeat('あ',15)],1=>['owner'=>$owner+1],2=>['secret'=>'hidden'],3=>['score'=>'２,３４５.５']};
            $hash=hash_file('sha256',$db->get_path_dat()); $position=ftell($db->hf);
            $allowed=attempt(fn()=>$db->update($patch));
            same($actor>0 && $org===1 && ($actor===$owner || $actor===4) && in_array($change,[0,3],true),$allowed);
            same($position,ftell($db->hf));
            if (!$allowed) same($hash,hash_file('sha256',$db->get_path_dat()));
            else {
                $r=$db->get(1); check(!isset($r['secret']),'Secret leaked'); same($owner,$r['owner']);
                // FFM truncates to 12 bytes before kana conversion: "２,３４" becomes 234.
                if ($change===0) same(str_repeat('あ',10),$r['name']); else same(234.0,$r['score']);
            }
            same(false,$ctl->guard);
        } finally {$db->close();}
    });
}

foreach ($actors as $actor) foreach (range(1,5) as $owner) foreach ([1,2] as $org) foreach ([0,1] as $enabled) {
    testCase('delete',compact('actor','owner','org','enabled'),function() use($actor,$owner,$org,$enabled){
        $ctl=context($actor,$enabled);$policy=new MatrixPolicy($ctl,$ctl->channel);$db=policyFixture('delete',[row($owner,$org,$enabled)],$ctl,$policy);
        try {
            $hash=hash_file('sha256',$db->get_path_dat());$allowed=attempt(fn()=>$db->delete(1));
            same($actor>0 && $org===1 && ($actor===$owner || $actor===4) && $enabled===1,$allowed);
            if (!$allowed) same($hash,hash_file('sha256',$db->get_path_dat()));
            elseif($actor>0) same(null,$db->get(1));
        }finally{$db->close();}
    });
}

$methods=['getall','select','filter','next','before','match','get_many','neighbors','neighbors_many','iterate_filter'];
foreach($methods as $method) foreach($actors as $actor) foreach(range(1,5) as $owner){
    testCase('read',compact('method','actor','owner'),function()use($method,$actor,$owner){
        $ctl=context($actor,$owner);$policy=new MatrixPolicy($ctl,$ctl->channel);
        $db=policyFixture('read',[row($owner,1),row(5,2),row($owner,1)],$ctl,$policy,(bool)($owner%2));
        try {
            $rows=[];$last=null;
            $allowed=attempt(function()use($db,$method,&$rows,&$last){
                $rows=match($method){
                    'getall'=>$db->getall('id'),
                    'select'=>$db->select('organization',1,true,'AND',null,SORT_DESC,1,$last),
                    'filter'=>$db->filter('name','予約',false,'AND',null,SORT_DESC,1,$last),
                    'match'=>$db->match('name','予約',1,$last),
                    'get_many'=>$db->get_many([1,3]),
                    'neighbors'=>$db->neighbors(1,null,1),
                    'neighbors_many'=>$db->neighbors_many([1,2],null,1,'both'),
                    'iterate_filter'=>$db->iterate_filter(static function($r){check(!isset($r['secret']),'Callback leaked secret');return true;}),
                    default=>[],
                };
                if($method==='next'){$db->seek(1);while(($r=$db->next())!==null)$rows[]=$r;}
                if($method==='before'){$db->seek_end();while(($r=$db->before())!==null)$rows[]=$r;}
            });
            $visible=$actor>0&&($actor===$owner||$actor===4);
            same($actor>0&&($method!=='get_many'||$visible),$allowed);
            if(!$allowed)return;
            $flat=$method==='neighbors_many'?array_merge(...array_values($rows)):$rows;
            if(!$visible){same([],$flat);return;}
            $expected=in_array($method,['select','filter','match','neighbors'],true)?1:2;
            same($expected,count($flat));
            foreach($flat as $r){if(is_array($r)){check(!isset($r['secret']),'Secret leaked');same(1,$r['organization']);}else check(in_array($r,[1,3],true),'Hidden ID leaked');}
            if($method==='select'||$method==='filter')same(false,$last);
            check(!attempt(fn()=>$db->get(2)),'Direct hidden row allowed');
            check(!attempt(fn()=>$db->select('secret','hidden')),'Secret query allowed');
        }finally{$db->close();}
    });
}

foreach(range(0,9)as $mode)foreach($actors as $actor){
    testCase('errors',compact('mode','actor'),function()use($mode,$actor){
        $ctl=context($actor,$mode);$ctl->guard=$mode===9;$policy=new MatrixPolicy($ctl,$ctl->channel);
        if($mode<3){
            global $workspace,$fmt;
            $root=$workspace.'/load-'.$mode.'-'.$actor.'/classes';mkdir($root.'/data/test',0770,true);mkdir($root.'/dsp',0770,true);mkdir($root.'/fmt',0770,true);
            file_put_contents($root.'/fmt/sample.fmt',$fmt);
            file_put_contents($root.'/dsp/registry.php',match($mode){0=>"<?php return ['test/sample'=>['file'=>'missing.php','class'=>'Absent']];",1=>"<?php return 'bad';",2=>"<?php syntax !"});
            check(!attempt(fn()=>new fixed_file_manager('sample',$root.'/data/test',$root.'/fmt',['controller'=>$ctl])),'Load error did not stop');return;
        }
        $db=policyFixture('errors',[row(max(1,$ctl->actor),1)],$ctl,$policy);
        try{
            $hash=hash_file('sha256',$db->get_path_dat());
            if ($mode === 9) {
                $policy->hideId = true;
                check(!attempt(fn() => $db->match('name', '予約')), 'match leaked a forbidden ID');
                same(true, $ctl->guard);
                same($hash, hash_file('sha256', $db->get_path_dat()));
                return;
            }
            $policy->error=match($mode){3,4=>'throw',5,6=>'new_db',default=>'nested'};
            $prior=$ctl->guard;
            check(!attempt(match($mode){3,5=>fn()=>$db->get(1),4,6=>fn()=>$db->update(['id'=>1,'name'=>'bad']),default=>fn()=>$db->delete(1)}),'Evaluation error did not stop');
            same($prior,$ctl->guard);same($hash,hash_file('sha256',$db->get_path_dat()));
            $policy->error='';$ctl->guard=false;$ctl->actor=max(1,$ctl->actor);same('予約',$db->get(1)['name']);
        }finally{$ctl->guard=false;$db->close();}
    });
}

// Fifty independent cross-process cases; each child reopens and rechecks under FFM locks.
foreach(range(0,4)as $variant)foreach(range(1,10)as $round){
    testCase('concurrency',compact('variant','round'),function()use($variant,$round){
        global $workspace;
        check(function_exists('pcntl_fork'),'pcntl required');
        $ctl=context(1,$variant);$policy=new MatrixPolicy($ctl,$ctl->channel);
        $db=policyFixture('race',[row(1,1)],$ctl,$policy,(bool)($variant%2));
        $path=$db->get_path_dat();$dir=dirname($path);$fmtDir=dirname($dir).'/fmt';$db->close();
        $children=[];
        for($child=0;$child<2;$child++){
            $pid=pcntl_fork();check($pid>=0,'Fork failed');
            if($pid===0){
                try{
                    $c=context($variant===4&&$child===1?2:1,$variant);$p=new MatrixPolicy($c,$c->channel);
                    $handle=new fixed_file_manager('sample',$dir,$fmtDir,['controller'=>$c,'dsp'=>$p]);
                    if($variant===4&&$child===1){check(!attempt(fn()=>$handle->update(['id'=>1,'score'=>999])),'Foreign concurrent write allowed');}
                    elseif($variant===3){$handle->delete(1);}
                    elseif($variant===2){for($n=0;$n<$round;$n++){$created=row(1,1);$created['name']='child-'.$child.'-'.$n;$handle->insert($created);}}
                    else{for($n=0;$n<$round;$n++){$r=$handle->get(1);$handle->update(['id'=>1,'score'=>$r['score']+1]);}}
                    $handle->close();exit(0);
                }catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(1);}
            }
            $children[]=$pid;
        }
        foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wifexited($status)&&pcntl_wexitstatus($status)===0,'Child failed');}
        $db=new fixed_file_manager('sample',$dir,$fmtDir,['controller'=>$ctl,'dsp'=>$policy]);
        try{
            if($variant===3)same(null,$db->get(1));
            elseif($variant===2){same(1+$round*2,count($db->getall()));same(range(1,1+$round*2),array_column($db->getall('id'),'id'));}
            else{same(1.25+$round*($variant===4?1:2),$db->get(1)['score']);same(1,count($db->select('owner',1)));}
            same($variant===2?1+$round*2:1,$db->get_header_info()['maxid']);
        }finally{$db->close();}
    });
}

fclose($report);
$summary=['total'=>$passed+$failed,'passed'=>$passed,'failed'=>$failed,'groups'=>$counts,'seed'=>'exhaustive-v1'];
file_put_contents($workspace.'/summary.json',json_encode($summary,JSON_PRETTY_PRINT));
echo json_encode($summary)."\n";
exit($failed===0&&$passed===1000?0:1);
