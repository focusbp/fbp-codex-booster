
{**
This template need the following parameters.
$field : The field format array of db/ai_field.fmt
$row : array of the values.
**}

{assign name $field["parameter_name"]}
{assign type $field["type"]}

{if $type == "text"}
	{$row.$name}
	
{else if $type == "number"}
	{html_display_value field=$field value=$row[$name]}
	
{else if $type == "float"}
	{html_display_value field=$field value=$row[$name]}
	
{else if $type == "textarea"}
	{$row.$name|escape|nl2br nofilter}

	
{else if $type == "textarea_links"}
	{$row.$name|nl2br|url_link nofilter}
	
{else if $type == "markdown"}
	{$row.$name|markdown nofilter}
	
{else if $type == "dropdown"}
	{if $field["colors"][$row.$name]}
		{assign background $field["colors"][$row.$name]}
		{assign color "#FFF"}
	{else}
		{assign background "#FFF"}
		{assign color "#000"}
	{/if}
	{if $field["is_table_dropdown"]}
		<span class="item_viewer_dropdown_left">
			<span class="item_viewer_dropdown_badge" style='background: {$background};color: {$color};padding:2px 10px;border-radius:5px;'>{$field["options"][$row[$name]]}</span>
		</span>
	{else}
		<span class="item_viewer_dropdown_badge" style='background: {$background};color: {$color};padding:2px 10px;border-radius:5px;'>{$field["options"][$row[$name]]}</span>
	{/if}
	
{else if $type == "date"}
	{html_date value=$row.$name}
	
{else if $type == "datetime"}
	<span class="world_datetime">{$row.$name}</span>
	
{else if $type == "time"}
	{$row.$name}
	
{else if $type == "year_month"}
	{html_year_month value=$row.$name}
	
{else if $type == "checkbox"}
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
				<span style='background: {$background};color: {$color};margin-bottom:2px;{$otherstyle}'>{$option}</span>
			{/if}
	    {/if}
	{/foreach}
			
{else if $type == "radio"}
	<span style='background: #{$item_setting[$name]["options"][$row[$name]]["color"]};'>{$field["options"][$row[$name]]}</span>
	
{else if $type == "color"}
	<span style="display:block;width:30px;height:30px;border-radius:5px;background:{$row[$name]};margin:0 auto;">&nbsp;</span>
	
{else if $type == "image"}
    {if $row[$name]}
		{if $ctl->is_public_media_class($class|default:null)}
			{assign file $ctl->get_file_info($row[$name], false)}
		{else}
			{assign file $ctl->get_file_info($row[$name])}
		{/if}

		{if empty($field["image_width"])}
			{assign "image_width" "200"}
		{else}
			{if $_use_thumbnail}
				{assign "image_width" $field["image_width_thumbnail"]}	
			{else}
				{assign "image_width" $field["image_width"]}
			{/if}
		{/if}
		{if $_use_thumbnail}
			{assign "path" $file["path_th"]}
		{else}
			{assign "path" $file["path"]}
		{/if}
		{if $ctl->is_public_media_class($class|default:null)}
			{assign "media_url" $ctl->get_public_media_url($path, $file["filename"], "image")}
			<img src="{$media_url}" style="max-width:{$image_width}px;">
		{else}
			<img src="app.php?class=db_exe&function=view_image&path={$path}" style="max-width:{$image_width}px;">
		{/if}
    {/if}
{else if $type == "file"}
    {if $row[$name]}
		{if $ctl->is_public_media_class($class|default:null)}
			{assign file $ctl->get_file_info($row[$name], false)}
		{else}
			{assign file $ctl->get_file_info($row[$name])}
		{/if}
		{if $ctl->is_public_media_class($class|default:null)}
			{assign "media_url" $ctl->get_public_media_url($file["path"], $file["filename"], "file")}
			<a href="{$media_url}" download="{$file["filename"]|escape}">{$file["filename"]}</a>
		{else}
			<span class="download-link lang download-btn-file_editscreen" data-class="{$class}" data-function="download_file" data-filename="{$file["filename"]}" data-path="{$file["path"]}">{$file["filename"]}</span>
		{/if}
    {/if}
	
{else if $type == "vimeo"}
    {if $row[$name] != ""}
	<span class="vimeo" data-vimeo_id="{$row[$name]}" style="min-height:300px;width:150px;"></span>
    {/if}
    
{/if}
