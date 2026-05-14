<main class="schedule-appointment-page">
	<section class="schedule-appointment-panel">
		<h2 style="margin-top:0;">Appointment Booked</h2>
		<p><strong>{$slot.title|escape}</strong></p>
		<p>{$slot._date_label|escape} {$slot._time_label|escape}</p>
		<div class="schedule-appointment-actions">
			<a class="schedule-appointment-button schedule-appointment-primary" href="{$calendar_url|escape}">Back to Calendar</a>
		</div>
	</section>
</main>
