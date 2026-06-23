<main class="service-main">
	<div class="service-shell">
		<section class="service-panel">
			<h1>Plans</h1>
			<p class="service-muted">Choose a plan to enable the service for your account.</p>
			<div class="service-grid">
				{foreach from=$plans item=plan}
					<article class="service-card">
						<h2>{$plan.name|escape}</h2>
						<p>{$plan.description|escape|nl2br}</p>
						<div class="service-price">&yen;{$plan.price|number_format}</div>
						<p class="service-muted">per {$plan.billing_cycle|escape}</p>
						<form method="post" action="{$plan.subscribe_url|escape}">
							<input type="hidden" name="plan_id" value="{$plan.id_enc|escape}">
							<button class="service-button service-button-primary" type="submit">Start</button>
						</form>
					</article>
				{/foreach}
			</div>
		</section>
	</div>
</main>
