<?php

class sample_sort_original_management
{
    private function tableName() {
        return "sample_sort_master";
    }

    private function listAreaId() {
        return "#sample_sort_original_management_list_area";
    }

    private function rows(Controller $ctl) {
        return $ctl->db($this->tableName())->getall("sort", SORT_ASC);
    }

    private function assignList(Controller $ctl) {
        $rows = $this->rows($ctl);
        $ctl->assign("rows", $rows);
        $ctl->assign("count", count($rows));
    }

    private function reloadList(Controller $ctl) {
        $this->assignList($ctl);
        $ctl->reload_area($this->listAreaId(), "list_area.tpl");
    }

    private function nextSort(Controller $ctl) {
        $rows = $this->rows($ctl);
        if (empty($rows)) {
            return 1;
        }
        $last = end($rows);
        return ((int) ($last["sort"] ?? 0)) + 1;
    }

    function run(Controller $ctl) {
        $this->assignList($ctl);
        $ctl->show_main_area("list.tpl", "Sample Original Sort");
    }

    function add_dialog(Controller $ctl) {
        $ctl->assign("row", ["title" => "", "note" => ""]);
        $ctl->show_multi_dialog("sample_sort_original_management_add", "add.tpl", "追加", 760);
    }

    function add_save(Controller $ctl) {
        $row = [
            "title" => trim((string) ($ctl->POST("title") ?? "")),
            "note" => trim((string) ($ctl->POST("note") ?? "")),
            "sort" => $this->nextSort($ctl),
        ];
        if ($row["title"] === "") {
            $ctl->res_error_message("title", "タイトルを入力してください。");
        }
        if ($ctl->count_res_error_message() > 0) {
            return;
        }
        $ctl->db($this->tableName())->insert($row);
        $ctl->close_multi_dialog("sample_sort_original_management_add");
        $this->reloadList($ctl);
    }

    function edit_dialog(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->assign("row", $row);
        $ctl->show_multi_dialog("sample_sort_original_management_edit", "edit.tpl", "編集", 760);
    }

    function edit_save(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $row["title"] = trim((string) ($ctl->POST("title") ?? ""));
        $row["note"] = trim((string) ($ctl->POST("note") ?? ""));
        if ($row["title"] === "") {
            $ctl->res_error_message("title", "タイトルを入力してください。");
        }
        if ($ctl->count_res_error_message() > 0) {
            return;
        }
        $ctl->db($this->tableName())->update($row);
        $ctl->close_multi_dialog("sample_sort_original_management_edit");
        $this->reloadList($ctl);
    }

    function delete_confirm(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->assign("row", $row);
        $ctl->show_multi_dialog("sample_sort_original_management_delete", "delete_confirm.tpl", "削除確認", 520);
    }

    function delete_execute(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->db($this->tableName())->delete($id);
        $ctl->close_multi_dialog("sample_sort_original_management_delete");
        $this->reloadList($ctl);
    }

    function sort_save(Controller $ctl) {
        $log = (string) ($ctl->POST("log") ?? "");
        $ids = array_values(array_filter(array_map("intval", explode(",", $log))));
        $sort = 1;
        foreach ($ids as $id) {
            $row = $ctl->db($this->tableName())->get($id);
            if (empty($row)) {
                continue;
            }
            $row["sort"] = $sort;
            $ctl->db($this->tableName())->update($row);
            $sort++;
        }
        $this->reloadList($ctl);
    }
}
