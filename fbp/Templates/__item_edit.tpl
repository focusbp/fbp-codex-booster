
{**
This template need the following parameters.
$field : The field format array of db/ai_field.fmt
$row : array of the values.
**}

{assign name $field["parameter_name"]}
{assign type $field["type"]}
{assign title $field["parameter_title"]}

<div class="field_edit">

<h6>
    <span class="lang"
      {if $field.title_color|default:'' != ''}
        style="color: {$field.title_color|escape};"
      {/if}
	>{$title}</span>
    {if $field["validation"] == 1}
        <span style="color:#860000;">*</span>
    {/if}	
</h6>
{if $field["parameter_description"] != ""}
	<p class="style_parameter_description">{$field["parameter_description"]|nl2br nofilter}</p>
{/if}

	
{if $type == "text"}
	
	<input type="text" name="{$name}" value="{$row.$name}">
	
	
{else if $type == "number"}
	{if $name != "id"}
		
		<input type="text" name="{$name}" value="{$row.$name}">
	{/if}
	
{else if $type == "float"}
	
	<input type="text" name="{$name}" value="{$row.$name}">
	
{else if $type == "textarea"}
	
	{if $field.max_bytes > 0}
		<textarea name="{$name}" class="wordcounter" data-counter_max="{$field.max_bytes}">{$row.$name}</textarea>
	{else}
		<textarea name="{$name}">{$row.$name}</textarea>
	{/if}
	
{else if $type == "textarea_links"}
	
	{if $field.max_bytes > 0}
		<textarea name="{$name}" class="wordcounter" data-counter_max="{$field.max_bytes}">{$row.$name}</textarea>
	{else}
		<textarea name="{$name}">{$row.$name}</textarea>
	{/if}
	
{else if $type == "markdown"}
	
	{if $field.max_bytes > 0}
		<textarea name="{$name}" class="wordcounter" data-counter_max="{$field.max_bytes}">{$row.$name}</textarea>
	{else}
		<textarea name="{$name}">{$row.$name}</textarea>
	{/if}
	
{else if $type == "dropdown"}
	
	{html_options name=$name options=$field["options"] selected=$row[$name]}
	
{else if $type == "date"}
	
	{html_input_date name="{$name}" value="{$row.$name}"}
	
{else if $type == "datetime"}
	
	<input type="text" name="{$name}" value="{$row.$name}" class="world_datetime">
	
{else if $type == "time"}
	
	<input type="text" name="{$name}" value="{$row.$name}" class="timepicker">
	
{else if $type == "year_month"}
	
	<input type="text" name="{$name}" value="{html_year_month value=$row.$name}" class="year_month_picker">
	
{else if $type == "checkbox"}
	{foreach $field["options"] as $key=>$option}
	    {if $row.$name}
		{if is_array($row.$name) && in_array($key,$row.$name)}
		    {assign is_checked checked}
		{else}
		    {assign is_checked ''}
		{/if}
	    {/if}
	<div>
		<input type="checkbox" id="{$name}_{$key}" name="{$name}[]" value="{$key}" {$is_checked}>
		<label for="{$name}_{$key}">{$option}</label>
	</div>
	{/foreach}
			
{else if $type == "radio"}
	{html_radios name="{$name}" options=$field["options"] selected=$row[$name] class="checkboxradio"}

   
{else if $type == "file"}
    {if $row[$name]}
		{assign file $ctl->get_file_info($row[$name])}
		<p class="download-link lang download-btn-file_editscreen" data-class="{$class}" data-function="download_file" data-filename="{$file["filename"]}" data-path="{$file["path"]}">{$file["filename"]}</p>
    {/if}
	<input type="file" name="{$name}" class="fr_image_paste" data-text="File Uploader">

{else if $type == "color"}
	
	<input type="text" name="{$name}" value="{$row[$name]}" class="colorpicker">
	
{else if $type == "image"}
	{if $row[$name]}
		{if empty($field["image_width"])}
			{assign "image_width" "200"}
		{else}
			{assign "image_width" $field["image_width"]}
		{/if}
	{assign file $ctl->get_file_info($row[$name])}
	<img src="app.php?class=db_exe&function=view_image&path={$file["path"]}&{$timestamp}" style="max-width:{$image_width}px	;">
	{/if}
	<input type="file" name="{$name}" class="fr_image_paste" data-text="Image Uploader">
	
{else if $type == "vimeo"}
    
    <div class="fr_vimeo_div">
	<div class="vimeo_upload_area">
		<input type="hidden" name="vimeo_title" value="from system" id="vimeo_title">
		<input type="hidden" name="vimeo_description" value="from system" id="vimeo_description">
		<p style="word-break:inherit;">{t key="vimeo.help.upload_or_input"}</p>
		<div style="width:50%;float: left;">
			<p style="margin-top:0px;">{t key="vimeo.upload_video"}</p>
			<input id="sliced_file" type="file" data-mode="vimeo" />
		</div>
		<div style="width:50%;float: left;">
			<p style="margin-top:0px;">{t key="vimeo.id"}</p>
			<input type="text" id="vimeo_id" name="{$name}" value="{$row[$name]}" style="margin-top:0px;">
		</div>
		<p class="error" id="sliced_error"></p>
	</div>
	</div>
	
{/if}

</div>
