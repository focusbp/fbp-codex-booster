<form id="dbs_db_fields_edit_form_{$data.id}">

	<input type="hidden" name="id" value="{$data.id}">
	<input type="hidden" name="db_id" value="{$data.db_id}">

	<div>
		<p class="lang">{t key="db.field_name_programming"}:</p>

		<input type="text" name="parameter_name" value="{$data.parameter_name}">
		<p class="error_message lang error_parameter_name"></p>
	</div>
	<div>
		<p class="lang">{t key="db.field_title_users"}:</p>
		<input type="text" name="parameter_title" value="{$data.parameter_title}">

		<p class="error_message lang error_parameter_title"></p>
	</div>

	<div>
		<p class="lang">{t key="db.field_description"}:</p>
		<input type="text" name="parameter_description" value="{$data.parameter_description}">
		<p class="error_message lang error_parameter_description"></p>
	</div>

	<div>
		<p class="lang">{t key="db.field_type"}:</p>
		{html_options name="type" id="type_event" selected=$data.type options=$type_opt}
	</div>

	<div id="area_option">
		{include file="_area_option.tpl"}
	</div>

	<div>
		<p class="lang">{t key="db.parent_relation_flag"}:</p>
		{html_options name="parent_relation_flag" selected=$data.parent_relation_flag|default:0 options=$parent_relation_flag_opt}
		<p class="error_message lang error_parent_relation_flag"></p>
	</div>
	<div>
		<p class="lang">{t key="db.parent_relation_parent"}:</p>
		{html_options name="parent_db_id" selected=$data.parent_db_id|default:0 options=$parent_db_opt}
		<p class="error_message lang error_parent_db_id"></p>
	</div>

	<div>
		<p class="lang">{t key="db.data_length_bytes"}:</p>
		<input class="field_length" type="text" name="length" value="{$data.length}">
		<p class="recommended_length"></p>
		<p class="error_message lang error_length"></p>
	</div>

	<div class="image_width">
		<p class="lang">{t key="db.image_width"}:</p>
		<input type="text" name="image_width" value="{$data.image_width}">
	</div>

	<div class="image_width">
		<p class="lang">{t key="db.thumbnail_width"}:</p>
		<input type="text" name="image_width_thumbnail" value="{$data.image_width_thumbnail}">
	</div>

	<div>
		<p class="lang">{t key="db.required"}:</p>
		{html_options name="validation" selected=$data.validation options=$validation_opt}
	</div>
	<div>
		<p class="lang">{t key="db.duplicate_check"}:</p>
		{html_options name="duplicate_check" selected=$data.duplicate_check options=$duplicate_check_opt}
	</div>
	<div>
		<p class="lang">{t key="db.format_check"}:</p>
		{html_options name="format_check" selected=$data.format_check options=$format_check_title_opt}
	</div>
	<div>
		<p class="lang">{t key="db.display_format"}:</p>
		{html_options name="display_format" selected=$data.display_format|default:0 options=$display_format_opt}
	</div>
	<div>
		<p class="lang">{t key="db.title_color"}:</p>
		<input type="text" name="title_color" value="{$data.title_color}" class="colorpicker">
	</div>


	<div>
		<button class="ajax-link lang" data-form="dbs_db_fields_edit_form_{$data.id}" data-class="{$class}" data-function="edit_fields_exe">{t key="common.update"}</button>
	</div>
</form>
