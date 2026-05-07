<div id="sample_calendar_original_management_calendar_area">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
        <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;min-width:0;">
            <div style="float:none;">
                <button type="button" class="ajax-link ui-button ui-corner-all change_week_button" data-class="sample_calendar_original_management" data-function="set_week" data-mode="previous">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button type="button" class="ajax-link ui-button ui-corner-all change_week_button" data-class="sample_calendar_original_management" data-function="set_week" data-mode="current">
                    <span class="material-symbols-outlined">today</span>
                </button>
                <button type="button" class="ajax-link ui-button ui-corner-all change_week_button" data-class="sample_calendar_original_management" data-function="set_week" data-mode="next">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>

            <div class="calendar_datepicker_area" style="float:none;margin-left:0;">
                <form id="sample_calendar_original_management_jump_form">
                    <input type="hidden" name="mode" value="jump">
                    <input type="text" name="jump_date" class="datepicker" value="{$jump_date}" style="width:120px;">
                    <button type="button" class="ajax-link ui-button ui-corner-all" data-class="sample_calendar_original_management" data-function="set_week" data-form="sample_calendar_original_management_jump_form">
                        <span class="material-symbols-outlined">keyboard_return</span>
                    </button>
                </form>
            </div>

            <div style="margin-top:12px;font-weight:bold;color:#334155;font-size:26px;line-height:1.2;min-width:0;">
                <span>{$week_label}</span>
                <span style="margin-left:10px;font-size:13px;font-weight:normal;color:#64748b;">{$timezone_label|escape}</span>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:flex-end;flex:0 0 auto;min-height:48px;">
            <button type="button" class="ajax-link button_link" data-class="sample_calendar_original_management" data-function="add_dialog">追加</button>
        </div>
    </div>

    <div class="calendar">
        {foreach from=$calendar_days item=day}
            <div class="calendar_day_bar" style="width:calc(100% / 7);">
                <div class="calendar_box days_{$day.day_label|escape}">
                    <p class="calendar_title">
                        <span class="date">{$day.date_label}</span>
                        <span class="day">（{$day.day_label}）</span>
                    </p>
                </div>

                {foreach from=$day.hours item=hour}
                    <div class="calendar_box {$occupied_travel[$hour.target_time]} {$occupied[$hour.target_time]}" data-datetime="{$hour.target_time}">
                        {if !is_array($assigned[$hour.target_time])}
                            <p>{$hour.label}</p>
                        {/if}
                        {foreach from=$assigned_travel[$hour.target_time] item=travel}
                            <div class="travel_marker travel_{$travel.type}">
                                {if $travel.type == "before"}移動開始{else}移動終了{/if} {$travel.time}
                            </div>
                        {/foreach}
                        {foreach from=$assigned[$hour.target_time] item=row}
                            <div class="task active_indicator">
                                <span class="controlbox">
                                    <button type="button" class="ajax-link listbutton" data-class="sample_calendar_original_management" data-function="delete_confirm" data-id="{$row.id}" style="float:right;color:#dc2626;margin-right:5px;">
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                    <button type="button" class="ajax-link listbutton" data-class="sample_calendar_original_management" data-function="edit_dialog" data-id="{$row.id}" style="float:right;color:#2d2d2d;">
                                        <span class="material-symbols-outlined">edit_square</span>
                                    </button>
                                </span>
                                <span class="time">{$row.start_time} - {$row.end_time}</span>
                                <div class="task_message">
                                    <div class="row_style">
                                        <span class="row_value"><p>{$row.title|escape}</p></span>
                                    </div>
                                    {if $row.travel_start_time != "" || $row.travel_end_time != ""}
                                        <div class="row_style">
                                            <span class="row_value">
                                                <p>
                                                    {if $row.travel_start_time != ""}移動前 {$row.travel_start_time}{/if}
                                                    {if $row.travel_start_time != "" && $row.travel_end_time != ""} / {/if}
                                                    {if $row.travel_end_time != ""}移動後 {$row.travel_end_time}{/if}
                                                </p>
                                            </span>
                                        </div>
                                    {/if}
                                    {if $row.status != ""}
                                        <div class="row_style">
                                            <span class="row_value"><p>{fields_view_direct db="sample_schedule" fields="status" data=$row}</p></span>
                                        </div>
                                    {/if}
                                    {if $row.detail != ""}
                                        <div class="row_style">
                                            <span class="row_value"><p>{$row.detail|escape|nl2br}</p></span>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        {/foreach}
                    </div>
                {/foreach}
            </div>
        {/foreach}
    </div>
</div>
