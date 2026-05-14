<style>
	.schedule-appointment-page {
		color: #1f2937;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		margin: 0 auto;
		max-width: 1080px;
		padding: 24px 16px 40px;
	}
	.schedule-appointment-header {
		border-bottom: 1px solid #d7e0ea;
		margin-bottom: 18px;
		padding: 18px 16px;
	}
	.schedule-appointment-header h1 {
		font-size: 28px;
		line-height: 1.25;
		margin: 0;
	}
	.schedule-appointment-provider {
		color: #475569;
		font-size: 15px;
		font-weight: 700;
		margin-top: 8px;
	}
	.schedule-appointment-nav {
		align-items: center;
		display: flex;
		gap: 16px;
		margin: 20px 0 14px;
	}
	.schedule-appointment-nav a,
	.schedule-appointment-button {
		background: #f8fafc;
		border: 1px solid #cbd5e1;
		border-radius: 8px;
		color: #1e293b;
		display: inline-flex;
		font-weight: 700;
		justify-content: center;
		min-width: 68px;
		padding: 10px 14px;
		text-decoration: none;
	}
	.schedule-appointment-period {
		color: #334155;
		font-weight: 700;
	}
	.schedule-appointment-calendar {
		border: 1px solid #d9e4ef;
		border-radius: 8px;
		overflow-x: auto;
	}
	.schedule-appointment-calendar table {
		border-collapse: collapse;
		min-width: 900px;
		width: 100%;
	}
	.schedule-appointment-calendar th,
	.schedule-appointment-calendar td {
		border: 1px solid #d9e4ef;
		padding: 0;
		text-align: center;
	}
	.schedule-appointment-calendar th {
		background: #eef5fc;
		color: #0f172a;
		font-weight: 800;
		padding: 12px 8px;
	}
	.schedule-appointment-time {
		background: #f8fafc;
		color: #334155;
		font-size: 13px;
		font-weight: 700;
		width: 64px;
	}
	.schedule-appointment-cell {
		background: #f8fafc;
		height: 52px;
		min-width: 150px;
		padding: 5px;
	}
	.schedule-appointment-empty {
		color: #94a3b8;
		font-weight: 700;
	}
	.schedule-appointment-select {
		background: #1f669b;
		border: 0;
		border-radius: 8px;
		color: #fff;
		display: block;
		font-weight: 800;
		padding: 13px 10px;
		text-decoration: none;
	}
	.schedule-appointment-panel {
		border: 1px solid #d7e0ea;
		border-radius: 8px;
		margin: 0 auto;
		max-width: 720px;
		padding: 20px;
	}
	.schedule-appointment-field {
		margin-bottom: 14px;
	}
	.schedule-appointment-field label {
		display: block;
		font-weight: 800;
		margin-bottom: 6px;
	}
	.schedule-appointment-field input,
	.schedule-appointment-field textarea {
		border: 1px solid #cbd5e1;
		border-radius: 8px;
		box-sizing: border-box;
		font: inherit;
		padding: 10px;
		width: 100%;
	}
	.schedule-appointment-error {
		color: #dc2626;
		font-size: 13px;
		font-weight: 700;
		margin: 4px 0 0;
	}
	.schedule-appointment-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		margin-top: 18px;
	}
	.schedule-appointment-primary {
		background: #1f669b;
		border-color: #1f669b;
		color: #fff;
	}
	@media (max-width: 720px) {
		.schedule-appointment-header h1 {
			font-size: 23px;
		}
		.schedule-appointment-nav {
			align-items: flex-start;
			flex-direction: column;
			gap: 10px;
		}
	}
</style>
