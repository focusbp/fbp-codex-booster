# 標準画面の追加・編集・削除の上書き

ノート設定「標準画面カスタマイズクラス」(`standard_screen_class`) にクラス名を登録する。
推奨名は `<tb_name>_customize_standard_screen`、配置はアプリの同名ディレクトリ内の同名PHP。
設定なしは従来どおり。設定済みでファイル・クラスが存在しない場合、またはメソッドがpublicインスタンスメソッドでない場合は書込み前にエラー。
クラスは必要なら `__construct(Controller $ctl)` を持てるが、DB変更はコンストラクタに書かない。

## 関数名別テンプレート

- [クラスの外枠](../assets/customize_standard_screen/class.php)
- [add](../assets/customize_standard_screen/add.php): `add(Controller $ctl, StandardScreenAction $action): void`
- [edit](../assets/customize_standard_screen/edit.php): `edit(Controller $ctl, StandardScreenAction $action): void`
- [delete](../assets/customize_standard_screen/delete.php): `delete(Controller $ctl, StandardScreenAction $action): void`
- [prepare_add](../assets/customize_standard_screen/prepare_add.php): `prepare_add(Controller $ctl, StandardScreenForm $form): void`
- [prepare_edit](../assets/customize_standard_screen/prepare_edit.php): `prepare_edit(Controller $ctl, StandardScreenForm $form): void`

メソッド断片をクラスの中へコピーし、検証や独自処理を追加する。断片ファイル自体は実行しない。
対応メソッドがなければ標準処理。メソッドがあれば戻り値にかかわらず処理を委譲し、returnで中止する。
標準フォーム・認可の後、DB・ファイル変更前に同期呼出しする。エラーはres_error_message後returnし、フォーム再表示・close・reloadをしない。

`$action->standard($post)` は補正したPOSTで標準実行処理を1回呼び、finallyで元のPOSTに戻す。
引数省略は元のPOST。標準の入力検証、DB更新、添付処理、post_action_class、表示更新をそのまま利用できる。
この場合、追加の保存・添付処理・finish・post_action_class呼出しを行わない。

## フォームの表示準備

prepare_add/prepare_editは標準フォームの項目設定と表示値が揃った後、HTML描画前にサーバー内で同期実行する。
通常画面・子ノート・単一レコードに対応。単一レコードは初回prepare_add、既存1件はprepare_editで、画面項目は従来どおりedit設定を使う。
prepareメソッドがなければ表示は従来どおり。データ処理用メソッドだけのクラスも変更不要。prepareではDB・定義の保存、送信等の副作用を実行しない。

- `$form->fields`：この画面の項目設定の配列。options、parameter_title、parameter_description、validation等をこの描画だけ調整できる。元の画面項目にない名前の追加・同名重複は拒否する。定義を永続更新しない。
  validationの変更は表示上の必須印の調整であり、標準保存時の検証定義は変更しない。
- `$form->row`：初期値・表示値。追加のデフォルト設定に使える。idと子ノートの親IDは呼出し元が保持し、prepareで差し替えられない。
- `$form->excludeOption($name, $value, $keepCurrent = true)`：ドロップダウン等の選択肢を除外。既存の保存値（配列値も含む）は既定で残して表示する。選び直しが必要ならfalseを指定し、表示値のクリア・空の選択肢・保存時の拒否条件も明示する。
- `$form->hide($name)`：表示を隠し、値はhidden入力で送る。チェックボックス配列も保持する。ブラウザーへ値は渡るので秘密情報の秘匿には使わない。
- `$form->readOnly($name)`：値の表示とhidden入力に置き換える。チェックボックス等で値が欠落しないよう保持する。HTML属性による見た目の制御だけで認可しない。
- `$form->operation/table/setting/child/single`：呼出し元が確定した読取り専用の文脈情報。

フォームで隠した値・読取り専用値・除外した選択肢もHTTPでは改ざんできる。add/editの実行メソッドで許可値を検証し、変更不可の項目はDBの元の値から設定し直すか、変更を拒否する。
画面から項目配列を完全に取り除く場合も、標準保存のcheckbox未送信時の空配列補完等を考慮する。通常はhide/readOnlyを使い、保存側の検証を併用する。
開いているフォーム内の入力変更だけではprepareは再実行されない。入力に即応する動的フォームや、認証付きファイル配信の変更はこの機能に含まない。

## 実行処理全体を置き換える場合

関数別の完全上書き用ひな形：[add](../assets/customize_standard_screen/add_full.php)、[edit](../assets/customize_standard_screen/edit_full.php)、[delete](../assets/customize_standard_screen/delete_full.php)。日付等の正規化・競合・権限・添付の業務検証は対象ノートに合わせて追加する。通常は上の標準委譲用テンプレートを優先する。

標準委譲せず、`$action->db()` のFFMで更新し、成功時に `$action->finish($after, $before)` を1回だけ呼ぶ。
finishは保存そのものをせず、標準側の後処理・表示更新を担当する。追加ではbeforeを省略、削除ではafterに削除前レコードを渡す。
追加・編集の保存テンプレートは以下（メソッド内部へ組み込み、業務要件に合わせる）。

```php
$post = $ctl->POST();
// ここまでに関係する全DBを開く。その後でcurrent()と関連レコードを読み直す。
$before = $action->operation === 'edit' ? $action->current() : null;
if ($action->operation === 'edit' && !$before) {
    $ctl->res_error_message('id', '対象がありません。'); return;
}
$screen = $action->single || $action->operation === 'edit' ? 'edit' : 'add';
$row = $before ?: $ctl->get_default_values($action->table);
// 更新可能なフォーム項目だけ採用する。hiddenや制御パラメータをDBへ丸ごと入れない。
foreach ($ctl->get_field_list($action->table, $screen) as $field) {
    $name = $field['parameter_name'];
    if (in_array($name, ['id', 'created_at', 'updated_at'], true)) continue;
    if ($field['type'] === 'checkbox') $row[$name] = $post[$name] ?? [];
    elseif (!in_array($field['type'], ['file', 'image'], true) && array_key_exists($name, $post)) $row[$name] = $post[$name];
}
if ($action->child) $row[$action->child['parent_field']] = $action->child['parent_id'];
// 業務上の検証、閲覧・更新権限、改ざん、同時更新をここで確認する。
$ctl->validate($action->table, $screen, $row, $before === null);
if ($ctl->count_res_error_message() > 0) return;
$db = $action->db();
if ($before) { $row['id'] = $before['id']; $db->update($row); }
else { unset($row['id']); $db->insert($row); }
$ctl->save_posted_files($action->table, $row);
$action->finish($db->get($row['id']), $before);
```

削除では参照・権限を検証後、`$action->deleteRecord((int)$before['id'])` で標準の再帰削除・添付削除を使い、`$action->finish($before)` を呼べる。
`deleteRecord` 自体は業務上の参照チェックを追加しない。元の標準削除のcascade設定を確認する。

## 適用範囲と担当

- 通常の一覧、カレンダー、子ノートのadd/edit/delete実行、単一レコードの初回追加・更新に対応。
- `$action->operation/table/setting/child/single` はサーバー側で確定した読取り専用情報。
- `$action->current()` は通常・子ノートではPOSTの暗号化idを解決し、単一レコードではその1件を取得する。必要な関連DBを先に開き、その後に最新値で判断する。
- 完全上書き時のcheckbox・日付等の正規化、入力・関連検証、添付保護、競合対策はアプリの担当。上の例は標準委譲と同等の汎用実装ではない。通常はstandard()を選ぶ。
- 未ログイン・操作認可、画面パターンの拒否は既存の入口で維持。業務固有の所有権・関連整合性はカスタマイズ内で検証する。
- `standard()` と `finish()` の二重実行は例外。メソッドreturn後に標準処理を追加実行しない。
- 標準複製・日時変更・並び替え、Excelセル保存、Original Screen、API/CLIのdata更新、MCP CRUDは対象外。これらも含めた制限はDSP等で別途設計する。
- FFMは複数DB・添付処理のロールバックを保証しない。finishで保存前検証を代用しない。後処理は既存post_action_classを維持する。
