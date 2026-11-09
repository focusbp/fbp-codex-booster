<p>架空の請求書を別タブで表示できます。保存はPDFビューアーの保存ボタンを使ってください。</p>
{foreach $documents as $id => $document}
<p id="pdf_sample_single_{$id|escape}">
    <a href="{$document.url|escape}" target="_blank" rel="noopener"
       data-filename="サンプル請求書-{$id|escape}.pdf">請求書{$id|escape}を別タブで開く</a>
</p>
{/foreach}
<form id="pdf_sample_bulk" action="{$bulk_url|escape}" method="get" target="_blank" rel="noopener">
    <p>一括表示する請求書を選択してください。</p>
    {foreach $documents as $id => $document}
    <label><input type="checkbox" name="document_ids[]" value="{$id|escape}" checked>
        請求書{$id|escape}</label>
    {/foreach}
    <button type="submit" data-filename="サンプル請求書-一括.pdf">
        選択した請求書を別タブで開く
    </button>
</form>
