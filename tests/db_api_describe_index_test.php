<?php
// Exercise the API parser in child processes because invalid formats exit with JSON.
if (($argv[1] ?? '') === 'child') {
    require __DIR__ . '/../fbp/app/db_api/db_api.php';
    $api = (new ReflectionClass('db_api'))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($api, 'parse_fmt_fields');
    $method->setAccessible(true);
    echo json_encode(['fields' => $method->invoke($api, $argv[2])]);
    exit;
}
$fixture = tempnam(__DIR__, 'fmt-index-');
try {
    $cases = [
        ['id,24,N\nparent_id,24,N\n', [false, false]],
        ['id,24,N,IDX\nparent_id,24,N,IDX\nname,60,T, IDX \n', [false, true, true]],
        ['parent_id,24,N,OTHER\n', null],
        ['parent_id,24,N,\n', null],
        ['parent_id,24,N,idx\n', null],
        ['parent_id,24,N,IDX,OTHER\n', null],
        ['parent_id,24\n', null],
    ];
    foreach ($cases as [$format, $expected]) {
        file_put_contents($fixture, str_replace('\\n', "\n", $format));
        $process = proc_open([PHP_BINARY, __FILE__, 'child', $fixture], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $result = json_decode(stream_get_contents($pipes[1]), true);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0 || $error !== '') throw new RuntimeException($error);
        if ($expected === null) {
            if (($result['error_code'] ?? '') !== 'invalid_fmt') throw new RuntimeException('Invalid definition accepted');
        } else {
            if (array_column($result['fields'] ?? [], 'indexed') !== $expected) throw new RuntimeException('Index flags do not match FFM');
            if ($result['fields'][1]['name'] !== 'parent_id' || $result['fields'][1]['size'] !== 24 || $result['fields'][1]['type'] !== 'N') throw new RuntimeException('Existing field metadata changed');
        }
    }
    echo "DB API describe: 7 format cases passed\n";
} finally {
    unlink($fixture);
}
