<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$page_title|escape} - {$app_name|escape}</title>
<style>
	body.service-page-body { margin:0; font-family:Arial, sans-serif; color:#1f2937; background:#f6f7fb; }
	.service-shell { width:min(1040px, calc(100% - 32px)); margin:0 auto; }
	.service-header { background:#ffffff; border-bottom:1px solid #d7dce5; }
	.service-header-inner { min-height:64px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
	.service-brand { font-weight:700; color:#111827; text-decoration:none; }
	.service-nav { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
	.service-nav a { color:#374151; text-decoration:none; font-size:14px; }
	.service-main { padding:32px 0 48px; }
	.service-panel { background:#ffffff; border:1px solid #d7dce5; border-radius:8px; padding:24px; }
	.service-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px; }
	.service-card { background:#ffffff; border:1px solid #d7dce5; border-radius:8px; padding:20px; display:flex; flex-direction:column; gap:12px; }
	.service-price { font-size:28px; font-weight:700; }
	.service-muted { color:#6b7280; }
	.service-form-row { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
	.service-input { box-sizing:border-box; width:100%; min-height:42px; padding:8px 10px; border:1px solid #b8c0cc; border-radius:6px; font-size:16px; }
	.service-actions { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:20px; }
	.service-button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:8px 14px; border:1px solid #9ca3af; border-radius:6px; background:#ffffff; color:#111827; text-decoration:none; cursor:pointer; font-weight:600; }
	.service-button-primary { background:#0f766e; border-color:#0f766e; color:#ffffff; }
	.service-error { background:#fee2e2; color:#991b1b; padding:10px 12px; border-radius:6px; }
	.service-success { background:#dcfce7; color:#166534; padding:10px 12px; border-radius:6px; }
	.service-table { width:100%; border-collapse:collapse; margin-top:16px; }
	.service-table th, .service-table td { border-bottom:1px solid #e5e7eb; padding:10px; text-align:left; }
	@media (max-width: 640px) { .service-header-inner { align-items:flex-start; flex-direction:column; padding:14px 0; } }
</style>
