<?php

// Real HTTP responses exercise exit(), headers, conditional requests and the
// standard db_exe routes. All files are isolated from real application data.
if (PHP_SAPI === 'cli-server') {
	interface Controller {}
	require_once getenv('FBP_CONTROLLER_FILE') ?: __DIR__ . '/../fbp/lib/Controller_class.php';
	require_once dirname(getenv('FBP_CONTROLLER_FILE') ?: __DIR__ . '/../fbp/lib/Controller_class.php') . '/../app/db_exe/db_exe.php';
	class SavedFileTestController extends Controller_class {
		public function __construct() {}
		public function decrypt($value) { return $value; }
	}
	$ctl = new SavedFileTestController();
	$ctl->dirs = (object) [
		'appdir_user' => getenv('SAVED_FILE_TEST_ROOT') . '/' . ($_GET['fixture'] ?? 'guarded'),
		'datadir' => getenv('SAVED_FILE_TEST_ROOT') . '/data',
	];
	$ctl->set_windowcode('media-test');
	$_SESSION['media-test'] = [
		'owner' => ($_GET['owner'] ?? '') === '1',
		'policy_mode' => $_GET['policy_mode'] ?? '',
	];
	$op = $_GET['op'] ?? 'image';
	$filename = $_GET['file'] ?? 'private.png';
	// Simulate headers established by earlier middleware.
	header('Cache-Control: public, max-age=9999');
	header('ETag: "stale"');
	if ($op === 'db_image') {
		$_GET['function'] = 'view_image';
		$_GET['path'] = $filename;
		$screen = new db_exe($ctl);
		$screen->view_image($ctl);
	} elseif ($op === 'db_download') {
		$_POST = ['function' => 'download_file', 'path' => $filename];
		$screen = new db_exe($ctl);
		$screen->download_file($ctl);
	} elseif ($op === 'download') {
		$ctl->res_saved_file($filename, 'sample.png');
	} else {
		$ctl->res_saved_image($filename, true, 3600, true);
	}
	exit;
}

function media_assert(bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}

function media_remove_tree(string $path): void {
	if (!is_dir($path) || is_link($path)) { unlink($path); return; }
	foreach (array_diff(scandir($path), ['.', '..']) as $name) media_remove_tree($path . '/' . $name);
	rmdir($path);
}

$base = getenv('FBP_TEST_TMP') ?: sys_get_temp_dir();
$root = $base . '/saved-file-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
$process = null;
$checks = 0;
try {
	foreach (['guarded/saved_file_access_guard', 'broken/saved_file_access_guard', 'legacy', 'data/upload'] as $dir) mkdir($root . '/' . $dir, 0700, true);
	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jGZkAAAAASUVORK5CYII=');
	file_put_contents($root . '/data/upload/private.png', $png);
	file_put_contents($root . '/data/upload/private_th.png', $png);
	file_put_contents($root . '/data/upload/public.txt', 'public');
	file_put_contents($root . '/outside.txt', 'outside-secret');
	symlink($root . '/data/upload/private.png', $root . '/data/upload/alias.png');
	symlink($root . '/outside.txt', $root . '/data/upload/outside.txt');
	file_put_contents($root . '/guarded/saved_file_access_guard/saved_file_access_guard.php', <<<'PHP'
<?php
class saved_file_access_guard {
    public function authorize(Controller $ctl, string $filename, string $operation) {
        if ($ctl->get_session('policy_mode') === 'throw') throw new RuntimeException('secret-error');
        if ($ctl->get_session('policy_mode') === 'invalid') return 'yes';
        if ($filename === 'public.txt') return null;
        return in_array($operation, ['image', 'download'], true)
            && in_array($filename, ['private.png', 'private_th.png'], true)
            && $ctl->get_session('owner') === true;
    }
}
PHP);
	file_put_contents($root . '/broken/saved_file_access_guard/saved_file_access_guard.php', '<?php class saved_file_access_guard {}');
	$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
	media_assert($socket !== false, 'Cannot allocate HTTP test port');
	$address = stream_socket_get_name($socket, false);
	fclose($socket);
	$env = getenv();
	$env['SAVED_FILE_TEST_ROOT'] = $root;
	$process = proc_open([PHP_BINARY, '-S', $address, __FILE__], [0 => ['pipe', 'r'], 1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']], $pipes, $root, $env);
	media_assert(is_resource($process), 'Cannot start HTTP test server');
	fclose($pipes[0]);
	$ready = false;
	for ($attempt = 0; $attempt < 50; $attempt++) {
		$client = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
		if ($client) { fclose($client); $ready = true; break; }
		usleep(50000);
	}
	media_assert($ready, 'HTTP test server did not become ready');
	$request = static function (array $query, array $headers = []) use ($address): array {
		$context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5, 'header' => implode("\r\n", $headers)]]);
		$body = file_get_contents('http://' . $address . '/?' . http_build_query($query), false, $context);
		return [$body, implode("\n", $http_response_header)];
	};
	foreach (['image', 'download', 'db_image', 'db_download'] as $op) {
		foreach (['private.png', 'private_th.png', './private.png', './/private.png', 'alias.png'] as $file) {
			[$body, $headers] = $request(['op' => $op, 'file' => $file], ['If-None-Match: "stale"', 'Range: bytes=0-5']);
			media_assert(str_contains($headers, '403 Forbidden') && $body === 'Forbidden', "$op $file denial failed: " . strtok($headers, "\n") . '; private bytes returned=' . ($body === $png ? 'yes' : 'no'));
			media_assert(str_contains($headers, 'no-store') && !str_contains($headers, 'ETag:'), 'Denial was cacheable');
			$checks++;
			[$body, $headers] = $request(['op' => $op, 'file' => $file, 'owner' => 1], ['If-None-Match: "stale"']);
			media_assert(str_contains($headers, '200 OK') && $body === $png, "$op $file rejected owner");
			media_assert(str_contains($headers, 'no-store') && !str_contains($headers, 'immutable') && !str_contains($headers, 'ETag:'), 'Private response was cacheable');
			$checks++;
		}
	}
	foreach (['../outside.txt', 'outside.txt', '/private.png', "private.png\0", 'folder/../private.png', 'folder\\private.png'] as $file) {
		[$body, $headers] = $request(['op' => 'download', 'file' => $file, 'owner' => 1]);
		media_assert(str_contains($headers, '403 Forbidden') && $body === 'Forbidden', 'Unsafe path was not denied');
		$checks++;
	}
	foreach ([['policy_mode' => 'throw'], ['policy_mode' => 'invalid'], ['fixture' => 'broken']] as $query) {
		[$body, $headers] = $request($query + ['owner' => 1]);
		media_assert(str_contains($headers, '403 Forbidden') && $body === 'Forbidden', 'Broken policy did not fail closed');
		$checks++;
	}
	[$body, $headers] = $request(['fixture' => 'legacy']);
	media_assert($body === $png && str_contains($headers, 'public') && str_contains($headers, 'immutable'), 'Unguarded image compatibility changed');
	$checks++;
	[$body, $headers] = $request(['fixture' => 'legacy', 'op' => 'download']);
	media_assert($body === $png && str_contains($headers, '200 OK'), 'Unguarded download compatibility changed');
	$checks++;
	[$body, $headers] = $request(['file' => 'public.txt', 'op' => 'download']);
	media_assert($body === 'public' && str_contains($headers, '200 OK'), 'Explicit public policy compatibility changed');
	$checks++;
	echo "saved file access: $checks HTTP checks passed\n";
} finally {
	if (is_resource($process)) { proc_terminate($process); proc_close($process); }
	media_remove_tree($root);
}
