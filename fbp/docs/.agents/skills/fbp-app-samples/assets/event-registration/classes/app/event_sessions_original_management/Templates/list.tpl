<div class="original_screen_page event_registration_admin_page lang_check_area" data-classname="event_sessions_original_management">
	<style>
		.event_registration_admin_page { padding: 4px 0 16px; color: #1f2937; }
		.event_registration_toolbar { gap: 8px; margin-bottom: 12px; }
		.event_registration_search_panel { margin-bottom: 12px; }
		.event_registration_search_form { width: 100%; }
		.event_registration_search_fields { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
		.event_registration_search_fields .search_form_item { min-width: 180px; }
		.event_registration_search_fields .field_type_text { flex: 1 1 260px; }
		.event_registration_search_fields .field_type_dropdown { flex: 0 1 220px; }
		.event_registration_search_actions { margin-left: auto; display: flex; justify-content: flex-end; align-items: center; }
		.event_registration_search_actions .button_link { margin-bottom: 5px; }
		.event_registration_action_cell { min-width: 220px; }
		.event_registration_link_box { padding: 14px 16px; border: 1px solid #dbe3ef; border-radius: 8px; background: #f8fafc; }
		.event_registration_link_box a { color: #1d4ed8; word-break: break-all; }
		.event_registration_participants_button { min-height: 32px; padding: 0 10px; white-space: nowrap; }
		.event_registration_participants_side { padding: 8px 0 18px; color: #1f2937; }
		.event_registration_participants_side h6 { margin: 0 0 6px; font-size: 18px; }
		.event_registration_participants_context { margin: 0 0 12px; color: #64748b; }
		.event_registration_participants_toolbar { display: flex; justify-content: flex-end; margin: 0 0 12px; }
		.event_registration_participants_table { width: 100%; margin-top: 10px; border-collapse: collapse; }
		.event_registration_participants_table th,
		.event_registration_participants_table td { padding: 9px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: middle; }
		.event_registration_participants_table th { color: #475569; font-size: 12px; background: #f8fafc; }
		.event_registration_participants_empty { margin: 12px 0; padding: 12px; border: 1px solid #dbe3ef; border-radius: 6px; color: #64748b; background: #f8fafc; }
		.event_registration_participants_action { width: 104px; text-align: right; white-space: nowrap; }
	</style>

	<div class="original_screen_toolbar original_screen_toolbar_end event_registration_toolbar">
		<button type="button" class="ajax-link button_link" invoke-function="public_url_dialog">Public Registration URL</button>
		<button type="button" class="ajax-link button_link" invoke-function="add_dialog">Add Session</button>
	</div>

	<div class="search_box original_search_panel event_registration_search_panel">
		<p class="original_search_panel_title">Search Conditions</p>
		<div class="original_search_panel_body">
			<div class="search_left">
				<form id="event_sessions_original_management_search_form" class="search_form_flex event_registration_search_form">
					<input type="hidden" name="_registration_session_search" value="1">
					<div class="event_registration_search_fields">
						<div class="search_form_item field_type_dropdown">
							{fields_form_original name="status" type="dropdown" value=$search.status options_arr=$session_status_filter_options title="Status" item_margin_top="0px"}
						</div>
						<div class="search_form_item field_type_text">
							{fields_form_original name="keyword" type="text" value=$search.keyword title="Keyword" item_margin_top="0px"}
						</div>
						<div class="event_registration_search_actions">
							<button type="button" class="ajax-link button_link" invoke-function="reload_list" data-form="event_sessions_original_management_search_form">Search</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>

	{include file="list_area.tpl"}
</div>
