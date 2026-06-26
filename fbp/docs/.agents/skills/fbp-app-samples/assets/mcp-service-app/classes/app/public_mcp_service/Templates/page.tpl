<div class="mcp-service-page">
	<header class="mcp-service-header">
		<strong>MCP Service Sample</strong>
		{if $mode|default:'' == 'portal'}
			<a href="{$logout_url|escape}">Logout</a>
		{/if}
	</header>

	{if $mode|default:'' == 'register'}
		<main class="mcp-service-panel">
			<h1>Create account</h1>
			{if $message|default:'' != ''}<p class="mcp-service-message">{$message|escape}</p>{/if}
			<form method="post" action="{$register_action_url|escape}">
				{if $from_mcp|default:0}<input type="hidden" name="from_mcp" value="1">{/if}
				<label>Name<input type="text" name="display_name" value="{$form.display_name|default:''|escape}" required></label>
				<label>Email<input type="email" name="email" value="{$form.email|default:''|escape}" autocomplete="username" required></label>
				<label>Password<input type="password" name="password" autocomplete="new-password" required></label>
				<label>Confirm password<input type="password" name="password_confirm" autocomplete="new-password" required></label>
				<button type="submit">Create account</button>
			</form>
		</main>
	{elseif $mode|default:'' == 'portal'}
		<main class="mcp-service-panel">
			<h1>MCP connection</h1>
			<p>Use this endpoint URL when connecting an MCP client.</p>
			<div class="mcp-service-url">{$mcp_endpoint_url|escape}</div>
		</main>
	{/if}
</div>
