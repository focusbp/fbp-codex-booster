<div id="customers_original_management_list_area">
	<p class="original_screen_toolbar_note">Customers: {$count}</p>
	<table class="original_screen_table">
		<tbody>
			{foreach from=$rows item=row}
				<tr class="active_indicator">
					<td class="row_style" style="width:80px;">
						<span class="row_title">ID</span>
						<span class="row_value row_value_id" style="text-align:right;"><p>{$row.id|escape}</p></span>
					</td>
					<td class="row_style">
						<span class="row_title">Company Name</span>
						<span class="row_value"><p>{$row.company_name|escape}</p></span>
					</td>
					<td class="row_style">
						<span class="row_title">Contact Name</span>
						<span class="row_value"><p>{$row.contact_name|escape}</p></span>
					</td>
					<td class="row_style">
						<span class="row_title">Email</span>
						<span class="row_value"><p>{$row.email|escape}</p></span>
					</td>
					<td class="row_style" style="width:150px;">
						<span class="row_title">Phone</span>
						<span class="row_value"><p>{$row.phone|escape}</p></span>
					</td>
					<td class="row_style" style="width:150px;">
						<span class="row_title">Status</span>
						<span class="row_value"><p>{fields_view_direct db="customers" fields="status" data=$row}</p></span>
					</td>
					<td class="row_style original_screen_action_cell customers_demo_action_cell">
						<button type="button" class="ajax-link listbutton original_screen_action_delete" data-class="customers_original_management" data-function="delete_dialog" data-id="{$row.id}">
							<span class="material-symbols-outlined">delete</span>
						</button>
						<button type="button" class="ajax-link listbutton original_screen_action_edit" data-class="customers_original_management" data-function="edit_dialog" data-id="{$row.id}">
							<span class="material-symbols-outlined">edit_square</span>
						</button>
						<button type="button" class="download-link listbutton customers_demo_pdf_icon" data-class="customers_pdf" data-function="detail_pdf" data-id="{$row.id}" data-filename="customer-{$row.id|escape}.pdf" data-open_new_tab="true">
							<span class="material-symbols-outlined">picture_as_pdf</span>
						</button>
						<button type="button" class="ajax-link listbutton customers_demo_detail_icon" data-class="customers_original_management" data-function="detail_dialog" data-id="{$row.id}">
							<span class="material-symbols-outlined">visibility</span>
						</button>
					</td>
				</tr>
			{/foreach}
			{if count($rows) === 0}
				<tr>
					<td colspan="7" class="original_screen_empty_row">No customers found.</td>
				</tr>
			{/if}
		</tbody>
	</table>
</div>
