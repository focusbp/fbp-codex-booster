<?php

interface ctl_ui {

	/**
	 * Embeds data into a Smarty template. For example, calling $ctl->assign("name", "Masaru Nakama")
	 * will replace {$name} in the template with "Masaru Nakama".
	 * 
	 * @param string $key The variable name to be used within the template.
	 * @param mixed $val The value to assign to the template variable. Arrays or other data types can also be passed.
	 * @return void This function does not return a result.
	 */
	function assign($key, $val);

	/**
	 * Embeds data into a Smarty template and returns the resulting HTML as a string.
	 * 
	 * @param string $template The name of the template file.
	 * @return string The resulting HTML after data is embedded into the template.
	 */
	function fetch($template);

	/**
	 * Processes a string with Smarty template variables and returns the result.
	 * 
	 * @param string $str The string containing Smarty template variables to be processed.
	 * @return string The processed string with template variables replaced.
	 */
	function fetch_string($str);

	/**
	 * Assigns field settings from a specified screen to a given group, allowing the dynamic embedding of fields in a template.
	 * 
	 * @param string $group_name The name of the group to which the fields will be assigned.
	 * @param int $screen_id The ID of the screen from which to assign fields.
	 * @param bool $option_emptydata Whether to include an empty data option. Default is true.
	 * @param bool $add_parent_dropdown Whether to add a parent dropdown for the fields. Default is false.
	 * @return void This function does not return a result.
	 */
	function assign_fields_from_screen($group_name, $screen_id, $option_emptydata = true, $add_parent_dropdown = false);

	/**
	 * Assigns field settings to a group, based on table name and screen or field name array, for dynamic embedding into a template.
	 * 
	 * @param string $group_name The name of the group to which the field settings will be assigned.
	 * @param string $table_name The name of the table containing the fields.
	 * @param mixed $screen_or_fieldnamearray Either the screen name or an array of field names to assign.
	 * @param bool $add_emptydata_to_options Whether to include empty data options in dropdown fields. Default is true.
	 * @param bool $add_parent_dropdown Whether to include a parent dropdown field. Default is false.
	 * @return void This function does not return a result.
	 */
	function assign_field_settings($group_name, $table_name, $screen_or_fieldnamearray, $add_emptydata_to_options = true, $add_parent_dropdown = false,$use_thumbnail=false);

	/**
	 * Reloads the current page in the user's browser.
	 *
	 * @return void This function forces a browser reload and does not return a result.
	 */
	function res_reload();

	/**
	 * Redirects the browser to a specified URL.
	 * 
	 * @param string $url The URL to redirect to. Typically, it is used with relative paths such as `app.php?class=xxxx&function=xxxx`, specifying the class and function. It is also possible to set an external URL.
	 */
	function res_redirect($url);

	/**
	 * Replaces the entire browser page content with the specified Smarty template.
	 * 
	 * @param string $template The name of the Smarty template located in the Templates folder. This template must generate the entire page, starting from the <html> tag.
	 */
	function display($template);

	/**
	 * Renders a public page by injecting content/header templates into
	 * fbp/Templates/publicsite_index.tpl.
	 *
	 * @param string $contents_template The template that generates body contents.
	 * @param string|null $header_template Optional template for extra header tags.
	 * @param string|null $contents_header_template Optional template rendered before body contents.
	 * @param string|null $contents_footer_template Optional template rendered after body contents.
	 * @return void
	 */
	function show_public_pages($contents_template, $header_template = null, $contents_header_template = null, $contents_footer_template = null);

	/**
	 * Backward-compatible alias for typo usage.
	 *
	 * @param string $contents_template
	 * @param string|null $header_template
	 * @param string|null $contents_header_template
	 * @param string|null $contents_footer_template
	 * @return void
	 */
	function show_pubic_pages($contents_template, $header_template = null, $contents_header_template = null, $contents_footer_template = null);

	/**
	 * Reloads the content of a specific HTML element.
	 * 
	 * @param string $id_or_class The ID or class name of the HTML element to reload.
	 * @param string $val_or_template_name The new content to display or the name of the template to be rendered.
	 * @return void This function does not return a result.
	 */
	function reload_area($id_or_class, $val_or_template_name);

	/**
	 * Displays a multi-dialog window using a specified template.
	 * 
	 * @param string $dialog_name The name of the dialog to display.
	 * @param string $template The template name to be used for the dialog.
	 * @param string $title The title of the dialog window.
	 * @param int $width The width of the dialog in pixels. Default is 600.
	 * @param string|null $fixed_bar_template The template for a fixed bar within the dialog. Can be null.
	 * @param array $options Additional options for configuring the dialog.
	 * @return void This function does not return a result.
	 */
	function show_multi_dialog($dialog_name, $template, $title = "", $width = 600, $fixed_bar_template = null, $options = array());


	/**
	 * Appends content to a specific area.
	 * 
	 * @param string $key The area identifier where content will be appended.
	 * @param mixed $val The content to append.
	 * @return void This function does not return a result.
	 */
	function append_area($key, $val_or_template_name);

	/**
	 * Displays a notification using a specified template.
	 * 
	 * @param string $template The template used for the notification.
	 * @param int $width The width of the notification in pixels. Default is 600.
	 * @param int $time The time in seconds before the notification disappears. Default is 5.
	 * @return void This function does not return a result.
	 */
	function show_notification($template, $width = 600, $time = 5);

	/**
	 * Converts Markdown text to HTML.
	 * 
	 * @param string $text The Markdown-formatted text.
	 * @return string The converted HTML string.
	 */
	function markdown_to_html($text);

	/**
	 * Displays a badge with a specific value.
	 * 
	 * @param string $id The ID of the badge element.
	 * @param string|int $val The value to display inside the badge.
	 * @return void This function does not return a result.
	 */
	function badge($id, $val);
	
	/**
	 * Processes an asynchronous (AJAX) request and calls the specified method from a class.
	 * 
	 * @param string $function The method name to call.
	 * @param array|null $post An optional array containing additional POST data.
	 * @param string $class The name of the class to invoke.
	 * @return void This function does not return a result.
	 */
	function invoke($function,$post=null,$class=null);

	/**
	 * Initializes and displays a Google Map with the specified settings.
	 * 
	 * @param string $tag_id The HTML tag ID where the map will be displayed. Default is "google_map".
	 * @param float $lat The latitude coordinate for the map. Default is 35.6947818.
	 * @param float $lng The longitude coordinate for the map. Default is 139.7763998.
	 * @param int $zoom The zoom level for the map. Default is 0.
	 * @return void This function does not return a result.
	 */
	function map($tag_id = "google_map", $lat = 35.6947818, $lng = 139.7763998, $zoom = 0);

	/**
	 * Adds a marker to the map at the specified location with an associated HTML content.
	 * 
	 * @param string $location The coordinates of the marker in "(latitude,longitude)" format.
	 * @param string $html The HTML content to display when the marker is clicked.
	 * @return void This function does not return a result.
	 */
	function map_add_marker($location, $html);

	/**
	 * Adds a tab to a dialog window.
	 * 
	 * @param string $dialog_name The name of the dialog where the tab will be added.
	 * @param string $tabname The name of the tab.
	 * @param string $title The title displayed on the tab.
	 * @param bool $selected Whether the tab should be initially selected (true or false).
	 * @param array $post_arr An array of data to be sent with the tab.
	 * @return void This function does not return a result.
	 */
	function add_tab($dialog_name, $tabname, $title, $selected, $post_arr);

	/**
	 * Closes all open dialogs except the one specified.
	 *
	 * @param string|null $exception The name of the dialog that should remain open, if any.
	 * @return void This function does not return a result.
	 */
	function close_all_dialog($exception=null);

	/**
	 * Displays a notification with custom text and style.
	 *
	 * @param string $txt The text content of the notification.
	 * @param int $time The duration in seconds for which the notification is displayed. Default is 2.
	 * @param string $background The background color of the notification. Default is "#4B70FF".
	 * @param string $color The text color of the notification. Default is "#FFF".
	 * @param int $fontsize The font size of the notification text. Default is 24.
	 * @param int $width The width of the notification in pixels. Default is 600.
	 * @return void This function does not return a result.
	 */
	function show_notification_text($txt, $time = 2, $background = "#4B70FF", $color = "#FFF", $fontsize = 24, $width = 600);

	/**
	 * Clears all error messages.
	 *
	 * @return void This function does not return a result.
	 */
	function clear_error_message();

	/**
	 * Sets an error message for a specific field.
	 *
	 * @param string $field The name of the field for which the error message applies.
	 * @param string $message The error message to display.
	 * @return void This function does not return a result.
	 */
	function res_error_message($field, $message);

	/**
	 * Returns the current number of error messages.
	 *
	 * @return int The number of error messages.
	 */
	function count_res_error_message();

	/**
	 * Sets the default width for the second work area.
	 *
	 * @param int|null $width The default width in pixels. If null, the framework default is used.
	 * @return void This function does not return a result.
	 */
	function set_second_work_area_default_width($width = null);

	/**
	 * Displays content in the second work area with a specified width.
	 *
	 * @param string $template The template to use for displaying content.
	 * @param int|null $width The width of the second work area in pixels. If null, the current default width or 300 is used.
	 * @return void This function does not return a result.
	 */
	function show_second_work_area($template, $width = null);
	
	
	/**
	 * Displays content in the main work area with a specified template and title.
	 *
	 * @param string $template The template to use for displaying content.
	 * @param string $title The title to display in the main work area.
	 * @return void This function does not return a result.
	 */
	function show_main_area($template, $title);

	/**
	 * Appends one dashboard widget to the dashboard queue.
	 *
	 * @param string $template The widget template to render.
	 * @param int|null $column_width Optional width (1-3). If null, current dashboard context width is used.
	 * @return void
	 */
	function show_dashboard_widget($template, $column_width = null);

	/**
	 * Closes the second work area.
	 *
	 * @return void This function does not return a result.
	 */
	function close_second_work_area();

	/**
	 * Displays a side menu with the specified template and settings.
	 *
	 * @param string $template The template to use for the side menu content.
	 * @param int $width The width of the side menu in pixels. Default is 300.
	 * @param int $time The animation duration in milliseconds. Default is 200.
	 * @param string $from The direction from which the side menu will appear. Default is "left".
	 * @return void This function does not return a result.
	 */
	function show_sidemenu($template, $width = 300, $time = 200, $from = 'left');

	/**
	 * Closes the side menu.
	 *
	 * @return void This function does not return a result.
	 */
	function close_sidemenu();
	
	function reload_work_area();
	
	function reload_menu();
	
	//function close_dialog($dialog_id=null);
	
	function show_popup($template, $width = 300, $height = 200);
	
	function reload_side_panel();
}
