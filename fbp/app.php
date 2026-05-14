<?php

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);
mb_internal_encoding("UTF-8");

// ブラウザによる途中中断禁止
ignore_user_abort(true);

if (isset($_REQUEST[session_name()])) {
	$requestedSessionId = (string) $_REQUEST[session_name()];
	if ($requestedSessionId !== '' && preg_match('/^[A-Za-z0-9,-]+$/', $requestedSessionId)) {
		session_id($requestedSessionId);
	}
}

// Session Start
session_start();

// アップロードデータ容量のチェック
if(isUploadMaxFilesizeExceeded() || isPostMaxSizeExceeded()) {
	$error = [
	    "error" => '<div style="background:red;border-radius:10px;text-align:center;color:#FFF;padding:20px;">' . 
	    '<span class="lang">The request payload exceeds the server\'s maximum allowed size.</span>'
	    . "<br /> post_max_size: " 
	    . ini_get('post_max_size') . " upload_max_filesize: " . ini_get("upload_max_filesize")
	    . '</div>',
	];
	echo json_encode($error);
	return;
}

include "lib/SmartyBootstrap.php";
fbp_include_smarty();
$smarty = new Smarty();

include("lib/fixed_file_manager/fixed_file_manager.php");
include("lib/ValueFormatter.php");
include("interface/Controller.php");
include("lib/Controller_class.php");
include("lib/I18nSimple.php");
include("interface/CodegenActionInterface.php");
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
	include('lib_ext/stream_filter/Stream_Filter_Mbstring7.php');
}else{
	include('lib_ext/stream_filter/Stream_Filter_Mbstring8.php');
}
include("lib/Dirs.php");
include("interface/vimeo/Vimeo.php");
include("lib/Vimeo_class.php");
include("interface/linebot/linebot.php");
include("lib/linebot/Linebot_class.php");
include("lib/pdfmaker/pdfmaker_class.php");

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

header("Cache-Control:no-cache,no-store,must-revalidate,max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma:no-cache");

// デフォルトでエスケープ（エスケープを解除するには、{$var nofilter}のようにする
// テキストの改行は {$var|escape|nl2br nofilter}
$smarty->escape_html  = true;

//エラー設定
$smarty->error_reporting =E_ALL & ~E_NOTICE & ~E_WARNING;

// Register original Smarty plugins without addPluginsDir() deprecation.
fbp_register_smarty_plugins($smarty, dirname(__FILE__) . "/lib/smarty_plugins_org/");
$smarty->registerPlugin('modifier', 'is_numeric', 'is_numeric');

//-----------------------------------------------------
// DIRS
//-----------------------------------------------------
$dir = new Dirs();
fbp_register_error_handler(fbp_load_error_report_level($dir));

//------------
// windowcodeのパラメーターのあるものは除く（過去の仕様）
//------------
check_url_windowcode();

//-------------
// appcodeのセット
//-------------
$url_ex = explode(".",$_SERVER['HTTP_HOST']);
$url_ex2 = explode("-",$url_ex[0],2);
if($url_ex2[0] == "test" || $url_ex2[0] == "192"){
	$testserver = true;
}else{
	$testserver = false;
}
if(isset($url_ex2[1])){
	$appcode = $url_ex2[1];
}else{
	$appcode = fbp_detect_appcode_from_path();
}
$smarty->assign("appcode",$appcode);
$smarty->assign("hostname",$url_ex[0]);

//---------------------
// class と function の取得
//---------------------
if(isset($_GET["class"])){
	$class = $_GET["class"];
}else{
	$class="";
}
if(isset($_GET["function"])){
	$function = $_GET["function"];
}else{
	$function="";
}
if ($_SERVER["REQUEST_METHOD"] == "GET") {
	$class = $_GET["class"] ?? "";
	if(!empty($_GET["function"])){
		$function = $_GET["function"];
	}else{
		$function = "page";
	}
}else{
	if(empty($class)){
		$class = $_POST["class"] ?? "";
	}
	if(empty($function)){
		$function = $_POST["function"] ?? "";
	}
}
if(empty($class) || empty($function)){
	$class = "login";
	$function = "page";
}

$smarty->assign("class",$class);
$smarty->assign("css_class",$class); // Default

// ベースのテンプレートディレクトリ指定
$base_template_dir = dirname(__FILE__) . "/Templates";
$smarty->assign("base_template_dir",$base_template_dir);

//画像強制更新用タイムスタンプ
$smarty->assign("timestamp", strtotime("now"));

// SETTING
$setting_fmt_dir = $dir->appdir_fw . "/setting/fmt";
$setting_data_dir = $dir->datadir  . "/setting/";
$ffm_setting = new fixed_file_manager("setting", $setting_data_dir,$setting_fmt_dir);
$setting = $ffm_setting->get(1);
if(empty($setting)){
	$d = array();
	$d["force_testmode"] = 1;
	$ffm_setting->insert($d);
	$setting = $ffm_setting->get(1);
}
if(empty($setting["secret"])){
	$setting["secret"] = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz!@#$%^&*()-_|{}[];:<>?/'), 0, 18);
	$ffm_setting->update($setting);	
}
if(empty($setting["iv"])){
	$setting["iv"] = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz!@#$%^&*()-_|{}[];:<>?/'), 0, 16);
	$ffm_setting->update($setting);
}
if (empty($setting["timezone"])){
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
date_default_timezone_set($setting["timezone"]);

$ffm_setting->close();  //この後使わないのでクローズ


// 強制テストモード
if($setting["force_testmode"] == 1){
	$testserver = true;
}

//Viewport デフォルト
if(empty($setting["viewport_public"])){
	$smarty->assign("viewport_public","width=600,viewport-fit=cover");
}else{
	$smarty->assign("viewport_public",$setting["viewport_public"]);
}
if(empty($setting["viewport_base"])){
	$smarty->assign("viewport_base","width=device-width");
}else{
	$smarty->assign("viewport_base",$setting["viewport_base"]);
}

// Smartyにアサイン
$smarty->assign("testserver",$testserver);
$framework_language_code = I18nSimple::get_language_code_from_setting($setting);
$locale_code = I18nSimple::get_locale_code_from_setting($setting);
$legacy_lang_default = I18nSimple::get_legacy_lang_code_from_setting($setting);
$GLOBALS["fbp_system_error_lang"] = $framework_language_code;
$smarty->assign("lang", $framework_language_code);
$smarty->assign("arr_lang",["en"=>"English","jp"=>"Japanese"]);
$smarty->assign("framework_language_code", $framework_language_code);
$smarty->assign("locale_code", $locale_code);
$smarty->assign("legacy_lang_default", $legacy_lang_default);
$smarty->assign("setting",$setting);

if (!startsWith($class, "_") && !is_appcon_request() && !class_file_exists($class, $dir)) {
	respond_not_found();
	exit;
}

$ctl = null;


try{
	//コントローラーを作成
	if(startsWith($class, "_")){
		$ctl = new Controller_class();
	}else{
		// クラスファイルのディレクトリ決定
		$ctl = new Controller_class($class,$smarty);
		
		// smartyにControllerをアサインしておく（自作プラグインで使用する）
		$smarty->assign("_ctl",$ctl);
	}

	// Windowcodeの生成
	if(!empty($_COOKIE["windowID"])){
		$windowcode = $_COOKIE["windowID"];
	}else if (!empty($_REQUEST["_windowcode"]) && preg_match('/^WID_[A-Za-z0-9._-]+$/', (string) $_REQUEST["_windowcode"])) {
		$windowcode = (string) $_REQUEST["_windowcode"];
	}else{
		$windowcode = uniqid("WID_");
		$ctl->assign("new_windowID",$windowcode);
	}
	$ctl->set_windowcode($windowcode);
	$smarty->assign("windowcode",$windowcode);

	// 新しいタブが開かれた場合は、古いセッションのデータをコピーする
	// Public側でページが変わったときにセッションデータの引き継ぎが必要
	if(!empty($_COOKIE["old_windowID"])){
		$old_windowID = $_COOKIE["old_windowID"];
		// Copy cookie
		if(!empty($_SESSION[$old_windowID])){
			$old_session = $_SESSION[$old_windowID];
			if(is_array($old_session)){
				foreach($old_session as $key=>$val){
					$ctl->set_session($key, $val);
				}
			}
		}
		// Remove cookie;
		app_setcookie("old_windowID", "", time() - 3600);
		unset($_COOKIE["old_windowID"]);
	}
	
	$ctl->set_session("class",$class);
	$ctl->set_session("appcode",$appcode);
	$ctl->set_session("testserver",$testserver);
	$ctl->set_session("setting",$setting);
	$ctl->set_check_login(true); //デフォルトを設定
	$ctl->set_called_function($function);
	$ctl->set_called_parameters();
	$ctl->set_userdir($dir->appdir_user);
	$ctl->assign("ctl",$ctl);

	// 強制Display(ajaxからの呼び出しで $ctl->display() を使った場合の動作
	$display_html = $ctl->get_session("_DISPLAY");
	if(empty($display_html) && $class == "public_pages" && !empty($_SESSION["_PUBLIC_DISPLAY"])){
		$display_html = $_SESSION["_PUBLIC_DISPLAY"];
		$_SESSION["_PUBLIC_DISPLAY"] = null;
	}
	if(!empty($display_html)){
		$ctl->set_session("_DISPLAY",null);
		echo $display_html;
		exit;
	}
	if($class=="_DISPLAY" && $function="_ARR"){
		$arr = $_SESSION["_DISPLAY_ARR"];
		if($arr != null){
			echo json_encode($arr);
			unset($_SESSION["_DISPLAY_ARR"]);
		}
		exit;
	}

	// Vimeo thumbnail
	if($class=="_VIMEO" && $function="_THUMBNAIL"){
		$vimeo_id = $ctl->POST("vimeo_id");
		$url = $ctl->get_vimeo_thumbnail($vimeo_id);
		echo json_encode(["url"=>$url]);
		exit;
	}

	// 選択肢の自動セット（Table情報以外）
	$constant_names = $ctl->get_all_constant_array_names(false,false);
	$smarty->assign("constant_array_name",$constant_names);
	foreach ($constant_names as $key => $arr_name) {
	    $constant_values = $ctl->get_constant_array($arr_name,false);
	    $smarty->assign($arr_name , $constant_values );

	    $constant_colors = $ctl->get_constant_array_color($arr_name);
	    $smarty->assign($arr_name. "_colors" , $constant_colors );
	}
	
	// 設定の読み込みなどで使用したDBを開放
	$ctl->close_all_db();

	//クラスを読み込み
	$appobj = getClassObject($ctl,$class,$dir);
	if($appobj == null){
		if (is_appcon_request()) {
			$ctl->report_server_error(new Exception(
				"Class \"$class\" was not found."
			));
			$ctl->res();
		}else{
			respond_not_found();
		}
		exit;
	}

	//init関数を実行（過去互換）
	if(method_exists($appobj,"init")){
		$appobj->init($ctl);
	}

	// SQUAREの読み込み           <--自動で読み込むように変更2024.8.19
	//if($ctl->get_square()){
	//	include("mysquare.php");
	//}

	// コンストラクタ内で停止が指示された場合
	if($ctl->flg_stop_executing_function){
		if(!$ctl->display_flg){
			$ctl->res();
		}
		$ctl->assign("add_css_public",$ctl->add_css_public);
		exit;
	}

	//$user_type_opt_colors[$user.type]
	//ログインチェック
	if($ctl->get_check_login()){
		// ログインが必要

		if(!$ctl->get_session("login")){
			if($_SERVER["REQUEST_METHOD"] == "GET"){
				header("Location: app.php?class=login");
			}else{
				if($_POST["class"] == "lang"){
					$arr = array();
					echo json_encode($arr);
				}else{
					$arr = ["location"=>"app.php?class=login"];
					echo json_encode($arr);
				}
			}
		}else{
			//ログインが必要なクラスを実行
			if(method_exists($appobj,$function)){
				if (!$ctl->authorize_management_access((string) $class, (string) $function)) {
					$ctl->deny_forbidden_access();
				}
				$appobj->$function($ctl);
			}else{
				if (is_appcon_request()) {
					$ctl->report_server_error(new Exception(
						"Class \"$class\" does not have a function \"$function\"."
					));
				}else{
					respond_not_found();
					exit;
				}
			}
			if($ctl->stop_res == false){
				$ctl->res();
			}
		}

	}else{
		//ログイン不要の場合
		if(method_exists($appobj, $function)){
			$appobj->$function($ctl);
		}else{
			if (is_appcon_request()) {
				$ctl->report_server_error(new Exception(
					"Class \"$class\" does not have a function \"$function\"."
				));
			}else{
				respond_not_found();
				exit;
			}
		}
		if(!$ctl->display_flg){
			$ctl->res();
		}
	}
	
	exit;
	
}catch(Throwable $e){
	$report_result = [
		"configured" => false,
		"reported" => false,
		"id" => null,
		"public_url" => "",
	];
	if (isset($ctl) && $ctl instanceof Controller_class) {
		$report_result = $ctl->report_server_error($e);
	}else{
		$report_result = report_bootstrap_error($e, $class, $function);
	}

	$error = format_exception_for_display($e);
	$error_text = format_exception_for_text($e);
	show_error($error, $report_result, get_server_error_public_url(), $error_text);
}

function show_error($error, $report_result = [], $public_url = "", $error_text = ""){
	$html = build_system_error_html($error, $report_result, $public_url, $error_text);
	if(($_POST["_call_from"] ?? "") == "appcon"){
		$md = [];
		$md["dialog_name"] = system_error_t("system_error.dialog_title");
		$md["html"] = $html;
		$md["title"] = system_error_t("system_error.dialog_title");
		$md["width"] = 800;
		//$md["testserver"] = $ctl->get_session("testserver");
		$md["post"] = $_POST;
		$md["multi_dialog_zindex"] = $_POST["multi_dialog_zindex"] ?? null;

		$mdset[] = $md;
		$json = json_encode(["multi_dialog"=>$mdset]);
		echo $json;
		exit;
	}else{
		header("HTTP/1.1 404 ");
		echo $html;
		exit;
	}	
}

function build_system_error_html($error, $report_result = [], $public_url = "", $error_text = "") {
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

	$text = $error_text !== "" ? $error_text : trim(strip_tags($error));
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
	$html .= "<div style=\"margin-top:" . ($configured ? "20px" : "6px") . ";\">";
	if ($configured) {
		$toggle_show = htmlspecialchars(system_error_t("system_error.detail_toggle_show"));
		$toggle_hide = htmlspecialchars(system_error_t("system_error.detail_toggle_hide"));
		$html .= "<button type=\"button\" onclick=\"var box=this.nextElementSibling; if(box){ var open=(box.style.display==='block'); box.style.display=open?'none':'block'; this.innerText=open?'".$toggle_show."':'".$toggle_hide."'; }\" style=\"padding:0;border:none;background:none;color:#475467;font-size:12px;cursor:pointer;text-decoration:underline;\">" . $toggle_show . "</button>";
		$html .= "<textarea readonly style=\"display:none;width:100%;min-height:180px;margin-top:12px;font-size:10px;line-height:1.5;box-sizing:border-box;\">" . htmlspecialchars($text) . "</textarea>";
	} else {
		$html .= "<textarea readonly style=\"display:block;width:100%;min-height:180px;margin-top:0;font-size:10px;line-height:1.5;box-sizing:border-box;\">" . htmlspecialchars($text) . "</textarea>";
	}
	$html .= "</div>";
	$html .= "</div>";
	$html .= "</div>";
	$html .= "</div>";
	$html .= "</div>";
	return $html;
}

function format_exception_for_display(Throwable $e) {
	$trace = $e->getTraceAsString();
	$trace_lines = explode("\n", $trace);
	$formatted_trace = "";

	foreach ($trace_lines as $line) {
		$formatted_trace .= "<p style=\"margin-top:10px;\">" . htmlspecialchars($line) . "</p>";
	}

	$message = htmlspecialchars($e->getMessage()) . "<br />";
	$message .= "<p><strong>" . htmlspecialchars(get_class($e)) . "</strong></p>";
	$message .= "<p>" . htmlspecialchars($e->getFile()) . ":" . (int) $e->getLine() . "</p>";
	return $message . $formatted_trace;
}

function format_exception_for_text(Throwable $e) {
	$text = (string) $e->getMessage() . "\n";
	$text .= get_class($e) . "\n";
	$text .= $e->getFile() . ":" . (int) $e->getLine() . "\n";
	$text .= $e->getTraceAsString();
	return trim($text);
}

function report_bootstrap_error(Throwable $e, $class, $function) {
	$report_url = trim((string) ($_SERVER["FBP_SERVER_ERROR_REPORT_URL"] ?? getenv("FBP_SERVER_ERROR_REPORT_URL")));
	$api_key = trim((string) ($_SERVER["FBP_SERVER_ERROR_API_KEY"] ?? getenv("FBP_SERVER_ERROR_API_KEY")));
	$api_secret = trim((string) ($_SERVER["FBP_SERVER_ERROR_API_SECRET"] ?? getenv("FBP_SERVER_ERROR_API_SECRET")));
	if ($report_url === "" || $api_key === "" || $api_secret === "") {
		return [
			"configured" => false,
			"reported" => false,
			"id" => null,
			"public_url" => "",
		];
	}
	$request_class = (string) ($_GET["class"] ?? $_POST["class"] ?? "");
	$request_function = (string) ($_GET["function"] ?? $_POST["function"] ?? "");
	if ($request_class === "" || $request_function === "") {
		return [
			"configured" => true,
			"reported" => false,
			"id" => null,
			"public_url" => "",
		];
	}

	$payload = [
		"occurred_at" => date("Y-m-d H:i:s"),
		"app_name" => basename(dirname(__FILE__)),
		"app_code" => "",
		"http_host" => (string) ($_SERVER["HTTP_HOST"] ?? ""),
		"request_uri" => (string) ($_SERVER["REQUEST_URI"] ?? ""),
		"request_method" => (string) ($_SERVER["REQUEST_METHOD"] ?? ""),
		"class_name" => $request_class,
		"function_name" => $request_function,
		"exception_class" => get_class($e),
		"message" => (string) $e->getMessage(),
		"file_path" => (string) $e->getFile(),
		"line_no" => (int) $e->getLine(),
		"trace_text" => (string) $e->getTraceAsString(),
		"post" => $_POST,
		"get" => $_GET,
		"session_user_id" => "",
		"session_login_id" => "",
		"remote_addr" => (string) ($_SERVER["REMOTE_ADDR"] ?? ""),
		"user_agent" => (string) ($_SERVER["HTTP_USER_AGENT"] ?? ""),
	];
	$payload["error_hash"] = hash("sha256", implode("\n", [
		(string) $payload["app_name"],
		(string) $payload["class_name"],
		(string) $payload["function_name"],
		(string) $payload["exception_class"],
		(string) $payload["message"],
		(string) $payload["file_path"],
		(string) $payload["line_no"],
	]));

	$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	if ($json === false) {
		return [
			"configured" => true,
			"reported" => false,
			"id" => null,
			"public_url" => "",
		];
	}

	$api_ts = (string) time();
	$path = (string) parse_url($report_url, PHP_URL_PATH);
	$query = (string) parse_url($report_url, PHP_URL_QUERY);
	$canonical = "POST\n" . $path . "\n" . $query . "\n" . $api_ts;
	$api_sign = hash_hmac("sha256", $canonical, $api_secret);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $report_url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($curl, CURLOPT_TIMEOUT, 3);
	curl_setopt($curl, CURLOPT_HTTPHEADER, [
		"Content-Type: application/json",
		"Content-Length: " . strlen($json),
		"X-API-KEY: " . $api_key,
		"X-API-TS: " . $api_ts,
		"X-API-SIGN: " . $api_sign,
		"X-FBP-ERROR-REPORT: 1",
	]);
	$host = (string) parse_url($report_url, PHP_URL_HOST);
	if ($host === "localhost" || $host === "127.0.0.1") {
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
	}
		$response = curl_exec($curl);
		$response_data = json_decode((string) $response, true);
	return [
		"configured" => true,
		"reported" => is_array($response_data) && !empty($response_data["ok"]),
		"id" => isset($response_data["id"]) ? (int) $response_data["id"] : null,
		"public_url" => is_array($response_data) ? (string) ($response_data["public_url"] ?? "") : "",
	];
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


function getClassObject(Controller $ctl,$class,Dirs $dir){
	
	//クラスを動的読み出し
	try{
		$classfile = $dir->get_class_dir($class) . "/$class.php";
	}catch(Exception $e){
		return null;
	}

	include_once($classfile);

	// リフレクションクラスのインスタンスを作成
	$reflectionClass = new ReflectionClass($class);

	// コンストラクタを取得
	$constructor = $reflectionClass->getConstructor();

	// コンストラクタが存在するかチェック
	if ($constructor) {
	    // コンストラクタのパラメータを取得
	    $params = $constructor->getParameters();

	    // パラメータがあるかチェック
	    if (count($params) > 0) {
		// パラメータがある場合
		$appobj = new $class($ctl);
	    } else {
		// パラメータがない場合
		$appobj = new $class;
	    }
	} else {
	    // コンストラクタが存在しない場合
	    $appobj = new $class;
	}

	return $appobj;

}

function is_appcon_request(): bool {
	return (($_POST["_call_from"] ?? "") === "appcon");
}

function class_file_exists(string $class, Dirs $dir): bool {
	try {
		$dir->get_class_dir($class);
		return true;
	} catch (Throwable $e) {
		return false;
	}
}

function respond_not_found(): void {
	header("HTTP/1.1 404 Not Found");
	header("Content-Type: text/plain; charset=UTF-8");
	echo "Not Found";
}

function check_url_windowcode(){
	// 現在のURLを取得
	$current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	// URLのクエリパラメータを解析
	$parsed_url = parse_url($current_url);
	parse_str($parsed_url['query'] ?? '', $query_params);

	// 'windowcode'パラメータが存在するか確認
	if (isset($query_params['windowcode'])) {
	    // 404エラーヘッダーを送信
	    header("HTTP/1.1 404 Not Found");

	    // 'windowcode'パラメータを取り除いたURLを構築
	    unset($query_params['windowcode']);
	    $new_query = http_build_query($query_params);
	    $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'];
	    if (!empty($new_query)) {
		$new_url .= '?' . $new_query;
	    }

	    // リダイレクト
	    header("Location: $new_url");
	    exit();
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

function cookie_path(){
	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
	$dir = rtrim(dirname($path), '/');   // 例: /miclub/fbp  or  /miclub  or ''
	$dir = preg_replace('#/fbp$#', '', $dir);
	$cookiePath = ($dir === '' || $dir === '/') ? '/' : $dir . '/';
	return $cookiePath;
}

function app_setcookie(string $name, string $value, int $expires): bool {
	$isSecure = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
	$options = [
		"expires" => $expires,
		"path" => cookie_path(),
		"secure" => $isSecure,
		"httponly" => true,
		"samesite" => "Lax",
	];
	return setcookie($name, $value, $options);
}

function iniSizeToBytes($val) {
    $val = trim($val);
    $last = strtolower(substr($val, -1));
    $num  = (int)$val;

    switch ($last) {
        case 'g':
            $num *= 1024;
            // no break
        case 'm':
            $num *= 1024;
            // no break
        case 'k':
            $num *= 1024;
    }
    return $num;
}

function isPostMaxSizeExceeded() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength <= 0) {
        return false;
    }

    $limit = iniSizeToBytes(ini_get('post_max_size'));

    // POST なのに $_POST / $_FILES が空＋Content-Length が post_max_size を超えている
    if ($contentLength > $limit && empty($_POST) && empty($_FILES)) {
        return true;
    }

    return false;
}

function isUploadMaxFilesizeExceeded(): bool {
    foreach ($_FILES as $info) {

        // multiple の場合
        if (is_array($info['error'])) {
            foreach ($info['error'] as $err) {
                if ($err === UPLOAD_ERR_INI_SIZE) {
                    return true;
                }
            }
        } else {
            // 単一ファイル
            if ($info['error'] === UPLOAD_ERR_INI_SIZE) {
                return true;
            }
        }
    }
    return false;
}
