<style>
{literal}
	.mcp-service-page {
		min-height: 100vh;
		background: #f5f7fb;
		color: #1f2937;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}
	.mcp-service-header {
		height: 56px;
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 24px;
		background: #fff;
		border-bottom: 1px solid #d8dee8;
	}
	.mcp-service-panel {
		width: min(560px, calc(100% - 32px));
		margin: 48px auto;
		background: #fff;
		border: 1px solid #d8dee8;
		border-radius: 8px;
		padding: 24px;
		box-shadow: 0 12px 30px rgba(31, 41, 55, 0.08);
	}
	.mcp-service-panel h1 {
		margin: 0 0 16px;
		font-size: 22px;
		letter-spacing: 0;
	}
	.mcp-service-panel label {
		display: block;
		margin-bottom: 12px;
		font-size: 14px;
	}
	.mcp-service-panel input {
		display: block;
		width: 100%;
		box-sizing: border-box;
		margin-top: 6px;
	}
	.mcp-service-panel button {
		min-height: 40px;
		padding: 0 16px;
		border-radius: 6px;
		border: 1px solid #2563eb;
		background: #2563eb;
		color: #fff;
		cursor: pointer;
	}
	.mcp-service-message {
		padding: 10px 12px;
		border-radius: 6px;
		background: #fff7ed;
		color: #9a3412;
	}
	.mcp-service-url {
		padding: 12px;
		border-radius: 6px;
		background: #eef2ff;
		overflow-wrap: anywhere;
		font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
		font-size: 13px;
	}
{/literal}
</style>
