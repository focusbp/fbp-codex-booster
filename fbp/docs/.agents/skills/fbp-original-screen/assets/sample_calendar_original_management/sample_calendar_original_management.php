<?php

class sample_calendar_original_management
{
    private function tableName() {
        return "sample_schedule";
    }

    private function title() {
        return "Sample Calendar Original Management";
    }

    private function calendarAreaId() {
        return "#sample_calendar_original_management_calendar_area";
    }

    private function defaultRow() {
        return [
            "title" => "",
            "detail" => "",
            "datetime" => "",
            "duration" => "60",
            "travel_before" => "0",
            "travel_after" => "0",
            "status" => "",
        ];
    }

    private function formFields() {
        return [
            "title",
            "detail",
            "datetime",
            "duration",
            "travel_before",
            "travel_after",
            "status",
        ];
    }

    private function weekSessionKey() {
        return "sample_calendar_original_management_week";
    }

    private function startOfWeek(int $time) {
        $base = strtotime(date("Y-m-d 00:00:00", $time));
        $dayOfWeek = (int) date("N", $base);
        return strtotime("-" . ($dayOfWeek - 1) . " day", $base);
    }

    private function currentWeekStart(Controller $ctl) {
        $saved = (int) ($ctl->get_session($this->weekSessionKey()) ?? 0);
        if ($saved > 0) {
            return $this->startOfWeek($saved);
        }
        return $this->startOfWeek(time());
    }

    private function saveWeekStart(Controller $ctl, int $weekStart) {
        $ctl->set_session($this->weekSessionKey(), $this->startOfWeek($weekStart));
    }

    private function rows(Controller $ctl, int $weekStart) {
        $weekEnd = strtotime("+7 day", $weekStart);
        $rows = $ctl->db($this->tableName())->getall("datetime", SORT_ASC);
        $result = [];
        foreach ($rows as $row) {
            $datetime = (int) ($row["datetime"] ?? 0);
            if ($datetime < $weekStart || $datetime >= $weekEnd) {
                continue;
            }
            $result[] = $row;
        }
        usort($result, function ($a, $b) {
            $adt = (int) ($a["datetime"] ?? 0);
            $bdt = (int) ($b["datetime"] ?? 0);
            if ($adt !== $bdt) {
                return $adt <=> $bdt;
            }
            return ((int) ($a["id"] ?? 0)) <=> ((int) ($b["id"] ?? 0));
        });
        return $result;
    }

    private function assignCalendarArea(Controller $ctl, int $weekStart) {
        $rows = $this->rows($ctl, $weekStart);
        $occupied = [];
        $occupiedTravel = [];
        $assigned = [];
        $assignedTravel = [];
        $startHour = 8;
        $endHour = 18;
        foreach ($rows as &$row) {
            $startTs = (int) ($row["datetime"] ?? 0);
            $duration = max(0, (int) ($row["duration"] ?? 60));
            $travelBefore = max(0, (int) ($row["travel_before"] ?? 0));
            $travelAfter = max(0, (int) ($row["travel_after"] ?? 0));
            $endTs = $startTs + ($duration * 60);
            $travelStartTs = $startTs - ($travelBefore * 60);
            $travelEndTs = $endTs + ($travelAfter * 60);

            $row["start_time"] = date("H:i", $startTs);
            $row["end_time"] = date("H:i", $endTs);
            $row["travel_start_time"] = $travelBefore > 0 ? date("H:i", $travelStartTs) : "";
            $row["travel_end_time"] = $travelAfter > 0 ? date("H:i", $travelEndTs) : "";

            $slotTime = $startTs - ($startTs % 3600);
            $assigned[$slotTime][] = $row;

            $startHourTs = $slotTime;
            $endHourTs = ceil($endTs / 3600) * 3600;
            for ($i = $startHourTs; $i < $endHourTs; $i += 3600) {
                $occupied[$i] = "occupied";
            }

            if ($travelBefore > 0) {
                $travelBeforeStartHour = $travelStartTs - ($travelStartTs % 3600);
                $travelBeforeEndHour = ceil($startTs / 3600) * 3600;
                for ($i = $travelBeforeStartHour; $i < $travelBeforeEndHour; $i += 3600) {
                    $occupiedTravel[$i] = "occupied_travel";
                }
                $assignedTravel[$travelBeforeStartHour][] = [
                    "type" => "before",
                    "time" => date("H:i", $travelStartTs),
                ];
            }

            if ($travelAfter > 0) {
                $travelAfterStartHour = $endTs - ($endTs % 3600);
                $travelAfterEndHour = ceil($travelEndTs / 3600) * 3600;
                for ($i = $travelAfterStartHour; $i < $travelAfterEndHour; $i += 3600) {
                    $occupiedTravel[$i] = "occupied_travel";
                }
                $assignedTravel[$travelAfterStartHour][] = [
                    "type" => "after",
                    "time" => date("H:i", $travelEndTs),
                ];
            }

            $visibleStartTs = $travelBefore > 0 ? $travelStartTs : $startTs;
            $visibleEndTs = $travelAfter > 0 ? $travelEndTs : $endTs;
            $hour = (int) date("G", $visibleStartTs);
            $endHourCandidate = (int) date("G", $visibleEndTs);
            if ($hour < $startHour) {
                $startHour = $hour;
            }
            if ($endHourCandidate > $endHour) {
                $endHour = $endHourCandidate;
            }
        }
        if ($startHour > 8) {
            $startHour = 8;
        }
        if ($endHour < 18) {
            $endHour = 18;
        }

        $calendarDays = [];
        $dayKeys = [
            "cron.weekday.mon",
            "cron.weekday.tue",
            "cron.weekday.wed",
            "cron.weekday.thu",
            "cron.weekday.fri",
            "cron.weekday.sat",
            "cron.weekday.sun",
        ];
        for ($i = 0; $i < 7; $i++) {
            $dayStart = strtotime("+" . $i . " day", $weekStart);
            $hours = [];
            for ($hour = $startHour; $hour <= $endHour; $hour++) {
                $hours[] = [
                    "label" => sprintf("%02d:00", $hour),
                    "target_time" => $dayStart + ($hour * 3600),
                ];
            }
            $calendarDays[] = [
                "date_label" => date($this->monthDayFormat($ctl), $dayStart),
                "day_label" => $ctl->t($dayKeys[$i]),
                "is_today" => (date("Y-m-d", $dayStart) === date("Y-m-d")),
                "hours" => $hours,
            ];
        }

        $ctl->assign("occupied", $occupied);
        $ctl->assign("occupied_travel", $occupiedTravel);
        $ctl->assign("assigned", $assigned);
        $ctl->assign("assigned_travel", $assignedTravel);
        $ctl->assign("week_previous", strtotime("-7 day", $weekStart));
        $ctl->assign("week_next", strtotime("+7 day", $weekStart));
        $ctl->assign("week_current", $this->startOfWeek(time()));
        $ctl->assign("jump_date", date("Y-m-d", $weekStart));
        $ctl->assign("week_label", date($this->dateFormat($ctl), $weekStart) . " - " . date($this->dateFormat($ctl), strtotime("+6 day", $weekStart)));
        $ctl->assign("timezone_label", date_default_timezone_get());
        $ctl->assign("calendar_days", $calendarDays);
    }

    private function assignForm(Controller $ctl, array $row) {
        $ctl->assign("row", array_merge($this->defaultRow(), $row));
        $ctl->assign("form_fields", $this->formFields());
    }

    private function collectRowFromPost(Controller $ctl, array $base = []) {
        $row = $base;
        foreach ($this->formFields() as $fieldName) {
            $row[$fieldName] = $ctl->POST($fieldName) ?? "";
        }
        return $row;
    }

    private function dateFormat(Controller $ctl) {
        $setting = $ctl->get_setting();
        return !empty($setting["date_format"]) ? (string) $setting["date_format"] : "Y/m/d";
    }

    private function monthDayFormat(Controller $ctl) {
        $setting = $ctl->get_setting();
        return !empty($setting["month_day_format"]) ? (string) $setting["month_day_format"] : "n/j";
    }

    private function validateRow(Controller $ctl, array $row) {
        if (trim((string) ($row["title"] ?? "")) === "") {
            $ctl->res_error_message("title", "件名を入力してください。");
        }
        if (empty($row["datetime"])) {
            $ctl->res_error_message("datetime", "日時を入力してください。");
        }
        if ((int) ($row["duration"] ?? 0) <= 0) {
            $ctl->res_error_message("duration", "所要時間は 1 以上で入力してください。");
        }
        if ((int) ($row["travel_before"] ?? 0) < 0) {
            $ctl->res_error_message("travel_before", "移動時間（前）は 0 以上で入力してください。");
        }
        if ((int) ($row["travel_after"] ?? 0) < 0) {
            $ctl->res_error_message("travel_after", "移動時間（後）は 0 以上で入力してください。");
        }
    }

    function run(Controller $ctl) {
        $weekStart = $this->currentWeekStart($ctl);
        $this->assignCalendarArea($ctl, $weekStart);
        $ctl->show_main_area("calendar.tpl", $this->title());
    }

    function set_week(Controller $ctl) {
        $mode = (string) ($ctl->POST("mode") ?? "");
        $weekStart = $this->currentWeekStart($ctl);
        if ($mode === "previous") {
            $weekStart = strtotime("-7 day", $weekStart);
        } elseif ($mode === "next") {
            $weekStart = strtotime("+7 day", $weekStart);
        } elseif ($mode === "current") {
            $weekStart = $this->startOfWeek(time());
        } elseif ($mode === "jump") {
            $jumpDate = trim((string) ($ctl->POST("jump_date") ?? ""));
            if ($jumpDate !== "") {
                $weekStart = $this->startOfWeek(strtotime($jumpDate));
            }
        }
        $this->saveWeekStart($ctl, $weekStart);
        $this->assignCalendarArea($ctl, $weekStart);
        $ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
    }

    function add_dialog(Controller $ctl) {
        $this->assignForm($ctl, $this->defaultRow());
        $ctl->show_multi_dialog("sample_calendar_original_management_add", "add.tpl", "予定追加", 760);
    }

    function add_save(Controller $ctl) {
        $row = $this->collectRowFromPost($ctl, $this->defaultRow());
        $this->validateRow($ctl, $row);
        if ($ctl->count_res_error_message() > 0) {
            return;
        }
        $ctl->db($this->tableName())->insert($row);
        $ctl->close_multi_dialog("sample_calendar_original_management_add");
        $this->assignCalendarArea($ctl, $this->currentWeekStart($ctl));
        $ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
    }

    function edit_dialog(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $this->assignForm($ctl, $row);
        $ctl->show_multi_dialog("sample_calendar_original_management_edit", "edit.tpl", "予定編集", 760);
    }

    function edit_save(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $row = $this->collectRowFromPost($ctl, $row);
        $row["id"] = $id;
        $this->validateRow($ctl, $row);
        if ($ctl->count_res_error_message() > 0) {
            return;
        }
        $ctl->db($this->tableName())->update($row);
        $ctl->close_multi_dialog("sample_calendar_original_management_edit");
        $this->assignCalendarArea($ctl, $this->currentWeekStart($ctl));
        $ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
    }

    function delete_confirm(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->assign("row", $row);
        $ctl->show_multi_dialog("sample_calendar_original_management_delete", "delete_confirm.tpl", "削除確認", 520);
    }

    function delete_execute(Controller $ctl) {
        $id = (int) ($ctl->POST("id") ?? 0);
        $row = $ctl->db($this->tableName())->get($id);
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません。");
            return;
        }
        $ctl->db($this->tableName())->delete($id);
        $ctl->close_multi_dialog("sample_calendar_original_management_delete");
        $this->assignCalendarArea($ctl, $this->currentWeekStart($ctl));
        $ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
    }
}
