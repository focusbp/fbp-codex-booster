
<div style="float:right;margin-bottom: 8px;">
	<button class="ajax-link lang" data-class="{$class}" data-function="add_fields" data-id="{$data.id}">{t key="db.add_field"}</button>
	<button class="ajax-link lang" data-class="{$class}" data-function="text_fields" data-id="{$data.id}">{t key="db.text"}</button>
	<button class="download-link lang" data-class="{$class}" data-function="pdf_fields" data-id="{$data.id}" data-filename="{$data.tb_name}.pdf" data-open_new_tab="true">PDF</button>
</div>

<div style="clear:both;"></div>
<table>
	<thead>
		<tr class="table-head">
			<th></th>
			<th class="lang">{t key="db.field_name"}</th>
			<th class="lang">{t key="db.field_title"}</th>
			<th class="lang">{t key="db.type"}</th>
			<th class="lang">{t key="db.display_format"}</th>
			<th>Index</th>
			<th class="lang">{t key="db.length"}</th>
			<th class="lang">{t key="db.options"}</th>
			<th></th>
		</tr>
	</thead>

	<tbody class="sort_parameters">
		{foreach $parameters as $item}
			{assign var=field_display_format value=$item.display_format|default:0}
			<tr id="{$item.id}">

				<td><div class="col col_handle"><span class="material-symbols-outlined handle">swap_vert</span></div></td>
				<td>{$item.parameter_name}</td>
				<td
				{if $item.title_color|default:'' != ''}
				  style="color: {$item.title_color|escape};"
				{/if}
			  >{$item.parameter_title}</td>
				<td>{$item.type}</td>
				<td>{$display_format_opt[$field_display_format]}</td>
				<td>{if $item.index_flag|default:0}IDX{/if}</td>
				<td>{$item.length}</td>
				<td>{$item.constant_array_name}
					<span class="option_list_in_row">
						{foreach $item.option_list as $v=>$t}
							<p>{$v} : {$t}</p>
						{/foreach}
					</span>
				</td>

				
				
				<td>
				    {if $item.parameter_name != 'parent_id'}
					<div class="ajax-link" data-class="{$class}" data-function="add_to_screen" data-id="{$item.id}" data-tb_name="{$data.tb_name}" data-db_id="{$data.id}" style="float:right;color:black;"><span class="material-symbols-outlined" style="color:#4BA3FF;margin-top:4px;cursor: pointer;">arrow_right_alt</span></div>

					<button class="ajax-link listbutton" data-class="{$class}" data-function="delete_fiedls" data-id="{$item.id}" style="float:right;color:black;margin-right:5px;"><span class="ui-icon ui-icon-trash"></span></button>

					<button class="ajax-link listbutton" data-class="{$class}" data-function="edit_fields" data-id="{$item.id}" style="float:right;color:black;"><span class="ui-icon ui-icon-pencil"></span></button>
					
				    {/if}
				</td>
			</tr>
		{/foreach}
	</tbody>
</table>
