<main class="service-main">
	<div class="service-shell">
		<section class="service-panel">
			<h1>Account</h1>
			<p><strong>{$member.name|escape}</strong> / {$member.email|escape}</p>
			{if $subscription|default:[]}
				<p class="service-success">Subscription active until {$subscription.current_period_end|date_format:"%Y-%m-%d"}.</p>
			{else}
				<p class="service-error">No active subscription.</p>
			{/if}
			<div class="service-actions">
				<a class="service-button service-button-primary" href="{$plans_url|escape}">Change Plan</a>
			</div>
			<h2>Payments</h2>
			<table class="service-table">
				<thead><tr><th>Date</th><th>Status</th><th>Amount</th></tr></thead>
				<tbody>
					{foreach from=$payments item=payment}
						<tr><td>{$payment.paid_at|date_format:"%Y-%m-%d"}</td><td>{$payment.payment_status|escape}</td><td>&yen;{$payment.amount|number_format}</td></tr>
					{foreachelse}
						<tr><td colspan="3">No payments.</td></tr>
					{/foreach}
				</tbody>
			</table>
		</section>
	</div>
</main>
