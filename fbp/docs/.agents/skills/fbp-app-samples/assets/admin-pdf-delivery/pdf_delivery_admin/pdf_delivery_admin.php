<?php
// Fixed fictional documents; normal FBP management login remains required.
class pdf_delivery_admin {
    private const DOCUMENTS = [
        'A' => ['recipient' => 'サンプル利用者A', 'amount' => 4000],
        'B' => ['recipient' => 'サンプル利用者B', 'amount' => 6000],
    ];

    function run(Controller $ctl) {
        $documents = self::DOCUMENTS;
        foreach ($documents as $id => &$document) {
            $document['url'] = $ctl->get_APP_URL('pdf_delivery_admin', 'single', ['document_id' => $id]);
        }
        unset($document);
        $ctl->assign('documents', $documents);
        $ctl->assign('bulk_url', $ctl->get_APP_URL('pdf_delivery_admin', 'bulk'));
        $ctl->show_multi_dialog('pdf_delivery_admin', 'download.tpl', '請求書PDFサンプル', 700);
    }

    function single(Controller $ctl) {
        $id = $ctl->GET('document_id');
        $this->send($ctl, [$id], true);
    }

    function bulk(Controller $ctl) {
        $this->send($ctl, $ctl->GET('document_ids'), false);
    }

    private function send(Controller $ctl, $ids, bool $single): void {
        // Adaptation: re-fetch records and authorize every requested ID here.
        // Never accept amounts/recipients from request parameters or disable management login.
        if (!is_array($ids) || count($ids) < 1 || count($ids) > count(self::DOCUMENTS)) {
            $this->reject($ctl); return;
        }
        foreach ($ids as $id) {
            if (!is_string($id) || !isset(self::DOCUMENTS[$id])) {
                $this->reject($ctl); return;
            }
        }
        $ids = array_values(array_unique($ids));
        $pdf = $ctl->create_pdfmaker();
        $pdf->setPageLayout(['pagesize' => 'A4', 'font' => 'gothic', 'pagenumber' => 'off']);
        foreach ($ids as $id) {
            $document = self::DOCUMENTS[$id];
            $pdf->addPage();
            $pdf->addText('請求書', ['fontsize' => 20, 'align' => 'C']);
            $pdf->addText('帳票番号: SAMPLE-' . $id);
            $pdf->addText($document['recipient'] . ' 様');
            $pdf->addText('ご請求金額: ' . number_format($document['amount']) . '円');
            $pdf->addText('発行元: サンプル株式会社');
        }
        $filename = $single ? 'サンプル請求書-' . $ids[0] . '.pdf' : 'サンプル請求書-一括.pdf';
        // Authenticated GET keeps the viewer's Save action and filename working.
        // Generate bytes before sending headers; no application-owned temp files.
        $bytes = $pdf->get_pdf_data();
        header('Content-Type: application/pdf');
        header("Content-Disposition: inline; filename=\"sample-invoice.pdf\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Cache-Control: private, no-store');
        echo $bytes;
        exit;
    }

    private function reject(Controller $ctl): void {
        http_response_code(400);
        header('Cache-Control: private, no-store');
        // A PDF GET is a browser navigation: return HTML, not dialog JSON.
        $ctl->show_public_pages('error.tpl');
    }
}
