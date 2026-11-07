# 影・AO・照明

```php
$scene->set_render([
    'shadows' => ['enabled' => true, 'quality' => 'medium'],
    'ao' => ['enabled' => true, 'intensity' => 0.8, 'radius' => 300, 'quality' => 'medium'],
    'exposure' => 1.0,
]);
$scene->set_lighting([
    'ambient' => ['color' => '#ffffff', 'ground_color' => '#aaa59a', 'intensity' => 0.5],
    'sun' => ['color' => '#fff4e5', 'intensity' => 2.0, 'position' => [-8000, 18000, 10000], 'target' => [0, 0, 0]],
    'environment' => ['preset' => 'studio', 'intensity' => 0.5],
]);
```

どちらも指定した項目だけを現在値へ反映し、JSONのrender／lightingに保存する。省略した古いJSONは旧表示相当の値（影・AO無効、露出1、従来の照明）で復元する。

| 設定 | 範囲・意味 |
| --- | --- |
| shadows.enabled | true／false。太陽光による影 |
| shadows.quality | low／medium／high。影画像1024／2048／4096px |
| ao.enabled | true／false。GTAOによる壁際・接地部の近接陰影 |
| ao.intensity | 0〜2。0で陰影を掛けない |
| ao.radius | 1〜5000mm。陰影を探す周囲の距離。初期300mm |
| ao.quality | low／medium／high。サンプル数8／16／32、AO計算解像度は表示の35%／50%／100% |
| exposure | 0.1〜4。画面全体の露出 |
| ambient | 空側colorと地面側ground_color、intensity 0〜8 |
| sun | color、intensity 0〜8、positionとtarget各±1000000mm |
| environment | presetはstudio／none、intensity 0〜8 |

影の範囲はシーンの寸法と光の向きから自動計算する。物体ごとにcast_shadowとreceive_shadowをtrue／falseで指定できる。グループでfalseにすると子にも反映する。ガラス等はcast_shadow=false、床はreceive_shadow=trueを基本にする。

```php
$scene->add_box('glass', [
    'size' => [1500, 1100, 10],
    'material' => ['color' => '#c7e3ec', 'opacity' => 0.3],
    'cast_shadow' => false,
]);
```

ブラウザー側では `canvas.setRender({ao:{enabled:false}})`、`canvas.setLighting({sun:{intensity:1.5}})` でも変更できる。getSceneに変更結果が含まれるため、通常のappcon保存で再現できる。exportImageも現在の影・AO・照明を含めて出力する。

AOは画面の深度と法線から計算する近似表現。操作・視点変更後に再計算し、静止中は描画を繰り返さない。高品質設定は描画負荷が増えるので、初期はmediumを使う。
