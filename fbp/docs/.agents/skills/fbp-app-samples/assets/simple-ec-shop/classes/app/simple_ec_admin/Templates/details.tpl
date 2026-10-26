<style>{literal}
.sec-order-details .sec-contact-box{border:1px solid #ccc;border-radius:10px;padding:18px;margin:22px 0;min-width:0}
.sec-order-details .sec-contact-box legend{font-size:19px;font-weight:700;padding:0 8px}
.sec-order-details .sec-contact-box dl{margin:0}
.sec-order-details .sec-contact-box dt{margin-top:10px;font-weight:600}
.sec-order-details .sec-contact-box dd{margin:4px 0 0;overflow-wrap:anywhere}
.sec-order-details .sec-contact-box p{overflow-wrap:anywhere}
.sec-order-details table{width:100%}
.sec-order-details .sec-order-number{text-align:right}
{/literal}</style>
<div class="sec-order-details">
<h2>受注 {$order.order_code|escape}</h2>
<p>ショップ：{$order.login_id|escape}</p>
<table><thead><tr><th>商品</th><th>種類</th><th class="sec-order-number">個数</th><th class="sec-order-number">単価</th></tr></thead><tbody>
{foreach $items as $i}<tr><td>{$i.name|escape}</td><td>{$i.code|escape}</td><td class="sec-order-number">{$i.count|escape}</td><td class="sec-order-number">{$i.price|number_format}円</td></tr>{/foreach}
</tbody></table>
<p class="sec-order-number">合計 {$order.gokei|number_format}円</p>
<fieldset class="sec-contact-box"><legend>申込者</legend><dl>
{foreach ['from_name'=>'氏名','mail'=>'メールアドレス','from_tel'=>'電話番号','from_zipcode'=>'郵便番号','from_address'=>'住所'] as $key=>$label}
<dt>{$label|escape}</dt><dd>{$order[$key]|default:'—'|public_text nofilter}</dd>
{/foreach}
</dl></fieldset>
<fieldset class="sec-contact-box"><legend>送付先</legend><dl>
{foreach ['name'=>'氏名','tel'=>'電話番号','zipcode'=>'郵便番号','address'=>'住所'] as $key=>$label}
<dt>{$label|escape}</dt><dd>{$order[$key]|default:'—'|public_text nofilter}</dd>
{/foreach}
</dl></fieldset>
<fieldset class="sec-contact-box sec-order-memo"><legend>連絡事項</legend><p>{$order.memo|default:'—'|public_text nofilter}</p></fieldset>
</div>
