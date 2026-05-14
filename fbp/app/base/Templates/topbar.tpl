<header class="lang_check_area" data-classname="base">

    {if $MYSESSION.testserver}
        <div class="testserver">
            <div class="testserver_title">{$base_i18n.dev_mode}</div>
			<button class="ajax-link release_button" data-class="release" data-function="download_zip" data-filename="release{$timestamp|date_format:"%Y%m%d"}.zip">{$base_i18n.download_release_file}</button>
        </div>
    {/if}

    <div class="logoarea">

        <div class="topbar_left_area">
			<div style="display: block;float:left;">
				<button id="left-sidebar-show-btn" class="ajax-link" data-class="base" data-function="show_left_sidemenu"><img src="app.php?class=base&function=img&file=menu-hamburger.png" /></button>
			</div>
			<div style="display: block;float: left;">
				<div style="height:47px;display: inline-block;margin-left:10px;margin-top: 7px;">
					<p style="font-size:22px;line-height: 10px;	color:#FFF;">{if $setting.system_name == null}{$base_i18n.app_name}{else}{$setting.system_name}{/if}</p>
					<p style="	color:#FFF;font-size:12px;line-height:28px;"> {if $setting.system_tag_line == null}{$base_i18n.tagline}{else}{$setting.system_tag_line}{/if}</p>
				</div>
			</div>
        </div>



	        <div class="topbar_infomation_area">
				
				<a class="logout-icon" href="app.php?class=login&function=logout"><span class="material-symbols-outlined">logout</span></a>
				<button class="ajax-link logout-icon" data-class="password_reset" data-function="page"><span class="material-symbols-outlined">key</span></button>
				<span class="topbar-login-user-name">{if $MYSESSION.name != ""}{$MYSESSION.name|escape}{else}{$MYSESSION.login_id|escape}{/if}</span>
	        </div>

        <div id="download_view">
            <div id="download_bar">
                <div id="download_message"></div>
                <div id="download_progress"></div>
            </div>
        </div>

    </div>


</header>
