<?php

interface ctl_db {

	/**
	 * Retrieves an instance of the database. The same instance is returned for multiple requests to the same table.
	 *
	 * @param string $table_name The name of the table.
	 * @param string|null $class The class folder name. If not specified, the database within the executing class is called.
	 * @param string|null $separated_by Used to separate data by login ID or other criteria.
	 * @return FFM Returns an instance of the FFM interface, allowing data manipulation (retrieval, addition, editing, etc.).
	 */
		function db(string $table_name, ?string $class = null, ?string $separated_by = null): FFM;

	/**
	 * Sets the default FFM open mode for databases opened later in this request.
	 * Returns false without changing the mode when a database is already open.
	 */
	function set_db_read_only(bool $read_only): bool;


	/**
	 * Validates for duplicate values in a specific field or set of fields.
	 *
	 * @param string $table_name The name of the table.
	 * @param string $ffm_class The FFM class name.
	 * @param string|array $field_names The field name or an array of field names to check for duplicates.
	 * @param mixed $target_values The values to check for duplication.
	 * @param int $exclude_id The ID of the record to exclude from the check.
	 * @return bool Returns true if duplicates exist, otherwise false.
	 */
	function validate_duplicate($table_name, $field_names, $target_values, $exclude_id = 0, $class="common");
	
	function validate($table_name,$screen_or_fieldnamearray,$post,$validate_upload_field=true): bool;

	/**
	 * Retrieves a list of fields based on a screen ID.
	 *
	 * @param string $screen_id The ID of the screen from which to retrieve the field list.
	 * @return array An array containing the field list.
	 */
	function get_field_list_from_screen($screen_id);

	/**
	 * Retrieves a list of fields for a specific table and screen name.
	 *
	 * @param string $table_name The name of the table.
	 * @param string $screen_name The name of the screen.
	 * @return array An array containing the field list.
	 */
	function get_field_list($table_name, $screen_or_fieldnamearray=null);

	/**
	 * Retrieves the table name based on the screen ID.
	 *
	 * @param string $screen_id The ID of the screen.
	 * @return string The name of the table.
	 */
	function get_table_name($screen_id);

	/**
	 * Retrieves the database settings based on the table name.
	 *
	 * @param string $table_name The name of the table.
	 * @return array An array containing the database settings.
	 */
	function get_db_setting($table_name);

	/**
	 * Retrieves the field settings for a given table and field name.
	 *
	 * @param string $table_name The name of the table.
	 * @param string $field_name The name of the field.
	 * @return array An array containing the field settings.
	 */
	function get_field_setting($table_name, $field_name);
	
	public function duplicate_rows($table_name,$id):int;
	
	function encrypt_file_fields($row,$table_name);
	
	function decrypt_file_fields($row,$table_name);
	
	public function exclude_disallow_fields($row, $table_name, $op, $throw_exception = false): array;
	
	public function exclude_field_list($field_list, $table_name,$op) : array;
}
