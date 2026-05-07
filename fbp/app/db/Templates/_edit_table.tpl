
<div class="edit_100">
	<form id="dbs_db_edit_form_{$data.id}" class="edit_form">

		<input type="hidden" name="id" value="{$data.id}">
		<input type="hidden" name="tb_name" value="{$data.tb_name}">
		<input type="hidden" id="has_child_tables" value="{if $has_child_tables}1{else}0{/if}">

		<p class="lang">{t key="db.menu_name"}</p>
		<input type="text" name="menu_name" value="{$data.menu_name}">
		{if $flg_change_tb_name}
		<p class="lang">{t key="db.table_name"}</p>
		<input type="text" name="tb_name" value="{$data.tb_name}">
		{/if}
		<p class="lang">{t key="db.description_or_memo"}</p>
		<input type="text" name="description" value="{$data.description}">
		<p>作成方法</p>
		{html_options id="screen_build_type" name="screen_build_type" options=$screen_build_type_opt selected=$data["screen_build_type"]}
		<p class="lang">{t key="db.parent_table"}</p>
		{html_options name="parent_tb_id" options=$parents_opt selected=$data["parent_tb_id"]}
		<div class="standard-screen-only-fields">
			<p class="lang">{t key="db.cascade_delete"}</p>
			{html_options name="cascade_delete_flag" options=$cascade_delete_flag_opt selected=$data["cascade_delete_flag"]}
		</div>
		<div id="child_table_dropdown_area" style="margin-top:8px; display:none;">
			<p class="lang">{t key="db.display_method_child_dropdown"}</p>
			{html_options name="dropdown_item_display_type" options=$dropdown_item_display_type_opt selected=$data["dropdown_item_display_type"]}
			<div id="dropdown_item_field_area" style="margin-top:8px; display:none;">
				<p class="lang">{t key="db.identifier_field_child_table"}</p>
				{html_options name="dropdown_item" options=$dropdown_item_opt selected=$data["dropdown_item"]}
			</div>
			<div id="dropdown_item_template_area" style="margin-top:8px; display:none;">
				<p class="lang">{t key="db.display_fields_for_dropdown"}</p>
				<input type="text" name="dropdown_item_template" value="{$data.dropdown_item_template}">
				<div style="margin-top:6px;line-height:1.8;">
					{foreach $dropdown_item_opt as $field_name=>$field_label}
						{if $field_name != "id"}
							<a href="#" class="dropdown-template-token" data-token="{$field_name|escape}" style="margin-right:8px;">{$field_label|escape}</a>
						{/if}
					{/foreach}
				</div>
			</div>
		</div>
		<p class="lang">{t key="db.menu"}</p>
		{html_options name="show_menu" options=$show_menu_opt selected=$data["show_menu"]}
		<p class="lang">{t key="db.menu_visibility"}</p>
		{html_options name="menu_visibility" options=$menu_visibility_opt selected=$data["menu_visibility"]}
		<div class="standard-screen-only-fields">
			<p class="lang">{t key="db.sort"}</p>
			{html_options id="sortkey" name="sortkey" options=$sortkey_opt selected=$data["sortkey"]}
			<p class="lang">{t key="db.sort_order"}</p>
			{html_options id="sort_order" name="sort_order" options=$sort_order_opt selected=$data["sort_order"]}
			<p class="lang">{t key="db.side_panel_width"}</p>
			<input type="text" name="list_width" value="{$data["list_width"]}">
			<p class="lang">{t key="db.dialog_width"}</p>
			<input type="text" name="edit_width" value="{$data["edit_width"]}">
			<p class="lang">{t key="db.list_type"}</p>
			{html_options id="list_type" name="list_type" options=$list_type_opt selected=$data["list_type"]}
			<p class="lang">{t key="db.side_panel_list_type"}</p>
			{html_options id="side_list_type" name="side_list_type" options=$side_list_type_opt selected=$data["side_list_type"]}
			<p class="lang">{t key="db.show_id_on_list"}</p>
			{html_options id="show_id" name="show_id" options=$show_id_opt selected=$data["show_id"]}
			<p class="lang">{t key="db.show_id_on_search"}</p>
			{html_options id="show_search_id" name="show_search_id" options=$show_id_opt selected=$data["show_search_id"]}
			<p class="lang">{t key="db.duplicate_icon"}</p>
			{html_options id="show_duplicate" name="show_duplicate" options=$show_duplicate_opt selected=$data["show_duplicate"]}
			<p class="lang">{t key="db.show_icon_on_parent_list"}</p>
			{html_options id="show_icon_on_parent_list" name="show_icon_on_parent_list" options=$show_icon_on_parent_list_opt selected=$data["show_icon_on_parent_list"]}
			<p class="lang">{t key="db.post_action_hook_class"}</p>
			<input type="text" name="post_action_class" value="{$data.post_action_class}">
		</div>




			<script>
				{literal}
				var dropdown_screen_build_type_event = function () {
					let screen_build_type = $("#screen_build_type").val();
					let isOriginal = screen_build_type == 1;
					$(".standard-screen-only-fields").toggle(!isOriginal);
					$(".standard-screen-only-fields").find("input, select, textarea, button").prop('disabled', isOriginal);
					if (isOriginal) {
						return;
					}
					dropdown_list_type_event();
				}
				var dropdown_list_type_event = function () {
					if ($("#screen_build_type").val() == 1) {
						$("#sortkey").prop('disabled', true);
						$("#sort_order").prop('disabled', true);
						return;
					}
					let list_type = $("#list_type").val();
					if (list_type == 0) {
					$("#sortkey").prop('disabled', false);
					$("#sort_order").prop('disabled', false);
				} else if (list_type == 1) {
					$("#sortkey").prop('disabled', true);
					$("#sort_order").prop('disabled', true);
				} else if (list_type == 2) {
					$("#sortkey").prop('disabled', true);
					$("#sort_order").prop('disabled', true);
				}
			}
			$("#screen_build_type").on("change", function () {
				dropdown_screen_build_type_event();
				dropdown_item_template_event();
			});
			$("#list_type").on("change", dropdown_list_type_event);
			dropdown_screen_build_type_event();

				var dropdown_item_template_event = function () {
					if ($("#screen_build_type").val() == 1) {
						$("#child_table_dropdown_area").hide();
						$("#dropdown_item_template_area").hide();
						$("#dropdown_item_field_area").hide();
						return;
					}
					let hasChildTables = $("#has_child_tables").val() === "1";
					if (!hasChildTables) {
						$("#child_table_dropdown_area").hide();
						$("#dropdown_item_template_area").hide();
						$("#dropdown_item_field_area").hide();
						return;
					}
					$("#child_table_dropdown_area").show();
				let mode = $("select[name='dropdown_item_display_type']").val();
				if (mode === "template") {
					$("#dropdown_item_template_area").show();
					$("#dropdown_item_field_area").hide();
				} else {
					$("#dropdown_item_template_area").hide();
					$("#dropdown_item_field_area").show();
				}
				}
				$("select[name='dropdown_item_display_type']").on("change", dropdown_item_template_event);
				$(".dropdown-template-token").on("click", function (e) {
					e.preventDefault();
					let token = "{" + "$" + $(this).data("token") + "}";
					let input = $("input[name='dropdown_item_template']");
					input.val((input.val() || "") + token);
					input.trigger("focus");
				});
				dropdown_item_template_event();
				{/literal}
			</script>



		<button class="ajax-link lang" data-form="dbs_db_edit_form_{$data.id}" data-class="{$class}" data-function="edit_exe">{t key="common.update"}</button>

	</form>


</div>
