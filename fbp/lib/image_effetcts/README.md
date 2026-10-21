# PHP画像エフェクト

汎用のリサイズ・位置指定クロップ・余白・回転反転・文字入れ・PNG/JPEG/WebP/ZIP出力には
`compose_batch`、比較一覧は`contact_sheet`、RGB ICC→sRGB変換は`normalize_srgb`を使う。
設定JSON・容量指定・再利用テンプレートと制限は[ImageComposition.md](ImageComposition.md)。
フィギュア向けの固定サイズ・マスクは不要。

AIによる文字・不要物の除去と背景補完は[BackgroundFill.md](BackgroundFill.md)。
PHPはOpenAI API、auto-taskはCodexの画像編集ツールへ分け、候補の指定範囲だけを共通処理で合成する。

`ImageEffects.php` をrequireし、`Fbp\ImageEffects\ImageEffects` のstaticメソッドを呼ぶ。
ディレクトリ名 `image_effetcts` は指定に合わせたもの。
PHP 8.1以上・GDを使用。合成・エフェクトはPHPのみ。
本体マスクを外部から渡すか、`createMask()` でPHPからPythonを呼んで生成する。
ImageEffects自体は支持具のAI判定、外部API通信、隠れた部品の復元は行わない。AI背景補完は上記の別入口で明示的に呼び出す。

## 背景追加エフェクト

`backgroundEffects($width, $height, $config)` は `['layer'=>透過GD画像, 'config'=>展開済みJSON配列]` を返す。
PHP/GDだけで描画し、外部API・Pythonは呼ばない。旧 `backgroundLights()` とその設定は互換のため維持する。

```php
$config = ['seed'=>123, 'effects'=>[
    ['type'=>'lightning', 'count'=>2, 'region'=>[5,10,95,40], 'size'=>[15,25], 'opacity'=>0.8],
    ['type'=>'ellipse', 'center'=>[50,15], 'radius'=>[25,8], 'angle'=>-20,
     'color'=>'#B4CEFF', 'opacity'=>0.4, 'blur'=>3],
]];
$effect = ImageEffects::backgroundEffects(1080, 1440, $config);
// backgroundは本体合成前の、不透明な背景GD画像。
imagealphablending($background, true);
imagecopy($background, $effect['layer'], 0, 0, 0, 0, 1080, 1440);
// $effect['config']をJSON保存すれば図形単位で編集・再現できる。
// process/presetも ['background_effects'=>$config] を受け付ける。
```

共通JSON：ルートはseed（省略でランダム、整数0〜2147483647）とeffects（必須配列）。effectsの順でsource-over合成。
typeはspotlight（光彩）/lightning（雷）/cloud（雲）/fireworks（花火）、またはline/circle/ellipse。
4種類のプリセットには `impact`（「迫力」、0.25〜2、既定1）を指定できる。模様の広がり・線幅・粒・ぼかしの倍率で、1.7なら1.7倍。
中心範囲・個数・色・透明度は維持。展開済みJSONには倍率を適用した図形だけを保存するため、再読込で二重拡大しない。
プリセットはcount（整数1〜8、既定1）、region（中心範囲の幅・高さ％、既定[10,10,90,75]）、size（[最小,最大]、1〜70、既定[15,30]）、
angle（回転範囲、−360〜360、既定[-20,20]）、colors（#RRGGBB配列1〜16色）、opacity（0〜1、既定.65）、blur（短辺％0〜20、既定1.2、花火は2.4）。
花火は線・先端の粒・中心を柔らかくぼかす。保存済みの展開済み図形JSONはそのまま再現できる。
regionは[左,上,右,下]の各0〜100。模様の端はregionからはみ出し得る。同じ上下限で値を固定できる。
sizeは図形生成用％スケールで種類ごとに広がりが異なる。seedと寸法が同じなら同じ模様になる。

直接図形の共通キー：center（幅・高さ％、各−200〜300、既定[50,50]）、color（#RRGGBB、既定白）、opacity（0〜1、既定.5）、
angle（時計回り・右0度、−360000〜360000、既定0）、blur（短辺％0〜100、既定0）。
lineはlength（短辺％0.01〜500、既定30）、thickness（短辺％0.01〜100、既定.3）。代わりにstart/end（幅・高さ％、各−200〜300）で両端も指定できる。
start/endは両方必須、center/angle/lengthと排他。circleのradiusは短辺％0.01〜200、既定10。
ellipseのradiusは[横半径,縦半径]で各短辺％0.01〜200、既定[10,6]。

未知キー・null・不正値は例外。展開後256図形、出力24百万画素、内部描画50百万画素が上限。
エフェクトだけ長辺640px以下で描画して拡大する。本体画像は縮小しない。ぼかしは距離による輪郭フェザーで、Gaussianではない。
Python/PHP共通JSON。GDの7bitアルファ・拡大補間の差があるため、出力PNGの画素完全一致は保証しない。
旧lightsのscreen/multiplyとは異なるため、新spotlightへの設定変換は自動では行わない。

## 既存ライブラリとの関係（形式・色空間）

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

## 元画像とマスクだけの反射

`ImageEffects::reflectionFromMask(GdImage $image, GdImage $mask, float $opacity=.4, int $depth=480): array`

同じサイズのsRGB元画像と白黒の本体マスクを渡す。座標・接地線・追加AI判定は不要。
返り値は`layer`（反射RGBA）、`mask`（反射確認用白黒マスク）、`report`。
reportはsupported/reason/split_x/halves/reflected_pixelsを含む。
画像幅の中央（floor(width/2)）でマスクを左右に分割する。各半分で列ごとの最下端を取得し、
その半分の本体高さの下側10％にある輪郭点から両端12％ずつを除き、最小二乗法で底辺の傾きを求める。
同じ傾きのまま、その半分の全マスク画素が線より上に収まる位置まで基準線を下げる。
halvesはside/x_range/supported/reason/floor_y/slope/intercept/angle_degrees/contact_line/reflected_pixelsを含む。

各列の基準Yを`F(x)=round(slope*x+intercept)`とし、白マスク内の元画素`(x,F(x)-d)`を`(x,F(x)+d)`へ写す。
X不変、縦倍率1.0、横拡大・ぼかしなし。足以外のマスク領域も対象とする。
opacityは0〜1、depthは正整数の減衰距離px。濃さは`opacity × (1-d/depth)^2`。depthまたは画像下端で打ち切り、圧縮しない。
両入力は最大24,000,000画素。空の半分・底辺の点が3点未満・最下端が画像最上端または最下端の場合はその半分をスキップ。
片側だけでも基準線を求められればsupported=true。各半分の理由と描画数も確認する。
異常値は角度の絶対値60度超、近似に使うX幅がmax(2,画像幅×0.005の四捨五入)未満、
近似誤差RMSがmax(2,画像高さ×0.01)px超、基準線の下方補正がmax(3,半分の本体高さ×0.05)px超とする。
該当する半分だけabnormal_bottom_lineとしてスキップする。
両側の角度がそれぞれ±3度以内、最下端Yの差がmax(2,画像高さ×0.005の四捨五入)px以内なら、
両側の最下端Yの大きい方を共通水平線として採用。片側だけ有効ならその側だけに適用する。
report.modeはhorizontal/split/left_only/right_only/none。thresholdsとhalves.detected_angle_degreesで判定基準と補正前の角度を確認できる。
X固定の列単位の反転であり、斜め軸に対する回転を伴う鏡映ではない。中央をまたぐ被写体は中央で反射線が切り替わる。
元画像・入力マスクは変更しない。色は元画像からコピーする。反射生成自体はマスク入力のロジックのみ。
マスク作成は別工程。見えない面は復元せず、物理的に正しい3D鏡像を保証する処理ではない。

Python CLIのreflection_maskと同じ検出・写像・丸めを使用する。
PNGのGD/Pillowのアルファ変換で最大1階調の差がある。RGBと確認用マスクは同一。

## 足の影専用API

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

## 共通CLI

`php cli.php <処理名> [引数]` で、アプリ・DB・Controllerなしで実行できる。
HTTP経由の起動は拒否する。`--help` は処理一覧、`<処理名> --help` は現在の引数定義を表示する。
ローカルの `run.sh` と `figure_*.py` はこの入口へ転送する。処理の追加は `Cli.php` と
必要なImageEffectsメソッドへ集約し、ローカル側に処理名のホワイトリストや描画コードを持たせない。

```bash
php cli.php background --width 1080 --height 1920 --output background.png
php cli.php process --input normalized.png --mask mask.png --output preview
php cli.php process --input normalized.png --mask mask.png --reviewed --output result
```

従来のローカルCLIの処理名・主要引数・ファイル構成を維持する。
`process` は確認前にプレビューを保存して終了コード3、確認後はframeworkの `process()` で合成する。
終了コードは0=成功、1=入力・実行エラー、2=画素保持／出力仕様検査の不合格、3=確認待ちまたは足影・反射の判定不能。
支持具JSONは入力寸法・SHA256・各領域の座標／confidence／occludes_subjectを検査する。
本体を隠す支持具は確認済みでも除去しない。出力と付随JSONは新規パスだけを受け付ける。

`--temp-dir` または環境変数 `IMAGE_EFFECTS_TEMP_DIR` で、HEICとPythonMaskに渡す書込み可能な一時領域を指定する。
CLIは一時領域を自動作成しない。アプリから各メソッドを使う場合は従来どおりControllerの一時領域を渡す。
`--source-is-srgb` はICC付き入力がすでにsRGB化済みであることの確認であり、変換処理ではない。
マスク座標を使う単独処理は正規化済みPNGを入力する。PNGの検査はframework出力のsRGBチャンクを認識する。
PHP/GDの制限・画素結果を基準とし、旧Pillow版との完全一致は保証しない。
