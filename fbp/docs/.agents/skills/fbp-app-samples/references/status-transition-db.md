# 状態変更と確認ダイアログ：DB定義

正本は `assets/status-transition/status-transition.json`。IDは導入先で採番し、固定しない。長さはDB保存上限（bytes）。

## `sample_cases`

表示名：サンプル：案件状態。独立ノート。

| 項目 | 型 | 長さ | 必須 |
| --- | --- | --- | --- |
| `title`（案件名） | text | 300 | ○ |
| `detail`（内容） | textarea | 3000 | — |
| `status`（状態） | dropdown | 24 | — |
| `version`（更新版） | number | 24 | — |
| `change_history`（状態変更履歴） | textarea | 6000 | — |

画面設定：

- `list`: `title`, `status`, `change_history`
- `add`: `title`, `detail`
- `edit`: `title`, `detail`
- `delete`: `title`
- `search`: `title`, `status`

## 選択肢

`sample_case_status`: 0=未対応, 1=対応中, 2=完了

実データの初期投入はない。管理権限・保存ルールは[フローの説明](status-transition.md)を参照。
