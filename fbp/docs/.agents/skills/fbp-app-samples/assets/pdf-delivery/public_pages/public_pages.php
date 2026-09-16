<?php

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
        $this->assign_invoice($ctl);
        // save_pdf/res_saved_file専用の管理アップロード領域を使用する。
        $stored = "sample_invoice_" . bin2hex(random_bytes(16)) . ".pdf";
        $cleanup = static function () use ($ctl, $stored) {
            if ($ctl->is_saved_file($stored)) {
                $ctl->delete_saved_file($stored);
            }
        };
        register_shutdown_function($cleanup); // res_saved_file()のexitにも対応
        try {
            $ctl->save_pdf("invoice.tpl", $stored);
            $ctl->res_saved_file($stored, "sample-invoice.pdf");
        } finally {
            $cleanup();
        }
    }
}
