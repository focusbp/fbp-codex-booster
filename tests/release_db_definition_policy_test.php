<?php
class Controller { function t($key, $params=[]) { return $key; } function cron_set() {} function get_setting() { return ['project_release_code'=>'app-target']; } }
require_once __DIR__ . '/../fbp/app/release/ReleaseManager.php';
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function removeTree($path) { if (!is_dir($path)) return; foreach (new FilesystemIterator($path) as $f) { if ($f->isDir()) removeTree($f->getPathname()); else unlink($f->getPathname()); } rmdir($path); }
$base = __DIR__ . '/tmp-release-db-' . bin2hex(random_bytes(5));
try {
	foreach (['legacy', 'off', 'on', 'off_included'] as $mode) {
		$source = $base . '/' . $mode . '/source'; $target = $base . '/' . $mode . '/target';
		foreach ([$source, $target] as $path) { mkdir($path . '/classes/app', 0777, true); mkdir($path . '/classes/data/db', 0777, true); mkdir($path . '/classes/data/_common/fmt', 0777, true); file_put_contents($path . '/classes/app/current.php', 'current'); }
		file_put_contents($source . '/classes/data/db/db.dat', 'SOURCE_DB'); file_put_contents($source . '/classes/data/_common/fmt/notes.fmt', 'SOURCE_FMT');
		file_put_contents($target . '/classes/data/db/db.dat', 'TARGET_DB'); file_put_contents($target . '/classes/data/db/local.dat', 'LOCAL_DB'); file_put_contents($target . '/classes/data/_common/fmt/notes.fmt', 'TARGET_FMT');
		$info = ['project_release_code'=>'app-target', 'type'=>'release']; if ($mode !== 'legacy') $info['deploy_db_definitions'] = !str_starts_with($mode, 'off');
		$zipPath = $base . '/' . $mode . '/payload.zip'; (new ReleaseManager($source, $zipPath))->create_release_zip_from_info($info);
		$zip = new ZipArchive(); $zip->open($zipPath); check(($zip->locateName('data/db/db.dat') !== false) === !str_starts_with($mode, 'off'), 'sender DB policy'); check(($zip->locateName('data/_common/fmt/notes.fmt') !== false) === !str_starts_with($mode, 'off'), 'sender fmt policy'); if ($mode === 'off_included') { $zip->addFromString('data/db/db.dat', 'MUST_NOT_DEPLOY'); $zip->addFromString('data/_common/fmt/notes.fmt', 'MUST_NOT_DEPLOY'); } if ($mode === 'legacy') $zip->addFromString('info.json', json_encode(['project_release_code'=>'app-target','type'=>'release'])); $zip->close();
		$receiver = new ReleaseManager($target); $receiver->validate_release_zip(new Controller(), $zipPath); (new ReleaseManager($target))->apply_release_zip(new Controller(), $zipPath);
		$off = str_starts_with($mode, 'off'); check(file_get_contents($target . '/classes/data/db/db.dat') === ($off ? 'TARGET_DB' : 'SOURCE_DB'), 'DB changed incorrectly'); check(file_exists($target . '/classes/data/db/local.dat') === $off, 'DB deletion policy'); check(file_get_contents($target . '/classes/data/_common/fmt/notes.fmt') === ($off ? 'TARGET_FMT' : 'SOURCE_FMT'), 'fmt changed incorrectly');
	}
	mkdir($base . '/invalid/classes', 0777, true);
	try { (new ReleaseManager($base . '/invalid', $base . '/bad.zip'))->create_release_zip_from_info(['deploy_db_definitions'=>'invalid']); throw new LogicException('accepted invalid DB policy'); } catch (RuntimeException $expected) {}
	echo "Release DB definition policy: sender, receiver, fmt preservation and invalid flag passed\n";
} finally { removeTree($base); }
