<!DOCTYPE html>
<html>
<head>
{include file="{$base_template_dir}/{$publicsite_header_template|default:'publicsite_header.tpl'}"}
{$html_header nofilter}
</head>

<body class="publicsite-body">
    <article class="class_style_{$class} lang_check_area publicsite-shell" data-classname="{$class}">
		
		
		{$contents_header nofilter}

		<section class="public_main_section">
			<div class="publicsite-main-inner">
				<div id="multi_dialog_{$class}" class="publicsite-content">
{$contents nofilter}
				</div>
			</div>
		</section>

		{$contents_footer nofilter}
			
	</article>
	
	{include file="{$base_template_dir}/publicsite_footer.tpl"}
</body>
</html>
