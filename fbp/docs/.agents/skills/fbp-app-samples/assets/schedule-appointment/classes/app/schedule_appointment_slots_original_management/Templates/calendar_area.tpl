<div id="schedule_appointment_slots_original_management_calendar_area">
	<div class="original_calendar_toolbar">
		<div class="original_calendar_toolbar_main">
			<div class="original_calendar_nav">
				<button type="button" class="ajax-link ui-button ui-corner-all original_calendar_change_week_button" data-class="schedule_appointment_slots_original_management" data-function="set_week" data-mode="previous">
					<span class="material-symbols-outlined">chevron_left</span>
				</button>
				<button type="button" class="ajax-link ui-button ui-corner-all original_calendar_change_week_button" data-class="schedule_appointment_slots_original_management" data-function="set_week" data-mode="current">
					<span class="material-symbols-outlined">today</span>
				</button>
				<button type="button" class="ajax-link ui-button ui-corner-all original_calendar_change_week_button" data-class="schedule_appointment_slots_original_management" data-function="set_week" data-mode="next">
					<span class="material-symbols-outlined">chevron_right</span>
				</button>
			</div>

			<div class="original_calendar_datepicker_area original_calendar_jump_compact">
				<form id="schedule_appointment_slots_original_management_jump_form">
					<input type="hidden" name="mode" value="jump">
					<input type="text" name="jump_date" class="datepicker original_calendar_jump_input" value="{$jump_date|escape}">
					<button type="button" class="ajax-link ui-button ui-corner-all" data-class="schedule_appointment_slots_original_management" data-function="set_week" data-form="schedule_appointment_slots_original_management_jump_form">
						<span class="material-symbols-outlined">keyboard_return</span>
					</button>
				</form>
			</div>

			<div class="original_calendar_heading">
				<span>{$week_label|escape}</span>
				<span class="original_calendar_timezone">{$timezone_label|escape}</span>
				<span class="original_calendar_timezone">{$current_user_name|escape}</span>
			</div>
		</div>
		<div class="original_calendar_add_action">
			<button type="button" class="ajax-link button_link" data-class="schedule_appointment_slots_original_management" data-function="public_url_dialog">Public URL</button>
			<button type="button" class="ajax-link button_link" data-class="schedule_appointment_slots_original_management" data-function="add_dialog">Add Slot</button>
		</div>
	</div>

	<div class="original_calendar_grid">
		{foreach from=$calendar_days item=day}
			<div class="original_calendar_day_bar">
				<div class="original_calendar_box">
					<p class="calendar_title">
						<span class="original_calendar_title_date">{$day.date_label|escape}</span>
						<span class="day">（{$day.day_label|escape}）</span>
					</p>
				</div>

				{foreach from=$day.hours item=hour}
					<div class="original_calendar_box {$occupied[$hour.target_time]|default:''|escape}" data-datetime="{$hour.target_time|escape}">
						<div class="schedule_appointment_slot_hour">
							{if !is_array($assigned[$hour.target_time])}
								<p>{$hour.label|escape}</p>
							{/if}
							<button type="button" class="ajax-link listbutton" data-class="schedule_appointment_slots_original_management" data-function="add_dialog" data-starts_at="{$hour.target_time|escape}">
								<span class="material-symbols-outlined">add</span>
							</button>
						</div>
						{foreach from=$assigned[$hour.target_time] item=row}
							<div class="original_calendar_task active_indicator schedule_appointment_slot_status_{$row.status|escape}">
								<span class="original_calendar_controlbox">
									<button type="button" class="ajax-link listbutton original_screen_action_delete" data-class="schedule_appointment_slots_original_management" data-function="delete_dialog" data-id="{$row.id|escape}">
										<span class="material-symbols-outlined">delete</span>
									</button>
									<button type="button" class="ajax-link listbutton original_screen_action_edit" data-class="schedule_appointment_slots_original_management" data-function="edit_dialog" data-id="{$row.id|escape}">
										<span class="material-symbols-outlined">edit_square</span>
									</button>
								</span>
								<span class="original_calendar_task_time">{$row.start_time|escape} - {$row.end_time|escape}</span>
								<div class="original_calendar_task_message">
									<div class="row_style">
										<span class="row_value"><p>{$row.title|escape}</p></span>
									</div>
									<div class="row_style">
										<span class="row_value"><p>{fields_view_direct db="schedule_appointment_slots" fields="status" data=$row}</p></span>
									</div>
									{if $row.customer_name != ""}
										<div class="row_style">
											<span class="row_value"><p>{$row.customer_name|escape}</p></span>
										</div>
									{/if}
									{if $row.customer_message != ""}
										<div class="row_style">
											<span class="row_value"><p>{$row.customer_message|escape|nl2br}</p></span>
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

<style>
	.schedule_appointment_slot_hour {
		align-items: center;
		display: flex;
		justify-content: space-between;
		min-height: 20px;
	}
	.schedule_appointment_slot_hour .listbutton {
		align-items: center;
		display: inline-flex;
		height: 24px;
		justify-content: center;
		width: 24px;
	}
	.schedule_appointment_slot_status_booked {
		border-color: #2563eb;
	}
	.schedule_appointment_slot_status_blocked {
		border-color: #64748b;
		opacity: .82;
	}
</style>
