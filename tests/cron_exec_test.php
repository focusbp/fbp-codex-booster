<?php
// Isolated DB fixtures; no application jobs, mail, or production data are used.
interface Controller {}
$framework = getenv('FBP_TEST_FRAMEWORK') ?: __DIR__ . '/../fbp';
putenv('FBP_FFM_LOG_DISABLE=1');
require_once $framework . '/lib/fixed_file_manager/fixed_file_manager.php';
require_once $framework . '/lib/Controller_class.php';
require_once $framework . '/lib/ValueFormatter.php';
require_once $framework . '/app/cron/cron.php';

class Dirs {
    public $datadir;
    function __construct() { $this->datadir = getenv('CRON_TEST_ROOT') . '/data'; }
    function get_class_dir($class) { return getenv('CRON_TEST_ROOT') . '/classes/' . $class; }
}
class CronTestController extends Controller_class {
    public $job;
    public $id = 1;
    public $ajaxCalled = false;
    function __construct() {
        $this->dirs = new Dirs();
        (new ReflectionProperty(Controller_class::class, 'dbarr'))->setValue($this, []);
        (new ReflectionProperty(Controller_class::class, 'class'))->setValue($this, 'cron');
    }
    function GET($key = null) { return ['function'=>'exec', 'id'=>$this->id][$key] ?? null; }
    function POST($key = null) { return $key === '_call_from' ? 'appcon' : null; }
    function decrypt($value) { return $value; }
    function assign($key, $value) {}
    function set_class($class) { (new ReflectionProperty(Controller_class::class, 'class'))->setValue($this, $class); }
    function t($key, $params = [], $lang = null) { return 'SUCCESS'; }
    function create_ValueFormatter(): ValueFormatter { return new ValueFormatter(); }
    function ajax($class, $function, $parameters = null) { $this->ajaxCalled = true; }
}
function getClassObject($ctl, $class, $dirs) {
    return new class { function run($ctl) { ($ctl->job)($ctl); } };
}
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function controller() { return new CronTestController(); }
function seed() {
    $ctl = controller();
    $db = $ctl->db('cron', 'cron');
    $row = ['title'=>'original', 'class_name'=>'job', 'function_name'=>'run', 'min'=>['0']];
    $id = $db->insert($row);
    $ctl->close_all_db();
    return $id;
}
function row($id) {
    $ctl = controller(); $r = $ctl->db('cron', 'cron')->get($id); $ctl->close_all_db(); return $r;
}
function checkReleased() {
    check(empty($GLOBALS['lock_class_arr']), 'FFM locks were not released');
    $fh = fopen(getenv('CRON_TEST_ROOT') . '/data/cron/cron.dat', 'r+');
    check(flock($fh, LOCK_EX | LOCK_NB), 'cron file remains locked');
    fclose($fh);
}

// A separate process exercises real contention and FFM's reversed acquisition order.
if (($argv[1] ?? '') === 'worker') {
    $mode = $argv[2]; $root = getenv('CRON_TEST_ROOT'); $ctl = controller();
    if ($mode === 'other') {
        $ctl->db('a', 'common');
        touch($root . '/other.ready');
        waitFor($root . '/cron.ready');
        $ctl->db('cron', 'cron');
        $ctl->close_all_db();
    } else {
        waitFor($root . '/other.ready');
        $ctl->job = function($ctl) use ($root) {
            touch($root . '/cron.ready');
            $ctl->db('a', 'common');
            $ctl->close_all_db();
        };
        (new cron($ctl))->exec($ctl);
    }
    echo "$mode OK\n";
    exit;
}
function waitFor($file) {
    $deadline = microtime(true) + 5;
    while (!file_exists($file)) {
        if (microtime(true) > $deadline) throw new RuntimeException('Barrier timeout');
        usleep(10000);
    }
}

$root = getenv('CRON_TEST_ROOT');
check($root && is_dir($root), 'Provide an empty CRON_TEST_ROOT directory');
mkdir($root . '/data'); mkdir($root . '/classes');
foreach (['cron','common'] as $class) { mkdir($root . '/classes/' . $class); mkdir($root . '/classes/' . $class . '/fmt'); }
copy($framework . '/app/cron/fmt/cron.fmt', $root . '/classes/cron/fmt/cron.fmt');
file_put_contents($root . '/classes/common/fmt/a.fmt', "id,24,N\nname,30,T\n");
$ctl = controller(); $ctl->db('a','common'); $ctl->close_all_db();

foreach (['normal','close_all','edit','delete','exception','type_error','long_error','missing'] as $mode) {
    $id = seed(); $ctl = controller(); $ctl->id = $mode === 'missing' ? 999999 : $id;
    $error = null;
    $ctl->job = function($ctl) use ($mode, $id, &$error) {
        check($mode !== 'missing', 'Missing job executed');
        $fh = fopen(getenv('CRON_TEST_ROOT') . '/data/cron/cron.dat', 'r+');
        check(!flock($fh, LOCK_EX | LOCK_NB), 'Pre-execution cron lock was released'); fclose($fh);
        $ctl->close_all_db();
        if ($mode === 'edit') {
            $ctl->db('cron','cron')->update(['id'=>$id,'title'=>'edited','min'=>['15']]);
        } elseif ($mode === 'delete') {
            $ctl->db('cron','cron')->delete($id);
        }
        if (in_array($mode, ['exception','type_error','long_error'], true)) {
            $error = $mode === 'type_error' ? new TypeError('job failed') : new RuntimeException($mode === 'long_error' ? str_repeat('失敗', 700) : 'job failed');
            throw $error;
        }
    };
    if ($mode === 'normal') {
        $ctl->job = function($ctl) {
            $GLOBALS['original_cron_db'] = $ctl->db('cron','cron');
        };
    }
    $caught = null;
    try { (new cron($ctl))->exec($ctl); } catch (Throwable $e) { $caught = $e; }
    check($caught === $error, "$mode: original exception not preserved");
    checkReleased();
    $saved = row($id);
    if ($mode === 'delete') check(empty($saved), 'Deleted job recreated');
    elseif ($mode === 'missing') check($saved['last_log'] === '', 'Missing job updated another job');
    else {
        check($error ? str_contains($saved['last_log'], get_class($error)) : str_contains($saved['last_log'], 'SUCCESS'), "$mode: missing log");
        check(strlen($saved['last_log']) <= 1000 && mb_check_encoding($saved['last_log'], 'UTF-8'), 'Invalid log length/encoding');
        if ($mode === 'edit') check($saved['title'] === 'edited' && $saved['min'] === ['15'], 'Settings overwritten');
    }
    check($ctl->ajaxCalled === ($error === null && $mode !== 'missing'), "$mode: unexpected AJAX result");
    echo "$mode OK\n";
}

$processes = [];
try {
    foreach (['other','cron'] as $mode) {
        $pipes = [];
        $p = proc_open([PHP_BINARY, __FILE__, 'worker', $mode], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        check(is_resource($p), 'Could not start worker'); fclose($pipes[0]);
        $processes[] = [$p,$pipes];
    }
    $deadline = microtime(true) + 10;
    foreach ($processes as [$p,$pipes]) {
        do {
            $status = proc_get_status($p);
            if (!$status['running']) break;
            check(microtime(true) < $deadline, 'Concurrent lock acquisition timed out');
            usleep(10000);
        } while (true);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        check($status['exitcode'] === 0, "Worker failed: $err"); echo $out;
    }
} finally {
    foreach ($processes as [$p,$pipes]) {
        $status = proc_get_status($p); if ($status['running']) proc_terminate($p, 9);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    }
}
checkReleased();
echo "cron execution regression checks passed\n";
