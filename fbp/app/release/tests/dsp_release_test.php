<?php
// Isolated archive/deployment unit test; never use an application root as its workspace.
if (PHP_SAPI !== 'cli' || $argc !== 2 || file_exists($argv[1])) exit(2);
interface Controller { public function cron_set(); public function t($key, $values = []); }
class ReleaseFixtureController implements Controller {
    public function cron_set() {}
    public function t($key, $values = []) { return $key; }
}
require_once dirname(__DIR__) . '/ReleaseManager.php';
$root = $argv[1];
mkdir($root . '/source/classes/app/example', 0770, true);
mkdir($root . '/source/classes/app/_dsp', 0770, true);
mkdir($root . '/target/classes/app', 0770, true);
file_put_contents($root . '/source/classes/app/example/example.php', '<?php class example {}');
file_put_contents($root . '/source/classes/app/_dsp/policy.md', 'fixture policy');
file_put_contents($root . '/source/classes/app/_dsp/registry.php', '<?php return [];');
mkdir($root . '/source/classes/dsp', 0770, true);
file_put_contents($root . '/source/classes/dsp/registry.php', '<?php return [];');
$source = new ReleaseManager($root . '/source');
$zipPath = $source->create_release_zip_from_info([]);
$archive = new ZipArchive; $archive->open($zipPath);
if ($archive->getFromName('app/_dsp/policy.md') !== 'fixture policy' || $archive->locateName('app/_dsp/registry.php') === false) throw new Exception('DSP absent from archive');
if ($archive->locateName('dsp/registry.php') !== false) throw new Exception('Old DSP path entered archive');
$archive->close();
$target = new ReleaseManager($root . '/target');
$target->apply_release_zip(new ReleaseFixtureController, $zipPath);
if (file_get_contents($root . '/target/classes/app/_dsp/policy.md') !== 'fixture policy') throw new Exception('DSP payload not deployed');
unlink($root . '/source/classes/app/_dsp/policy.md'); unlink($root . '/source/classes/app/_dsp/registry.php'); rmdir($root . '/source/classes/app/_dsp');
$oldArchive = $source->create_release_zip_from_info([]);
$target->apply_release_zip(new ReleaseFixtureController, $oldArchive);
if (file_exists($root . '/target/classes/app/_dsp')) throw new Exception('Application replacement retained removed DSP');
echo "PASS: DSP archive inclusion, fixture deployment and normal application replacement\n";
