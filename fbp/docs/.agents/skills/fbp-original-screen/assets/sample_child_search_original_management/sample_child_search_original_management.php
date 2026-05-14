<?php

class sample_child_search_original_management
{
    private function tableName() {
        return "sample_child";
    }

    private function sideAreaId() {
        return "#sample_child_search_original_management_side_area";
    }

    private function defaultFilter() {
        return [
            "keyword" => "",
            "status" => "",
        ];
    }

    private function filterSessionKey($parentId) {
        return "sample_child_search_original_management_filter_" . (int) $parentId;
    }

    private function currentFilter(Controller $ctl, $parentId) {
        $raw = $ctl->get_session($this->filterSessionKey($parentId));
        return is_array($raw) ? array_merge($this->defaultFilter(), $raw) : $this->defaultFilter();
    }

    private function saveFilter(Controller $ctl, $parentId, array $filter) {
        $ctl->set_session($this->filterSessionKey($parentId), array_merge($this->defaultFilter(), $filter));
    }

    private function rows(Controller $ctl, $parentId, array $filter) {
        $rows = $ctl->db($this->tableName())->select("parent_id", (int) $parentId, true, "AND", "id", SORT_DESC);
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

    private function assignSideArea(Controller $ctl, $parentId, array $filter, int $max) {
        $allRows = $this->rows($ctl, $parentId, $filter);
        $rows = array_slice($allRows, 0, $max);
        $ctl->assign("rows", $rows);
        $ctl->assign("count", count($allRows));
        $ctl->assign("max", $max);
        $ctl->assign("is_last", count($allRows) <= $max);
        $ctl->assign("parent_id", (int) $parentId);
        $ctl->assign("db_id", (int) ($ctl->POST("db_id") ?? 0));
        $ctl->assign("filter", $filter);
        $ctl->assign("status_options", $ctl->get_constant_array("table/sample_child/status", true));
    }

    private function showSidePanel(Controller $ctl, $parentId, array $filter, int $max = 10) {
        $this->assignSideArea($ctl, $parentId, $filter, $max);
        $ctl->assign("table_title", "Sample Child");
        $ctl->show_second_work_area("rows_child.tpl", 760);
    }

    function rows_child(Controller $ctl) {
        $parentId = (int) ($ctl->POST("parent_id") ?? 0);
        if ($parentId <= 0) {
            $ctl->show_notification_text("親データが見つかりません。");
            return;
        }
        $this->showSidePanel($ctl, $parentId, $this->currentFilter($ctl, $parentId), 10);
    }

    function apply_child_filter(Controller $ctl) {
        $parentId = (int) ($ctl->POST("parent_id") ?? 0);
        if ($parentId <= 0) {
            $ctl->show_notification_text("親データが見つかりません。");
            return;
        }
        $filter = [
            "keyword" => trim((string) ($ctl->POST("keyword") ?? "")),
            "status" => trim((string) ($ctl->POST("status") ?? "")),
        ];
        $this->saveFilter($ctl, $parentId, $filter);
        $this->assignSideArea($ctl, $parentId, $filter, 10);
        $ctl->reload_area($this->sideAreaId(), "rows_child_area.tpl");
    }

    function rows_child_more(Controller $ctl) {
        $parentId = (int) ($ctl->POST("parent_id") ?? 0);
        if ($parentId <= 0) {
            $ctl->show_notification_text("親データが見つかりません。");
            return;
        }
        $max = $ctl->increment_post_value("max", 10);
        $this->assignSideArea($ctl, $parentId, $this->currentFilter($ctl, $parentId), $max);
        $ctl->reload_area($this->sideAreaId(), "rows_child_area.tpl");
    }
}
