# fixed_file_manager 本番IDX候補監査（2026-08-14）

## 目的

本番データの件数、`.fmt` の既存IDX、Standard Screenの検索項目、アプリコード中の `select()` 完全一致条件を読み取り専用で照合し、IDX追加候補を抽出した。

本監査では本番の `.fmt`、`.dat`、DB項目定義を変更していない。値分布の集計では値そのものを保存・出力せず、有効件数、異なる値の数、最大グループ件数だけを使用した。

## 対象と判定方法

- 管理上の登録先66環境を確認し、共通DBデータと`.fmt`を取得できた45環境、282テーブルを集計した。
- 残り21環境は、データディレクトリが存在しない旧・テスト登録、または共通DBの`.fmt`を持たない環境であり、候補判定から除外した。
- 既存IDXは2項目だった。
  - `app-soshikikaikaku / project.status`
  - `app-soshikikaikaku / task.parent_id`
- 候補は、完全一致検索で実際に使われること、データ件数、値の分散、最大一致グループの小ささを優先した。
- T型の部分一致はIDX候補にせず、文字列ブロック検索を使用する前提とした。

「最大候補率」は、有効行のうち最も件数の多い検索値が占める割合である。完全一致検索時の候補削減効果を保守的に見る目安として使う。

## 優先候補

次は、実データ量と実際の呼出し条件の両方から、IDX追加効果が明確な候補である。

| 優先度 | アプリ | テーブル.項目 | 有効件数 | 異なる値 | 最大候補率 | 根拠 |
|---|---|---|---:|---:|---:|---|
| A | app-zennichi | `invoice_detail.parent_id` | 24,621 | 1,224 | 0.80% | `select()` 3箇所。明細取得で約125分の1まで候補を絞れる |
| A | app-soshikikaikaku | `task_history.parent_id` | 5,243 | 584 | 1.60% | `select()` 7箇所。履歴取得で約62分の1 |
| A | app-zennichi | `invoice_location.customer_location_id` | 1,904 | 470 | 0.32% | `select()` 5箇所。約317分の1 |
| A | app-zennichi | `invoice_location.parent_id` | 1,904 | 788 | 2.63% | `select()` 4箇所。約38分の1 |
| A | app-zennichi | `item.parent_id` | 1,893 | 437 | 1.90% | `select()` 4箇所。約53分の1 |
| A | app-soshikikaikaku | `quotation_detail.parent_id` | 709 | 157 | 3.95% | `select()` 4箇所。約25分の1 |
| A | app-zennichi | `invoice.customer_id` | 602 | 199 | 1.00% | Standard Screen検索項目かつ`select()` 3箇所。約100分の1 |
| A | app-zennichi | `customer_location.parent_id` | 449 | 198 | 10.69% | `select()` 5箇所。約9分の1 |
| A | app-nb | `drawing_results.line_member_id` | 359 | 265 | 0.56% | `select()` 2箇所。約180分の1 |

`app-soshikikaikaku / task.status` は有効3,331件、7値で、Standard Screenの検索項目である。最大値が57.79%を占めるため上表ほどの候補削減率ではないが、タスク一覧で状態検索を頻繁に使うなら優先度A相当として追加してよい。最大グループでも約1.7分の1、少数状態ではそれ以上の削減になる。

## 次点候補

次は効果を見込めるが、現在件数が小さい、削除済み行が多い、またはソースから直接の呼出しを確認できなかったため、利用頻度を確認してから追加する。

| アプリ | テーブル.項目 | 有効件数 | 異なる値 | 最大候補率 | 判断 |
|---|---|---:|---:|---:|---|
| app-bluecrane2 | `collection_agency_monthly_property_lines.owner_id` | 1,223 | 48 | 17.66% | Standard Screen検索。元スロット2,600件中1,377件が削除済み |
| app-daitomiraku | `customer_order_item.parent_id` | 396 | 126 | 3.03% | 分散は良いが、リテラルの`select()`呼出しは未検出 |
| app-soshikikaikaku | `task_ai_usage_monthly.parent_id` | 390 | 341 | 0.77% | Standard Screen検索。今後の増加を見込むなら有効 |
| app-small | `bni_chapter.region_id` | 405 | 53 | 6.91% | `select()` 1箇所。同期・地域別取得の頻度次第 |
| app-bluecrane | `tenant_report_detail.parent_id` | 123 | 116 | 1.63% | 分散は非常に良いが、現時点の件数が小さい |

次のT型項目は完全一致専用のIDXとしては有効だが、現時点の件数では急いで追加する必要はない。

- `app-small / bni_chapter.public_chapter_id`: 有効405件、405種類、完全一致呼出しあり
- `app-tr / line_member.userid`: 有効275件、275種類、完全一致呼出し4箇所
- `app-miclub / line_member.userid`: 有効119件、119種類、完全一致呼出し4箇所

## IDXを付けないほうがよい例

- `app-daitomiraku / line_public_url_token.line_member_id`: 4,519スロットが全件削除済みで、有効行が0件
- `app-wordgritty / wordlist_ngsl.status`: 有効2,799件がすべて同じ値
- `app-soshikikaikaku / invoice.agency_id`: 有効1,015件中1,010件が同じ値
- `app-soshikikaikaku / project.agency_id`: 有効206件中196件が同じ値
- `member_type`、単一状態に偏った`status`など、ほぼ全件が同じ値になる項目
- 氏名、住所、メモなどのT型部分一致項目。これらはIDXではなく文字列ブロック検索の対象とする

値が極端に偏る項目は、索引ファイルの維持コストに対して候補削減が小さい。単に`*_id`や`status`という名前だけでIDXを追加しない。

## フォーマット差異の確認

データ破損を示すレコード境界不整合は確認されなかった。ただし、`app-soshikikaikaku` の次の2テーブルは、`.dat`ヘッダーに保存された旧フォーマットと現在の`.fmt`が異なる。

- `cashflow_step`: 現在の`.fmt`では`parent_id`、`old_id`が削除済み
- `invoice_detail`: 現在の`.fmt`には`source_detail_key`が追加済み

これは未変換のフォーマット変更であり、書込み可能モードで次に開かれた際に既存の`changeFormat()`対象となる。IDX追加前に、通常のフォーマット変換が完了することと変換後データを確認してから進める。

## 推奨する適用順

1. 優先候補を一度に全アプリへ追加せず、まず件数と呼出し頻度が最も高い`invoice_detail.parent_id`と`task_history.parent_id`を各プロジェクトのテスト環境へ追加する。
2. IDX OFF/ONで検索結果を厳密比較し、代表検索の時間、索引構築時間、索引容量を記録する。
3. Standard Screenの一覧、検索、子一覧、追加、更新、削除、復元を確認する。
4. 問題がなければ同じプロジェクト内の残りの優先候補へ段階的に広げる。
5. 次点候補は件数増加または遅延計測を契機に追加する。

本監査は候補抽出までであり、DB項目定義の`index_flag`および本番データは変更していない。
