<div class="event_registration_participants_side">
	<h6>Participants</h6>
	<p class="event_registration_participants_context">{$session_label|escape}</p>
	<div class="event_registration_participants_toolbar">
		<button type="button" class="ajax-link button_link" invoke-function="participant_add_dialog" data-session_id="{$session.id|escape}">Add Participant</button>
	</div>
	<div id="event_sessions_original_management_participants_side_area">
		{include file="participants_side_area.tpl"}
	</div>
</div>
