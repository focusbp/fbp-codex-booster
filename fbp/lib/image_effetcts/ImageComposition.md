# 任意サイズの画像配置・一括出力

`compose_batch --config jobs.json --output new-directory [--zip]`。

### 矢印・囲みレイヤー

`type: "arrow"` / `"rect"` は元画像の原寸pxで `x,y,x2,y2`（整数、画像内）を指定する。
囲みは2点を対角とする枠線、矢印は1点目から2点目へ向く開いた矢尻を描く。
`color` は既定 `#DE3029`、`stroke_width` は1〜64px（既定4）。
矢印のみ `head_length` は1〜1000px（既定18）、`head_angle` は0より大きくπ/2未満のラジアン（既定0.5）。同一点への矢印は拒否する。
線の画像外部分はクリップされる。指定順に画像・図形・既存の日本語textレイヤーを重ねられる。
SVGファイル自体は読み込まず、呼出し側で注釈座標を原寸に換算する。

```json
{"type":"arrow","x":30,"y":90,"x2":180,"y2":40,"stroke_width":4,"head_length":18}
```
画像の切り抜き、リサイズ、配置、文字入れ、PNG/JPEG/WebP出力をPHP/GDで行う。
明示的なRGB ICC→sRGB変換のみLittle CMSを使用する。画像生成・隠れた部分の復元は行わない。

```json
{"jobs":[
  {"name":"desktop-a.png","base":"page.png","layers":[
    {"input":"banner.png","x":100,"y":200,"width":800,"height":400,"fit":"cover"}
  ]},
  {"name":"resized-banner.png","width":1495,"height":732,"background":"#FFFFFF","layers":[
    {"input":"banner.png","width":1495,"height":732,"fit":"contain"}
  ]}
]}
```

- 入力パスは設定JSONのあるディレクトリ基準。絶対パスも可。URL不可。
- base指定時は向き補正後の寸法と画素を保持し、width/height/backgroundは併用不可。
  ベースなしはwidth/height必須、backgroundは#RRGGBBまたはtransparent（既定白）。
- layersは指定順に合成。x/yは左上からの整数px（省略0）、width/height必須。
  配置矩形はキャンバス内に収める。差し込み外のベース画素は変えない。
- fitはcontain（既定、等比で全体を収め中央配置）、cover（等比で切り抜き）、stretch（指定寸法へ変形）。
  containの余白はベース／背景が見える。文字の切れや画像の変形を目視する。
- layerのcropは任意の元領域[x,y,width,height]。EXIF向き補正後の整数pxで指定し、その領域へfitを適用。
- 入力はPNG/JPEG。EXIF向き補正とPNGアルファ合成に対応。
  ICC付き入力はnormalize_srgbを指定するか、事前にsRGB化済みの場合だけ--source-is-srgbで確認する。
- 1画像24百万画素・40MB以内。1バッチ1〜100件、合計240百万出力画素、各32レイヤー以内。
- nameは英数字で始まる英数字・_・-の名前＋.png/.jpg/.jpeg/.webp。拡張子で実際の形式を選ぶ。
  重複・未知キー・null・範囲外は拒否。
- 出力ディレクトリは新規限定。全入力と設定を事前検証し、通常例外では当該実行の出力を除去。
  強制終了時の残存ディレクトリは上書きせず、別の出力先で再実行する。
- PNGはsRGBマーカー付き。report.jsonに寸法・実品質・バイト数・入出力SHA256・配置設定を記録。
  reportは入力パスを含むローカル参照用。--zipのimages.zipには納品画像だけを含める。
  ZIPにはPHP zip拡張が必要。外部コマンド・API・モデル取得は使用しない。
- 成功終了は機械処理完了。位置・文字・PC/SPの組合せを目視して納品する。

入口はphp cli.php、ローカルではimage_effects/run.sh。案件別コードは不要でJSONだけを用意する。

## 切り抜き位置・回転・反転

画像layerに以下を指定できる。type省略はimage。

- `position: [x,y]`：各0〜1。coverでは切り抜く範囲の寄せ、containでは余白内の配置位置。
  [0,0]が左上、[0.5,0]が上中央、[1,1]が右下。省略は中央。
- `focus: [x,y]`：cover専用。顔や建物など注目点の画像内相対位置を指定し、その点を中心に収める。
  端では画像外へ出ないよう補正する。positionと排他。自動顔検出ではなく手動の注目点指定。
- `rotate`：時計回りの角度−360〜360（小数可、既定0）。透過背景で回転後の全体を確保。
- `flip`：none（既定）/horizontal/vertical/both。
- 適用順はEXIF向き補正→crop→rotate→flip→fit→配置。
  cropは向き補正直後のpx、focus/positionは回転・反転後の画像を基準にする。

## 余白・キャンバス拡張

jobの`padding: [top,right,bottom,left]`で、合成後の外周へ整数pxの余白を追加する。
baseなしはbackground、baseありはmatte（既定白）を余白色に使う。transparentも可。
既存の内容は拡縮せず右・下へ移動する。width/heightは余白を足す前のサイズ。
指定の最終寸法に収めるだけならpaddingではなく、その寸法のキャンバス＋containを使う。

## 日本語の文字入れ

```json
{"type":"text","text":"沖縄の特等席\n勝連で暮らす","font":"font.ttf",
 "font_size":24,"color":"#202020","align":"center","line_height":1.4,
 "x":40,"y":50,"width":600,"height":150}
```

fontは読取可能なTTF/OTF/TTC。日本語は対応グリフを持つフォントを選ぶ。
font_sizeはFreeTypeのポイント単位、1〜1000の整数。colorは#RRGGBB（既定黒）、
alignはleft/center/right（既定left）、line_heightは1〜5（既定1.4）。
描画領域の左上がx/y。明示改行に対応し、領域を超える文字は描画前に拒否する。
自動折返し・自動縮小・縦書き・グリフの自動補完はしない。
フォントファイルのhashもreportへ記録する。mbstring・GD FreeTypeが必要。

### 文字本体の太さ

text layerの`body_width`は0〜32の整数px（既定0）。指定フォントの元字形を
文字本体の色で円形に広げ、文字本体を太くする。例：`"body_width": 3`。
フォントファイルは通常・太字のどちらも指定でき、太字フォントへ追加の太さも指定できる。
これはフォント自体のウェイト変更ではなく、描画による太らせ処理。太くしすぎると文字内部の隙間が狭まる。
袋文字と併用すると外縁も本体の拡張分だけ外へ移動し、縁の指定幅を保つ。
領域検査は本体の拡張＋最大縁幅を含む。省略・0は従来と同じ出力。

### 袋文字（単色・多重の縁取り）

text layerに`outlines`を指定する。

```json
{"type":"text","text":"こんにちは","font":"font.ttf","font_size":80,
 "color":"#E95E9D","outlines":[{"color":"#FFFFFF","width":6},{"color":"#173454","width":12}],
 "x":30,"y":30,"width":840,"height":180}
```

各要素はcolor（不透明な#RRGGBB）とwidth（1〜32の整数px）。0〜4重まで指定できる。
widthは太さ調整後の文字本体の外側へ広げる半径で、内側から外側の順に厳密な昇順にする。
例の白6px＋紺12pxは、白を6px、紺をその外へさらに約6px広げる二重縁。
円形の描画位置を重ねて丸い縁を作り、外縁→内縁→元の文字の順に描く。
文字本体の追加太さはbody_width、フォント本来の太字は太字のフォントファイルで指定する。
縁取りの最大幅とアンチエイリアス端の余裕を含めて幅・高さ・行間を検査し、切れる設定は事前拒否する。
省略または空配列で縁なし。長文・太い多重縁による過大な描画を避けるため、
本体を太くする場合の(2×body_width＋1)²と、各縁の(2×(body_width＋width)＋1)²の
合計×文字数が250,000を超える指定は拒否する。本体0の場合は本体拡張の計算を加えない。

## JPEG/WebP・容量制限

jobの`quality`は1〜100（既定85）、`min_quality`は1〜quality（既定40またはqualityの小さい方）。
`max_bytes`を指定すると、JPEG/WebPは寸法を固定したまま範囲内の品質を探索する。
条件に収まらない場合はエラーにし、通常例外では当該バッチの生成物を除去する。
品質指定はPNGには適用せず、容量超過時は停止する。自動的に寸法を縮小しない。
JPEGは透明部分をmatte（#RRGGBB、既定白）で埋める。WebPにはGDの対応が必要。
EXIFなどの元メタデータは出力へ引き継がない。PNG以外へICCタグを埋め込む処理はない。

## sRGB正規化

`normalize_srgb --input source.jpg --output new.png`、またはjobの`normalize_srgb: true`。
後者はbaseと全画像layerを合成前に変換する。文字の色値はsRGB指定。
JPEG APP2・PNG iCCPのRGB ICCを抽出し、Little CMSで画素をsRGBへ変換する。
未タグのRGBはsRGBと仮定して画素を保持。透過アルファと向き補正を維持する。
CMYK・グレースケールICCは非対応として拒否する。16bitの精度はGDの8bitへ下がる。
プロファイル破損・4MB超過・ICC分割欠損・依存機能不足で停止し、単なるタグ除去へ代替しない。
ICC付き変換にはPHP FFIとliblcms2.so.2が必要。この環境のCLIで動作確認済み。
処理ライブラリは[Little CMS公式API](https://github.com/mm2/Little-CMS/blob/master/include/lcms2.h)を使用する。

## 比較一覧

`contact_sheet --config sheet.json --output new-directory [--zip]`。
itemsは1〜16個の`{"input":"preview.png","label":"A案"}`。
columns（1〜10、既定3）、cell_width/height（各1〜10000、既定400/300）、gap（0〜1000、既定20）、
label_height（1〜1000、既定50）、font_size（既定20）で並べる。
font省略時はframework同梱のIPAexゴシック。background/colorは既定白/濃灰。
name省略はcontact-sheet.png。quality/min_quality/max_bytes/normalize_srgbも利用可能。
余ったセルは背景色。文字のはみ出し・全体24百万画素超過は拒否する。
同梱例は`presets/contact-sheet.example.json`。

## 設定テンプレート

設定ルートのtemplatesに名前→job既定値のマップを指定し、jobのtemplateで選択する。
CLIの`--templates template-map.json`で外部マップも読める。同名の重複は拒否。
job指定値でトップレベルの項目を上書きし、layers等の配列は全体を置き換える。テンプレート継承はしない。
テンプレート内の入力パスもジョブ設定JSONのある場所を基準に解決する。
`presets/composition-sizes.json`にはPC/SPメイン、サブ、SNS正方形・縦長のサイズを同梱。
作成例は`presets/production-jobs.example.json`。source.jpg/font.ttfとcrop座標を手元の素材に合わせて変更し、
上記サイズマップを--templatesで読み込む。入力寸法やライセンスに適したフォントを使う。
