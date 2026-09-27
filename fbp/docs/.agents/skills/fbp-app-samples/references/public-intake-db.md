# 公開フォームと管理側受付：DB定義

正本は `assets/public-intake/public-intake.json`。IDは導入先で採番し、固定しない。長さはDB保存上限（bytes）。

## `sample_intakes`

表示名：サンプル：受付。独立ノート。

| 項目 | 型 | 長さ | 必須 |
| --- | --- | --- | --- |
| `name`（お名前） | text | 300 | ○ |
| `email`（メールアドレス） | text | 762 | ○ |
| `message`（お問い合わせ内容） | textarea | 3000 | ○ |
| `received_at`（受付日時） | datetime | 24 | — |
| `status`（対応状況） | dropdown | 24 | — |
| `request_key`（重複防止キー） | text | 64 | — |

画面設定：

- `list`: `received_at`, `name`, `email`, `message`, `status`
- `edit`: `status`
- `search`: `name`, `status`

## 選択肢

`sample_intake_status`: 0=未対応, 2=対応済

実データの初期投入はない。管理権限・保存ルールは[フローの説明](public-intake.md)を参照。
