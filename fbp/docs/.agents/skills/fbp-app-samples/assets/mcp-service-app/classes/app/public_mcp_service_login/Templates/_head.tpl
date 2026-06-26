<style>
{literal}
	.mcp-service-root {
		min-height: 100vh;
		display: grid;
		place-items: center;
		padding: 24px;
		box-sizing: border-box;
		background: #eef2f7;
		color: #1f2937;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}
	.mcp-service-panel {
		width: min(460px, 100%);
		background: #fff;
		border: 1px solid #d8dee8;
		border-radius: 8px;
		padding: 24px;
		box-shadow: 0 16px 38px rgba(31, 41, 55, 0.12);
	}
	.mcp-service-panel h1 {
		margin: 0 0 8px;
		font-size: 22px;
		line-height: 1.3;
		letter-spacing: 0;
	}
	.mcp-service-lead {
		margin: 0 0 18px;
		color: #60717a;
		line-height: 1.7;
	}
	.mcp-service-message {
		margin: 0 0 14px;
		padding: 10px 12px;
		border-radius: 6px;
		background: #fff7ed;
		color: #9a3412;
	}
	.mcp-service-primary,
	.mcp-service-secondary {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 40px;
		box-sizing: border-box;
		padding: 0 16px;
		border-radius: 6px;
		border: 1px solid #2563eb;
		background: #2563eb;
		color: #fff;
		text-decoration: none;
		font-size: 14px;
		line-height: 1.2;
		cursor: pointer;
	}
	.mcp-service-secondary {
		background: #fff;
		color: #2563eb;
	}
	.public-actions {
		display: flex;
		justify-content: space-between;
		gap: 12px;
		margin-top: 18px;
	}
{/literal}
</style>
