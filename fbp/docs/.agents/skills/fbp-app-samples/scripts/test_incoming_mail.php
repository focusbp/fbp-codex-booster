<?php
// Offline guards and installer checks. No framework bootstrap, credentials, or networking.
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_dir($argv[1]) || glob($argv[1] . '/*')) exit(2);
interface Controller {}
interface CodegenActionInterface {}
$root = dirname(__DIR__) . '/assets/incoming-mail';
$manifest = json_decode(file_get_contents($root . '/incoming-mail.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($manifest['files'] as $file) if (str_ends_with($file, '.php')) require_once $root . '/' . $file;
final class SampleMailGuardController implements Controller {
    public bool $admin = false;
    public array $post = [];
    function get_session($key) { return $key === 'login'; }
    function is_app_admin() { return $this->admin; }
    function POST($key = null) { return $key === null ? $this->post : ($this->post[$key] ?? null); }
    function get_dsp_system_subject() { return null; }
}
$count = 0;
function check($v) { global $count; if (!$v) throw new RuntimeException('Sample check failed'); ++$count; }
$ctl = new SampleMailGuardController();
$accounts = new sample_mail_accounts_visibility_filter(); $messages = new sample_mail_messages_visibility_filter();
check(!$accounts->can_access($ctl, 'page')); check(!$messages->can_access($ctl, 'page'));
foreach (['run', 'configure', 'save', 'cron'] as $method) {
    try { (new sample_mail_import())->$method($ctl); throw new LogicException('Unauthorized action accepted'); }
    catch (RuntimeException $e) { ++$count; }
}
$ctl->admin = true;
check($accounts->can_access($ctl, 'page')); check($messages->can_access($ctl, 'page'));
foreach (['add_exe', 'edit_exe', 'delete_exe', 'duplicate'] as $action) check(!$messages->can_access($ctl, $action));
foreach (['receive_state', 'receive_password', 'last_error'] as $field) { $ctl->post = [$field => 'forged']; check(!$accounts->can_access($ctl, 'edit_exe')); }
$ctl->post = ['title' => 'fixture', 'enabled' => '1']; check($accounts->can_access($ctl, 'edit_exe'));
mkdir($argv[1] . '/classes/app', 0700, true);
$installer = __DIR__ . '/install_incoming_mail.php';
$command = [PHP_BINARY, $installer, '--code-root=' . realpath($argv[1])];
function install(array $command): array {
    $p = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out, $err];
}
[$exit, $output] = install($command); check($exit === 0 && json_decode($output, true)['data_inserted'] === 0);
foreach ($manifest['files'] as $file) check(hash_file('sha256', $argv[1] . '/' . $file) === hash_file('sha256', $root . '/' . $file));
[$exit] = install($command); check($exit !== 0);
echo "PASS $count incoming sample checks: guards, forged fields, install and overwrite rejection\n";
