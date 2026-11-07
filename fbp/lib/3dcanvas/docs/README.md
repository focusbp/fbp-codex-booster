# 3D Canvas 素材・部品・表示設定

シーンはPHPの生成クラスで組み立て、Controllerで表示・保存する。座標と長さはmm、Yが上、回転は度。マウスとタッチの操作はデフォルトで有効。

```php
$scene = $ctl->create_3dcanvas();
$scene->add_box('floor', [
    'size' => [6000, 100, 5000], 'position' => [0, -50, 0],
    'material' => ['texture' => 'wood_oak'], 'cast_shadow' => false,
]);
$scene->add_part('table_oak', 'meeting_table', ['position' => [0, 0, 0]]);
$scene->add_part('chair_fabric', 'chair1', ['position' => [0, 0, 1000], 'rotation' => [0, 180, 0]]);
$scene->set_render([
    'shadows' => ['enabled' => true, 'quality' => 'medium'],
    'ao' => ['enabled' => true, 'radius' => 300, 'intensity' => 0.8],
]);
$scene->set_lighting(['sun' => ['position' => [-8000, 18000, 10000], 'color' => '#fff4e5']]);
$ctl->draw_3dcanvas('scene_view', $scene);
```

テンプレートには幅・高さのある一意のIDを配置する。

```html
<div id="scene_view" style="width:100%;height:600px"></div>
```

- [テクスチャ一覧・指定方法](textures.md)
- [部品一覧・追加方法](parts.md)
- [影・AO・照明の設定](render.md)
- [Controller・保存・ライフサイクル](../../../docs/3dcanvas-design.md)

画像は `texture/`、部品JSONは `parts_json/` が編集元。アプリにはIDで指定し、保存JSONへ配信URLやファイルパスを持たせない。部品は汎用図形へ展開して保存する。保存済みシーンは素材・部品のカタログ変更で形状を書き換えない。既存の素材IDは削除せず、新しい外観には新IDを発行する。

配信用JSを変更したときはライブラリの `build.sh` で生成物を更新する。既存JSONでは影・AOを無効とする既定値を補い、従来の表示を維持する。
