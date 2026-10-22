<?php
// Offline contracts; no real Vimeo requests, DB writes or framework initialization.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fbp = realpath($argv[1] ?? dirname(__DIR__, 5));
if (!$fbp || !is_dir($fbp . '/interface/chartjs')) throw new RuntimeException('Specify framework fbp root');
$assets = dirname(__DIR__) . '/assets';
require $assets . '/advanced-charts/advanced_chart_sample/advanced_chart_sample.php';
require $assets . '/vcard-qr/vcard_qr_sample/vcard_builder.php';
require $assets . '/vimeo-integration/vimeo_video_template/vimeo_video_template.php';
foreach (['Chart', 'Dataset', 'Dataset_Bar', 'Dataset_Line', 'Scale'] as $name) require_once $fbp . '/interface/chartjs/' . $name . '.php';
foreach (['Chart', 'Dataset', 'Dataset_Bar', 'Dataset_Line', 'Scale'] as $name) require_once $fbp . '/lib/chartjs/' . $name . '_class.php';

$checks = 0;
function check($value, $message) {
    global $checks;
    if (!$value) throw new RuntimeException($message);
    $checks++;
}
function fails(callable $action, $message) {
    try { $action(); } catch (Throwable $e) { check(true, $message); return; }
    throw new RuntimeException($message);
}
check(sample_linear_trend::fit([]) === [], 'empty series');
check(sample_linear_trend::fit([2, 4, 6], 2) === [2.0, 4.0, 6.0, 8.0, 10.0], 'known increasing fit and extension');
check(sample_linear_trend::fit([3, 3]) === [3.0, 3.0], 'constant series');
check(sample_linear_trend::fit([4, 2, 0], 1) === [4.0, 2.0, 0.0, -2.0], 'negative trend');
foreach ([[1], [1, null], [1, INF], [1, NAN], [1, '2']] as $values) fails(fn() => sample_linear_trend::fit($values), 'reject invalid observations');
fails(fn() => sample_linear_trend::fit([1, 2], -1), 'reject invalid horizon');
$payload = sample_vcard_builder::build(['name' => "姓;名,さん\\\nInjected:field", 'phone' => '+81-3-0000-0000']);
check(str_contains($payload, 'VERSION:3.0' . "\r\n") && str_contains($payload, 'N:'), 'required vCard fields');
check(str_contains($payload, 'FN:姓\\;名\\,さん\\\\\\nInjected:field' . "\r\n"), 'text escaping and line injection prevention');
$name = str_repeat('日本語', 15);
$folded = sample_vcard_builder::build(['name' => $name]);
foreach (explode("\r\n", $folded) as $line) check(strlen($line) <= 75 && mb_check_encoding($line, 'UTF-8'), 'UTF-8 octet folding');
$unfolded = str_replace("\r\n ", '', $folded);
check(str_contains($unfolded, 'FN:' . $name . "\r\n"), 'fold round-trip');
fails(fn() => sample_vcard_builder::build(['name' => '']), 'empty name');
fails(fn() => sample_vcard_builder::build(['name' => "A\0B"]), 'control character');
fails(fn() => sample_vcard_builder::build(['name' => 'A', 'note' => str_repeat('x', 1600)]), 'oversized QR');

class Controller {
    public $post = [], $session = [], $vars = [], $charts = [], $notices = [], $uploader = null, $dialogs = [], $closed = [], $user = 'operator';
    function POST($key) { return $this->post[$key] ?? null; }
    function assign($key, $value) { $this->vars[$key] = $value; }
    function random_alphabet($length) { return bin2hex(random_bytes($length / 2)); }
    function create_chart() { return new \chartjs\Chart_class(); }
    function chart_draw($id, $chart) { $this->charts[$id] = $chart->to_array(); }
    function show_dashboard_widget($tpl) { $this->dialogs[] = $tpl; }
    function show_multi_dialog(...$args) { $this->dialogs[] = $args; }
    function close_multi_dialog($id) { $this->closed[] = $id; }
    function show_notification_text($text) { $this->notices[] = $text; }
    function get_setting() { return ['vimeo_access_token' => 'fake-offline-token']; }
    function get_login_user_id() { return $this->user; }
    function set_session($key, $value) { $this->session[$key] = $value; }
    function get_session($key) { return $this->session[$key] ?? null; }
    function show_vimeo_uploader(...$args) { $this->uploader = $args; }
    function get_vimeo_callback_parameter_array() { return $this->post['params'] ?? null; }
    function get_vimeo_id_uploaded() { return $this->post['vimeo_id'] ?? null; }
}
$chartCtl = new Controller();
$charts = new advanced_chart_sample();
$charts->dashboard($chartCtl); $charts->dashboard($chartCtl);
check(count($chartCtl->charts) === 4, 'unique canvas IDs across widgets');
$specs = array_values($chartCtl->charts);
check($specs[0]['data']['datasets'][0]['type'] === 'bar' && $specs[0]['data']['datasets'][1]['type'] === 'line', 'mixed chart types');
check($specs[0]['data']['datasets'][1]['yAxisID'] === 'count' && $specs[0]['options']['scales']['count']['position'] === 'right', 'second axis binding');
check(count($specs[1]['data']['labels']) === count($specs[1]['data']['datasets'][1]['data']), 'regression/label alignment');
check(array_slice($specs[1]['data']['datasets'][0]['data'], -2) === [null, null], 'no invented future observations');

$calls = [];
$owner = '/users/7'; $httpFailure = false;
$api = new sample_vimeo_api('fake-offline-token', function($method, $path, $data) use (&$calls, &$owner, &$httpFailure) {
    $calls[] = [$method, $path, $data];
    if ($httpFailure) return [503, '{}'];
    if ($path === '/me') return [200, json_encode(['uri' => '/users/7'])];
    if ($method === 'GET') return [200, json_encode(['uri' => $path, 'user' => ['uri' => $owner]])];
    return [$method === 'DELETE' ? 204 : 200, '{}'];
});
$api->edit('123', 'Example', 'Text');
check(end($calls)[2] === ['name' => 'Example', 'description' => 'Text'], 'metadata PATCH preserves remote privacy');
$api->delete('123'); check(end($calls)[0] === 'DELETE', 'delete requires 204');
fails(fn() => sample_vimeo_api::id('https://vimeo.com/123'), 'reject arbitrary URL');
$owner = '/users/8'; $calls = [];
fails(fn() => $api->delete('123'), 'foreign remote owner');
check(count(array_filter($calls, fn($c) => $c[0] === 'DELETE')) === 0, 'foreign video not deleted');
$owner = '/users/7';

class VimeoFixture extends vimeo_video_template {
    public $allowed = true, $row = ['id' => 1, 'revision' => 1, 'vimeo_id' => ''], $client;
    protected function canManage(Controller $ctl): bool { return $this->allowed; }
    protected function loadRecord(Controller $ctl, int $id): ?array { return $id === 1 ? $this->row : null; }
    protected function saveVideo(Controller $ctl, array $record, string $id): void { $this->row['vimeo_id'] = $id; $this->row['revision']++; }
    protected function detachVideo(Controller $ctl, array $record): void { $this->row['vimeo_id'] = ''; $this->row['revision']++; }
    protected function api(Controller $ctl): sample_vimeo_api { return $this->client; }
}
$default = new vimeo_video_template(); $ctl = new Controller(); $ctl->post = ['record_id' => 1];
$default->upload($ctl); check($ctl->uploader === null && count($ctl->notices) === 1, 'default access denied before exposing uploader');
$fixture = new VimeoFixture(); $fixture->client = $api; $ctl = new Controller(); $ctl->post = ['record_id' => 1];
$fixture->upload($ctl); $nonce = $ctl->uploader[3]['nonce'];
$ctl->post = ['params' => ['nonce' => $nonce], 'vimeo_id' => '123'];
$fixture->uploaded($ctl); check($fixture->row['vimeo_id'] === '123', 'verified callback saved');
$calls = []; $fixture->uploaded($ctl); check($calls === [], 'callback nonce cannot be replayed');
$ctl->post = ['record_id' => 1]; $fixture->confirm_delete($ctl); $nonce = $ctl->vars['delete_nonce'];
$ctl->post = ['delete_nonce' => $nonce, 'vimeo_id' => '999']; $calls = [];
$fixture->delete_video($ctl);
check($fixture->row['vimeo_id'] === '' && end($calls)[1] === '/videos/123', 'delete uses saved binding, not submitted ID');
$calls = []; $fixture->delete_video($ctl); check($calls === [], 'delete nonce cannot be replayed');
foreach (['different-user', 'stale-record', 'expired', 'revoked'] as $case) {
    $fixture = new VimeoFixture(); $fixture->client = $api; $fixture->row['vimeo_id'] = '123';
    $ctl = new Controller(); $ctl->post = ['record_id' => 1]; $fixture->confirm_delete($ctl);
    $ctl->post = ['delete_nonce' => $ctl->vars['delete_nonce']];
    if ($case === 'different-user') $ctl->user = 'other';
    if ($case === 'stale-record') $fixture->row['revision']++;
    if ($case === 'expired') $ctl->session['vimeo_template_delete']['expires'] = 1;
    if ($case === 'revoked') $fixture->allowed = false;
    $calls = []; $fixture->delete_video($ctl);
    check($calls === [] && $fixture->row['vimeo_id'] === '123', $case . ' cannot delete');
}
$fixture = new VimeoFixture(); $fixture->client = $api; $fixture->row['vimeo_id'] = '123';
$ctl = new Controller(); $ctl->post = ['record_id' => 1]; $fixture->confirm_delete($ctl);
$ctl->post = ['delete_nonce' => $ctl->vars['delete_nonce']]; $httpFailure = true;
$fixture->delete_video($ctl); check($fixture->row['vimeo_id'] === '123', 'HTTP failure keeps local binding');
$httpFailure = false; $calls = []; $fixture->delete_video($ctl); check($calls === [], 'unknown result is not automatically retried');
echo 'PASS ' . $checks . " offline feature-template checks; no Vimeo account accessed.\n";
