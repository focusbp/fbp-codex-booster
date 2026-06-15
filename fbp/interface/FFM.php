<?php

/**
 * Interface for Fixed File Management.
 * Provides methods for handling data in a file-based structure,
 * including operations like insertion, deletion, updating, and data retrieval.
 * 
 * @author nakama
 */
interface FFM {

	/**
	 * Clears all data from the file.
	 * 
	 * @return void
	 */
	function allclear();

	/**
	 * Inserts new data into the file. The ID of the inserted data will be stored in the dataset and returned.
	 * 
	 * @param array $dataset The data to insert. The ID will be set in $dataset["id"] after insertion.
	 * @return int The ID of the inserted data.
	 */
	function insert(&$dataset);

	/**
	 * Enables or disables strict fixed-length field validation before writes.
	 *
	 * @param bool $flg True to throw on field size overflow, false to keep legacy truncation behavior.
	 * @return void
	 */
	function set_strict_field_length(bool $flg): void;

	/**
	 * Validates dataset values against fixed field sizes.
	 *
	 * @param array $dataset The data to validate.
	 * @return array List of field size violations.
	 */
	function validate_field_lengths(array $dataset): array;

	/**
	 * Deletes the data corresponding to the provided ID.
	 * 
	 * @param int $id The ID of the data to delete.
	 * @return void
	 */
	function delete($id);

	/**
	 * Updates existing data in the file.
	 * 
	 * @param array $dataset The updated data, including the ID of the data to update.
	 * @return void
	 */
	function update($dataset);

	/**
	 * Restores an in-place deleted record whose flag is X.
	 *
	 * @param array $dataset The full data row to restore, including id.
	 * @return void
	 */
	function restore_deleted_record(array $dataset): void;

	/**
	 * Retrieves the next available data entry.
	 * 
	 * @return array|null The next data entry or null if no data is available.
	 */
	function next();

	/**
	 * Moves the file pointer to the specified position in the data file.
	 * 
	 * @param int $start_number The number of the record to move to, starting from 1.
	 * @return bool True if the position is valid, otherwise false.
	 */
	function seek($start_number);

	/**
	 * Moves the file pointer to the last data record.
	 * 
	 * @return void
	 */
	function seek_end();

	/**
	 * Retrieves the previous available data entry.
	 * 
	 * @return array|null The previous data entry or null if no data is available.
	 */
	function before();

	/**
	 * Retrieves all data entries as an array, optionally sorted by a specific item and order.
	 * 
	 * @param string $sort_item The field name to sort by.
	 * @param int $sort_order The sort order. Use SORT_ASC or SORT_DESC.
	 * @return array The array of all data entries.
	 */
	function getall($sortitem = null, $sort_order = SORT_ASC);

	/**
	 * Retrieves the data entry that matches the provided ID.
	 * 
	 * @param int $id The ID of the data entry to retrieve.
	 * @return array|null The matching data entry or null if no match is found.
	 */
	function get($id);

	/**
	 * Retrieves the file path of the data file.
	 * 
	 * @return string The file path.
	 */
	function get_path_dat();

	/**
	 * Closes the data file.
	 * 
	 * @return void
	 */
	function close();

	/**
	 * Selects data based on a matching condition for a specific field.
	 * 
	 * @param string $itemname The field to filter.
	 * @param mixed $value The value to match.
	 * @param array|string $match_patterns The matching condition(s), which can be a single pattern (e.g., '=') or an array of patterns (e.g., ['=', '>']) (default: true).
	 * @param string $and_or Logical operator (AND/OR) for multiple conditions (default: AND).
	 * @param string|null $sortitem Field to sort the result by (optional).
	 * @param int $sort_order Sort order (SORT_ASC or SORT_DESC, default: SORT_DESC).
	 * @param int|null $max Maximum number of results to return (optional).
	 * @param bool|null $is_last Reference to a variable that indicates if the last result has been reached.
	 * @return array The filtered data entries.
	 */
	function select($itemname, $value, $match_patterns = true, $and_or = "AND", $sortitem = null, $sort_order = SORT_DESC, $max = null, &$is_last = null);

	/**
	 * Filters data based on conditions for multiple fields.
	 * 
	 * @param array|string $itemname The field or fields to filter by.
	 * @param array|string $value The value or values to match.
	 * @param bool $exact_match Whether to use exact matching (default: false).
	 * @param string $and_or Logical operator (AND/OR) for multiple conditions (default: AND).
	 * @param string|null $sortitem Field to sort the result by (optional).
	 * @param int $sort_order Sort order (SORT_ASC or SORT_DESC, default: SORT_DESC).
	 * @param int|null $max Maximum number of results to return (optional).
	 * @param bool|null $is_last Reference to a variable that indicates if the last result has been reached.
	 * @return array The filtered data entries.
	 */
	function filter($itemname, $value, $exact_match = false, $and_or = "AND", $sortitem = null, $sort_order = SORT_DESC, $max = null, &$is_last = null);

	/**
	 * Retrieves an array of prohibited item names.
	 * 
	 * @return array The array of prohibited item names.
	 */
	function get_prohibition_items();

	/**
	 * Retrieves the identifier for the current instance.
	 * This function is used for managing files in the framework.
	 * 
	 * @return string The identifier.
	 */
	function get_identifier();
	
	// Filterで、0の値を有効にするか (Default:false 0は検索から排除)
	public function set_flg_filter_zero($flg);
	
	public function iterate_filter($func);
}
