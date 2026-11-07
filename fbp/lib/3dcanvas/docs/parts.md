# 部品JSON

`parts_json/catalog.json` のIDを使う。部品はすべて既存の汎用図形とグループで構成し、専用の描画処理を追加しない。

| ID | 内容 | 標準寸法 幅×高さ×奥行（mm） |
| --- | --- | --- |
| door_single | 片開きドア・枠・取手（開いた状態） | 1000 × 2170 × 150（枠寸法） |
| window_double | 2枚窓・枠・透過ガラス | 1800 × 1300 × 160 |
| table_oak | 木製テーブル | 1400 × 758 × 800 |
| chair_fabric | 布張り椅子・金属脚 | 470 × 880 × 470 |
| cabinet_low | 扉・取手付きローカビネット | 1400 × 800 × 470 |

```php
$scene->add_part('door_single', 'consultation_door', [
    'position' => [2500, 0, 1200],
    'rotation' => [0, 90, 0],
    'scale' => [0.9, 1, 1],
]);
```

原点は床面の中心、正面は+Z。窓は下枠の底がY=0なので、腰高さをpositionのYに指定する。door_singleは左側の吊元から55度開いた固定構成で、枠の奥行以外に開いた扉の張出しがある。位置・回転・拡大率・visible・parentを指定できる。

追加時にルートgroupを作り、子IDを `consultation_door__leaf` などへ置換する。同じ部品を何個置いてもIDが衝突しない。通常のgetScene、setColor、setVisibleから展開後の子を操作できる。展開に失敗した場合はシーンを部分更新しない。

## 部品の追加

1. 固定JSONにschema_version=1とobjects配列を定義する。objectsの各要素はSceneと同じ図形・材質仕様を使う。
2. 子のparentは部品内のgroupのIDを指定し、外部部品を参照しない。
3. catalog.jsonへfile、title、size_mm、origin、front、sourceを登録する。
4. add_partで検証する。JSや任意ファイルパスはJSONに含めない。

保存されるのは展開後の図形。共通部品を更新しても、保存済み案件の形は維持する。部品ファイルはシーンではないためrevision、views、renderを持たせず、表示設定は配置先シーンに指定する。
