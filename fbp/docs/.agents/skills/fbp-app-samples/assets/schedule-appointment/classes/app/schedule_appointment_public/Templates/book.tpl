<main class="schedule-appointment-page">
	<section class="schedule-appointment-panel">
		<h2 style="margin-top:0;">Book Appointment</h2>
		<p><strong>{$slot.title|escape}</strong></p>
		<p>{$slot._date_label|escape} {$slot._time_label|escape}</p>

		<form method="post" action="{$save_url|escape}">
			<input type="hidden" name="user" value="{$user_key|escape}">
			<input type="hidden" name="slot" value="{$slot_key|escape}">

			<div class="schedule-appointment-field">
				<label for="customer_name">Name</label>
				<input id="customer_name" type="text" name="customer_name" value="{$row.customer_name|escape}">
				{if $errors.customer_name != ""}<p class="schedule-appointment-error">{$errors.customer_name|escape}</p>{/if}
			</div>
			<div class="schedule-appointment-field">
				<label for="customer_email">Email</label>
				<input id="customer_email" type="email" name="customer_email" value="{$row.customer_email|escape}">
				{if $errors.customer_email != ""}<p class="schedule-appointment-error">{$errors.customer_email|escape}</p>{/if}
			</div>
			<div class="schedule-appointment-field">
				<label for="customer_phone">Phone</label>
				<input id="customer_phone" type="text" name="customer_phone" value="{$row.customer_phone|escape}">
			</div>
			<div class="schedule-appointment-field">
				<label for="customer_message">Message</label>
				<textarea id="customer_message" name="customer_message" rows="4">{$row.customer_message|escape}</textarea>
			</div>

			<div class="schedule-appointment-actions">
				<button type="submit" class="schedule-appointment-button schedule-appointment-primary">Book</button>
				<a class="schedule-appointment-button" href="{$calendar_url|escape}">Back</a>
			</div>
		</form>
	</section>
</main>
