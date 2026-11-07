# CLIとデータ契約

Skillディレクトリからの例。出力先と取得元は案件へ合わせる。収集は元サイトを変更せず、prepare/verify/statusも本番へ書き込まない。

```bash
python3 scripts/website_migration_collect.py https://example.org/ --output /work/capture
python3 scripts/website_migration_collect.py https://example.org/ --output /work/capture --resume
python3 scripts/website_migration_collect.py https://example.org/ --output /work/capture --resume --retry-failed
python3 scripts/website_migration_collect.py https://example.org/ --output /work/capture --allow-asset-host cdn.example.org --wp-type works
python3 scripts/website_migration_prepare.py --capture /work/capture --output /work/prepared
python3 scripts/website_migration_prepare.py --wxr /work/source.xml --uploads-zip /work/uploads.zip --post-type works=post --output /work/prepared
python3 scripts/website_migration_verify.py --manifest /work/prepared/migration.json --output /work/integrity.json
```

Python 3.10以上。sourceは公開HTTP/HTTPS URLだけ。認証ヘッダー・Cookie・プロキシ資格情報を利用せずGETのみ送る。認証/変更用クエリ、管理画面、未承認ホスト、非公開IPを拒否し、確認済みIPへ接続する。リダイレクト先も範囲確認する。ローカルテスト等を明示した場合だけ--allow-privateを使う。

collectの上限は--max-pages/--max-assets/--max-records/--max-requests/--max-bytes/--max-total-bytes、間隔は--delay、通信時間は--timeout。既定は200ページ・2000素材・5000API項目・3000通信・1応答25MiB・1実行500MiB。robotsのCrawl-delayが大きい場合はそちらを優先する。再開は範囲と方式を固定し、上限は増やせる。原本改変時は再開を拒否する。

HTTP 429では実行を停止してキューを残す。相手の待機条件を確認してから再開し、上限回避や連続再試行をしない。HTTP charsetやHTMLの宣言から日本語の文字コードを判定し、文字化け候補はレビューへ残す。

## JavaScript描画

```bash
python3 scripts/website_migration_collect.py https://example.org/ --output /work/capture \
  --render-js --browser-command /path/to/node-or-playwright-wrapper
```

PlaywrightとChromeを利用できるラッパーを指定する。ブラウザーは--browser-executableで変更できる。ブラウザーGETはPythonの範囲・robots・通信量チェックへ中継し、外部POST・WebSocket・サービスワーカーは使用しない。クリック・ログイン・フォーム送信・ダウンロード承認を行わない。原本HTMLと描画後DOMは別レコード。POSTが必要な画面は部分取得となる。

## 生成物

| ファイル | 内容 |
|---|---|
| capture/capture.json | fbp.website.capture v1。URL/最終URL・取得日時・種別・MIME・status・SHA・参照先・pending/errors/excluded |
| capture/objects/<hash>.bin | 取得バイト列。原本として保持しWeb配信しない |
| prepared/migration.json | fbp.website.migration v1。articles/media/supporting_assets/missing_assets/counts/review |
| prepared/media/ | 内容からPNG/JPEG/GIF/WebP/PDFと判定。取込み時はさらにfinfo照合 |
| prepared/supporting-assets/ | CSS/JS/SVG/font等。レビュー対象で、自動公開/コード配置しない |
| prepared/routes.json | fbp.website.routes v1。旧URL・FBP関数名の候補・期待status・IDプレースホルダー |
| prepared/field-capacities.json | 元データ各項目の最大UTF-8バイト数。ノート設計ではこれを下回らず、将来の編集余地も確保する |

WXRはpost/pageが既定。追加型は--post-type 元の型=postまたはpage。uploadsはディレクトリ/ZIPに対応し、公開prefixを推定できなければ--uploads-base-urlを指定する。未参照素材も保持するが公開可否不明のものは非公開候補。ZIPのパストラバーサル・リンク・重複・容量超過を拒否する。

articlesの項目はexternal_key、original_url、source_type、source_status、kind、slug、title、status(0/1)、published_at(UTC epoch/0)、categories、body_html、asset_urls、featured_media_url、snapshot、record_hash。日付不明は0で表示側は空欄、futureで日時不明なら公開候補にしない。

パスワード保護はsource_status=protected・status=0として保持する。元パスワードをmanifestやアプリへコピーせず、必要な閲覧制御を別途設計する。

mediaはexternal_key、original_url、file(出力ルート相対)、sha256、size、mime、status、record_hash。元URLをファイルパスとして展開しない。record_hashは自身を除いたキー順ソート・UTF-8・空白なしJSONのSHA-256。取得元URLとFBP側のID対応は取込みreceiptに保持する。source_attachmentsには元のWordPress添付ID・URL・親IDの対応を残すため、ギャラリー等の適応にも利用できる。

## 移行先の照合

```bash
python3 scripts/website_migration_verify.py --manifest /work/prepared/migration.json \
  --inventory /work/target-inventory.json --output /work/compare.json
python3 scripts/website_migration_verify.py --manifest /work/prepared/migration.json \
  --base-url https://example.org/ --routes /work/prepared/routes.json \
  --import-receipt /work/import-receipt.json --output /work/public-check.json
```

inventory/receiptは雛形ImporterのCLI出力。console_logを含むCLI envelopeも受けられる。IDはparams内の{"article_key":"wp:post:7"}をreceipt.article_idsから解決する。receiptのmanifest SHAが違えば拒否する。routesは実装済み関数名・期待タイトル・公開/非公開へ適応してreview_required=falseにする。既存機能との名前衝突も確認する。

verifyは整合性・inventory・明示した公開URL/タイトル/canonical/内部リンク/素材を確認する。別の正規URLへまとめる場合はrouteにcanonical_urlを明示する。Basic認証・ログイン、ブラウザー操作、レイアウト、DNS伝播、メール着信は検証しない。ゲートウェイや画面確認は既存のブラウザー手順を併用する。

collect/verifyの終了コード0はそのコマンドの範囲の成功、1は部分取得/検証不一致、2は入力/実行エラー。prepareの0はレビュー完了ではない。review_requiredと欠落/除外を確認する。

## ツール自体の検証

Pythonのunittestをscripts/testsで実行する。ローカルHTTP fixtureだけを使い、運用中のサイトやDBへ書き込まない。Importerの契約試験はPHP 8.1以上を使う。MIGRATION_BROWSER_COMMANDにPlaywrightのラッパーを指定すると、DOM取得・外部POSTの拒否も実際のブラウザーで確認する。生成物は実行環境の指定一時ディレクトリに置く。
