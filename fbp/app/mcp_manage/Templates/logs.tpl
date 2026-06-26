<table class="moredata" style="width:100%;">
	<thead>
		<tr class="table-head">
			<th style="width:8%;">ID</th>
			<th style="width:15%;">Tool</th>
			<th style="width:10%;">Subject</th>
			<th style="width:12%;">Status</th>
			<th>Request</th>
			<th style="width:18%;">Error</th>
		</tr>
	</thead>
	<tbody>
		{foreach $logs as $log}
			<tr>
				<td>{$log.id}</td>
				<td>{$log.tool_name|escape}<br><span style="font-size:11px;color:#6b7280;">{$log.method|escape}</span></td>
				<td>{if $log.subject_type}{$log.subject_type|escape}: {$log.subject_id|escape}{else}{$log.user_id|escape}{/if}<br><span style="font-size:11px;color:#6b7280;">{$log.subject_label|escape}</span></td>
				<td>{$log.result_status|escape}</td>
				<td style="font-size:11px;line-height:1.4;white-space:pre-wrap;">{$log.request_json|escape}</td>
				<td style="font-size:11px;color:#b42318;line-height:1.4;">{$log.error_message|escape}</td>
			</tr>
		{/foreach}
	</tbody>
</table>
