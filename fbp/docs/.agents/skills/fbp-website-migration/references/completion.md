# 工程と完了条件

| 工程 | 確認すること |
|---|---|
| 収集 | 合意範囲・原本・欠落/除外/上限・取得できないデータ |
| 実装 | FBP入口・管理画面・デザイン・フォーム等の作成と確認 |
| データ | テスト/本番の明示取込み、件数・内容・素材・公開範囲の照合 |
| DNS | 正規名/wwwのA/AAAA/CNAMEと移設先の照合。MX/TXT等を保持 |
| サーバー | 既存conf/証明書/転送、許可された変更、構文検査とreload |
| 公開 | 証明書・HTTPS・www転送・パス/クエリ保持・代表画面・画像・非公開制御 |
| 通知 | 保存、送信受付、実着信を個別確認。実送信しなければ未実施 |

DNSは実際の権威DNSを管理する会社の画面で案内する。取得元WebサーバーのSSL設定だけへ誘導しない。設定済みなら再変更を求めず、設置先のApache・証明書・公開先を調べる。SSL表示問題は証明書・チェーン・ブラウザーのURL/エラーを確認し、再発行を繰り返さない。

公開切替には対象サーバー・アプリ・ドメイン・保持する設定・バックアップ・戻し方を具体化する。root認証は環境固有の許可条件に従い、通常の実装承認やCLIフラグだけで取得しない。

## 進捗CLI

```bash
python3 scripts/website_migration_status.py --workspace /work/progress init --appcode app-example --plan /work/prepared/migration.json
python3 scripts/website_migration_status.py --workspace /work/progress record --stage production_import --state verified --note '本番取込みと照合を確認' --evidence /work/compare.json
python3 scripts/website_migration_status.py --workspace /work/progress show
```

同じ状態・メモ・証拠でイベントを増やさない。plan変更後はreplanで以前の段階をリセットして再確認する。証拠ファイルの変更・欠落も検出し、その工程を未確認へ戻して集計する。ハッシュは変更検知用で、許可・電子署名・本番操作の証明ではない。

published（コード/データ/DNS/TLS/ルーティング/コンテンツの記録）とmigration_complete（範囲・原本・テスト・バックアップ・通知を含む全体）を分ける。not_applicableは本当に対象外の場合だけ理由を書く。フォームがあるのに送信未確認だから対象外にしてはいけない。

実際に確認した範囲を完了として報告し、未確認の着信等が残れば明記する。コードだけで記事/画像も公開済みとしない。進捗CLIはTaskへ投稿・完了操作をしない。auto-taskでは既存の許可・snapshot・状態遷移を守り、DNS待機や未実施説明だけで同じコメントを繰り返さない。
