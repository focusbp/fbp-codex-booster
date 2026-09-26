<?php
// Run from a test web runtime, passing an isolated temporary directory.
require_once __DIR__ . '/../../../lib/fixed_file_manager/fixed_file_manager.php';
require_once __DIR__ . '/../../../lib/ExternalKeys.php';
require_once __DIR__ . '/../../release/ReleaseManager.php';
$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root) || count(scandir($root)) !== 2) throw new RuntimeException('An empty test directory is required.');
mkdir($root . '/classes/data', 0700, true);
$db = new fixed_file_manager('external_keys', $root . '/classes/data/external_keys', __DIR__ . '/../fmt');
$store = new ExternalKeys($db);
$checks = 0;
function check($ok, string $name): void { global $checks; if (!$ok) throw new RuntimeException($name); $checks++; }
$value = '  00123-secret-test-12345  ';
$id = $store->save(0, 'Sample_Key', '表示名', $value);
check($store->get('Sample_Key') === $value, 'Whitespace and numeric prefix preservation');
check($store->get('sample_key') === null, 'Case-sensitive lookup');
check($store->get('missing') === null, 'Missing value');
check(!array_key_exists('value', $store->metadata()[0]), 'Metadata excludes secret');
check(count($store->metadata('表示')) === 1, 'Title search');
check(count($store->metadata('Sample')) === 1, 'Key search');
check(count($store->metadata('secret-test')) === 0, 'No secret search');
check(isset($store->validate(0, 'Sample_Key', 'Duplicate', 'x')['key']), 'Duplicate rejection');
foreach (['', '1bad', 'has space', 'a.b', str_repeat('a', 256)] as $bad) check(isset($store->validate(0, $bad, 'title', 'x')['key']), 'Invalid key');
check(isset($store->validate(0, 'Valid', '', 'x')['title']), 'Required title');
check(isset($store->validate(0, 'Valid', str_repeat('名', 86), 'x')['title']), 'Title byte limit');
check(isset($store->validate(0, 'Valid', 'title', '')['external_key_secret']), 'Required initial value');
check(isset($store->validate(0, 'Valid', 'title', str_repeat('x', 8193))['external_key_secret']), 'Value byte limit');
check(isset($store->validate(99999, 'Valid', 'title', 'x')['id']), 'Unknown edit ID');
$store->save($id, 'Sample_Key', 'Changed title', '');
check($store->get('Sample_Key') === $value, 'Blank edit keeps value');
$long = str_repeat('x', 8192);
$store->save($id, 'Sample_Key', 'Changed title', $long);
check($store->get('Sample_Key') === $long, '8192 byte roundtrip');
$store->save(0, str_repeat('K', 255), str_repeat('名', 85), "00123\nline2\t ");
check($store->get(str_repeat('K', 255)) === "00123\nline2\t ", 'Boundaries and multiline roundtrip');
$store->delete($id);
check($store->get('Sample_Key') === null, 'Delete');
$log = file_get_contents($root . '/classes/log/ffm/' . date('Ymd') . '.jsonl');
check(strpos($log, $value) === false && strpos($log, base64_encode($value)) === false, 'Logs contain no raw or encoded value');
check(strpos($log, '[masked]') !== false, 'Logs mask value');
$release = (new ReflectionClass(ReleaseManager::class))->getDefaultProperties();
check(!isset($release['db_file_copy_list']['external_keys']), 'External key data excluded from releases');
check(!in_array('external_keys', $release['db_copy_list'], true), 'No directory-wide backup export');
$releaseReflection = new ReflectionClass(ReleaseManager::class);
$excluded = $releaseReflection->getMethod('isExcludedArchivePath');
$releasePolicy = $releaseReflection->newInstanceWithoutConstructor();
check($excluded->invoke($releasePolicy, 'data/external_keys/external_keys.dat'), 'Ignore secret data in older release archives');
check($excluded->invoke($releasePolicy, 'data/external_keys/backup.dat'), 'Ignore entire secret data directory on receive');
check(!$excluded->invoke($releasePolicy, 'data/constant_array/constant_array.dat'), 'Unrelated release data remains eligible');
check($excluded->invoke($releasePolicy, 'data/project_integration/items.dat'), 'Do not deploy queued settings to another environment');
check($excluded->invoke($releasePolicy, 'data/integration_settings/receipts.dat'), 'Apply receipts remain environment-local');
$settingsDb = new fixed_file_manager('setting', $root . '/classes/data/setting', __DIR__ . '/../../setting/fmt');
$settingRow = ['smtp_password'=>'fixture-smtp-secret','chatgpt_api_key'=>'fixture-ai-secret'];
$settingsDb->insert($settingRow);
$settingLog = file_get_contents($root . '/classes/log/ffm/' . date('Ymd') . '.jsonl');
check(!str_contains($settingLog, 'fixture-smtp-secret') && !str_contains($settingLog, 'fixture-ai-secret'), 'Settings secrets omitted from operation log');
$snapshotMethod = (new ReflectionClass($settingsDb))->getMethod('snapshot_dat_file');
check(!$snapshotMethod->invoke($settingsDb, 'test')['created'], 'Settings secret snapshot omitted');
$settingsDb->close();
require_once __DIR__ . '/../../../interface/Controller.php';
require_once __DIR__ . '/../../../lib/Controller_class.php';
class ExternalKeyTestController extends Controller_class {
    public FFM $testDb;
    public function __construct(FFM $db, string $root) {
        $this->testDb = $db;
        $this->dirs = (object) ['appdir_user' => $root];
    }
    public function db($name, ?string $class = null, ?string $separated_by = null): FFM { return $this->testDb; }
}
$ctl = new ExternalKeyTestController($db, $root);
check($ctl->get_external_key(str_repeat('K', 255)) === "00123\nline2\t ", 'Controller optional getter');
check($ctl->require_external_key(str_repeat('K', 255)) === "00123\nline2\t ", 'Controller required getter');
try { $ctl->require_external_key('Missing'); check(false, 'Required missing must fail'); }
catch (RuntimeException $e) { check(strpos($e->getMessage(), $value) === false, 'Missing exception excludes secrets'); }
$window = new ReflectionProperty(Controller_class::class, 'windowcode');
$window->setValue($ctl, 'external-key-test');
foreach ([['app_admin' => 1], ['developer_permission' => 1], [], ['data_manager_permission' => 1]] as $i => $role) {
    $_SESSION['external-key-test'] = $role;
    foreach (['page', 'add', 'edit', 'save', 'delete', 'delete_exe'] as $fn) {
        check($ctl->authorize_management_access('external_keys', $fn) === ($i === 0), 'Management permission on ' . $fn);
    }
}
$db->close();
echo "PASS: $checks external key storage/security/release-policy checks\n";
