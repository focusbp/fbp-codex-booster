<!doctype html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>MCP OAuth</title>
	<style>
		{literal}
		body{font-family:Arial,sans-serif;background:#f8fafc;color:#111827;margin:0;padding:24px;}
		.panel{max-width:560px;margin:40px auto;background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:24px;}
		h1{font-size:20px;margin:0 0 16px;}
		p{font-size:14px;line-height:1.7;}
		.meta{background:#f3f4f6;border-radius:6px;padding:12px;font-size:13px;line-height:1.7;}
		.actions{display:flex;justify-content:flex-end;margin-top:20px;}
		button{min-height:40px;padding:0 18px;border:0;border-radius:4px;background:#111827;color:#fff;cursor:pointer;}
		{/literal}
	</style>
</head>
<body>
	<div class="panel">
		<h1>{$server.title|escape}</h1>
		<p>{t key="mcp_server.authorize_message"}</p>
		<div class="meta">
			User: {$user.name|default:$user.login_id|escape}<br>
			Client: {$oauth_params.client_id|escape}<br>
			Scope: {$scope|escape}
		</div>
		<form method="post" action="{$ctl->get_APP_URL('mcp_server','authorize_confirm')|escape}">
			<input type="hidden" name="server" value="{$oauth_params.server|default:'default'|escape}">
			<input type="hidden" name="response_type" value="{$oauth_params.response_type|escape}">
			<input type="hidden" name="client_id" value="{$oauth_params.client_id|escape}">
			<input type="hidden" name="redirect_uri" value="{$oauth_params.redirect_uri|escape}">
			<input type="hidden" name="scope" value="{$scope|escape}">
			<input type="hidden" name="state" value="{$oauth_params.state|escape}">
			<input type="hidden" name="code_challenge" value="{$oauth_params.code_challenge|escape}">
			<input type="hidden" name="code_challenge_method" value="{$oauth_params.code_challenge_method|escape}">
			<input type="hidden" name="resource" value="{$oauth_params.resource|escape}">
			<div class="actions">
				<button type="submit">{t key="mcp_server.authorize_button"}</button>
			</div>
		</form>
	</div>
</body>
</html>
