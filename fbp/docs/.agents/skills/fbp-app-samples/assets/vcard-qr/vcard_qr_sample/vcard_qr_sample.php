<?php
require_once __DIR__ . '/vcard_builder.php';

class vcard_qr_sample {
    function run(Controller $ctl) {
        $ctl->show_multi_dialog('vcard_qr_sample', 'download.tpl', '連絡先QR付きPDF', 640);
    }

    function download(Controller $ctl) {
        // Fixed fictional contact. For real contacts, re-fetch and authorize the record here.
        $contact = ['name' => 'サンプル 太郎', 'family_name' => 'サンプル', 'given_name' => '太郎',
            'phone' => '+81-3-0000-0000', 'email' => 'taro@example.invalid', 'organization' => 'サンプル株式会社'];
        $pdf = self::document($ctl, $contact);
        $pdf->download_pdf('連絡先QRサンプル.pdf');
    }

    static function document(Controller $ctl, array $contact) {
        $payload = sample_vcard_builder::build($contact);
        $pdf = $ctl->create_pdfmaker();
        $pdf->setPageLayout(['pagesize' => 'A4', 'font' => 'gothic', 'pagenumber' => 'off']);
        $pdf->addPage();
        $pdf->addText('連絡先QRサンプル', ['fontsize' => 18]);
        $pdf->addText($contact['name'], ['margintop' => 6]);
        $pdf->addText((string) ($contact['organization'] ?? ''));
        $pdf->addText((string) ($contact['phone'] ?? ''));
        $pdf->addText((string) ($contact['email'] ?? ''));
        $pdf->addQrCode($payload, ['width' => 45, 'margintop' => 8]);
        return $pdf;
    }
}
