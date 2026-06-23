<body class="service-page-body">
<header class="service-header">
	<div class="service-shell service-header-inner">
		<a class="service-brand" href="{$plans_url|escape}">{$app_name|escape}</a>
		<nav class="service-nav">
			<a href="{$plans_url|escape}">Plans</a>
			{if $member|default:[]}
				<a href="{$account_url|escape}">Account</a>
				<a href="{$logout_url|escape}">Logout</a>
			{else}
				<a href="{$login_url|escape}">Login</a>
				<a href="{$register_url|escape}">Create Account</a>
			{/if}
		</nav>
	</div>
</header>
