
{$order.from_name}様

{if $type == 0}
{$shop.shopname}でのご購入ありがとうございました。
{else if $type == 1}
{$shop.shopname}でのお申込みありがとうございました。
{else if $type == 2}
{$shop.shopname}でのお問い合わせありがとうございました。
{else if $type == 3}
{$shop.shopname}での見積もり依頼ありがとうございました。
{else if $type == 4}
{$shop.shopname}でのサブスクリプションのお申し込みありがとうございました。
{/if}

{if $shop.paytype == 0 && $order.gokei > 0}
-------------------------------------
＜お支払について＞

{$shop.oshiharai}
-------------------------------------
{/if}

【コード】
{$order.order_code}

【日時】
{$order.order_date} {$order.order_time}

【お申込者】
氏名：{$order.from_name}
郵便番号：{$order.from_zipcode}
住所：{$order.from_address}
電話番号：{$order.from_tel}
メールアドレス：{$order.mail}

{if $type == 0}
【送付先】
氏名：{$order.name}
郵便番号：{$order.zipcode}
住所：{$order.address}
電話番号：{$order.tel}
メールアドレス：{$order.mail}
{/if}

{foreach $shohin_list as $shohin}
-------------------------
{$shohin.name} {$shohin.code}
{if $type == 0 || $type == 1}
{$shohin.count} {$shohin.unit}
{/if}
{if $type == 0 || $type == 1}
{$shohin.price}円
{else if $type == 4}
{$shohin.price}円/月
{/if}
{/foreach}

{if $order.gokei > 0}
----------------
小計：￥{$order.shokei}
{if $type == 0}
送料：￥{$order.soryo}
{/if}
消費税：￥{$order.zei}
税込合計：￥{$order.gokei}
{/if}

{if $type == 0}
【連絡事項】
{else if $type == 1}
【その他記入欄】
{else if $type == 2}
【お問い合わせ内容】
{else if $type == 3}
【見積依頼内容】
{else if $type == 4}
【連絡事項】
{/if}
{$order.memo}

---------------------------------------------
{$shop.company}
{$shop.address}
{$shop.tel}
{$shop.mail}
---------------------------------------------
