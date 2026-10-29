{if !$ocr_ready}<p>OpenAI設定が未完了です。外部連携設定へAPIキー・URL・画像対応モデルを登録してください。</p>{/if}
<form id="excel_ocr_form" enctype="multipart/form-data" onsubmit="return false;">
<input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_token"></p>
<label for="ocr_read_mode">読取件数</label><select id="ocr_read_mode" name="read_mode"><option value="single">1件のデータ</option><option value="multiple">複数のデータ（最大20件）</option></select><p class="error_message error_read_mode"></p>
{fields_form_original name="file" type="file" title="読み取る画像（PNG/JPEG・5MB以下）"}<p class="error_message error_file"></p>
<div class="button_row"><button type="button" class="ajax-link" data-class="{$ocr_class|escape}" data-function="read" data-form="excel_ocr_form">読み取る</button></div></form>
