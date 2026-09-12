#!/usr/bin/env python3
"""Integration regression against an explicitly supplied disposable/test FBP web root.
Creates unique notes through CLI, verifies single-record behavior, removes fixture definitions.
Usage: python3 single_record_test.py /path/to/test-app
"""
import concurrent.futures
import json
import os
from pathlib import Path
import subprocess
import sys
import time

root = Path(sys.argv[1]).resolve()
assert '/projects/' not in str(root), 'Run against a test runtime, never source.'
prefix = 'single_test_' + str(os.getpid()) + '_' + str(int(time.time()))
notes, fields, screens, buttons = [], [], [], []
checks = 0


def cli(command, data=None, fail=False, concurrent=False):
    env = os.environ.copy()
    if concurrent:
        env['FBP_CLI_NO_LOCK'] = '1'
    result = subprocess.run(['php', 'fbp/cli.php', command, '--json=' + json.dumps(data or {})],
                            cwd=root, env=env, capture_output=True, text=True, timeout=30)
    if fail:
        assert result.returncode != 0, (command, result.stdout)
        return result.stderr
    assert result.returncode == 0, (command, result.stdout, result.stderr)
    output = json.loads(result.stdout)
    assert output.get('ok', True), output
    return output


def check(condition, description):
    global checks
    assert condition, description
    checks += 1
    print('PASS', description, flush=True)


def call(function, post=None, classname='db_exe', concurrent=False, session='main'):
    return cli('app_call', {'class': classname, 'function': function,
                           'windowcode': prefix + session, 'post': dict(post or {})},
               concurrent=concurrent)['response_json']


def rows():
    return cli('data_list', {'table': prefix, 'max': 10})['items']


def saved(response):
    return any('保存しました' in n['html'] or '>Saved<' in n['html'] for n in response.get('notifications', []))


try:
    for name, kind in [(prefix, 3), (prefix + '_other', 0)]:
        notes.append(cli('db_tables_add', {'tb_name': name, 'list_type': kind, 'screen_build_type': 0,
                                          'show_menu': 0, 'menu_name': name, 'list_width': 800, 'edit_width': 800})['id'])
    note = notes[0]
    for name, kind, default in [('name', 'text', 'Default'), ('flags', 'checkbox', ''),
                                 ('month', 'year_month', ''), ('internal', 'text', 'protected')]:
        field = cli('db_fields_add', {'db_id': note, 'parameter_name': name, 'parameter_title': name,
                                     'type': kind, 'length': 150, 'default_value': default, 'validation': int(name == 'name')})['id']
        fields.append(field)
        if name != 'internal':
            screens.append(cli('screen_fields_add', {'tb_name': prefix, 'screen_name': 'edit', 'parameter_name': name})['id'])
    response = call('page', {'db_id': note})
    html = response['work_area']['html']
    check('single_record_form' in html and 'Default' in html and 'search_box' not in html, 'empty note displays main form and defaults')
    check(rows() == [], 'opening the screen does not insert a row')
    check(cli('standard_screen_check', {'tb_name': prefix})['summary'] == {'ERROR': 0, 'WARN': 0, 'INFO': 0}, 'checker requires edit fields only')
    response = call('save_single_exe', {'db_id': note, 'name': ''})
    check(bool(response.get('errormessage')) and not saved(response) and not rows(), 'invalid first save does not insert or notify success')
    # Distinct PHP sessions and no global CLI lock: exercise the actual FFM locking.
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        futures = [pool.submit(call, 'save_single_exe', {'db_id': note, 'name': 'First ' + str(i), 'flags': ['a'], 'month': '2026-09'}, concurrent=True, session=str(i)) for i in range(2)]
        results = [future.result() for future in futures]
    check(all(saved(result) for result in results) and len(rows()) == 1, 'simultaneous first saves keep exactly one record')
    record_id = rows()[0]['id']
    response = call('save_single_exe', {'db_id': note, 'id': 999999, 'name': 'Updated', 'internal': 'tampered'})
    record = rows()[0]
    check(saved(response) and record['id'] == record_id and record['name'] == 'Updated', 'update selects the stored ID and not the posted ID')
    check(record['internal'] == 'protected' and record['flags'] == [], 'hidden fields are preserved and unchecked checkboxes clear')
    for function in ['add', 'add_exe', 'edit', 'edit_exe', 'duplicate', 'delete', 'delete_exe', 'rows', 'rows_child', 'add_child_exe', 'edit_child_exe', 'delete_child_exe', 'manual_sort', 'rows_weekly_calendar', 'edit_datetime_exe']:
        response = call(function, {'db_id': note, 'id': record_id, 'name': 'Illegal'})
        check(bool(response.get('notifications')) and rows() == [record], 'direct ' + function + ' is blocked')
    button = {'tb_name': prefix, 'place': 0, 'button_title': 'Top action', 'class_name': prefix + '_action', 'function_name': 'run', 'dialog_width': 800}
    buttons.append(cli('db_additionals_add', button)['id'])
    check('Top action' in call('page', {'db_id': note})['work_area']['html'], 'top button is rendered')
    for place in [1, 2, 3]:
        cli('db_additionals_add', dict(button, place=place), fail=True)
        cli('db_additionals_edit', {'id': buttons[0], 'place': place}, fail=True)
        response = call('add_exe', dict(button, place=place, class_name=prefix + '_bad' + str(place)), 'db_additionals')
        check(bool(response.get('errormessage')), 'UI and CLI reject button place ' + str(place))
    response = call('edit_exe', dict(button, id=buttons[0], place=1), 'db_additionals')
    check(bool(response.get('errormessage')), 'UI rejects editing a top button into a row button')
    response = call('button_sort_exe', {'tb_name': prefix, 'groups_json': json.dumps({'1': buttons})}, 'db_additionals')
    check(bool(response.get('errormessage')) and next(b for b in cli('db_additionals_list')['items'] if b['id'] == buttons[0])['place'] == 0, 'drag placement cannot move a top button to a row')
    cli('db_tables_edit', {'id': note, 'parent_tb_id': notes[1]}, fail=True)
    check(True, 'single record cannot become a child note')
    cli('data_delete', {'table': prefix, 'id': record_id})
    record_id = cli('data_add', {'table': prefix, 'data': {'name': 'Seed'}})['id']
    response = call('save_single_exe', {'db_id': note, 'name': 'Updated'})
    check(record_id > 1 and rows()[0]['id'] == record_id and saved(response), 'existing IDs other than 1 are supported')
    # Generic data CLI is intentionally outside the screen constraint; use it to inject corrupt data.
    extra = cli('data_add', {'table': prefix, 'data': {'name': 'Unexpected'}})['id']
    response = call('save_single_exe', {'db_id': note, 'name': 'Do not overwrite'})
    check(not saved(response) and len(rows()) == 2 and next(r for r in rows() if r['id'] == record_id)['name'] == 'Updated', 'multiple records prevent save without changing either record')
    check('single_record_form' not in call('page', {'db_id': note})['work_area']['html'], 'multiple records suppress the editable form')
    cli('db_tables_edit', {'id': note, 'list_type': 0})
    cli('db_tables_edit', {'id': note, 'list_type': 3}, fail=True)
    check(True, 'switching a multi-row note to single record is rejected')
    cli('data_delete', {'table': prefix, 'id': extra})
    row_button = cli('db_additionals_add', dict(button, place=1, class_name=prefix + '_row'))['id']
    buttons.append(row_button)
    cli('db_tables_edit', {'id': note, 'list_type': 3}, fail=True)
    check(True, 'existing row buttons prevent conversion')
    response = call('page', {'db_id': note})
    check('search_box' in response['work_area']['html'], 'search-and-table rendering still works')
finally:
    for id in buttons:
        cli('db_additionals_delete', {'id': id})
    if notes:
        for row in rows():
            cli('data_delete', {'table': prefix, 'id': row['id']})
    for id in screens:
        cli('screen_fields_delete', {'id': id, 'tb_name': prefix, 'screen_name': 'edit'})
    for id in fields:
        cli('db_fields_delete', {'id': id})
    for id in reversed(notes):
        cli('db_tables_delete', {'id': id})
    # These files were created by this fixture via the CLI, and contain no app data.
    for name in [prefix, prefix + '_other']:
        for path in [root / 'classes/data/common' / (name + '.dat'), root / 'classes/data/_common/fmt' / (name + '.fmt')]:
            if path.is_file():
                path.unlink()
print('PASS', checks, 'checks; fixture definitions removed')
