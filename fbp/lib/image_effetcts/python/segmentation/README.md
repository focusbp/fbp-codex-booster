# ローカル背景分離の同梱物

既存の背景分離ライブラリから再利用。PHPが画像をデコードし、Python標準ライブラリのワーカーがBMPを読みマスクPNGを返す。Pillowやアプリの管理画面には依存しない。

元画像の画素はPHPの合成処理で使用する。U2NETPの出力は本体の候補マスクだけであり、元画像・本体・隠れたパーツを生成しない。画像の外部送信、実行時のダウンロード、pip、利用者のホームへのキャッシュはない。推論失敗・同梱物のハッシュ不一致はエラーにし、旧方式へ戻さない。

実行条件はLinux x86_64、glibc 2.27以上、libstdc++、Python 3のctypes。モデルとCPU runtime、Cブリッジをアプリに同梱するためPython追加パッケージは不要。サーバー上でのコンパイルも不要。既存のGD・ZipArchive・HEIC変換器・ICC変換条件は従来どおり。別環境への配布ではこの実行条件と実加工を確認する。

- [U2NETPの配布元・正規化仕様](https://github.com/danielgatis/rembg/blob/main/rembg/sessions/u2netp.py)、[ONNX重み](https://github.com/danielgatis/rembg/releases/download/v0.0.0/u2netp.onnx)。配布元MD5 `8e83ca70e441ab06c318d82300c84806` と照合済み。
- [U-2-Net](https://github.com/xuebinqin/U-2-Net)、Apache 2.0。`U2NET-LICENSE` を同梱。
- 正規化を参照したrembgはMIT。`REMBG-LICENSE` を同梱。画像の縮小は独自の双線形補間、輪郭は元RGBをガイドに補正する。
- [ONNX Runtime 1.18.1](https://github.com/microsoft/onnxruntime/releases/tag/v1.18.1) の公式Linux x64 CPU配布物。`libonnxruntime.so.1.18.1` を通常ファイル `libonnxruntime.so` として同梱し、`onnxruntime_c_api.h`、MITライセンス、ThirdPartyNoticesを保持。テレメトリを無効にしCPU 1スレッドで実行する。
- `segment.c` はアプリのブリッジ・輪郭補正。`segment.so` は次のコマンドでこのディレクトリ内でビルドできる。生成後は `assets.json` と `segmentation.py` のSHA256を照合・更新して実加工を再確認する。

```sh
gcc -O2 -fPIC -shared segment.c -lm -o segment.so
```

自動マスクは細い武器、同系色の部品、装甲の隙間、支持具を誤認しうる。画素差分0は選択範囲の画素保持を示すもので、切り抜き精度の保証には使わない。本ツールでは確認画像を提示し、支持具は多角形で除外する。マスクを修正する場合は同じ寸法の白黒PNGを用意する。全身写真と反射の床位置は明示指定する。アプリ側の承認・ZIP機能は本ツールに含めない。
