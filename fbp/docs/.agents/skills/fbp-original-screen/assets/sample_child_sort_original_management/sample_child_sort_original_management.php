<?php

class sample_child_sort_original_management
{
    private function tableName() {
        return "sample_child_sort";
    }

    private function sideAreaId() {
        return "#sample_child_sort_original_management_side_area";
    }

    private function rows(Controller $ctl, $parentId) {
        return $ctl->db($this->tableName())->select("parent_id", (int) $parentId, true, "AND", "sort", SORT_ASC);
    }

    private function assignSideArea(Controller $ctl, $parentId) {
        $ctl->assign("rows", $this->rows($ctl, $parentId));
        $ctl->assign("parent_id", (int) $parentId);
        $ctl->assign("db_id", (int) ($ctl->POST("db_id") ?? 0));
    }

    function rows_child(Controller $ctl) {
        $parentId = (int) ($ctl->POST("parent_id") ?? 0);
        if ($parentId <= 0) {
            $ctl->show_notification_text("親データが見つかりません。");
            return;
        }
        $this->assignSideArea($ctl, $parentId);
        $ctl->assign("table_title", "Sample Child Sort");
        $ctl->show_second_work_area("rows_child.tpl", 760);
    }

    function sort_child_save(Controller $ctl) {
        $parentId = (int) ($ctl->POST("parent_id") ?? 0);
        if ($parentId <= 0) {
            $ctl->show_notification_text("親データが見つかりません。");
            return;
        }
        $ids = array_values(array_filter(array_map("intval", explode(",", (string) ($ctl->POST("log") ?? "")))));
        $sort = 1;
        foreach ($ids as $id) {
            $row = $ctl->db($this->tableName())->get($id);
            if (empty($row) || (int) ($row["parent_id"] ?? 0) !== $parentId) {
                continue;
            }
            $row["sort"] = $sort;
            $ctl->db($this->tableName())->update($row);
            $sort++;
        }
        $this->assignSideArea($ctl, $parentId);
        $ctl->reload_area($this->sideAreaId(), "rows_child_area.tpl");
    }
}
