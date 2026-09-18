<?php

// Keep PHP diagnostics in server logs, never in HTTP responses.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);
mb_language("Japanese");
mb_internal_encoding("UTF-8");

include("lib/fixed_file_manager/fixed_file_manager.php");
include("lib/Dirs.php");
include("interface/Controller.php");
include("lib/Controller_class.php");
include("lib/pdfmaker/function_image.php");
include("lib/pdfmaker/pdfmaker_class.php");
include("lib/pdfmaker/macFileNameNormalizer.php");

function fbp_load_error_report_level(Dirs $dir): string {
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

function fbp_register_error_handler(string $level): void {
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

function fbp_detect_appcode(): string {
	$host = (string) ($_SERVER["HTTP_HOST"] ?? "");
	$url_ex = explode(".", $host);
	$url_ex2 = explode("-", $url_ex[0] ?? "", 2);
	if (isset($url_ex2[1])) {
		return (string) $url_ex2[1];
	}
	return fbp_detect_appcode_from_path();
}

function fbp_detect_appcode_from_path(): string {
	$paths = [
		(string) parse_url((string) ($_SERVER["REQUEST_URI"] ?? ""), PHP_URL_PATH),
		(string) ($_SERVER["SCRIPT_NAME"] ?? ""),
		(string) ($_SERVER["PHP_SELF"] ?? ""),
	];
	foreach ($paths as $path) {
		foreach (explode("/", trim($path, "/")) as $part) {
			$part = rawurldecode($part);
			if (strpos($part, "app-") === 0) {
				return $part;
			}
		}
	}
	return "";
}

function fbp_get_windowcode(): string {
	return (string) ($_COOKIE["windowID"] ?? $_GET["windowID"] ?? $_POST["windowID"] ?? "");
}

function fbp_resolve_pdf_request_context(Controller_class $ctl): array {
	$source_class = (string) $ctl->get_session("pdf_source_class");
	$source_function = (string) $ctl->get_session("pdf_source_function");
	$cmd = (string) ($_GET["cmd"] ?? "");
	$default_function = ($cmd === "download") ? "download" : "page";
	if ($source_class === "") {
		$source_class = "apppdf";
	}
	if ($source_function === "") {
		$source_function = $default_function;
	}
	return [
		"class" => $source_class,
		"function" => $source_function,
	];
}

function show_error($report_result = [], $public_url = "") {
	$html = build_system_error_html($report_result, $public_url);
	header("HTTP/1.1 404 ");
	echo $html;
	exit;
}

function build_system_error_html($report_result = [], $public_url = "") {
	$configured = !empty($report_result["configured"]);
	$reported = !empty($report_result["reported"]);
	$report_id = isset($report_result["id"]) ? (int) $report_result["id"] : null;
	$dialog_public_url = (string) ($report_result["public_url"] ?? "");
	if ($dialog_public_url === "") {
		$dialog_public_url = $public_url;
	}
	$detail = system_error_t("system_error.detail.failed");
	if (!$configured) {
		$detail = system_error_t("system_error.detail.unconfigured");
	}
	if ($reported) {
		$detail = system_error_t("system_error.detail.reported");
	}
	if ($configured) {
		$detail .= system_error_t("system_error.detail.tail");
	}

	// Only public status information belongs in this response.
	$html = "<div class=\"error\" style=\"line-height:1.8;padding:12px 8px 4px;max-width:800px;margin:0 auto;margin-top:20px;\">";
	$html .= "<div style=\"padding-top:18px;\">";
	$html .= "<div style=\"display:flex;align-items:flex-start;gap:28px;\">";
	$html .= "<div style=\"flex:0 0 220px;padding-left:8px;\">";
	$html .= "<img src=\"css/images/server_error.png\" alt=\"system error\" style=\"display:block;width:200px;height:auto;float:right;\">";
	$html .= "</div>";
	$html .= "<div style=\"flex:1 1 auto;padding-right:18px;\">";
	$html .= "<p style=\"margin:0 0 20px;color:#d92d20;font-size:14px;font-weight:700;line-height:1.75;\">" . htmlspecialchars($detail) . "</p>";
	$html .= "<div style=\"display:flex;align-items:center;justify-content:space-between;gap:20px;\">";
	if ($configured && $report_id !== null) {
		$html .= "<div>";
		$html .= "<span style=\"display:inline-block;font-size:44px;font-weight:700;line-height:1;color:#000;vertical-align:middle;\">#" . $report_id . "</span>";
		$html .= "</div>";
	}
	if ($configured && $dialog_public_url !== "") {
		$link = htmlspecialchars($dialog_public_url);
		$html .= "<div style=\"clear:both;text-align:center;\">";
		$html .= "<a href=\"" . $link . "\" target=\"_blank\" rel=\"noopener noreferrer\" style=\"display:inline-block;padding:14px 30px;border-radius:999px;background:#bf2518;color:#fff;text-decoration:none;font-size:16px;font-weight:700;white-space:nowrap;\">" . htmlspecialchars(system_error_t("system_error.progress_link")) . "</a>";
		$html .= "</div>";
	}
	$html .= "</div>";
	$html .= "</div>";
	$html .= "</div>";
	$html .= "</div>";
	$html .= "</div>";
	return $html;
}

function get_server_error_public_url() {
	return trim((string) ($_SERVER["FBP_SERVER_ERROR_PUBLIC_URL"] ?? getenv("FBP_SERVER_ERROR_PUBLIC_URL")));
}

function system_error_t($key, $params = []) {
	static $messages_cache = [];
	$lang = (string) ($GLOBALS["fbp_system_error_lang"] ?? "ja");
	if (!isset($messages_cache[$lang])) {
		$file = dirname(__FILE__) . "/app/lang/json/lang_" . $lang . ".json";
		if (!is_file($file)) {
			$file = dirname(__FILE__) . "/app/lang/json/lang_ja.json";
		}
		$json = @file_get_contents($file);
		$messages_cache[$lang] = is_string($json) ? (json_decode($json, true) ?: []) : [];
	}
	$text = $messages_cache[$lang][$key] ?? null;
	if (!is_string($text) || $text === "") {
		$fallback_file = dirname(__FILE__) . "/app/lang/json/lang_ja.json";
		if (!isset($messages_cache["ja"])) {
			$json = @file_get_contents($fallback_file);
			$messages_cache["ja"] = is_string($json) ? (json_decode($json, true) ?: []) : [];
		}
		$text = $messages_cache["ja"][$key] ?? $key;
	}
	foreach ($params as $name => $value) {
		$text = str_replace("{" . $name . "}", (string) $value, $text);
	}
	return $text;
}

try {
	// キャッシュを防ぐためにURLを変更してリダイレクト
	if (empty($_GET["time"])) {
		header("Location: apppdf.php?time=" . strtotime("now"));
		return;
	}

	if (isset($_REQUEST[session_name()])) {
		session_id($_REQUEST[session_name()]);
	}
	session_start();

	$dir = new Dirs();
	fbp_register_error_handler(fbp_load_error_report_level($dir));

	$ctl = new Controller_class();
	$windowcode = fbp_get_windowcode();
	if ($windowcode !== "") {
		$ctl->set_windowcode($windowcode);
		$ctl->set_session("appcode", fbp_detect_appcode());
	}

	$request_context = fbp_resolve_pdf_request_context($ctl);
	if (empty($_GET["class"]) && empty($_POST["class"])) {
		$_GET["class"] = $request_context["class"];
	}
	if (empty($_GET["function"]) && empty($_POST["function"])) {
		$_GET["function"] = $request_context["function"];
	}

	$mac_normalizer = new macFileNameNormalizer();
	$txt = $_SESSION["pdf_text"];
	$pdf_filename = $_SESSION["pdf_filename"];
	if (!endsWith($pdf_filename, ".pdf")) {
		$pdf_filename .= ".pdf";
	}
	$imgdir = $_SESSION["pdf_imgdir"];

	// 文字化け対応
	$txt = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $txt);
	$txt = $mac_normalizer->normalizeUtf8MacFileName($txt);
	$pdf_filename = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $pdf_filename);

	$pdfmaker = new pdfmaker_class();

	if (($_GET["cmd"] ?? "") == "download") {
		if (is_smartphone() && !is_line_inapp_browser()) {
			$pdfmaker->makepdf($txt, $imgdir, $pdf_filename, "D");
		} else {
			$pdfmaker->makepdf($txt, $imgdir, $pdf_filename);
		}
	} else {
		if (is_smartphone()) {
			$url = 'apppdf.php?cmd=download&time=' . time();
			$url = url_with_sid($url);
			$html = file_get_contents(dirname(__FILE__) . "/lib/pdfmaker/downloadpage.tpl");
			$html = str_replace('{$url}', $url, $html);
			echo $html;
		} else {
			$pdfmaker->makepdf($txt, $imgdir, $pdf_filename);
		}
	}
} catch (Throwable $e) {
	$report_result = [
		"configured" => false,
		"reported" => false,
		"id" => null,
		"public_url" => "",
	];
	if (isset($ctl) && $ctl instanceof Controller_class) {
		$report_result = $ctl->report_server_error($e);
	}
	show_error($report_result, get_server_error_public_url());
}

function url_with_sid(string $url): string {
	$sidParam = session_name() . '=' . rawurlencode(session_id());
	$hasQuery = (parse_url($url, PHP_URL_QUERY) !== null);
	return $url . ($hasQuery ? '&' : '?') . $sidParam;
}

function is_line_inapp_browser(string $ua = null): bool {
	if (is_null($ua)) {
		$ua = $_SERVER['HTTP_USER_AGENT'];
	}

	$ua_l = strtolower($ua);

	if (strpos($ua_l, ' line/') !== false) {
		return true;
	}

	if (strpos($ua_l, 'line/') !== false) {
		return true;
	}

	return false;
}

function is_smartphone($ua = null) {
	if (is_null($ua)) {
		$ua = $_SERVER['HTTP_USER_AGENT'];
	}

	if (preg_match('/iPhone|iPod|iPad|Android/ui', $ua)) {
		return true;
	} else {
		return false;
	}
}

function endsWith($haystack, $needle) {
	$length = strlen($needle);
	if ($length == 0) {
		return true;
	}

	return (substr($haystack, -$length) === $needle);
}
