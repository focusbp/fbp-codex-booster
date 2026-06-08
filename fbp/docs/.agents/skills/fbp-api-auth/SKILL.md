---
name: fbp-api-auth
description: Implement HMAC-SHA256 API authentication for FBP API classes and provide reproducible client-side test flows.
---

# fbp-api-auth

## trigger conditions
- APIクラスの認証追加・修正を行う
- HMAC署名仕様のクライアント検証が必要

## workflow
1. APIクラス名を `*_api` で統一。
2. エントリで `verify_api_request()` を実行し不正時は終了。
3. canonical文字列と署名仕様を合わせる。
4. bash/Pythonクライアントで署名付きリクエスト検証。
5. クライアント側の接続先切替・認証情報解決は、環境固有の運用ガイドに従う。
6. 疎通確認は read-only の軽い endpoint から始める。
7. 更新系 endpoint の検証は、対象環境とデータ影響を明示してから行う。

## update endpoint checks
- `add` 相当の endpoint は、新規ID採番と必須項目検証を確認する。
- `edit` 相当の endpoint は、対象ID必須・存在しないIDのエラーを確認する。
- `delete` 相当の endpoint は、数値ID必須・削除済み確認の戻り値を確認する。
- 本番データを扱う場合は、環境切替と更新範囲を明示する。

## constraints
- `api_secret` をログやレスポンスに出さない。
- 時刻ズレ許容やnonce再利用防止の方針を明示する。
