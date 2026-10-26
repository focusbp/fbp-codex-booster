<p>{$contract.item_name|escape}</p>
<table style="width:100%"><thead><tr><th>日時</th><th>氏名</th><th>メールアドレス</th><th style="text-align:right">金額</th><th>ステータス</th></tr></thead><tbody>
{foreach $payments as $payment}<tr><td>{$payment.created_at|date_format:'%Y/%m/%d %H:%M'}</td><td>{$payment.name|escape}</td><td>{$payment.mail|escape}</td><td style="text-align:right">{$payment.amount|number_format}円</td><td>{$payment.result|escape}</td></tr>{foreachelse}<tr><td colspan="5">決済履歴はありません。</td></tr>{/foreach}
</tbody></table>
