# 3D Canvas 初期実装

## 構成

汎用図形・材質・グループ階層と移動／回転の周期アニメーションを組み合わせる。ウサギ専用API、アプリ独自生成器、PDF自動読取りは提供しない。ウサギと医院パースはapp-fwtest側のサンプルで、共通ライブラリに案件固有モデルは含めない。

- 共通実装は `fbp/lib/3dcanvas`、Sceneインターフェースは `fbp/interface/canvas3d/Scene.php`。
- Three.js 0.180.0を同梱し、`build.sh`で `fbp/js/3dcanvas-runtime.js` を生成する。libの直接配信禁止を維持する。利用者のnpm実行や外部CDN接続は不要。
- JSONは標準dataディレクトリの `_3dcanvas/<model_id>/scene.json` に限定する。DBには32文字のmodel_idだけを保持する。
- schema_version=1、revision、units=mm、background、objects、views、animationsを保存する。Yが上の右手系、回転は度。平面XYの押し出し方向は+Z。

## Controller API

| API | 契約 |
| --- | --- |
| `create_3dcanvas(): Scene` | 生成クラスを返す |
| `create_3dcanvas_json(Scene $scene): string` | 新規保存しmodel_idを返す。保存版は1 |
| `get_3dcanvas_json(string $model_id): Scene` | JSONを検証し生成クラスとして復元 |
| `save_3dcanvas_json(string $model_id, Scene $scene, int $expected_revision): int` | 版照合後に保存し新revisionを返す。入力インスタンスは変更しない |
| `draw_3dcanvas(string $tag_id, Scene $scene, array $options = []): void` | appconのcanvas3d応答に描画指示を追加 |
| `destroy_3dcanvas(string $tag_id): void` | appcon応答に破棄指示を追加 |

```php
$scene = $ctl->create_3dcanvas();
$scene->add_box('wall', [
    'size' => [5000, 2600, 120], 'position' => [0, 1300, 0],
    'material' => ['color' => '#eeeeee', 'roughness' => 0.8],
]);
$ctl->show_multi_dialog('preview', 'preview.tpl', '立体表示', 1000);
$ctl->draw_3dcanvas('scene_view', $scene);
```

テンプレートには幅・高さのある `<div id="scene_view" style="width:100%;height:520px"></div>` を置く。IDは表示中の文書全体で一意にする。複数ダイアログでは呼出側で別IDを発行する。重複IDは描画を拒否する。

アプリは通常の認証下でレコードの閲覧／編集権限を確認し、DBのmodel_idを解決して呼ぶ。model_idから直接取得できる共通公開エンドポイントは作らない。原図も権限確認済みのアプリ関数から配信する。

## 図形と材質

- `add_box`、`add_sphere`、`add_cylinder`、`add_cone`、`add_plane`、`add_circle`、`add_torus`、`add_rounded_box`、`add_extrude`、`add_group`。引数は `($id, $options)`、戻り値はScene。`add($type, $id, $options)` でも同じ固定種別を指定できる。
- 共通指定はposition、rotation、scale（各3要素配列）、visible、parent（親groupのID）。図形ごとのsize、radius、height、tube、points、depthを指定する。
- 建築の開口は箱を上下左右に分割し、階段は段ごとの箱で作る。建築専用APIは初期版に追加しない。
- materialはcolor、roughness、metalness、opacity、emissive、emissive_intensity。textureは内蔵checker／wood／tileのみで、repeatとtexture_rotationに対応する。任意URL・ファイルパスは受け付けない。
- 共通の照明と室内環境マップを用意する。鏡面のリアルタイム反射・外部テクスチャ素材管理は初期対象外。
- `set_view($name, ['position'=>[...], 'target'=>[...], 'fov'=>45])` で視点を登録する。
- `animate($id, ['property'=>'rotation', 'axis'=>'y', 'amplitude'=>10, 'period'=>3, 'phase'=>0])` は元の姿勢を中心とする正弦波の動き。positionも指定可能。動作の瞬間状態は保存しない。
- `to_array()` でデータ化し、POSTされたJSONは `Scene_class::from_json()` で復元する。未知の仕様・キー・参照不正・循環階層は拒否する。上限はJSON 2MB、部品1500、階層32、視点16、動作128。

## appconとブラウザーAPI

appconがHTMLを反映した後にcanvas3d指示を処理する。必要時のみ共通モジュールを読み込み、sceneは応答にオブジェクトとして同梱する。HTMLへのJSON埋込み・eval・別fetchは不要。

```js
const element = document.getElementById('scene_view');
const canvas = await FBP3DCanvas.ready(element);
if (canvas) {
    canvas.setView('birdseye');
    const data = canvas.getScene();
    const png = await canvas.exportImage(); // PNG Blob
}
```

デフォルトで左ドラッグ回転、ホイールズーム、右ドラッグ平行移動を有効にする。タッチは1本指で回転、2本指でズーム・移動。`interactive=false` で無効にできる。drawのviewは登録視点名、animate=falseは自動動作停止。

表示APIはsetView、setColor、setVisible、getScene、exportImage、pause、resume、getState、destroy。getSceneは現在の視点をviews.currentへ反映する。表示内の色・視点変更はメモリー上だけで、保存はアプリのappcon送信処理から明示的に行う。従来のappconコールバックは3D準備完了を待たないためreadyを使う。

再描画・DOM削除・画面終了時は旧表示とGPU資源を破棄し、ロード中の古い指示も取り消す。サイズ変更に追従し、非表示・画面外では停止。静的sceneは必要時描画。動きを減らす設定で自動動作を停止する。WebGL非対応・コンテキスト喪失時は案内を表示し、元のsceneを保持する。

## 保存整合性

保存ルート外参照・シンボリックリンクを拒否し、破損を空sceneへ置換しない。更新はController管理の専用 `_3dcanvas/lock` FFMで直列化する。最新revision照合から一時ファイルへの書込み・原子的置換まで保持し、JSON本体を版の正本とする。アプリ独自flockは使わない。

業務DBは先に開き、追加DBによるロック再取得後にDB紐付けを再読込みする。読取り専用Controllerの保存は拒否する。新規作成とDB紐付けは一括トランザクションではない。失敗復旧・削除・複製はアプリ側で管理し、共有や自動削除は初期版では行わない。

業務JSONを通常のソース同期・定義配布で上書きしない。バックアップ・移管ではDB紐付けとモデルフォルダを組で扱う。

## 検証用サンプル

app-fwtestのsample_canvas3dで汎用部品のみのウサギと医院パースを同時表示する。医院はTask:4780の同一原図ページ画像の手動トレース。高さ等は仮定であり、建築寸法精度の認証・PDF自動解析は含まない。元の医院PDF実体の登録は行っていない。

サンプル専用DBに名前とモデルフォルダIDを保存し、色・視点変更、保存・再読込み、PNG出力を試せる。継続用のスキーマ／保存境界・別プロセス競合・ブラウザー操作テストはapp-fwtestのtests/canvas3d。本番配布は別工程。
