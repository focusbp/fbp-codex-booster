<main class="schedule-appointment-page">
	<section class="schedule-appointment-panel">
		<h2 style="margin-top:0;">Appointment Booked</h2>
		<p><strong>{$slot.title|escape}</strong></p>
		<p>{$slot._date_label|escape} {$slot._time_label|escape}</p>
		<div class="schedule-appointment-actions">
			<div class="schedule-appointment-actions-back">
				<a class="schedule-appointment-button" href="{$calendar_url|escape}">Back to Calendar</a>
			</div>
		</div>
	</section>
</main>
