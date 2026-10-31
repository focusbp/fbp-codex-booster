<form id="sample_statement_csv_commit" onsubmit="return false;">
    <input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_token" role="alert"></p>
    <p>{$filename|escape}：新規 {$preview.insert_count|escape} 件 ／ 取込済み {$preview.duplicate_count|escape} 件 ／ 要確認 {$preview.conflict_count|escape} 件</p>
    <p>取引IDがない別ファイルとの類似明細は、自動で同一と決めず取り込みを停止します。取引IDのある銀行形式へ変換して確認してください。</p>
    <div style="max-height:340px;overflow:auto;"><table><thead><tr><th>行</th><th>入出金日</th><th>摘要</th><th>入金額</th><th>出金額</th><th>残高</th><th>対象区分</th><th>判定</th></tr></thead><tbody>
    {foreach $preview.rows as $row}<tr><td>{$row.source_row|escape}</td><td>{$row.occurred_on|date_format:'%Y/%m/%d'}</td><td>{$row.description|escape}</td><td>{$row.amount|number_format}</td><td>{$row.withdrawal_amount|number_format}</td><td>{$row.balance_amount|number_format}</td><td>{if $row.eligibility == 2}対象外{else}通常{/if}</td><td>{$row.import_status|escape}</td></tr>{/foreach}
    </tbody></table></div>
    {if $preview.conflict_count == 0}<div class="button_row"><button type="button" class="ajax-link" data-class="sample_statement_csv" data-form="sample_statement_csv_commit" invoke-function="save">取り込む</button></div>{/if}
</form>
