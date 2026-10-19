# PHP画像エフェクト

`ImageEffects.php` をrequireし、`Fbp\ImageEffects\ImageEffects` のstaticメソッドを呼ぶ。
ディレクトリ名 `image_effetcts` は指定に合わせたもの。
PHP 8.1以上・GDを使用。Python・推論モデル・AI/API通信は不要。
自動背景分離は行わず、呼び出し側が本体マスクと必要な多角形を渡す。

## 既存ライブラリとの関係

- HEICは既存の `lib_ext/php-heic-to-jpg` を利用。既存変換バイナリの実行権限と対応OSが必要。JPEGを経由するため、HEICの元画素と完全一致する変換ではない。
- JPEG/PNGは既存のController画像処理と同じPHP GDで読み書きする。新しい画像ライブラリは追加しない。
- `Controller::resize_saved_image()` は元ファイルのHEIC→JPEG置換とJPEG出力を行うため、そのまま呼ばず、HEICライブラリとGDを直接利用する。本ライブラリは元ファイルを変更しない。
- GDはICC色空間変換を行わない。ICC付き入力は既定で拒否する。sRGBに変換済みであることを確認した場合だけ `prepare(..., sourceIsSrgb: true)` を指定する。非sRGB画像を変換するフラグではない。
- ICCなし入力はsRGBと仮定する。HEICの色変換品質は既存コンバーターに依存するので、広色域写真は事前にsRGB化する。
- 出力PNGにはsRGBチャンクを付与する。元ICC・EXIF・GPSは引き継がない。入力は最大40MB・デコード後24百万画素。

## 基本例

```php
require_once __DIR__ . '/ImageEffects.php'; // 呼出し元に合わせて解決
use Fbp\ImageEffects\ImageEffects;

$photo = ImageEffects::prepare($photoPath, $ctl->get_temp_dir(), sourceIsSrgb: true);
$mask = ImageEffects::prepare($maskPath, $ctl->get_temp_dir());
$waiting = ImageEffects::process($photo, $mask); // 確認画像だけを返す
$previewPng = ImageEffects::pngData($waiting['preview']);

// 利用者が本体マスクを確認した後に実行する
$done = ImageEffects::process($photo, $mask, [
    'reviewed' => true,
    'settings' => ['light_strength' => 0.14], // 背景だけに照明
    'full_body' => true,
    'reflections' => [['floor_y' => 1500]],
    'contacts' => [[700, 1500, 50]],
]);
$png = ImageEffects::pngData($done['result']);
$report = $done['report'];
```

`sourceIsSrgb: true` は実際にsRGB化済みの画像だけに指定する。
マスクは画像と同じサイズの不透明なグレースケール画像（128以上=本体、未満=背景）。
輪郭のフェザリングと高度なアルファマッティングは行わない。
ファイル取得・所有者確認・認証・DB・保存はアプリの責務。
生成物は通常の保存APIへ渡し、一時ファイルはControllerの一時ディレクトリに置く。
受け取ったリクエストのファイルパスをそのまま渡さず、サーバー側で保存済みファイルを解決する。

## 関数と引数

すべての画像引数・戻り値はGDの `GdImage`。処理中は入力GDオブジェクトを変更しない。
各メソッドはファイル・DBへの書込みを行わず、`savePng` と `savePreset` だけが指定ファイルを作る。
`prepare` はHEIC変換に限りControllerから渡された一時ディレクトリを使い、終了時に削除する。

| メソッド | 引数・戻り値 |
|---|---|
| `prepare(path, tempDir, sourceIsSrgb=false)` | ローカルJPEG/PNG/HEICを読み込み、JPEGのEXIF方向を補正。`tempDir` は `$ctl->get_temp_dir()`。元ファイルは保持 |
| `mask(source)` | グレースケールを白黒マスクへ変換。色付き・透明マスクは拒否 |
| `previewMasks(image, mask, polygons=[])` | 本体範囲を緑、削除予定の多角形を赤線で重ねた画像 |
| `removeSupports(mask, polygons, reviewed=false, occludesSubject=false)` | 多角形内をマスクから除外。未確認・本体を隠す支持具の除去は拒否 |
| `background(width=1080, height=1920, top=239, bottom=255)` | 白〜グレーの背景。`top/bottom` は0〜255 |
| `layout(image, mask, width=1080, height=1920, margin=80)` | 等比縮小・中央配置。拡大しない。`reference`・`mask`・`transform` を返す |
| `shadow(width, height, contacts, opacity=0.15, blur=12)` | 透過PNG用の接地影レイヤー。`contacts=[[x,y,横半径],...]`、強度0〜1。ぼかし幅を使った楕円ガウス近似 |
| `reflection(image, mask, floorY, fullBody, opacity=0.2, depth=220)` | 元本体画像由来の反射レイヤー。`floorY` は床のY座標、`fullBody=true` 必須。深さpx、強度0〜1 |
| `lighting(image, strength=0.12, center=[0.5,0.25], radius=0.7, target='background', allowSubjectChange=false)` | 白い光のグラデーションを入力全体に合成。中心は縦横比率0〜1、半径は正の比率。本体へ使う場合は `target='subject'` と変更許可が必要 |
| `compose(reference, mask, background, layers=[])` | 各レイヤーを指定順で合成し、本体を最後にコピー。全入力は同じ寸法 |
| `verify(reference, result, mask)` | 輪郭1pxを除く本体内部のRGB差分・画素数を返す。比較基準は方向補正・sRGB確認・縮小後の画像 |
| `comparison(before, after)` | 同じ寸法の加工前後を左右に並べる |
| `pngData(image)` | sRGB情報付きPNGバイナリを返す |
| `savePng(image, path)` | 新規PNG保存。既存ファイルの上書きは拒否 |
| `settings(values=[])` | 設定を検査して既定値を補う。未知の項目は拒否 |
| `loadPreset(path)` / `savePreset(settings, path)` | 設定JSONの読込み／新規保存 |
| `process(image, mask, options=[])` | 一連の処理。未確認なら `state=awaiting_mask_review` と `preview`、確認後は `reference/mask/background/layers/result/report` を返す |

`polygons` は `[[[x,y],[x,y],[x,y],...], ...]`。
座標は入力原寸px、左上原点。AIによる支持具の検出はしない。
`occludesSubject` の値も呼び出し側の判断であり、本体との重なりを自動判定するものではない。

影と反射は配置後キャンバスの座標を使う。`transform` の `offset` と `resized_size/source_size` で元写真の座標を換算する。
足が異なる奥行きにある場合は、それぞれの足に対応するマスクと床位置で `reflection()` を複数回呼び、レイヤーを重ねる。
反射は2D近似であり、物理的な照明・反射の再現ではない。

## processのoptions

| 項目 | 内容 |
|---|---|
| `settings` | 以下の設定配列。省略項目は既定値 |
| `reviewed` | `true` で確認後の合成を実行。それ以外は確認画像で停止 |
| `polygons` | 任意の支持具の除去多角形。原寸座標 |
| `occludes_subject` | `true` なら本体復元が必要なので拒否 |
| `full_body` | 反射が必要なら `true`。全身かどうかの自動判定はしない |
| `reflections` | `[['floor_y'=>整数, 'mask'=>配置後GdImage], ...]`。mask省略時は配置後の本体マスク全体。指定マスクは実際の本体範囲と交差させる |
| `contacts` | 影の `[[x,y,横半径],...]`、配置後座標 |
| `allow_subject_change` | 本体への照明変更を明示許可。画素が変われば検査は不合格のまま返す |

## 設定

`width=1080`、`height=1920`、`margin=80`（px）、`background_top=239`、`background_bottom=255`（0〜255）、
`reflection_opacity=0.2`、`reflection_depth=220`（px）、`shadow_opacity=0.15`、`shadow_blur=12`（px）、
`light_strength=0`、`light_target='background'`。
強度は0〜1、余白は画像領域が残る値、反射深さは1以上、影のぼかしは0〜1000。

本体の画素差分0は、選択した領域の保持だけを示す。マスク精度や除去対象の妥当性は保証しない。
本体の再生成、隠れたパーツの復元、AI背景分離、AIによる支持具判定は実装しない。
