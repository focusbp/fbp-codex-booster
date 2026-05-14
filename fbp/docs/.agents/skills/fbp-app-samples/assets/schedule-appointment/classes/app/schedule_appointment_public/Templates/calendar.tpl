<main class="schedule-appointment-page">
	<div class="schedule-appointment-nav">
		<a href="{$previous_url|escape}">Previous</a>
		<div class="schedule-appointment-period">{$week_label|escape}</div>
		<a href="{$next_url|escape}">Next</a>
	</div>

	<div class="schedule-appointment-calendar">
		<table>
			<thead>
				<tr>
					<th>Time</th>
					{foreach from=$calendar_days item=day}
						<th>{$day.date_label|escape}<br>{$day.day_label|escape}</th>
					{/foreach}
				</tr>
			</thead>
			<tbody>
				{foreach from=$calendar_days[0].times item=time_row name=time_loop}
					<tr>
						<td class="schedule-appointment-time">{$time_row.label|escape}</td>
						{foreach from=$calendar_days item=day}
							{assign var=day_time value=$day.times[$smarty.foreach.time_loop.index]}
							<td>
								<div class="schedule-appointment-cell">
									{if is_array($day_time.slot) && $day_time.slot.id != ""}
										<a class="schedule-appointment-select" href="{$day_time.slot.book_url|escape}">Select</a>
									{else}
										<span class="schedule-appointment-empty">-</span>
									{/if}
								</div>
							</td>
						{/foreach}
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>
</main>
