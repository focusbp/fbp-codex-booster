<p>架空の請求書を単票・一括で取得できます。</p>
{foreach $documents as $id => $document}
<form id="pdf_sample_single_{$id|escape}" onsubmit="return false;">
    <input type="hidden" name="document_id" value="{$id|escape}">
    <button type="button" class="download-link" data-class="pdf_delivery_admin"
            data-function="single" data-form="pdf_sample_single_{$id|escape}"
            data-filename="サンプル請求書-{$id|escape}.pdf" data-open_new_tab="true">
        請求書{$id|escape}をダウンロード
    </button>
</form>
{/foreach}
<form id="pdf_sample_bulk" onsubmit="return false;">
    <p>一括取得する請求書を選択してください。</p>
    {foreach $documents as $id => $document}
    <label><input type="checkbox" name="document_ids[]" value="{$id|escape}" checked>
        請求書{$id|escape}</label>
    {/foreach}
    <button type="button" class="download-link" data-class="pdf_delivery_admin"
            data-function="bulk" data-form="pdf_sample_bulk"
            data-filename="サンプル請求書-一括.pdf" data-open_new_tab="true">
        選択した請求書を一括ダウンロード
    </button>
</form>
