# 3D Canvas

汎用図形・材質・グループと宣言的なアニメーションの共通ライブラリ。
アプリは `$ctl->create_3dcanvas()` で生成し、`$ctl->draw_3dcanvas()` でappcon応答へ追加する。
マウス／タッチ操作はデフォルトで有効。

API、JSON保存、権限、サンプルの詳細は [利用仕様](../../docs/3dcanvas-design.md) を参照。

ブラウザー側を変更した場合は `bash build.sh` で配信用JSとライセンスを更新する。
ビルドは固定版esbuild 0.25.0、エンジンはThree.js 0.180.0。
利用先でのビルドやCDN接続は不要。vendorの追加モジュールは同梱版Three.jsへの相対importに変更している。
