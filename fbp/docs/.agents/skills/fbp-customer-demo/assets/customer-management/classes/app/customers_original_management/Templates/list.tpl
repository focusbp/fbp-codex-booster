<div class="original_screen_page customers_demo_page lang_check_area" data-classname="customers_original_management">
	<style>
		.customers_demo_page { padding: 4px 0 16px; color: #1f2937; }
		.customers_demo_toolbar { gap: 8px; margin-bottom: 12px; }
		.customers_demo_search_panel { margin-bottom: 12px; }
		.customers_demo_search_form { width: 100%; }
		.customers_demo_search_fields { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
		.customers_demo_search_fields .search_form_item { min-width: 180px; }
		.customers_demo_search_fields .field_type_text { flex: 1 1 260px; }
		.customers_demo_search_fields .field_type_dropdown { flex: 0 1 220px; }
		.customers_demo_search_actions { margin-left: auto; display: flex; justify-content: flex-end; align-items: center; }
		.customers_demo_search_actions .button_link { margin-bottom: 5px; }
		.customers_demo_action_cell { min-width: 176px; }
		.customers_demo_pdf_icon .material-symbols-outlined { color: #991b1b; }
		.customers_demo_detail_icon .material-symbols-outlined { color: #1d4ed8; }
		.customers_demo_detail_grid { display: grid; grid-template-columns: 160px 1fr; gap: 8px 12px; }
		.customers_demo_detail_label { color: #64748b; font-size: 12px; }
		.customers_demo_detail_value { min-width: 0; }
	</style>

	<div class="original_screen_toolbar original_screen_toolbar_end customers_demo_toolbar">
			<button type="button" class="ajax-link button_link" data-class="customers_csv" data-function="upload_form">Import CSV</button>
			<button type="button" class="download-link button_link" data-class="customers_csv" data-function="download" data-filename="customers.csv">Export CSV</button>
			<button type="button" class="download-link button_link" data-class="customers_pdf" data-function="list_pdf" data-filename="customers.pdf" data-open_new_tab="true">List PDF</button>
		<button type="button" class="ajax-link button_link" invoke-function="add_dialog">Add Customer</button>
	</div>

	<div class="search_box original_search_panel customers_demo_search_panel">
		<p class="original_search_panel_title">Search Conditions</p>
		<div class="original_search_panel_body">
			<div class="search_left">
				<form id="customers_original_management_search_form" class="search_form_flex customers_demo_search_form">
					<input type="hidden" name="_customers_search" value="1">
					<div class="customers_demo_search_fields">
						<div class="search_form_item field_type_dropdown">
							{fields_form_original name="status" type="dropdown" value=$search.status options_arr=$status_filter_options title="Status" item_margin_top="0px"}
						</div>
						<div class="search_form_item field_type_text">
							{fields_form_original name="keyword" type="text" value=$search.keyword title="Keyword" item_margin_top="0px"}
						</div>
						<div class="customers_demo_search_actions">
							<button type="button" class="ajax-link button_link" invoke-function="reload_list" data-form="customers_original_management_search_form">Search</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>

	{include file="list_area.tpl"}
</div>
