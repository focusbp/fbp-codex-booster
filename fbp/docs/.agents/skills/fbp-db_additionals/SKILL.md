---
name: fbp-db_additionals
description: Design and implement db_additionals features as extensions around legacy Standard Screen flows, from entry-point decision to registration and verification.
---

# fbp-db_additionals

## trigger conditions
- 既存の Standard Screen だけでは要件を満たせず追加機能が必要
- `db_additionals` の add/edit/list 運用が必要

## workflow
1. まずこの対応が Standard Screen 系の拡張か確認する。新規 Original Screen を組むなら `db_additionals` を第一候補にしない。
2. 入口判定: `screen_fields` で足りるか確認。
3. `db_additionals_list` で既存重複を確認。
4. 追加機能を実装し `db_additionals` に登録。
5. `app_call` と `data_*` で動作確認。

## dialog width policy
- `db_additionals_add` / `db_additionals_edit` では `dialog_width` を必ず明示設定する。
- 幅は px の実数値として扱い、最小 `600`、最大 `1200`、`clamp(600, auto_calculated_width, 1200)` で決定する。
- 自動決定の目安:
  - 項目数・情報量が少ない: `600`
  - 中程度: `800`〜`1000`
  - 項目数・情報量が多い: `1200`
- `9` などの1桁/異常に小さい値は入力・更新しない。既存値が異常な場合は `600` 以上へ補正してから作業を継続する。
- 個別の入力部品や `select` に対する `width` 指定は、ユーザーから明示要求がある場合のみ追加する。指定がない場合は不要な固定 `width` を入れない。

## constraints
- `db_additionals` は Standard Screen 側の拡張として扱う。Original Screen の新規制作を置き換える用途に広げない。
- 先に標準機能で解決できるかを必ず検討する。
- `db_additionals_list` の結果で対象レコードの `dialog_width` が `600`〜`1200` に入っていることを確認する。
- PDF生成を `db_additional` ボタンから実行する場合は、いったんダイアログを表示し、ダイアログ内 `download-link` でダウンロードさせる（`ajax-link` でPDFダウンロードは不可）。
