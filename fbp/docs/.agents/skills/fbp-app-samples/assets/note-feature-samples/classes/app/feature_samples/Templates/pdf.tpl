<p>商品サンプル {$row_count|escape}件をA4の一覧PDFに出力します。</p>
<form id="feature_pdf_form" onsubmit="return false;"><input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_token"></p>
<div class="button_row"><button type="button" class="download-link" data-class="feature_samples" data-function="pdf" data-form="feature_pdf_form" data-filename="商品サンプル一覧.pdf" data-open_new_tab="true">一覧PDFを開く</button></div></form>
