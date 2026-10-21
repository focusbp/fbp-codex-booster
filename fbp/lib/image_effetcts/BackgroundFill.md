# AI背景補完

焼き込み文字・不要物の指定範囲をAIで補完し、候補画像の該当画素だけを元画像へ戻す。
元の背景を復元できる保証はなく、最終画像の目視確認が必要。
通常の合成は従来どおりImageCompositionを使う。

## 呼出し先の区分

- PHPアプリ: `BackgroundFill::run()`がOpenAI Images編集APIを呼ぶ。APIキーは呼出し元が渡す。
- auto-task / ローカルCLI: 準備→Codexの組込み画像編集ツール→候補合成。CLIはAPIを呼ばない。
- `AUTO_TASK_CODEX=1`ではPHP API呼出しも拒否する。ツールがなければ未実行として止め、APIへ自動切替しない。
- 実行文脈はサーバーコードが決める。JSON・GET/POSTからproviderやAPIキーを受け取らない。

## 設定JSON

```json
{
  "input": "banner.png",
  "rectangles": [[100, 200, 300, 70], [100, 300, 500, 100]],
  "prompt": "指定範囲の文字だけを消し、周囲に合わせて青空と雲を自然に補完する。文字を追加しない。"
}
```

`input`は必須。`rectangles`または`mask`のどちらか一方を必ず指定する。
矩形は原寸pxの`[x,y,width,height]`、1〜100個。画像外や小数座標は拒否。
`mask`は画像と同寸の不透明な白黒PNG。白が補完範囲、黒が保持範囲。
ファイルパスはJSONのディレクトリ基準。URL入力は禁止。
PNG/JPEG、40MB・24百万画素以下。入力のEXIF向きを補正した座標を使う。
ICC付き入力は先にnormalize_srgbするか、sRGBであることを明示する。
入力画像は変更しない。出力先は既存親ディレクトリ配下の新規ディレクトリのみ。

## Codex / auto-task

```sh
run.sh background_fill_prepare --config job.json --output prepared
```

出力は`input.png`、白が編集範囲の`mask.png`、透明が編集範囲の`api-mask.png`、
赤い範囲表示の`preview.png`、ハッシュ付き`request.json`。
`request.json`の状態は`awaiting_candidate`であり、準備成功を補完完了と扱わない。

1. input.pngとpreview.pngをview_imageで確認する。
2. 組込み画像編集ツールにinput.pngとmask.pngを参照画像として渡す。入力画像を事前に表示し、request.jsonのpromptを使う。ツール自身のマスク専用引数は前提にしない。
3. 生成結果を作業領域の候補画像へ保存する。ツールが使用不能なら停止・報告し、独自APIスクリプトへ迂回しない。
4. 次の共通CLIで合成し、目視確認する。文字入れは合成後にcompose_batchで行う。

```sh
run.sh background_fill_apply --request prepared/request.json --candidate candidate.png --output applied
```

候補は同じ構図・寸法が原則。生成ツールが寸法を変えた場合、構図が一致していることを確認後に
`--resize-candidate`を明示指定できる。縦横比差1%以内に限り原寸へリサイズする。
構図ずれや縦横比の大きな変化は再生成する。
境界をなじませる場合はJSONの`feather`またはapplyの`--feather`へ0〜64pxを指定する（既定0）。
マスクの内側だけで元画像との混合率を上げるため、範囲外の画素は変えない。
消したい文字・物体とマスク境界の間にはfeather以上の余白を設ける。余白不足では元の文字が残る。
APIの自然な編集でも範囲外の画素は変わり得るため、候補をそのまま納品せず共通合成を通す。
result.pngとreport.jsonを出力し、マスク外の画素差0を保存後に再検証する。
透明な補完範囲、変更のない候補、準備ファイルの変更、上書きは拒否する。
reportのvisual_review_requiredは常にtrue。画素検査は背景の自然さや文字残りの合格判定ではない。

## PHPアプリ

```php
// $frameworkRootはアプリの既存読込経路から解決したfbpディレクトリ。
require_once $frameworkRoot . '/lib/image_effetcts/BackgroundFill.php';
$report = \Fbp\ImageEffects\BackgroundFill::run(
    $job, $inputDirectory, $newOutputDirectory,
    $apiKey, $ctl->get_temp_dir(), 'gpt-image-2'
);
// 最終画像: $newOutputDirectory . '/applied/result.png'
```

上のrequireパスはアプリの既存読込方式に合わせる。APIキーは既存のサーバー側設定から渡し、
JSON・レポート・ログへ保存しない。prepare/applyはPHPから個別利用もできる。
通信は固定のOpenAI HTTPSエンドポイント、リダイレクトなし、自動リトライなし。
画像モデルは引数で明示変更できる。API候補はControllerの一時領域へ保存し、成功・例外時とも削除する。
API失敗時も準備成果物は残すため、出力先は呼出し元の管理領域とし、不要になったら削除する。
API自体のライブ接続試験と、スタブでの処理・画素検査は区別して報告する。
