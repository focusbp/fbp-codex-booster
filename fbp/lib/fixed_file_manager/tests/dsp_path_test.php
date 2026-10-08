<?php
/** Registry location regression, using only a new disposable workspace. */
if (PHP_SAPI !== 'cli' || $argc !== 2 || file_exists($argv[1])) exit(2);
interface Controller {}
final class PathController implements Controller {}
require_once dirname(__DIR__, 2) . '/DspRuntime.php';
$root = $argv[1];
mkdir($root . '/classes/data/_common', 0770, true);
mkdir($root . '/classes/dsp', 0770, true);
file_put_contents($root . '/classes/dsp/registry.php', '<?php throw new RuntimeException("Old registry must not load");');
$ctl = new PathController;
$resolve = fn() => DspRuntime::resolve($root . '/classes/data/_common', 'example', $ctl, 'cli', 'common');
if ($resolve() !== null) throw new RuntimeException('Old path activated a policy');
mkdir($root . '/classes/app/_dsp', 0770, true);
file_put_contents($root . '/classes/app/_dsp/registry.php', '<?php return ["common/example"=>["file"=>"PathPolicy.php","class"=>"PathPolicy"]];');
file_put_contents($root . '/classes/app/_dsp/PathPolicy.php', <<<'CODE'
<?php
final class PathPolicy implements DspInterface {
    public function __construct(Controller $ctl, string $channel) {}
    public function authorizeInsert(array $row): void {}
    public function authorizeRead(array $request): void {}
    public function inspectRead(array $row): DspReadDecision { return new DspReadDecision(true, ['id']); }
    public function authorizeUpdate(array $before, array $after, array $fields): void {}
    public function authorizeDelete(array $before): void {}
}
CODE);
if (!$resolve() instanceof PathPolicy) throw new RuntimeException('New registry was not loaded');
if (DspRuntime::resolve($root . '/classes/data/_common', 'undefined', $ctl, 'cli', 'common') !== null)
    throw new RuntimeException('Undefined DB acquired a policy');
unlink($root . '/classes/app/_dsp/PathPolicy.php');
try { $resolve(); throw new RuntimeException('Registered failure did not stop'); }
catch (DspException $expected) {}
echo "PASS: new path, old path ignored, undefined compatibility, load failure stops\n";
