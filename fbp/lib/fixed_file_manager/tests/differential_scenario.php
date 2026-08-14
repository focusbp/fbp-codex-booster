<?php

if (PHP_SAPI !== "cli") {
	http_response_code(404);
	exit;
}

class Controller_class {
	public static function getInstance() { return null; }
}

if ($argc !== 3) {
	fwrite(STDERR, "Usage: php differential_scenario.php <ffm.php> <workdir>\n");
	exit(2);
}

putenv("FBP_FFM_LOG_DISABLE=1");
require_once $argv[1];
$root = $argv[2];
@mkdir($root . "/data", 0777, true);
@mkdir($root . "/fmt", 0777, true);
file_put_contents($root . "/fmt/sample.fmt", "id,24,N\nparent_id,24,N\nname,60,T\nstatus,24,N\nsort,24,N\n");
$GLOBALS["lock_class_arr"] = [];
$ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt");

foreach ([
	["parent_id" => 10, "name" => "alpha", "status" => 0, "sort" => 30],
	["parent_id" => 20, "name" => "bravo", "status" => 1, "sort" => 10],
	["parent_id" => 10, "name" => "charlie", "status" => 1, "sort" => 20],
	["parent_id" => 10, "name" => "delta", "status" => 2, "sort" => 20],
] as $row) {
	$ffm->insert($row);
}

$before = $ffm->select("parent_id", 10, true, "AND", "sort", SORT_ASC);
$row = $ffm->get(3);
$row["parent_id"] = 20;
$row["name"] = "charlie-updated";
$ffm->update($row);
$ffm->delete(2);
$deleted = ["id" => 2, "parent_id" => 20, "name" => "bravo", "status" => 1, "sort" => 10];
$ffm->restore_deleted_record($deleted);
$is_last = null;
$filtered = $ffm->filter(["parent_id"], [10], true, "AND", "id", SORT_DESC, 2, $is_last);
$all = $ffm->getall("id", SORT_ASC);
$header = $ffm->get_header_info();
$ffm->close();

echo json_encode([
	"before" => $before,
	"filtered" => $filtered,
	"is_last" => $is_last,
	"all" => $all,
	"header" => $header,
	"dat_sha256" => hash_file("sha256", $root . "/data/sample.dat"),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
