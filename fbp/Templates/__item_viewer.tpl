
{**
This template need the following parameters.
$field : The field format array of db/ai_field.fmt
$row : array of the values.
**}

{assign name $field["parameter_name"]}
{assign type $field["type"]}

{if $type == "text"}
	<p>{$row.$name}</p>
	
{else if $type == "number"}
	<p style="white-space: nowrap;text-align: right;" class="number_max_width">{html_display_value field=$field value=$row[$name]}</p>
	
{else if $type == "float"}
	<p style="white-space: nowrap;text-align: right;" class="number_max_width">{html_display_value field=$field value=$row[$name]}</p>
	
{else if $type == "textarea"}
	<p>{$row.$name|escape|nl2br nofilter}</p>

	
{else if $type == "textarea_links"}
	<p>{$row.$name|nl2br|url_link nofilter}</p>
	
{else if $type == "markdown"}
	<p>{$row.$name|markdown nofilter}</p>
	
{else if $type == "dropdown"}
	{if $field["colors"][$row.$name]}
		{assign background $field["colors"][$row.$name]}
		{assign color "#FFF"}
	{else}
		{assign background "transparent"}
		{assign color "#000"}
	{/if}
	{if $field["is_table_dropdown"]}
		<p style='background: {$background};color: {$color};padding:4px 10px;border-radius:5px;text-align:left;'>{$field["options"][$row[$name]]}</p>
	{else}
		<p style='background: {$background};color: {$color};padding:4px 10px;border-radius:5px;text-align:center;'>{$field["options"][$row[$name]]}</p>
	{/if}
	
{else if $type == "date"}
	<p style="white-space: nowrap;">{html_date value=$row.$name}</p>
	
{else if $type == "datetime"}
	<p class="world_datetime">{$row.$name}</p>
	
{else if $type == "time"}
	<p style="white-space: nowrap;">{$row.$name}</p>
	
{else if $type == "year_month"}
	<p style="white-space: nowrap">{html_year_month value=$row.$name}</p>
	
{else if $type == "checkbox"}
	<p>
	<ul class="checkbox_ul">
	{foreach $field["options"] as $key=>$option}
	    {if is_array($row.$name)}
			{if in_array($key,$row.$name)}
				{if $field["colors"][$key]}
					{assign background $field["colors"][$key]}
					{assign color "#FFF"}
					{assign otherstyle "border-radius:5px;padding:4px;"}
				{else}
					{assign background "#FFF"}
					{assign color "#000"}
				{/if}
				<li style='background: {$background};color: {$color};margin-bottom:2px;{$otherstyle}'>{$option}</li>
			{/if}
	    {/if}
	{/foreach}
	</ul>
	</p>
			
{else if $type == "radio"}
	<p style="text-align: center;"><span style='background: #{$item_setting[$name]["options"][$row[$name]]["color"]};'>{$field["options"][$row[$name]]}</span></p>
	
{else if $type == "color"}
	<p><span style="display:block;width:30px;height:30px;border-radius:5px;background:{$row[$name]};margin:0 auto;">&nbsp;</span></p>
	
{else if $type == "image"}
    {if $row[$name]}
		
		{if empty($field["image_width"])}
			{assign "image_width" "200"}
		{else}
			{if $_use_thumbnail}
				{assign "image_width" $field["image_width_thumbnail"]}	
			{else}
				{assign "image_width" $field["image_width"]}
			{/if}
		{/if}
		{assign file $ctl->get_file_info($row[$name])}
		{if $_use_thumbnail}
			{assign "path" $file["path_th"]}
		{else}
			{assign "path" $file["path"]}
		{/if}
	<p><img src="app.php?class=db_exe&function=view_image&path={$path}" style="max-width:{$image_width}px;"></p>
    {/if}
{else if $type == "file"}
    {if $row[$name]}
		{assign file $ctl->get_file_info($row[$name])}
		<p class="download-link lang download-btn-file_editscreen" data-class="{$class}" data-function="download_file" data-filename="{$file["filename"]}" data-path="{$file["path"]}">{$file["filename"]}</p>
    {/if}
	
{else if $type == "vimeo"}
    {if $row[$name] != ""}
	<div class="vimeo" data-vimeo_id="{$row[$name]}" style="min-height:300px;width:150px;"></div>
    {/if}
    
{/if}
