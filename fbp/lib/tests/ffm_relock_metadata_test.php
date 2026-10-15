<?php
/** Run with an explicit disposable directory; workers use independent PHP processes. */
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__).'/fixed_file_manager/fixed_file_manager.php';
function relockCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function relockWait(string $path): void {
    $deadline = microtime(true) + 10;
    while (!is_file($path)) {
        if (microtime(true) > $deadline) throw new RuntimeException('Worker coordination timed out');
        usleep(10000);
    }
}
class RelockTestFfm extends fixed_file_manager {
    public bool $pauseAfterRelease = false;
    public function closeDatFile() {
        parent::closeDatFile();
        if ($this->pauseAfterRelease) {
            $this->pauseAfterRelease = false;
            $dir = dirname($this->path_dat);
            touch($dir.'/released');
            relockWait($dir.'/writer_done');
        }
    }
}
$dir = $argv[1] ?? '';
relockCheck($dir !== '' && is_dir($dir), 'Supply an existing disposable directory');
$options = ['index_disabled'=>true];
if (($argv[2] ?? '') === 'writer') {
    relockWait($dir.'/released');
    $db = new fixed_file_manager('z', $dir, $dir, $options);
    $row = ['value'=>20];
    relockCheck($db->insert($row) === 2, 'Concurrent writer must allocate ID 2');
    $db->close();
    touch($dir.'/writer_done');
    exit(0);
}
foreach (['write', 'read'] as $mode) {
    $case = $dir.'/'.$mode;
    relockCheck(mkdir($case, 0700), 'Test directory already exists');
    foreach (['a', 'z'] as $name) file_put_contents($case.'/'.$name.'.fmt', "id,24,N\nvalue,24,N\n");
    $a = new fixed_file_manager('a', $case, $case, $options);
    $a->close();
    $seed = new fixed_file_manager('z', $case, $case, $options);
    $row = ['value'=>10];
    relockCheck($seed->insert($row) === 1, 'Initial ID must be 1');
    $seed->close();
    $readOnly = $mode === 'read';
    $z = new RelockTestFfm('z', $case, $case, $options + ['read_only'=>$readOnly]);
    $process = proc_open([PHP_BINARY, __FILE__, $case, 'writer'],
        [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    relockCheck(is_resource($process), 'Could not start concurrent writer');
    fclose($pipes[0]);
    $z->pauseAfterRelease = true;
    // Opening a after z exercises the actual automatic lock-order adjustment.
    $a = new fixed_file_manager('a', $case, $case, $options + ['read_only'=>$readOnly]);
    try {
        if (!$readOnly) {
            $row = ['value'=>30];
            relockCheck($z->insert($row) === 3, 'Reacquired writer reused the concurrent ID');
        }
        $ids = $readOnly ? [1,2] : [1,2,3];
        $rows = $z->get_many($ids);
        relockCheck(array_column($rows, 'id') === $ids, 'Refreshed max ID must expose the concurrent row');
        relockCheck(array_column($rows, 'value') === ($readOnly ? [10,20] : [10,20,30]), 'Record values changed');
        $raw = file_get_contents($case.'/z.dat');
        $headerSize = (int) substr($raw, 28, 16);
        $recordSize = (int) substr($raw, 20, 8);
        $storedIds = [];
        for ($offset=$headerSize; $offset<strlen($raw); $offset+=$recordSize) $storedIds[]=(int)substr($raw,$offset+1,24);
        relockCheck($storedIds === $ids, 'Persisted IDs must be ordered and unique');
    } finally {
        $a->close();
        $z->close();
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        relockCheck(proc_close($process) === 0, 'Concurrent writer failed: '.$stdout.$stderr);
    }
}
echo "PASS: concurrent lock reorder preserves unique IDs, values and read-only visibility\n";
