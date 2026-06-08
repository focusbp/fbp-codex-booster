---
name: fbp-yamato-csv
description: Implement or maintain Yamato Transport B2送り状 CSV export in FBP apps, including the Yamato shipping origin/settings note, B2 basic layout column order, db_additionals export dialogs, Shift_JIS CSV output, cool/frozen row splitting, and delivery address validation.
---

# fbp-yamato-csv

## trigger conditions
- ヤマト運輸 / クロネコヤマト / Yamato B2 の送り状CSVを出力する。
- 「配送元情報」「ヤマト配送情報」ノートを作る、または項目を確認する。
- B2基本レイアウトのCSV列順、出力値、Shift_JIS/CRLF、クール区分を扱う。

## companion skills
- `fbp-csv-media`: CSV download実装と検証。
- `fbp-db`: `yamato_shipping_setting` のDB/項目追加。
- `fbp-db_additionals` + `fbp-dialog`: 受注画面上部ボタンとプレビューダイアログ。
- `fbp-cli`: `db_tables_list` / `db_fields_list` / `screen_fields_list` / `app_call` 検証。

## shipping settings note
単純マスタなので Standard Screen でよい。配送元情報ノート名は、実装上は次を基本にする。

- table: `yamato_shipping_setting`
- menu_name: `ヤマト配送情報`
- description: `ヤマトB2 CSV出力用の発送元・請求先設定`
- `show_menu=1`, `screen_build_type=0`, `dropdown_item=name`, `show_id=1`

項目:

| parameter_name | title | type | length | required | default | purpose |
| --- | --- | --- | --- | --- | --- | --- |
| `name` | 設定名 | text | 255 | yes |  | プレビューの選択肢名 |
| `billing_customer_code` | 請求先顧客コード | text | 12 | yes |  | B2列40 |
| `billing_class_code` | 請求先分類コード | text | 3 | no |  | B2列41 |
| `fare_management_number` | 運賃管理番号 | text | 2 | yes |  | B2列42 |
| `shipper_name` | ご依頼主名 | text | 255 | yes |  | B2列25、32バイトに丸める |
| `shipper_zip` | ご依頼主郵便番号 | text | 20 | yes |  | B2列22 |
| `shipper_address` | ご依頼主住所 | text | 255 | yes |  | B2列23 |
| `shipper_tel` | ご依頼主電話番号 | text | 50 | yes |  | B2列20 |
| `invoice_type` | 送り状種類 | text | 1 | yes | `0` | B2列2、空なら`0` |
| `issue_count` | 発行枚数 | number | 24 | yes | `1` | B2列38、1-99に丸める |
| `memo` | メモ | textarea | 1000 | no |  | 管理用 |

画面項目:
- list: `name`, `billing_customer_code`, `fare_management_number`, `shipper_name`
- add/edit: 全項目

## export dialog
受注画面の上部ボタンにする。

- `db_additionals.tb_name`: `customer_order`
- `button_title`: `ヤマトCSV出力`
- `class_name`: `<order_table>_yamato_csv_additional`
- `function_name`: `run`
- `place=0`, `close_button=2`, `dialog_width=1000`

プレビューでは対象件数、配送先、住所、配送方法を表示し、`yamato_shipping_setting` を選択させる。出荷予定日はCSV出力時に入力する。

```smarty
<button type="button" class="download-link"
  data-class="customer_order_yamato_csv_additional"
  data-function="download_csv_exe"
  data-filename="yamato_export.csv">CSVダウンロード</button>
```

## target orders
appごとにステータス値は調整する。今回の運用では次を配送CSV対象にした。

- `customer_order.delivery_type == 0`: 配送。店頭受取は除外。
- `customer_order.order_status == 4`: 発送準備済み。

配送先側に最低限必要な項目:
- `shipping_name`
- `shipping_tel`
- `shipping_zip`
- `shipping_prefecture`
- `shipping_address`
- 任意: `gift_memo`

## B2 basic layout
B2基本レイアウトは95列固定。`array_fill(0, 95, "")` で空行を作り、必要列だけ埋める。列番号はヤマト仕様上は1始まり、PHP配列は0始まりなのでずれに注意する。

全ヘッダ順は `references/yamato-b2-basic-layout.md` を読む。

主な出力列:

| PHP index | B2 column | header | value |
| --- | --- | --- | --- |
| 0 | 1 | お客様管理番号 | 受注ID。温度帯で分割時は `<id>-F` / `<id>-R` |
| 1 | 2 | 送り状種類 | `yamato_shipping_setting.invoice_type` の先頭1文字、空なら`0` |
| 2 | 3 | クール区分 | 冷凍`1`、冷蔵/クール`2` |
| 4 | 5 | 出荷予定日 | `Y/m/d` |
| 8 | 9 | お届け先電話番号 | `shipping_tel` |
| 10 | 11 | お届け先郵便番号 | `shipping_zip` |
| 11 | 12 | お届け先住所 | 都道府県ラベル + `shipping_address` |
| 15 | 16 | お届け先名 | `shipping_name`、32バイトに丸める |
| 19 | 20 | ご依頼主電話番号 | `shipper_tel` |
| 21 | 22 | ご依頼主郵便番号 | `shipper_zip` |
| 22 | 23 | ご依頼主住所 | `shipper_address` |
| 24 | 25 | ご依頼主名 | `shipper_name`、32バイトに丸める |
| 27 | 28 | 品名１ | 商品名1、50バイトに丸める |
| 29 | 30 | 品名２ | 商品名2、50バイトに丸める。3件以上なら「他」を付ける |
| 32 | 33 | 記事 | `gift_memo`、44バイトに丸める |
| 35 | 36 | 止置き | `0` |
| 37 | 38 | 発行枚数 | `issue_count`、1-99 |
| 39 | 40 | 請求先顧客コード | `billing_customer_code` |
| 40 | 41 | 請求先分類コード | `billing_class_code` |
| 41 | 42 | 運賃管理番号 | `fare_management_number` |
| 42 | 43 | クロネコwebコレクトデータ登録 | `0` |
| 47 | 48 | お届け予定ｅメール利用区分 | `0` |
| 51 | 52 | お届け完了ｅメール利用区分 | `0` |
| 54 | 55 | クロネコ収納代行利用区分 | `0` |

## temperature splitting
受注明細の配送方法が冷凍/冷蔵で混在する場合は、1受注を温度帯別に複数CSV行へ分割する。

- 冷凍: customer control no suffix `F`, クール区分`1`
- 冷蔵/クール: customer control no suffix `R`, クール区分`2`
- 温度帯が1種類だけなら管理番号は受注IDのみ。
- 明細がない場合は冷蔵扱いで1行出す。

## validation and formatting
- `yamato_shipping_setting` 未選択、出荷予定日なし、対象受注なしはCSV出力前に止める。
- ヤマトB2は配送先都道府県が実質必須。`prefecture_opt` のラベルを `shipping_address` の先頭へ連結する。住所がすでに都道府県から始まる場合は二重連結しない。
- `shipping_address` 空、都道府県未設定かつ住所先頭にも都道府県がない受注は出力前にエラーにする。
- 改行、タブはスペースへ正規化する。
- 名称や記事は `mb_strimwidth()` でバイト幅上限に丸める。

## CSV response
ヤマトB2向けは Shift_JIS / CRLF 固定にする。

```php
while (ob_get_level() > 0) {
    ob_end_clean();
}
header("Content-Type: text/csv; charset=Shift_JIS");
header("Content-Disposition: attachment; filename=\"yamato_export_" . date("Ymd") . ".csv\"");
$ctl->res_csv($headers, "sjis-win", "\r\n");
foreach ($rows as $row) {
    $ctl->res_csv($row, "sjis-win", "\r\n");
}
exit;
```

`download-link` のエラーは通常のHTML再描画ではなく、HTTP 400 + JSONで返す。

```php
header("Content-Type: application/json; charset=UTF-8");
header("X-FBP-Download-Error: 1");
header("X-FBP-Download-Error-Title: " . rawurlencode("CSV出力エラー"));
echo json_encode(["title" => "CSV出力エラー", "message" => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
```

## verification
- `php -l` for additional class PHP.
- `php <app-root>/fbp/cli.php db_tables_list`
- `php <app-root>/fbp/cli.php db_fields_list --json='{"db_id":<yamato_shipping_setting db_id>}'`
- `php <app-root>/fbp/cli.php screen_fields_list --json='{"tb_name":"yamato_shipping_setting","screen_name":"list"}'`
- `php <app-root>/fbp/cli.php app_call --json='{"class":"customer_order_yamato_csv_additional","function":"run"}'`
- CSV downloadはCLIの `output_file` で保存し、ヘッダ列数が95、文字コードがShift_JIS、改行がCRLFであることを確認する。
