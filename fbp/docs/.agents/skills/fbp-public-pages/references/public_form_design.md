# 公開フォームのデザイン・エラー表示

新規の入力→確認→完了フォームの基本デザイン。既存ブランドや利用者のデザイン指定を優先し、管理画面や公開サイト全体へ広いCSSを適用しない。業務上の必須項目・形式制限・メール送信先はフォームごとに決める。

## 配置

- 淡い水色のページ背景と、最大幅720px・余白20px・角丸12pxの白いカード。
- 項目タイトルから入力欄まで6px。コメントがあればタイトル直下・入力欄の上へ置く。DB定義の `parameter_description` は標準helperで表示できる。
- 所在地・長文欄など広く使う項目のwrapperには `.public-field-wide` を付ける。入力欄は親幅を超えない。
- 確認・送信・戻るは共通の幅180px・高さ50px・文字18px。主操作は青、戻るは薄いグレー背景と濃い文字。戻るは左、主操作は右。狭い画面では折り返す。
- 日付選択の年・月のスタイルはframework基本CSSを使い、アプリへ複製しない。

## 項目とエラーのテンプレート

DB項目では `fields_form_direct` を使う。`$fields` は `get_field_list()` などで取得した定義配列。確認画面では `fields_view_direct` を使い、各項目の見出し・値・エラーを並べる。DB定義のない入力だけ `fields_form_original` を使う。

```smarty
<main class="public-page-root">
<h1>{$page_title|escape}</h1>
<form id="public_form" class="public-form" onsubmit="return false;">
    <input type="hidden" name="csrf" value="{$csrf|escape}">
    {foreach $fields as $field}
    <div class="public-field{if $field.parameter_name == 'address'} public-field-wide{/if}">
        {fields_form_direct field_group=[$field] data="row"}
        <p class="error_message error_{$field.parameter_name|escape}" role="alert"></p>
    </div>
    {/foreach}
    <p class="error_message error_form" role="alert"></p>
    <div class="public-actions">
        <div class="public-actions-back">
            <button type="button" class="ajax-link button_link secondary" data-class="public_pages" data-function="back_function" data-form="public_form">戻る</button>
        </div>
        <div class="public-actions-main">
            <button type="button" class="ajax-link button_link" data-class="public_pages" data-function="confirm_function" data-form="public_form">確認する</button>
        </div>
    </div>
</form>
</main>
```

初回の入力画面で戻る先がなければ `.public-actions-back` を省略する。確認画面は「戻る」と「送信する」を同じ構造で表示する。確認ダイアログでは既存の取消・戻る導線を維持し、テンプレートをコピーするだけで未実装の関数を呼ばない。

## 全エラーを一度に返す

各項目の検証結果を収集し、最初の入力エラーで終了しない。項目直下に詳細を、ボタン直上には固定文「エラーがあります。修正してください。」を表示する。エラー時は入力値・フォームを維持し、`show_multi_dialog()` や `reload_area()` で作り直さない。

```php
$errors = [];
// 各項目について必須・形式・長さ・重複など必要な条件を確認し、
// $errors[$parameter_name] にその項目のメッセージを格納する。
foreach ($fieldValidators as $name => $validate) {
    try { $values[$name] = $validate($post[$name] ?? null); }
    catch (DomainException $e) { $errors[$name] = $e->getMessage(); }
}
if ($errors) {
    foreach ($errors as $name => $message) $ctl->res_error_message($name, $message);
    $ctl->res_error_message('form', 'エラーがあります。修正してください。');
    return;
}
// すべて成功した後で確認状態を保存し、確認画面へ進む。
```

CSRF・期限切れ・配送失敗など項目へ帰属しないエラーには、専用の表示領域へ原因と再操作を案内する。最終保存前にも検証し、確認済み値はサーバー側で保持する。

## ページ固有CSS

`style.css` またはhead用テンプレートへ置く。Smartyの `<style>` では `{literal}` で囲む。`.public-page-root` はサンプル固有のルートクラスへ置き換えてよい。

```css
body.publicsite-body { margin: 0; background: #f5fafc; color: #202e3c; font-family: system-ui, sans-serif; }
.public-page-root { box-sizing: border-box; width: min(100%, 720px); margin: 30px auto; padding: 20px; background: #fff; border: 1px solid #d7e8f3; border-radius: 12px; }
.public-page-root h1 { font-size: 26px; }
.public-page-root h2 { margin-top: 28px; padding-bottom: 8px; border-bottom: 1px solid #ddd; font-size: 20px; }
.public-form .field_edit h6 { margin: 0 0 6px; font-size: 14px; line-height: 1.5; }
.public-form .style_parameter_description { margin: 0 0 6px; font-size: 14px; line-height: 1.5; }
.public-form .public-field { margin-top: 10px; }
.public-form input:not([type=hidden]):not([type=checkbox]):not([type=radio]), .public-form select, .public-form textarea { box-sizing: border-box; max-width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
.public-form textarea, .public-form .public-field-wide input { width: 100%; }
.public-form .error_message { color: #b91c1c; }
.public-form .public-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 16px; margin: 24px 0 10px; }
.public-form .public-actions-back { display: flex; margin-right: auto; }
.public-form .public-actions-main { display: flex; margin-left: auto; }
.public-form .public-actions .button_link { display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; min-width: 180px; min-height: 50px; margin: 0; padding: 12px 28px; border: 0; border-radius: 6px; background: #0879f9; color: #fff; font-size: 18px; font-weight: 600; line-height: 1.4; cursor: pointer; }
.public-form .public-actions .secondary { background: #e5e7eb; color: #374151; }
.public-form .public-actions .button_link:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
```

## 確認範囲

PCと狭い幅で、項目コメントの順序・横はみ出し・左右のボタン位置を確認する。複数未入力・複数形式エラーを同時に発生させ、各項目の詳細と共通メッセージ、入力値の保持を確認する。デザインの検証だけで実登録・実メール送信は行わない。
