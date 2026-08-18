<?php

interface ctl_fw {
	
	/**
	 * Retrieves the POST data. Typically, use `$post = $ctl->POST();` to get the data, then access it using `$post["parameter_name"]`.
	 *
	 * 1. Form-based submission method
	 * Use a unique ID in the form and specify it using the `data-form` attribute in the button.
	 * Example:
	 * <form id="form_{$timestamp}"></form>
	 * <button class="ajax-link" data-form="form_{$timestamp}"></button>
	 * 
	 * 2. Button-based method
	 * Send custom data attributes and retrieve them as `$post["name"]` and `$post["address"]`.
	 * <button class="ajax-link" data-name="Name" data-address="Address"></button>
	 * 
	 * @param string|null $key Specify the key to get its value. Usually, get the entire array first with `$post = $ctl->POST();`.
	 * @return mixed The POST data or a specific value based on the provided key.
	 */
	function POST($key = null);
	
	/**
	 * Retrieves URL parameters.
	 *
	 * @param string|null $key The key of the URL parameter to retrieve. If not specified, returns all parameters as an array.
	 * @return mixed The value associated with the key, or all parameters as an array.
	 */
	function GET($key = null);
	
	/**
	 * Increments the value of the specified POST key.
	 *
	 * @param string $key The POST key to increment.
	 * @param int $increment_value The value to increment by.
	 * @return int The incremented value.
	 */
	function increment_post_value($key, $increment_value);
	
	/**
	 * Sets data to be passed to JavaScript. Although this method can set data directly, it is recommended to use appropriate methods for data manipulation.
	 *
	 * @param string $key The key for the data to set.
	 * @param string $val The value to associate with the key.
	 * @return void
	 */
	function append_res_data($key, $val);
	
	/**
	 * A method automatically called by the framework to return JSON in response to JavaScript requests. Usually, there is no need to use this directly.
	 *
	 * @return void
	 */
	function res();
	
	/**
	 * Displays the POST data for debugging purposes.
	 *
	 * @return void
	 */
	function debug_post();
	
	/**
	 * Retrieves debug information.
	 *
	 * @return mixed Debug information.
	 */
	function get_debug_info();
	
	/**
	 * Specifies the directory for data.
	 *
	 * @param string $dir The directory path to set.
	 * @return void
	 */
	function set_data_dir($dir);
	
	/**
	 * Dumps the specified message and object.
	 *
	 * @param string $message The message to display.
	 * @param mixed $obj The object to dump.
	 * @return void
	 */
	function var_dump($message, $obj);
	
	/**
	 * Sets the class for the controller.
	 *
	 * @param string $class The class name.
	 * @return void
	 */
	function set_class($class);
	
	/**
	 * Retrieves session data.
	 *
	 * @param string $key The key of the session data to retrieve.
	 * @return mixed The session data associated with the key.
	 */
	function get_session($key);
	
	/**
	 * Retrieves application settings.
	 *
	 * @return mixed Application settings.
	 */
	function get_setting();
	
	function save_setting($setting);

	function generate_api_credentials();

	function verify_api_request();
	
	/**
	 * Retrieves the application code.
	 *
	 * @return string The application code.
	 */
	function get_appcode();

	/**
	 * Retrieves a writable directory for temporary application files.
	 *
	 * @return string Absolute directory path without a trailing separator.
	 */
	function get_temp_dir();
	
	/**
	 * Sets session data.
	 *
	 * @param string $key The key of the session data.
	 * @param mixed $val The value to store in the session.
	 * @return void
	 */
	function set_session($key, $val);
	
	/**
	 * Retrieves the window code.
	 *
	 * @return string The window code.
	 */
	function get_windowcode();
	
	/**
	 * Sets the window code.
	 *
	 * @param string $windowcode The window code to set.
	 * @return void
	 */
	function set_windowcode($windowcode);
	
	/**
	 * Adds a CSS class to the public area.
	 *
	 * @param string $class The CSS class to add.
	 * @return void
	 */
	function add_css_public($class);
	
	/**
	 * Tests the server connection.
	 *
	 * @return mixed The result of the test.
	 */
	function testserver();
	
	/**
	 * Translates a key using the lightweight i18n catalog.
	 *
	 * @param string $key Translation key.
	 * @param array $params Placeholder values such as ["name" => "Alice"].
	 * @param string|null $lang Optional language override. Use "en" or "local".
	 * @return string
	 */
	function t($key, $params = [], $lang = null);
	
	/**
	 * Logs in to a specified node.
	 *
	 * @param string $room_name The room name.
	 * @param string $group_name The group name.
	 * @param string $name The user's name.
	 * @return void
	 */
	function login_node($room_name, $group_name, $name);
	
	/**
	 * Sends data to a node.
	 *
	 * @param mixed $data The data to send.
	 * @param string $room_name The room name (optional).
	 * @param string $group_name The group name (optional).
	 * @param string $user_id The user ID (optional).
	 * @return void
	 */
	function send_to_node($data, $room_name = "", $group_name = "", $user_id = "");
	
	/**
	 * Sends a PDF to a node.
	 *
	 * @param string $pdf_template The PDF template name.
	 * @param string $pdf_filename The filename of the PDF.
	 * @param string $room_name The room name (optional).
	 * @param string $group_name The group name (optional).
	 * @param string $user_id The user ID (optional).
	 * @return void
	 */
	function send_pdf_to_node($pdf_template, $pdf_filename, $room_name = "", $group_name = "", $user_id = "");
	
	/**
	 * Sets the name of the called function.
	 *
	 * @param string $name The name of the function.
	 * @return void
	 */
	function set_called_function($name);
	
	/**
	 * Retrieves the name of the called function.
	 *
	 * @return string The name of the called function.
	 */
	function get_called_function();
	
	/**
	 * Retrieves the class name.
	 *
	 * @return string The class name.
	 */
	function get_classname();
	
	/**
	 * Returns JSON response with the given array.
	 *
	 * @param array $array The array to convert to JSON.
	 * @return void
	 */
	function res_json($array);
	
	/**
	 * Sends an API request.
	 *
	 * @param string $api_url The API URL.
	 * @param string $class The class name.
	 * @param string $function The function name.
	 * @param array $post_arr The POST data (optional).
	 * @return mixed The API response.
	 */
	function api($api_url, $class, $function, $post_arr = []);
	
	/**
	 * Generates a random number.
	 *
	 * @param int $length The length of the number (default 8).
	 * @return string The random number.
	 */
	function random_number($length = 8);
	
	/**
	 * Generates a random alphabetic string.
	 *
	 * @param int $length The length of the string (default 8).
	 * @return string The random alphabetic string.
	 */
	function random_alphabet($length = 8);
	
	/**
	 * Generates a random password.
	 *
	 * @param int $length The length of the password (default 8).
	 * @return string The random password.
	 */
	function random_password($length = 8);
	
	/**
	 * Converts a string to a timestamp based on the timezone.
	 *
	 * @param string $str The string to convert.
	 * @param string $timezone The timezone for the conversion.
	 * @return int The Unix timestamp.
	 */
	function strtotime($str, $timezone);
	
	/**
	 * Formats a date.
	 *
	 * @param string $format The date format.
	 * @param int $timestamp The Unix timestamp.
	 * @param string $timezone The timezone (default UTC).
	 * @return string The formatted date.
	 */
	function date($format, $timestamp, $timezone = "UTC");
	
	/**
	 * Outputs a log message to the console.
	 *
	 * @param string $log The log message.
	 * @return void
	 */
	function console_log($log);
	
	/**
	 * Adds a value to a constant array.
	 *
	 * @param string $array_name The name of the array.
	 * @param string $key The key to associate with the value.
	 * @param string $value The value to add.
	 * @param string $color The color to associate with the value (default #ccc).
	 * @return void
	 */
	function add_constant_array($array_name, $key, $value, $color = "#ccc");
	
	/**
	 * Checks if a constant array exists.
	 *
	 * @param string $array_name The name of the array.
	 * @return bool Returns true if the array exists, otherwise false.
	 */
	function is_constant_array($array_name);
	
	/**
	 * Retrieves all constant array names.
	 *
	 * @param bool $emptydata Whether to include empty data (default false).
	 * @param bool $include_table_field Whether to include table fields (default true).
	 * @return array The list of constant array names.
	 */
	function get_all_constant_array_names($emptydata = false, $include_table_field = true);
	
	/**
	 * Retrieves a constant array.
	 *
	 * @param string $array_name The name of the array.
	 * @param bool $emptydata Whether to include empty data (default false).
	 * @return array The constant array.
	 */
	function get_constant_array($array_name, $emptydata = false);
	
	/**
	 * Retrieves the color associated with a constant array.
	 *
	 * @param string $array_name The name of the array.
	 * @return string The color associated with the array.
	 */
	function get_constant_array_color($array_name);
	
	/**
	 * Stops the execution of a function.
	 *
	 * @return void
	 */
	function stop_executing_function();
	
	
	/**
	 * Retrieves encrypted POST data.
	 *
	 * @param string $parameter_name The key of the POST parameter.
	 * @return string The encrypted value.
	 */
	function decrypt_post($parameter_name);
	
	/**
	 * Retrieves default values based on a screen ID or table name.
	 *
	 * @param string $table_name_or_screen_id The table name or screen ID.
	 * @return array An array of default values.
	 */
	function get_default_values($table_name_or_screen_id);
	
	/**
	 * Retrieves a list of email templates.
	 *
	 * @param bool $add_empty_data Whether to include empty data (default true).
	 * @return array A list of email templates.
	 */
	function get_email_template_list($add_empty_data = true);
	
	/**
	 * Builds an application URL using standard query syntax by default.
	 *
	 * Pass ["query_format" => "legacy"] as the fourth argument only when the
	 * former "&key=value" query form is explicitly required.
	 */
	function get_APP_URL($class = null, $function = null, $params = null, $options = []);
	
	
	function polling_start($nickname, $status_text, $info_data=[], $timeout_seconds=60, $timeout_handler_function=null,$timeout_handler_class=null);
	
	function polling_wait();
	
	/**
	 * Returns a list of polling information.
	 *
	 * Each element in the returned array is an associative array with the following keys:
	 * - 'nickname' (string): The nickname of the user.
	 * - 'status_text' (string): The status text.
	 * - 'polling_id' (string): The polling ID.
	 *
	 * @return array<int, array{nickname: string, status_text: string, polling_id: string}>
	 */
	function polling_list():array;
	
	function polling_transmit($polling_id,$invoke_function,$params=[],$invoke_class=null):bool;
	
	function polling_get_sender();
	
	function polling_update_status($status_text, $current_required_status=null, $polling_id = null): bool;
	
	function polling_get_status($polling_id=null);
	
	function polling_get_info_data($polling_id=null);
	
	function polling_get_polling_id();
	
	function polling_stop();
	
	
}
