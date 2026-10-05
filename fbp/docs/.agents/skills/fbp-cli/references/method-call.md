# ヘルパー関数単位の確認

`method_call` はFBP CLIの共通初期化を通して対象メソッドを一度呼ぶ。
通常のアプリクラス解決（アプリ優先、次にframework）と、CLIですでに読み込んだクラスを使う。
HTTPからの入口は追加しない。実行場所は環境ルールに従う。

```sh
php fbp/cli.php method_call --json='{"class":"helper","function":"format","args":[3661],"expect":"1.01.01"}'
php fbp/cli.php method_call --json-file=method-case.json
```

- 必須: `class`、`function`。名前空間・ファイルパス指定は対象外。
- `args`: 順序付きの引数配列。省略は引数なし。JSONのobjectはPHP連想配列に変換される。
- `expect`: 指定時は戻り値をPHPの `===` で照合。型・配列のキーと順序も比較する。`null` も期待値として指定可能。
- 期待値なしは `EXECUTED` と戻り値を返す。期待値ありは `PASS` / `FAIL`。正常終了0、不一致・読込み/引数/実行例外は1。エラーは発生段階と内容を返す。
- public/private/protected、継承メソッド、static、既定引数、可変長引数に対応。参照渡し引数・コンストラクタ/デストラクタそのものの直接指定は対象外。
- 通常のインスタンス生成を行う。必要なコンストラクタ引数は `constructor_args` 配列で指定する。staticではインスタンスを作らない。
- コンストラクタ状態に依存しない処理だけを確認したい場合、明示的な `without_constructor:true` で生成時のコンストラクタを省略できる。既定では省略しない。
- Controllerの注入、`init()`、ログイン、画面の描画ライフサイクルは実行しない。Controllerやリクエスト文脈が必要な確認は `app_call` / `app_check` を使う。
- 戻り値はJSONにできるスカラー・配列・null（深さ128まで）。object/resourceや非有限数は取得失敗として返す。
- `echo`等は `output` へ分離する。非UTF-8出力は `output_encoding:base64`。途中でexitした場合は結果を確認できないためERROR。値のfalseは実行エラー扱いにしない。
- 関数は実際に動く。DB更新等の副作用をロールバックしない。エラー時でも実施済み操作があり得るため、出力を確認せずに再試行しない。

ローカルラッパーがある環境では、`method_call <class> <function> [extra-json]` の位置引数形式で呼べる。
成否だけ必要なら既存の `--summary` / `--summary-json` を使い、詳細は表示された保存先を読む。
戻り値そのものが必要なら通常出力を使う。要約の未検証欄は、追加検証の義務を意味しない。

新しい機能があることを理由にテストを増やさない。変更した動作の確認が既存のCLI・画面確認で足りていれば重ねず、単純なヘルパーの代表入力を確認する場合に使う。
