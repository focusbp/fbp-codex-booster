<div class="original_screen_page line_member_basic_page lang_check_area" data-classname="line_member_original_management">
	<style>
		.line_member_basic_page { padding:4px 0 16px; color:#1f2937; }
		.line_member_basic_toolbar { gap:8px; margin-bottom:12px; }
		.line_member_basic_search_panel { margin-bottom:12px; }
		.line_member_basic_search_form { width:100%; }
		.line_member_basic_search_fields { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
		.line_member_basic_search_fields .search_form_item { min-width:180px; }
		.line_member_basic_search_fields .field_type_text { flex:1 1 260px; }
		.line_member_basic_search_fields .field_type_dropdown { flex:0 1 220px; }
		.line_member_basic_search_actions { margin-left:auto; display:flex; justify-content:flex-end; align-items:center; }
		.line_member_basic_action_cell { min-width:120px; }
	</style>

	<div class="original_screen_toolbar original_screen_toolbar_end line_member_basic_toolbar">
		<button type="button" class="ajax-link button_link" invoke-function="add_dialog">Add LINE Member</button>
	</div>

	<div class="search_box original_search_panel line_member_basic_search_panel">
		<p class="original_search_panel_title">Search Conditions</p>
		<div class="original_search_panel_body">
			<div class="search_left">
				<form id="line_member_original_management_search_form" class="search_form_flex line_member_basic_search_form">
					<input type="hidden" name="_line_member_search" value="1">
					<div class="line_member_basic_search_fields">
						<div class="search_form_item field_type_dropdown">
							{fields_form_original name="member_type" type="dropdown" value=$search.member_type options_arr=$member_type_filter_options title="Member Type" item_margin_top="0px"}
						</div>
						<div class="search_form_item field_type_text">
							{fields_form_original name="keyword" type="text" value=$search.keyword title="Keyword" item_margin_top="0px"}
						</div>
						<div class="line_member_basic_search_actions">
							<button type="button" class="ajax-link button_link" invoke-function="reload_list" data-form="line_member_original_management_search_form">Search</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>

	{include file="list_area.tpl"}
</div>
