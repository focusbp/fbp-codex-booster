# 親子ノートと対応履歴

顧客一覧の「対応履歴」アイコンから、選択した顧客のサイドパネルを開く。標準の追加・編集・削除を使い、保存後に子一覧へ反映する。

`sample_contacts_visibility_filter` と `sample_contact_history_visibility_filter` は管理ログインと管理者権限を確認する。親子の絞り込み・親IDは Standard Screen に任せる。子ノートは独立メニューを持たない。親の削除時には子履歴も削除するので、履歴保持が必要な案件では削除方針を変更する。

対象は顧客対応、案件メモ、設備の点検履歴など。担当者別アクセス制御、添付、集計、変更監査は含まない。

## 導入

[共通手順](workflow-samples.md)に従い、`parent-child-history` を指定する。コードと設定の正本は `assets/parent-child-history/`。構造は [parent-child-history-db.md](parent-child-history-db.md) を参照。

## 確認する操作

- 顧客を選び、子履歴を追加・編集・削除でき、サイドパネルが更新される。
- 別の顧客を選ぶと先の顧客の履歴が出ない。
- 未ログイン・非管理者はノートへアクセスできない。
- PCと狭い画面で標準サイドパネルを確認する。
