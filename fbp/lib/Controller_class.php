<?php

class Controller_class implements Controller {

	private $class;
	private $userdir;
	private $template_dir;
	private $dbarr;
	public $smarty;
	private $arr; //レスポンス用
	private $flg_check_login;
	private $assign_list;
	private $debug_obj;
	private $flg_square = false;
	private $square_application_id;
	private $square_access_token;
	private $square_location_id;
	private $windowcode;
	public $add_css_public;
	private $node_login = false;
	private $called_function;
	public $display_flg = false;
	private $called_parameters;
	public $dirs;
	public $flg_stop_executing_function = false;
	private $cache_field;
	private $polling_start = false;
	private $square_error = "";
	public $stop_res = false;
	private static $instance = null;
	public $openai;
	private $assistant;
	private $mcrypt;
	private $dashbord_items = [];
	private $dashbord_column_width = 1;

	function __construct($class = null, $smarty = null) {

		$this->dirs = new Dirs();
		$this->smarty = $smarty;
		$this->dbarr = array();
		$this->class = $class;
		$this->assign_list = array();
		$this->debug_obj = array();
		$this->cache_field = array();

		$this->set_class($class);

		// OpenAIのインターフェイスはここで読み込む
		$this->include_openai_interfaces();

		self::$instance = $this;
	}

	public static function getInstance(): ?Controller {
		return self::$instance;
	}

	function api($api_url, $class, $function, $post_arr = []) {

		$post_arr["class"] = $class;
		$post_arr["function"] = $function;

		$postdata = http_build_query($post_arr);

		$header = array(
		    "Content-Type: application/x-www-form-urlencoded",
		);

		$opts = array('http' =>
		    array(
			'method' => 'POST',
			'header' => implode("\r\n", $header),
			'content' => $postdata
		    )
		);

		$context = stream_context_create($opts);

		//データベースすべてをロック解除
		foreach ($GLOBALS["lock_class_arr"] as $c) {
			$c->closeDatFile();
		}

		$result_json = file_get_contents($api_url, false, $context);

		//データベースを再度ロック
		foreach ($GLOBALS["lock_class_arr"] as $c) {
			$c->openDatFile(false, false);
		}

		$result = json_decode($result_json, true);
		if ($result == null) {
			throw new Exception("API RECEIVED SERVER ERROR:" . $result_json);
		} else {
			if ($result["location"] == "app.php?class=login") {
				throw new Exception("[API ERROR] The server isn't public page.");
			}
			return $result;
		}
	}

	function get_session($key) {
		if (empty($this->windowcode)) {
			return null;
		}
		if (isset($_SESSION[$this->windowcode][$key])) {
			return $_SESSION[$this->windowcode][$key];
		} else {
			return null;
		}
	}

	function set_session($key, $val) {
		//$this->console_log("SET SESSION:" . $this->windowcode . " " . $key . " " . $val);
		if (!empty($this->windowcode)) {
			$_SESSION[$this->windowcode][$key] = $val;
		}
	}

	function get_windowcode() {
		return $this->windowcode;
	}

	function set_windowcode($windowcode) {
		$this->console_log("windowcode=" . $windowcode);
		$this->windowcode = $windowcode;
	}

	function set_data_dir($dir) {
		$this->dirs->datadir = $dir;
	}

	function set_class($class) {
		$this->class = $class;
		if ($class == null) {
			return;
		}
		if (is_object($this->smarty)) {
			$this->smarty->setTemplateDir($this->dirs->get_class_dir($class) . "/Templates/");
			$this->smarty->setCompileDir($this->dirs->datadir . "/templates_c/" . "$class" . "/");
			$compile_dir = $this->smarty->getCompileDir();
			if (!is_dir($compile_dir) && !@mkdir($compile_dir, 0777, true) && !is_dir($compile_dir)) {
				throw new RuntimeException("Failed to create Smarty compile dir: " . $compile_dir);
			}
		}
	}

	function make_db($name, $class = null) {
		if ($class == null) {
			$class = $this->class;
		}
		$path_fmt = $this->dirs->get_class_dir($class) . "/fmt/$name.fmt";
		file_put_contents($path_fmt, "id,24,N\n");
	}

	//DBの接続を作成・取得
	function db($name, ?string $class = null, ?string $separated_by = null): FFM {

		if ($class == null) {
			$fdir = $this->dirs->get_class_dir($this->class) . "/fmt";
			if (is_file($fdir . "/$name.fmt")) {
				$class = $this->class;
			} else {
				$class = "common";
			}
		}

		if ($separated_by != null) {
			$separated_by = "_" . $separated_by;
		} else {
			$separated_by = "";
		}

		$ddir = $this->dirs->datadir . "/" . $class . $separated_by;
		$fdir = $this->dirs->get_class_dir($class) . "/fmt";

		$key = $ddir . "/" . $name;
		if (!isset($this->dbarr[$key])) {
			$ffm = new fixed_file_manager($name, $ddir, $fdir);
			$ffm->set_controller($this);
			$ffm->set_info($name, $class);
			$this->dbarr[$key] = $ffm;
			return $ffm;
		} else {
			$ffm = $this->dbarr[$key];
			//check
			$ffm->check_hf();
			return $ffm;
		}
	}

	// DBの接続をクローズ(ffmのオブジェクトを使用）
	function close_db_by_ffm(FFM $ffm) {
		foreach ($this->dbarr as $key => $ffm_obj) {
			if ($ffm == $ffm_obj) {
				$ffm->close();
				unset($this->dbarr[$key]);
				return;
			}
		}
	}

	function close_all_db() {
		foreach ($this->dbarr as $key => $ffm) {
			$ffm->close();
			unset($this->dbarr[$key]);
		}
	}

	//smartyを取得
	function smarty() {
		return $this->smarty;
	}

	//リダイレクト
	function res_redirect($url) {
		if ($this->POST("_call_from") == "appcon") {
			$this->arr["location"] = $url;
		} else {
			header("Location: $url");
		}
	}

	//リロード
	function res_reload() {
		$this->arr["reload"] = "do";
	}

//	//検索
//	function res_search(){
//		echo "do_search";
//	}
//	
//	//ダイアログを閉じる
//	function res_close(){
//		echo "close";
//	}
	//ダイアログ
	function res_dialog($title, $template, $options = array()) {
		$this->arr["title"] = $title;
		if (!empty($options["width"])) {
			$this->arr["width"] = $options["width"];
		} else {
			$this->arr["width"] = 600;
		}
		if (!empty($options["height"])) {
			$this->arr["height"] = $options["height"];
		} else {
			$this->arr["height"] = null;
		}
		$this->arr["dialog_options"] = $options;
		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		$screen_debug_key = $this->register_screen_debug_context($dialog_name, $template);
		$this->smarty->assign("screen_debug_key", $screen_debug_key);
		$tmp = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");
		$fixed_bar = null;
		$html = '<div class="class_style_' . $this->class . '">' . $tmp . '</div>';
		$this->arr["reloadarea"]["#dialog"] = $html;
		$this->res();
	}

	//PDF(OLD)
	function res_pdf($imgdir, $pdf_template, $download_filename, $title = "Print", $width = 600) {

		if ($imgdir == null) {
			$_SESSION["pdf_imgdir"] = $this->dirs->datadir . "/upload/";
		} else {
			$_SESSION["pdf_imgdir"] = $this->get_session("appdir") . "/" . $this->get_session("class") . "/" . $imgdir . "/";
		}
		$this->set_session("pdf_source_class", (string) $this->class);
		$this->set_session("pdf_source_function", (string) $this->get_called_function());
		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		$this->smarty->escape_html = false;
		$_SESSION['pdf_text'] = $this->fetch_pdf_template_with_sanitized_vars($pdf_template);
		$this->console_log("Template:" . $this->class . "/" . $pdf_template, "#CE5C00");
		$_SESSION["pdf_filename"] = $download_filename;

		$is_smartphone = $this->is_smartphone();
		$this->assign("is_smartphone",$is_smartphone);
		$url = $this->url_with_sid('apppdf.php?cmd=download&time=' . time());
		$this->assign("url",$url);
		$this->show_multi_dialog("__pdf__" . date("Ymdhs"), dirname(__FILE__) . "/../Templates/print_dialog.tpl", $title, $width);
	}

	//
	function show_pdf($pdf_template, $download_filename, $title = "Print", $width = 600) {

		$root_url = $this->get_APP_URL();
		$this->assign("root_url", $root_url);

		$this->set_session("pdf_source_class", (string) $this->class);
		$this->set_session("pdf_source_function", (string) $this->get_called_function());
		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		$this->smarty->escape_html = false;
		$_SESSION['pdf_text'] = $this->fetch_pdf_template_with_sanitized_vars($pdf_template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");
		$_SESSION["pdf_filename"] = $download_filename;
		$_SESSION["pdf_imgdir"] = [$this->dirs->get_class_dir($this->class) . "/images/", $this->dirs->datadir . "/upload/"];
		$this->assign("time", time());
		
		$is_smartphone = $this->is_smartphone();
		$this->assign("is_smartphone",$is_smartphone);
		$url = $this->url_with_sid('apppdf.php?cmd=download&time=' . time());
		$this->assign("url",$url);
		$this->show_multi_dialog("__pdf__" . date("Ymdhs"), dirname(__FILE__) . "/../Templates/print_dialog.tpl", $title, $width);
	}
	
	private function url_with_sid(string $url): string {
	    $sidParam = session_name().'='.rawurlencode(session_id());
	    $hasQuery = (parse_url($url, PHP_URL_QUERY) !== null);
	    return $url . ($hasQuery ? '&' : '?') . $sidParam;
	}

	// Save PDF
	function save_pdf($pdf_template, $pdf_filename) {
		include_once(dirname(__FILE__) . "/../lib/pdfmaker/pdfmaker_class.php");

		$imgdir = $this->dirs->datadir . "/upload/";

		$txt = $this->fetch_pdf_template_with_sanitized_vars($pdf_template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");

		$pdfmaker = new pdfmaker_class();
		$pdfmaker->makepdf($txt, $imgdir, $this->dirs->datadir . "/upload/" . $pdf_filename, "F");
	}

	private function fetch_pdf_template_with_sanitized_vars(string $pdf_template): string {
		$original_vars = $this->smarty->getTemplateVars();
		foreach ($original_vars as $key => $value) {
			$this->smarty->assign($key, $this->sanitize_pdf_template_var($value));
		}
		try {
			return $this->smarty->fetch($pdf_template);
		} finally {
			foreach ($original_vars as $key => $value) {
				$this->smarty->assign($key, $value);
			}
		}
	}

	private function sanitize_pdf_template_var($value) {
		if (is_string($value)) {
			return preg_replace('/(^|\\R)---/u', '$1 ---', $value);
		}
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = $this->sanitize_pdf_template_var($item);
			}
			return $value;
		}
		return $value;
	}

	//レスポンス
	function append_res_data($key, $val) {
		$checkarr = ["title", "width", "reloadarea", "height", "chart", "dialog_options"];
		foreach ($checkarr as $c) {
			if ($c == $key) {
				throw new Exception($c . " はキーとして使用できません。");
			}
		}
		$this->arr[$key] = $val;
	}

	//reload_area
	function reload_area($id_or_class, $val_or_template_name) {

		if (is_array($val_or_template_name)) {
			$val_or_template_name = json_encode($val_or_template_name);
		}

		if (endsWith($val_or_template_name, ".tpl")) {
			$val = $this->fetch($val_or_template_name);
		} else {
			$val = $val_or_template_name;
		}
		$this->arr["reloadarea"][$id_or_class] = $val;
	}

	function set_response_value($key, $value) {
		$this->arr[$key] = $value;
	}

	//error message
	private function get_error_scope() {
		$scope = trim((string) $this->POST("_error_scope"));
		if ($scope !== "") {
			return $scope;
		}
		$dialog_id = trim((string) $this->POST("_dialog_id"));
		if ($dialog_id !== "") {
			return $dialog_id;
		}
		$classname = trim((string) $this->get_classname());
		if ($classname !== "") {
			return $classname;
		}
		return "";
	}

	function res_error_message($field, $message) {
		$dialog_id = $this->POST("_dialog_id");
		$md["dialog_id"] = $dialog_id;
		$md["classname"] = $this->get_classname();
		$md["scope"] = $this->get_error_scope();
		$md["field"] = $field;
		$md["message"] = $message;
		$this->arr["errormessage"][] = $md;
	}

	function count_res_error_message() {
		if (isset($this->arr["errormessage"]) && is_array($this->arr["errormessage"])) {
			return count($this->arr["errormessage"]);
		} else {
			return 0;
		}
	}

	function clear_error_message() {
		$this->arr["errormessage"] = null;
		$this->arr["clear_error_message"] = "true";
		$scope = $this->get_error_scope();
		if ($scope !== "") {
			$this->arr["clear_error_scope"] = $scope;
		}
	}

	//append area
	function append_area($id_or_class, $val_or_template_name) {
		if (endsWith($val_or_template_name, ".tpl")) {
			$val = $this->fetch($val_or_template_name);
		} else {
			$val = $val_or_template_name;
		}
		$this->arr["appendarea"][$id_or_class] = $val;
	}

	//jsonで応答を返す
	function res() {

		// DBのログを出力
		if ($this->testserver()) {
			$this->show_dbarr_console_log();
		}

		if ($this->arr == null) {
			echo "";
		} else {
			if (!$this->display_flg) {

				$this->arr["class"] = $this->class;
				if (isset($this->session_arr)) {
					$this->arr["session"] = json_encode($this->session_arr);
				}
				echo json_encode($this->arr);
			}
		}
		$this->arr = null;
	}

	//smarty assign
	function assign($key, $val) {
		if ($this->smarty != null) {
			$this->smarty->assign($key, $val);
			$this->assign_list[$key] = $val;
		}
	}

	function display($template) {
		$this->arr["class"] = $this->class;
		$_SESSION["_DISPLAY_ARR"] = $this->arr;

		if ($this->POST("_call_from") == "appcon") {
			$html = $this->smarty->fetch($template);
			$this->set_session("_DISPLAY", $html);
			if ($this->class === "public_pages") {
				$_SESSION["_PUBLIC_DISPLAY"] = $html;
			}
			$this->res_reload();
		} else {
			$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
			$this->smarty->display($template);
			$this->display_flg = true;
		}
	}

	function show_public_pages($contents_template, $header_template = null, $contents_header_template = null, $contents_footer_template = null) {
		$current_class = trim((string) $this->get_classname());
		if ($current_class !== "") {
			$this->assign("class", $current_class);
		}
		$screen_debug_key = $this->register_screen_debug_context("publicsite", $contents_template);
		$this->assign("screen_debug_key", $screen_debug_key);
		$contents = $this->fetch($contents_template);
		$this->assign("contents", $contents);
		$this->assign("publicsite_menu_items", $this->build_publicsite_menu_items());

		$html_header = "";
		if (!empty($header_template)) {
			$html_header = $this->fetch($header_template);
		}
		$this->assign("html_header", $html_header);

		$contents_header = "";
		if (!empty($contents_header_template)) {
			$contents_header = $this->fetch($contents_header_template);
		}
		$this->assign("contents_header", $contents_header);

		$contents_footer = "";
		if (!empty($contents_footer_template)) {
			$contents_footer = $this->fetch($contents_footer_template);
		}
		$this->assign("contents_footer", $contents_footer);

		$this->display(dirname(__FILE__) . "/../Templates/publicsite_index.tpl");
	}

	private function build_publicsite_menu_items(): array {
		$items = [];
		try {
			$list = $this->db("public_pages_registry", "public_pages_registry")->getall("menu_sort", SORT_ASC);
		} catch (Throwable $e) {
			return $items;
		}
		foreach ($list as $row) {
			if ((int) ($row["enabled"] ?? 0) !== 1) {
				continue;
			}
			if ((int) ($row["show_in_menu"] ?? 0) !== 1) {
				continue;
			}
			$function_name = trim((string) ($row["function_name"] ?? ""));
			if ($function_name === "") {
				continue;
			}
			$label = trim((string) ($row["menu_label"] ?? ""));
			if ($label === "") {
				$label = trim((string) ($row["title"] ?? ""));
			}
			if ($label === "") {
				$label = $function_name;
			}
			$items[] = [
				"label" => $label,
				"url" => $this->get_APP_URL("public_pages", $function_name),
				"selected" => ($this->function === $function_name ? 1 : 0),
			];
		}
		return $items;
	}

	function show_pubic_pages($contents_template, $header_template = null, $contents_header_template = null, $contents_footer_template = null) {
		$this->show_public_pages($contents_template, $header_template, $contents_header_template, $contents_footer_template);
	}

	function fetch($template) {
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");
		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		return $this->smarty->fetch($template);
	}

	function list_reset() {
		$this->set_session("morepage_startidx", 1);
		$this->set_session("search", null);
	}

	function POST($key = null) {

		$post = $_POST;

		if ($key == null) {
			return $post;
		} else {
			return $post[$key] ?? null;
		}
	}

	function set_called_parameters() {
		$this->called_parameters = [];
		foreach ($_POST as $key => $val) {
			if ($key == "class" || $key == "function") {
				//
			} else {
				$this->called_parameters[$key] = $val;
			}
		}
	}

	function GET($key = null) {
		if ($key == null) {
			return $_GET;
		} else {
			return $_GET[$key] ?? null;
		}
	}

	function res_image($subdir, $filename, $class = null, $cache = true, $maxAge = 3600, $immutable = false) {
		// セッションを停止（ロックするため）
		session_write_close();

		// すべてのデータベースをクローズ（既存処理）
		foreach ($GLOBALS["lock_class_arr"] as $c) {
			$c->close();
		}

		// エラーを非表示（既存踏襲）
		error_reporting(~E_ALL);

		// セッション由来の no-cache を抑止（session_start() より前が理想）
		if ($cache && function_exists('session_cache_limiter')) {
			session_cache_limiter('');
		}

		// パス安全化
		$filename = basename($filename);
		if ($class === null) {
			$class = $this->class;
		}
		// subdir の簡易バリデーション（英数/._-/ のみ許可）
		if (!preg_match('#^[A-Za-z0-9._-]+$#', $subdir)) {
			echo "Invalid subdir";
			return;
		}

		$filepath = $this->dirs->get_class_dir($class) . "/{$subdir}/{$filename}";
		if (!is_file($filepath)) {
			// 存在しない場合の簡易応答（必要なら $this->blank_image() に）
			header_remove('Cache-Control');
			header_remove('Pragma');
			header_remove('Expires');
			header_remove('Last-Modified');
			header('Content-Type: text/plain; charset=UTF-8');
			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
			http_response_code(404);
			echo "File not found: class={$class} subdir={$subdir} filename={$filename}";
			return;
		}

		// MIME 判定
		$mimetype = 'application/octet-stream';
		if (class_exists('finfo')) {
			$fi = new finfo(FILEINFO_MIME_TYPE);
			$mt = $fi->file($filepath);
			if ($mt)
				$mimetype = $mt;
		}

		// 共通ヘッダ
		header('Content-Type: ' . $mimetype);
		header('X-Content-Type-Options: nosniff');

		if ($cache) {
			// 競合ヘッダを除去（明示的にクリーン）
			header_remove('Cache-Control');
			header_remove('Pragma');
			header_remove('Expires');
			header_remove('Last-Modified'); // mtimeは使わない
			// mtime不使用：URLベースの安定ETag
			$etag = '"u-' . hash('sha256', $_SERVER['REQUEST_URI']) . '"';

			// キャッシュポリシー
			if ($immutable) {
				header('Cache-Control: public, max-age=31536000, immutable');
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
			} else {
				header("Cache-Control: public, max-age={$maxAge}");
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
			}
			header('ETag: ' . $etag);

			// 条件付きGET（ETagのみで判定）
			$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
			if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
				header('HTTP/1.1 304 Not Modified');
				exit();
			}

			// Content-Length（任意）
			$size = @filesize($filepath);
			if ($size !== false) {
				header('Content-Length: ' . $size);
			}

			// HEADリクエストなら本文無し
			if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD') {
				exit();
			}

			readfile($filepath);
			exit();
		} else {
			// キャッシュ不可
			header_remove('Cache-Control');
			header_remove('Pragma');
			header_remove('Expires');
			header_remove('Last-Modified');

			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
			header('Pragma: no-cache');
			header('Expires: 0');

			// Content-Length（任意）
			$size = @filesize($filepath);
			if ($size !== false) {
				header('Content-Length: ' . $size);
			}

			if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD') {
				exit();
			}

			readfile($filepath);
			exit();
		}
	}

	//
	function get_posted_filename($post_name, $key = null) {
		if ($key == null) {
			return $_FILES[$post_name]['name'];
		} else {
			return $_FILES[$post_name]['name'][$key];
		}
	}

	private function get_uploaded_basename($name) {
		$name = (string) $name;
		$name = str_replace("\0", "", $name);
		$name = str_replace("\\", "/", $name);
		$parts = explode("/", $name);
		$base = end($parts);
		if ($base === false) {
			return "";
		}
		return (string) $base;
	}

	function get_posted_filepath($post_name) {
		return $_FILES[$post_name]['tmp_name'];
	}

	function get_filedata_posted($post_name, $key = null) {
		if (empty($_FILES[$post_name]) || !is_array($_FILES[$post_name])) {
			return "";
		}

		if (!is_null($key)) {
			$path = $_FILES[$post_name]['tmp_name'][$key] ?? "";
		} else {
			$path = $_FILES[$post_name]['tmp_name'] ?? "";
		}

		if ($path === "" || !is_file($path) || !is_readable($path)) {
			return "";
		}

		$data = file_get_contents($path);
		if ($data === false) {
			return "";
		}

		return $data;
	}

	// POSTされたかの確認
	function is_posted_file($post_name) {
		// フィールド自体が無い（post_max_size 超過など） or 配列でない
		if (empty($_FILES[$post_name]) || !is_array($_FILES[$post_name])) {
			return false;
		}

		$file = $_FILES[$post_name];

		if ($file['error'] !== UPLOAD_ERR_OK) {
			return false;
		}

		// サイズ 0 はファイルなし扱い（空ファイルを許可したいならここは外す）
		if ($file['size'] <= 0) {
			return false;
		}

		// 実際に HTTP アップロードされた一時ファイルか確認
		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			return false;
		}

		return true;
	}

	//POSTされたファイルの拡張子を取得（小文字に変換）
	function get_posted_file_extention($post_name) {
		$pathinfo = pathinfo($this->get_posted_filename($post_name));
		return mb_strtolower($pathinfo["extension"]);
	}

	//POSTされたファイルを保存
	function save_posted_file($post_name, $save_filename, $key = null) {
		$upload_dir = $this->dirs->datadir . "/upload";
		//$this->log("saved_posted_file: post_name=" . $post_name . " saved_file_name:" . $upload_dir . "/$save_filename");
		//アップロード用のディレクトリ
		if (!is_dir($upload_dir)) {
			mkdir($upload_dir);
		}

		//保存
		//$path = $_FILES[$post_name]['tmp_name'];
		if (!is_null($key)) {
			$path = $_FILES[$post_name]['tmp_name'][$key];
		} else {
			$path = $_FILES[$post_name]['tmp_name'];
		}

		if (is_file($upload_dir . "/$save_filename")) {
			unlink($upload_dir . "/$save_filename");
		}

		$res = move_uploaded_file($path, $upload_dir . "/$save_filename");
		if (!$res && defined("CLI_APP_CALL")) {
			// CLIテストでは move_uploaded_file が失敗するためコピーで対応
			$res = copy($path, $upload_dir . "/$save_filename");
		}
		if (!$res) {
			//$this->log("saved_posted_file: can't save file: " . $path . " ->" . $upload_dir . "/$save_filename");
		}
	}

	public function duplicate_rows($table_name, $id): int {

		$fields = $this->get_field_list($table_name);
		$table_identifer = $this->db($table_name)->get_identifier();
		$ffm_upload = $this->db("file", "upload");

		$data = $this->db($table_name)->get($id);

		// 複製
		$new_id = $this->db($table_name)->insert($data);

		// ファイルを処理
		foreach ($fields as $f) {
			if ($f["type"] == "file" || $f["type"] == "image") {
				$parameter_name = $f["parameter_name"];

				$row_id = $data[$parameter_name];
				$file = $ffm_upload->get($row_id);

				$src_path = $file["path"];
				$src_path_th = $file["path_th"];

				$ffm_upload->insert($file);
				$file["path"] = "upload_file_" . $file["id"];
				$file["path_th"] = $file["path"] . "_th";
				$file["table_identifer"] = $table_identifer;
				$file["row_id"] = $data["id"];
				$ffm_upload->update($file);

				$data[$parameter_name] = $file["id"];
				$this->db($table_name)->update($data);

				//ファイルを複製
				$this->copy_saved_file($src_path, $file["path"]);
				if ($f["type"] == "image") {
					$this->copy_saved_file($src_path_th, $file["path_th"]);
				}
			}
		}

		return $new_id;
	}

	public function delete_file($file_id) {
		$file = $this->db("file", "upload")->get($file_id);

		if ($file) {
			try {
				$this->delete_saved_file($file["path"]);
				$this->delete_saved_file($file["path_th"]);
			} catch (Exception $e) {
				// nothing
			}
			$this->db("file", "upload")->delete($file["id"]);
		}
	}

	/**
	 * $_FILES[$parameter_name] の処理可能なキー一覧を返す
	 * - 単数: [null]
	 * - 複数: [0, 1, 2, ...]（UPLOAD_ERR_OK のものだけ）
	 * エラーや未選択（UPLOAD_ERR_NO_FILE）は除外
	 * @return array<int|null>
	 */
	public function get_posted_file_keys($parameter_name) {
		if (!isset($_FILES[$parameter_name])) {
			return [];
		}
		$f = $_FILES[$parameter_name];

		// 単数
		if (!is_array($f['name'])) {
			$err = isset($f['error']) ? $f['error'] : UPLOAD_ERR_NO_FILE;
			return ($err === UPLOAD_ERR_OK) ? [null] : [];
		}

		// 複数
		$keys = [];
		foreach ($f['name'] as $i => $_) {
			$err = isset($f['error'][$i]) ? $f['error'][$i] : UPLOAD_ERR_NO_FILE;
			if ($err === UPLOAD_ERR_OK) {
				$keys[] = $i;
			}
		}
		return $keys;
	}

	public function store_posted_file(
		$parameter_name,
		$image_width = null,
		$image_width_thumbnail = null,
		$table_name = null,
		$row_id = null,
		$key = null
	): int {

		if ($table_name != null) {
			$table_identifer = $this->db($table_name)->get_identifier();
		} else {
			$table_identifer = null;
		}

		$val = $_FILES[$parameter_name];
		$ismultiple = is_array($val['name']);

		// ★ multiple のときだけ $key を使う（単数なら null を渡す）
		$k = $ismultiple ? (int) $key : null;

		// ★ ファイル名の取り方を1行に
		$fname = $this->get_uploaded_basename($ismultiple ? $val['name'][$k] : $val['name']);

		$file = [];
		$file['filename'] = $fname;

		$ffm_upload = $this->db('file', 'upload');
		$ffm_upload->insert($file);

		$file['path'] = 'upload_file_' . $file['id'];
		$file['path_th'] = $file['path'] . '_th';
		$file['table_identifer'] = $table_identifer;
		$file['row_id'] = $row_id;
		$ffm_upload->update($file);

		// ★ 第3引数は multiple のときだけ $k、単数は null
		$this->save_posted_file($parameter_name, $file['path'], $k);

		if ($image_width !== null) {
			$this->resize_saved_image($file['path'], $file['path'], $image_width);
		}
		if ($image_width_thumbnail !== null) {
			$this->resize_saved_image($file['path'], $file['path_th'], $image_width_thumbnail);
		}

		return (int) $file['id'];
	}

	//
	private function update_all_files_posted(&$row, $table_name, $fields) {

		foreach ($_FILES as $parameter_name => $val) {

			$s = $_FILES[$parameter_name]['error'];

			if (isset($val) && $s === UPLOAD_ERR_OK) {

				// find the image_width
				$image_width = null;
				$image_width_thumbnail = null;
				foreach ($fields as $f) {
					if ($f["parameter_name"] == $parameter_name) {
						$image_width = $f["image_width"];
						$image_width_thumbnail = $f["image_width_thumbnail"];
					}
				}

				$id = $row[$parameter_name];
				if ($id) {
					$this->delete_file($id);
				}

				$file_id = $this->store_posted_file($parameter_name, $image_width, $image_width_thumbnail, $table_name, $row["id"]);
				$row[$parameter_name] = $file_id;
			} else {
				if ($s != UPLOAD_ERR_NO_FILE) {
					$errorMessages = [
					    UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
					    UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.",
					    UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
					    UPLOAD_ERR_NO_FILE => "No file was uploaded.",
					    UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
					    UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
					    UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload.",
					];

					// Get the error message or a default message for unknown error codes
					$errorMessage = isset($errorMessages[$s]) ? $errorMessages[$s] : "An unknown error occurred.";

					// Throw an exception with the error message
					throw new Exception("File upload error: " . $errorMessage);
				}
			}

			$this->db($table_name)->update($row);
		}
	}

	function delete_files($table_name, $row_id) {
		$table_identifer = $this->db($table_name)->get_identifier();
		if ($table_identifer !== null && $row_id !== null) {
			$ffm_upload = $this->db("file", "upload");
			$list = $ffm_upload->select(["table_identifer", "row_id"], [$table_identifer, $row_id]);
			foreach ($list as $file) {
				try {
					$this->delete_saved_file($file["path"]);
					$this->delete_saved_file($file["path_th"]);
				} catch (Exception $e) {
					// nothing
				}
				$ffm_upload->delete($file["id"]);
			}
		}
	}

	function get_file_info($id, $encrypt = true) {
		$ffm_upload = $this->db("file", "upload");
		$file = $ffm_upload->get($id);
		$arr = [
		    "id" => $file["id"],
		    "filename" => $file["filename"],
		    "path" => $file["path"],
		    "path_th" => $file["path_th"],
		    "fullpath" => $this->dirs->datadir . "/upload/" . $file["path"],
		    "fullpath_th" => $this->dirs->datadir . "/upload/" . $file["path_th"],
		];

		if ($encrypt) {
			if (!empty($arr["path"])) {
				$arr["path"] = $this->encrypt($arr["path"]);
			}
			if (!empty($arr["path_th"])) {
				$arr["path_th"] = $this->encrypt($arr["path_th"]);
			}
		}

		return $arr;
	}

	function save_posted_files($table_name, &$row) {
		$this->save_files($table_name, $row);
	}

	function save_files($table_name, &$row) {

		$fields = $this->get_field_list($table_name);

		$this->update_all_files_posted($row, $table_name, $fields);
	}

	//ファイルを作成
	function save_file($filename, $data) {
		$upload_dir = $this->dirs->datadir . "/upload";

		//アップロード用のディレクトリ
		if (!is_dir($upload_dir)) {
			mkdir($upload_dir);
		}

		//保存
		file_put_contents($upload_dir . "/$filename", $data);
	}

	function read_file($filename) {
		$upload_dir = $this->dirs->datadir . "/upload";
		//アップロード用のディレクトリ
		if (!is_dir($upload_dir)) {
			mkdir($upload_dir);
		}
		$path = $upload_dir . "/$filename";
		if (is_file($path)) {
			return file_get_contents($path);
		} else {
			return "";
		}
	}

	//
	function is_saved_file($filename) {
		if (is_file($this->dirs->datadir . "/upload/$filename")) {
			return true;
		} else {
			return false;
		}
	}

	function blank_image() {
		header('Content-Type: image/png');
		header("Cache-Control:no-cache,no-store,must-revalidate,max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma:no-cache");
		$time_newest = strtotime("now");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s", $time_newest) . "GMT");
		echo base64_decode("iVBORw0KGgoAAAANSUhEUgAAAGQAAAABCAYAAAAo2wu9AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADklEQVQYlWNgGAWDCgAAAZEAAejZ5ZUAAAAASUVORK5CYII=");
	}

	function res_saved_image($filename, $cache = true, $maxAge = 3600, $immutable = false) {

		session_write_close();

		// セッション由来の no-cache を抑止（session_start() より前に呼ばれる想定）
		if ($cache) {
			if (function_exists('session_cache_limiter')) {
				@session_cache_limiter('');
			}
		}

		$filepath = $this->dirs->datadir . "/upload/$filename";
		if (!is_file($filepath)) {
			$this->blank_image();
			return;
		}
		$delete_after_output = $this->is_temporary_qrcode_file($filename);

		// MIME
		$mimetype = 'application/octet-stream';
		if (class_exists('finfo')) {
			$fi = new finfo(FILEINFO_MIME_TYPE);
			$mt = $fi->file($filepath);
			if ($mt)
				$mimetype = $mt;
		}
		header('Content-Type: ' . $mimetype);

		if ($cache) {
			// 競合回避
			header_remove('Cache-Control');
			header_remove('Pragma');
			header_remove('Expires');
			header_remove('Last-Modified'); // mtimeは使わない
			// ===== mtimeを使わない: URLベースの安定ETag =====
			// リソースURLが内容ごとに変わらないなら path 等を含めてOK
			$etag = '"u-' . hash('sha256', $_SERVER['REQUEST_URI']) . '"'; // 強いETag（W/ なし）
			// 条件付きGET（ETagだけで判定）
			$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
			if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
				header('HTTP/1.1 304 Not Modified');
				if ($immutable) {
					header('Cache-Control: public, max-age=31536000, immutable');
					header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
				} else {
					header("Cache-Control: public, max-age={$maxAge}");
					header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
				}
				header('ETag: ' . $etag);
				exit();
			}

			// 通常200
			if ($immutable) {
				header('Cache-Control: public, max-age=31536000, immutable');
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
				// immutable運用ならETagは不要だが、残しても害はない（デバッグやCDNに有用）
			} else {
				header("Cache-Control: public, max-age={$maxAge}");
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
			}
			header('ETag: ' . $etag);

			// 任意：Content-Length（mtime不要）
			$size = @filesize($filepath);
			if ($size !== false)
				header('Content-Length: ' . $size);

			readfile($filepath);
			if ($delete_after_output && is_file($filepath)) {
				unlink($filepath);
			}
			exit();
		} else {
			// キャッシュ不可
			header_remove('Cache-Control');
			header_remove('Pragma');
			header_remove('Expires');
			header_remove('Last-Modified');

			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
			header('Pragma: no-cache');
			header('Expires: 0');

			readfile($filepath);
			if ($delete_after_output && is_file($filepath)) {
				unlink($filepath);
			}
			exit();
		}
	}

	private function is_temporary_qrcode_file($filename) {
		return preg_match('/^qr-code(?:-text)?-?[a-f0-9]+\.png$/', $filename) === 1;
	}

	function res_saved_file($filename, $download_name = null) {
		//エラーを非表示
		//error_reporting(~E_ALL);

		$filepath = $this->dirs->datadir . "/upload/$filename";
		if (!is_file($filepath) || !is_readable($filepath)) {
			$this->respond_download_error(
				$this->t("common.download_error_title"),
				$this->t("common.download_file_not_found")
			);
			return;
		}

		$fp = fopen($filepath, "rb");
		if ($fp === false) {
			$this->respond_download_error(
				$this->t("common.download_error_title"),
				$this->t("common.download_file_open_failed", ["file" => basename((string) $filename)])
			);
			return;
		}

		$mimeType = 'application/octet-stream';
		if ($download_name === null || trim((string) $download_name) === "") {
			$download_name = basename((string) $filename);
		} else {
			$download_name = str_replace(["\r", "\n", "\0"], "", trim((string) $download_name));
		}
		$ascii_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $download_name);
		if ($ascii_name === "" || $ascii_name === null) {
			$ascii_name = "download";
		}
		header('Content-Type: ' . $mimeType);
		header('Content-Disposition: attachment; filename="' . addcslashes($ascii_name, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($download_name));
		header('Content-Length: ' . filesize($filepath));

		while (!feof($fp)) {
			$contents = fread($fp, 1024);
			echo $contents;
		}
		fclose($fp);
		exit();
	}

	private function respond_download_error($title, $message) {
		$title = (string) $title;
		$message = (string) $message;
		$download_mode = strtolower(trim((string) ($_REQUEST["_download_mode"] ?? "")));
		http_response_code(404);
		header("X-FBP-Download-Error: 1");
		header("X-FBP-Download-Error-Title: " . rawurlencode($title));
		if ($download_mode === "open_new_tab") {
			header("Content-Type: text/html; charset=UTF-8");
			echo $this->build_download_error_html($title, $message);
			exit();
		}

		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode([
			"title" => $title,
			"message" => $message,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit();
	}

	private function build_download_error_html($title, $message) {
		$title = htmlspecialchars((string) $title, ENT_QUOTES, "UTF-8");
		$message = nl2br(htmlspecialchars((string) $message, ENT_QUOTES, "UTF-8"));
		return '<!DOCTYPE html>'
			. '<html lang="en"><head><meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
			. '<title>' . $title . '</title>'
			. '<style>'
			. 'body{margin:0;font-family:sans-serif;background:#f8fafc;color:#0f172a;}'
			. '.wrap{max-width:720px;margin:48px auto;padding:0 20px;}'
			. '.panel{background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:24px;box-shadow:0 10px 30px rgba(15,23,42,0.08);}'
			. 'h1{margin:0 0 12px;font-size:24px;}'
			. 'p{margin:0;line-height:1.7;}'
			. '</style></head><body><div class="wrap"><div class="panel"><h1>' . $title . '</h1><p>' . $message . '</p></div></div></body></html>';
	}

	function remove_saved_file($filename) {

		$filepath = $this->dirs->datadir . "/upload/$filename";
		if (is_file($filepath)) {
			unlink($filepath);
		}
	}

	function delete_saved_file($filename) {
		$this->remove_saved_file($filename);
	}

	//POSTされた情報を見る
	function debug_post() {
		echo "<pre>";
		var_dump($_POST);
		echo "</pre>";
	}

	function copy_saved_file($src_filename, $to_filename) {
		$file = $this->dirs->datadir . "/upload/$src_filename";
		if (!is_file($file)) {
			return;
		}

		$file_to = $this->dirs->datadir . "/upload/$to_filename";
		if (is_file($file_to)) {
			unlink($file_to);
		}
		copy($file, $file_to);
	}

	//----------------------------------------
	// イメージのリサイズ
	//----------------------------------------
	function resize_saved_image($inputfile, $outputfile, $width, $quality = 100, $adjust_height = false) {
		$file = $this->dirs->datadir . "/upload/$inputfile";
		if (!is_file($file) || $width == null)
			return;

		require_once __DIR__ . "/../lib_ext/php-heic-to-jpg/src/HeicToJpg.php";
		$HeicToJpg = new Maestroerror\HeicToJpg();
		if ($HeicToJpg->isHeic($file)) {
			$HeicToJpg->convert($file)->saveAs($file); // HEIC→JPG（※EXIF回転を持つ可能性あり）
		}

		$type = exif_imagetype($file);
		if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_PNG], true))
			return;

		// 画像読み込み
		switch ($type) {
			case IMAGETYPE_GIF: $image = imagecreatefromgif($file);
				break;
			case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($file);
				break;
			case IMAGETYPE_PNG: $image = imagecreatefrompng($file);
				break;
		}

		// --- ここがポイント：EXIF Orientation を反映 ---
		if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
			$exif = @exif_read_data($file);
			if (!empty($exif['Orientation'])) {
				switch ($exif['Orientation']) {
					case 3: $image = imagerotate($image, 180, 0);
						break;       // 180°
					case 6: $image = imagerotate($image, -90, 0);
						break;       // 90°CW
					case 8: $image = imagerotate($image, 90, 0);
						break; // 90°CCW
				}
			}
		}
		// ----------------------------------------------
		// 元サイズ
		[$image_w, $image_h] = [imagesx($image), imagesy($image)];

		// 比率計算
		$proportion = $image_w / $image_h;
		$height = $width / $proportion;

		if ($adjust_height && $proportion < 1) {
			$height = $width;
			$width = (int) round($width * $proportion);
		}

		$width = (int) round($width);
		$height = (int) round($height);

		$canvas = imagecreatetruecolor($width, $height);

		// 透過対応（PNG/GIF）
		if ($type !== IMAGETYPE_JPEG) {
			imagealphablending($canvas, false);
			imagesavealpha($canvas, true);
		}

		imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $image_w, $image_h);

		// 出力（JPEG 以外も必要なら条件分岐）
		imagejpeg($canvas, $this->dirs->datadir . "/upload/$outputfile", $quality);

		imagedestroy($image);
		imagedestroy($canvas);
	}

	function set_check_login($flg) {
		$this->flg_check_login = $flg;
	}

	function get_check_login() {
		return $this->flg_check_login;
	}

	function get_saved_file_path($filename) {
		return $this->get_saved_filepath($filename);
	}

	function get_saved_filepath($filename) {
		$filepath = $this->dirs->datadir . "/upload/$filename";
		return $filepath;
	}

	function res_csv($row_arr, $encode = "sjis-win", $ret = "\r\n", $quote = "") {

		foreach ($row_arr as $key => $value) {
			$value = (string)$value;
			$value = str_replace('"', '""', $value); // CSVの定石
			$value = '"' . $value . '"';
			$row_arr[$key] = mb_convert_encoding($value, $encode);
		}

		echo implode(",", $row_arr) . $ret;
		$this->stop_res = true;
	}


	function show_multi_dialog($dialog_name, $template, $title = "", $width = 600, $fixed_bar_template = null, $options = array()) {

		// 以前のオプションとの互換
		if ($fixed_bar_template === false || $fixed_bar_template === true) {
			$fixed_bar_template = null;
		}
		if ($options === false || $options === true) {
			$options = array();
		}
		if (!is_array($options)) {
			$options = array();
		}
		// modal option normalization
		if (isset($options["modal"]) && !isset($options["modal_flg"])) {
			$options["modal_flg"] = $options["modal"] ? 1 : 0;
		}
		if (!isset($options["modal_flg"])) {
			$options["modal_flg"] = 0;
		}
		$options["modal_flg"] = $options["modal_flg"] ? 1 : 0;
		
		if($this->testserver()){
			$title .= " " . $this->class . " / " . $this->POST("function");
		}

		$dialog_name = str_replace([" ", ".", "#"], "", $dialog_name);
		$screen_debug_key = $this->register_screen_debug_context($dialog_name, $template);

		$this->smarty->assign("dialog_name", $dialog_name);
		$this->smarty->assign("screen_debug_key", $screen_debug_key);

		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		$tmp = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");
		$fixed_bar = null;

		if ($fixed_bar_template != null) {
			$fixed_bar = $this->smarty->fetch($fixed_bar_template);
			$this->console_log("Template:" . $this->class . "/" . $fixed_bar_template, "#CE5C00");
		}

		$html = '<div class="class_style_' . $this->class . '">' . $tmp . '</div>';
		$md["dialog_name"] = $dialog_name;
		$md["html"] = $html;
			$md["title"] = $title;
			$md["width"] = $width;
			$md["fixed_bar"] = $fixed_bar;
			$md["options"] = $options;
			$md["testserver"] = $this->get_session("testserver");
			$md["forcopy"] = $this->class . "/" . $this->POST("function");
			$md["post"] = $_POST;
			$md["screen_debug_key"] = $screen_debug_key;
			$md["multi_dialog_zindex"] = isset($_POST["multi_dialog_zindex"]) ? $_POST["multi_dialog_zindex"] : null;
			$this->arr["multi_dialog"][] = $md;
		}

	function register_screen_debug_context($dialog_name, $template) {
		if ($this->class === "screen_debug_log") {
			return "";
		}
		try {
			$screen_key = "SK-" . strtoupper(bin2hex(random_bytes(4)));
		} catch (Throwable $e) {
			$screen_key = "SK-" . strtoupper(substr(md5(uniqid("", true)), 0, 8));
		}
		$this->set_session("screen_debug_context", [
			"screen_key" => $screen_key,
			"appcode" => (string) ($this->get_appcode() ?? ""),
			"app_class" => (string) $this->class,
			"app_function" => (string) ($_POST["function"] ?? $_GET["function"] ?? ""),
			"template" => (string) $template,
			"dialog_id" => (string) $dialog_name,
			"url" => (string) ($_SERVER["REQUEST_URI"] ?? ""),
			"get" => $_GET,
			"post" => $_POST,
			"files" => $this->screen_debug_files_meta($_FILES),
			"user_id" => (string) ($this->get_login_user_id() ?? ""),
			"user_name" => (string) ($this->get_login_name() ?? $this->get_login_id() ?? ""),
			"user_agent" => (string) ($_SERVER["HTTP_USER_AGENT"] ?? ""),
			"created_at" => time(),
		]);
		return $screen_key;
	}

	private function screen_debug_files_meta($files) {
		if (!is_array($files)) {
			return [];
		}
		$out = [];
		foreach ($files as $key => $file) {
			$out[$key] = $this->screen_debug_file_meta_item($file);
		}
		return $out;
	}

	private function screen_debug_file_meta_item($file) {
		if (!is_array($file)) {
			return [];
		}
		if (isset($file["name"]) && is_array($file["name"])) {
			$out = [];
			foreach ($file["name"] as $idx => $name) {
				$out[$idx] = [
					"name" => $name,
					"type" => $file["type"][$idx] ?? "",
					"size" => $file["size"][$idx] ?? 0,
					"error" => $file["error"][$idx] ?? 0,
				];
			}
			return $out;
		}
		return [
			"name" => $file["name"] ?? "",
			"type" => $file["type"] ?? "",
			"size" => $file["size"] ?? 0,
			"error" => $file["error"] ?? 0,
		];
	}

	function show_notification_text($txt, $time = 2, $background = "#4B70FF", $color = "#FFF", $fontsize = 24, $width = 600) {
		$style = "background:$background;color:$color;font-size:{$fontsize}px;";
		$md["html"] = '<div class="class_style_' . $this->class . '"><div class="fr_notification lang" style="' . $style . '">' . $txt . '</div></div>';
		$md["width"] = $width;
		$md["time"] = $time;
		$md["multi_dialog_zindex"] = isset($_POST["multi_dialog_zindex"]) ? $_POST["multi_dialog_zindex"] : null;
		$this->arr["notifications"][] = $md;
	}

	function show_notification($template, $width = 600, $time = 5) {

		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		$this->smarty->assign("screen_debug_key", $this->register_screen_debug_context($dialog_name, $template));
		$tmp = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");

		$html = '<div class="class_style_' . $this->class . '">' . $tmp . '</div>';
		$md["html"] = $html;
		$md["width"] = $width;
		$md["time"] = $time;
		$md["multi_dialog_zindex"] = $_POST["multi_dialog_zindex"] ?? null;
		$this->arr["notifications"][] = $md;
	}

	function show_sidemenu($template, $width = 300, $time = 200, $from = 'left') {
		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);

		$tmp = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");

		$html = '<div class="class_style_' . $this->class . '">' . $tmp . '</div>';
		$md["html"] = $html;
		$md["width"] = $width;
		$md["time"] = $time;
		$md["from"] = $from;
		$md["multi_dialog_zindex"] = $_POST["multi_dialog_zindex"] ?? null;
		$this->arr["sidemenu"][] = $md;
	}

	function close_sidemenu() {
		$this->arr["close_sidemenu"] = true;
	}

	function show_second_work_area($template, $width = 300) {
		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);

		$tmp = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");

		$html = '<div class="class_style_' . $this->class . '">' . $tmp . '</div>';
		$md["html"] = $html;
		$md["width"] = $width;
		$md["multi_dialog_zindex"] = $_POST["multi_dialog_zindex"] ?? null;
		$this->arr["second_work_area"][] = $md;
	}

	function close_second_work_area() {
		$this->arr["close_second_work_area"] = "close";
	}

	function show_popup($template, $width = 300, $height = 200) {
		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);

		$menu_file = $this->dirs->appdir_user . "/common/menu.tpl";
		if (is_file($menu_file)) {
			$this->assign('menu_file', $menu_file);
		}

		$this->smarty->setTemplateDir($this->dirs->get_class_dir($this->class) . "/Templates/");
		$tmp = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");

		$html = '<div class="class_style_' . $this->class . '">' . $tmp . '</div>';
		$md["html"] = $html;
		$md["width"] = $width;
		$md["height"] = $height;
		$md["multi_dialog_zindex"] = $_POST["multi_dialog_zindex"] ?? null;
		$this->arr["popup"][] = $md;
	}

	function show_main_area($template, $title, $o = null) {

		// 過去との整合性をとる
		if (!endsWith($template, ".tpl")) {
			$template = $title;
			$title = $o;
		}
		$dialog_name = "mainarea";

		// F5で再読込したときに自動表示
		if ($this->called_function == "page") {
			$alma = [
			    "class" => $this->class,
			    "function" => $this->called_function,
			    "parameters" => $this->called_parameters
			];
			$this->set_session("__AUTO_LOAD_MAIN_AREA", $alma);
		}

		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		$screen_debug_key = $this->register_screen_debug_context($dialog_name, $template);
		$this->smarty->assign("screen_debug_key", $screen_debug_key);
		$tmp = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");
		$html = '<div class="class_style_' . $this->class . '">' . $tmp . '</div>';
		$md["dialog_name"] = $dialog_name;
		$md["html"] = $html;
		$md["title"] = $title;
		$md["testserver"] = $this->get_session("testserver");
		$md["post"] = $_POST;
		$md["screen_debug_key"] = $screen_debug_key;
		$this->arr["work_area"] = $md;
	}

	function show_dashboard_widget($template, $column_width = null) {
		if ($column_width !== null) {
			$this->set_dashbord_column_width((int)$column_width);
		}

		$width = (int)$this->dashbord_column_width;
		if ($width < 1 || $width > 3) {
			$width = 1;
		}

		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		$tmp = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");
		$html = '<div class="class_style_' . $this->class . '">' . $tmp . '</div>';

		$this->dashbord_items[] = [
		    "column_width" => $width,
		    "html" => $html
		];
	}

	function set_dashbord_column_width($column_width) {
		$column_width = (int)$column_width;
		if ($column_width < 1 || $column_width > 3) {
			$column_width = 1;
		}
		$this->dashbord_column_width = $column_width;
	}

	function get_dashbord_items() {
		return $this->dashbord_items;
	}

	function reset_dashbord_items() {
		$this->dashbord_items = [];
		$this->dashbord_column_width = 1;
	}

	function ajax($class, $function, $post_arr = null) {

		if (is_array($post_arr)) {
			foreach ($post_arr as $key => $p) {
				if ($key == "class" || $key == "function" || $key == "cmd") {
					unset($post_arr[$key]);
				}
			}
		}

		$md = [];
		$md["class"] = $class;
		$md["function"] = $function;
		if ($post_arr == null) {
			$post_arr = array();
		}
		$md["post_arr"] = json_encode($post_arr);
		$md["cmd"] = "ajax";
		$this->arr["ajax"][] = $md;
	}

	function invoke($function, $post = null, $class = null) {
		if ($class == null) {
			$class = $this->class;
		}
		$this->ajax($class, $function, $post);
	}

	function close_multi_dialog($dialog_name, $class = null) {

		$md = [];
		$md["dialog_name"] = $dialog_name;
		if ($class == null) {
			$class = $this->class;
		}
		$md["class"] = $class;
		$md["cmd"] = "close";
		$this->arr["multi_dialog"][] = $md;
	}

	// nullを回避してインクリメントする
	function increment_post_value($key, $increment_value) {
		if ($this->POST($key) === "null") {
			return $increment_value;
		} else if (!is_numeric($this->POST($key))) {
			return $increment_value;
		} else {
			return $this->POST($key) + $increment_value;
		}
	}

	// Debug
	function get_debug_info() {
		
	}

	function var_dump($message, $obj = null) {
		$this->debug_obj[$message] = $obj;
	}

	function log($obj) {

		$this->debug_obj[] = $obj;

		$log_dir = $this->dirs->logdir;

		if (!is_dir($log_dir)) {
			mkdir($log_dir);
		}


		$file = $log_dir . "/log.txt";
		$fsize = @filesize($file);
		if ($fsize > 1000000) {
			$fp = fopen($file, "w");
		} else {
			$fp = fopen($file, "a");
		}

		flock($fp, LOCK_EX);
		fwrite($fp, date("Y/m/d H:i:s") . " ");
		if ($obj == null) {
			fwrite($fp, "NULL");
		} else {
			fwrite($fp, print_r($obj, true));
		}
		fwrite($fp, "\n");
		flock($fp, LOCK_UN);
		fclose($fp);
	}

	function get_appcode() {
		return $this->get_session("appcode");
	}

	function get_setting() {
		$setting = $_SESSION[$this->windowcode]["setting"] ?? null;
		if (is_array($setting)) {
			if (empty($setting["error_report_level"])) {
				$setting["error_report_level"] = "legacy_compatible";
				$_SESSION[$this->windowcode]["setting"] = $setting;
			}
			return $setting;
		}
		$setting = $this->db("setting", "setting")->get(1);
		if (!is_array($setting)) {
			$setting = [];
		}
		if (empty($setting["error_report_level"])) {
			$setting["error_report_level"] = "legacy_compatible";
		}
		$_SESSION[$this->windowcode]["setting"] = $setting;
		return $setting;
	}

	function save_setting($setting) {
		if (!is_array($setting)) {
			$setting = [];
		}
		if (empty($setting["error_report_level"])) {
			$setting["error_report_level"] = "legacy_compatible";
		}
		$_SESSION[$this->windowcode]["setting"] = $setting;
		$this->db("setting", "setting")->update($setting);
	}

	function generate_api_credentials() {
		$setting = $this->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}

		$changed = false;
		if (empty($setting["api_key"])) {
			$setting["api_key"] = $this->generate_secure_hex(24);
			$changed = true;
		}
		if (empty($setting["api_secret"])) {
			$setting["api_secret"] = $this->generate_secure_hex(48);
			$changed = true;
		}

		if ($changed) {
			$this->save_setting($setting);
		}

		return $setting;
	}

	function get_release_api_credentials() {
		$setting = $this->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}
		return [
			"api_key" => (string) ($setting["release_api_key"] ?? ""),
			"api_secret" => (string) ($setting["release_api_secret"] ?? ""),
		];
	}

	function verify_api_request() {
		$setting = $this->generate_api_credentials();
		return $this->verify_hmac_request(
			(string) ($setting["api_key"] ?? ""),
			(string) ($setting["api_secret"] ?? "")
		);
	}

	function verify_release_api_request() {
		$cred = $this->get_release_api_credentials();
		if ($cred["api_key"] !== "" && $cred["api_secret"] !== "") {
			return $this->verify_hmac_request($cred["api_key"], $cred["api_secret"]);
		}
		return $this->verify_api_request();
	}

	function get_server_error_report_settings() {
		$settings = [
			"url" => $this->get_env_value("FBP_SERVER_ERROR_REPORT_URL"),
			"api_key" => $this->get_env_value("FBP_SERVER_ERROR_API_KEY"),
			"api_secret" => $this->get_env_value("FBP_SERVER_ERROR_API_SECRET"),
		];
		$settings["configured"] = ($settings["url"] !== "" && $settings["api_key"] !== "" && $settings["api_secret"] !== "");
		return $settings;
	}

	function verify_server_error_report_request() {
		$settings = $this->get_server_error_report_settings();
		return $this->verify_hmac_request(
			(string) ($settings["api_key"] ?? ""),
			(string) ($settings["api_secret"] ?? "")
		);
	}

	function report_server_error(Throwable $e) {
		try {
			$settings = $this->get_server_error_report_settings();
			$report_url = (string) ($settings["url"] ?? "");
			$api_key = (string) ($settings["api_key"] ?? "");
			$api_secret = (string) ($settings["api_secret"] ?? "");
			if ($report_url === "" || $api_key === "" || $api_secret === "") {
				return [
					"configured" => false,
					"reported" => false,
					"id" => null,
					"public_url" => "",
				];
			}
			if (!empty($_SERVER["HTTP_X_FBP_ERROR_REPORT"])) {
				return [
					"configured" => true,
					"reported" => false,
					"id" => null,
					"public_url" => "",
				];
			}
			if (($this->GET("class") ?? $this->POST("class")) === "server_error_api"
				&& ($this->GET("function") ?? $this->POST("function")) === "report") {
				return [
					"configured" => true,
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
				"app_name" => basename((string) $this->dirs->basedir),
				"app_code" => (string) $this->get_appcode(),
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
				"post" => $this->sanitize_server_error_value($_POST),
				"get" => $this->sanitize_server_error_value($_GET),
				"session_user_id" => $this->sanitize_server_error_value($this->get_login_user_id()),
				"session_login_id" => $this->sanitize_server_error_value($this->get_login_id()),
				"remote_addr" => (string) ($_SERVER["REMOTE_ADDR"] ?? ""),
				"user_agent" => (string) ($_SERVER["HTTP_USER_AGENT"] ?? ""),
			];
			$payload["error_hash"] = hash(
				"sha256",
				implode("\n", [
					(string) $payload["app_name"],
					(string) $payload["class_name"],
					(string) $payload["function_name"],
					(string) $payload["exception_class"],
					(string) $payload["message"],
					(string) $payload["file_path"],
					(string) $payload["line_no"],
				])
			);

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
		} catch (Throwable $report_error) {
			return [
				"configured" => true,
				"reported" => false,
				"id" => null,
				"public_url" => "",
			];
		}
	}

	private function verify_hmac_request($expected_api_key, $expected_api_secret) {
		if ($expected_api_key === "" || $expected_api_secret === "") {
			return $this->res_unauthorized_api_request();
		}

		$api_key = isset($_SERVER["HTTP_X_API_KEY"]) ? trim($_SERVER["HTTP_X_API_KEY"]) : "";
		$api_ts = isset($_SERVER["HTTP_X_API_TS"]) ? trim($_SERVER["HTTP_X_API_TS"]) : "";
		$api_sign = isset($_SERVER["HTTP_X_API_SIGN"]) ? strtolower(trim($_SERVER["HTTP_X_API_SIGN"])) : "";

		if ($api_key === "" || $api_ts === "" || $api_sign === "") {
			return $this->res_unauthorized_api_request();
		}

		if (!hash_equals((string) $expected_api_key, $api_key)) {
			return $this->res_unauthorized_api_request();
		}

		if (!ctype_digit($api_ts)) {
			return $this->res_unauthorized_api_request();
		}

		$ts = (int) $api_ts;
		if (abs(time() - $ts) > 300) {
			return $this->res_unauthorized_api_request();
		}

		$method = isset($_SERVER["REQUEST_METHOD"]) ? strtoupper($_SERVER["REQUEST_METHOD"]) : "";
		$request_uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
		$path = (string) parse_url($request_uri, PHP_URL_PATH);
		$query = isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : "";

		$canonical = $method . "\n" . $path . "\n" . $query . "\n" . $api_ts;
		$expected_sign = hash_hmac("sha256", $canonical, (string) $expected_api_secret);

		if (!hash_equals($expected_sign, $api_sign)) {
			return $this->res_unauthorized_api_request();
		}

		return true;
	}

	private function get_env_value($name) {
		$value = $_SERVER[$name] ?? getenv($name);
		if (!is_string($value)) {
			return "";
		}
		return trim($value);
	}

	private function sanitize_server_error_value($value, $key = "", $depth = 0) {
		if ($depth > 5) {
			return "[depth_limit]";
		}
		$key_lower = strtolower((string) $key);
		$mask_words = ["password", "passwd", "pwd", "secret", "token", "api_key", "api_secret", "authorization"];
		foreach ($mask_words as $word) {
			if ($key_lower !== "" && strpos($key_lower, $word) !== false) {
				return "[masked]";
			}
		}
		if (is_array($value)) {
			$res = [];
			foreach ($value as $child_key => $child_value) {
				$res[$child_key] = $this->sanitize_server_error_value($child_value, (string) $child_key, $depth + 1);
			}
			return $res;
		}
		if (is_object($value)) {
			return "[object " . get_class($value) . "]";
		}
		if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
			return $value;
		}
		$text = (string) $value;
		if (strlen($text) > 5000) {
			$text = substr($text, 0, 5000) . "...[truncated]";
		}
		return $text;
	}

	private function generate_secure_hex($length) {
		$size = (int) ceil($length / 2);
		return substr(bin2hex(random_bytes($size)), 0, $length);
	}

	private function res_unauthorized_api_request() {
		http_response_code(401);
		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode([
			"ok" => false,
			"error" => "Unauthorized API request"
		]);
		return false;
	}

	function get_login_name() {
		return $_SESSION[$this->windowcode]["name"] ?? null;
	}

	function get_login_id() {
		return $_SESSION[$this->windowcode]["login_id"] ?? null;
	}

	function get_login_user_id() {
		return $_SESSION[$this->windowcode]["user_id"] ?? null;
	}

	function get_login_type() {
		return $_SESSION[$this->windowcode]["type"] ?? null;
	}

	function is_app_admin(): bool {
		return !empty($_SESSION[$this->windowcode]["app_admin"]);
	}

	function has_developer_permission(): bool {
		return (int) ($_SESSION[$this->windowcode]["developer_permission"] ?? 0) === 1;
	}

	function has_data_manager_permission(): bool {
		return (int) ($_SESSION[$this->windowcode]["data_manager_permission"] ?? 0) === 1;
	}

	function authorize_management_access(string $class, string $function): bool {
		$admin_only_classes = [
			"setting",
			"user",
		];
		if (in_array($class, $admin_only_classes, true)) {
			return $this->is_app_admin();
		}

		$developer_or_admin_classes = [
			"cron",
			"dashboard",
			"db",
			"db_additionals",
			"email_format",
			"embed_app",
			"panel_constants",
			"public_assets",
			"public_pages_registry",
			"release",
			"webhook_rule",
		];
		if (in_array($class, $developer_or_admin_classes, true)) {
			return $this->is_app_admin() || $this->has_developer_permission();
		}

		$data_manager_or_admin_classes = [
			"restore",
		];
		if (in_array($class, $data_manager_or_admin_classes, true)) {
			return $this->is_app_admin() || $this->has_data_manager_permission();
		}

		if ($class === "panel") {
			if ($function === "release_backup") {
				return $this->is_app_admin() || $this->has_data_manager_permission();
			}
			return $this->is_app_admin() || $this->has_developer_permission();
		}

		return true;
	}

	function deny_forbidden_access(): void {
		http_response_code(403);
		$message = "Forbidden";
		if ($_SERVER["REQUEST_METHOD"] === "POST") {
			header("Content-Type: application/json; charset=UTF-8");
			echo json_encode([
				"ok" => false,
				"error" => $message,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit;
		}

		header("Content-Type: text/plain; charset=UTF-8");
		echo $message;
		exit;
	}

	function get_user_db() {
		return $this->db("user", "user");
	}

	function show_vimeo_uploader($dialog_name, $callback_class_name, $callback_function_name, $callback_parameter_array) {

		$setting = $this->get_setting();
		$vimeo_access_token = $setting["vimeo_access_token"];

		$this->smarty->assign("callback_class_name", $callback_class_name);
		$this->smarty->assign("callback_function_name", $callback_function_name);
		$this->smarty->assign("callback_parameter_array", base64_encode(json_encode($callback_parameter_array)));
		$this->append_res_data("vimeo_access_token", $vimeo_access_token);
		$this->show_multi_dialog($dialog_name, dirname(__FILE__) . "/../Templates/vimeo_upload_area.tpl", "Vimeo Uploader");
	}

	function get_vimeo_id_uploaded() {
		return $_POST["vimeo_id"] ?? null;
	}

	function get_vimeo_title_uploaded() {
		return $_POST["vimeo_title"] ?? null;
	}

	function get_vimeo_description_uploaded() {
		return $_POST["vimeo_description"] ?? null;
	}

	function get_vimeo_callback_parameter_array() {
		$callback_parameter_array = $_POST["callback_parameter_array"] ?? "";
		if ($callback_parameter_array !== "") {
			return json_decode(base64_decode($callback_parameter_array), true);
		} else {
			return null;
		}
	}

	//Vimeoのサムネイルを取得する
	function get_vimeo_thumbnail($vimeo_id) {
		if (is_numeric($vimeo_id) && $vimeo_id > 0) {
			$url = "https://vimeo.com/api/oembed.json?url=https%3A//vimeo.com/" . $vimeo_id;

			$json = @file_get_contents($url);
			if (!empty($json)) {
				$arr = json_decode($json, true);
				return $arr["thumbnail_url"];
			}
		}
	}

	function delete_vimeo($vimeo_id) {
		$vimeo = $this->create_vimeo();
		return $vimeo->delete($vimeo_id);
	}

	function get_lang() {
		return "jp";
	}

	function t($key, $params = [], $lang = null) {
		$i18n = new I18nSimple($this);
		return $i18n->translate((string) $key, is_array($params) ? $params : [], $lang);
	}

	function set_square($square_application_id = null, $square_access_token = null, $square_location_id = null) {
		if (!class_exists("mysquare")) {
			include(dirname(__FILE__) . "/../lib/mysquare.php");
		}
		if ($square_application_id == null || $square_access_token == null || $square_location_id == null) {
			$setting = $this->get_setting();
			$this->square_application_id = $setting["square_application_id"];
			$this->square_access_token = $setting["square_access_token"];
			$this->square_location_id = $setting["square_location_id"];
		} else {
			$this->square_application_id = $square_application_id;
			$this->square_access_token = $square_access_token;
			$this->square_location_id = $square_location_id;
		}
		$this->flg_square = true;
	}

	function get_square() {
		return $this->flg_square;
	}

	function show_square_dialog($callback_class_name, $callback_function_name, $callback_parameter_array, $error_msg = "", $amount = "") {
		$dialog_name = "SQUARE_DIALOG";

		if (!class_exists("mysquare")) {
			$this->set_square();
		}

		$this->smarty->assign("name", $callback_parameter_array["name"] ?? "");
		$this->smarty->assign("email", $callback_parameter_array["email"] ?? "");
		$this->smarty->assign("address", $callback_parameter_array["address"] ?? "");

		$this->smarty->assign("dialog_name", $dialog_name);
		$this->smarty->assign("square_application_id", $this->square_application_id);
		$this->smarty->assign("square_location_id", $this->square_location_id);
		$this->smarty->assign("callback_class", $callback_class_name);
		$this->smarty->assign("callback_function", $callback_function_name);
		$this->smarty->assign("callback_parameter_array", base64_encode(json_encode($callback_parameter_array)));
		if ($error_msg != "") {
			$error_msg = "An error is occured. Please try again.<br>" . $error_msg;
		}
		$settings = $this->get_setting();
		$this->smarty->assign("error", $error_msg);
		$this->smarty->assign("currency", $settings['currency']);
		$this->smarty->assign("amount", $amount);
		$this->smarty->assign("public", $this->POST("public"));

		$this->show_multi_dialog($dialog_name, dirname(__FILE__) . "/../Templates/square.tpl", "SQUARE");
	}

	function square_show_dialog($amount, $callback_function, $error_message = "") {
		if (!class_exists("mysquare")) {
			$this->set_square();
		}

		$this->smarty->assign("square_application_id", $this->square_application_id);
		$this->smarty->assign("square_location_id", $this->square_location_id);
		$this->smarty->assign("callback_class", $this->class);
		$this->smarty->assign("callback_function", $callback_function);
		$this->smarty->assign("callback_parameter_array", base64_encode(json_encode([])));
		$settings = $this->get_setting();
		$this->smarty->assign("error", $error_message);
		$this->smarty->assign("currency", $settings['currency']);
		$this->smarty->assign("amount", $amount);

		$this->show_multi_dialog("SQUARE_DIALOG", dirname(__FILE__) . "/../Templates/square.tpl", "SQUARE");
	}

	function close_square_dialog() {
		$this->close_multi_dialog("SQUARE_DIALOG");
	}

	function square_close_dialog() {
		$this->close_multi_dialog("SQUARE_DIALOG");
	}

	function square_regist_customer($name, $email, $address, $locality = "Japan", $country = "JP"): ?string {
		try {
			if (!class_exists("mysquare")) {
				$this->set_square();
			}

			$mysquare = new mysquare($this->square_access_token, $this->get_session("testserver"));
			return $mysquare->regist_customer($name, $email, $address, $locality, $country);
		} catch (Exception $e) {
			$this->square_error = $e->getMessage();
			return null;
		}
	}

	function square_regist_card($square_customer_id): ?string {
		try {
			$nonce = $_POST["nonce"] ?? null;
			if (!class_exists("mysquare")) {
				$this->set_square();
			}
			$mysquare = new mysquare($this->square_access_token, $this->get_session("testserver"));
			return $mysquare->regist_card($square_customer_id, $nonce);
		} catch (Throwable $e) {
			$this->square_error = $e->getMessage();
			return null;
		}
	}

	function square_payment($square_customer_id, $card_id, $price, $currency = null): bool {
		try {
			if (!class_exists("mysquare")) {
				$this->set_square();
			}
			$settings = $this->get_setting();
			if ($currency == null) {
				$currency = $settings['currency'];
			}
			if (empty($currency)) {
				$currency = "JPY";
			}
			$mysquare = new mysquare($this->square_access_token, $this->get_session("testserver"));
			$result = $mysquare->payment($square_customer_id, $card_id, $price, $currency);

			if (!$result) {
				$this->square_error = $mysquare->get_error();
				return false;
			} else {
				return true;
			}
		} catch (Exception $e) {
			$this->square_error = $e->getMessage();
			return false;
		}
	}

	function square_get_error(): ?string {
		return $this->square_error;
	}

	function get_square_callback_parameter_array() {
		$callback_parameter_array = $_POST["callback_parameter_array"] ?? "";
		if ($callback_parameter_array !== "") {
			return json_decode(base64_decode($callback_parameter_array), true);
		} else {
			return null;
		}
	}

	function get_email_template_list($add_empty_data = true) {
		$ffm = $this->db("email_format", "email_format");
		$list = $ffm->getall("key", SORT_ASC);
		$arr = [];
		if ($add_empty_data) {
			$arr[""] = "";
		}
		foreach ($list as $d) {
			$arr[$d["key"]] = $d["template_name"];
		}
		return $arr;
	}

	function send_mail_prepared_format($to, $format_key, $attachment_files = null, $default_subject = "", $default_template = null) {

		$setting = $this->get_setting();
		if (empty($setting["smtp_from"])) {
			throw new Exception("You must set Mail Address (for from) on the setting.");
		}

		$ffm_email_format = $this->db("email_format", "email_format");
		$email_format_list = $ffm_email_format->select("key", $format_key);
		if (count($email_format_list) == 0) {
			if (empty($default_subject) || $default_template == null) {
				throw new Exception("There is no email_format which key is " . $format_key . ". You can set \$default_subject and \$default_template to send_mail_prepared_format.");
			} else {
				$dir = new Dirs();
				$arr = array();
				$arr["key"] = $format_key;
				$arr["template_name"] = $default_subject;
				$arr["subject"] = $default_subject;

				$template_path = $this->dirs->get_class_dir($this->class) . "/Templates/" . $default_template;
				$arr["body"] = file_get_contents($template_path);
				$ffm_email_format->insert($arr);
				$email_format = $arr;
			}
		} else {
			$email_format = $email_format_list[0];
		}

		$subject = $this->fetch_string($email_format["subject"]);
		$body = $this->fetch_string($email_format["body"]);
		$this->send_mail_string(null, $to, $subject, $body, $attachment_files);
	}

	function get_mail_body_prepared_format($format_key) {
		$ffm_email_format = $this->db("email_format", "email_format");
		$email_format_list = $ffm_email_format->select("key", $format_key);
		if ($email_format_list > 0) {
			$email_format = $email_format_list[0];
			$body = $this->fetch_string($email_format["body"]);
			return $body;
		} else {
			throw new Exception("There is no email_format which key is " . $format_key);
		}
	}

	function send_mail($from, $to, $subject, $template, $attachment_files = null, $throw_on_error = false) {
		$body = $this->smarty->fetch($template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");
		$this->send_mail_string($from, $to, $subject, $body, $attachment_files, $throw_on_error);
	}

	function send_mail_text($to, $subject, $body, $attachment_files = null, $throw_on_error = false) {
		$this->send_mail_string(null, $to, $subject, $body, $attachment_files, $throw_on_error);
	}

	function send_mail_string($from, $to, $subject, $body, $attachment_files = null, $throw_on_error = false) {

		$this->console_log("### MAIL ###");
		$to_log = is_array($to) ? implode(", ", array_values($to)) : (string) $to;
		$this->console_log("To:" . $to_log);
		$this->console_log("Subject:" . $subject);
		$this->console_log($body);

		require_once(dirname(__FILE__) . '/../lib_ext/phpmailer/PHPMailer.php');
		require_once(dirname(__FILE__) . '/../lib_ext/phpmailer/Exception.php');
		require_once(dirname(__FILE__) . '/../lib_ext/phpmailer/SMTP.php');

		$setting = $this->get_setting();

		if ($from == null) {
			if (empty($setting["smtp_from"])) {
				throw new Exception("You must set Mail Address (for from) on the setting.");
			}
			$from = $setting["smtp_from"];
		}

		$email = new PHPMailer\PHPMailer\PHPMailer(true);
		$email->CharSet = 'utf-8';
		$email->isSMTP();
		$email->Host = $setting["smtp_server"];
		$email->SMTPAuth = true;
		$email->Username = $setting["smtp_user"];
		$email->Password = $setting["smtp_password"];
		if ($setting["smtp_secure"] == 1) {
			$email->SMTPSecure = "tls";
		} else if ($setting["smtp_secure"] == 2) {
			$email->SMTPSecure = "ssl";
		} else {
			$email->SMTPSecure = false;
		}
		$email->Port = $setting["smtp_port"];

		try {
			$email->SetFrom($from);
			if (is_array($to)) {
				foreach ($to as $key => $value) {
					$email->addBCC($value);
				}
			} else {
				$email->addAddress($to);
			}

			$email->Subject = $subject;
			$email->Body = $body;

			if ($attachment_files != null) {
				if (is_array($attachment_files)) {
					foreach ($attachment_files as $f) {
						$email->addAttachment($this->dirs->datadir . "/upload/" . $f);
					}
				} else {
					$email->addAttachment($this->dirs->datadir . "/upload/" . $attachment_files);
				}
			}

			$email->Send();
		} catch (\PHPMailer\PHPMailer\Exception $e) {
			if ($this->testserver() || $throw_on_error) {
				$details = [
					$e->getMessage(),
					"PHPMailer ErrorInfo: " . (string) $email->ErrorInfo,
					"SMTP host: " . (string) ($setting["smtp_server"] ?? ""),
					"SMTP port: " . (string) ($setting["smtp_port"] ?? ""),
					"SMTP secure: " . (string) ($setting["smtp_secure"] ?? ""),
					"SMTP user: " . (string) ($setting["smtp_user"] ?? ""),
				];
				throw new Exception(implode("\n", array_filter($details)));
			}
		} catch (\Throwable $e) {
			if ($this->testserver() || $throw_on_error) {
				throw $e;
			}
		}
	}

	function add_css_public($class) {
		$this->add_css_public[] = $class;
	}

	function testserver() {
		return $this->get_session("testserver");
	}

	function encrypt($str) {
		if ($str == null) {
			return null;
		}
		$setting = $this->get_setting();
		if($this->mcrypt == null){
			include_once(dirname(__FILE__) . "/Mcrypt.php");
			$this->mcrypt = new Mcrypt($setting["secret"], $setting["iv"]);
		}
		
		return $this->mcrypt->encrypt($str);
	}

	function decrypt($encrypt) {
		$setting = $this->get_setting();
		if ($setting == null) {
			throw new Exception("Setting is null");
		}
		if($this->mcrypt == null){
			include_once(dirname(__FILE__) . "/Mcrypt.php");
			$this->mcrypt = new Mcrypt($setting["secret"], $setting["iv"]);
		}
		return $this->mcrypt->decrypt($encrypt);
	}

	function is_public_class($className = null) {
		if ($className === null || $className === "") {
			$className = $this->class;
		}
		return is_string($className) && strpos($className, "public_") === 0;
	}

	function is_public_media_class($className = null) {
		if ($className === null || $className === "") {
			$className = $this->class;
		}
		if (!is_string($className) || $className === "") {
			return false;
		}
		if ($this->is_public_class($className)) {
			return true;
		}
		return in_array($className, ["agency_portal", "agency_portal_support"], true);
	}

	function get_public_media_token($path, $filename = "", $mode = "file") {
		$path = trim((string) $path);
		if ($path === "") {
			return "";
		}
		$payload = [
		    "path" => $path,
		    "filename" => (string) $filename,
		    "mode" => (string) $mode,
		];
		return urldecode($this->encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
	}

	function get_public_media_url($path, $filename = "", $mode = "file") {
		$token = $this->get_public_media_token($path, $filename, $mode);
		if ($token === "") {
			return "";
		}
		$function = $mode === "image" ? "view_image" : "download_file";
		return $this->get_APP_URL("public_media", $function, ["token" => $token]);
	}

	function fetch_string($str) {
		return $this->fetch("string:" . $str);
	}

	function login_node($room_name, $group_name, $name) {
		$md["room_name"] = $room_name;
		$md["group_name"] = $group_name;
		$md["user_id"] = $user_id;
		$this->arr["login_node"] = $md;

		$this->node_login = true;
	}

	function send_to_node($data, $room_name = "", $group_name = "", $user_id = "") {

		if (!$this->node_login) {
			throw new Exception("You must call login_node before calling send_to_node");
		}

		$md["data"] = $data;
		$md["room_name"] = $room_name;
		$md["group_name"] = $group_name;
		$md["user_id"] = $user_id;
		$this->arr["send_to_node"][] = $md;
	}

	function close_all_dialog($exception = null) {
		if ($exception != null) {
			$dialog_name = str_replace([" ", ".", "#"], "", $exception);
			$this->arr["close_all_dialog"]["exception"] = $dialog_name;
		} else {
			$this->arr["close_all_dialog"]["exception"] = "";
		}
	}

	function send_file_to_node($filename, $room_name = "", $group_name = "", $user_id = "") {
		if ($this->is_saved_file($filename)) {
			$filepath = $this->dirs->datadir . "/upload/$filename";
			$d = file_get_contents($filepath);
			$base64 = base64_encode($d);
			$data["file"] = $base64;
			$data["filename"] = $filename;
			$this->send_to_node($data, $room_name, $group_name, $user_id);
		} else {
			throw new Exception("file is not exist: " . $filename);
		}
	}

	function send_pdf_to_node($pdf_template, $pdf_filename, $room_name = "", $group_name = "", $user_id = "") {
		include_once(dirname(__FILE__) . "/pdfmaker/pdfmaker_class.php");

		$imgdir = [$this->dirs->get_class_dir($this->class) . "/images/", $this->dirs->datadir . "/upload/"];

		$txt = $this->smarty->fetch($pdf_template);
		$this->console_log("Template:" . $this->class . "/" . $template, "#CE5C00");

		$pdfmaker = new pdfmaker_class();
		$pdfmaker->makepdf($txt, $imgdir, $this->dirs->datadir . "/upload/" . $pdf_filename, "F");

		$this->send_file_to_node($pdf_filename, $room_name, $group_name, $user_id);

		$this->remove_saved_file($pdf_filename);
	}

	function markdown_to_html($text) {
		include_once(dirname(__FILE__) . "/../lib_ext/markdown/Parsedown.php");
		$Parsedown = new Parsedown();
		$html = $Parsedown->text($text);

		return $html;
	}

	function badge($id, $val) {
		$md = [];
		$md["id"] = $id;
		$md["val"] = $val;
		$this->arr["badge"][] = $md;
	}

	function set_called_function($name) {
		$this->called_function = $name;
	}

	function get_called_function() {
		return $this->called_function;
	}

	function map($tag_id = "google_map", $lat = 35.6947818, $lng = 139.7763998, $zoom = 0) {
		$md = [];
		$md["tag_id"] = $tag_id;
		$md["lat"] = (float) $lat;
		$md["lng"] = (float) $lng;
		$md["zoom"] = $zoom;
		$this->arr["map"] = $md;
	}

	function map_add_marker($location, $html) {
		$md = [];
		$ex = explode(",", $location);
		$lat = str_replace("(", "", $ex[0]);
		$lng = str_replace([")", " "], "", $ex[1]);
		$md["location"] = ["lat" => (float) $lat, "lng" => (float) $lng];
		$md["html"] = $html;
		$this->arr["map_marker"][] = $md;
	}

	function get_classname() {
		return $this->class;
	}

	function add_tab($dialog_name, $tabname, $title, $selected, $post_arr) {

		$dialog_name = str_replace([" ", ".", "#"], "", $dialog_name);

		$md = ["dialog_name" => $dialog_name,
		    "tabname" => $tabname,
		    "title" => $title,
		    "selected" => $selected,
		    "post_arr" => $post_arr
		];
		$this->arr["add_tab"][] = $md;
	}

	function qrcode_text_binary($text, $level = 'L', $size = 3, $margin = 4) {

		require_once __DIR__ . '/../lib_ext/phpqrcode/qrlib.php';
		// Remove old qr-code-text*.png files for a while after the migration, then delete this cleanup.
		$this->cleanup_legacy_qrcode_text_files();

		ob_start();
		QRcode::png($text, false, $level, $size, $margin);
		$image_data = ob_get_clean();

		if ($image_data === false) {
			return "";
		}

		return $image_data;
	}

	private function cleanup_legacy_qrcode_text_files() {
		$upload_dir = $this->dirs->datadir . "/upload";
		if (!is_dir($upload_dir)) {
			return;
		}

		foreach (glob($upload_dir . "/qr-code-text*.png") as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
	}

	private function save_qrcode_png($content, $prefix, $level = 'L', $size = 3) {
		require_once __DIR__ . '/../lib_ext/phpqrcode/qrlib.php';
		$upload_dir = $this->dirs->datadir . "/upload";

		if (!is_dir($upload_dir)) {
			mkdir($upload_dir);
		}

		$filename = $prefix . hash('sha256', $content) . '.png';
		$filePath = $upload_dir . "/" . $filename;

		if (!file_exists($filePath)) {
			QRcode::png($content, $filePath, $level, $size);
		}

		return $filename;
	}

	function res_json($array) {
		//$data = json_encode($array, JSON_PRETTY_PRINT);
		$data = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		header('Content-Type: application/json');
		header('Expires: 0'); //No caching allowed
		header('Cache-Control: must-revalidate');
		header('Content-Length: ' . strlen($data));
		file_put_contents('php://output', $data);

		exit();
	}

	function google_calendar_link($timestamp_start, $timestamp_end, $title, $description, $location = "", $timezone = "") {

		if ($timezone == "") {
			$timezone = $this->POST("_timezone");
		}

		$timezone_list = DateTimeZone::listIdentifiers();
		if (in_array($timezone, $timezone_list)) {
			if (!empty($timestamp_start) && !empty($timestamp_end) && !empty($title)) {
				if (is_int($timestamp_start)) {
					$format_timestamp_start = date("Ymd", $timestamp_start) . "T" . date("His", $timestamp_start);
				} else {
					$format_timestamp_start = date("Ymd", strtotime($timestamp_start)) . "T" . date("His", strtotime($timestamp_start));
				}
				if (is_int($timestamp_end)) {
					$format_timestamp_end = date("Ymd", $timestamp_end) . "T" . date("His", $timestamp_end);
				} else {
					$format_timestamp_end = date("Ymd", strtotime($timestamp_end)) . "T" . date("His", strtotime($timestamp_end));
				}

				$url = "https://calendar.google.com/calendar/r/eventedit?text=" . urlencode($title) . "&details=" . urlencode($description) . "&location=" . urlencode($location) . "&dates=" . urlencode($format_timestamp_start) . "/" . urlencode($format_timestamp_end) . "&ctz=" . urlencode($timezone);
				return $url;
			} else {
				throw new Exception("Please check parameter values. ");
			}
		} else {
			throw new Exception("Timezone does not exist.");
		}
	}

	function unzip($filename, $subdir) {
		if (empty($filename) || empty($subdir))
			return false;

		$filename = $this->dirs->datadir . "/upload/" . $filename;
		$dir = $this->dirs->datadir . "/upload/" . $subdir;

		$zip = new ZipArchive;
		$res = $zip->open($filename);
		if ($res === TRUE) {
			if (!file_exists($dir))
				mkdir($dir, 0755);

			$zip->extractTo($dir);
			$zip->close();
			return true;
		} else {
			return false;
		}
	}

	function get_file_list($subdir = null, $recursive = false, $flags = 0) {
		$dir = $this->dirs->datadir . "/upload/" . $subdir;

		if ($recursive) {
			$files = $this->glob_recursive($dir . "/*", $flags);
		} else {
			$files = array_diff(scandir($dir), array('.', '..'));
		}

		return $files;
	}

	function glob_recursive($full_path, $flags = 0) {
		$files = glob(dirname($full_path) . "/*.{*}", GLOB_BRACE);
		foreach (glob(dirname($full_path) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
			$files = array_merge($files, $this->glob_recursive($dir . '/' . basename($full_path), $flags));
		}
		return $files;
	}

	function delete_folder($subdir) {
		if (empty($subdir))
			return false;

		$upload_dir = $this->dirs->datadir . "/upload/";
		$dir = $upload_dir . $subdir;
		$files = glob($dir . '/*');
		foreach ($files as $file) {
			is_dir($file) ? $this->delete_folder(str_replace($upload_dir, "", $file)) : unlink($file);
		}
		return rmdir($dir);
	}

	function random_number($length = 8) {
		return substr(str_shuffle("0123456789"), 0, $length);
	}

	function random_alphabet($length = 8) {
		return substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz"), 0, $length);
	}

	function random_password($length = 8) {
		return substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz0123456789!@#$%&:/"), 0, $length);
	}

	function translate($q, $language_source = "ja", $language_target = "en") {
		$setting = $this->get_setting();

		if (empty($setting["api_key_map"])) {
			throw new Exception("Please set Google API KEY");
		}

		$url = "https://translation.googleapis.com/language/translate/v2";
		$data = [
		    'key' => $setting["api_key_map"],
		    'q' => $q,
		    'target' => $language_target,
		    'source' => $language_source
		];
		$postdata = http_build_query($data);
		$header = array(
		    "Content-Type: application/x-www-form-urlencoded",
		);
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : "";
		if ($origin !== "") {
			$header[] = "Referer: " . $origin;
		}
		$opts = array('http' =>
		    array(
			'method' => 'POST',
			'header' => implode("\r\n", $header),
			'content' => $postdata
		    )
		);
		$context = stream_context_create($opts);
		$result_json = file_get_contents($url, false, $context);
		$responseData = json_decode($result_json, TRUE);

		return $responseData['data']['translations'][0]['translatedText'];
	}

	function text_to_speech($text, $filename_mp3, $lang = 'en-US', $voice = '', $pitch = 1, $speed = 1) {

		$text = trim($text);

		if (empty($text)) {
			return false;
		}

		$params = [
		    "audioConfig" => [
			"audioEncoding" => "LINEAR16",
			"pitch" => $pitch,
			"speakingRate" => $speed,
			"effectsProfileId" => [
			    "medium-bluetooth-speaker-class-device"
			]
		    ],
		    "input" => [
			"text" => $text
		    ],
		    "voice" => [
			"languageCode" => $lang, //ja-JP
			"name" => $voice //en-US-Wavenet-F
		    ]
		];

		$data_string = json_encode($params);

		$setting = $this->get_setting();

		$url = 'https://texttospeech.googleapis.com/v1/text:synthesize?fields=audioContent&key=' . $setting["api_key_map"];
		$handle = curl_init($url);

		curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($handle, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt(
			$handle,
			CURLOPT_HTTPHEADER,
			array_filter([
			    'Content-Type: application/json',
			    'Content-Length: ' . strlen($data_string),
			    (!empty($_SERVER['HTTP_ORIGIN']) ? 'Referer: ' . $_SERVER['HTTP_ORIGIN'] : null),
			])
		);
		$response = curl_exec($handle);
			$responseDecoded = json_decode($response, true);
		if ($responseDecoded['audioContent']) {
			$speech_data = $responseDecoded['audioContent'];

			$path = $this->dirs->datadir . "/upload/";
			file_put_contents($path . $filename_mp3, base64_decode($speech_data));
		} else {
			throw new Exception($response);
		}

		return false;
	}

	function strtotime($str, $timezone) {

		// check
		$arr = DateTimeZone::listIdentifiers();
		if (!in_array($timezone, $arr)) {
			throw new Exception("Timezone name is wrong : " . $timezone);
		}

		$moto = date_default_timezone_get();
		date_default_timezone_set($timezone);
		$ret = strtotime($str);
		date_default_timezone_set($moto);
		return $ret;
	}

	function date($format, $timestamp, $timezone = "UTC") {

		// check
		$arr = DateTimeZone::listIdentifiers();
		if (!in_array($timezone, $arr)) {
			throw new Exception("Timezone name is wrong : " . $timezone);
		}

		$moto = date_default_timezone_get();
		date_default_timezone_set($timezone);
		$ret = date($format, $timestamp);
		date_default_timezone_set($moto);
		return $ret;
	}

	private function ai_render_prompt($prompt_or_smartytemplate, $log_template = true) {
		if (endsWith($prompt_or_smartytemplate, ".tpl")) {
			$prompt = $this->smarty->fetch($prompt_or_smartytemplate);
			if ($log_template) {
				$this->console_log("Template:" . $this->class . "/" . $prompt_or_smartytemplate, "#CE5C00");
			}
			return $prompt;
		}
		return $prompt_or_smartytemplate;
	}

	private function ai_reset_history($session_key, $label) {
		$this->console_log("////RESET " . strtoupper($label) . "///");
		$this->set_session($session_key, []);
	}

	private function ai_add_history($session_key, $role, $prompt_or_smartytemplate) {
		$prompt = $this->ai_render_prompt($prompt_or_smartytemplate, false);
		$history = $this->get_session($session_key);
		if (!is_array($history)) {
			$history = [];
		}
		$history[] = ['role' => $role, 'content' => $prompt];
		$this->set_session($session_key, $history);
	}

	private function ai_get_history($session_key): array {
		$history = $this->get_session($session_key);
		return is_array($history) ? $history : [];
	}

	private function ai_completion_request($api_name, $session_key, $prompt_or_smartytemplate, $role, $temperature, $tokens, $key, $url, $model) {

		$prompt = $this->ai_render_prompt($prompt_or_smartytemplate);

		if (empty($prompt)) {
			$this->console_log("PROMPT IS EMPTY");
			return;
		}

		$this->console_log($prompt);

		if (empty($key) || empty($url) || empty($model)) {
			throw new Exception($api_name . "(): please set key,url,model for AI");
		}

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);

		$headers = array(
		    "Content-Type: application/json",
		    "Authorization: Bearer $key",
		);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$messages = $this->ai_get_history($session_key);
		$messages[] = ['role' => $role, 'content' => $prompt];

		$data = array(
		    "model" => $model,
		    "temperature" => $temperature,
		    "max_completion_tokens" => $tokens,
		    "messages" => $messages
		);

		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

		$this->close_all_db();

		$response1 = curl_exec($curl);
		if ($response1 === false) {
				$curl_error = curl_error($curl);
				$curl_errno = curl_errno($curl);
			$message = $api_name . "(): curl error(" . $curl_errno . "): " . $curl_error;
			$this->console_log(strtoupper($api_name) . " ERROR", "#D20000");
			$this->console_log($message, "#D20000");
			return $message;
		}

		$response2 = json_decode($response1, true);
		if (!is_array($response2)) {
				$json_error = json_last_error_msg();
			$message = $api_name . "(): invalid JSON response: " . $json_error;
			$this->console_log(strtoupper($api_name) . " ERROR", "#D20000");
			$this->console_log($message, "#D20000");
			$this->console_log($response1, "#D20000");
			return $message;
		}
			$generated_text = $response2['choices'][0]['message']['content'] ?? null;

		if ($generated_text == null) {
			if (isset($response2["error"]) && is_array($response2["error"])) {
				$message = $response2["error"]["message"];
				$this->console_log(strtoupper($api_name) . " ERROR", "#D20000");
				$this->console_log($response2, "#D20000");
				return $message;
			}
			$this->console_log($response1, "#D20000");
			return $response1;
		}

		$this->console_log($generated_text);
		return $generated_text;
	}

	function chatGPT_reset_history() {
		$this->ai_reset_history("chatgpt_history", "chatgpt");
	}

	function chatGPT_add_history($role, $prompt_or_smartytemplate) {
		$this->ai_add_history("chatgpt_history", $role, $prompt_or_smartytemplate);
	}

	function chatGPT_get_history(): array {
		return $this->ai_get_history("chatgpt_history");
	}

	function chatGPT($prompt_or_smartytemplate, $role = "user", $temperature = 0, $tokens = 1000, $mode = "api") {
		$setting = $this->get_setting();

		if ($mode == "api") {
			$key = $setting["chatgpt_api_key"];
			$url = $setting["chatgpt_api_url"];
			$model = $setting["chatgpt_api_model"];
		} else if ($mode == "coding") {
			$key = $setting["chatgpt_coding_key"];
			$url = $setting["chatgpt_coding_url"];
			$model = $setting["chatgpt_coding_model"];
		} else {
			throw new Exception("chatGPT(): \$mode is wrong");
		}

		return $this->ai_completion_request("chatGPT", "chatgpt_history", $prompt_or_smartytemplate, $role, $temperature, $tokens, $key, $url, $model);
	}

	function chatGPT_image_analysis($prompt_or_smartytemplate, array $image_paths, $temperature = 0, $tokens = 1000, $mode = "api") {

		if (endsWith($prompt_or_smartytemplate, ".tpl")) {
			$prompt = $this->smarty->fetch($prompt_or_smartytemplate);
			$this->console_log("Template:" . $this->class . "/" . $prompt_or_smartytemplate, "#CE5C00");
		} else {
			$prompt = $prompt_or_smartytemplate;
		}

		if (empty($prompt)) {
			throw new Exception("chatGPT_image_analysis(): prompt is empty");
		}

		$setting = $this->get_setting();
		if ($mode == "api") {
			$key = $setting["chatgpt_api_key"];
			$url = $setting["chatgpt_api_url"];
			$model = $setting["chatgpt_api_model"];
		} else if ($mode == "coding") {
			$key = $setting["chatgpt_coding_key"];
			$url = $setting["chatgpt_coding_url"];
			$model = $setting["chatgpt_api_model"];
		} else {
			throw new Exception("chatGPT_image_analysis(): \$mode is wrong");
		}

		if (empty($key) || empty($url) || empty($model)) {
			throw new Exception("chatGPT_image_analysis(): please set key,url,model for AI");
		}

		if (count($image_paths) === 0) {
			throw new Exception("chatGPT_image_analysis(): image file is required");
		}

		$content = [
		    [
			"type" => "text",
			"text" => (string) $prompt
		    ]
		];

		foreach ($image_paths as $path) {
			$path = (string) $path;
			if ($path === "" || !is_file($path) || !is_readable($path)) {
				throw new Exception("chatGPT_image_analysis(): image file is not readable");
			}
			$raw = file_get_contents($path);
			if ($raw === false || $raw === "") {
				throw new Exception("chatGPT_image_analysis(): failed to read image file");
			}
			$mime = mime_content_type($path);
			if ($mime === false || strpos($mime, "image/") !== 0) {
				throw new Exception("chatGPT_image_analysis(): only image files are supported");
			}

			$content[] = [
			    "type" => "image_url",
			    "image_url" => [
				"url" => "data:" . $mime . ";base64," . base64_encode($raw),
				"detail" => "auto"
			    ]
			];
		}

		$data = [
		    "model" => $model,
		    "temperature" => (float) $temperature,
		    "max_completion_tokens" => (int) $tokens,
		    "messages" => [
			[
			    "role" => "user",
			    "content" => $content
			]
		    ]
		];

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
		    "Content-Type: application/json",
		    "Authorization: Bearer $key",
		]);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

			$this->close_all_db();
			$response1 = curl_exec($curl);

		$response2 = json_decode($response1, true);

		$message = $response2["choices"][0]["message"]["content"] ?? null;
		$text = "";
		if (is_string($message)) {
			$text = $message;
		} else if (is_array($message)) {
			foreach ($message as $part) {
				if (!is_array($part)) {
					continue;
				}
				if (isset($part["text"])) {
					$text .= (string) $part["text"];
					continue;
				}
				if (isset($part["type"]) && $part["type"] === "output_text" && isset($part["output_text"])) {
					$text .= (string) $part["output_text"];
					continue;
				}
			}
		}
		$text = trim($text);
		if ($text !== "") {
			return $text;
		}

		if (is_array($response2["error"] ?? null)) {
			return (string) ($response2["error"]["message"] ?? "Unknown API error");
		}

		$finishReason = (string) ($response2["choices"][0]["finish_reason"] ?? "");
		if ($finishReason === "length") {
			return "モデルの出力上限に達したため本文を取得できませんでした。トークン上限を増やして再実行してください。";
		}

		return "画像解析の応答本文を取得できませんでした。";
	}

	function chat_show_text($message, $align = "left", $overwrite = false) {
		if (empty($message)) {
			return;
		}
		$message_with_link = $this->text_to_link($message);
		$arr = [
		    'html' => nl2br($message_with_link),
		    'type' => 'text',
		    'overwrite' => $overwrite,
		    'chatid' => $this->POST("_chatid"),
		    'align' => $align,
		];
		$this->arr["chat"][] = $arr;
	}
	
	function text_to_link($text, $target = "_blank") {
		$pattern = '/((?:https?|ftp):\/\/[-_.!~*\'()a-zA-Z0-9;\/?:@&=+$,%#]+)/';
		$replace = '<a href="$1" target="' . $target . '">$1</a>';
		$text = preg_replace($pattern, $replace, $text);
		return $text;
	}

	function chat_history_array_normalize($history) {
		if (!is_array($history)) {
			return [];
		}
		$out = [];
		foreach ($history as $row) {
			if (!is_array($row)) {
				continue;
			}
			$role = trim((string) ($row["role"] ?? ""));
			if ($role === "") {
				$role = "assistant";
			}
			$message = (string) ($row["message"] ?? "");
			$time = (string) ($row["time"] ?? "");
			if ($time === "" && !empty($row["created_at"])) {
				$ts = (int) $row["created_at"];
				if ($ts > 0) {
					$time = date("Y/m/d H:i", $ts);
				}
			}
			if ($time === "") {
				$time = date("Y/m/d H:i");
			}
			$out[] = [
			    "role" => $role,
			    "message" => $message,
			    "time" => $time,
			];
		}
		return $out;
	}

	function chat_history_array_add($history, $role, $message, $time = null, $max = 200) {
		$history = $this->chat_history_array_normalize($history);
		$max = (int) $max;
		if ($max <= 0) {
			$max = 200;
		}
		$row = [
		    "role" => trim((string) $role) === "" ? "assistant" : trim((string) $role),
		    "message" => (string) $message,
		    "time" => $time === null || $time === "" ? date("Y/m/d H:i") : (string) $time,
		];
		$history[] = $row;
		if (count($history) > $max) {
			$history = array_slice($history, -$max);
		}
		return $history;
	}

	function chat_history_array_for_display($history, $linkify = true) {
		$history = $this->chat_history_array_normalize($history);
		$out = [];
		foreach ($history as $row) {
			$message = (string) ($row["message"] ?? "");
			if ($linkify) {
				$message = $this->text_to_link($message);
			}
			$row["message"] = $message;
			$out[] = $row;
		}
		return $out;
	}

	function chat_history_array_to_messages($history) {
		$history = $this->chat_history_array_normalize($history);
		$out = [];
		foreach ($history as $row) {
			$out[] = [
			    "role" => (string) ($row["role"] ?? "assistant"),
			    "content" => (string) ($row["message"] ?? ""),
			];
		}
		return $out;
	}

	function chat_show_html($template_name, $align = "left", $overwrite = false) {
		$this->console_log("Template:" . $this->class . "/" . $template_name, "#CE5C00");
		$this->smarty->assign("MYSESSION", $_SESSION[$this->windowcode]);
		$template = $this->smarty->fetch($template_name);
		$html = '<div class="class_style_' . $this->class . ' chat_view_box">' . $template . '</div>';
		$arr = [
		    'html' => $html,
		    'type' => 'html',
		    'overwrite' => $overwrite,
		    'chatid' => $this->POST("_chatid"),
		    'align' => $align
		];
		$this->arr["chat"][] = $arr;
	}

	function chat_clear($chatid = null) {
		if ($chatid == null) {
			$arr = [
			    "type" => "clear",
			    "chatid" => "all"
			];
		} else {
			$arr = [
			    "type" => "clear",
			    "chatid" => $chatid
			];
		}
		$this->arr["chat"][] = $arr;
	}

	function chat_clear_after() {
		$arr = [
		    "type" => "clear_after",
		    "chatid" => $this->POST("_chatid")
		];
		$this->arr["chat"][] = $arr;
	}

	function console_log($log, $color = "#064683") {

		if ($this->get_session("testserver")) {
			if (is_array($log)) {
				$arr = [
				    "log" => json_encode($log, JSON_PRETTY_PRINT),
				    "color" => $color
				];
			} else {
				$arr = [
				    "log" => $log,
				    "color" => $color
				];
			}
			$this->arr["console_log"][] = $arr;
		}
	}

	function cron_log($log) {
		$this->arr["cron_log"][] = $log;
	}

	function set_userdir($user_dir) {
		$this->userdir = $user_dir;
	}

	function get_userdir() {
		return $this->userdir;
	}

	function is_constant_array($array_name) {
		$ffm_constant_array = $this->db("constant_array", "constant_array");

		$list = $ffm_constant_array->select("array_name", $array_name);
		if (count($list) == 0) {
			return false;
		} else {
			return true;
		}
	}

	function add_constant_array($array_name, $key, $value, $color = "#ccc") {

		$array_name = trim((string) $array_name);
		if ($array_name === "" || !preg_match('/^[a-z][a-z0-9_]*$/', $array_name)) {
			throw new Exception("Array Name should be a lowercase identifier");
		}

		$ffm_constant_array = $this->db("constant_array", "constant_array");
		$ffm_values = $this->db("values", "constant_array");

		$list = $ffm_constant_array->select("array_name", $array_name);
		if (count($list) == 0) {
			$ca = ["array_name" => $array_name];
			$ffm_constant_array->insert($ca);
		} else {
			$ca = $list[0];
		}
		$v = [
		    "constant_array_id" => $ca["id"],
		    "key" => $key,
		    "value" => $value,
		    "color" => $color
		];
		$ffm_values->insert($v);
	}

	function get_all_constant_array_names($emptydata = false, $include_table_field = true) {
		$constant_array_names = [];
		if ($emptydata) {
			$constant_array_names[""] = "";
		}

		$ffm_constant_array = $this->db("constant_array", "constant_array");
		$constant_array = $ffm_constant_array->getall();

		$table_fields_entry = null;
		foreach ($constant_array as $key => $value) {
			if ($value['array_name'] === "table_fields") {
				$table_fields_entry = [$key, $value['array_name']];
				continue;
			}
			$constant_array_names[$key] = $value['array_name'];
		}

		// Other table field
		if ($include_table_field) {
			$fmt_db = $this->db("db", "db");
			$fmt_fields = $this->db("db_fields", "db");
			$db_list = $fmt_db->getall("tb_name", SORT_ASC);
			foreach ($db_list as $db) {
				$table_key = "table/" . $db["tb_name"];
				$constant_array_names[$table_key] = $table_key;

				$field_list = $fmt_fields->select("db_id", $db["id"], true, "AND", "parameter_name", SORT_ASC);
				foreach ($field_list as $field) {
					if ($field["type"] == "text") {
						$key = "table/" . $db["tb_name"] . "/" . $field["parameter_name"];
						$constant_array_names[$key] = $key;
					}
				}
			}
		}

		if ($table_fields_entry !== null) {
			$constant_array_names[$table_fields_entry[0]] = $table_fields_entry[1];
		}

		return $constant_array_names;
	}

	function get_constant_array($array_name, $emptydata = false) {

		if ($emptydata) {
			$valuearr[""] = "";
		} else {
			$valuearr = [];
		}

		if (startsWith($array_name, "table/")) {

			$table_and_field = substr($array_name, 6);
			$ex = explode("/", $table_and_field);  // explode "tablename/fieldname"
			if (count($ex) == 2) {
				// Making database dynamic
				$ffm = $this->db($ex[0], "common");
				$list = $this->get_table_dropdown_rows($ex[0], $ffm);
				foreach ($list as $d) {
					$valuearr[$d["id"]] = $d[$ex[1]];
				}
			}
		} else {
			// Others
			$ffm_constant_array = $this->db("constant_array", "constant_array");
			$ffm_values = $this->db("values", "constant_array");

			$constant_array_list = $ffm_constant_array->select(['array_name'], [$array_name]);
			if (empty($constant_array_list)) {
				return $valuearr;
			}
			$constant_array = $constant_array_list[0];
			$value_array = $ffm_values->select('constant_array_id', $constant_array['id'], true, "AND", "sort", SORT_ASC);

			foreach ($value_array as $key => $value) {
				$valuearr[$value['key']] = $value['value'];
			}
		}

		return $valuearr;
	}

	function get_constant_array_color($array_name) {

		$valuearr = [];
		if (startsWith($array_name, "table/")) {
			return $valuearr;
		} else {

			$ffm_constant_array = $this->db("constant_array", "constant_array");
			$ffm_values = $this->db("values", "constant_array");

			$constant_array_list = $ffm_constant_array->select(['array_name'], [$array_name]);
			if (empty($constant_array_list)) {
				return $valuearr;
			}
			$constant_array = $constant_array_list[0];
			$value_array = $ffm_values->select('constant_array_id', $constant_array['id'], true, "AND", "sort", SORT_ASC);

			$valuearr = array();
			foreach ($value_array as $key => $value) {
				if ($value['color']) {
					$valuearr[$value['key']] = $value['color'];
				}
			}
			return $valuearr;
		}
	}

	function validate_duplicate($table_name, $field_name, $target_value, $exclude_id = 0, $class = "common") {

		$ffm = $this->db($table_name, $class);
		$data = $ffm->select($field_name, $target_value);

		if (count($data) == 0) {
			$is_duplicate = true;
		} else {
			// exclude $exclude_id (Edit screen is needed)
			foreach ($data as $k => $d) {
				if ($d["id"] == $exclude_id) {
					unset($data[$k]);
				}
			}
			if (count($data) > 0) {
				$is_duplicate = false;
				$this->res_error_message($field_name, "Duplicated");
			} else {
				$is_duplicate = true;
			}
		}
		return $is_duplicate;
	}

	function get_res_array() {
		return $this->arr;
	}

	function set_css_other_class($class_name) {
		$this->assign("css_class", $class_name);
	}

	function stop_executing_function() {
		$this->flg_stop_executing_function = true;
	}

	function assign_fields_from_screen($group_name, $screen_id, $option_emptydata = true, $add_parent_dropdown = false) {
		$fmt_screen = $this->db("screen", "db");
		$screen = $fmt_screen->get($screen_id);
		$table_name = $screen["tb_name"];
		$this->assign_field_settings($group_name, $table_name, $screen["screen_name"], $option_emptydata, $add_parent_dropdown);
	}

	function assign_field_settings($group_name, $table_name, $screen_or_fieldnamearray, $option_emptydata = true, $add_parent_dropdown = false, $use_thumbnail = false) {


		$this->assign("_use_thumbnail", $use_thumbnail);

		$arr_list = [];
		if (!is_array($screen_or_fieldnamearray)) {
			$screen_name = $screen_or_fieldnamearray;
			$flg = "screen";
		} else {
			$field_names = $screen_or_fieldnamearray;
			$flg = "array";
			
			if(in_array("parent_id",$field_names)){
				$add_parent_dropdown = true;
				$key = array_search("parent_id", $field_names, true);
				if ($key !== false) {
				    unset($field_names[$key]);
				}
			}
		}

		// Making Parent field
		if ($add_parent_dropdown) {
			$fmt_db = $this->db("db", "db");
			$db = $fmt_db->select("tb_name", $table_name)[0];
			if ($db["parent_tb_id"] > 0) {
				$db_parent = $fmt_db->get($db["parent_tb_id"]);
				$dropdown_item = $db_parent["dropdown_item"];
				if (empty($dropdown_item)) {
					$dropdown_item = "id";
				}
				$dropdown_item_display_type = $db_parent["dropdown_item_display_type"] ?? "field";
				$dropdown_item_template = trim((string) ($db_parent["dropdown_item_template"] ?? ""));
				$parent_field_map = $this->get_db_field_map_by_db_id((int) ($db_parent["id"] ?? 0));
				$fmt_parent = $this->db($db_parent["tb_name"], "common");
				$list = $fmt_parent->getall($db_parent["sortkey"], $db_parent["sort_order"]);
				$option_arr = [];
				if ($option_emptydata) {
					$option_arr[""] = "";
				}
				foreach ($list as $p) {
					if ($dropdown_item_display_type === "template" && $dropdown_item_template !== "") {
						$option_arr[$p["id"]] = $this->build_label_from_template($dropdown_item_template, $p, $parent_field_map);
					} else {
						if (!is_array($p[$dropdown_item])) {
							$option_arr[$p["id"]] = $p[$dropdown_item];
						}
					}
				}

				$arr = [
				    "parameter_name" => "parent_id",
				    "parameter_title" => $db_parent["menu_name"],
				    "type" => "dropdown",
				    "is_table_dropdown" => true,
				    "options" => $option_arr,
				];
				$arr_list[] = $arr;
			}
		}

		$fmt_screen_fields = $this->db("screen_fields", "db");
		$fmt_fields = $this->db("db_fields", "db");
		if ($flg == "screen") {
			$screen_fields_list = $fmt_screen_fields->select(["tb_name", "screen_name"], [$table_name, $screen_name], true, "AND", "sort", SORT_ASC);
			
		} else {
			$fmt_db = $this->db("db", "db");
			$db = $fmt_db->select("tb_name", $table_name)[0];
			$screen_fields_list = [];
			foreach ($field_names as $fieldname) {
				$f = $fmt_fields->select(["db_id", "parameter_name"], [$db["id"], $fieldname])[0];
				$screen_fields_list[] = ["db_fields_id" => $f["id"]];
			}
		}

		foreach ($screen_fields_list as &$sf) {

			$f = $fmt_fields->get($sf["db_fields_id"]);

			if ($f != null) {
				// Options
				$constant_array_name = $f["constant_array_name"];
				$display_fields_for_dropdown = trim((string) ($f["display_fields_for_dropdown"] ?? ""));
				$emptydata = ($f["type"] == "checkbox" || $f["type"] == "radio") ? false : $option_emptydata;
				$force_empty_for_table_dropdown = ($flg == "screen" && $screen_name != "search" && $f["type"] == "dropdown" && startsWith($constant_array_name, "table/"));
				if ($force_empty_for_table_dropdown) {
					$emptydata = true;
				}
				if (startsWith($constant_array_name, "table/") && $display_fields_for_dropdown !== "") {
					$option_arr = $this->build_table_dropdown_options($constant_array_name, $display_fields_for_dropdown, $emptydata);
				} else {
					if ($f["type"] == "checkbox" || $f["type"] == "radio") {
						$option_arr = $this->get_constant_array($constant_array_name, false);
					} else {
						if(startsWith($constant_array_name, "table/")){
							$option_arr = $this->get_constant_array($constant_array_name, true);
						}else{
							$option_arr = $this->get_constant_array($constant_array_name, $option_emptydata);
						}
					}
				}
				$option_color = $this->get_constant_array_color($constant_array_name);

				$arr = $f;
				$arr["is_table_dropdown"] = startsWith($constant_array_name, "table/");
				$arr["options"] = $option_arr;
				$arr["colors"] = $option_color;

				if ($f["type"] == "textarea") {
					$arr["max_bytes"] = $f["length"];
				}

				$arr_list[] = $arr;
			} else {
				$fmt_screen_fields->delete($sf["id"]);
			}
		}
		$this->assign($group_name, $arr_list);
	}

	private function build_table_dropdown_options($constant_array_name, $display_template, $emptydata) {
		$option_arr = [];
		if ($emptydata) {
			$option_arr[""] = "";
		}

		if (!startsWith($constant_array_name, "table/")) {
			return $option_arr;
		}

		$table_and_field = substr($constant_array_name, 6);
		$ex = explode("/", $table_and_field);
		$table_name = $ex[0] ?? "";
		$default_field = $ex[1] ?? "";
		if ($table_name === "") {
			return $option_arr;
		}

		$ffm = $this->db($table_name, "common");
		$field_map = $this->get_db_field_map_by_table_name($table_name);
		$list = $this->get_table_dropdown_rows($table_name, $ffm);
		foreach ($list as $d) {
			if ($display_template !== "") {
				$label = $this->build_label_from_template($display_template, $d, $field_map);
			} else {
				if ($default_field !== "" && isset($d[$default_field])) {
					$label = $d[$default_field];
				} else {
					$label = $d["id"];
				}
			}
			$option_arr[$d["id"]] = $label;
		}

		return $option_arr;
	}

	private function get_table_dropdown_rows($table_name, $ffm) {
		$fmt_db = $this->db("db", "db");
		$fmt_fields = $this->db("db_fields", "db");
		$db_list = $fmt_db->select("tb_name", $table_name);
		if (count($db_list) > 0) {
			$db_id = (int) ($db_list[0]["id"] ?? 0);
			if ($db_id > 0) {
				$sort_fields = $fmt_fields->select(["db_id", "parameter_name"], [$db_id, "sort"], true, "AND", "id", SORT_ASC);
				if (count($sort_fields) > 0) {
					return $ffm->getall("sort", SORT_ASC);
				}
			}
		}
		return $ffm->getall("id", SORT_DESC);
	}

	private function get_db_field_map_by_table_name(string $table_name): array {
		if ($table_name === "") {
			return [];
		}
		$fmt_db = $this->db("db", "db");
		$db_list = $fmt_db->select("tb_name", $table_name);
		if (count($db_list) === 0) {
			return [];
		}
		return $this->get_db_field_map_by_db_id((int) ($db_list[0]["id"] ?? 0));
	}

	private function get_db_field_map_by_db_id(int $db_id): array {
		if ($db_id <= 0) {
			return [];
		}
		$fmt_fields = $this->db("db_fields", "db");
		$field_map = [];
		$field_list = $fmt_fields->select("db_id", $db_id, true, "AND", "sort", SORT_ASC);
		foreach ($field_list as $field) {
			$parameter_name = (string) ($field["parameter_name"] ?? "");
			if ($parameter_name === "") {
				continue;
			}
			$field_map[$parameter_name] = $field;
		}
		return $field_map;
	}

	private function build_label_from_template($template, $row, array $field_map = []) {
		if ($template === "") {
			return "";
		}
		$formatter = $this->create_ValueFormatter();
		return preg_replace_callback('/{\$([a-zA-Z0-9_]+)}/', function ($m) use ($row, $field_map, $formatter) {
			$key = $m[1];
			$val = $row[$key] ?? "";
			if (is_array($val)) {
				return "";
			}
			if (isset($field_map[$key]) && is_array($field_map[$key])) {
				return $formatter->format_for_field($field_map[$key], $val);
			}
			return (string) $val;
		}, $template);
	}

	function get_default_values($table_name_or_screen_id) {

		if (is_int($table_name_or_screen_id)) {
			$fmt_screen = $this->db("screen", "db");
			$screen = $fmt_screen->get($table_name_or_screen_id);
			$table_name = $screen["tb_name"];
		} else {
			$table_name = $table_name_or_screen_id;
		}

		$fmt_db = $this->db("db", "db");
		$fmt_fields = $this->db("db_fields", "db");
		$list = $fmt_db->select("tb_name", $table_name);
		if (count($list) > 0) {
			$fields_list = $fmt_fields->select("db_id", $list[0]["id"]);
			$arr = [];
			foreach ($fields_list as $f) {
				if ($f["default_value"] !== "") {
					$arr[$f["parameter_name"]] = $f["default_value"];
				}
			}
			return $arr;
		} else {
			return [];
		}
	}

	function get_field_list_from_screen($screen_id) {
		$fmt_screen = $this->db("screen", "db");
		$screen = $fmt_screen->get($screen_id);
		$table_name = $screen["tb_name"];
		return $this->get_field_list($table_name, $screen["screen_name"]);
	}

	function get_field_list($table_name, $screen_or_fieldnamearray = null) {


		$fmt_db = $this->db("db", "db");
		$db = $fmt_db->select("tb_name", $table_name)[0];

		$fmt_fields = $this->db("db_fields", "db");
		if ($screen_or_fieldnamearray == null) {
			return $fmt_fields->select("db_id", $db["id"]);
		}

		$fmt_screen_fields = $this->db("screen_fields", "db");

		if (is_array($screen_or_fieldnamearray)) {
			$screen_fields_list = [];
			foreach ($screen_or_fieldnamearray as $parameter_name) {
				$field = $fmt_fields->select(["db_id", "parameter_name"], [$db["id"], $parameter_name])[0];
				$screen_fields_list[] = ["db_fields_id" => $field["id"]];
			}
		} else {

			$screen_fields_list = $fmt_screen_fields->select(["tb_name", "screen_name"], [$table_name, $screen_or_fieldnamearray], true, "AND", "sort", SORT_ASC);
		}

		$arr_list = [];

		if ($db["parent_tb_id"] > 0) {
			$f = [
			    "parameter_name" => "parent_id",
			    "parameter_title" => $db["menu_name"],
			    "validation" => 1,
			];
			$arr_list[] = $f;
		}

		foreach ($screen_fields_list as &$sf) {
			$f = $fmt_fields->get($sf["db_fields_id"]);
			$arr_list[] = $f;
		}
		return $arr_list;
	}

	function decrypt_post($parameter_name) {
		$e = $this->POST($parameter_name);
		if (!empty($e)) {
			return $this->decrypt($e);
		} else {
			return null;
		}
	}

	function chat_set_login($table_name, $id) {
		$this->set_session("chat_login_table", $table_name);
		$this->set_session("chat_login_id", $id);
	}

	function chat_set_logout() {
		$this->set_session("chat_login_table", null);
		$this->set_session("chat_login_id", null);
	}

	function chat_is_logined() {
		if ($this->get_session("chat_login_table") == null) {
			return false;
		} else {
			return true;
		}
	}

	function chat_get_login_member() {
		$table = $this->get_session("chat_login_table");
		$id = $this->get_session("chat_login_id");
		$ffm = $this->db($table, "common");
		$d = $ffm->get($id);
		return $d;
	}

	function chat_update_login_member($data) {
		$table = $this->get_session("chat_login_table");
		$id = $this->get_session("chat_login_id");
		if ($id == $data["id"]) {
			$ffm = $this->db($table, "common");
			$ffm->update($data);
		} else {
			throw new Exception("Wrong member data.");
		}
	}

	function chat_get_login_table() {
		return $this->get_session("chat_login_table");
	}

	function get_table_name($screen_id) {
		$fmt_screen = $this->db("screen", "db");
		$screen = $fmt_screen->get($screen_id);
		$table_name = $screen["tb_name"];
		return $table_name;
	}

	function get_db_setting($tb_name) {
		$ffm_db = $this->db("db", "db");
		$list = $ffm_db->select("tb_name", $tb_name);
		if (count($list) > 0) {
			return $list[0];
		} else {
			return null;
		}
	}

	function get_field_setting($tb_name, $field_name) {

		if (!empty($this->cache_field["$tb_name/$field_name"])) {
			return $this->cache_field["$tb_name/$field_name"];
		}

		$ffm_db = $this->db("db", "db");
		$ffm_field = $this->db("db_fields", "db");

		$db_list = $ffm_db->select("tb_name", $tb_name);
		if (count($db_list) > 0) {
			$field_list = $ffm_field->select("parameter_name", $field_name);
			if (count($field_list) > 0) {
				$this->cache_field["$tb_name/$field_name"] = $field_list[0];
				return $field_list[0];
			}
		}
		return null;
	}

	function include_chart_lib() {

		$if_folder = dirname(__FILE__) . "/../interface/chartjs";
		$class_folder = dirname(__FILE__) . "/chartjs";

		$if_files = [
		    "Chart.php",
		    "Dataset.php",
		    "Dataset_Bar.php",
		    "Dataset_Bubble.php",
		    "Dataset_Line.php",
		    "Dataset_Pie.php",
		    "Dataset_PolarArea.php",
		    "Dataset_Radar.php",
		    "Dataset_Scatter.php",
		    "Dataset_Doughnut.php",
		    "Scale.php"
		];

		$class_files = [
		    "Chart_class.php",
		    "Dataset_class.php",
		    "Dataset_Bar_class.php",
		    "Dataset_Bubble_class.php",
		    "Dataset_Line_class.php",
		    "Dataset_Pie_class.php",
		    "Dataset_PolarArea_class.php",
		    "Dataset_Radar_class.php",
		    "Dataset_Scatter_class.php",
		    "Dataset_Doughnut_class.php",
		    "Scale_class.php"
		];

		foreach ($if_files as $f) {
			include_once $if_folder . "/" . $f;
		}

		foreach ($class_files as $f) {
			include_once $class_folder . "/" . $f;
		}
	}

	function create_chart(): \chartjs\Chart {
		$this->include_chart_lib();
		return new \chartjs\Chart_class();
	}

	function chart_draw($canvas_tag_id, \chartjs\Chart $chart) {

		$canvas_tag_id = str_replace("#", "", $canvas_tag_id);

		$arr = $chart->to_array();
		$arr = $this->array_filter_recursive($arr);

		$this->include_chart_lib();
		$this->arr["chartjs"][] = [
		    "tag_id" => $canvas_tag_id,
		    "chart" => $arr,
		];
	}
	
	private function array_filter_recursive($input) {
		foreach ($input as &$value) {
			if (is_array($value)) {
				$value = $this->array_filter_recursive($value);
			}
		}
		return array_filter($input, function ($val) {
			return !is_null($val) && $val !== '' && $val !== [];
		});
	}

	function create_openai($model, $assistant, $base_instruction = "", ?\openai\Recorder $message_recorder = null, ?\openai\StatusManager $status_manager = null, $network_logger = null): \openai\OpenAI_class {
		$this->include_openai_lib();
		$setting = $this->get_setting();

		$this->assistant = $assistant;

		$class_dir = $this->dirs->get_class_dir($this->class);
		$vectorSyncDir = $class_dir . "/vector_store";
		$toolsDir = $class_dir . "/function_tools";

		$vectorStoreId = $assistant["openai_vector_store_id"];
		$vectorStoreName = "vs_" . $assistant["name"] . "_" . $assistant["id"];
		$vs = $this->encrypt($vectorStoreName);

		if ($message_recorder == null) {
			$message_recorder = new openai\SessionRecorder($this->windowcode, $vs);
		}

		if ($status_manager == null) {
			$status_manager = new openai\SessionStatusManager($this->windowcode, $vs);
		}

		if ($network_logger == null) {
			$network_logger = new openai\SessionLogger($this->windowcode, $vs);
		}

		$status_manager->get_status("Start");

		$openai = new \openai\OpenAI_class($setting["chatgpt_api_key"],
			$vectorSyncDir,
			$toolsDir,
			$model,
			$setting["openai_logfile"],
			$base_instruction,
			$assistant["databases"],
			$message_recorder,
			$status_manager,
			$network_logger,
			$this
		);

		// vector store 作成
		if (empty($vectorStoreId)) {
			$vectorStoreId = $openai->createVectorStore($vectorStoreName);
			$assistant["openai_vector_store_id"] = $vectorStoreId;
		}
		$openai->set_vector_store_id($vectorStoreId);

		$openai->curl_timeout = $assistant["curl_timeout"];

		if (!empty($assistant["instructions"])) {
			$openai->add_system($assistant["instructions"]);
		}

		$this->openai = $openai;

		return $openai;
	}

	function include_openai_interfaces() {

		$if_folder = dirname(__FILE__) . "/../interface/openai";

		$if_files = [
		    "OpenAI.php",
		    "Response.php",
		    "FunctionTool.php",
		    "Recorder.php",
		    "StatusManager.php",
		    "Logger.php"
		];

		foreach ($if_files as $f) {
			include_once $if_folder . "/" . $f;
		}
	}

	function include_openai_lib() {
		$class_folder = dirname(__FILE__) . "/openai";
		$class_files = [
		    "OpenAI_class.php",
		    "Response_class.php",
		    "FileRecorder.php",
		    "SessionRecorder.php",
		    "SessionStatusManager.php",
		    "FileStatusManager.php",
		    "FileLogger.php",
		    "SessionLogger.php",
		    "token_usage_tracker.php"
		];
		foreach ($class_files as $f) {
			include_once $class_folder . "/" . $f;
		}
	}

	function openai_get_assistant() {
		return $this->assistant;
	}

	function reload_work_area() {
		$alma = $this->get_session("__AUTO_LOAD_MAIN_AREA");
		if (!empty($alma)) {
			$this->ajax($alma["class"], $alma["function"], $alma["parameters"]);
		}
	}
	
	function reload_menu() {
		$this->invoke("show_menu", [], "base");
	}

	function close_dialog($dialog_id = null) {

		if ($dialog_id == null) {
			$dialog_id = $this->POST("_dialog_id");
		}

		$md = [];
		$md["dialog_id"] = $dialog_id;
		$this->arr["close_dialog_by_id"][] = $md;
	}

	function get_APP_URL($class = null, $function = null, $params = null) {
		$force_https = false;

		// スキーム判定
		if ($force_https) {
			$scheme = "https";
		} else {
			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
		}



		// ホスト名（例: example.com）
		$host = $_SERVER['HTTP_HOST'] ?? '';

		// リクエストされているパスのディレクトリ（例: /foo）
		$dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

		// ベースURLを組み立てる
		$url = $scheme . '://' . $host . ($dir === '' ? '' : $dir);

		if (endsWith($url, "/fbp")) {
			$url = substr($url, 0, -strlen("/fbp"));
		}
		if (endsWith($url, "/")) {
			$url = substr($url, 0, -strlen("/"));
		}

		// params (auto URL-encode)
		$p = "";
		if ($params != null) {
			if (is_array($params)) {
				$q = http_build_query($params, "", "&", PHP_QUERY_RFC3986);
				if ($q !== "") {
					$p = "&" . $q;
				}
			} else {
				$p = "&" . (string) $params;
			}
		}

		if ($class == null) {
			return $url . "/$function$p";
		} else {
			return $url . "/$class*$function$p";
		}
	}

	/**
	 * Validates the input data based on the field validation rules.
	 *
	 * This function checks if the required fields are present in the input data (either post data or file uploads).
	 * If a field requires validation and is missing, an error message is recorded.
	 *
	 * @param string $table_name The name of the table where the field validation rules are defined.
	 * @param string $screen_name The name of the screen or form to retrieve field validation rules.
	 * @param array $post The input data to validate, typically coming from POST.
	 * @param bool $validate_upload_field Optional. Whether to check file uploads (for fields of type 'file' or 'image'). Default is true.
	 *
	 * @return bool Returns true if validation passes, false if any required fields are missing.
	 */
	function validate($table_name, $screen_or_fieldnamearray, $post, $validate_upload_field = true): bool {

		$fields = $this->get_field_list($table_name, $screen_or_fieldnamearray);
		$error_fields = [];

		// Validate
		foreach ($fields as $f) {
			if (($f["validation"] ?? 0) == 1) {
				$pname = $f["parameter_name"] ?? "";
				if ($pname != "parent_id") {
					$field_type = $f["type"] ?? "";
					if ($field_type == "file" || $field_type == "image") {
						if ($validate_upload_field) {
							if (!$this->is_posted_file($pname)) {
								$this->res_error_message($pname, "Required");
								$error_fields[] = $pname;
							}
						}
					} else {
						if (($post[$pname] ?? "") == "") {
							$this->res_error_message($pname, "Required");
							$error_fields[] = $pname;
						}
					}
				}
			}
		}

		// Duplicate
		foreach ($fields as $f) {
			if (($f["duplicate_check"] ?? 0) == 1) {
				$pname = $f["parameter_name"] ?? "";
				if ($pname != "parent_id") {
					if (!in_array($pname, $error_fields)) {
						$field_type = $f["type"] ?? "";
						if ($field_type == "text" || $field_type == "number" || $field_type == "year_month") {

							$post_id = $post["id"] ?? "";
							if (is_numeric($post_id)) {
								$id = $post_id;
							} else {
								$id = $this->decrypt($post_id);
							}

							$res = $this->validate_duplicate($table_name, [$pname], [$post[$pname] ?? ""], $id);
							if (!$res) {
								$this->res_error_message($pname, "Duplicated");
								$error_fields[] = $pname;
							}
						}
					}
				}
			}
		}

		// Format Check
		$format_check_opt = [
		    'none' => "",
		    'email' => '/^[\w\.-]+@[a-zA-Z\d\.-]+\.[a-zA-Z]{2,}$/',
		    'phone' => '/^\+?\d{1,4}[-\s]?\(?\d{1,4}\)?[-\s]?\d{1,4}[-\s]?\d{1,4}[-\s]?\d{1,4}$/',
		    'postal' => '/^\d{3,10}([-]?\d{3,10})?$/',
		    'url' => '/^https?:\/\/[\w\.-]+\.[a-zA-Z]{2,}$/',
		    'date_yyyy_mm_dd' => '/^\d{4}\/\d{2}\/\d{2}$/',
		    'alphanumeric' => '/^[a-zA-Z0-9]+$/',
		    'alphabet_only' => '/^[a-zA-Z]+$/',
		    'numeric_only' => '/^\d+$/',
		    'any_characters' => '/^[a-zA-Z0-9\W_]+$/',
		    'password_easy' => '/^[a-zA-Z0-9\W_]+$/',
		    'password_hard' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/'
		];
		foreach ($fields as $f) {
			$format_check = $f["format_check"] ?? "none";
			if (!empty($format_check_opt[$format_check])) {
				$pname = $f["parameter_name"] ?? "";
					if ($pname != "parent_id") {
							if (!empty($post[$pname] ?? null)) {
								if (!in_array($pname, $error_fields)) {
									if (($f["type"] ?? "") == "text") {
										$res = preg_match($format_check_opt[$format_check], $post[$pname] ?? "");
								if (!$res) {
									$this->res_error_message($pname, "Format Error");
									$error_fields[] = $pname;
								}
							}
						}
					}
				}
			}
		}

		// Return the result
		if ($this->count_res_error_message() == 0) {
			return true;
		} else {
			return false;
		}
	}

	function create_vimeo(): Vimeo {
		$setting = $this->get_setting();
		return new Vimeo_class($setting);
	}

	function show_dbarr_console_log() {
		foreach ($this->dbarr as $key => $ffm) {
			$lastPart = basename($key);
			// 親ディレクトリを取得して結合
			$parentDir = basename(dirname($key));
			$result = '/' . $parentDir . '/' . $lastPart;
			$this->console_log("DB: $result", "#2A45C5");
		}
	}

	function polling_start($nickname, $status_text, $info_data = [], $timeout_seconds = 60, $timeout_handler_function = null, $timeout_handler_class = null) {

		if ($timeout_handler_class === null) {
			$timeout_handler_class = $this->class;
		}

		$polling_id = uniqid();
		$data = [
		    "polling_id" => $polling_id,
		    "nickname" => $nickname,
		    "status_text" => $status_text,
		    "info_data" => $info_data,
		    "timeout_seconds" => $timeout_seconds,
		    "timeout_handler_function" => $timeout_handler_function,
		    "timeout_handler_class" => $timeout_handler_class,
		];
		$this->set_session("_polling_id", $polling_id);
		$this->arr["polling"] = $data;

		$this->polling_start = true;
	}

	/**
	 * Waiting for the polling start
	 */
	function polling_wait() {
		if ($this->polling_start) {
			throw new Exception("This function cannot be used within the same function where polling_start() is executed.");
		}
		$polling_id = $this->get_session("_polling_id");
		if ($polling_id == null) {
			throw new Exception("call polling_start first.");
		}

		while (true) {
			if ($this->polling_get_status($polling_id) !== false) {
				break;
			}
			sleep(1);
		}
	}

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
	function polling_list(): array {

		if ($this->polling_start) {
			throw new Exception("This function cannot be used within the same function where polling_start() is executed.");
		}

		$my_polling_id = $this->get_session("_polling_id");

		$dir = $this->dirs->pollingdir;
		$result = [];

		// $dir内のディレクトリをスキャン
		$pollingDirs = array_filter(glob($dir . '/*'), 'is_dir');

		foreach ($pollingDirs as $pollingDir) {
			$polling_id = basename($pollingDir); // ディレクトリ名がpolling_id
			$infoFile = $pollingDir . '/info.json';

			if (file_exists($infoFile)) {
				// ファイルをロックして読み込む
				if (($handle = fopen($infoFile, 'r')) === false) {
					return false; // ファイルのオープンに失敗した場合
				}

				if (!flock($handle, LOCK_SH)) { // 共有ロックを取得
					fclose($handle);
					return false; // ロックの取得に失敗した場合
				}

				$content = stream_get_contents($handle); // ファイル内容を一括読み込み
				if ($content === false) {
					flock($handle, LOCK_UN); // ロックを解除
					fclose($handle);
					return false; // ファイルの読み込みに失敗した場合
				}

				flock($handle, LOCK_UN); // ロックを解除
				fclose($handle); // ファイルを閉じる

				$data = json_decode($content, true);

				if ($polling_id != $my_polling_id) {
					$result[] = [
					    "polling_id" => $polling_id,
					    "nickname" => $data['nickname'],
					    "status_text" => $data["status_text"],
					    "info_data" => $data["info_data"]
					];
				}
			}
		}

		return $result;
	}

	function polling_update_status($status_text, $current_required_status = null, $polling_id = null): bool {

		if ($this->polling_start) {
			throw new Exception("This function cannot be used within the same function where polling_start() is executed.");
		}

		if ($polling_id == null) {
			$polling_id = $this->get_session("_polling_id");
		}

		$dir = $this->dirs->pollingdir;
		$pollingDir = $dir . '/' . $polling_id;
		$infoFile = $pollingDir . '/info.json';

		// 該当するpolling_idのディレクトリが存在するか確認
		if (!is_dir($pollingDir)) {
			return false; // 該当するpolling_idのディレクトリが存在しない場合はfalseを返す
		}

		// info.jsonファイルが存在するか確認
		if (!file_exists($infoFile)) {
			return false; // info.jsonファイルが存在しない場合はfalseを返す
		}

		// ファイルをロックして操作
		$fp = fopen($infoFile, 'c+'); // 読み書きモードでファイルを開く
		if (!$fp) {
			return false; // ファイルを開けなかった場合
		}

		// ファイルロック (排他ロック)
		if (!flock($fp, LOCK_EX)) {
			fclose($fp);
			return false; // ロックに失敗した場合
		}

		// ファイル内容を読み取る
		$content = stream_get_contents($fp);
		$data = json_decode($content, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			flock($fp, LOCK_UN); // ロック解除
			fclose($fp);
			return false; // JSONの読み込みに失敗した場合
		}

		// 現在の status_text を確認
		if ($current_required_status !== null) {
			if ($data['status_text'] !== $current_required_status) {
				flock($fp, LOCK_UN); // ロック解除
				fclose($fp);
				return false; // 現在のstatus_textが条件と一致しない場合
			}
		}

		// status_textを更新
		$data['status_text'] = $status_text;

		// ファイルを先頭から上書き
		ftruncate($fp, 0); // ファイルを空にする
		rewind($fp); // ファイルポインタを先頭に戻す
		$updatedContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if (fwrite($fp, $updatedContent) === false) {
			flock($fp, LOCK_UN); // ロック解除
			fclose($fp);
			return false; // ファイルの書き込みに失敗した場合
		}

		// ロック解除とファイルクローズ
		flock($fp, LOCK_UN);
		fclose($fp);

		return true; // 正常に更新できた場合
	}

	function polling_transmit($polling_id, $invoke_function, $params = [], $invoke_class = null): bool {

		$clientDir = $this->dirs->pollingdir . "/" . $polling_id;

		if ($invoke_class == null) {
			$invoke_class = $this->class;
		}

		// 対象ディレクトリが存在しない場合falseを返す
		if (!is_dir($clientDir)) {
			$this->console_log("There is no polling ID folder. : '{$polling_id}'.");
			return false;
		}

		$my_polling_id = $this->get_session("_polling_id");

		// データを構築
		$data = array_merge(
			[
			    "class" => $invoke_class,
			    "function" => $invoke_function,
			    "_sender_polling_id" => $my_polling_id
			],
			$params
		);

		// ユニークなファイル名を生成
		$filePath = $clientDir . '/msg_' . uniqid() . '.json';

		// 排他制御でファイル作成
		$fp = fopen($filePath, 'c+');
		if (!$fp) {
			$this->console_log("Failed to create message file for polling ID '{$polling_id}'.");
			return false;
		}

		if (flock($fp, LOCK_EX)) { // 排他制御を実施
			fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
			fflush($fp); // バッファをフラッシュ
			flock($fp, LOCK_UN); // ロック解除
		} else {
			fclose($fp);
			$this->console_log("Could not lock the file for writing.");
			return false;
		}


		fclose($fp);
		return true;
	}

	function polling_stop() {

		if ($this->polling_start) {
			throw new Exception("This function cannot be used within the same function where polling_start() is executed.");
		}

		$polling_id = $this->get_session("_polling_id");
		try {
			$clientDir = $this->dirs->pollingdir . "/" . $polling_id;
			array_map('unlink', glob($clientDir . '/*')); // 中のファイルを削除
			rmdir($clientDir); // ディレクトリを削除
		} catch (Exception $e) {
			// Nothing to do
		}
	}

	function polling_get_sender() {

		if ($this->polling_start) {
			throw new Exception("This function cannot be used within the same function where polling_start() is executed.");
		}

		return $this->POST("_sender_polling_id");
	}

	function polling_get_status($polling_id = null) {

		if ($this->polling_start) {
			throw new Exception("This function cannot be used within the same function where polling_start() is executed.");
		}

		if ($polling_id == null) {
			$polling_id = $this->get_session("_polling_id");
		}

		$dir = $this->dirs->pollingdir;
		$pollingDir = $dir . '/' . $polling_id;
		$infoFile = $pollingDir . '/info.json';

		// 該当するpolling_idのディレクトリが存在するか確認
		if (!is_dir($pollingDir)) {
			return false; // 該当するpolling_idのディレクトリが存在しない場合はfalseを返す
		}

		// info.jsonファイルが存在するか確認
		if (!file_exists($infoFile)) {
			return false; // info.jsonファイルが存在しない場合はfalseを返す
		}

		// ファイルをロックして読み込む
		if (($handle = fopen($infoFile, 'r')) === false) {
			return false; // ファイルのオープンに失敗した場合
		}

		if (!flock($handle, LOCK_SH)) { // 共有ロックを取得
			fclose($handle);
			return false; // ロックの取得に失敗した場合
		}

		$content = stream_get_contents($handle); // ファイル内容を一括読み込み
		if ($content === false) {
			flock($handle, LOCK_UN); // ロックを解除
			fclose($handle);
			return false; // ファイルの読み込みに失敗した場合
		}

		flock($handle, LOCK_UN); // ロックを解除
		fclose($handle); // ファイルを閉じる
		// JSONデコード
		$data = json_decode($content, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return false; // JSONの読み込みに失敗した場合
		}

		return $data["status_text"];
	}

	function polling_get_info_data($polling_id = null) {
		if ($this->polling_start) {
			throw new Exception("This function cannot be used within the same function where polling_start() is executed.");
		}

		if ($polling_id == null) {
			$polling_id = $this->get_session("_polling_id");
		}

		$dir = $this->dirs->pollingdir;
		$pollingDir = $dir . '/' . $polling_id;
		$infoFile = $pollingDir . '/info.json';

		// 該当するpolling_idのディレクトリが存在するか確認
		if (!is_dir($pollingDir)) {
			return false; // 該当するpolling_idのディレクトリが存在しない場合はfalseを返す
		}

		// info.jsonファイルが存在するか確認
		if (!file_exists($infoFile)) {
			return false; // info.jsonファイルが存在しない場合はfalseを返す
		}

		// ファイルをロックして読み込む
		if (($handle = fopen($infoFile, 'r')) === false) {
			return false; // ファイルのオープンに失敗した場合
		}

		if (!flock($handle, LOCK_SH)) { // 共有ロックを取得
			fclose($handle);
			return false; // ロックの取得に失敗した場合
		}

		$content = stream_get_contents($handle); // ファイル内容を一括読み込み
		if ($content === false) {
			flock($handle, LOCK_UN); // ロックを解除
			fclose($handle);
			return false; // ファイルの読み込みに失敗した場合
		}

		flock($handle, LOCK_UN); // ロックを解除
		fclose($handle); // ファイルを閉じる
		// JSONデコード
		$data = json_decode($content, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return false; // JSONの読み込みに失敗した場合
		}

		return $data["info_data"];
	}

	function polling_get_polling_id() {
		if ($this->polling_start) {
			throw new Exception("This function cannot be used within the same function where polling_start() is executed.");
		}
		return $this->get_session("_polling_id");
	}

	function create_linebot(): linebot {
		$setting = $this->get_setting();
		$linebot = new Linebot_class($setting["line_channel_secret"], $setting["line_accesstoken"], $setting["line_logfile"]);
		return $linebot;
	}

	function encrypt_file_fields($row, $table_name) {

		$fields = $this->get_field_list($table_name);
		// ファイルを処理
		foreach ($fields as $f) {
			if ($f["type"] == "file" || $f["type"] == "image") {
				$pname = $f["parameter_name"];
				if (!empty($row[$pname])) {
					$row[$pname] = $this->encrypt($row[$pname]);
				}
			}
		}
		return $row;
	}

	function decrypt_file_fields($row, $table_name) {

		$fields = $this->get_field_list($table_name);
		// ファイルを処理
		foreach ($fields as $f) {
			if ($f["type"] == "file" || $f["type"] == "image") {
				$pname = $f["parameter_name"];
				if (!empty($row[$pname])) {
					$row[$pname] = $this->decrypt($row[$pname]);
				}
			}
		}
		return $row;
	}

	private $exclude_arr = [];
	
	public function exclude_field_list($field_list, $table_name,$op) : array{
		$fmt_db = $this->db("db", "db");
		$db = $fmt_db->select("tb_name", $table_name)[0];
		$db_id = $db["id"];
		
		$ap_list = $this->db("access_policy","assistants")->select(
			["assistant_id","db_id"],
			[$this->assistant["id"],$db_id]
			);
		
		$check_list = [];
		foreach($ap_list as $ap){
			$check_list[$ap["field_id"]] = $ap["policy"];
		}
		
		foreach($field_list as $key=>$f){
			
			if (in_array($f["parameter_name"], ['id', '_enc_id', 'parent_id', 'api_user_id'])) {
				continue;
			}
			
			if(!in_array($op,$check_list[$f["id"]])){
				unset($field_list[$key]);
			}
		}
		
		return $field_list;
	}

	public function exclude_disallow_fields($row, $table_name, $op, $throw_exception = false): array {

		$VALID = ['add' => 1, 'update' => 1, 'get' => 1, 'find' => 1, 'delete' => 1];
		if (!isset($VALID[$op])) {
			throw new Exception("Invalid op name: " . $op);
		}

		$fmt_db = $this->db("db", "db");
		$db = $fmt_db->select("tb_name", $table_name)[0];
		$db_id = $db["id"];

		$ap_list = $this->db("access_policy","assistants")->select(
			["assistant_id","db_id"],
			[$this->assistant["id"],$db_id]
			);

		foreach ($row as $param_name => $v) {

			// 特定の項目を除外
			if (in_array($param_name, ['id', '_enc_id', 'parent_id', 'api_user_id'])) {
				continue;
			}
			
			$bot_access_policy = [];
			foreach ($ap_list as $ap) {
				if ($ap["parameter_name"] == $param_name) {
					$bot_access_policy = $ap["policy"];
					break;
				}
			}

			if (!in_array($op, $bot_access_policy)) {
				if ($throw_exception) {
					throw new Exception(sprintf(
								"Operation '%s' not allowed on field '%s'",
								$op,
								$param_name
							));
				} else {
					unset($row[$param_name]);
					$this->exclude_arr[] = ['field' => $f["parameter_name"], 'reason' => 'forbidden', 'op' => $op];
				}
			}
		}

		return $row;
	}

	function get_exclude_fields(): array {
		return $this->exclude_arr;
	}
	

	function is_smartphone($ua = null){
		if ( is_null($ua) ) {
			$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
		}

		if ( preg_match('/iPhone|iPod|iPad|Android/ui', $ua) ) {
			return true;
		} else {
			return false;
		}
	}
	
	function reload_side_panel(){
		$sidepanel = $this->get_session("_side_panel");
		if(!empty($sidepanel)){
			$this->invoke("rows_child",$sidepanel,"db_exe");
		}
	}
	
	function create_pdfmaker(): pdfmaker_class{
		$pm = new pdfmaker_class();
		$pm->set_controller($this);
		return $pm;
	}

	function create_ValueFormatter(): ValueFormatter {
		$setting = $this->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}
		return new ValueFormatter($setting);
	}
	
	function cron_set() {
		
		include(dirname(__FILE__) . "/CronRegister.php");
		$cr = new CronRegister();

		// Cron
		$list_cron = $this->db("cron","cron")->getall();
		foreach($list_cron as $a){				
			// 未設定は除外して登録する
			if(!(empty($a["min"]) && empty($a["hour"]) && empty($a["day"]) && empty($a["month"] && empty($a["weekday"])))){
				$id_enc = $this->encrypt($a["id"]);
				$url = $this->get_APP_URL("cron","exec",["id"=>$id_enc]);
				$command = "curl -s '" . $url . "'";
				$cr->add($a["min"], $a["hour"], $a["day"], $a["month"], $a["weekday"], $command);
			}

		}

		$cr->write_all();

	}

}
