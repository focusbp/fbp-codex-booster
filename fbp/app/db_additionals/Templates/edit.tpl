<div style="display: block;overflow: hidden;">
	<form id="additional_form_{$timestamp}">

		<input type="hidden" name="id" value="{$post.id}">
		<input type="hidden" name="target_area" value="{$target_area|default:''}">
		<input type="hidden" name="reload_db_id" value="{$reload_db_id|default:0}">
		
		
		<div class="form-row">
			<p class="lang" style="font-weight:bold;">{t key="db_additionals.button_title"}</p>
			<p style="font-size:14px;margin-top:0px;">{t key="db_additionals.button_title_help"}</p>
			<div style="display: flex;">
				{html_options name="button_type" options=$button_type_opt selected=$post.button_type style="width:300px;margin-right:10px;"}
			<input type="text" name="button_title" value="{$post.button_title}">
			</div>
			<p class="error_message error_button_title"></p>
		</div>
			
		<div class="form-row">
			<p class="lang" style="font-weight:bold;">{t key="db_additionals.place"}</p>
			<div style="display:flex; gap:10px;">
			  <div style="width:50%;">
				  {html_options name="tb_name" options=$database_names selected=$post["tb_name"]}
			  </div>

			  <div style="width:50%;">
				  {html_options name="place" options=$place_opt selected=$post["place"]}
			  </div>
			</div>
			<p class="error_message error_place lang">{$errors['place']}</p>
		</div>

		<div class="form-row">
			<p class="lang" style="font-weight:bold;">{t key="db_additionals.show_button"}</p>
			{html_options name="show_button" options=$show_button_opt selected=$post["show_button"]}
		</div>


			<div class="form-row" style="display:flex; gap:10px;">
				<div style="width:50%;">
					<p class="lang" style="font-weight:bold;">{t key="db_additionals.action_name"}</p>
					<p style="font-size:18px;font-weight:bold;">{$post.class_name}</p>
					<input type="hidden" name="class_name" value="{$post.class_name}">
					<p class="error_message error_class_name"></p>
				</div>
				<div style="width:50%;">
					<p class="lang" style="font-weight:bold;">{t key="db_additionals.function_name"}</p>
					<p style="font-size:18px;font-weight:bold;">{$post.function_name|default:'run'}</p>
					<input type="hidden" name="function_name" value="{$post.function_name|default:'run'}">
					<p class="error_message error_function_name"></p>
				</div>
			</div>



		<div style="margin-top:5px;">
			
			<button type="button"
					class="ajax-link lang"
					invoke-function="edit_exe"
					data-reload_db_id="{$reload_db_id}"
					data-flg="save_only">
			  {t key="common.save"}
			</button>
			
			
		</div>

	{include file="_single_record_places.tpl"}
	</form>


</div>
					
