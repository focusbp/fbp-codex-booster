# 複合グラフ・回帰線テンプレート

`assets/advanced-charts/` はDB不要の架空データで、棒＋折れ線の左右2軸と、実績＋最小二乗回帰線を表示する。Dashboard登録は `fbp-dashboard` を使う。

```sh
php <skill-dir>/scripts/install_feature_template.php advanced-charts <app-source-root>
```

同期後に管理Ajaxで `advanced_chart_sample/run` を呼ぶ。ウィジェットでは `advanced_chart_sample/dashboard`、幅3を登録する。インストーラーはコードを置くだけで、定義・データ・Dashboardを変更しない。

- `linear_trend.php` は等間隔の数値配列に限定。空配列は空、1件は例外、定数列は水平線。null・数値文字列・非有限値を拒否する。欠測のゼロ埋めや不等間隔の日付を自動補間しない。
- `fit($values, $future)` は実績分と延長分を返す。延長は回帰直線の外挿で、予測精度や将来値の保証ではない。表示上も実績と区別する。
- `draw()` の架空集計値を権限確認済みデータへ置き換える。ラベルと各系列の長さ・順序・単位を揃える。2軸の系列には `yAxisID` を明示する。現行wrapperに専用setterがないため、アプリ内の匿名Dataset派生クラスで `to_array()` を補足している。framework改修は不要。
- `create_chart()` で型を読み込んでから派生Datasetを生成する。各描画のcanvas IDは一意にし、Chart.jsを重ねてロードしない。
- 現行 `chart_draw()` はnullを除去する。サンプルの実績は末尾の将来枠だけ欠ける形にし、中間のnullで欠測を表現しない。

検証: `scripts/test_feature_templates.php <framework-fbp-root>` で回帰の数値・異常入力を確認。3件をコード配置したテストアプリで `scripts/verify_feature_templates.cjs` を実行できる（`FEATURE_TEST_URL / FEATURE_TEST_OUTPUT_DIR` と管理ログインを環境変数で渡す）。同じ画面内の2図、左右の単位、将来ラベル、canvas IDの重複なしを確認する。
