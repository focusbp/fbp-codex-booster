<?php

/** Rebuild the order PDF layout using the current PDF API. */
class SimpleEcOrderReport {
    public static function build(Controller $ctl, array $order, array $shop, array $items) {
        $pdf=$ctl->create_pdfmaker();
        $pdf->setPageLayout(['orientation'=>'P','pagesize'=>'A4','font'=>'gothic','pagenumber'=>'off']);
        $date=$ctl->create_ValueFormatter()->format_date($order['order_date']);
        $pdf->addPage();
        self::heading($pdf,$order,$shop,$date,true);
        $pdf->addText('納品書',['fontsize'=>16,'align'=>'C','margintop'=>10]);
        $table=[['商品名・種類','個数']];
        foreach($items as $item)$table[]=[trim($item['name'].' '.$item['code']),(string)(int)$item['count']];
        $pdf->addTable($table,['columnsize'=>[50,50],'columnalign'=>['L','R'],'margintop'=>10]);
        $pdf->addText('以上',['align'=>'R','margintop'=>10]);
        $pdf->addPage();
        self::heading($pdf,$order,$shop,$date,false);
        $pdf->addText('領収書',['fontsize'=>16,'align'=>'C','margintop'=>10]);
        $table=[['商品名・種類','価格','個数','小計']];
        foreach($items as $item)$table[]=[trim($item['name'].' '.$item['code']),number_format((int)$item['price']),(string)(int)$item['count'],number_format((int)$item['price']*(int)$item['count'])];
        if((int)$order['type']!==4){
            $table[]=['','','消費税',number_format((int)$order['zei'])];
            $table[]=['','','送料',number_format((int)$order['soryo'])];
        }
        $table[]=['','','税込合計',number_format((int)$order['gokei'])];
        $pdf->addTable($table,['columnsize'=>[40,20,20,20],'columnalign'=>['L','R','R','R'],'margintop'=>10]);
        $pdf->addText('以上',['align'=>'R','margintop'=>10]);
        return $pdf;
    }

    private static function heading($pdf,array $order,array $shop,string $date,bool $delivery): void {
        $pdf->addText('注文日：'.$date."\n".'受注コード：'.$order['order_code'],['align'=>'R']);
        $prefix=$delivery&&trim((string)($order['name']??''))!==''?'':'from_';
        $recipient=($delivery?"【送付先】\n":'').($order[$prefix.'zipcode']??'')."\n".($order[$prefix.'address']??'')."\n".($order[$prefix.'name']??'').'様';
        $pdf->addText($recipient,['align'=>'L']);
        $pdf->addText(implode("\n",[$shop['company'],$shop['address'],$shop['tel'],$shop['mail']]),['align'=>'R']);
    }
}
