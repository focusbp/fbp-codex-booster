#!/usr/bin/env python3
"""Verify optional list ID prefixes against a supplied test web runtime; clean fixtures."""
import json
import os
from pathlib import Path
import subprocess
import sys

root = Path(sys.argv[1]).resolve()
assert '/web/' in str(root), 'A test web runtime is required.'
name = 'identifier_test_' + str(os.getpid())
note = field = screen = record = None


def cli(command, data, fail=False):
    result = subprocess.run(['php', 'fbp/cli.php', command, '--json=' + json.dumps(data)],
                            cwd=root, capture_output=True, text=True, timeout=30)
    if fail:
        assert result.returncode != 0, result.stdout
        return
    assert result.returncode == 0, (result.stdout, result.stderr)
    return json.loads(result.stdout)


def call(cls, fn, post):
    return cli('app_call', {'class': cls, 'function': fn, 'post': post})['response_json']


def html():
    return json.dumps(call('db_exe', 'rows', {'db_id': note}), ensure_ascii=False)


try:
    note = cli('db_tables_add', {'tb_name': name, 'menu_name': 'Identifier test', 'show_menu': 0,
                                'show_id': 1, 'list_type': 0, 'sortkey': 'id', 'sort_order': 3,
                                'list_width': 800, 'edit_width': 800})['id']
    field = cli('db_fields_add', {'db_id': note, 'parameter_name': 'title', 'parameter_title': 'Title',
                                 'type': 'text', 'length': 80})['id']
    screen = cli('screen_fields_add', {'tb_name': name, 'screen_name': 'list', 'parameter_name': 'title'})['id']
    record = cli('data_add', {'table': name, 'data': {'title': 'Identifier test'}})['id']
    assert f'<p>{record}</p>' in html(), 'unset prefix keeps numeric ID'
    for kind in [0, 1]:
        cli('db_tables_edit', {'id': note, 'list_type': kind, 'identifier_prefix': 'Example-1_test'})
        assert f'[Example-1_test:{record}]' in html(), 'both list patterns render prefix'
    cli('db_tables_edit', {'id': note, 'show_id': 0})
    assert '[Example-1_test:' not in html(), 'hidden ID stays hidden'
    cli('db_tables_edit', {'id': note, 'show_id': 1, 'identifier_prefix': ''})
    assert f'<p>{record}</p>' in html(), 'clear restores numeric ID'
    for invalid in ['<script>', 'a:b', 'a b', '1test', 'a' * 65, ['bad']]:
        cli('db_tables_edit', {'id': note, 'identifier_prefix': invalid}, fail=True)
    response = call('db', 'edit_silent_exe', {'id': note, 'identifier_prefix': 'invalid:prefix'})
    assert response.get('errormessage'), 'UI rejects invalid prefix'
    call('db', 'edit_exe', {'id': note, 'tb_name': name, 'identifier_prefix': 'Saved'})
    assert f'[Saved:{record}]' in html(), 'management form saves prefix'
    response = call('db', 'edit', {'id': note, 'mode': 'database'})
    assert 'identifier_prefix' in json.dumps(response), 'management form exposes setting'
    check = cli('standard_screen_check', {'tb_name': name})
    assert check['summary']['ERROR'] == 0
    print('PASS: list/manual sort, unset/clear/hidden ID, CLI and UI validation, UI save/read-back, standard screen check')
finally:
    if record:
        cli('data_delete', {'table': name, 'id': record})
    if screen:
        cli('screen_fields_delete', {'tb_name': name, 'screen_name': 'list', 'id': screen})
    if field:
        cli('db_fields_delete', {'id': field})
    if note:
        call('db', 'delete_exe', {'id': note})
