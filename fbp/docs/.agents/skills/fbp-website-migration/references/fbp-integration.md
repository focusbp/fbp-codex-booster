# FBPアプリとCLI取込み

## 公開・管理画面

記事・素材・問い合わせの管理ノートを必要に応じて作る。CRUDはStandard Screenを基本とし、既存会員・LINE等のテーブルと権限を保持する。テーマは案件ごとに適応し、顧客HTML/画像を共通Skillへ同梱しない。

public_pagesの明示メソッドを入口にし、get_APP_URLで生成する。固定ページtop/contact/terms、一覧blog/event、詳細article_detail?id等。FBPはmethod_existsで入口を確認するため、__callだけで任意スラッグを受ける構成にしない。管理側で追加したページにはpage_detail?id等を使う。

旧URL対応表を保持し、制御できる設置先で互換入口/301を設定する。制御できない旧サーバーの転送を設定済みとしない。内部リンクとcanonicalを揃え、カテゴリ・ページ番号・記事IDは通常のクエリとして検証する。

本文は許可したHTML/属性/URLだけ表示し、公開範囲と予約日時を確認する。内部ヘッダー/フッター行や非公開素材をID直指定で公開しない。画像/srcset/CSS背景・アイキャッチ・ギャラリー・短縮コードを素材とURL対応表へ接続する。faviconはヘッダーロゴと共有するか、合意したアイコンをheadへ設定する。

フォームはFBP自身の入力・検証・保存・通知へ再構築する。テストから本番宛先へ送信せず、失敗・二重送信・期限切れを扱う。実送信/着信確認は、その範囲の許可後に実施する。

## URLの精査・変更

移行元URLと現在の移行先URLを調べ、識別キー（例：`path`、`page`、`view`）やパスによる振分けで切り替えている各ページを、FBPのfunctionへ対応付ける。キー名を固定せず、実際のルーティング処理と値の意味を確認する。記事ID・検索条件・ページ番号等の通常パラメータは、ページ切替用の識別キーと区別する。旧URL、新URL、入口メソッド、必要なクエリ、旧URLの扱いを対応表へ記録する。収集ツールのURL案は取込み後の実ID・既存関数名を確認して確定する。

| 旧方式の例 | 移行先の例 |
|---|---|
| `?path=about` / `?view=/about/` | `public_pages*about` |
| `?page=contact` | `public_pages*contact` |
| 記事スラッグによる振分け | `public_pages*article_detail?id=<取込み後のID>` |

public_pagesに対応する明示メソッドを作り、表示内容は共通の描画処理へ委譲してよい。任意の識別キーの値をそのままメソッド名・テンプレート名へ渡さない。既存の問い合わせ処理・LINE・認証callback等と衝突しない名前を選ぶ。

ナビ・フッター・本文内リンク・パンくず・ページ送り・JS内の遷移先を変更し、canonical・OG URL・サイトマップも新URLへ揃える。URL生成は`$ctl->get_APP_URL("public_pages", "about")`等を使い、`id`やページ番号は検証した通常クエリとして渡す。画像・PDF等の素材URLと外部リンクは、ページ振分けの置換に巻き込まない。

旧URLは既知の対応だけを互換入口から正規URLへ転送する。301は参照用GET/HEADに使い、フォームPOSTやcallbackは既存契約を確認して別途扱う。クエリを無条件に引き継がず、必要な値とフラグメントの扱いを確認する。対応表にないページ識別値や存在しない記事は404とし、全てトップへ転送しない。

変更後は新URLへの直接アクセス・再読込み、ヘッダー/フッターからの切替、一覧から詳細、旧URLからの転送を確認する。転送ループ・多段転送・クエリ欠落がなく、canonicalが最終URLを指すこと、通常の内部リンクに旧識別キーによるページ切替が残っていないことを確認する。公開画面の操作確認はfbp-playwright、HTTP照合は移行verifyを必要に応じて使う。旧サーバーの転送を制御できない場合は未対応範囲として残す。

## Importerの適応

assets/website_import/website_import.phpを対象アプリへコピーする。既存クラスは上書きしない。ノート作成・サイト自動生成・リリースのツールではない。

雛形はwm_article、wm_mediaを使用する。既存ノートを使う場合は定数・マッピングを一緒に修正し検証する。通常のDB/ノート構築手順でフィールド・画面・権限を用意し、原本の最長UTF-8バイト数から十分なサイズを選ぶ。

| ノート | 必要な項目 |
|---|---|
| wm_article | external_key/original_url/source_type/source_status/kind/slug/title/featured_media_url（文字列）; status/published_at/snapshot（数値）; categories/asset_urls（JSON文字列）; body（十分な長さの本文文字列）; record_hash（64文字） |
| wm_media | external_key/original_url/source_file/source_hash/mime/record_hash（文字列）; size/status（数値）; file（通常のFBPアップロードID・file型） |

複数の移行元を一つのノートへ統合するなら、prepareとImporterの識別子へ元サイトキーを含めるよう適応する。公開/管理の可視性やDSPは実利用者と操作に合わせて設計する。

CLI app_callでmanifestだけ渡すとdry-run。実データ取込みは許可とバックアップを確認し、apply=true、reviewed=true、confirm_manifest_sha256を渡す。フラグやハッシュは実際の操作許可を代替しない。manifestはファイルパス、素材はそのディレクトリからの相対パスで読む。

HTTPは404で拒否する。全入力・MIME・既存キー・ハッシュを先に照合し、差異は上書きしない。upload/fileとsave_fileを使用し、supporting_assetsを実行可能ファイルとして配信しない。保存後のフィールド/ファイルも照合する。

同一データの正常な再実行は作成0件。途中失敗は成功と扱わず、作成行・ファイル・FFMログ・バックアップから復旧範囲を決める。検査回避のためハッシュを書換えず、上書き/削除の範囲を確認して再開する。アプリ独自flockは使わない。

同梱テストのImporter検証は隔離したストアによる契約試験。実アプリへ適応した際はfbp-dbに従い、テスト環境のFFMで別プロセスから同時実行し、重複・デッドロック・保存フィールドの欠落がないことを確認してから本番へ進む。

inventoryは管理対象フィールドから再計算し、保存済みrecord_hashだけを信用しない。upload実体SHAも返す。初回取込み時点の一致と、その後の利用者による編集を区別する。
