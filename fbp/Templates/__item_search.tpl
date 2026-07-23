
{**
This template need the following parameters.
$field : The field format array of db/ai_field.fmt
$row : array of the values.
**}

{assign name $field["parameter_name"]}
{assign type $field["type"]}
{assign title $field["parameter_title"]}

<div class="field_edit" data-parameter-name="{$name|escape}" data-parameter-title="{$title|escape}" data-field-type="{$type|escape}">

{if empty($hide_title)}
<h6 class="lang">{$title}</h6>
{/if}
	
{if $type == "text"}
	
	<input type="text" name="{$name}" value="{$row.$name}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">
	
	
{else if $type == "number"}
	{if $name != "id"}
		
		<input type="text" name="{$name}" value="{$row.$name}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">
	{else}
		<input type="text" name="{$name}" value="{$row.$name}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">
	{/if}
	
{else if $type == "float"}
	
	<input type="text" name="{$name}" value="{$row.$name}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">
	
{else if $type == "textarea"}
	
	<input type="text" name="{$name}" value="{$row.$name}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">
	
{else if $type == "textarea_links"}
	
	{if $field.max_bytes > 0}
		<textarea name="{$name}" class="wordcounter" data-counter_max="{$field.max_bytes}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">{$row.$name}</textarea>
	{else}
		<textarea name="{$name}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">{$row.$name}</textarea>
	{/if}
	
{else if $type == "markdown"}
	
	{if $field.max_bytes > 0}
		<textarea name="{$name}" class="wordcounter" data-counter_max="{$field.max_bytes}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">{$row.$name}</textarea>
	{else}
		<textarea name="{$name}" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">{$row.$name}</textarea>
	{/if}
	
{else if $type == "dropdown"}
	<select name="{$name}">
		{foreach $field["options"] as $key=>$option}
			{assign var=option_value value=$key|cat:''}
			{assign var=selected_value value=$row[$name]|default:''|cat:''}
			<option value="{$option_value|escape}" {if $selected_value === $option_value}selected{/if}>{if $option_value === ''}{t key="common.unselected"}{else}{$option}{/if}</option>
		{/foreach}
	</select>

{else if $type == "checkbox"}
	<select name="{$name}">
		<option value="" {if $row[$name]|default:'' == ''}selected{/if}>{t key="common.unselected"}</option>
		<option value="__EMPTY__" {if $row[$name]|default:'' == '__EMPTY__'}selected{/if}>{t key="common.none_selected"}</option>
		{foreach $field["options"] as $key=>$option}
			<option value="{$key|escape}" {if $row[$name]|default:null !== null && $row[$name]|cat:'' === $key|cat:''}selected{/if}>{$option}</option>
		{/foreach}
	</select>
	
{else if $type == "date"}
	{assign var="from_name" value=$name|cat:"_from"}
	{assign var="to_name" value=$name|cat:"_to"}
	<div class="search_date_range" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:nowrap;">
		<div style="display:flex;align-items:center;gap:6px;flex:1 1 0;min-width:0;">
			<p style="margin:0;font-size:11px;color:#64748b;white-space:nowrap;">From</p>
			{html_input_date name=$from_name value=$row[$from_name]|default:''}
		</div>
		<div style="display:flex;align-items:center;gap:6px;flex:1 1 0;min-width:0;">
			<p style="margin:0;font-size:11px;color:#64748b;white-space:nowrap;">To</p>
			{html_input_date name=$to_name value=$row[$to_name]|default:''}
		</div>
	</div>
	
{else if $type == "datetime"}
	{assign var="from_name" value=$name|cat:"_from"}
	{assign var="to_name" value=$name|cat:"_to"}
	<div class="search_datetime_range" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:nowrap;">
		<div style="display:flex;align-items:center;gap:6px;flex:1 1 0;min-width:0;">
			<p style="margin:0;font-size:11px;color:#64748b;white-space:nowrap;">From</p>
			<input type="text" name="{$from_name}" value="{$row[$from_name]|default:''}" class="world_datetime" data-search-name="{$from_name|escape}" data-search-title="{$title|escape} From" data-search-type="{$type|escape}">
		</div>
		<div style="display:flex;align-items:center;gap:6px;flex:1 1 0;min-width:0;">
			<p style="margin:0;font-size:11px;color:#64748b;white-space:nowrap;">To</p>
			<input type="text" name="{$to_name}" value="{$row[$to_name]|default:''}" class="world_datetime" data-search-name="{$to_name|escape}" data-search-title="{$title|escape} To" data-search-type="{$type|escape}">
		</div>
	</div>
	
{else if $type == "time"}
	{assign var="from_name" value=$name|cat:"_from"}
	{assign var="to_name" value=$name|cat:"_to"}
	<div class="search_time_range" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:nowrap;">
		<div style="display:flex;align-items:center;gap:6px;flex:1 1 0;min-width:0;">
			<p style="margin:0;font-size:11px;color:#64748b;white-space:nowrap;">From</p>
			<input type="text" name="{$from_name}" value="{$row[$from_name]|default:''}" class="timepicker" data-search-name="{$from_name|escape}" data-search-title="{$title|escape} From" data-search-type="{$type|escape}">
		</div>
		<div style="display:flex;align-items:center;gap:6px;flex:1 1 0;min-width:0;">
			<p style="margin:0;font-size:11px;color:#64748b;white-space:nowrap;">To</p>
			<input type="text" name="{$to_name}" value="{$row[$to_name]|default:''}" class="timepicker" data-search-name="{$to_name|escape}" data-search-title="{$title|escape} To" data-search-type="{$type|escape}">
		</div>
	</div>
	
{else if $type == "year_month"}
	
	<input type="text" name="{$name}" value="{html_year_month value=$row.$name}" class="year_month_picker" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">	
			
{else if $type == "radio"}
	<select name="{$name}">
		<option value="" {if $row[$name]|default:'' == ''}selected{/if}>{t key="common.unselected"}</option>
		{foreach $field["options"] as $key=>$option}
			<option value="{$key|escape}" {if $row[$name]|default:null !== null && $row[$name]|cat:'' === $key|cat:''}selected{/if}>{$option}</option>
		{/foreach}
	</select>
	
{else if $type == "color"}
	
	<input type="text" name="{$name}" value="{$row[$name]}" class="colorpicker" data-search-name="{$name|escape}" data-search-title="{$title|escape}" data-search-type="{$type|escape}">
		
{else}
	<p>{t key="common.search_field_not_supported"}</p>

{/if}

<span class="error">{$errors[$name]}</span>

</div>
