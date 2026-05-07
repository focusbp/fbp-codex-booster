<?php

class minimal_calendar_original_management
{
    private function tableName() {
        return "sample_schedule";
    }

    private function calendarAreaId() {
        return "#minimal_calendar_original_management_calendar_area";
    }

    private function weekSessionKey() {
        return "minimal_calendar_original_management_week";
    }

    private function startOfWeek(int $time) {
        $base = strtotime(date("Y-m-d 00:00:00", $time));
        $dayOfWeek = (int) date("N", $base);
        return strtotime("-" . ($dayOfWeek - 1) . " day", $base);
    }

    private function currentWeekStart(Controller $ctl) {
        $saved = (int) ($ctl->get_session($this->weekSessionKey()) ?? 0);
        return $saved > 0 ? $this->startOfWeek($saved) : $this->startOfWeek(time());
    }

    private function saveWeekStart(Controller $ctl, int $weekStart) {
        $ctl->set_session($this->weekSessionKey(), $this->startOfWeek($weekStart));
    }

    private function assignCalendarArea(Controller $ctl, int $weekStart) {
        $rows = $ctl->db($this->tableName())->getall("datetime", SORT_ASC);
        $assigned = [];
        foreach ($rows as $row) {
            $datetime = (int) ($row["datetime"] ?? 0);
            if ($datetime < $weekStart || $datetime >= strtotime("+7 day", $weekStart)) {
                continue;
            }
            $row["start_time"] = date("H:i", $datetime);
            $slotTime = $datetime - ($datetime % 3600);
            $assigned[$slotTime][] = $row;
        }

        $days = [];
        $dayLabels = ["月", "火", "水", "木", "金", "土", "日"];
        for ($i = 0; $i < 7; $i++) {
            $dayStart = strtotime("+" . $i . " day", $weekStart);
            $hours = [];
            for ($hour = 8; $hour <= 18; $hour++) {
                $hours[] = [
                    "label" => sprintf("%02d:00", $hour),
                    "target_time" => $dayStart + ($hour * 3600),
                ];
            }
            $days[] = [
                "date_label" => date("n/j", $dayStart),
                "day_label" => $dayLabels[$i],
                "hours" => $hours,
            ];
        }

        $ctl->assign("assigned", $assigned);
        $ctl->assign("week_label", date("Y/m/d", $weekStart) . " - " . date("Y/m/d", strtotime("+6 day", $weekStart)));
        $ctl->assign("week_previous", strtotime("-7 day", $weekStart));
        $ctl->assign("week_next", strtotime("+7 day", $weekStart));
        $ctl->assign("calendar_days", $days);
    }

    function run(Controller $ctl) {
        $weekStart = $this->currentWeekStart($ctl);
        $this->assignCalendarArea($ctl, $weekStart);
        $ctl->show_main_area("calendar.tpl", "Minimal Original Calendar");
    }

    function set_week(Controller $ctl) {
        $mode = (string) ($ctl->POST("mode") ?? "");
        $weekStart = $this->currentWeekStart($ctl);
        if ($mode === "previous") {
            $weekStart = strtotime("-7 day", $weekStart);
        } elseif ($mode === "next") {
            $weekStart = strtotime("+7 day", $weekStart);
        }
        $this->saveWeekStart($ctl, $weekStart);
        $this->assignCalendarArea($ctl, $weekStart);
        $ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
    }
}
