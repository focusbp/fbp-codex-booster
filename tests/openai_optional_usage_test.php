<?php
// Isolated storage regression: no API call, credentials, or application data.
$root = $argv[1] ?? '';
$temporary = $argv[2] ?? '';
if (!$root || !$temporary) throw new RuntimeException('Execution root and temporary directory required');
require $root . '/fbp/interface/openai/OpenAI.php';
require $root . '/fbp/lib/openai/OpenAI_class.php';
class token_usage_tracker {
    function getTotals() { return ['input_tokens'=>10,'output_tokens'=>3,'total_tokens'=>13]; }
}
class UsageFixture {
    public array $rows = [];
    public bool $fail = false;
    function select(...$args) {
        if ($this->fail) throw new RuntimeException('storage failure');
        return $this->rows;
    }
    function insert(&$row) { $row['id']=1; $this->rows=[$row]; }
    function update(&$row) { $this->rows=[$row]; }
}
class Controller {
    public $dirs;
    public $storage;
    public int $calls = 0;
    function __construct($dirs) { $this->dirs=$dirs; $this->storage=new UsageFixture(); }
    function db($table,$owner) {
        if ($table!=='usage'||$owner!=='assistants') throw new RuntimeException('Unexpected storage target');
        $this->calls++; return $this->storage;
    }
}
function check($value,$name) { if (!$value) throw new RuntimeException($name); echo "PASS $name\n"; }
$base=rtrim($temporary,'/').'/optional-usage-'.bin2hex(random_bytes(6));
$dirs=(object)['appdir_user'=>$base.'/app','appdir_fw'=>$base.'/fw'];
$store=new ReflectionMethod(openai\OpenAI_class::class,'store_usage');$store->setAccessible(true);
$tracker=new ReflectionProperty(openai\OpenAI_class::class,'tokenUsageTracker');$tracker->setAccessible(true);
try {
    $ctl=new Controller($dirs);
    $client=new openai\OpenAI_class('',null,'fixture',null,'',[],null,null,null,$ctl);
    $tracker->setValue($client,new token_usage_tracker());
    $store->invoke($client);
    check($ctl->calls===0,'Missing assistants skips storage');
    foreach ([$dirs->appdir_user,$dirs->appdir_fw] as $dir) {
        mkdir($dir.'/assistants',0777,true);
        file_put_contents($dir.'/assistants/assistants.php',"<?php\n");
        $ctl->storage->rows=[];
        $store->invoke($client);$store->invoke($client);
        $row=$ctl->storage->rows[0];
        check($row['in']===20&&$row['out']===6&&$row['total']===26&&$row['count']===2,'Existing assistants keeps monthly storage');
        $ctl->storage->fail=true;
        try { $store->invoke($client); throw new LogicException('Missing storage failure'); }
        catch (RuntimeException $e) { check($e->getMessage()==='storage failure','Real storage failures remain visible'); }
        $ctl->storage->fail=false;
        unlink($dir.'/assistants/assistants.php');rmdir($dir.'/assistants');rmdir($dir);
    }
    $store->invoke(new openai\OpenAI_class(''));
    check(true,'Client without controller skips storage');
} finally {
    foreach ([$dirs->appdir_user,$dirs->appdir_fw] as $dir) {
        @unlink($dir.'/assistants/assistants.php');@rmdir($dir.'/assistants');@rmdir($dir);
    }
    @rmdir($base);
}
