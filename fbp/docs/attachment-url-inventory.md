# 添付・画像URLの横断検索

2026-11-11、ローカルの既存アプリとフレームワークを静的検索した一次一覧。[CSV一覧](attachment-url-inventory.csv)にプロジェクト・ファイル・行・指定方式・宛先・候補分類を保存。業務データ・生成URL・認証情報は含めない。

## 対象と件数

- 61プロジェクト、PHP/TPL/JS 5,367ファイル。個別アプリはソースのclasses/app、フレームワークはapp・Templates・lib・jsを対象とした。専用テストアプリも含む。
- `get_APP_URL()` 1,113箇所、配信に関連する `data-function` 177箇所、`get_public_media_url()` 8箇所。合計1,298箇所を収録した。
- 名称から配信候補としたもの325箇所、動的指定等の追加確認候補95箇所、その他URL878箇所。
- 「配信候補」は画像・添付以外のCSV/PDF出力や、コメント内の例を含み得る。実稼働中の添付経路数ではない。共通部品1箇所が複数アプリに作用する場合もある。

## 影響の判断に使える例

| 場所 | 確認した指定 | 設計への意味 |
| --- | --- | --- |
| framework Templates/__image_preview.tpl | db_exe/view_image | 管理側の標準画像を切り替える候補。公開側での共通部品利用も確認する |
| framework Templates/__item_edit.tpl、__item_viewer.tpl、__item_viewer_notag.tpl | data-function=download_file | URL生成の検索だけでは漏れる。data-classと呼出元の確認が必要 |
| framework Controller::get_public_media_url | public_mediaへの間接生成 | 公開側の従来配信を維持する対象 |
| framework upload/upload.php | upload/pull | 既存配信を今回一律には変更しない |
| app-small public_pages/public_pages.php | db_exe/view_image | 公開ページでも旧db_exeを使用している実例 |
| app-daitomiraku public_pages/public_pages.php | db_exe/view_image | 同上。db_exeの既存入口の一括認証化は避ける |
| app-movingbase public_pagesの各テンプレート | public_pages/view_image | 公開用のアプリ独自配信 |
| app-one2one _one2one/Forms.phpとone2one_formsのテンプレート | one2one_*の画像・ファイル取得 | 既存のアプリ専用経路を再利用する候補 |
| app-soshikikaikaku task_support_dashboard | 専用の画像・ファイル取得 | 管理向け独自配信。共通URL変更だけでは移行されない |

## 一覧の使い方と限界

1. 配信候補を管理画面・公開ページ・認証済み会員/連携・生成物に分ける。公開クラス名だけでログイン要否を確定しない。
2. 宛先のコンストラクタ・認証・閲覧条件を確認し、共通テンプレートの呼出元へ戻って用途を確認する。
3. 動的なクラス/関数指定95箇所を確認する。CSVは静的抽出であり、複数行の複雑な式や同一行の複数呼出しは原文で再確認する。
4. ハードコードURL、JSによるURL組立て、独自readfile、DBに保存されたHTML/URL、外部CDN、ローカルにない本番ソースはこの一覧だけでは網羅できない。実装対象を決めた後に対応経路と実HTTPを確認する。

今回はローカルソースの検索と設計整理のみ。各アプリの本番動作・全画面の外観を検証した結果ではない。
