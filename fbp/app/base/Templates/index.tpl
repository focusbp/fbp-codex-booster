<!DOCTYPE html>
<html>
	{include file="{$base_template_dir}/header.tpl"}
	<body>
		{include file="topbar.tpl"}
		<article>

			<div id="menu_area" class="lang_check_area" data-classname="base">

				<div id="menu"></div>

			</div>

			<div class="content">


				<div id="work_area">
					{if $show_empty_main_menu}
						{include file="empty_main_menu.tpl"}
					{/if}

				</div>
					
				<div id="work_area_second">

				</div>
					
			</div>

		</article>

		<div id="appcode" style="display: none;">{$appcode}</div>


		{include file="{$base_template_dir}/footer.tpl"}


	</body>

</html>
