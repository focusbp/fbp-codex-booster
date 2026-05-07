<?php

class minimal_note_original_management
{
    private function tableName() {
        return "sample_note";
    }

    private function listAreaId() {
        return "#minimal_note_original_management_list_area";
    }

    function run(Controller $ctl) {
        $rows = $ctl->db($this->tableName())->getall("id", SORT_DESC);
        $ctl->assign("rows", $rows);
        $ctl->show_main_area("list.tpl", "Minimal Original CRUD");
    }

    function reload_list(Controller $ctl) {
        $rows = $ctl->db($this->tableName())->getall("id", SORT_DESC);
        $ctl->assign("rows", $rows);
        $ctl->reload_area($this->listAreaId(), "list_area.tpl");
    }

    function add_dialog(Controller $ctl) {
        $ctl->assign("row", ["title" => "", "detail" => "", "status" => ""]);
        $ctl->show_multi_dialog("minimal_note_original_management_add", "add.tpl", "追加", 720);
    }

    function add_save(Controller $ctl) {
        $row = [
            "title" => trim((string) ($ctl->POST("title") ?? "")),
            "detail" => trim((string) ($ctl->POST("detail") ?? "")),
            "status" => (string) ($ctl->POST("status") ?? ""),
        ];
        if ($row["title"] === "") {
            $ctl->res_error_message("title", "題名を入力してください。");
        }
        if ($ctl->count_res_error_message() > 0) {
            return;
        }
        $ctl->db($this->tableName())->insert($row);
        $ctl->close_multi_dialog("minimal_note_original_management_add");
        $this->reload_list($ctl);
    }

    function edit_dialog(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->assign("row", $row);
        $ctl->show_multi_dialog("minimal_note_original_management_edit", "edit.tpl", "編集", 720);
    }

    function edit_save(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $row["title"] = trim((string) ($ctl->POST("title") ?? ""));
        $row["detail"] = trim((string) ($ctl->POST("detail") ?? ""));
        $row["status"] = (string) ($ctl->POST("status") ?? "");
        if ($row["title"] === "") {
            $ctl->res_error_message("title", "題名を入力してください。");
        }
        if ($ctl->count_res_error_message() > 0) {
            return;
        }
        $ctl->db($this->tableName())->update($row);
        $ctl->close_multi_dialog("minimal_note_original_management_edit");
        $this->reload_list($ctl);
    }

    function delete_dialog(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->assign("row", $row);
        $ctl->show_multi_dialog("minimal_note_original_management_delete", "delete_confirm.tpl", "削除確認", 480);
    }

    function delete_save(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->db($this->tableName())->delete($id);
        $ctl->close_multi_dialog("minimal_note_original_management_delete");
        $this->reload_list($ctl);
    }
}
