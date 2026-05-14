<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Safety guard: do not run CLI from NetBeansProjects workspace.
$cli_path = realpath(__FILE__);
if ($cli_path === false) {
	$cli_path = __FILE__;
}
if (strpos($cli_path, "/NetBeansProjects/") !== false) {
	fwrite(STDERR, "ERROR: cli.php must not be executed under NetBeansProjects.\n");
	exit(1);
}

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);
mb_internal_encoding("UTF-8");

// Prevent concurrent cli.php execution per app directory.
// FBP_CLI_NO_LOCK=1 is used by nested CLI calls (e.g. programmer -> cli.php)
// to avoid self-deadlock on this global lock.
$skip_lock = ((string) getenv("FBP_CLI_NO_LOCK") === "1");
$cli_lock_fp = null;
if (!$skip_lock) {
	$lock_key = md5((string) realpath(__DIR__));
	$lock_file = sys_get_temp_dir() . "/fbp_cli_" . $lock_key . ".lock";
	$cli_lock_fp = fopen($lock_file, "c");
	if ($cli_lock_fp === false) {
		fwrite(STDERR, "ERROR: failed to open CLI lock file: " . $lock_file . "\n");
		exit(1);
	}
	if (!flock($cli_lock_fp, LOCK_EX)) {
		fwrite(STDERR, "ERROR: failed to acquire CLI lock: " . $lock_file . "\n");
		fclose($cli_lock_fp);
		exit(1);
	}
	register_shutdown_function(function () use (&$cli_lock_fp) {
		if (is_resource($cli_lock_fp)) {
			flock($cli_lock_fp, LOCK_UN);
			fclose($cli_lock_fp);
		}
	});
}

include "lib/SmartyBootstrap.php";
fbp_include_smarty();
$smarty = new Smarty();

include("lib/ValueFormatter.php");
include("interface/Controller.php");
include("lib/Controller_class.php");
include("lib/I18nSimple.php");
include("interface/CodegenActionInterface.php");
include("interface/linebot/linebot.php");
include("lib/linebot/Linebot_class.php");
include("lib/fixed_file_manager/fixed_file_manager.php");
include("lib/Dirs.php");
include("lib/pdfmaker/pdfmaker_class.php");

$dir = new Dirs();

function cli_load_error_report_level(Dirs $dir): string {
	try {
		$ffm = new fixed_file_manager("setting", $dir->datadir . "/setting", $dir->get_class_dir("setting") . "/fmt");
		$setting = $ffm->get(1);
		$ffm->close();
	} catch (Throwable $e) {
		$setting = [];
	}
	$level = is_array($setting) ? (string) ($setting["error_report_level"] ?? "legacy_compatible") : "legacy_compatible";
	if (!in_array($level, ["legacy_compatible", "strict"], true)) {
		$level = "legacy_compatible";
	}
	return $level;
}

function cli_register_error_handler(string $level): void {
	set_error_handler(function ($severity, $message, $file, $line) use ($level) {
		if (!(error_reporting() & $severity)) {
			return false;
		}
		if ($severity === E_RECOVERABLE_ERROR) {
			throw new ErrorException($message, 0, $severity, $file, $line);
		}
		$reportable = [E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED];
		if (in_array($severity, $reportable, true)) {
			if ($level === "strict") {
				throw new ErrorException($message, 0, $severity, $file, $line);
			}
			return true;
		}
		throw new ErrorException($message, 0, $severity, $file, $line);
	});
}

cli_register_error_handler(cli_load_error_report_level($dir));

function cli_prepare_smarty(Smarty $smarty) {
	$smarty->escape_html  = true;
	$smarty->error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING;
	fbp_register_smarty_plugins($smarty, dirname(__FILE__) . "/lib/smarty_plugins_org/");
	$smarty->registerPlugin('modifier', 'is_numeric', 'is_numeric');
	$base_template_dir = dirname(__FILE__) . "/Templates";
	$smarty->assign("base_template_dir", $base_template_dir);
	$smarty->assign("timestamp", strtotime("now"));
}

function cli_get_class_object(Controller $ctl, $class, Dirs $dir) {
	try {
		$classfile = $dir->get_class_dir($class) . "/$class.php";
	} catch (Exception $e) {
		return null;
	}
	include_once($classfile);

	$reflectionClass = new ReflectionClass($class);
	$constructor = $reflectionClass->getConstructor();
	if ($constructor) {
		$params = $constructor->getParameters();
		if (count($params) > 0) {
			return new $class($ctl);
		}
		return new $class;
	}
	return new $class;
}

function create_db($name, $data_dir, $fmt_dir) {
	if (!isset($GLOBALS["cli_db_pool"]) || !is_array($GLOBALS["cli_db_pool"])) {
		$GLOBALS["cli_db_pool"] = [];
	}
	$pool = &$GLOBALS["cli_db_pool"];
	$key = $data_dir . "|" . $fmt_dir . "|" . $name;
	if (isset($pool[$key])) {
		return $pool[$key];
	}
	$pool[$key] = new fixed_file_manager($name, $data_dir, $fmt_dir);
	return $pool[$key];
}

function cli_close_all_db() {
	if (!isset($GLOBALS["cli_db_pool"]) || !is_array($GLOBALS["cli_db_pool"])) {
		return;
	}
	foreach ($GLOBALS["cli_db_pool"] as $ffm) {
		if (is_object($ffm) && method_exists($ffm, "close")) {
			$ffm->close();
		}
	}
	$GLOBALS["cli_db_pool"] = [];
}

function cli_db(Dirs $dir, $table) {
	$table = (string) $table;
	if ($table === "") {
		throw new Exception("table name is empty");
	}

	$app_user_fmt = $dir->appdir_user . "/" . $table . "/fmt/" . $table . ".fmt";
	if (is_file($app_user_fmt)) {
		$data_dir = $dir->datadir . "/" . $table . "/";
		$fmt_dir = $dir->appdir_user . "/" . $table . "/fmt";
		return create_db($table, $data_dir, $fmt_dir);
	}

	$app_fw_fmt = $dir->appdir_fw . "/" . $table . "/fmt/" . $table . ".fmt";
	if (is_file($app_fw_fmt)) {
		$data_dir = $dir->datadir . "/" . $table . "/";
		$fmt_dir = $dir->appdir_fw . "/" . $table . "/fmt";
		return create_db($table, $data_dir, $fmt_dir);
	}

	// common: data dir is /classes/data/common, fmt dir is /classes/data/_common/fmt
	$data_dir = $dir->datadir . "/common/";
	$fmt_dir = $dir->get_class_dir("common") . "/fmt";
	return create_db($table, $data_dir, $fmt_dir);
}

function cli_make_table_format(Dirs $dir, $ffm_db, $ffm_db_fields) {
	$fmt_root = $dir->get_class_dir("common") . "/fmt/";
	if (is_dir($fmt_root)) {
		$files = glob($fmt_root . '*');
		foreach ($files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
	} else {
		mkdir($fmt_root, 0777, true);
	}

	$tables = $ffm_db->getall("sort", SORT_ASC);
	foreach ($tables as $table) {
		$db_id = $table["id"];
		$txt = "id,24,N\n";
		$fields = $ffm_db_fields->select("db_id", $db_id, true, "AND", "sort", SORT_ASC);
		foreach ($fields as $field) {
			$t = "T";
			if ($field["type"] == "number"
				|| $field["type"] == "radio"
				|| $field["type"] == "datetime"
				|| $field["type"] == "date"
				|| $field["type"] == "time") {
				$t = "N";
			} else if ($field["type"] == "dropdown" && startsWith((string) ($field["constant_array_name"] ?? ""), "table/")) {
				$t = "N";
			} else if ($field["type"] == "float") {
				$t = "F";
			} else if ($field["type"] == "checkbox") {
				$t = "A";
			}
			$txt .= $field["parameter_name"] . "," . $field["length"] . "," . $t . "\n";
		}
		file_put_contents($fmt_root . $table["tb_name"] . ".fmt", $txt);
	}
}

function cli_prepare_setting(Dirs $dir) {
	$setting_fmt_dir = $dir->appdir_fw . "/setting/fmt";
	$setting_data_dir = $dir->datadir . "/setting/";
	$ffm_setting = create_db("setting", $setting_data_dir, $setting_fmt_dir);
	$setting = $ffm_setting->get(1);
	if (empty($setting)) {
		$d = [];
		$d["force_testmode"] = 1;
		$ffm_setting->insert($d);
		$setting = $ffm_setting->get(1);
	}
	if (empty($setting["secret"])) {
		$setting["secret"] = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz!@#$%^&*()-_|{}[];:<>?/'), 0, 18);
		$ffm_setting->update($setting);
	}
	if (empty($setting["iv"])) {
		$setting["iv"] = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz!@#$%^&*()-_|{}[];:<>?/'), 0, 16);
		$ffm_setting->update($setting);
	}
	if (empty($setting["timezone"])) {
		$setting["timezone"] = date_default_timezone_get();
		$ffm_setting->update($setting);
	}
	if (empty($setting["date_format"])) {
		$setting["date_format"] = "Y/m/d";
		$ffm_setting->update($setting);
	}
	if (empty($setting["datetime_format"])) {
		$setting["datetime_format"] = "Y/m/d H:i";
		$ffm_setting->update($setting);
	}
	if (empty($setting["year_month_format"])) {
		$setting["year_month_format"] = "Y/m";
		$ffm_setting->update($setting);
	}
	if (empty($setting["locale_code"])) {
		$setting["locale_code"] = I18nSimple::get_default_locale_code_from_language_code(
			I18nSimple::get_language_code_from_setting($setting)
		);
		$ffm_setting->update($setting);
	}
}

function cli_get_setting(Dirs $dir) {
	$setting_fmt_dir = $dir->appdir_fw . "/setting/fmt";
	$setting_data_dir = $dir->datadir . "/setting/";
	$ffm_setting = create_db("setting", $setting_data_dir, $setting_fmt_dir);
	return $ffm_setting->get(1);
}

function cli_get_setting_db(Dirs $dir) {
	$setting_fmt_dir = $dir->appdir_fw . "/setting/fmt";
	$setting_data_dir = $dir->datadir . "/setting/";
	return create_db("setting", $setting_data_dir, $setting_fmt_dir);
}

$db_fmt_dir = $dir->appdir_fw . "/db/fmt";
$db_data_dir = $dir->datadir  . "/db/";
$ffm_db =  create_db("db", $db_data_dir, $db_fmt_dir);
$ffm_db_fields =  create_db("db_fields", $db_data_dir, $db_fmt_dir);

$ca_fmt_dir = $dir->appdir_fw . "/constant_array/fmt";
$ca_data_dir = $dir->datadir  . "/constant_array/";
$ffm_constant_array = create_db("constant_array", $ca_data_dir, $ca_fmt_dir);
$ffm_values = create_db("values", $ca_data_dir, $ca_fmt_dir);

$add_fmt_dir = $dir->appdir_fw . "/db_additionals/fmt";
$add_data_dir = $dir->datadir  . "/db_additionals/";
$ffm_additionals = create_db("additionals", $add_data_dir, $add_fmt_dir);

$email_fmt_dir = $dir->appdir_fw . "/email_format/fmt";
$email_data_dir = $dir->datadir  . "/email_format/";
$ffm_email_format = create_db("email_format", $email_data_dir, $email_fmt_dir);

$cron_fmt_dir = $dir->appdir_fw . "/cron/fmt";
$cron_data_dir = $dir->datadir  . "/cron/";
$ffm_cron = create_db("cron", $cron_data_dir, $cron_fmt_dir);

$webhook_rule_fmt_dir = $dir->appdir_fw . "/webhook_rule/fmt";
$webhook_rule_data_dir = $dir->datadir  . "/webhook_rule/";
$ffm_webhook_rule = create_db("webhook_rule", $webhook_rule_data_dir, $webhook_rule_fmt_dir);

$embed_app_fmt_dir = $dir->appdir_fw . "/embed_app/fmt";
$embed_app_data_dir = $dir->datadir  . "/embed_app/";
$ffm_embed_app = create_db("embed_app", $embed_app_data_dir, $embed_app_fmt_dir);

$db_admin_fmt_dir = $dir->appdir_fw . "/db/fmt";
$db_admin_data_dir = $dir->datadir  . "/db/";
$ffm_db_admin = create_db("db", $db_admin_data_dir, $db_admin_fmt_dir);
$ffm_db_fields_admin = create_db("db_fields", $db_admin_data_dir, $db_admin_fmt_dir);
$ffm_screen_fields_admin = create_db("screen_fields", $db_admin_data_dir, $db_admin_fmt_dir);

$command = $argv[1] ?? null;

function cli_output_json($data, $exit_code = 0) {
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit($exit_code);
}

function cli_get_json_arg(array $argv): array {
	$json = null;
	for ($i = 1; $i < count($argv); $i++) {
		$arg = $argv[$i];
		if (strpos($arg, "--json=") === 0) {
			$json = substr($arg, 7);
			break;
		}
		if ($arg === "--json" && isset($argv[$i + 1])) {
			$json = $argv[$i + 1];
			break;
		}
		if (strpos($arg, "--json-file=") === 0) {
			$json = "@" . substr($arg, 12);
			break;
		}
		if ($arg === "--json-file" && isset($argv[$i + 1])) {
			$json = "@" . $argv[$i + 1];
			break;
		}
	}
	if ($json === null || $json === "") {
		return [false, "Missing --json argument", null];
	}
	if (strpos($json, "@") === 0) {
		$json_file = substr($json, 1);
		if ($json_file === "" || !is_file($json_file)) {
			return [false, "JSON file not found", null];
		}
		$json = file_get_contents($json_file);
		if ($json === false) {
			return [false, "Could not read JSON file", null];
		}
	}
	$data = json_decode($json, true);
	if (!is_array($data)) {
		return [false, "Invalid JSON", null];
	}
	return [true, "", $data];
}

function cli_ensure_parent_id_field($ffm_db_admin, $ffm_db_fields_admin, int $db_id): bool {
	$table = $ffm_db_admin->get($db_id);
	if (empty($table)) {
		return false;
	}
	$parent_tb_id = (int) ($table["parent_tb_id"] ?? 0);
	if ($parent_tb_id <= 0) {
		return false;
	}

	$rows = $ffm_db_fields_admin->select(
		["db_id", "parameter_name"],
		[$db_id, "parent_id"],
		true,
		"AND",
		"id",
		SORT_ASC
	);
	if (!empty($rows)) {
		return false;
	}

	$field = [
		"db_id" => $db_id,
		"parameter_name" => "parent_id",
		"parameter_title" => "Parent ID",
		"type" => "number",
		"length" => 24,
		"sort" => 0,
	];
	$ffm_db_fields_admin->insert($field);
	return true;
}

function cli_resolve_screen_field_links($ffm_db_admin, $ffm_db_fields_admin, array $data): array {
	if (!empty($data["db_fields_id"])) {
		$field = $ffm_db_fields_admin->get((int) $data["db_fields_id"]);
		if (empty($field)) {
			return [false, "db_fields_id not found: " . (int) $data["db_fields_id"], $data];
		}
		if (empty($data["parameter_name"])) {
			$data["parameter_name"] = (string) ($field["parameter_name"] ?? "");
		}
		return [true, "", $data];
	}

	if (empty($data["tb_name"]) || empty($data["parameter_name"])) {
		return [false, "Missing tb_name or parameter_name to resolve db_fields_id", $data];
	}

	$db_list = $ffm_db_admin->select("tb_name", (string) $data["tb_name"]);
	if (empty($db_list)) {
		return [false, "tb_name not found in db: " . (string) $data["tb_name"], $data];
	}
	$db = $db_list[0];

	$field_list = $ffm_db_fields_admin->select(
		["db_id", "parameter_name"],
		[(int) ($db["id"] ?? 0), (string) $data["parameter_name"]],
		true,
		"AND",
		"id",
		SORT_ASC
	);
	if (empty($field_list)) {
		return [
			false,
			"db_fields not found: tb_name=" . (string) $data["tb_name"] . ", parameter_name=" . (string) $data["parameter_name"],
			$data
		];
	}

	$data["db_fields_id"] = (int) ($field_list[0]["id"] ?? 0);
	return [true, "", $data];
}

function cli_apply_cron(Dirs $dir, Smarty $smarty): void {
	cli_prepare_setting($dir);
	$setting = cli_get_setting($dir);
	if (session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}
	$windowcode = "CLI_CRON_" . uniqid();
	$_SESSION[$windowcode] = [];
	$ctl = new Controller_class("cron", $smarty);
	$ctl->set_windowcode($windowcode);
	$ctl->set_session("setting", $setting);
	try {
		$ctl->cron_set();
	} catch (Throwable $e) {
		// ignore cron_set errors in CLI context
	}
}

function startsWith($haystack, $needle) {
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle) {
	$length = strlen($needle);
	if ($length == 0) {
		return true;
	}
	return (substr($haystack, -$length) === $needle);
}

function getClassObject(Controller $ctl, $class, Dirs $dir){
	return cli_get_class_object($ctl, $class, $dir);
}

function cli_extract_email_placeholders($text) {
	if (!is_string($text) || $text === "") {
		return [];
	}
	$matches = [];
	preg_match_all('/\\{\\$([A-Za-z0-9_]+)\\.([A-Za-z0-9_]+)\\}/', $text, $matches, PREG_SET_ORDER);
	$list = [];
	foreach ($matches as $m) {
		$table = $m[1];
		$field = $m[2];
		$key = $table . "." . $field;
		$list[$key] = ["table" => $table, "field" => $field];
	}
	return array_values($list);
}

function cli_normalize_db_field_payload(array $data): array {
	$type = (string) ($data["type"] ?? "");
	$default_lengths = [
		"text" => 255,
		"number" => 24,
		"float" => 24,
		"textarea" => 1000,
		"textarea_links" => 1000,
		"markdown" => 1000,
		"dropdown" => 24,
		"checkbox" => 255,
		"radio" => 3,
		"date" => 15,
		"datetime" => 15,
		"year_month" => 15,
		"color" => 15,
		"file" => 24,
		"image" => 24,
		"vimeo" => 255,
	];

	if ($type !== "" && isset($default_lengths[$type])) {
		$length = (int) ($data["length"] ?? 0);
		if ($length <= 0) {
			$data["length"] = $default_lengths[$type];
		}
	}

	if ($type === "image") {
		if (!isset($data["image_width"]) || $data["image_width"] === "" || $data["image_width"] === null) {
			$data["image_width"] = 300;
		}
		if (!isset($data["image_width_thumbnail"]) || $data["image_width_thumbnail"] === "" || $data["image_width_thumbnail"] === null) {
			$data["image_width_thumbnail"] = 120;
		}
	}

	return $data;
}

function cli_normalize_session_id($session_id, $fallback = "CLIAPPCALL") {
	$session_id = preg_replace('/[^A-Za-z0-9,-]/', '', (string) $session_id);
	if ($session_id === null) {
		$session_id = "";
	}
	if ($session_id === "") {
		$session_id = (string) $fallback;
	}
	if (strlen($session_id) > 120) {
		$session_id = substr($session_id, 0, 120);
	}
	return $session_id;
}

function cli_app_call_execute(array $data, Dirs $dir, Smarty $smarty) {
	if (!defined("CLI_APP_CALL")) {
		define("CLI_APP_CALL", true);
	}
	$class = (string) ($data["class"] ?? "");
	$function = (string) ($data["function"] ?? "");
	if ($class === "" || $function === "") {
		throw new Exception("Missing class or function in --json");
	}

	$post = $data["post"] ?? [];
	$get = $data["get"] ?? [];
	$cookies = $data["cookies"] ?? [];
	$files = $data["files"] ?? [];
	if (!is_array($post)) { $post = []; }
	if (!is_array($get)) { $get = []; }
	if (!is_array($cookies)) { $cookies = []; }
	if (!is_array($files)) { $files = []; }

	if (!isset($post["class"])) { $post["class"] = $class; }
	if (!isset($post["function"])) { $post["function"] = $function; }

	$_POST = $post;
	$_GET = $get;
	$_COOKIE = $cookies;
	$_FILES = [];
	foreach ($files as $key => $info) {
		if (!is_array($info)) {
			continue;
		}
		$path = (string) ($info["path"] ?? "");
		if ($path === "" || !is_file($path)) {
			throw new Exception("File not found: " . $key);
		}
		$_FILES[$key] = [
			"name" => (string) ($info["name"] ?? basename($path)),
			"type" => (string) ($info["type"] ?? "application/octet-stream"),
			"tmp_name" => $path,
			"error" => 0,
			"size" => filesize($path)
		];
	}

	$_SERVER["REQUEST_METHOD"] = !empty($post) ? "POST" : "GET";
	if (empty($_SERVER["HTTP_HOST"])) { $_SERVER["HTTP_HOST"] = "cli.local"; }
	if (empty($_SERVER["REQUEST_URI"])) { $_SERVER["REQUEST_URI"] = "/cli"; }

	cli_prepare_setting($dir);
	cli_prepare_smarty($smarty);
	$setting = cli_get_setting($dir);

	$windowcode = (string) ($data["windowcode"] ?? "");
	if ($windowcode === "") {
		$windowcode = "CLIAPPCALL";
	}
	$session_fallback = cli_normalize_session_id($windowcode, "CLIAPPCALL");
	$session_id = cli_normalize_session_id((string) ($data["session_id"] ?? ""), $session_fallback);
	if ($session_id !== "" && session_status() !== PHP_SESSION_ACTIVE) {
		@session_id($session_id);
	}
	if (session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}

	$ctl = new Controller_class($class, $smarty);
	$smarty->assign("_ctl", $ctl);

	$ctl->set_windowcode($windowcode);
	$smarty->assign("windowcode", $windowcode);

	$appcode = (string) ($data["appcode"] ?? "");
	$testserver = isset($data["testserver"]) ? (bool) $data["testserver"] : true;
	$check_login = isset($data["check_login"]) ? (bool) $data["check_login"] : false;
	$login = isset($data["login"]) ? (bool) $data["login"] : true;
	$ctl->set_session("class", $class);
	$ctl->set_session("appcode", $appcode);
	$ctl->set_session("testserver", $testserver);
	$ctl->set_session("setting", $setting);
	if ($login) {
		$ctl->set_session("login", true);
	}
	$ctl->set_check_login($check_login);
	$ctl->set_called_function($function);
	$ctl->set_called_parameters();
	$ctl->set_userdir($dir->appdir_user);
	$ctl->assign("ctl", $ctl);

	cli_close_all_db();
	$constant_names = $ctl->get_all_constant_array_names(false, false);
	$smarty->assign("constant_array_name", $constant_names);
	foreach ($constant_names as $arr_name) {
		$constant_values = $ctl->get_constant_array($arr_name, false);
		$smarty->assign($arr_name, $constant_values);
		$constant_colors = $ctl->get_constant_array_color($arr_name);
		$smarty->assign($arr_name . "_colors", $constant_colors);
	}
	$ctl->close_all_db();

	$appobj = cli_get_class_object($ctl, $class, $dir);
	if ($appobj == null) {
		throw new Exception("Class not found: " . $class);
	}

	if (method_exists($appobj, "init")) {
		$appobj->init($ctl);
	}

	$output_file = (string) ($data["output_file"] ?? "");
	ob_start();

	try {
		if ($check_login && !$ctl->get_session("login")) {
			echo json_encode([
				"ok" => false,
				"error" => "login required",
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		} else {
			if (method_exists($appobj, $function)) {
				$appobj->$function($ctl);
			} else {
				echo json_encode([
					"ok" => false,
					"error" => "Class \"$class\" does not have function \"$function\"",
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			}
			if (!$ctl->display_flg && !$ctl->stop_res) {
				$ctl->res();
			}
		}
	} catch (Throwable $e) {
		echo json_encode([
			"ok" => false,
			"error" => $e->getMessage(),
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	}

	$buffer = ob_get_clean();
	$response_json = json_decode($buffer, true);
	$out = [
		"ok" => true,
		"class" => $class,
		"function" => $function,
		"session_id" => session_id(),
		"windowcode" => $windowcode,
		"request" => [
			"post" => $post,
			"get" => $get,
			"cookies" => $cookies,
			"files" => $files,
		],
	];

	if ($output_file !== "") {
		file_put_contents($output_file, $buffer);
		$out["output_file"] = $output_file;
		$out["bytes"] = strlen($buffer);
	}
	if (is_array($response_json)) {
		$out["response_json"] = $response_json;
		if (isset($response_json["console_log"])) {
			$out["console_log"] = $response_json["console_log"];
		}
		if (isset($response_json["post"])) {
			$out["response_post"] = $response_json["post"];
		}
	} else if ($output_file === "") {
		$out["response_text"] = $buffer;
	}
	return $out;
}

function cli_get_value_by_path($data, $path, &$exists = null) {
	$exists = true;
	if ($path === null || $path === "") {
		return $data;
	}
	$current = $data;
	$parts = explode(".", (string) $path);
	foreach ($parts as $part) {
		if ($part === "") {
			continue;
		}
		if (is_array($current) && array_key_exists($part, $current)) {
			$current = $current[$part];
			continue;
		}
		if (is_array($current) && ctype_digit((string) $part)) {
			$idx = (int) $part;
			if (array_key_exists($idx, $current)) {
				$current = $current[$idx];
				continue;
			}
		}
		$exists = false;
		return null;
	}
	return $current;
}

function cli_scalar_to_string($value) {
	if (is_bool($value)) {
		return $value ? "true" : "false";
	}
	if ($value === null) {
		return "null";
	}
	if (is_scalar($value)) {
		return (string) $value;
	}
	return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function cli_run_checks($result, $checks) {
	if (!is_array($checks)) {
		throw new Exception("checks must be an array");
	}
	$out = [];
	$all_ok = true;
	foreach ($checks as $i => $check) {
		if (!is_array($check)) {
			throw new Exception("check at index $i must be an object");
		}
		$path = (string) ($check["path"] ?? "");
		$label = (string) ($check["label"] ?? ($path !== "" ? $path : "check_" . $i));
		$value = cli_get_value_by_path($result, $path, $exists);
		$ok = true;
		$reason = "ok";

		if (array_key_exists("exists", $check)) {
			$want_exists = (bool) $check["exists"];
			if ($exists !== $want_exists) {
				$ok = false;
				$reason = $want_exists ? "path does not exist" : "path exists unexpectedly";
			}
		}
		if ($ok && array_key_exists("equals", $check)) {
			if ($value != $check["equals"]) {
				$ok = false;
				$reason = "equals mismatch";
			}
		}
		if ($ok && array_key_exists("contains", $check)) {
			$haystack = cli_scalar_to_string($value);
			$needle = (string) $check["contains"];
			if (strpos($haystack, $needle) === false) {
				$ok = false;
				$reason = "contains mismatch";
			}
		}
		if ($ok && array_key_exists("not_contains", $check)) {
			$haystack = cli_scalar_to_string($value);
			$needle = (string) $check["not_contains"];
			if (strpos($haystack, $needle) !== false) {
				$ok = false;
				$reason = "not_contains mismatch";
			}
		}
		if ($ok && array_key_exists("regex", $check)) {
			$pattern = (string) $check["regex"];
			$haystack = cli_scalar_to_string($value);
			if (@preg_match($pattern, "") === false) {
				throw new Exception("invalid regex at check index $i");
			}
			if (!preg_match($pattern, $haystack)) {
				$ok = false;
				$reason = "regex mismatch";
			}
		}
		if ($ok && array_key_exists("count_eq", $check)) {
			$count = is_array($value) ? count($value) : 0;
			if ($count !== (int) $check["count_eq"]) {
				$ok = false;
				$reason = "count_eq mismatch";
			}
		}
		if ($ok && array_key_exists("count_gte", $check)) {
			$count = is_array($value) ? count($value) : 0;
			if ($count < (int) $check["count_gte"]) {
				$ok = false;
				$reason = "count_gte mismatch";
			}
		}

		$out[] = [
			"label" => $label,
			"path" => $path,
			"ok" => $ok,
			"reason" => $reason,
			"exists" => $exists,
			"value_preview" => mb_substr(cli_scalar_to_string($value), 0, 300),
		];
		if (!$ok) {
			$all_ok = false;
		}
	}
	return [$all_ok, $out];
}

function cli_get_table_field_map($ffm_db_admin, $ffm_db_fields_admin) {
	$tables = $ffm_db_admin->getall("id", SORT_ASC);
	$fields = $ffm_db_fields_admin->getall("id", SORT_ASC);
	$id_to_table = [];
	foreach ($tables as $t) {
		$id_to_table[(int) $t["id"]] = $t["tb_name"];
	}
	$map = [];
	foreach ($fields as $f) {
		$db_id = (int) ($f["db_id"] ?? 0);
		if (!isset($id_to_table[$db_id])) {
			continue;
		}
		$tb = $id_to_table[$db_id];
		if (!isset($map[$tb])) {
			$map[$tb] = [];
		}
		$map[$tb][$f["parameter_name"]] = true;
	}
	return $map;
}

if ($command === "db_additionals_list") {
	$list = $ffm_additionals->getall("id", SORT_DESC);
	$out = [
	    "items" => array_values($list),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_additionals_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["show_button"])) {
		$data["show_button"] = 0;
	}
	$id = $ffm_additionals->insert($data);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_additionals_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$ffm_additionals->update($data);
	$out = [
	    "ok" => true,
	    "id" => $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_additionals_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$ffm_additionals->delete((int) $data["id"]);
	$out = [
	    "ok" => true,
	    "id" => (int) $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "app_call") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	try {
		$out = cli_app_call_execute($data, $dir, $smarty);
		cli_output_json($out, 0);
	} catch (Throwable $e) {
		fwrite(STDERR, $e->getMessage() . "\n");
		exit(1);
	}
}

if ($command === "app_check") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	try {
		$result = cli_app_call_execute($data, $dir, $smarty);
		[$all_ok, $check_results] = cli_run_checks($result, $data["checks"] ?? []);
		$out = [
			"ok" => $all_ok,
			"class" => $result["class"] ?? "",
			"function" => $result["function"] ?? "",
			"session_id" => $result["session_id"] ?? "",
			"windowcode" => $result["windowcode"] ?? "",
			"checks" => $check_results,
		];
		if (!empty($data["include_result"])) {
			$out["result"] = $result;
		}
		cli_output_json($out, $all_ok ? 0 : 1);
	} catch (Throwable $e) {
		fwrite(STDERR, $e->getMessage() . "\n");
		exit(1);
	}
}

if ($command === "setting_get") {
	cli_prepare_setting($dir);
	$setting = cli_get_setting($dir);
	$out = [
	    "setting" => $setting,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "setting_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (count($data) === 0) {
		fwrite(STDERR, "Empty --json is not allowed\n");
		exit(1);
	}

	cli_prepare_setting($dir);
	$ffm_setting = cli_get_setting_db($dir);
	$setting = $ffm_setting->get(1);
	if (empty($setting)) {
		fwrite(STDERR, "setting row(1) was not found\n");
		exit(1);
	}

	foreach ($data as $k => $v) {
		if ((string) $k === "id") {
			continue;
		}
		$setting[$k] = $v;
	}
	$setting["id"] = 1;
	$ffm_setting->update($setting);

	$out = [
	    "ok" => true,
	    "id" => 1,
	    "setting" => $ffm_setting->get(1),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "encrypt_string") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$plain = (string) ($data["text"] ?? "");
	cli_prepare_setting($dir);
	$setting = cli_get_setting($dir);
	if (session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}
	$windowcode = "CLI_ENC_" . uniqid();
	$_SESSION[$windowcode] = [];
	$ctl = new Controller_class("common", $smarty);
	$ctl->set_windowcode($windowcode);
	$ctl->set_session("setting", $setting);
	$encrypted = $ctl->encrypt($plain);
	$out = [
	    "encrypted" => $encrypted,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "decrypt_string") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$encrypted = (string) ($data["text"] ?? "");
	cli_prepare_setting($dir);
	$setting = cli_get_setting($dir);
	if (session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}
	$windowcode = "CLI_DEC_" . uniqid();
	$_SESSION[$windowcode] = [];
	$ctl = new Controller_class("common", $smarty);
	$ctl->set_windowcode($windowcode);
	$ctl->set_session("setting", $setting);
	$decrypted = $ctl->decrypt($encrypted);
	$out = [
	    "decrypted" => $decrypted,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "email_format_list") {
	$list = $ffm_email_format->getall("sort", SORT_ASC);
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if ($ok) {
		if (!empty($data["key"])) {
			$list = $ffm_email_format->select("key", $data["key"]);
		} else if (!empty($data["template_name"])) {
			$list = $ffm_email_format->select("template_name", $data["template_name"]);
		}
	}
	$out = [
	    "items" => array_values($list),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "email_format_get") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$item = null;
	if (isset($data["id"])) {
		$item = $ffm_email_format->get((int) $data["id"]);
	} else if (!empty($data["key"])) {
		$list = $ffm_email_format->select("key", $data["key"]);
		$item = $list ? $list[0] : null;
	} else {
		fwrite(STDERR, "Missing id or key in --json\n");
		exit(1);
	}
	$out = [
	    "item" => $item,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "cron_list") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	$list = $ffm_cron->getall("sort", SORT_ASC);
	if ($ok && is_array($data)) {
		if (!empty($data["id"])) {
			$list = [$ffm_cron->get((int) $data["id"])];
		} else if (!empty($data["class_name"])) {
			$list = $ffm_cron->select("class_name", (string) $data["class_name"]);
		} else if (!empty($data["function_name"])) {
			$list = $ffm_cron->select("function_name", (string) $data["function_name"]);
		}
	}
	$out = [
	    "items" => array_values(array_filter($list)),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "cron_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$data["min"] = $data["min"] ?? [];
	$data["hour"] = $data["hour"] ?? [];
	$data["day"] = $data["day"] ?? [];
	$data["month"] = $data["month"] ?? [];
	$data["weekday"] = $data["weekday"] ?? [];
	$id = $ffm_cron->insert($data);
	cli_apply_cron($dir, $smarty);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "cron_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$data["min"] = $data["min"] ?? [];
	$data["hour"] = $data["hour"] ?? [];
	$data["day"] = $data["day"] ?? [];
	$data["month"] = $data["month"] ?? [];
	$data["weekday"] = $data["weekday"] ?? [];
	$d = $ffm_cron->get((int) $data["id"]);
	foreach ($data as $key => $val) {
		$d[$key] = $val;
	}
	$ffm_cron->update($d);
	cli_apply_cron($dir, $smarty);
	$out = [
	    "ok" => true,
	    "id" => (int) $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "cron_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$ffm_cron->delete((int) $data["id"]);
	cli_apply_cron($dir, $smarty);
	$out = [
	    "ok" => true,
	    "id" => (int) $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "webhook_rule_list") {
	$list = $ffm_webhook_rule->getall("sort", SORT_ASC);
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if ($ok && is_array($data)) {
		if (isset($data["id"])) {
			$id = (int) $data["id"];
			$item = $ffm_webhook_rule->get($id);
			$list = $item ? [$item] : [];
		} else if (isset($data["channel"])) {
			$list = $ffm_webhook_rule->select("channel", (string) $data["channel"]);
		} else if (isset($data["enabled"])) {
			$list = $ffm_webhook_rule->select("enabled", (int) $data["enabled"]);
		}
	}
	$out = [
	    "items" => array_values(array_filter($list)),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "webhook_rule_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["channel"])) {
		fwrite(STDERR, "Missing channel in --json\n");
		exit(1);
	}
	if (empty($data["keyword"])) {
		fwrite(STDERR, "Missing keyword in --json\n");
		exit(1);
	}
	if (empty($data["action_class"])) {
		fwrite(STDERR, "Missing action_class in --json\n");
		exit(1);
	}
	if (!isset($data["match_type"])) {
		$data["match_type"] = "exact";
	}
	if (!isset($data["enabled"])) {
		$data["enabled"] = 1;
	}

	$duplicate = $ffm_webhook_rule->select(
		["channel", "keyword"],
		[(string) $data["channel"], (string) $data["keyword"]],
		true,
		"AND",
		"id",
		SORT_ASC
	);
	if (!empty($duplicate)) {
		fwrite(STDERR, "Duplicate webhook_rule: channel+keyword already exists\n");
		exit(1);
	}

	$list = $ffm_webhook_rule->getall("sort", SORT_DESC);
	$data["sort"] = empty($list) ? 0 : ((int) ($list[0]["sort"] ?? 0) + 1);
	$data["updated_at"] = time();

	$id = $ffm_webhook_rule->insert($data);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "webhook_rule_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$id = (int) $data["id"];
	$current = $ffm_webhook_rule->get($id);
	if (empty($current)) {
		fwrite(STDERR, "webhook_rule not found: id=" . $id . "\n");
		exit(1);
	}

	$next = $current;
	foreach ($data as $key => $val) {
		$next[$key] = $val;
	}

	if (isset($next["channel"]) && isset($next["keyword"])) {
		$duplicate = $ffm_webhook_rule->select(
			["channel", "keyword"],
			[(string) $next["channel"], (string) $next["keyword"]],
			true,
			"AND",
			"id",
			SORT_ASC
		);
		foreach ($duplicate as $row) {
			if ((int) ($row["id"] ?? 0) !== $id) {
				fwrite(STDERR, "Duplicate webhook_rule: channel+keyword already exists\n");
				exit(1);
			}
		}
	}

	$next["updated_at"] = time();
	$ffm_webhook_rule->update($next);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "webhook_rule_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$id = (int) $data["id"];
	$ffm_webhook_rule->delete($id);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "embed_app_list") {
	$list = $ffm_embed_app->getall("sort", SORT_ASC);
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if ($ok && is_array($data)) {
		if (isset($data["id"])) {
			$id = (int) $data["id"];
			$item = $ffm_embed_app->get($id);
			$list = $item ? [$item] : [];
		} else if (isset($data["class_name"])) {
			$list = $ffm_embed_app->select("class_name", (string) $data["class_name"]);
		} else if (isset($data["embed_key"])) {
			$list = $ffm_embed_app->select("embed_key", (string) $data["embed_key"]);
		} else if (isset($data["enabled"])) {
			$list = $ffm_embed_app->select("enabled", (int) $data["enabled"]);
		}
	}
	$out = [
	    "items" => array_values(array_filter($list)),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "embed_app_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (empty($data["class_name"])) {
		fwrite(STDERR, "Missing class_name in --json\n");
		exit(1);
	}

	$class_name = trim((string) $data["class_name"]);
	$data["class_name"] = $class_name;
	$data["embed_key"] = $class_name; // Rule: embed_key is same as class_name at registration.

	if (empty($data["title"])) {
		$data["title"] = $class_name;
	}
	if (!isset($data["allowed_origins"])) {
		$data["allowed_origins"] = "";
	}
	if (!isset($data["enabled"])) {
		$data["enabled"] = 1;
	}

	$duplicate = $ffm_embed_app->select("embed_key", $data["embed_key"]);
	if (!empty($duplicate)) {
		fwrite(STDERR, "Duplicate embed_app: embed_key already exists (" . $data["embed_key"] . ")\n");
		exit(1);
	}

	$list = $ffm_embed_app->getall("sort", SORT_DESC);
	$data["sort"] = empty($list) ? 0 : ((int) ($list[0]["sort"] ?? 0) + 1);
	$data["created_at"] = time();
	$data["updated_at"] = time();

	$id = $ffm_embed_app->insert($data);
	$out = [
	    "ok" => true,
	    "id" => $id,
	    "embed_key" => $data["embed_key"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "embed_app_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$id = (int) $data["id"];
	$current = $ffm_embed_app->get($id);
	if (empty($current)) {
		fwrite(STDERR, "embed_app not found: id=" . $id . "\n");
		exit(1);
	}

	$next = $current;
	foreach ($data as $key => $val) {
		$next[$key] = $val;
	}
	$next["updated_at"] = time();
	$ffm_embed_app->update($next);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "embed_app_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$id = (int) $data["id"];
	$ffm_embed_app->delete($id);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "email_format_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$id = $ffm_email_format->insert($data);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "email_format_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$ffm_email_format->update($data);
	$out = [
	    "ok" => true,
	    "id" => $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "email_format_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$id = (int) $data["id"];
	$ffm_email_format->delete($id);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "email_format_validate") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$subject = (string) ($data["subject"] ?? "");
	$body = (string) ($data["body"] ?? "");
	if (isset($data["id"])) {
		$row = $ffm_email_format->get((int) $data["id"]);
		if ($row) {
			$subject = (string) ($row["subject"] ?? "");
			$body = (string) ($row["body"] ?? "");
		}
	}

	$placeholders = array_merge(
		cli_extract_email_placeholders($subject),
		cli_extract_email_placeholders($body)
	);

	$map = cli_get_table_field_map($ffm_db_admin, $ffm_db_fields_admin);
	$unknown_tables = [];
	$unknown_fields = [];
	foreach ($placeholders as $ph) {
		$tb = $ph["table"];
		$fd = $ph["field"];
		if (!isset($map[$tb])) {
			$unknown_tables[$tb] = true;
			continue;
		}
		if (!isset($map[$tb][$fd])) {
			$unknown_fields[] = ["table" => $tb, "field" => $fd];
		}
	}

	$out = [
	    "placeholders" => array_values($placeholders),
	    "unknown_tables" => array_keys($unknown_tables),
	    "unknown_fields" => $unknown_fields,
	    "ok" => (count($unknown_tables) === 0 && count($unknown_fields) === 0),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "constant_array_list") {
	$list = $ffm_constant_array->getall("id", SORT_ASC);
	$out = [
	    "items" => array_values($list),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

function cli_validate_constant_array_name($name) {
	$name = trim((string) $name);
	if ($name === "") {
		return [false, "Missing array_name in --json"];
	}
	if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
		return [false, "array_name must be a lowercase identifier"];
	}
	if (startsWith($name, "table_")) {
		return [false, "array_name must not start with table_"];
	}
	return [true, ""];
}

if ($command === "constant_array_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	[$name_ok, $name_err] = cli_validate_constant_array_name($data["array_name"] ?? "");
	if (!$name_ok) {
		fwrite(STDERR, $name_err . "\n");
		exit(1);
	}
	$id = $ffm_constant_array->insert($data);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "constant_array_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	[$name_ok, $name_err] = cli_validate_constant_array_name($data["array_name"] ?? "");
	if (!$name_ok) {
		fwrite(STDERR, $name_err . "\n");
		exit(1);
	}
	$ffm_constant_array->update($data);
	$out = [
	    "ok" => true,
	    "id" => $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "constant_array_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$id = (int) $data["id"];
	$list = $ffm_values->select("constant_array_id", $id);
	foreach ($list as $val) {
		$ffm_values->delete($val["id"]);
	}
	$ffm_constant_array->delete($id);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "constant_values_list") {
	$items = $ffm_values->getall("sort", SORT_ASC);
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if ($ok && isset($data["constant_array_id"])) {
		$target = (int) $data["constant_array_id"];
		$items = array_values(array_filter($items, function ($row) use ($target) {
			return (int) ($row["constant_array_id"] ?? 0) === $target;
		}));
	}
	$out = [
	    "items" => array_values($items),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "constant_values_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["constant_array_id"])) {
		fwrite(STDERR, "Missing constant_array_id in --json\n");
		exit(1);
	}
	if (!isset($data["key"])) {
		fwrite(STDERR, "Missing key in --json\n");
		exit(1);
	}
	$constant_array_id = (int) $data["constant_array_id"];
	$key = trim((string) $data["key"]);
	if ($key === "") {
		fwrite(STDERR, "Missing key in --json\n");
		exit(1);
	}
	$rows = $ffm_values->select("constant_array_id", $constant_array_id);
	foreach ($rows as $row) {
		if ((string) ($row["key"] ?? "") === $key) {
			fwrite(STDERR, "Duplicate key in constant_array_id\n");
			exit(1);
		}
	}
	$id = $ffm_values->insert($data);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "constant_values_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	if (isset($data["constant_array_id"]) && isset($data["key"])) {
		$constant_array_id = (int) $data["constant_array_id"];
		$key = trim((string) $data["key"]);
		if ($key === "") {
			fwrite(STDERR, "Missing key in --json\n");
			exit(1);
		}
		$rows = $ffm_values->select("constant_array_id", $constant_array_id);
		foreach ($rows as $row) {
			if ((int) ($row["id"] ?? 0) === (int) $data["id"]) {
				continue;
			}
			if ((string) ($row["key"] ?? "") === $key) {
				fwrite(STDERR, "Duplicate key in constant_array_id\n");
				exit(1);
			}
		}
	}
	$ffm_values->update($data);
	$out = [
	    "ok" => true,
	    "id" => $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "constant_values_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$id = (int) $data["id"];
	$ffm_values->delete($id);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_tables_list") {
	$list = $ffm_db_admin->getall("sort", SORT_ASC);
	$out = [
	    "items" => array_values($list),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_tables_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (empty($data["tb_name"])) {
		fwrite(STDERR, "Missing tb_name in --json\n");
		exit(1);
	}
	$duplicate = $ffm_db_admin->select("tb_name", (string) $data["tb_name"]);
	if (!empty($duplicate)) {
		fwrite(STDERR, "Duplicate db table: tb_name=" . (string) $data["tb_name"] . "\n");
		exit(1);
	}
	if (!isset($data["show_menu"]) || $data["show_menu"] === "") {
		$data["show_menu"] = 1;
	}
	$id = $ffm_db_admin->insert($data);
	$parent_id_field_added = cli_ensure_parent_id_field($ffm_db_admin, $ffm_db_fields_admin, (int) $id);
	cli_make_table_format($dir, $ffm_db_admin, $ffm_db_fields_admin);
	$out = [
	    "ok" => true,
	    "id" => $id,
	    "parent_id_field_added" => $parent_id_field_added,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_tables_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$ffm_db_admin->update($data);
	$parent_id_field_added = cli_ensure_parent_id_field($ffm_db_admin, $ffm_db_fields_admin, (int) $data["id"]);
	cli_make_table_format($dir, $ffm_db_admin, $ffm_db_fields_admin);
	$out = [
	    "ok" => true,
	    "id" => $data["id"],
	    "parent_id_field_added" => $parent_id_field_added,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_tables_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$ffm_db_admin->delete((int) $data["id"]);
	cli_make_table_format($dir, $ffm_db_admin, $ffm_db_fields_admin);
	$out = [
	    "ok" => true,
	    "id" => (int) $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_fields_list") {
	$items = $ffm_db_fields_admin->getall("sort", SORT_ASC);
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if ($ok && isset($data["db_id"])) {
		$target = (int) $data["db_id"];
		$items = array_values(array_filter($items, function ($row) use ($target) {
			return (int) ($row["db_id"] ?? 0) === $target;
		}));
	}
	$out = [
	    "items" => array_values($items),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_fields_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["db_id"])) {
		fwrite(STDERR, "Missing db_id in --json\n");
		exit(1);
	}
	if (empty($data["parameter_name"])) {
		fwrite(STDERR, "Missing parameter_name in --json\n");
		exit(1);
	}
	$upsert = !empty($data["upsert"]);
	unset($data["upsert"]);

	$duplicate = $ffm_db_fields_admin->select(
		["db_id", "parameter_name"],
		[(int) $data["db_id"], (string) $data["parameter_name"]],
		true,
		"AND",
		"id",
		SORT_ASC
	);
	if (!empty($duplicate)) {
		$existing = $duplicate[0];
		if (!$upsert) {
			fwrite(
				STDERR,
				"Duplicate db_fields: db_id=" . (int) $data["db_id"] . ", parameter_name=" . (string) $data["parameter_name"] . "\n"
			);
			fwrite(STDERR, "Use db_fields_edit or set upsert=1 to update existing record.\n");
			exit(1);
		}
		$update = $existing;
		foreach ($data as $k => $v) {
			$update[$k] = $v;
		}
		$update["id"] = (int) $existing["id"];
		$update = cli_normalize_db_field_payload($update);
		$ffm_db_fields_admin->update($update);
		cli_make_table_format($dir, $ffm_db_admin, $ffm_db_fields_admin);
		$out = [
		    "ok" => true,
		    "id" => (int) $existing["id"],
		    "updated" => true,
		];
		echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		exit(0);
	}

	$data = cli_normalize_db_field_payload($data);
	$id = $ffm_db_fields_admin->insert($data);
	cli_make_table_format($dir, $ffm_db_admin, $ffm_db_fields_admin);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_fields_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$data = cli_normalize_db_field_payload($data);
	$ffm_db_fields_admin->update($data);
	cli_make_table_format($dir, $ffm_db_admin, $ffm_db_fields_admin);
	$out = [
	    "ok" => true,
	    "id" => $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_fields_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	$ffm_db_fields_admin->delete((int) $data["id"]);
	cli_make_table_format($dir, $ffm_db_admin, $ffm_db_fields_admin);
	$out = [
	    "ok" => true,
	    "id" => (int) $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "screen_fields_list") {
	$list = $ffm_screen_fields_admin->getall("sort", SORT_ASC);
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$tb = $data["tb_name"] ?? null;
	$sn = $data["screen_name"] ?? null;
	if (!$tb || !$sn) {
		fwrite(STDERR, "Missing tb_name or screen_name in --json\n");
		exit(1);
	}
	$list = array_values(array_filter($list, function ($row) use ($tb, $sn) {
		return (string) ($row["tb_name"] ?? "") === (string) $tb
		    && (string) ($row["screen_name"] ?? "") === (string) $sn;
	}));
	$out = [
	    "items" => array_values($list),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "screen_fields_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (empty($data["tb_name"]) || empty($data["screen_name"])) {
		fwrite(STDERR, "Missing tb_name or screen_name in --json\n");
		exit(1);
	}
	if (empty($data["parameter_name"]) && empty($data["db_fields_id"])) {
		fwrite(STDERR, "Missing parameter_name or db_fields_id in --json\n");
		exit(1);
	}
	[$ok_resolve, $err_resolve, $data] = cli_resolve_screen_field_links($ffm_db_admin, $ffm_db_fields_admin, $data);
	if (!$ok_resolve) {
		fwrite(STDERR, $err_resolve . "\n");
		exit(1);
	}
	$upsert = !empty($data["upsert"]);
	unset($data["upsert"]);

	$duplicate = $ffm_screen_fields_admin->select(
		["tb_name", "screen_name", "parameter_name"],
		[(string) $data["tb_name"], (string) $data["screen_name"], (string) $data["parameter_name"]],
		true,
		"AND",
		"id",
		SORT_ASC
	);
	if (!empty($duplicate)) {
		$existing = $duplicate[0];
		if (!$upsert) {
			fwrite(
				STDERR,
				"Duplicate screen_fields: tb_name=" . (string) $data["tb_name"] .
				", screen_name=" . (string) $data["screen_name"] .
				", parameter_name=" . (string) $data["parameter_name"] . "\n"
			);
			fwrite(STDERR, "Use screen_fields_edit or set upsert=1 to update existing record.\n");
			exit(1);
		}
		$update = $existing;
		foreach ($data as $k => $v) {
			$update[$k] = $v;
		}
		$update["id"] = (int) $existing["id"];
		$ffm_screen_fields_admin->update($update);
		$out = [
		    "ok" => true,
		    "id" => (int) $existing["id"],
		    "updated" => true,
		];
		echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		exit(0);
	}

	$id = $ffm_screen_fields_admin->insert($data);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "screen_fields_edit") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	if (!isset($data["id"])) {
		fwrite(STDERR, "Missing id in --json\n");
		exit(1);
	}
	if (empty($data["tb_name"]) || empty($data["screen_name"])) {
		fwrite(STDERR, "Missing tb_name or screen_name in --json\n");
		exit(1);
	}
	[$ok_resolve, $err_resolve, $data] = cli_resolve_screen_field_links($ffm_db_admin, $ffm_db_fields_admin, $data);
	if (!$ok_resolve) {
		fwrite(STDERR, $err_resolve . "\n");
		exit(1);
	}
	$ffm_screen_fields_admin->update($data);
	$out = [
	    "ok" => true,
	    "id" => $data["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "screen_fields_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$tb = $data["tb_name"] ?? null;
	$sn = $data["screen_name"] ?? null;
	if (!$tb || !$sn) {
		fwrite(STDERR, "Missing tb_name or screen_name in --json\n");
		exit(1);
	}
	if (isset($data["id"])) {
		$ffm_screen_fields_admin->delete((int) $data["id"]);
	} else if (isset($data["db_fields_id"])) {
		$list = $ffm_screen_fields_admin->select(
			["tb_name", "screen_name", "db_fields_id"],
			[$tb, $sn, $data["db_fields_id"]],
			true,
			"AND",
			"sort",
			SORT_ASC
		);
		foreach ($list as $val) {
			$ffm_screen_fields_admin->delete($val["id"]);
		}
	} else {
		fwrite(STDERR, "Missing id or db_fields_id in --json\n");
		exit(1);
	}
	$out = [
	    "ok" => true,
	    "id" => isset($data["id"]) ? (int) $data["id"] : null,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "data_list") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$table = $data["table"] ?? null;
	$max = (int) ($data["max"] ?? 0);
	if (!$table || $max <= 0) {
		fwrite(STDERR, "Missing table or max in --json\n");
		exit(1);
	}
	$ffm = cli_db($dir, $table);
	$rows = $ffm->getall("id", SORT_DESC);
	if ($max > 0 && count($rows) > $max) {
		$rows = array_slice($rows, 0, $max);
	}
	$out = [
	    "items" => array_values($rows),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "data_add") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$table = $data["table"] ?? null;
	$row = $data["data"] ?? null;
	if (!$table || !is_array($row)) {
		fwrite(STDERR, "Missing table or data in --json\n");
		exit(1);
	}
	$ffm = cli_db($dir, $table);
	$id = $ffm->insert($row);
	$out = [
	    "ok" => true,
	    "id" => $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "data_update") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$table = $data["table"] ?? null;
	$row = $data["data"] ?? null;
	if (!$table || !is_array($row) || !isset($row["id"])) {
		fwrite(STDERR, "Missing table or data.id in --json\n");
		exit(1);
	}
	$ffm = cli_db($dir, $table);
	$ffm->update($row);
	$out = [
	    "ok" => true,
	    "id" => $row["id"],
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "data_delete") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$table = $data["table"] ?? null;
	$id = $data["id"] ?? null;
	if (!$table || $id === null) {
		fwrite(STDERR, "Missing table or id in --json\n");
		exit(1);
	}
	$ffm = cli_db($dir, $table);
	$ffm->delete((int) $id);
	$out = [
	    "ok" => true,
	    "id" => (int) $id,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "data_get") {
	[$ok, $err, $data] = cli_get_json_arg($argv);
	if (!$ok) {
		fwrite(STDERR, $err . "\n");
		exit(1);
	}
	$table = $data["table"] ?? null;
	$id = $data["id"] ?? null;
	if (!$table || $id === null) {
		fwrite(STDERR, "Missing table or id in --json\n");
		exit(1);
	}
	$ffm = cli_db($dir, $table);
	$row = $ffm->get((int) $id);
	$out = [
	    "item" => $row,
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

if ($command === "db_schema") {
	$db_list = $ffm_db->getall("sort", SORT_ASC);
	$db_fields_all = $ffm_db_fields->getall("sort", SORT_ASC);
	$fields_by_db_id = [];
	foreach ($db_fields_all as $f) {
		$fid = (int) ($f["db_id"] ?? 0);
		if (!isset($fields_by_db_id[$fid])) {
			$fields_by_db_id[$fid] = [];
		}
		$fields_by_db_id[$fid][] = $f;
	}

	$db_by_id = [];
	foreach ($db_list as $db) {
		$db_by_id[(int) $db["id"]] = $db;
	}

	$ca_list = $ffm_constant_array->getall();
	$ca_by_name = [];
	foreach ($ca_list as $ca) {
		$ca_by_name[$ca["array_name"]] = (int) $ca["id"];
	}
	$values_all = $ffm_values->getall("sort", SORT_ASC);
	$values_by_ca_id = [];
	foreach ($values_all as $v) {
		$cid = (int) ($v["constant_array_id"] ?? 0);
		if (!isset($values_by_ca_id[$cid])) {
			$values_by_ca_id[$cid] = [];
		}
		$values_by_ca_id[$cid][] = $v;
	}

	$relations = [];
	foreach ($db_list as $db) {
		$from_table = $db["tb_name"];

		// parent_id relation
		$parent_tb_id = (int) ($db["parent_tb_id"] ?? 0);
		if ($parent_tb_id > 0 && isset($db_by_id[$parent_tb_id])) {
			$relations[] = [
			    "from_table" => $from_table,
			    "from_field" => "parent_id",
			    "to_table" => $db_by_id[$parent_tb_id]["tb_name"],
			    "to_field" => "id",
			    "cardinality" => "many-to-one",
			];
		}

		// dropdown/checkbox with table/ relation
		$field_list = $fields_by_db_id[(int) ($db["id"] ?? 0)] ?? [];
		foreach ($field_list as $f) {
			$type = $f["type"] ?? "";
			if ($type !== "dropdown" && $type !== "checkbox") {
				continue;
			}
			$ca = (string) ($f["constant_array_name"] ?? "");
			if ($ca === "" || strpos($ca, "table/") !== 0) {
				continue;
			}
			$ex = explode("/", $ca, 2);
			$to_table = $ex[1] ?? "";
			if ($to_table === "") {
				continue;
			}
			$relations[] = [
			    "from_table" => $from_table,
			    "from_field" => $f["parameter_name"],
			    "to_table" => $to_table,
			    "to_field" => "id",
			    "cardinality" => "many-to-one",
			];
		}
	}

	$tables = [];
	foreach ($db_list as $db) {
		$db_id = (int) ($db["id"] ?? 0);
		$fields = [];

		$fields[] = [
		    "parameter_name" => "id",
		    "type" => "Number",
		];
		$fields[] = [
		    "parameter_name" => "_id_enc",
		    "type" => "Text",
		];

		$field_list = $fields_by_db_id[$db_id] ?? [];
		foreach ($field_list as $f) {
			$af = [
			    "parameter_name" => $f["parameter_name"],
			    "parameter_title" => $f["parameter_title"],
			    "type" => $f["type"],
			];

			if ($f["validation"] == 1) {
				$af["required"] = true;
			}
			if (!empty($f["default_value"])) {
				$af["default_value"] = $f["default_value"];
			}
			if (!empty($f["length"])) {
				$af["length"] = (int) $f["length"];
			}
			if (!empty($f["parameter_description"])) {
				$af["description"] = $f["parameter_description"];
			}
			if (!empty($f["constant_array_name"])) {
				$af["constant_array_name"] = $f["constant_array_name"];
				$ca_name = (string) $f["constant_array_name"];
				if (isset($ca_by_name[$ca_name])) {
					$cid = $ca_by_name[$ca_name];
					$opts = [];
					foreach ($values_by_ca_id[$cid] ?? [] as $v) {
						$opts[] = [
						    "key" => $v["key"],
						    "value" => $v["value"],
						    "color" => $v["color"],
						];
					}
					$af["options"] = $opts;
				}
			}

			$fields[] = $af;
		}

		$table = [
		    "table_name" => $db["tb_name"],
		    "menu_name" => $db["menu_name"] ?? "",
		    "description" => $db["description"] ?? "",
		    "parent_tb_id" => (int) ($db["parent_tb_id"] ?? 0),
		    "fields" => $fields,
		];

		$tables[] = $table;
	}

	$out = [
	    "tables" => $tables,
	    "relations" => $relations,
	];

	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit(0);
}

fwrite(STDERR, "Usage: php cli.php db_schema | setting_get | setting_edit --json='{}' | app_call --json='{}' | app_check --json='{}' | db_additionals_list | db_additionals_add --json='{}' | db_additionals_edit --json='{}' | db_additionals_delete --json='{}' | db_additionals_generate --json='{\"id\":1}' | db_tables_list | db_tables_add --json='{}' | db_tables_edit --json='{}' | db_tables_delete --json='{}' | db_fields_list [--json='{\"db_id\":1}'] | db_fields_add --json='{}' | db_fields_edit --json='{}' | db_fields_delete --json='{}' | screen_fields_list --json='{\"tb_name\":\"xxx\",\"screen_name\":\"list\"}' | screen_fields_add --json='{}' | screen_fields_edit --json='{}' | screen_fields_delete --json='{}' | cron_list [--json='{\"id\":1}'] | cron_add --json='{}' | cron_edit --json='{}' | cron_delete --json='{}' | webhook_rule_list [--json='{\"id\":1}'] | webhook_rule_add --json='{}' | webhook_rule_edit --json='{}' | webhook_rule_delete --json='{\"id\":1}' | embed_app_list [--json='{\"id\":1}'] | embed_app_add --json='{}' | embed_app_edit --json='{}' | embed_app_delete --json='{\"id\":1}' | email_format_list [--json='{\"id\":1}'] | email_format_get --json='{\"id\":1}' | email_format_add --json='{}' | email_format_edit --json='{}' | email_format_delete --json='{\"id\":1}' | email_format_validate --json='{\"id\":1}'\n");
fwrite(STDERR, "app_call/app_check: windowcodeを固定する場合、session_id未指定時はwindowcode由来の有効なsession_idを自動使用します。session_idに使える文字は英数字・'-'・','です。\n");
exit(1);
