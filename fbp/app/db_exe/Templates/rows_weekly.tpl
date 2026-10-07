
{if $testserver || $setting.show_developer_panel == 1}
	<div class="db_edit_button_area">
		<button class="ajax-link" invoke-class="db" invoke-function="edit" data-id="{$db_id}" data-mode="database">
			<span class="material-symbols-outlined">description</span>
		</button>
	</div>
	<div style="clear:both;"></div>
{/if}

{if $show_search_box }
	<div class="search_box" style="margin:8px 0 14px 0;padding:10px 14px 12px 14px;border:1px solid #d7deea;border-radius:10px;background:#f8fafc;display:flex;flex-direction:column;justify-content:center;">
		<p style="margin:0 0 8px 0;min-height:18px;display:flex;align-items:center;font-size:13px;line-height:1.2;font-weight:bold;color:#334155;">{t key="db_exe.search_panel_title"}</p>
		<div class="search_left">
			<form id="form_{$timestamp}" class="search_form_flex calendar_search_form">
				<input type="hidden" name="db_id" value="{$db_id}">
				{foreach $search_group as $field}
					<div class="search_form_item field_type_{$field.type|escape}" data-parameter-name="{$field.parameter_name|escape}" data-parameter-title="{$field.parameter_title|escape}" data-field-type="{$field.type|escape}">
						{include file="{$base_template_dir}/__item_search.tpl"}
						<p class="error_message error_{$field["parameter_name"]}" style="margin-top:0px;"></p>
						{assign var="search_field_list" value=$search_field_list|cat:$field.parameter_name}
						{assign var="search_field_list" value=$search_field_list|cat:","}
					</div>
				{/foreach}
				<input type="hidden" name="_search_field_list" value="{$search_field_list}">
			</form>
		</div>
		<div class="search_right" style="display:none;">
			<button class="ajax-link lang" data-class="{$class}" data-function="search_weekly_calendar" data-form="form_{$timestamp}">Search</button>
		</div>
	</div>
{/if}

<div>
	<div style="float:right;margin-bottom: 8px;">
		
		{if $flg_add_button}
		<button class="ajax-link lang" data-class="{$class}" data-function="add" data-db_id="{$db_id}"><span class="material-symbols-outlined" style="font-size:18px;vertical-align:text-bottom;margin-right:2px;">add_circle</span>{t key="common.add"}</button>
		{/if}

		<button class="ajax-link lang" data-class="{$class}" data-function="unassigned_tasks" data-db_id="{$db_id}">Show Unassigned Tasks</button>
		
		{foreach $additionals as $a}
			{if $a.button_type == 0}
			<button class="ajax-link lang {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}">{$a.button_title}</button>
			{else}
				<button class="ajax-link lang {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}" style="padding:6px;"><span class="material-symbols-outlined">{$a.button_title}</span></button>
			{/if}
			
		{/foreach}

		
		
	</div>
</div>

<div id="calendar_area">

	<div class="timezone_area">
		<p><span class="material-symbols-outlined">globe_asia</span><span>{$timezone}</span></p>
	</div>

	<button class="ajax-link ui-button ui-corner-all change_week_button calendar_week_navigation" data-d="{$time_previous}" data-class="{$class}" data-function="set_datetime" data-db_id="{$db_id}"><span class="material-symbols-outlined">chevron_left</span></button>
	<button class="ajax-link ui-button ui-corner-all change_week_button calendar_week_navigation" data-d="{$time_today}" data-class="{$class}" data-function="set_datetime" data-db_id="{$db_id}"><span class="material-symbols-outlined">today</span></button>
	<button class="ajax-link ui-button ui-corner-all change_week_button calendar_week_navigation" data-d="{$time_next}" data-class="{$class}" data-function="set_datetime" data-db_id="{$db_id}"><span class="material-symbols-outlined">chevron_right</span></button>

	<div class="calendar_day_navigation">
		<button class="ajax-link" data-d="{$time_previous_day}" data-class="{$class}" data-function="set_datetime" data-db_id="{$db_id}">前日</button>
		<button class="ajax-link" data-d="{$time_today}" data-class="{$class}" data-function="set_datetime" data-db_id="{$db_id}">今日</button>
		<button class="ajax-link" data-d="{$time_next_day}" data-class="{$class}" data-function="set_datetime" data-db_id="{$db_id}">翌日</button>
	</div>

	<div class="calendar_datepicker_area">
		<form id="calendar_datepicer_form_{$timestamp}">
			<input type="hidden" name="class" value="{$class|escape}">
			<input type="hidden" name="function" value="set_datetime">
			<input type="hidden" name="db_id" value="{$db_id}">
			<input type="text" name="d" class="datepicker" id="calendar_datepicker" value="{$calendar_datepicker_d}" style="width:100%;box-sizing:border-box;">
		</form>
	</div>



	<div style="clear:both;"></div>

	<div class="calendar">

		{foreach $calendar_arr as $s}
			<div class="calendar_day_bar{if $s.timestamp == $calendar_selected_day} calendar_selected_day{/if}" style="width:calc(100% / 7);">
				<div class="calendar_box days_{$s.w}">
					<p class="calendar_title"><span class="year">{$s.year}</span><span class="month lang">{$s.month}</span></p><p class="calendar_title"><span class="date">{$s.date}</span><span class="day">（<span class="lang">{$s.day}</span>）</span></p>
				</div>

				{foreach $s["hours"] as $h}
					<div class="calendar_box {$occupied_travel[$h.target_time]} {$occupied[$h.target_time]}" data-datetime="{$h.target_time}">
						{if !is_array($assigned[$h.target_time])}
							<p>{$h.h}:00</p>
						{/if}
						{foreach $assigned_travel[$h.target_time] as $travel}
							<div class="travel_marker travel_{$travel.type}">
								{if $travel.type == "before"}移動開始{else}移動終了{/if} {$travel.time}
							</div>
						{/foreach}
						{foreach $assigned[$h.target_time] as $row}
							{include file="_row_for_weekly.tpl"}
						{/foreach}
					</div>
				{/foreach}

			</div>
		{/foreach}

	</div>
</div>

<script>
	$("#calendar_datepicker").off("change.calendarDate").on("change.calendarDate", function () {
		appcon("app.php", new FormData(this.form));
	});
	$(".active_indicator_trigger").on("click", function () {
		$(".active_indicator").removeClass("indicator_active");
		$(this).parents(".active_indicator").addClass("indicator_active");
	});
</script>
