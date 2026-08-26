
<div style="margin-top: 10px;">
    <button class="ajax-link lang" data-class="{$class}" data-function="add" style="margin-top: 0px;">{t key="email_format.add_button"}</button>
    <button class="ajax-link lang" data-class="{$class}" data-function="json_upload" style="margin-top: 0px;">{t key="email_format.json_upload"}</button>
    <button class="download-link lang" data-filename="email_template.json" data-class="{$class}" data-function="json_download" style="margin-top: 0px;">{t key="email_format.json_download"}</button>    
</div>
<table style="margin-top:20px;" class="moredata">
	<thead>
		<tr class="table-head" style="background-color: #FFF;color: black;border-top: none;">
			<th class="lang">{t key="email_format.template_name"}</th>
			<th class="lang">Key</th>
			<th class="lang">{t key="email_format.subject"}</th>

			<th></th>
		</tr>
	</thead>
	<tbody>
		{foreach $items as $item}
			<tr>

				<td>{$item.template_name}</td>
				<td>{$item.key}</td>
				<td>{$item.subject}</td>

				<td>

					<button class="ajax-link listbutton" data-class="{$class}" data-function="delete" data-id="{$item.id}" style="float:right;color:black;margin-right:5px;"><span class="ui-icon ui-icon-trash"></span></button>

					<button class="ajax-link listbutton" data-class="{$class}" data-function="edit" data-id="{$item.id}" style="float:right;color:black;"><span class="ui-icon ui-icon-pencil"></span></button>
				</td>
			</tr>
		{/foreach}
	</tbody>
</table>
