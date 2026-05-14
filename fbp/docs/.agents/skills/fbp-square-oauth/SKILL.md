---
name: fbp-square-oauth
description: Implement seller/shop-scoped Square OAuth connection flows in FBP apps, including app-wide Square credentials, shop token storage, authorize/callback URLs, token refresh, and OAuth verification.
---

# fbp-square-oauth

## trigger conditions
- ショップごとに Square OAuth 連携を追加する
- `shop` に Square seller token / location / merchant ID を保存する
- Square Developers の Application ID / Secret と FBP `setting` を整理する
- OAuth callback 後に JSON ではなく通常画面へ戻したい
- Sandbox OAuth の 400 / 白画面 / redirect_uri 問題を調査する

## related skills
- DB項目を追加/削除する場合は `fbp-db` も使う。
- ショップ一覧ボタンや管理画面を触る場合は `fbp-original-screen` / `fbp-dialog` も使う。
- 決済実行そのものは `fbp-square-payment` を使う。この skill は OAuth 連携までを扱う。

## data design
- Square Application ID / Secret はショップ固有ではなくアプリ共通設定に置く。
- `shop` には seller account から得た OAuth 結果だけを保存する。
- 推奨 `shop` fields:
  - `square_location_id`
  - `square_access_token`
  - `square_refresh_token`
  - `square_token_expires_at`
  - `square_merchant_id`
- `shop.square_application_id` は原則不要。既存にある場合は整理候補。
- `setting.square_application_secret` が無い場合は追加し、設定画面では `password` 入力 + 空欄保存時は既存値保持 + `設定済み` マスク表示にする。

## workflow
1. 公式 Square OAuth docs を確認する。特に Sandbox/Production の URL と `session` 差分は変わりうるため、必要なら公式だけを参照する。
2. `setting` に app-wide credentials を用意する。
   - `square_application_id`
   - `square_application_secret`
   - 既存の `square_access_token` は従来のテスト/直接決済用として混同しない。
3. `shop` に seller token fields を用意し、手入力フォームから token 類は外す。
4. ショップ一覧に `SQUARE連携` ボタンを追加する。
5. `square_connect()` で authorize URL にリダイレクトする。
6. `square_oauth_callback()` で `state` を検証し、`code` を `/oauth2/token` に交換する。
7. token 取得後 `/v2/locations` を呼び、最初の active location ID を保存する。
8. callback 完了後は直接管理画面 class を描画せず、`__AUTO_LOAD_MAIN_AREA` をセットして `base*page` へ戻す。
9. 公開決済では注文の `shop_id` に紐づく shop token/location を `set_square()` に渡す。期限が近い場合は refresh token で更新する。

## Square URL rules
- Sandbox authorize base: `https://connect.squareupsandbox.com/oauth2/authorize`
- Production authorize base: `https://connect.squareup.com/oauth2/authorize`
- Sandbox token base: `https://connect.squareupsandbox.com/oauth2/token`
- Production token base: `https://connect.squareup.com/oauth2/token`
- Sandbox では `session=false` を付けない。Sandbox は `session=true` default のみ。
- Production では `session=false` を付ける。
- `scope` は space-separated string を URL encode する。`+` 区切りで問題ない。
- callback URL は Square Developers に登録した Redirect URL と完全一致させる。
- FBP の `*` URL が Square 側で通らない場合があるため、OAuth callback は次の標準形式を優先する。
  - `https://.../fbp/app.php?class=shop_original_management&function=square_oauth_callback`

## Sandbox checklist
- Square Developer Console で対象 app の environment が Sandbox になっている。
- Sandbox OAuth Redirect URL に callback URL を登録している。
- Seller 用 Sandbox test account を作成している。
- OAuth開始前に Developer Console の Sandbox test accounts から対象 seller を `Open in Square Dashboard` で開いている。
- 400のHTMLに `first launch the seller test account from the Developer Console` が出る場合、seller test account 未起動。

## developer handoff
開発者へ手順を伝える場合は、次を短く案内する。

### Square Developer Console settings
1. Square Developer Console で対象 Application を開く。
2. Sandbox / Production の利用環境を確認する。
3. OAuth redirect URL に FBP callback URL を完全一致で登録する。
   - 推奨形式: `https://<domain>/<app>/fbp/app.php?class=shop_original_management&function=square_oauth_callback`
   - `*` を含む FBP URL は Square 側で通らない場合があるため避ける。
4. Sandbox では Seller test account を作成し、初回OAuth前に `Open in Square Dashboard` で一度開く。
5. Application ID と Application Secret を控える。Access token はOAuth連携用の事前設定には使わない。

### FBP settings
1. FBP管理画面の setting に次を設定する。
   - `square_application_id`
   - `square_application_secret`
2. `square_application_secret` はマスク表示し、空欄保存では既存値を保持する。
3. 既存の `square_access_token` はアプリ共通の直接決済/テスト用として扱い、ショップOAuth token と混同しない。

### Test flow
1. Sandbox環境でショップ管理一覧を開く。
2. 対象ショップの `SQUARE連携` を押す。
3. Square認可画面へ遷移することを確認する。
4. Sandbox seller で許可する。
5. FBP管理画面へ戻り、JSONが直接表示されないことを確認する。
6. `shop` に `square_access_token`, `square_refresh_token`, `square_token_expires_at`, `square_merchant_id`, `square_location_id` が保存されたことを確認する。
7. 公開決済側で注文の `shop_id` に紐づく shop token/location が使われることを確認する。

## sample code
- Read `references/sample-shop-square-oauth.md` when implementing a shop-scoped OAuth flow.

## verification
- `php -l` for changed PHP files.
- `copy_to_web.sh app-xxx`.
- `app_call <shop_management_class> run` succeeds.
- `setting.page` shows `Application Secret` as masked/blank input, not raw secret.
- OAuth authorize request reaches Square without 400.
- Callback stores `square_access_token`, `square_refresh_token`, `square_token_expires_at`, `square_merchant_id`, and `square_location_id`.
- After callback, browser returns to normal FBP page, not raw JSON.
