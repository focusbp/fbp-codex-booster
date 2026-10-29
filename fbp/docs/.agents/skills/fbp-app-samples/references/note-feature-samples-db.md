# 機能サンプルのノート・保存先

| ノート | 用途 |
| --- | --- |
| feature_sample_products | 商品一覧、CSV、PDF、MCP、公開フォーム・単品ショップの入力値 |
| feature_sample_orders | ショップ決済管理（内部・メニュー非表示）。Squareの状態・再課金防止・商品ノート行ID |
| feature_sample_mail_send | Excel風：氏名・メールアドレス。全件をアドレス単位で重複排除して個別送信 |
| feature_sample_mail_receive | Excel風：日付・タイトル・内容（textarea 3000バイト） |
| feature_sample_mail_send_log | 宛先ごとの送信状態（内部・メニュー非表示） |

公開フォーム・ショップは現在の対象ノートの入力項目・型・順序を取得し、確認後は同じ項目IDへ保存する。フォーム受付用の別ノートは作成しない。checkout_key（text 64）は非表示の内部項目であり、公開POSTから指定させない。ショップは内部決済記録のnote_row_idへ保存先行を対応付ける。

商品サンプルの共通インストーラーは通常ノートを初期配置する。Excel風ではA〜Dの項目IDとcode/name/price/memoの業務役割を対応付ける。Excel列と通常項目を二重登録しない。列定義と表示順は現在のノートを正本とし、Developer Modeの通常編集に対応する。

MCPは検索（一覧）・追加・変更・削除の4関数。変更系はmcp.write、管理者OAuth subject、confirm=trueを要求する。

メール用の追加ノートはconfigure_note_mail_samples.pyで明示的なテスト環境に導入する。メールの件名・本文は空欄から入力し、ノート全件の重複排除後の宛先一覧を確認する。メールテスト送信先は必須にしない。実送信の検証は利用者の明示指示がある場合のみ行う。
