---
name: fbp-add-locale
description: Add a new localization language to app-framework5, including framework language options, lang JSON, datepicker verification, and locale-specific setting guidance.
---

# fbp-add-locale

## trigger conditions
- 新しい対応言語を追加する
- `framework_language_code` の候補を増やす
- `lang_*.json` を新規追加する
- ベトナム語など新しいローカライズ言語の初期導入を行う

## workflow
1. `I18nSimple::get_language_options()` に言語コードを追加する。
2. `locale_code` の既定値と候補を `I18nSimple` / `setting` で確認し、必要なら候補を追加する。
3. まず `fbp/app/lang/json/lang_<code>.json` を追加し、必要に応じて `lang_<locale>.json` も追加する。
4. `lang_en.json` をベースに翻訳し、まずはホーム画面・setting・wizard など露出の大きい画面から埋める。
5. `framework_language_code` と `locale_code` を `setting` で選べること、言語変更時に locale 候補が絞り込まれることを確認する。
6. datepicker / timepicker / `date datetime year_month` が設定値どおり表示されるか確認する。
7. `setting` の推奨値を決める。
   例: `timezone`, `date_format`, `datetime_format`, `year_month_format`, `currency`, 数値区切り
8. CLI で `setting_get`, `app_call(setting/page)`, `app_call(wizard/run)` を確認する。
9. ブラウザで言語切替、datepicker、通知、wizard、メール、CSV/PDF の主要表示を確認する。

## required checks
- `framework_language_code` は 2 文字コードで追加する。
- `locale_code` を使う場合は `ll-RR` 形式にする。
- `lang_<code>.json` は JSON decode で検証する。
- `lang_<locale>.json` を使う場合は、読み込み順を確認する。
- 英語未翻訳時は `lang_en.json` fallback になる前提で、キー欠落を把握したうえで進める。
- `php -l` と `app_call` を最低 1 回ずつ実行する。

## locale scope
- 翻訳対象:
  - `t()` を使う文言
  - `lang_<code>.json`
  - 必要に応じて `lang_<locale>.json`
- ローカライズ対象:
  - `framework_language_code`
  - `locale_code`
  - datepicker 表示言語
  - `date / datetime / year_month` の setting
  - `number / currency` の setting

## recommended strategy
- 新言語は、まず `language` 1本ではなく `language + locale` を意識して設計する。
- 中国語のような地域差が大きい言語は、まず `zh-CN` を完成させてから `zh-TW` 差分へ広げる。
- 翻訳キーは UI 文言から優先し、長い prompt 本文や内部説明は後回しでよい。
- 一括翻訳ツールを使う場合は、`lang_en.json` をベースに生成し、その後でブランド名・技術用語だけ手修正する。

## translate vs keep
- 中国語など東洋圏向けでも、次は原則そのまま残してよい:
  - `OpenAI`, `LINE`, `API`, `CSV`, `PDF`, `Webhook`, `HMAC`
  - `class_name`, `function_name`, `db`, `tb_name` などの開発用語
- 次は優先的に翻訳する:
  - 画面タイトル
  - ボタンラベル
  - 通知文言
  - バリデーションメッセージ
  - Wizard の説明文・選択肢
- `Configured` のような補助表示も翻訳キー化する。

## constraints
- 旧 `.lang` 翻訳は現状 `jp` 固定仕様。新言語追加ではここを広げない。
- HTML 通常表示は helper 優先。PHP 直書きの CSV/PDF/Mail は `$ctl->create_ValueFormatter()` を使う。
- ブラウザ locale / timezone を正にしない。サーバー設定を正とする。
- 新言語追加時に `lang_default` や旧 `jp/en` cookie 仕様を主導で広げない。
- `wizard.php` や tpl に直書き文言が残っている場合、翻訳ファイルだけでは完結しない。必要なら先に `t()` 化する。

## useful files
- `fbp/lib/I18nSimple.php`
- `fbp/app/lang/json/lang_en.json`
- `fbp/app/lang/json/lang_ja.json`
- `fbp/app/lang/json/lang_zh.json`
- `fbp/app/lang/json/lang_zh-cn.json`
- `fbp/app/setting/setting.php`
- `fbp/app/setting/Templates/index.tpl`
- `fbp/app/wizard/wizard.php`
- `fbp/app/wizard/Templates/`
- `fbp/js/function.js`

## cli checks
- `php <framework-web-root>/fbp/cli.php setting_get`
- `php <framework-web-root>/fbp/cli.php app_call --json='{"class":"setting","function":"page"}'`
- `php <framework-web-root>/fbp/cli.php app_call --json='{"class":"wizard","function":"run"}'`
- 必要に応じて `app_call` で対象画面の HTML を確認する。

## browser checklist
- 設定画面で新言語を選べる
- 言語変更時に `locale_code` 候補が適切に切り替わる
- 主要タブ文言が新言語で出る
- Wizard ホームの主要カテゴリと選択肢
- datepicker の月名・曜日名・ボタン文言
- timepicker の表示形式
- 通知文言
- CSV/PDF/Mail の主要文言と日付表示
