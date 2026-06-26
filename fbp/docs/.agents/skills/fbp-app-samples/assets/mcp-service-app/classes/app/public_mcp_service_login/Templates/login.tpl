<div class="mcp-service-root">
	<section class="mcp-service-panel">
		<h1>MCP Service Login</h1>
		<p class="mcp-service-lead">Authorize an MCP client to use this service with your account.</p>
		{if $message|default:'' != ''}<p class="mcp-service-message">{$message|escape}</p>{/if}
		<form class="public-form" method="post" action="{$login_action_url|escape}">
			<div class="public-field">
				<label for="mcp_service_email">Email</label>
				<input id="mcp_service_email" type="email" name="email" value="{$email|default:''|escape}" autocomplete="username" required>
			</div>
			<div class="public-field">
				<label for="mcp_service_password">Password</label>
				<input id="mcp_service_password" type="password" name="password" autocomplete="current-password" required>
			</div>
			<div class="public-actions">
				<a class="mcp-service-secondary" href="{$register_url|escape}">Create account</a>
				<button class="mcp-service-primary" type="submit">Login for MCP</button>
			</div>
		</form>
	</section>
</div>
