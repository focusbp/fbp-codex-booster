"""Run against synced test CLI; fixture exists only in the supplied temporary directory."""
import json
from pathlib import Path
import subprocess
import sys
import tempfile

root = Path(sys.argv[1]).resolve()
assert root.parent == Path('/home/nakama/web'), 'Test runtime only'
with tempfile.TemporaryDirectory(dir=sys.argv[2], prefix='method-cli-') as directory:
    fixture = Path(directory) / 'fixture.php'
    fixture.write_text('''<?php
class CliMethodIntegrationFixture {
    private function format($value) { echo "output"; return $value; }
    public static function stop() { echo "not a result"; exit(0); }
    public static function fail() { throw new RuntimeException("expected failure"); }
}
''')
    cases = [
        ({'function': 'format', 'args': [3661], 'expect': 3661}, 'PASS', 0),
        ({'function': 'format', 'args': [3661], 'expect': '3661'}, 'FAIL', 1),
        ({'function': 'format', 'args': [False]}, 'EXECUTED', 0),
        ({'function': 'format', 'args': [None], 'expect': None}, 'PASS', 0),
        ({'function': 'format'}, 'ERROR', 1),
        ({'function': 'missing'}, 'ERROR', 1),
        ({'function': 'stop'}, 'ERROR', 1),
        ({'function': 'fail'}, 'ERROR', 1),
    ]
    for data, status, code in cases:
        data['class'] = 'CliMethodIntegrationFixture'
        payload = Path(directory) / 'case.json'
        payload.write_text(json.dumps(data))
        result = subprocess.run(['php', '-d', 'auto_prepend_file='+str(fixture),
                                 str(root/'fbp/cli.php'), 'method_call', '--json-file='+str(payload)],
                                cwd=root, text=True, capture_output=True, timeout=20)
        actual = json.loads(result.stdout)
        assert result.returncode == code and actual['status'] == status, (data, result.stdout, result.stderr)
        if status in ('PASS', 'FAIL', 'EXECUTED'):
            assert actual['output'] == 'output'
    print('PASS: 8 real CLI cases including private calls, null/false, mismatch, exceptions and exit(0)')
