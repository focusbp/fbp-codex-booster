# PHP画像エフェクト

`ImageEffects.php` をrequireし、`Fbp\ImageEffects\ImageEffects` のstaticメソッドを呼ぶ。
ディレクトリ名 `image_effetcts` は指定に合わせたもの。
PHP 8.1以上・GDを使用。合成・エフェクトはPHPのみ。
本体マスクを外部から渡すか、`createMask()` でPHPからPythonを呼んで生成する。
支持具のAI判定、外部API通信、隠れた部品の復元は行わない。

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
$generated = ImageEffects::createMask($photo, $ctl->get_temp_dir());
$mask = $generated['mask'];
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
`savePng` と `savePreset` は指定ファイルを作る。
`prepare` のHEIC変換と `createMask` はControllerから渡された一時ディレクトリを使い、終了時に削除する。
その他の画像メソッドはファイル・DBへの書込みを行わない。

| メソッド | 引数・戻り値 |
|---|---|
| `prepare(path, tempDir, sourceIsSrgb=false)` | ローカルJPEG/PNG/HEICを読み込み、JPEGのEXIF方向を補正。`tempDir` は `$ctl->get_temp_dir()`。元ファイルは保持 |
| `createMask(image, tempDir, python=null, timeout=60, tolerance=28)` | PHPから同梱Pythonワーカーで候補マスクを新規生成。`mask` と `report` を返す。タイムアウト1〜300秒、背景分離閾値の調整値0〜100（大きいほど候補を広く残す） |
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
本体の再生成、隠れたパーツの復元、AIによる支持具判定は実装しない。

## Pythonマスク生成

`PythonMask.php` がPHPでデコード済みのRGBを一時BMPへ出し、配列形式の `proc_open` で
`python/mask_worker.py` を実行する。ワーカーはU2NETPで候補を推定し、元RGBを使って輪郭を補正する。
本体画像を生成・変更せず、マスクだけを返す。寸法・SHA256を照合し、一時BMP/PNGは成功・失敗時とも削除する。
生成されたマスクは目視確認が必要。空またはほぼ全面のマスク、推論失敗、タイムアウトは例外にし、別方式に黙って切り替えない。

実行環境はPython 3.10以上、Linux x86_64、glibc 2.27以上、libstdc++。
Python標準ライブラリと同梱ネイティブライブラリだけを使い、pipパッケージ・Pillow・numpy・Python版onnxruntimeは不要。
PHPのファイル参照制限がない場合、画像処理用共通venvがあれば優先し、なければシステムのPythonを使う。
`open_basedir` が設定されている場合はシステムPythonを既定にし、アプリ外の実行ファイルをPHPでstatしない。
この場合も専用venvを使うなら `python` 引数で明示する。既存のPHPファイル参照制限を緩める必要はない。
明示する場合は `python` 引数にサーバー側で管理する絶対実行パスを渡す。
リクエスト値からPython・モデル・スクリプトのパスを受け取らない。
PHP実行ユーザーにPython起動とライブラリ読取りの権限が必要で、`open_basedir`・`proc_open` の制限も確認する。
モデルとONNX Runtimeは `python/segmentation/` に同梱し、実行時にダウンロードしない。
出典・ライセンス・配布物のハッシュは同ディレクトリの説明を参照する。

## 多点連続グラデーション

`ImageEffects::gradient(int $width, int $height, array $config): GdImage`

幅・高さは正整数、合計最大24,000,000画素。configは次の共通JSON形式。

```json
{"angle":0,"stops":[{"position":0,"color":"#C7CAD1","easing":"smooth"},{"position":55,"color":"#F7F8FA","easing":"smooth"},{"position":62,"color":"#F8F9FB","easing":"smooth"},{"position":68,"color":"#EBECF1","easing":"smooth"},{"position":100,"color":"#E1E3EA"}]}
```

- `angle`: 有限数の度数。既定0=下、90=右、180=上、270=左。360度周期。
- `stops`: 2〜256点でN−1領域。境界位置と色を共有して連続的につなぐ。
- `position`: 0〜100の数値、厳密な昇順、先頭0・末尾100が必須。
  軸は画像の実寸比で回転し、全頂点の投影範囲を0〜100へ正規化する。
- `color`: sRGBの`#RRGGBB`。各RGBチャンネルを直接補間する。
- `easing`: 次の点までの`linear`（既定）または`smooth`（smoothstep）。末尾は未使用。

単一画素や軸方向の長さが0の画像では始点色を使う。
`settings` / `loadPreset` / `savePreset` / `process`にも`background_gradient`を追加。
null（既定）で従来の2色グレー背景、指定時に多点背景を使用する。
Pythonのfigure_gradient.pyと同じ設定を使用できる。追加依存ライブラリはない。
出力は`savePng`でsRGBマーカー付きPNGへ保存する。影・照明・反射は別処理。

## 複数の背景光源

`ImageEffects::backgroundLights(GdImage $background, array $config): GdImage`

入力はsRGBの背景GD画像、最大24,000,000画素。新しいGD画像を返し入力を変更しない。
本体をcomposeする前に適用する。PHPでICC変換はしないため、prepareの既存制限を守る。

```json
{"lights":[{"center":[28,4],"radius":[20,27],"angle":-15,"color":"#FFFFFF","strength":0.65,"falloff":2,"blend":"screen"}]}
```

| 設定 | 意味・既定値 |
| --- | --- |
| lights | 順番に合成する光源配列、0〜32個。空配列は画素変更なし |
| center | 中心[x,y]。幅・高さに対する％、−100〜200。既定[50,50] |
| radius | 回転前の楕円半径[x,y]。幅・高さの％、0.1〜400。既定[30,30] |
| angle | 楕円の時計回りの角度。0は横軸、−10億〜10億度、既定0 |
| color | #RRGGBB、既定#FFFFFF |
| strength | 中心合成強度0〜1、既定0.3。0は無効 |
| falloff | 減衰指数0.25〜8、既定2（Gaussian）。大きくすると中心が広がり、小さくすると集中 |
| blend | screen（既定、明るく）／normal（色を混ぜる）／multiply（暗く） |

楕円距離dに対し強度は `strength * exp(-0.5 * d^falloff)`。
radiusは光が消える境界ではなく減衰基準。各光源ごとにsRGB各チャンネルを合成して丸める。
配列順で結果が変わる。白色背景にscreenをかけてもそれ以上明るくならない。
アルファ値は維持する。物理的な照明・影・被写体照明の再現ではなく、背景用の光の効果。
Pythonのfigure_lights.pyと共通のJSON。追加依存なし。

settings/loadPreset/savePreset/processでは`background_lights`に同じオブジェクトを指定。
null（既定）は追加なし。多点背景→複数光源→従来の単一照明→影・反射→本体合成の順。

## 接地線に沿う反射

`ImageEffects::reflectionOnSurface(GdImage $image, GdImage $mask, array $contactLine, bool $fullBody, float $opacity=.4, int $depth=480, float $verticalScale=.75, float $blur=0): GdImage`

- image/mask: 同サイズの配置済みsRGB参照画像と白黒の本体／片足マスク。最大24,000,000画素。
- contactLine: 画像内の整数座標`[[x,y],...]`、2〜画像幅個、Xの厳密昇順。Yは1〜高さ−2。
  隣接点を線形補間し、両端外は端の線分を延長する。反射元の範囲はmaskで指定する。
- fullBody: trueが必須。
- opacity: 0〜1、既定0.4。depth: 下方への減衰距離px、正整数、既定480。
- verticalScale: 縦圧縮率0.1〜2、既定0.75。1未満で反射を縦方向に圧縮。
- blur: 柔らかなぼかし0〜100px、既定0。PHPのGDとPythonのPillowではぼかしの画素結果に差がある。

RGBAの新しい反射レイヤーを返す。本体へはcomposeの最後に元画素をコピーする。
脚ごとに別マスク・接地線で呼び出す。従来reflectionの水平軸と異なり、列ごとに足裏の接地位置を変える。
Pythonのfigure_reflection_surface.pyと引数の意味を共有する。従来のreflectionとprocess既定は変更しない。
2Dの接地補正と圧縮による近似であり、隠れた面・足裏を生成せず、物理的に正しい3D鏡像は保証しない。

## 足の影専用

`ImageEffects::footShadow(GdImage $mask, array $options=[], bool $fullBody=false): array`

白い本体・黒い背景の全身マスクから、下部28%の左右の足を分離して足裏候補を求める。
足裏中央部の輪郭を直線近似し、接地影と短い投影影を作る。入力マスクは変更しない。
返り値は`layer`（RGBA GD画像）と`report`（supported/reason/footprints/settings）。
fullBody=trueで全身と確認済みの場合だけ適用する。空・端切れ・足が分離できない場合は
透明レイヤーとsupported=falseを返す。モデル・AI通信を使わず、足形状や靴底を生成しない。

| options | 意味・範囲・既定値 |
| --- | --- |
| opacity | 濃さ0〜1、既定0.3 |
| blur | 柔らかい影のぼかし0〜100px、既定5 |
| length | 投影影の長さ。画像幅の0〜50%、既定8 |
| angle | 投影方向。0右・90下・180左・270上、−10億〜10億度、既定200 |

footprintsはマスクから求めた画素座標で、写真固定の座標を使わない。画像は最大24,000,000画素。
自動の足判定は幾何的な近似。全身判定・支持具の判定は呼出側で行い、結果を目視確認する。
背景・反射へ重ねてからcomposeで本体画素を戻す。Pythonのfoot_shadowと同じ設定形式で、
ぼかし・描画エンジンの差による画素差はある。従来shadow/processの既定は変更しない。
