# 多対多突合サンプル：DBと画面

定義の正本は`assets/many-to-many-reconciliation/many-to-many-reconciliation.json`。
全テーブルを`sample_`で始め、既存案件のデータ・テーブルに依存しない。

| ノート | 役割 | 管理画面 |
|---|---|---|
| sample_recognition_items | 計上日・相手先・内容・計上額 | Standard Screen、追加・編集・検索 |
| sample_receipt_items | 入出金日・摘要・入金額・出金額・残高・取込情報 | Standard Screen、通帳CSV取込・検索、手動追加は補助 |
| sample_reconciliation_links | 計上IDと入金IDの直接関係 | メニュー非表示、専用操作だけで変更 |
| sample_reconciliation_events | 関係の追加・解除時の履歴 | メニュー非表示、読み取りのみ |

## 共通元明細

`scope_key`、`currency`（JPY固定）、`occurred_on`（標準date）、`counterparty`、
`description`、`amount`（整数円）、`eligibility`（0通常／1保留／2対象外）。
サンプルの金額範囲は0〜999999999999円。出金・返金・個別充当の金額を負の入金として扱わない。
別通貨に拡張する場合はその最小通貨単位の整数と表示桁数を定義する。

派生列は`group_recognition`、`group_receipt`、`group_difference`、`relation_state`、
`balance_state`、`link_count`。追加・編集画面から外し、可視性フィルターでも不正POSTを拒否する。
両側で同じグループ金額を示す。グループ合計の行単位再加算は禁止。

## 入出金の取込情報

`withdrawal_amount`、`balance_amount`、`import_account_key`、`external_transaction_id`、
`source_key`、`source_facts`、`source_file`、`source_row`を保持する。
`amount`は入金額で、出金行はamount=0・eligibility=2。
通帳の記録と突合対象区分を分け、出金行を黙って捨てない。
取込識別子・出金額・残高はサンプルの標準編集から変更しない。

## 中間関係

`recognition_id`、`receipt_id`、`scope_key`、`active`、`revision`、作成・更新日時と操作者、
`pending_event`（書込待ち履歴JSON）。充当額は持たない。
ペアは過去分を含め1行、解除でactive=0、再追加で同じ行をactive=1・revision加算。

FFMはアプリサービスの排他と最新再読込で一意性を保つ。SQLで実装する場合は
`UNIQUE(scope_key, recognition_id, receipt_id)`と元明細への外部キーを付ける。

## 履歴

`event_key`（関係ID:revision）、`link_id`、`action`、`actor_id`、`occurred_at`、
`operation_id`、左右のID・操作時金額、`method`を記録する。
一括追加は同じoperation_idで追跡する。初期版のmethodはmanual。
元明細編集の履歴と、関係操作の履歴を混同しない。

## 画面・権限・制約

双方に「突合」「突合解除」「グループ詳細」、入出金側上部に「通帳CSV取込」。
一覧・検索・保存・CSVはログイン済みアプリ管理者に限定する。
リンクと履歴の標準CRUDは拒否し、取込や専用操作からだけ変更する。
scope_keyは管理者が指定する結合境界。一般利用者へ開放する場合は、
利用者の所属範囲による表示・保存認可を追加し、POSTされたscope_keyを認可根拠にしない。

関係がある間は管理範囲・通貨の変更と保留／対象外化を拒否する。
元明細に過去を含む関係があれば物理削除を拒否し、解除後の対象外化で保存を残す。
CLI・汎用DB APIは管理用の直接操作であり、専用サービスの検証・保存後処理を迂回する。
本番の外部書込みを許す場合は同じサービスを呼ぶアダプターに限定する。
