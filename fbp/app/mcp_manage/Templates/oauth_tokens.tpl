<table class="moredata" style="width:100%;">
	<thead>
		<tr class="table-head">
			<th style="width:8%;">ID</th>
			<th style="width:16%;">Subject</th>
			<th style="width:18%;">Client</th>
			<th>Scope</th>
			<th style="width:12%;">Expires</th>
			<th style="width:10%;">Status</th>
			<th style="width:8%;"></th>
		</tr>
	</thead>
	<tbody>
		{foreach $tokens as $token}
			<tr>
				<td>{$token.id}</td>
				<td>{$token.subject_display|escape}<br><span style="font-size:11px;color:#6b7280;">{$token.subject_type|escape}: {$token.subject_id|escape}</span></td>
				<td style="font-size:11px;word-break:break-all;">{$token.client_id|escape}</td>
				<td>{$token.scope|escape}</td>
				<td>{if $token.expires_at > 0}{$token.expires_at|date_format:"%Y/%m/%d %H:%M"}{/if}</td>
				<td>
					{if $token.revoked == 1}revoked{elseif !$token.user_status_valid}subject invalid{else}active{/if}
				</td>
				<td>
					{if $token.revoked != 1}
						<button class="ajax-link" data-class="{$class}" data-function="revoke_oauth_token" data-id="{$token.id}" style="float:right;">{t key="mcp_manage.revoke"}</button>
					{/if}
				</td>
			</tr>
		{/foreach}
	</tbody>
</table>
