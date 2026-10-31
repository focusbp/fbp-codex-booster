<form id="sample_reconciliation_form" onsubmit="return false;" data-amounts="{$amounts_json|escape}" data-component="{$component_json|escape}">
    <input type="hidden" name="id" value="{$form.id|escape}">
    <input type="hidden" name="token" value="{$token|escape}">
    <p class="error_message error_id"></p><p class="error_message error_token" role="alert"></p>
    {literal}<style>
    .recon-summary{padding:12px;background:var(--fbp-framework-background-color,#f5f7fa);border:1px solid #bbb;margin-bottom:12px;overflow-wrap:anywhere;}
    .recon-scroll{overflow:auto;max-height:420px;}.recon-table{width:100%;border-collapse:collapse;}.recon-table th,.recon-table td{padding:8px;border-bottom:1px solid #ccc;text-align:left;}.recon-filters{display:flex;gap:12px;flex-wrap:wrap;align-items:end;}.recon-actions{display:flex;gap:10px;align-items:center;justify-content:space-between;margin:12px 0;flex-wrap:wrap;}.recon-right{margin-left:auto;}.recon-number{white-space:nowrap;}
    </style>{/literal}
    <div class="recon-summary">
        <p><b>{$side_label|escape} ID {$origin.id|escape}</b> / {$origin.date_text|escape} / {$origin.counterparty|escape} / {$origin.description|escape} / 自身の金額 {$origin.amount_text|escape} 円</p>
        <p>グループ全体：計上 {$totals.group_recognition|number_format} 円 ／ 入金 {$totals.group_receipt|number_format} 円 ／ 差額 {$totals.group_difference|number_format} 円（{$totals.balance_state|escape}）</p>
        <p>差額 ＝ 入金合計 − 計上合計。グループの一致は、個別の完済・業務完了とは別です。</p>
    </div>
    {if $mode == 'add'}
        <div class="recon-filters">
            <label>名称・内容・金額<input type="text" name="keyword" value="{$form.keyword|escape}"><span class="error_message error_keyword"></span></label>
            <div>{fields_form_original name="start_on" type="date" title="開始日" value=$form.start_on item_margin_top="0px"}<p class="error_message error_start_on"></p></div>
            <div>{fields_form_original name="end_on" type="date" title="終了日" value=$form.end_on item_margin_top="0px"}<p class="error_message error_end_on"></p></div>
            <label>紐付け状態<select name="relation_filter"><option value="">すべて</option><option value="linked"{if $form.relation_filter == 'linked'} selected{/if}>紐付けあり</option><option value="unlinked"{if $form.relation_filter == 'unlinked'} selected{/if}>未紐付け</option></select><span class="error_message error_relation_filter"></span></label>
            <button type="button" class="ajax-link" data-class="sample_reconciliation" data-form="sample_reconciliation_form" data-page="1" invoke-function="{$run_function|escape}">検索</button>
        </div>
        <p>金額差があっても追加できます。既存の紐付けは保持します。選択した相手の既存グループも合流します。</p>
        <p class="error_message error_target_ids"></p><p class="error_message error_target_id"></p><p class="error_message error_page"></p>
        <div class="recon-actions"><span class="recon-preview" aria-live="polite"></span><button type="button" class="ajax-link recon-right" data-class="sample_reconciliation" data-form="sample_reconciliation_form" invoke-function="save">選択分を追加</button></div>
        <div class="recon-scroll"><table class="recon-table"><thead><tr><th>選択</th><th>ID／日付</th><th>{$opposite_label|escape}明細</th><th>自身の金額</th><th>既存グループ</th><th></th></tr></thead><tbody>
        {foreach $candidates as $candidate}
            <tr><td>{if !$candidate.already_linked}<input type="checkbox" name="target_ids[]" value="{$candidate.id|escape}" aria-label="ID {$candidate.id|escape} を選択" data-component="{$candidate.component_json|escape}">{/if}</td>
            <td>{$candidate.id|escape}<br>{$candidate.date_text|escape}</td><td>{$candidate.counterparty|escape}<br>{$candidate.description|escape}</td><td class="recon-number">{$candidate.amount_text|escape} 円</td>
            <td>計上 {$candidate.group_recognition|number_format} 円<br>入金 {$candidate.group_receipt|number_format} 円<br>差額 {$candidate.group_difference|number_format} 円<br>{$candidate.relation_state|escape}（直接 {$candidate.link_count|escape} 件）</td>
            <td>{if $candidate.already_linked}追加済み{else}<button type="button" class="ajax-link" data-class="sample_reconciliation" data-form="sample_reconciliation_form" data-target_id="{$candidate.id|escape}" invoke-function="save">追加</button>{/if}</td></tr>
        {foreachelse}<tr><td colspan="6">該当する明細はありません。</td></tr>{/foreach}
        </tbody></table></div>
        <div class="recon-actions"><span>{$candidate_count|escape} 件 ／ {$page_no|escape} / {$page_count|escape} ページ（ページ変更時は選択を解除します）</span>
        {if $page_no > 1}<button type="button" class="ajax-link" data-class="sample_reconciliation" data-form="sample_reconciliation_form" data-page="{$page_no-1}" invoke-function="{$run_function|escape}">前へ</button>{/if}
        {if $page_no < $page_count}<button type="button" class="ajax-link" data-class="sample_reconciliation" data-form="sample_reconciliation_form" data-page="{$page_no+1}" invoke-function="{$run_function|escape}">次へ</button>{/if}</div>
        {literal}<script>
        (function(){var form=document.getElementById('sample_reconciliation_form');if(!form)return;
        var amounts=JSON.parse(form.dataset.amounts),base=JSON.parse(form.dataset.component);
        function update(){var left=new Set(base.left),right=new Set(base.right);form.querySelectorAll('input[name="target_ids[]"]:checked').forEach(function(box){var c=JSON.parse(box.dataset.component);c.left.forEach(x=>left.add(x));c.right.forEach(x=>right.add(x));});
        var l=0,r=0;left.forEach(x=>l+=Number(amounts.left&&amounts.left[x]||0));right.forEach(x=>r+=Number(amounts.right&&amounts.right[x]||0));
        var f=n=>n.toLocaleString('ja-JP');form.querySelector('.recon-preview').textContent='追加後の全体：計上 '+f(l)+' 円 ／ 入金 '+f(r)+' 円 ／ 差額 '+f(r-l)+' 円';}
        form.addEventListener('change',update);update();})();
        </script>{/literal}
    {elseif $mode == 'remove'}
        <p>この明細に直接紐付いた関係だけを解除します。他の関係は残ります。</p>
        <p class="error_message error_link_id"></p>
        {foreach $current as $match}<div class="recon-actions"><span>ID {$match.item.id|escape} ／ {$match.item.date_text|escape} ／ {$match.item.counterparty|escape} ／ {$match.item.description|escape} ／ {$match.item.amount_text|escape} 円</span>
        <button type="button" class="ajax-link recon-right" data-class="sample_reconciliation" data-form="sample_reconciliation_form" data-link_id="{$match.link_id|escape}" invoke-function="save">この紐付けを解除</button></div>
        {foreachelse}<p>直接紐付いている明細はありません。</p>{/foreach}
    {else}
        {foreach $details as $detail_side => $items}<p><b>{if $detail_side == 'left'}計上明細{else}入金明細{/if}</b></p>
        <div class="recon-scroll"><table class="recon-table"><tbody>{foreach $items as $item}<tr><td>ID {$item.id|escape}</td><td>{$item.date_text|escape}</td><td>{$item.counterparty|escape}</td><td>{$item.description|escape}</td><td>{$item.amount_text|escape} 円</td></tr>{/foreach}</tbody></table></div>{/foreach}
        <p><b>この起点の直接関係</b></p>{foreach $current as $match}<p>相手 ID {$match.item.id|escape} ／ 関係 ID {$match.link_id|escape}</p>{foreachelse}<p>直接関係なし</p>{/foreach}
        <p><b>関連する操作履歴</b>（{$event_count|escape} 件中、最新100件）</p>
        {foreach $events as $event}<p>{$event.occurred_at|date_format:'%Y/%m/%d %H:%M:%S'} ／ {$event.action|escape} ／ 関係 {$event.link_id|escape} ／ 計上 {$event.recognition_id|escape}：{$event.recognition_amount|number_format} 円 ／ 入金 {$event.receipt_id|escape}：{$event.receipt_amount|number_format} 円 ／ 操作者 {$event.actor_id|escape}</p>{foreachelse}<p>履歴はありません。</p>{/foreach}
    {/if}
</form>
