# テクスチャ

`texture/catalog.json` に登録したIDを `material.texture` に指定する。

| ID | 素材 | 画像1枚が表す寸法（mm） | 色画像 |
| --- | --- | --- | --- |
| wood_oak | オークの木床 | 1200 × 1800 | [画像](../texture/wood/wood_oak_color.png) |
| wood_walnut | ウォールナット | 1200 × 1800 | [画像](../texture/wood/wood_walnut_color.png) |
| wall_plaster | 白い塗り壁 | 1000 × 1000 | [画像](../texture/wall/wall_plaster_color.png) |
| tile_stone | 石調タイル（600mm角） | 1200 × 1200 | [画像](../texture/tile/tile_stone_color.png) |
| fabric_linen | リネン調の布 | 500 × 500 | [画像](../texture/fabric/fabric_linen_color.png) |

各素材は512pxの色・法線（凹凸）・粗さの3画像を持つ。色だけsRGB、法線と粗さは数値データとして読み込む。旧 `wood`、`tile`、`checker` は従来の簡易模様として引き続き利用できる。

```php
'material' => [
    'texture' => 'wood_oak',
    'color' => '#ffffff', // 画像に掛ける色。省略時は素材の標準値
    'repeat' => [1, 1], // 標準の実寸に対する繰返し倍率
    'texture_rotation' => 90,
    'normal_strength' => 0.5,
    'texture_size' => [1200, 1800], // 省略すればカタログの実寸
]
```

箱・角丸箱・平面・押し出し形状は面の位置から実寸UVを作り、親グループの拡大率も反映する。球などは通常のUVにrepeatを適用する。repeatは0.01〜100、texture_sizeは各1〜100000mm、normal_strengthは0〜2、texture_rotationは±360度。色・粗さの値は画像へ乗算する。粗さ画像の値をそのまま使う標準倍率は1。

## 追加方法

1. 画像3枚をtextureの用途別フォルダに置く。継ぎ目の目立たない画像を使う。
2. catalog.jsonへID、title、size_mm、maps（color／normal／roughness）、標準material、source、licenseを登録する。
3. 生成・検証クラスで同じIDを指定し、模様の実寸と向きをサンプルで確認する。

配信は共通の `canvas3d_assets/texture` が登録IDとmap種別だけを受け付ける。libの直接配信は禁止したままにする。素材は公開してよいフレームワーク所有の固定画像に限定し、顧客画像や保存モデルを置かない。図面などの非公開データは従来どおりアプリの権限確認付き配信を使う。

## 出典・ライセンス

同梱の5素材は `tools/generate_assets.py` で作成したオリジナルの手続き生成画像。外部の写真・生成サービス・第三者の素材集は使用していない。フレームワークのプロジェクトライセンスに従う。再生成時だけPython、numpy、Pillowが必要で、通常の表示・配信には不要。
