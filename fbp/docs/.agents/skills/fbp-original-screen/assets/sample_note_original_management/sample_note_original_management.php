<?php

class sample_note_original_management
{
    private function tableName() {
        return "sample_note";
    }

    private function listAreaId() {
        return "#sample_note_original_management_list_area";
    }

    private function defaultFilter() {
        return [
            "keyword" => "",
            "status" => "",
        ];
    }

    private function filterSessionKey() {
        return "sample_note_original_management_filter";
    }

    private function currentFilter(Controller $ctl) {
        $raw = $ctl->get_session($this->filterSessionKey());
        return is_array($raw) ? array_merge($this->defaultFilter(), $raw) : $this->defaultFilter();
    }

    private function saveFilter(Controller $ctl, array $filter) {
        $ctl->set_session($this->filterSessionKey(), array_merge($this->defaultFilter(), $filter));
    }

    private function rows(Controller $ctl, array $filter) {
        $rows = $ctl->db($this->tableName())->getall("id", SORT_DESC);
        $result = [];
        foreach ($rows as $row) {
            if ($filter["status"] !== "" && (string) ($row["status"] ?? "") !== (string) $filter["status"]) {
                continue;
            }
            $keyword = trim((string) ($filter["keyword"] ?? ""));
            if ($keyword !== "") {
                $haystack = trim((string) (($row["title"] ?? "") . " " . ($row["detail"] ?? "")));
                if ($haystack === "" || mb_stripos($haystack, $keyword) === false) {
                    continue;
                }
            }
            $result[] = $row;
        }
        return $result;
    }

    private function assignListArea(Controller $ctl, array $filter, int $max = 10) {
        $all_rows = $this->rows($ctl, $filter);
        $count = count($all_rows);
        $rows = array_slice($all_rows, 0, $max);
        $ctl->assign("rows", $rows);
        $ctl->assign("count", $count);
        $ctl->assign("max", $max);
        $ctl->assign("is_last", ($count <= $max));
    }

    function run(Controller $ctl) {
        $filter = $this->currentFilter($ctl);
        $ctl->assign("filter", $filter);
        $ctl->assign("status_options", $ctl->get_constant_array("table/sample_note/status", true));
        $this->assignListArea($ctl, $filter, 10);
        $ctl->show_main_area("list.tpl", "Sample Original Management");
    }

    function apply_filter(Controller $ctl) {
        $filter = [
            "keyword" => trim((string) ($ctl->POST("keyword") ?? "")),
            "status" => trim((string) ($ctl->POST("status") ?? "")),
        ];
        $this->saveFilter($ctl, $filter);
        $this->assignListArea($ctl, $filter, 10);
        $ctl->reload_area($this->listAreaId(), "list_area.tpl");
    }

    function rows_more(Controller $ctl) {
        $filter = $this->currentFilter($ctl);
        $max = $ctl->increment_post_value("max", 10);
        $this->assignListArea($ctl, $filter, $max);
        $ctl->reload_area($this->listAreaId(), "list_area.tpl");
    }

    function add_dialog(Controller $ctl) {
        $ctl->assign("row", ["title" => "", "detail" => "", "status" => ""]);
        $ctl->assign("status_options", $ctl->get_constant_array("table/sample_note/status", true));
        $ctl->show_multi_dialog("sample_note_original_management_add", "add.tpl", "追加", 760);
    }

    function add_save(Controller $ctl) {
        $row = [
            "title" => trim((string) ($ctl->POST("title") ?? "")),
            "detail" => trim((string) ($ctl->POST("detail") ?? "")),
            "status" => trim((string) ($ctl->POST("status") ?? "")),
        ];
        if ($row["title"] === "") {
            $ctl->res_error_message("title", "題名を入力してください。");
        }
        if ($ctl->count_res_error_message() > 0) {
            return;
        }
        $ctl->db($this->tableName())->insert($row);
        $ctl->close_multi_dialog("sample_note_original_management_add");
        $this->assignListArea($ctl, $this->currentFilter($ctl), 10);
        $ctl->reload_area($this->listAreaId(), "list_area.tpl");
    }

    function edit_dialog(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->assign("row", $row);
        $ctl->assign("status_options", $ctl->get_constant_array("table/sample_note/status", true));
        $ctl->show_multi_dialog("sample_note_original_management_edit", "edit.tpl", "編集", 760);
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
        $row["status"] = trim((string) ($ctl->POST("status") ?? ""));
        if ($row["title"] === "") {
            $ctl->res_error_message("title", "題名を入力してください。");
        }
        if ($ctl->count_res_error_message() > 0) {
            return;
        }
        $ctl->db($this->tableName())->update($row);
        $ctl->close_multi_dialog("sample_note_original_management_edit");
        $this->assignListArea($ctl, $this->currentFilter($ctl), 10);
        $ctl->reload_area($this->listAreaId(), "list_area.tpl");
    }

    function delete_confirm(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->assign("row", $row);
        $ctl->show_multi_dialog("sample_note_original_management_delete", "delete_confirm.tpl", "削除確認", 520);
    }

    function delete_execute(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->db($this->tableName())->delete($id);
        $ctl->close_multi_dialog("sample_note_original_management_delete");
        $this->assignListArea($ctl, $this->currentFilter($ctl), 10);
        $ctl->reload_area($this->listAreaId(), "list_area.tpl");
    }
}
