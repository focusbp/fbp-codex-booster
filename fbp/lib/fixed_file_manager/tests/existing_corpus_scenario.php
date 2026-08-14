<?php

if (PHP_SAPI !== "cli") {
	http_response_code(404);
	exit;
}

class Controller_class {
	public static function getInstance() { return null; }
}

if ($argc !== 3) {
	fwrite(STDERR, "Usage: php existing_corpus_scenario.php <ffm.php> <copied.dat>\n");
	exit(2);
}

putenv("FBP_FFM_LOG_DISABLE=1");
require_once $argv[1];
$ffm = fixed_file_manager::open_dat_header_readonly($argv[2]);
$all = $ffm->getall("id", SORT_ASC);
$parent_value = null;
foreach ($all as $row) {
	if (array_key_exists("parent_id", $row)) {
		$parent_value = $row["parent_id"];
		break;
	}
}
$selected = $parent_value === null ? [] : $ffm->select("parent_id", $parent_value, true, "AND", "id", SORT_DESC, 100);
$header = $ffm->get_header_info();
$ffm->close();
echo json_encode([
	"row_count" => count($all),
	"rows_hash" => hash("sha256", serialize($all)),
	"parent_value" => $parent_value,
	"selected_count" => count($selected),
	"selected_hash" => hash("sha256", serialize($selected)),
	"header" => $header,
	"dat_hash" => hash_file("sha256", $argv[2]),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
