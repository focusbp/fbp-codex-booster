<div id="minimal_calendar_original_management_calendar_area">
    <div style="margin-bottom:12px;">
        <button type="button" class="ajax-link" data-class="minimal_calendar_original_management" data-function="set_week" data-mode="previous">前週</button>
        <button type="button" class="ajax-link" data-class="minimal_calendar_original_management" data-function="set_week" data-mode="next">次週</button>
        <span style="margin-left:12px;font-weight:bold;">{$week_label}</span>
    </div>
    <div style="display:flex;width:100%;">
        {foreach from=$calendar_days item=day}
            <div style="width:calc(100% / 7);border-top:1px solid #ccc;">
                <div style="border-bottom:1px solid #ccc;padding:4px;">
                    <strong>{$day.date_label}</strong>（{$day.day_label}）
                </div>
                {foreach from=$day.hours item=hour}
                    <div style="border-bottom:1px solid #ddd;padding:4px;min-height:42px;">
                        {if !is_array($assigned[$hour.target_time])}
                            {$hour.label}
                        {/if}
                        {foreach from=$assigned[$hour.target_time] item=row}
                            <div style="border:1px solid #4ba3ff;padding:4px;margin-top:4px;background:#fff;">
                                {$row.start_time} {$row.title|escape}
                            </div>
                        {/foreach}
                    </div>
                {/foreach}
            </div>
        {/foreach}
    </div>
</div>
