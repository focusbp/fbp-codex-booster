<?php
require_once __DIR__ . '/pdf_delivery_access.php';

// 固定の架空データだけを使う公開サンプル。実データでは各入口で権限を確認する。
class public_pages {
    function __construct(Controller $ctl) {
        $ctl->set_check_login(false);
    }

    function page(Controller $ctl) {
        $ctl->assign("download_url", $ctl->get_APP_URL("public_pages", "download", ["download" => "1"]));
        $ctl->show_public_pages("page.tpl", null, null, null, ["css_mode" => "minimal"]);
    }

    private function assign_invoice(Controller $ctl): void {
        $ctl->assign("issued_at", $ctl->create_ValueFormatter()->format_date(time()));
        $ctl->assign("amount", 4000);
    }

    // 基本方式: Ajaxがダイアログ表示指示を受け取り、apppdf.phpがPDFを返す。
    function preview(Controller $ctl) {
        $this->assign_invoice($ctl);
        $ctl->show_pdf("invoice.tpl", "sample-invoice.pdf", "請求書プレビュー", 800);
    }

    // 直接取得が必要な場合: JSONではなくPDF本体を返す。
    function download(Controller $ctl) {
        $this->send_invoice($ctl, time(), 4000);
    }

    // Adapt these two hooks to the app's verified identity and current DB records.
    // Fail closed until adapted. Never take identity/ownership from request fields.
    protected function authenticated_subject(Controller $ctl): ?string {
        return null;
    }

    // null id: select the document at the verified entry (e.g. confirmation email).
    // non-null id: re-read that exact record. Return id, owner, issuable, issued_at,
    // amount. A grant is not a substitute for this live authorization check.
    protected function load_document(Controller $ctl, ?string $id): ?array {
        return null;
    }

    private function allowed(?string $subject, ?array $document): bool {
        return $subject !== null && $subject !== '' && $document !== null
            && !empty($document['id']) && isset($document['owner'])
            && hash_equals((string)$document['owner'], $subject)
            && ($document['issuable'] ?? false) === true;
    }

    private function private_response(): void {
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
    }

    private function document_error(Controller $ctl): void {
        $this->private_response();
        http_response_code(403);
        // Normal GET navigation requires HTML, not Ajax notification JSON.
        $ctl->show_public_pages('document_error.tpl', null, null, null, ['css_mode' => 'minimal']);
    }

    function protected_page(Controller $ctl) {
        $this->private_response();
        $subject = $this->authenticated_subject($ctl);
        $document = $subject === null ? null : $this->load_document($ctl, null);
        if (!$this->allowed($subject, $document)) { $this->document_error($ctl); return; }
        $token = pdf_delivery_access::issue($_SESSION, $subject, (string)$document['id'], time());
        $ctl->assign('download_url', $ctl->get_APP_URL('public_pages', 'protected_download', ['code' => $token, 'download' => '1']));
        $ctl->show_public_pages('protected_page.tpl', null, null, null, ['css_mode' => 'minimal']);
    }

    function protected_download(Controller $ctl) {
        $this->private_response();
        $subject = $this->authenticated_subject($ctl);
        $id = pdf_delivery_access::resolve($_SESSION, $subject, $ctl->GET('code'), time());
        $document = $id === null ? null : $this->load_document($ctl, $id);
        if (!$this->allowed($subject, $document) || (string)$document['id'] !== $id) {
            $this->document_error($ctl); return;
        }
        $this->send_invoice($ctl, $document['issued_at'], $document['amount']);
    }

    private function send_invoice(Controller $ctl, int $issued_at, $amount): void {
        $pdf = $ctl->create_pdfmaker();
        $pdf->setPageLayout(['orientation' => 'P', 'pagesize' => 'A4', 'font' => 'gothic', 'pagenumber' => 'off']);
        $pdf->addText('請求書', ['x' => 10, 'y' => 15, 'fontsize' => 20, 'width' => 190, 'align' => 'C']);
        $pdf->addText('発行日: ' . $ctl->create_ValueFormatter()->format_date($issued_at), ['x' => 140, 'y' => 30, 'fontsize' => 10]);
        $pdf->addText("サンプル利用者 様\n件名: 懇親会費", ['x' => 15, 'y' => 50, 'fontsize' => 12]);
        $pdf->addText('ご請求金額: ' . number_format($amount) . '円', ['x' => 15, 'y' => 80, 'fontsize' => 16]);
        $pdf->addText('発行元: サンプル株式会社', ['x' => 15, 'y' => 120, 'fontsize' => 11]);
        // Headers, managed-file initialization and cleanup belong to the framework.
        $pdf->download_pdf('sample-invoice.pdf');
    }
}
