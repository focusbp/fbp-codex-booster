<p>現在の入力列順のUTF-8 CSVを取り込みます。ヘッダー：{$csv_headers|escape}。同じ商品コードは更新、未登録コードは追加します。最大500行・1MBです。</p>
<form id="feature_csv_form" enctype="multipart/form-data" onsubmit="return false;">
<input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_token"></p>
{fields_form_original name="file" type="file" title="CSVファイル"}<p class="error_message error_file"></p>
<div class="button_row"><button class="ajax-link" data-class="feature_samples" data-function="upload_exe" data-form="feature_csv_form">取り込む</button></div></form>
