# 親子ノートと対応履歴：DB定義

正本は `assets/parent-child-history/parent-child-history.json`。IDは導入先で採番し、固定しない。長さはDB保存上限（bytes）。

## `sample_contacts`

表示名：サンプル：顧客。独立ノート。

| 項目 | 型 | 長さ | 必須 |
| --- | --- | --- | --- |
| `name`（顧客名） | text | 300 | ○ |
| `contact_name`（担当者） | text | 300 | — |
| `email`（メールアドレス） | text | 762 | — |
| `memo`（備考） | textarea | 1500 | — |

画面設定：

- `list`: `name`, `contact_name`, `email`
- `add`: `name`, `contact_name`, `email`, `memo`
- `edit`: `name`, `contact_name`, `email`, `memo`
- `delete`: `name`
- `search`: `name`, `contact_name`

## `sample_contact_history`

表示名：対応履歴。親：`sample_contacts`。`parent_id` は親設定により自動生成される。

| 項目 | 型 | 長さ | 必須 |
| --- | --- | --- | --- |
| `contacted_on`（対応日） | date | 24 | ○ |
| `subject`（件名） | text | 300 | ○ |
| `staff`（担当者） | text | 300 | — |
| `detail`（内容） | textarea | 3000 | — |

画面設定：

- `list`: `contacted_on`, `subject`, `staff`
- `list_on_side`: `contacted_on`, `subject`, `staff`
- `add`: `contacted_on`, `subject`, `staff`, `detail`
- `edit`: `contacted_on`, `subject`, `staff`, `detail`
- `delete`: `subject`
- `search`: `subject`


実データの初期投入はない。管理権限・保存ルールは[フローの説明](parent-child-history.md)を参照。
