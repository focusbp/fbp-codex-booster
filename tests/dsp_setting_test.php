<?php
/** Run with the synchronized framework root and a new disposable workspace. */
if (PHP_SAPI !== 'cli' || $argc !== 3 || file_exists($argv[2])) exit(2);
interface Controller {}
class Controller_class implements Controller {
    public array $setting = [];
    public int $dependencies = 0;
    public bool $guard = false;
    public string $channel = 'admin';
    public static function getInstance() { return null; }
    public function get_session($key) { return $key === 'setting' ? $this->setting : null; }
    public function get_dsp_channel() { return $this->channel; }
    public function freeze_dsp_channel() {}
    public function get_prohibit_new_db() { return $this->guard; }
    public function set_prohibit_new_db($value) { $this->guard = $value; }
    public function encrypt($value) { return 'fixture-' . $value; }
    public function db(...$args) { $this->dependencies++; }
}
require $argv[1] . '/fbp/lib/fixed_file_manager/fixed_file_manager.php';
$root = $argv[2]; $dir = $root . '/classes/data/common'; $fmt = $root . '/fmt';
mkdir($dir, 0770, true); mkdir($fmt, 0770, true);
file_put_contents($fmt . '/example.fmt', "id,12,N\nvalue,30,T\n");
$seed = new fixed_file_manager('example', $dir, $fmt);
$row = ['value' => 'fixture']; $seed->insert($row); $seed->close();
mkdir($root . '/classes/app/_dsp', 0770, true);
file_put_contents($root . '/classes/app/_dsp/registry.php', '<?php return ["common/example"=>["file"=>"TogglePolicy.php","class"=>"TogglePolicy","dependencies"=>[["table"=>"dependency"]]]];');
file_put_contents($root . '/classes/app/_dsp/TogglePolicy.php', <<<'PHP'
<?php
class TogglePolicy implements DspInterface {
    public function __construct(Controller $ctl, string $channel) {}
    private function deny(): void { throw new DspException('toggle_fixture', 'denied'); }
    public function authorizeInsert(array $r): void { $this->deny(); }
    public function authorizeRead(array $r): void { $this->deny(); }
    public function inspectRead(array $r): DspReadDecision { $this->deny(); }
    public function authorizeUpdate(array $a, array $b, array $f): void { $this->deny(); }
    public function authorizeDelete(array $r): void { $this->deny(); }
}
PHP);
$checks = 0;
function check($ok, $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function denied(callable $action): void {
    try { $action(); } catch (DspException $e) { check($e->getDetails()['reason'] === 'denied', 'Expected policy refusal'); return; }
    throw new RuntimeException('Expected DSP refusal');
}
$ctl = new Controller_class;
$open = fn($options = []) => new fixed_file_manager('example', $dir, $fmt, ['controller' => $ctl, 'database_class' => 'common'] + $options);
foreach ([null, 0, '0', '', 2, true, [], 'OFF'] as $value) {
    $ctl->setting = $value === null ? [] : ['dsp_disabled' => $value];
    $db = $open(); denied(fn() => $db->get(1)); check(!$ctl->guard, 'Guard restored'); $db->close();
}
$_GET['dsp_disabled'] = $_POST['dsp_disabled'] = '1';
$ctl->setting = []; $db = $open(); denied(fn() => $db->get(1));
$new = ['value' => 'forbidden']; denied(function () use ($db, &$new) { $db->insert($new); });
$update = ['id' => 1, 'value' => 'forbidden']; denied(function () use ($db, &$update) { $db->update($update); });
denied(fn() => $db->delete(1)); $db->close();
foreach (['admin', 'public', 'mcp', 'api', 'cron', 'cli'] as $channel) {
    $ctl->channel = $channel; $ctl->setting = ['dsp_disabled' => '1']; $ctl->dependencies = 0;
    $db = $open(); check($ctl->dependencies === 0, 'OFF must not open dependencies');
    check($db->get(1)['value'] === 'fixture', 'OFF read');
    $new = ['value' => 'added']; $db->insert($new);
    $update = ['id' => $new['id'], 'value' => 'updated']; $db->update($update);
    check($db->get($new['id'])['value'] === 'updated', 'OFF insert/update');
    $db->delete($new['id']); check($db->get($new['id']) === null, 'OFF delete'); $db->close();
}
$ctl->setting = ['dsp_disabled' => 0]; $db = $open(); denied(fn() => $db->get(1)); $db->close();
$ctl->setting = ['dsp_disabled' => 1];
$db = $open(['dsp' => new TogglePolicy($ctl, 'admin')]); check($db->get(1)['value'] === 'fixture', 'OFF explicit policy'); $db->close();
$bad = $root . '/broken/classes/data/common'; mkdir($bad, 0770, true);
mkdir($root . '/broken/classes/app/_dsp', 0770, true);
file_put_contents($root . '/broken/classes/app/_dsp/registry.php', '<?php throw new RuntimeException("broken");');
check(DspRuntime::resolve($bad, 'example', $ctl, 'admin', 'common') === null, 'OFF avoids registry loading');
$ctl->setting = ['dsp_disabled' => 0];
try { DspRuntime::resolve($bad, 'example', $ctl, 'admin', 'common'); throw new RuntimeException('ON failed open'); }
catch (DspException $e) { check(true, 'ON fails closed'); }
echo "PASS: $checks checks; default ON, tampering ignored, all channels/CRUD OFF, no dependencies, ON restoration, explicit policies and broken registry\n";
