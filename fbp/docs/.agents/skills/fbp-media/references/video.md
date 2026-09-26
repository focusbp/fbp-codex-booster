# 動画の保存・配信

## 保存と公開範囲
- 実行時にアップロード・生成する動画は DB の `type=file` と `classes/data/upload/` を使う。公開動画でも `classes/app/images/` に実行時配置しない。
- 保存は標準のファイル保存フローを使い、作品レコードと所有者・ファイルIDを紐付ける。動画に画像用リサイズ処理を適用しない。ポスター画像を生成する場合は別の画像として保存・管理し、動画と同じ公開範囲で配信する。
- 許可する容量・形式・コーデックは対象ブラウザーと要件に合わせて決める。拡張子やアップロード申告MIMEだけで内容を判断しない。`type=file` で保存できることとブラウザーで再生できることは別。
- 非公開の `view_video` はログイン必須、公開する `view_public_video` のみコンストラクタの公開関数リストに含める。各要求でレコードの閲覧権限または公開状態を確認する。

## 表示例
配信関数はアプリ側で実装する。以下はURL生成・画面表示の例であり、動画応答処理そのものではない。

```php
// 非公開動画。公開動画では公開状態を検証する view_public_video を指定する。
$ctl->assign("video_url", $ctl->get_APP_URL("media", "view_video", [
    "id" => $row["id"],
]));
```

```smarty
<video controls playsinline preload="metadata"
       src="{$video_url|escape}"
       style="max-width:100%;height:auto;">
    このブラウザーでは動画を再生できません。
</video>
```

ポスターを付ける場合は、同じ作品の画像配信関数で生成したURLを `poster` に指定する。非公開のポスターを固定公開素材のURLへ向けない。動画の表示には `download-link` や全量のbase64/blob変換を使わず、配信関数のURLを指定する。

## 再生用応答の実装条件
- 現行の `res_saved_file()` は `Content-Disposition: attachment` で全量を返すダウンロード用。これを呼ぶだけでinline再生・Range対応が完了したと扱わない。`res_saved_image()` も動画用ではない。
- 権限確認後に `$ctl->res_saved_media($stored, ["cache" => false])` を使う。共通APIがMIME判定、inline配信、GET/HEAD、単一byte Range、分割送信、セッションロック解放、応答終了を担当する。公開キャッシュが必要な場合だけ `["cache" => true, "max_age" => 3600]` を指定する。
- 同APIは実体のMIMEを判定し、対応する画像・動画・音声だけを返す。未対応形式やSVGなどの能動コンテンツは415、保存領域外や不明なファイルは404となる。
- 共通のメディアガードによる追加認可は行わない。呼出し元でログイン・所有者・公開状態を確認し、非公開では `cache=false` を使う。`res_saved_media()` 自体はログイン要否を変更しない。
- 既存の `res_saved_image()`・`res_saved_file()` は互換維持のため残る。動画の通常再生では独自Range処理を複製せず `res_saved_media()` を使う。
- レコードIDから権限確認済みのファイルを解決し、保存領域内の読み取り可能な実体であることを確認する。クライアントから絶対パスや任意の保存名を受け取って読み出さない。
- 正しい動画MIME、`Content-Disposition: inline`、`X-Content-Type-Options: nosniff` を返す。非公開では成功・拒否とも `Cache-Control: private, no-store` とし、公開用キャッシュと混在させない。
- 認証・権限確認は通常GETだけでなくRange要求・HEADにも適用し、拒否時は動画バイトを返さない。認証確認後にセッションへの書込みを終え、ストリーム送信前にセッションロックを解放して他の画面操作を妨げない。
- 大きい動画を一括でメモリーへ読み込まず、必要範囲を分割して送信する。通常のHTML・JSON応答が末尾へ混ざらないよう、動画送信完了後は応答を終了する。

## RangeとHEAD
[RFC 9110](https://httpwg.org/specs/rfc9110.html#range.requests) に従う。

- 全量GETは200と全長、対応する単一byte Rangeは206と `Content-Range`・部分長を返す。送信バイト数をヘッダと一致させる。
- 開始位置のみ・末尾からの指定も扱う。満たせない範囲には416と `Content-Range: bytes */<全長>` を返す。
- 未対応の複数範囲を単一範囲と誤解釈しない。複数範囲を実装しない場合はRangeを無視して全量200とする方針などを明示する。
- HEADは本文なし。RangeはGETで扱う。`res_saved_media()` はvalidatorを発行しないため、`If-Range` 付き要求は全量200とする。未対応の複数範囲・不正形式もRangeを無視して全量200とする。
- `Accept-Ranges: bytes` は実際にRangeを処理できる場合に設定する。

## 実装時の検証
- 実動画でPC・対象モバイルブラウザーの再生開始、途中へのシーク、終端付近の再生を確認する。保存成功・HTTP 200だけで再生可能としない。
- 全量GET、先頭/途中/末尾のRange、範囲外、HEADでステータス・ヘッダ・実際の本文長を確認する。
- 非公開では本人の成功、未ログイン・別ユーザーの拒否をRange付きでも確認する。ポスター・ダウンロード・標準の別取得経路も同じ公開範囲であることを確認する。
- 公開では匿名再生と非公開・削除済み作品の拒否を確認する。削除・公開終了時のキャッシュ方針も要件に合わせる。
