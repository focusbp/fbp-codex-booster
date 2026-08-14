<?php

include dirname(__FILE__) . '/../../interface/FFM.php';

if (!class_exists("FixedFileManagerFieldLengthException", false)) {
	class FixedFileManagerFieldLengthException extends Exception {
		private $violations;

		function __construct(array $violations) {
			$this->violations = array_values($violations);
			parent::__construct($this->build_message($this->violations));
		}

		public function getViolations(): array {
			return $this->violations;
		}

		private function build_message(array $violations): string {
			if (count($violations) === 0) {
				return "フィールドサイズを超えています。";
			}
			$messages = [];
			foreach ($violations as $violation) {
				$field = (string) ($violation["field"] ?? "");
				$actual = (int) ($violation["actual_bytes"] ?? 0);
				$max = (int) ($violation["max_bytes"] ?? 0);
				$ja = (int) ($violation["max_chars_ja"] ?? 0);
				$messages[] = $field . " は " . $actual . " bytes です（上限 " . $max . " bytes / 日本語目安 " . $ja . "文字）。";
			}
			return "フィールドサイズを超えています: " . implode(" ", $messages);
		}
	}
}

class fixed_file_manager_read_only_format_change_required extends RuntimeException {}

class fixed_file_manager implements FFM {
	private const INDEX_VERSION = 2;
	private const INDEX_SHARDS = 128;
	private const INDEX_MANIFEST_MAGIC = "FFMMETA2";
	private const INDEX_SHARD_MAGIC = "FFMSHRD2";
	private const INDEX_MANIFEST_SIZE = 4200;
	private const INDEX_SHARD_HEADER_SIZE = 64;
	private const INDEX_SHARD_ENTRY_SIZE = 48;
	private const TEXT_SCAN_BLOCK_BYTES = 4194304;
	private const TEXT_SCAN_MAX_CANDIDATES = 50000;
	

	private $filename;
	private $format;
	private $json;
	private $datadir;
	private $formatdir;
	public $hf; // ファイルハンドラー
	private $eof;
	private $path_fmt;
	public $path_dat;
	private $path_bak;
	private $path_tmp;
	private $path_json;
	private $header;  // ver:バージョン  maxid:最大ID　format_txt:フォーマットのテキスト表記
	private $before_end_flg;
	private $flg_prepared = false;
	private $prohibition_item_name = ["class", "function", "dbclass", "db", "dbname", "windowcode", "cmd", "chart", "multi_dialog", "reloadarea", "appendarea", "debug_window", "form", "data"];
	private $flg_filter_zero = false;
	private $info_classname;
	private $info_tablename;
	private $ctl;
	private $read_only = false;
	private $format_source = "fmt";
	private $operation_log_dir;
	private $strict_field_length = false;
	private $allow_zero_length_read_for_change_format = false;
	private $index_cache = [];
	private $index_ready = [];
	private $index_disabled = false;
	private $text_search_disabled = false;

	private function is_empty_filter_itemname($iname): bool {
		if (is_array($iname)) {
			foreach ($iname as $name) {
				if (!$this->is_empty_filter_itemname($name)) {
					return false;
				}
			}
			return true;
		}
		return trim((string) $iname) === "";
	}

	private function is_numeric_text_value($value): bool {
		return preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/', trim((string) $value)) === 1;
	}

	private function is_select_text_match($stored_value, $search_value): bool {
		$stored = trim((string) $stored_value);
		if ($stored === $search_value) {
			return true;
		}
		if (!is_int($search_value) && !is_float($search_value)) {
			return false;
		}
		$search = (string) $search_value;
		return $this->is_numeric_text_value($stored)
			&& $this->is_numeric_text_value($search)
			&& $stored === $search;
	}

	private function is_filter_text_partial_match($stored_value, $search_value): bool {
		$stored = trim((string) $stored_value);
		if (!is_scalar($search_value) && $search_value !== null) {
			// Keep the legacy strpos() type error for unsupported filter values.
			strpos($stored, $search_value);
			return false;
		}
		$search = (string) $search_value;
		if (($stored == $search) || strpos($stored, $search) !== false) {
			return true;
		}
		$stored_upper = strtoupper($stored);
		$search_upper = strtoupper($search);
		if (strpos($stored_upper, $search_upper) !== false) {
			return true;
		}
		$tokens = preg_split("/[\s,]+/", $search_upper);
		if (!is_array($tokens)) return false;
		foreach ($tokens as $token) {
			if ($token !== "" && strpos($stored_upper, $token) !== false) return true;
		}
		return false;
	}

	/*
	 * デバッグ用
	 */

	function get_path_dat() {
		return $this->path_dat;
	}

	public function get_header_info(): array {
		return $this->header;
	}

	public function get_format(): array {
		return $this->format;
	}
	
	function set_controller(Controller $ctl){
		$this->ctl = $ctl;
	}

	public function set_strict_field_length(bool $flg): void {
		$this->strict_field_length = $flg;
	}

	public function validate_field_lengths(array $dataset): array {
		return $this->field_length_violations($dataset);
	}

//	function get_unique_key(){
//		return $this->datadir . $this->filename; //  Controller_class.php の $key = $ddir . "/" . $name と同じ
//	}

	/*
	 * コンストラクター
	 */
	function __construct($filename, $datadir = null, $formatdir = null, array $options = []) {

		$this->format_source = (string) ($options["format_source"] ?? "fmt");
		if (!in_array($this->format_source, ["fmt", "dat_header"], true)) {
			throw new Exception("Unknown format source : " . $this->format_source);
		}
		$this->read_only = !empty($options["read_only"]);
		$this->index_disabled = !empty($options["index_disabled"]) || (string) getenv("FBP_FFM_INDEX_DISABLE") === "1";
		$this->text_search_disabled = !empty($options["text_search_disabled"]) || (string) getenv("FBP_FFM_TEXT_SEARCH_DISABLE") === "1";

		//パラメータ設定
		if ($datadir == null) {
			throw new Exception("datadir is null");
			//$this->datadir = dirname(__FILE__) . "/data/";
		} else {
			$this->datadir = $datadir . "/";
		}
		if ($formatdir == null) {
			if ($this->format_source === "dat_header") {
				$this->formatdir = null;
			} else {
				throw new Exception("formatdir is null");
				//$this->formatdir = dirname(__FILE__) . "/fmt/";
			}
		} else {
			$this->formatdir = $formatdir . "/";
		}
		$this->filename = $filename;
		$this->path_fmt = $this->formatdir === null ? null : $this->formatdir . $filename . ".fmt";
		$this->path_dat = $this->datadir . $filename . ".dat";
		$this->path_tmp = $this->datadir . $filename . ".tmp";
		$this->path_bak = $this->datadir . $filename . "-" . date("Ymd") . ".bak";
		$this->path_json = $this->formatdir === null ? null : $this->formatdir . $filename . ".json";

		//フォルダ作成
		if (!$this->read_only && !is_dir($this->datadir)) {
			$check = mkdir($this->datadir);
			if (!$check) {
				echo "Directory: " . $this->datadir;
			}
		}
		if (!$this->read_only && $this->formatdir !== null && !is_dir($this->formatdir)) {
			$res = @mkdir($this->formatdir, 0777, true);
			if ($res === false) {
				throw new Exception("Can't make directory:" . $this->formatdir);
			}
		}

		if ($this->format_source === "dat_header") {
			if (!is_file($this->path_dat)) {
				throw new Exception("No dat file : " . $this->path_dat);
			}
			$this->openDatFile();
			return;
		}

		// fmtファイルの読み込み
		$format_txt = $this->readFmtFile();

		// datファイルがなかったら作成する
		if (!file_exists($this->path_dat)) {
			$header_txt = $this->makeHeader(0, $format_txt, $this->parseFormat($format_txt));
			file_put_contents($this->path_dat, $header_txt);

			//パーミッションを変更する
			chmod($this->path_dat, 0770);
		}

		// datファイルをオープン
		$this->openDatFile();

		// フォーマットの変化を検知してデータを変換する
		if ($format_txt != $this->header["format_txt"]) {
			if ($this->read_only) {
				$this->close();
				throw new fixed_file_manager_read_only_format_change_required(
					"Read-only fixed_file_manager requires format conversion : " . $this->path_dat
				);
			}
			$this->flg_prepared = false;
			$this->changeFormat($format_txt);
		}
		$this->prepare_indexes();
	}

	public static function can_open_read_only(string $filename, string $datadir, string $formatdir): bool {
		$path_dat = rtrim($datadir, "/") . "/" . $filename . ".dat";
		$path_fmt = rtrim($formatdir, "/") . "/" . $filename . ".fmt";
		if (!is_file($path_dat) || !is_file($path_fmt)) return false;

		$fmt_source = @file_get_contents($path_fmt);
		if (!is_string($fmt_source)) return false;
		$format_txt = self::normalize_format_text($fmt_source);
		$hf = @fopen($path_dat, "rb");
		if (!is_resource($hf)) return false;
		try {
			if (!flock($hf, LOCK_SH)) return false;
			$fixed_header = fread($hf, 44);
			if (!is_string($fixed_header) || strlen($fixed_header) !== 44) return false;
			$header_size_text = substr($fixed_header, 28, 16);
			if (preg_match('/^[0-9]{16}$/', $header_size_text) !== 1) return false;
			$header_size = (int) $header_size_text;
			$file_stat = fstat($hf);
			if ($header_size < 44 || !is_array($file_stat) || $header_size > (int) ($file_stat["size"] ?? 0)) return false;
			$format_size = $header_size - 44;
			$dat_format = fread($hf, $format_size);
			return is_string($dat_format)
				&& strlen($dat_format) === $format_size
				&& trim($dat_format) === $format_txt;
		} finally {
			@flock($hf, LOCK_UN);
			@fclose($hf);
		}
	}

	private static function normalize_format_text(string $source): string {
		$ret = "";
		foreach (explode("\n", $source) as $line) {
			$line = trim($line);
			if ($line !== "") {
				$ret .= str_replace(":", "", $line) . ":";
			}
		}
		return $ret;
	}

	public static function open_dat_header_readonly(string $path_dat): fixed_file_manager {
		$path_dat = trim($path_dat);
		if ($path_dat === "") {
			throw new Exception("dat path is empty");
		}
		if (substr($path_dat, -4) !== ".dat") {
			throw new Exception("dat path must end with .dat : " . $path_dat);
		}
		$filename = pathinfo($path_dat, PATHINFO_FILENAME);
		$datadir = dirname($path_dat);
		return new fixed_file_manager($filename, $datadir, null, [
			"format_source" => "dat_header",
			"read_only" => true,
		]);
	}

	private function assert_writable(string $operation): void {
		if ($this->read_only) {
			throw new Exception("Read-only fixed_file_manager cannot " . $operation . " : " . $this->path_dat);
		}
	}

	/*
	 * 全データ削除
	 */

	public function allclear() {
		$this->assert_writable("allclear");
		$this->mark_indexes_dirty();
		$snapshot = $this->snapshot_dat_file("allclear");
		$this->write_operation_log("allclear", null, null, [
			"snapshot" => $snapshot,
		]);
		$this->close();
		$format_txt = $this->readFmtFile();
		$header_txt = $this->makeHeader(0, $format_txt, $this->parseFormat($format_txt));
		file_put_contents($this->path_dat, $header_txt);
		//パーミッションを変更する
		chmod($this->path_dat, 0770);

		$this->flg_prepared = false;
		$this->openDatFile();
		$this->rebuild_indexes();
	}

	/*
	 * datファイルをオープン
	 */

	function openDatFile($flg_order_check = true, $flg_check_duplicate = true) {

		if ($flg_check_duplicate) {
			$realpath = realpath($this->path_dat);
			if (!isset($GLOBALS["lock_class_arr"])) {
				$GLOBALS["lock_class_arr"] = array();
			}
			foreach ($GLOBALS["lock_class_arr"] as $key => $c) {
				$rp = realpath($c->path_dat);
				if ($realpath == $rp) {
					throw new Exception("You can't open the database as multiple instance. : " . $realpath);
				}
			}
		}

		$open_mode = $this->read_only ? "rb" : "r+b";
		if ($this->hf = fopen($this->path_dat, $open_mode)) {

			//$this->log(realpath($this->path_dat),"WAIT");

			if ($flg_order_check) {

				//アルファベット順に開かれているかチェック
				$rp = realpath($this->path_dat);
				$GLOBALS["lock_class_arr"][] = $this;
				//ファイル名を取り出してソート
				$sortkey = array();
				foreach ($GLOBALS["lock_class_arr"] as $key => $c) {
					$sortkey[$key] = realpath($c->path_dat);
				}
				array_multisort($sortkey, SORT_ASC, $GLOBALS["lock_class_arr"]);

				$endclass = end($GLOBALS["lock_class_arr"]);

				if ($rp != realpath($endclass->path_dat)) {
					//アルファベット順じゃない！
					//すべてロック解除
					foreach ($GLOBALS["lock_class_arr"] as $c) {
						$c->closeDatFile();
					}

					//順序通りに再度ロック
					foreach ($GLOBALS["lock_class_arr"] as $c) {
						$c->openDatFile(false, false);
					}
					return;
				}
			}

			//ロック実行
			$lock_mode = $this->read_only ? LOCK_SH : LOCK_EX;
			$lockresult = flock($this->hf, $lock_mode);

			//$this->log(realpath($this->path_dat),"LOCK");

			if (!$this->flg_prepared) {
				//スクリプト終了時に安全に終了するための関数を登録する
				register_shutdown_function(function ($class) {
					if (is_resource($class->hf)) {
						@flock($class->hf, LOCK_UN);
						@fclose($class->hf);
					}
				}, $this);

				// ヘッダを読み込む
				$this->header = $this->getHeader();

				// フォーマットをセット
				$this->format = $this->parseFormat($this->header["format_txt"]);

				// jsonを読み込み
				if ($this->path_json !== null && is_file($this->path_json)) {
					$json = file_get_contents($this->path_json);
					$this->json = json_decode($json, true);
				} else {
					$this->json = [];
				}

				$this->flg_prepared = true;
			}

			// 終端を記録
			fseek($this->hf, 0, SEEK_END);
			$this->eof = ftell($this->hf);
		} else {
			throw new Exception('File Open Fail : ' . $this->path_dat);
		}
	}

	/*
	 * $ctl->db からインスタンスを作成した場合は、直接このclose()を呼んではならない。$ctl->close_db_by_ffm(FFM $ffm) でクローズすること！
	 */

	function close() {
		$this->closeDatFile();

		// リストから削除
		$rp = realpath($this->path_dat);
		foreach ($GLOBALS["lock_class_arr"] as $key => $c) {
			if ($rp == realpath($c->path_dat)) {
				unset($GLOBALS["lock_class_arr"][$key]);
			}
		}
	}

	//クローズ
	function closeDatFile() {
		$this->close_index_cache();
		if (is_resource($this->hf)) {
			@flock($this->hf, LOCK_UN);
			@fclose($this->hf);
		}
	}

	// Save JSON
	function save_json() {
		$this->assert_writable("save_json");

		foreach ($this->json as $key => $jname) {
			$flg = false;
			foreach ($this->format as $item) {
				if ($key == $item["name"]) {
					$flg = true;
					break;
				}
			}
			if (!$flg) {
				unset($this->json[$key]);
			}
		}

		file_put_contents($this->path_json, json_encode($this->json));
	}

	private function field_length_value_string(array $field, $value): string {
		if ($field["type"] === "A") {
			$encoded = json_encode($value);
			return $encoded === false ? "" : (string) $encoded;
		}
		if (is_array($value)) {
			throw new Exception($field["name"] . " has array value. Please check the file format. The type should be A(=array)");
		}
		return (string) $value;
	}

	private function field_length_violations(array $dataset): array {
		$violations = [];
		foreach ($this->format as $field) {
			$name = (string) ($field["name"] ?? "");
			$max_bytes = (int) ($field["size"] ?? 0);
			if ($name === "" || $name === "id" || $max_bytes <= 0 || !array_key_exists($name, $dataset)) {
				continue;
			}
			$value_string = $this->field_length_value_string($field, $dataset[$name]);
			$actual_bytes = strlen($value_string);
			if ($actual_bytes <= $max_bytes) {
				continue;
			}
			$violations[] = [
				"field" => $name,
				"type" => (string) ($field["type"] ?? ""),
				"max_bytes" => $max_bytes,
				"max_chars_ja" => max(1, (int) floor($max_bytes / 3)),
				"actual_bytes" => $actual_bytes,
				"actual_chars" => function_exists("mb_strlen") ? mb_strlen($value_string, "UTF-8") : strlen($value_string),
				"value_preview" => function_exists("mb_strimwidth") ? mb_strimwidth($value_string, 0, 80, "...", "UTF-8") : substr($value_string, 0, 80),
			];
		}
		return $violations;
	}

	private function assert_field_lengths(array $dataset): void {
		if (!$this->strict_field_length) {
			return;
		}
		$violations = $this->field_length_violations($dataset);
		if (count($violations) > 0) {
			throw new FixedFileManagerFieldLengthException($violations);
		}
	}

	function insert(&$dataset) {
		$this->assert_writable("insert");
		// $this->hf をチェックする
		$this->check_hf();
		$this->assert_field_lengths($dataset);

		$this->mark_indexes_dirty();
		$p = ftell($this->hf); //あとで戻す
		// 
		//最大IDをセット
		$this->header["maxid"]++;
		$id = $this->header["maxid"];
		$dataset["id"] = $id;
		$this->write_operation_log("insert", null, $dataset);

		//最大IDの変更のためヘッダを保存
		$header_txt = $this->makeHeader($this->header["maxid"], $this->header["format_txt"], $this->format);
		rewind($this->hf);
		fwrite($this->hf, $header_txt);

		//ファイルの最後に移動
		fseek($this->hf, 0, SEEK_END);

		//書き込む
		$this->writedata($dataset);

		// 終端を記録
		fseek($this->hf, 0, SEEK_END);
		$this->eof = ftell($this->hf);

		fseek($this->hf, $p); //戻す
		$this->apply_index_insert($dataset);
		return $id;
	}

	function delete($id) {
		$this->assert_writable("delete");
		$p = ftell($this->hf); //あとで戻す
		$d = $this->get($id);
		//ポインタを戻す
		if ($d != null) {
			$this->mark_indexes_dirty();
			$this->write_operation_log("delete", $d, null);
			fseek($this->hf, -1 * $this->header["recordsize"], SEEK_CUR);
			fwrite($this->hf, "X");
			$this->apply_index_delete($d);
		}
		fseek($this->hf, $p); //戻す
	}

	function update($dataset) {
		$this->assert_writable("update");
		$this->assert_field_lengths($dataset);
		$p = ftell($this->hf); //あとで戻す
		$d = $this->get($dataset["id"]);

		//ポインタを戻して書き込む
		if ($d != null) {
			$this->mark_indexes_dirty();

			// $dのデータを上書き
			foreach ($dataset as $key => $val) {
				$d[$key] = $val;
			}
			$before = $this->get($dataset["id"]);
			$this->write_operation_log("update", $before, $d);

			fseek($this->hf, -1 * $this->header["recordsize"], SEEK_CUR);
			$this->writedata($d);
			$this->apply_index_update($before, $d);
		}
		fseek($this->hf, $p); //戻す
	}

	// seek()を行った後に呼び出してデータを取得する
	function next() {

		while (ftell($this->hf) < $this->eof) {

			// flgとIDをファイルから読み込む
			$flg = fread($this->hf, 1);
			$id_f = (int) fread($this->hf, $this->format[0]["size"]);

			if ($flg == " ") {
				$arr = array();
				$arr["id"] = $id_f;
				$this->readdata($arr);
				return $arr;
			} else {
				//ポインタを移動
				$move = $this->header["recordsize"] - 1 - $this->format[0]["size"]; //レコードサイズからflgとＩＤのサイズを引いた分
				fseek($this->hf, $move, SEEK_CUR);
			}
		}
		return null;
	}

	private function find_record_offset_by_id($id, bool $include_deleted = false): ?array {
		if (empty($id)) {
			return null;
		}

		$this->check_hf();
		$current = ftell($this->hf);

		try {
			fseek($this->hf, $this->header["headersize"]);

			$start = 1;
			$end = ($this->eof - $this->header["headersize"]) / $this->header["recordsize"];
			if ($end < 1) {
				return null;
			}
			$center = $start + floor(($end - $start) / 2);

			while (true) {
				$offset = $this->header["headersize"] + $this->header["recordsize"] * ($center - 1);
				fseek($this->hf, $offset);

				$flg = fread($this->hf, 1);
				$id_f = (int) fread($this->hf, $this->format[0]["size"]);

				if ((int) $id === $id_f) {
					if ($flg === " " || ($include_deleted && $flg === "X")) {
						return [
							"offset" => $offset,
							"flag" => $flg,
							"id" => $id_f,
						];
					}
					return null;
				}

				if ($start >= $end) {
					return null;
				}

				if ((int) $id > $id_f) {
					$start = $center + 1;
				} else {
					$end = $center - 1;
				}
				$center = $start + floor(($end - $start) / 2);
			}
		} finally {
			fseek($this->hf, $current);
		}
	}

	public function restore_deleted_record(array $dataset): void {
		$this->assert_writable("restore_deleted_record");
		if (empty($dataset["id"])) {
			throw new Exception("id is required to restore deleted record");
		}

		$record = $this->find_record_offset_by_id((int) $dataset["id"], true);
		if ($record === null) {
			throw new Exception("Deleted record was not found: " . (int) $dataset["id"]);
		}
		if ($record["flag"] !== "X") {
			throw new Exception("Record is not deleted: " . (int) $dataset["id"]);
		}

		$p = ftell($this->hf);
		$this->write_operation_log("restore_deleted_record", null, $dataset, [
			"restored_from_flag" => "X",
		]);
		fseek($this->hf, (int) $record["offset"]);
		$this->mark_indexes_dirty();
		$this->writedata($dataset);
		$this->apply_index_insert($dataset);
		fseek($this->hf, $p);
	}

	// 指定した件数のデータに移動する
	function seek($start_number) {

		//先頭からヘッダ分移動する
		fseek($this->hf, $this->header["headersize"]);

		$c = 0;

		while (ftell($this->hf) < $this->eof) {

			// flgとIDをファイルから読み込む
			$flg = fread($this->hf, 1);
			$id_f = (int) fread($this->hf, $this->format[0]["size"]);

			// flgをチェック
			if ($flg == " ") {
				$c++;

				if ($c == $start_number) {
					//ポインタをデータの先頭に戻す
					fseek($this->hf, -1 * ($this->format[0]["size"] + 1), SEEK_CUR);
					return true;
				}
			}
			//ポインタを移動
			$move = $this->header["recordsize"] - 1 - $this->format[0]["size"]; //レコードサイズからflgとＩＤのサイズを引いた分
			fseek($this->hf, $move, SEEK_CUR);
		}

		return false;
	}

	function seek_end() {

		$this->check_hf();

		//ファイルの最後に移動
		fseek($this->hf, 0, SEEK_END);

		//レコードサイズ分移動
		while (ftell($this->hf) >= $this->header["headersize"]) {
			fseek($this->hf, -1 * $this->header["recordsize"], SEEK_CUR);
			$flg = fread($this->hf, 1);
			if ($flg == " ") {
				fseek($this->hf, -1, SEEK_CUR);
				return true;
			} else {
				if (ftell($this->hf) < $this->header["recordsize"]) {
					fseek($this->hf, 0);
				} else {
					fseek($this->hf, -1, SEEK_CUR);
				}
			}
		}

		$this->before_end_flg = true;

		return false;
	}

	function before() {

		while (ftell($this->hf) >= $this->header["headersize"]) {

			// flgとIDをファイルから読み込む
			$flg = fread($this->hf, 1);
			$id_f = (int) fread($this->hf, $this->format[0]["size"]);

			if ($flg == " ") {
				$arr = array();
				$arr["id"] = $id_f;
				$this->readdata($arr);
				$move = -1 * $this->header["recordsize"] * 2;
				if (ftell($this->hf) < (-1 * $move)) {
					fseek($this->hf, 0);
				} else {
					fseek($this->hf, $move, SEEK_CUR);
				}
				return $arr;
			} else {
				//ポインタを移動
				$move = -1 * $this->header["recordsize"] - 1 - $this->format[0]["size"];
				if (ftell($this->hf) < (-1 * $move)) {
					fseek($this->hf, 0);
				} else {
					fseek($this->hf, $move, SEEK_CUR);
				}
			}
		}
		return null;
	}

	//
	function filter($itemname, $value, $exact_match = false, $and_or = "AND", $sortitem = null, $sort_order = SORT_DESC, $max = null, &$is_last = null, $match_patterns = null) {


		// 配列以外でも受け付ける
		if (!is_array($itemname)) {
			$itemname = [$itemname];
		}
		if (!is_array($value)) {
			$value = [$value];
		}
		if (!is_array($match_patterns)) {
			$match_patterns = [];
			foreach ($itemname as $key => $val) {
				$match_patterns[$key] = "=";
			}
		}

		if ($sort_order == null) {
			$sort_order = SORT_DESC;
		} else {
			if ($sort_order == "asc") {
				$sort_order = SORT_ASC;
			} else if ($sort_order == "desc") {
				$sort_order = SORT_DESC;
			}
		}

		if ($sortitem == "id" && $sort_order == SORT_DESC) {
			$sortitem = null;
		}

		// エラーチェック
		if (!is_array($itemname)) {
			throw new Exception("\$itemname should be array. filter([\"itemname\"],[\"value\"]...");
		}

		if (!is_array($value)) {
			throw new Exception("\$value should be array. filter([\"itemname\"],[\"value\"]...");
		}

		// エラーチェック
		if (count($itemname) != count($value)) {
			throw new Exception("It must be same number of array \$itemname and \$value");
		}

		if (!($and_or == "AND" || $and_or == "OR")) {
			throw new Exception("The and_or parameter must be 'AND' or 'OR'");
		}

		$is_last = true;

		// IDを整列し直す
		$itemname = array_values($itemname);
		$value = array_values($value);

		// 空の項目名は条件として扱わない
		foreach ($itemname as $key => $iname) {
			if ($this->is_empty_filter_itemname($iname)) {
				unset($itemname[$key]);
				unset($value[$key]);
				unset($match_patterns[$key]);
			}
		}
		$itemname = array_values($itemname);
		$value = array_values($value);
		$match_patterns = array_values($match_patterns);

		// 空白の値を排除
		if ($this->flg_filter_zero) {
			// 0 を１つの値とする
			foreach ($value as $key => $val) {
				if ($value[$key] === "0" || $value[$key] === 0) {
					continue;
				} else {
					if (empty($value[$key])) {
						unset($itemname[$key]);
						unset($value[$key]);
						unset($match_patterns[$key]);
					}
				}
			}
		} else {
			// 0は空白とみなす
			foreach ($value as $key => $val) {
				if (empty($value[$key])) {
					unset($itemname[$key]);
					unset($value[$key]);
					unset($match_patterns[$key]);
				}
			}
		}
		$itemname = array_values($itemname);
		$value = array_values($value);
		$match_patterns = array_values($match_patterns);

		// Itemtypeをセット
		$itemtype = array();
		foreach ($itemname as $iname) {
			if (!is_array($iname)) {
				foreach ($this->format as $f) {
					if ($f["name"] == $iname) {
						$itemtype[$iname] = $f["type"];
						break;
					}
				}
			} else {
				foreach ($iname as $name) {
					foreach ($this->format as $f) {
						if ($f["name"] == $name) {
							$itemtype[$name] = $f["type"];
							break;
						}
					}
				}
			}
		}

		// 
		if (count($itemname) == 0) {
			$all = true;
		} else {
			$all = false;
		}

		// Standard Screen は部分一致を含むため exact_match=false で呼ばれる。
		// この場合も、AND 条件に含まれる数値型の完全一致だけは安全に候補を絞れる。
		$candidate_ids = $this->indexed_candidate_ids($itemname, $value, $match_patterns, $and_or, !$exact_match);
		if ($candidate_ids === null && !$exact_match) {
			$candidate_ids = $this->text_filter_candidate_ids($itemname, $value, $and_or, $sortitem, $max);
		}
		$candidate_pos = 0;
		if ($candidate_ids === null) {
			$this->seek_end();
		}
		$arr = array();
		$c = 0;

		while (true) {
			if ($candidate_ids === null) {
				$d = $this->before();
				if ($d === null) break;
			} else {
				if ($candidate_pos >= count($candidate_ids)) break;
				$d = $this->get($candidate_ids[$candidate_pos++]);
				if ($d === null) continue;
			}

			if ($and_or == "AND") {
				$flg = true;
			} else {
				$flg = false;
			}

			if ($all == false) {
				foreach ($itemname as $key => $iname) {

					if (is_array($iname)) {
						// itemがarrayの場合
						//この機能を使う場合はすべてテキストでなけれならなない
						foreach ($iname as $name) {
							if ($itemtype[$name] != "T") {
								throw new Exception("If you use item as array, these type must be T : $name = " . $itemtype[$name]);
							}
						}

						$check = false;
						foreach ($iname as $name) {
							$field_check = false;
							$v = trim($d[$name]);
							if (empty($v)) {
								continue;
							}
							if ($exact_match) {
								if ($v === $value[$key]) {
									$field_check = true;
								}
							} else {
								$field_check = $this->is_filter_text_partial_match($v, $value[$key]);
							}
							$check = $check || $field_check;
						}

						if ($and_or == "AND") {
							$flg = $flg && $check;
						} else {
							$flg = $flg || $check;
						}
					} else if ($itemtype[$iname] == "T") {
						// テキスト
						$check = false;
						$v = trim($d[$iname]);
						if ($exact_match) {
							if ($v === $value[$key]) {
								$check = true;
							}
						} else {
							$check = $this->is_filter_text_partial_match($v, $value[$key]);
						}

						if ($and_or == "AND") {
							$flg = $flg && $check;
						} else {
							$flg = $flg || $check;
						}
					} else if ($itemtype[$iname] == "A") {
						// 配列(JSON)保存された checkbox を検索する
						$check = false;
						$field_value = $d[$iname] ?? [];
						$search_value = $value[$key];
						if (!is_array($field_value)) {
							$field_value = (($tmp = json_decode((string) $field_value, true)) !== null) ? $tmp : [];
						}

						if (is_array($search_value)) {
							$search_value = array_values(array_filter($search_value, static function ($v) {
								return $v !== "" && $v !== null;
							}));
							if (count($search_value) === 0) {
								$check = true;
							} else {
								$matched = 0;
								foreach ($search_value as $sv) {
									if (in_array((string) $sv, array_map('strval', $field_value), true)) {
										$matched++;
									}
								}
								$check = ($matched === count($search_value));
							}
						} else if ($search_value === "__EMPTY__") {
							$check = count($field_value) === 0;
						} else if ($search_value === "" || $search_value === null) {
							$check = true;
						} else {
							$check = in_array((string) $search_value, array_map('strval', $field_value), true);
						}

						if ($and_or == "AND") {
							$flg = $flg && $check;
						} else {
							$flg = $flg || $check;
						}
					} else {
						// 数字
						$check = false;
						$v = $d[$iname];
						$match_pattern = $match_patterns[$key] ?? "=";
						if ($match_pattern == "=") {
							if ($v == $value[$key]) {
								$check = true;
							}
						} else if ($match_pattern == ">") {
							if ($v > $value[$key]) {
								$check = true;
							}
						} else if ($match_pattern == "<") {
							if ($v < $value[$key]) {
								$check = true;
							}
						} else if ($match_pattern == ">=") {
							if ($v >= $value[$key]) {
								$check = true;
							}
						} else if ($match_pattern == "<=") {
							if ($v <= $value[$key]) {
								$check = true;
							}
						} else {
							throw new Exception("Match Pattern is wrong: " . $match_pattern);
						}

						if ($and_or == "AND") {
							$flg = $flg && $check;
						} else {
							$flg = $flg || $check;
						}
					}
				}
			} else {
				$flg = true;
			}

			if ($flg) {
				$arr[] = $d;
				$c++;
				if ($sortitem == null) {
					if ($max != null) {
						if ($c > $max) {
							$is_last = false;
							break;
						}
					}
				}
			}
		}

		// ソート
		if ($sortitem != null) {
			$sortkey = array();
			foreach ($arr as $key => $d) {
				$sortkey[$key] = $d[$sortitem] ?? null;
			}
			array_multisort($sortkey, $sort_order, $arr);
		}

		// ソートがあった場合にmaxを超えている場合があるので切る
		if ($max != null) {
			$c = 0;
			foreach ($arr as $key => $val) {
				$c++;
				if ($c > $max) {
					unset($arr[$key]);
					$is_last = false;
				}
			}
		}

		// 暗号化を入れる
		$ctl = Controller_class::getInstance();
		if ($ctl != null) {
			// 
			foreach ($arr as &$d) {
				$d["_id_enc"] = $ctl->encrypt($d["id"]);
			}
		}

		return $arr;
	}

	function select($itemname, $value, $match_patterns = true, $and_or = "AND", $sortitem = null, $sort_order = SORT_DESC, $max = null, &$is_last = null) {

		// 配列以外でも受け付ける
		if (!is_array($itemname)) {
			$itemname = [$itemname];
		}
		if (!is_array($value)) {
			$value = [$value];
		}

		$itemname = array_values($itemname);
		$value = array_values($value);
		if (is_array($match_patterns)) {
			$match_patterns = array_values($match_patterns);
		}

		// 空の項目名は条件として扱わない
		foreach ($itemname as $key => $iname) {
			if ($this->is_empty_filter_itemname($iname)) {
				unset($itemname[$key]);
				unset($value[$key]);
				if (is_array($match_patterns)) {
					unset($match_patterns[$key]);
				}
			}
		}
		$itemname = array_values($itemname);
		$value = array_values($value);
		if (is_array($match_patterns)) {
			$match_patterns = array_values($match_patterns);
		}

		if ($sort_order == null) {
			$sort_order = SORT_DESC;
		}

		if ($sortitem == "id" && $sort_order == SORT_DESC) {
			$sortitem = null;
		}

		// 前はtrue/falseでやっていた。falseは部分一致の意味だったがバグを生むので不要
		if (!is_array($match_patterns)) {
			$match_patterns = [];
			foreach ($itemname as $key => $val) {
				$match_patterns[$key] = "=";
			}
		}

		// エラーチェック
		if (!is_array($itemname)) {
			throw new Exception("\$itemname should be array. select([\"itemname\"],[\"value\"]...");
		}

		if (!is_array($value)) {
			throw new Exception("\$value should be array. select([\"itemname\"],[\"value\"]...");
		}

		if (count($itemname) != count($value)) {
			throw new Exception("It must be same number of array \$itemname and \$value");
		}

		if (!($and_or == "AND" || $and_or == "OR")) {
			throw new Exception("The and_or parameter must be 'AND' or 'OR'");
		}

		foreach ($this->format as $f) {
			if ($f["type"] == "A" && in_array($f["name"], $itemname)) {
				throw new Exception("Can't search Field type A!");
			}
		}

		if (count($itemname) == 0) {
			return $this->getall($sortitem, $sort_order);
		}

		$is_last = true;

		// Itemtypeをセット
		$itemtype = array();
		foreach ($itemname as $iname) {
			foreach ($this->format as $f) {
				if ($f["name"] == $iname) {
					$itemtype[$iname] = $f["type"];
					break;
				}
			}
		}

		$candidate_ids = $this->indexed_candidate_ids($itemname, $value, $match_patterns, $and_or);
		$candidate_pos = 0;
		if ($candidate_ids === null) {
			$this->seek_end();
		}
		$arr = array();
		$c = 0;

		while (true) {
			if ($candidate_ids === null) {
				$d = $this->before();
				if ($d === null) break;
			} else {
				if ($candidate_pos >= count($candidate_ids)) break;
				$d = $this->get($candidate_ids[$candidate_pos++]);
				if ($d === null) continue;
			}

			if ($and_or == "AND") {
				$flg = true;
			} else {
				$flg = false;
			}

			foreach ($itemname as $key => $iname) {

				if ($itemtype[$iname] == "T") {
					// テキスト
					$check = false;
					$v = trim($d[$iname]);

					$match_pattern = $match_patterns[$key];
					if ($match_pattern == "=") {
						if ($this->is_select_text_match($v, $value[$key])) {
							$check = true;
						}
					} else {
						throw new Exception("Match Pattern is wrong: " . $match_pattern);
					}

					if ($and_or == "AND") {
						$flg = $flg && $check;
					} else {
						$flg = $flg || $check;
					}
				} else {
					// 数字
					$check = false;
					$v = $d[$iname];
					$match_pattern = $match_patterns[$key];
					if ($match_pattern == "=") {
						if ($v == $value[$key]) {
							$check = true;
						}
					} else if ($match_pattern == ">") {
						if ($v > $value[$key]) {
							$check = true;
						}
					} else if ($match_pattern == "<") {
						if ($v < $value[$key]) {
							$check = true;
						}
					} else if ($match_pattern == ">=") {
						if ($v >= $value[$key]) {
							$check = true;
						}
					} else if ($match_pattern == "<=") {
						if ($v <= $value[$key]) {
							$check = true;
						}
					} else {
						throw new Exception("Match Pattern is wrong: " . $match_pattern);
					}

					if ($and_or == "AND") {
						$flg = $flg && $check;
					} else {
						$flg = $flg || $check;
					}
				}
			}


			if ($flg) {
				$arr[] = $d;
				$c++;
				if ($sortitem == null) {
					if ($max != null) {
						if ($c >= $max) {
							$is_last = false;
							break;
						}
					}
				}
			}
		}


		// ソート
		if ($sortitem != null) {
			$sortkey = array();
			foreach ($arr as $key => $d) {
				$sortkey[$key] = $d[$sortitem] ?? null;
			}
			array_multisort($sortkey, $sort_order, $arr);
		}

		// ソートがあった場合にmaxを超えている場合があるので切る
		if ($max != null) {
			$c = 0;
			foreach ($arr as $key => $val) {
				$c++;
				if ($c > $max) {
					unset($arr[$key]);
					$is_last = false;
				}
			}
		}

		// 暗号化を入れる
		$ctl = Controller_class::getInstance();
		if ($ctl != null) {
			// 
			foreach ($arr as &$d) {
				$d["_id_enc"] = $ctl->encrypt($d["id"]);
			}
		}

		return $arr;
	}

	/*
	 * DEPREATED 部分一致したデータを取得
	 */

	function match_list($itemname, $value, $sortitem = null, $sort_order = SORT_ASC, $max = null, &$is_last = null) {
		throw new Exception("Deprecated function : match_list");
	}

	/*
	 * 部分一致したＩＤのリストを取得
	 */

	function match($itemname, $value, $max = null, &$is_last = null, $exact_match = false) {

		//初期値（最後までいかなかった場合に検出できるのでそこで $is_last=falseにしている）
		$is_last = true;

		//itemのサイズを見つける
		$itemsize = 0;
		$beforesize = 0;
		foreach ($this->format as $f) {
			if ($f["type"] == "A" && in_array($f["name"], $itemname)) {
				throw new Exception("Can't search Field type A!");
			}
			if ($f["name"] == $itemname) {
				$itemsize = $f["size"];
				$type = $f["type"];
				break;
			}
			$beforesize += $f["size"];
		}
		$beforesize = $beforesize - $this->format[0]["size"];
		$aftersize = $this->header["recordsize"] - $itemsize - $beforesize - $this->format[0]["size"] - 1;

		if ($itemsize == 0) {
			throw new Exception("wrong itemname : " . $itemname);
		}

		fseek($this->hf, $this->header["headersize"]);
		$ret = array();
		$c = 0;
		while (ftell($this->hf) < $this->eof) {
			// 対象のitemより前を移動
			$flg = fread($this->hf, 1);
			$id = (int) fread($this->hf, $this->format[0]["size"]);
			fseek($this->hf, $beforesize, SEEK_CUR);
			$v = fread($this->hf, $itemsize);
			fseek($this->hf, $aftersize, SEEK_CUR);
			if ($flg == " ") {
				$check = false;

				if ($type == "T" || $type == "F") {
					$v = trim($v);
					if ($exact_match) {
						if ($v === $value) {
							$check = true;
						}
					} else {
						if (($v == $value) || (strpos($v, $value) !== false)) {
							$check = true;
						}
					}
				} else if ($type == "N") {
					$v = (int) $v;
					if ($v == $value) {
						$check = true;
					}
				}

				if ($check) {
					$ret[] = $id;
					$c++;
					if ($max != null && $c >= $max) {
						$is_last = false;
						break;
					}
				}
			}
		}
		return $ret;
	}

	public function getall($sortitem = null, $sort_order = SORT_ASC) {
		$this->seek_end();
		$arr = array();
		while (($d = $this->before()) != null) {
			$arr[] = $d;
		}

		if ($sortitem != null) {
			$sortkey = array();
			foreach ($arr as $key => $d) {
				$sortkey[$key] = $d[$sortitem] ?? null;
			}
			array_multisort($sortkey, $sort_order, $arr);
			return $arr;
		} else {
			return $arr;
		}
	}

	// ID以外のデータを読み込む
	private function readdata(&$arr) {
		foreach ($this->format as $f) {
			//データを読み込む
			if ($f["name"] != "id") {
				if ((int) $f["size"] === 0 && $this->allow_zero_length_read_for_change_format) {
					$arr[$f["name"]] = "";
				} else {
					$arr[$f["name"]] = fread($this->hf, $f["size"]);
				}
				//形式を変換
				if ($f["type"] == "N") {
					$arr[$f["name"]] = (int) $arr[$f["name"]];
				} else if ($f["type"] == "T") {
					$arr[$f["name"]] = trim($arr[$f["name"]], " ");
				} else if ($f["type"] == "F") {
					$arr[$f["name"]] = (float) $arr[$f["name"]];
				} else if ($f["type"] == "A") {
					$arr[$f["name"]] = (($tmp = json_decode($arr[$f["name"]], true)) !== null) ? $tmp : [];
				}
			}
		}
	}

	private function writedata($dataset, $hf = null, $format = null) {

		if ($dataset["id"] == 0) {
			return;
		}

		if ($hf == null) {
			$hf = $this->hf;
		}
		if ($format == null) {
			$format = $this->format;
		}

		fwrite($hf, " "); //フラグ
		foreach ($format as $f) {
			// データを取り出す
			$t = isset($dataset[$f["name"]]) ? $dataset[$f["name"]] : "";

			//　バイト数内にカットする
			if ($f["type"] != "A") {
				if(is_array($t)){
					throw new Exception($f["name"] . " has array value. Please check the file format. The type should be A(=array)");
				}
				$t = mb_strcut((string) $t, 0, $f["size"]);
			}

			if ($f["type"] == "T") {
				//文字
				$d = sprintf("%" . $f["size"] . "s", $t);
			} else if ($f["type"] == "N") {
				//数字
				//全角->半角変換
				$t = mb_convert_kana($t, "rnas");
				$t = str_replace(",", "", $t);

				$d = sprintf("%0" . $f["size"] . "d", $t);
			} else if ($f["type"] == "F") {
				//全角->半角変換
				$t = mb_convert_kana($t, "rnas");
				$t = str_replace(",", "", $t);

				// 文字として保存
				$d = sprintf("%" . $f["size"] . "s", $t);
			} else if ($f["type"] == "A") {
				$t = json_encode($t);
				$length = strlen($t);
				if ($f["size"] < $length) {
					throw new Exception("Field size is low: " . $f["size"] . " in " . $f["name"]);
				} else {
					$d = sprintf("%" . $f["size"] . "s", $t);
				}
			} else {
				throw new Exception("wrong type: " . $f["type"]);
			}
			fwrite($hf, $d);
		}
	}

	/*
	 * IDでデータを検索。二分探索
	 */

	function get($id) {

		// Validation
		// $id が0 or null の場合に不正なデータが返されてしまうので回避する
		if (empty($id)) {
			return null;
		}

		//先頭からヘッダ分移動する
		fseek($this->hf, $this->header["headersize"]);

		//最初の
		$start = 1;
		$end = ($this->eof - $this->header["headersize"]) / $this->header["recordsize"];
		$center = $start + floor(($end - $start) / 2);

		while (true) {

			$p_center = $this->header["headersize"] + $this->header["recordsize"] * ($center - 1);
			fseek($this->hf, $p_center);

			// flgとIDをファイルから読み込む
			$flg = fread($this->hf, 1);
			$id_f = (int) fread($this->hf, $this->format[0]["size"]);

			// 該当するデータが見つかった処理
			if ($id == $id_f) {
				if ($flg == " ") {
					$arr = array();
					$arr["id"] = $id_f;
					$this->readdata($arr);

					// 暗号化を入れる
					if (class_exists("Controller_class", false)) {
						$ctl = Controller_class::getInstance();
						if ($ctl != null && !($this->info_classname === "setting" && $this->info_tablename === "setting")) {
							// 
							$arr["_id_enc"] = $ctl->encrypt($arr["id"]);
						}
					}

					return $arr;
				} else {
					//削除されていた場合
					return null;
				}
			}

			// データがない場合
			if ($start >= $end) {
				return null;
			}

			// startとendを再設定
			if ($id > $id_f) {
				$start = $center + 1;
			} else {
				$end = $center - 1;
			}
			$center = $start + floor(($end - $start) / 2);

			//$this->log("start={$start} end={$end} center={$center}");
		}
	}

	/*
	 * フォーマットが変更された
	 */

	private function changeFormat($newformat) {
		$this->assert_writable("changeFormat");

		// Do not wait for the format lock while still holding the dat lock.
		// When two requests detect the same format change, the first request
		// releases and reopens the dat file after taking the format lock. A
		// second request that kept the dat lock while waiting for that format
		// lock would deadlock with the first request.
		$this->closeDatFile();
		$this->flg_prepared = false;
		$lock = $this->acquire_change_format_lock();
		$tmp_path = "";
		try {
			// Another process may have been waiting on an old file handle while the
			// first process renamed the dat file. Reopen the path after taking the
			// format lock so stale handles cannot run a second conversion.
			$this->openDatFile(false, false);
			if ($newformat == $this->header["format_txt"]) {
				return;
			}

			$newf = $this->parseFormat($newformat);
			$active_count_before = $this->count_active_records_in_current_dat();
			$source_mode = file_exists($this->path_dat) ? (fileperms($this->path_dat) & 0777) : 0770;
			$snapshot = $this->snapshot_dat_file("change_format");
			$this->write_operation_log("change_format", null, null, [
				"snapshot" => $snapshot,
				"before_format_hash" => hash("sha256", (string) ($this->header["format_txt"] ?? "")),
				"after_format_hash" => hash("sha256", $newformat),
				"active_count_before" => $active_count_before,
			]);

			$tmp_path = $this->make_unique_tmp_path();
			if (!$h_tmp = fopen($tmp_path, "xb")) {
				throw new Exception("Can't open tmpfile:" . $tmp_path);
			}

			$converted_count = 0;
			try {
				// ヘッダを書き込む
				flock($h_tmp, LOCK_EX);
				$header_txt = $this->makeHeader($this->header["maxid"], $newformat, $newf);
				fwrite($h_tmp, $header_txt);

				//現データの先頭に移動
				$this->seek(1);

				//現データを読み取りながらTMPに書き込む
				$previous_allow_zero_length_read = $this->allow_zero_length_read_for_change_format;
				$this->allow_zero_length_read_for_change_format = true;
				try {
					while (($d = $this->next()) != null) {
						$this->writedata($d, $h_tmp, $newf);
						$converted_count++;
					}
				} finally {
					$this->allow_zero_length_read_for_change_format = $previous_allow_zero_length_read;
				}

				fflush($h_tmp);
				flock($h_tmp, LOCK_UN);
			} finally {
				fclose($h_tmp);
			}

			if ($converted_count !== $active_count_before) {
				@unlink($tmp_path);
				throw new Exception("changeFormat active record count mismatch: before={$active_count_before} converted={$converted_count} dat={$this->path_dat}");
			}

			// datファイルをバックアップする
			flock($this->hf, LOCK_UN);
			fclose($this->hf);
			$backup_path = $this->make_unique_backup_path();
			if (!rename($this->path_dat, $backup_path)) {
				@unlink($tmp_path);
				throw new Exception("Can't backup dat file:" . $this->path_dat);
			}

			// tmpからdatに変換する
			if (!rename($tmp_path, $this->path_dat)) {
				throw new Exception("Can't rename tmpfile to dat file:" . $tmp_path);
			}
			chmod($this->path_dat, $source_mode);
			$tmp_path = "";

			// datを一度閉じて、再度再度オープンする
			$this->close();
			$this->flg_prepared = false;
			$this->openDatFile();
			$this->rebuild_indexes();
		} finally {
			if ($tmp_path !== "" && is_file($tmp_path)) {
				@unlink($tmp_path);
			}
			$this->release_change_format_lock($lock);
		}
	}

	private function acquire_change_format_lock() {
		$path = $this->datadir . $this->filename . ".change_format.lock";
		$fh = fopen($path, "c");
		if ($fh === false) {
			throw new Exception("Can't open change format lock:" . $path);
		}
		if (!flock($fh, LOCK_EX)) {
			fclose($fh);
			throw new Exception("Can't lock change format lock:" . $path);
		}
		return $fh;
	}

	private function release_change_format_lock($fh): void {
		if (is_resource($fh)) {
			@flock($fh, LOCK_UN);
			@fclose($fh);
		}
	}

	private function count_active_records_in_current_dat(): int {
		$p = ftell($this->hf);
		$count = 0;
		fseek($this->hf, $this->header["headersize"]);
		while (ftell($this->hf) < $this->eof) {
			$flg = fread($this->hf, 1);
			if ($flg === " ") {
				$count++;
			}
			$move = $this->header["recordsize"] - 1;
			if ($move > 0) {
				fseek($this->hf, $move, SEEK_CUR);
			}
		}
		fseek($this->hf, $p);
		return $count;
	}

	private function readFmtFile() {
		//-----------------
		//fmtファイルを読み込み
		//-----------------
		$txt = @file_get_contents($this->path_fmt);
		if (!$txt === false) {
			return self::normalize_format_text($txt);
		} else {
			throw new Exception('No format file : ' . $this->path_fmt);
		}
	}

	/*
	 * format形式を解析する
	 */

	private function parseFormat($txt) {
		$lines = explode(":", $txt);
		$arr = array();
		foreach ($lines as $line) {
			$line = trim($line);
			if (!empty($line)) {
				$data = explode(",", $line);
				if (count($data) === 3 || count($data) === 4) {
					$option = count($data) === 4 ? trim((string) $data[3]) : "";
					if (count($data) === 4 && $option !== "IDX") {
						throw new Exception('Wrong format option : ' . $line);
					}
					$ar = array();
					$ar["name"] = trim((string) $data[0]);
					$ar["size"] = trim((string) $data[1]);
					$ar["type"] = trim((string) $data[2]);
					$ar["indexed"] = $option === "IDX" && $ar["name"] !== "id";
					$arr[] = $ar;
				} else {
					throw new Exception('Wrong format : ' . $line);
				}
			}
		}
		//フォーマットをチェックする
		if (count($arr) == 0) {
			//アイテムがない場合
			throw new Exception('Format Error : no items');
		}

		//使用できない名前をチェック
		foreach ($arr as $ar) {
			foreach ($this->prohibition_item_name as $c) {
				if ($ar["name"] == $c) {
					throw new Exception("Format Error : You can't use " . $c . " in " . $this->filename . ".fmt");
				}
			}
			if (!preg_match("/^[a-z0-9_]+$/", $ar["name"])) {
				throw new Exception("Format Error : This item has prohibited character " . $ar["name"] . " in " . $this->filename . ".fmt");
			}
		}

		if ($arr[0]["name"] != "id") {
			//最初のアイテムがidでない場合
			throw new Exception('Format Error : First item must be "id"' . $txt);
		}
		return $arr;
	}

	private function indexed_fields(): array {
		if ($this->index_disabled) {
			return [];
		}
		return array_values(array_filter($this->format, static function (array $field): bool {
			return !empty($field["indexed"])
				&& ($field["name"] ?? "") !== "id"
				&& in_array((string) ($field["type"] ?? ""), ["T", "N", "F"], true);
		}));
	}

	private function index_path(string $field): string {
		return $this->path_dat . "." . $field . ".idx";
	}

	private function index_manifest_path(string $field): string {
		return $this->index_path($field) . "/manifest.bin";
	}

	private function index_shard_path(string $field, int $shard): string {
		return $this->index_path($field) . "/" . sprintf("%03d.bin", $shard);
	}

	private function index_dirty_path(): string {
		return $this->path_dat . ".idx.dirty";
	}

	private function index_key(array $field, $value, bool $raw = false): string {
		$type = (string) ($field["type"] ?? "T");
		if ($type === "N") {
			$normalized = (string) (int) $value;
		} elseif ($type === "F") {
			$normalized = sprintf("%.17g", (float) $value);
		} else {
			$normalized = trim((string) $value);
		}
		return hash("sha256", $type . "\0" . $normalized, $raw);
	}

	private function index_shard_number(string $raw_key): int {
		return ord($raw_key[0]) >> 1;
	}

	private function index_format_hash(bool $raw = true): string {
		return hash("sha256", (string) $this->header["format_txt"], $raw);
	}

	private function index_identity(array $field): string {
		return hash("sha256", $this->filename . "\0" . (string) $field["name"] . "\0" . (string) $field["type"] . "\0" . $this->index_format_hash(false), true);
	}

	private function index_manifest_payload(array $field, int $count, array $checksums): string {
		if (count($checksums) !== self::INDEX_SHARDS) throw new Exception("Index checksum count mismatch");
		$type = str_pad(substr((string) $field["type"], 0, 1), 8, "\0");
		$payload = self::INDEX_MANIFEST_MAGIC
			. pack("NNJJ", self::INDEX_VERSION, self::INDEX_SHARDS, (int) $this->header["maxid"], $count)
			. $type
			. $this->index_format_hash(true)
			. $this->index_identity($field);
		foreach ($checksums as $checksum) {
			if (!is_string($checksum) || strlen($checksum) !== 32) throw new Exception("Invalid index shard checksum");
			$payload .= $checksum;
		}
		return $payload;
	}

	private function read_index_manifest(array $field): ?array {
		$path = $this->index_manifest_path((string) $field["name"]);
		$payload = is_file($path) ? @file_get_contents($path) : false;
		if (!is_string($payload) || strlen($payload) !== self::INDEX_MANIFEST_SIZE || substr($payload, 0, 8) !== self::INDEX_MANIFEST_MAGIC) {
			return null;
		}
		$meta = unpack("Nversion/Nshards/Jmaxid/Jcount", substr($payload, 8, 24));
		if ((int) $meta["version"] !== self::INDEX_VERSION
			|| (int) $meta["shards"] !== self::INDEX_SHARDS
			|| (int) $meta["maxid"] !== (int) $this->header["maxid"]
			|| rtrim(substr($payload, 32, 8), "\0") !== (string) $field["type"]
			|| !hash_equals($this->index_format_hash(true), substr($payload, 40, 32))
			|| !hash_equals($this->index_identity($field), substr($payload, 72, 32))) {
			return null;
		}
		return [
			"count" => (int) $meta["count"],
			"maxid" => (int) $meta["maxid"],
			"checksums" => str_split(substr($payload, 104), 32),
		];
	}

	private function parse_index_shard_header($handle, array $field, int $shard, ?int $file_size = null): ?array {
		if (!is_resource($handle) || fseek($handle, 0) !== 0) return null;
		$payload = fread($handle, self::INDEX_SHARD_HEADER_SIZE);
		if (!is_string($payload) || strlen($payload) !== self::INDEX_SHARD_HEADER_SIZE || substr($payload, 0, 8) !== self::INDEX_SHARD_MAGIC) {
			return null;
		}
		$meta = unpack("Nversion/Nshard/Nkey_count/Nreserved/Jid_count", substr($payload, 8, 24));
		$key_count = (int) $meta["key_count"];
		$id_count = (int) $meta["id_count"];
		$expected_size = self::INDEX_SHARD_HEADER_SIZE + ($key_count * self::INDEX_SHARD_ENTRY_SIZE) + ($id_count * 8);
		if ((int) $meta["version"] !== self::INDEX_VERSION
			|| (int) $meta["shard"] !== $shard
			|| $key_count < 0
			|| $id_count < $key_count
			|| !hash_equals($this->index_identity($field), substr($payload, 32, 32))
			|| ($file_size !== null && $file_size !== $expected_size)) {
			return null;
		}
		return [
			"key_count" => $key_count,
			"id_count" => $id_count,
			"ids_offset" => self::INDEX_SHARD_HEADER_SIZE + ($key_count * self::INDEX_SHARD_ENTRY_SIZE),
		];
	}

	private function validate_index_field(array $field, array $manifest): bool {
		$total = 0;
		for ($shard = 0; $shard < self::INDEX_SHARDS; $shard++) {
			$path = $this->index_shard_path((string) $field["name"], $shard);
			$size = is_file($path) ? filesize($path) : false;
			$handle = $size === false ? false : @fopen($path, "rb");
			$meta = is_resource($handle) ? $this->parse_index_shard_header($handle, $field, $shard, (int) $size) : null;
			if (is_resource($handle)) fclose($handle);
			if ($meta === null) return false;
			$total += (int) $meta["id_count"];
		}
		return $total === (int) $manifest["count"];
	}

	private function close_index_cache(?string $field = null, ?int $shard = null): void {
		foreach ($this->index_cache as $field_name => &$shards) {
			if ($field !== null && $field_name !== $field) continue;
			foreach ($shards as $shard_number => $state) {
				if ($shard !== null && (int) $shard_number !== $shard) continue;
				if (is_array($state) && isset($state["handle"]) && is_resource($state["handle"])) fclose($state["handle"]);
				unset($shards[$shard_number]);
			}
			if (count($shards) === 0) unset($this->index_cache[$field_name]);
		}
		unset($shards);
	}

	private function remove_index_path(string $path): void {
		if (is_file($path) || is_link($path)) {
			@unlink($path);
			return;
		}
		if (!is_dir($path)) return;
		foreach (array_diff(scandir($path), [".", ".."]) as $name) $this->remove_index_path($path . "/" . $name);
		@rmdir($path);
	}

	private function cleanup_unused_indexes(array $fields): void {
		if ($this->index_disabled) return;
		$keep = [];
		foreach ($fields as $field) $keep[$this->index_path((string) $field["name"])] = true;
		foreach (glob($this->path_dat . ".*.idx") ?: [] as $path) {
			if (!isset($keep[$path])) $this->remove_index_path($path);
		}
	}

	private function prepare_indexes(): void {
		$this->close_index_cache();
		$this->index_cache = [];
		$this->index_ready = [];
		$fields = $this->indexed_fields();
		if (count($fields) === 0 || is_file($this->index_dirty_path())) {
			return;
		}
		$needs_rebuild = false;
		foreach ($fields as $field) {
			$name = (string) $field["name"];
			$manifest = is_dir($this->index_path($name)) ? $this->read_index_manifest($field) : null;
			if ($manifest === null || !$this->validate_index_field($field, $manifest)) {
				$needs_rebuild = true;
				continue;
			}
			$this->index_ready[$name] = $manifest;
		}
		if ($needs_rebuild && !$this->read_only) {
			try {
				$this->mark_indexes_dirty();
				$this->rebuild_indexes();
			} catch (Throwable $e) {
				$this->close_index_cache();
				$this->index_cache = [];
				$this->index_ready = [];
			}
		}
	}

	public function rebuild_indexes(): void {
		$this->assert_writable("rebuild indexes");
		$fields = $this->indexed_fields();
		$this->close_index_cache();
		if (count($fields) === 0) {
			$this->cleanup_unused_indexes($fields);
			$this->clear_indexes_dirty();
			return;
		}
		$current = ftell($this->hf);
		$this->mark_indexes_dirty();
		try {
			foreach ($fields as $field) {
				$values_by_shard = array_fill(0, self::INDEX_SHARDS, []);
				$count = 0;
				fseek($this->hf, $this->header["headersize"]);
				while (($row = $this->next()) !== null) {
					$raw_key = $this->index_key($field, $row[$field["name"]] ?? null, true);
					$shard = $this->index_shard_number($raw_key);
					$hex_key = bin2hex($raw_key);
					$values_by_shard[$shard][$hex_key] = $values_by_shard[$shard][$hex_key] ?? [];
					$values_by_shard[$shard][$hex_key][] = (int) $row["id"];
					$count++;
				}
				$checksums = $this->build_index_directory($field, $values_by_shard, $count);
				$this->index_ready[$field["name"]] = ["count" => $count, "maxid" => (int) $this->header["maxid"], "checksums" => $checksums];
				unset($values_by_shard);
			}
			$this->cleanup_unused_indexes($fields);
			$this->clear_indexes_dirty();
		} finally {
			if (is_resource($this->hf)) fseek($this->hf, $current);
		}
	}

	private function mark_indexes_dirty(): void {
		if (count($this->indexed_fields()) === 0) {
			return;
		}
		if (file_put_contents($this->index_dirty_path(), "1\n", LOCK_EX) === false) {
			throw new Exception("Failed to mark indexes dirty: " . $this->index_dirty_path());
		}
	}

	private function clear_indexes_dirty(): void {
		$path = $this->index_dirty_path();
		if (is_file($path)) {
			@unlink($path);
		}
	}

	private function build_index_directory(array $field, array $values_by_shard, int $count): array {
		$path = $this->index_path((string) $field["name"]);
		foreach (array_merge(glob($path . ".tmp.*") ?: [], glob($path . ".old.*") ?: []) as $stale) $this->remove_index_path($stale);
		$tmp = $path . ".tmp." . getmypid() . "." . bin2hex(random_bytes(4));
		$old = $path . ".old." . getmypid() . "." . bin2hex(random_bytes(4));
		if (!mkdir($tmp, 0770, true)) throw new Exception("Failed to create index directory: " . $tmp);
		try {
			$checksums = [];
			foreach ($values_by_shard as $shard => $values) {
				$shard_path = $tmp . "/" . sprintf("%03d.bin", $shard);
				$this->write_index_shard_file($shard_path, $field, (int) $shard, $values);
				$checksums[] = hash_file("sha256", $shard_path, true);
			}
			if (file_put_contents($tmp . "/manifest.bin", $this->index_manifest_payload($field, $count, $checksums), LOCK_EX) === false) {
				throw new Exception("Failed to write index manifest: " . $tmp);
			}
			if (file_exists($path) && !rename($path, $old)) throw new Exception("Failed to preserve old index: " . $path);
			if (!rename($tmp, $path)) {
				if (file_exists($old)) @rename($old, $path);
				throw new Exception("Failed to activate index: " . $path);
			}
			$this->remove_index_path($old);
		} finally {
			$this->remove_index_path($tmp);
		}
		return $checksums;
	}

	private function write_index_shard_file(string $path, array $field, int $shard, array $values): void {
		ksort($values, SORT_STRING);
		$directory = "";
		$id_data = "";
		$id_offset = 0;
		foreach ($values as $hex_key => $ids) {
			if (preg_match('/^[a-f0-9]{64}$/', (string) $hex_key) !== 1) throw new Exception("Invalid index key");
			$raw_key = hex2bin($hex_key);
			if ($this->index_shard_number($raw_key) !== $shard) throw new Exception("Index key is in wrong shard");
			$ids = array_values(array_unique(array_map("intval", $ids)));
			sort($ids, SORT_NUMERIC);
			foreach ($ids as $id) if ($id <= 0 || $id > (int) $this->header["maxid"]) throw new Exception("Invalid index ID: " . $id);
			$directory .= $raw_key . pack("JN", $id_offset, count($ids)) . pack("N", 0);
			if (count($ids) > 0) $id_data .= pack("J*", ...$ids);
			$id_offset += count($ids);
		}
		$payload = self::INDEX_SHARD_MAGIC
			. pack("NNNNJ", self::INDEX_VERSION, $shard, count($values), 0, $id_offset)
			. $this->index_identity($field)
			. $directory
			. $id_data;
		if (file_put_contents($path, $payload, LOCK_EX) === false) throw new Exception("Failed to write index shard: " . $path);
	}

	private function read_index_shard_values(array $field, int $shard): ?array {
		$name = (string) $field["name"];
		$path = $this->index_shard_path((string) $field["name"], $shard);
		$expected_checksum = $this->index_ready[$name]["checksums"][$shard] ?? null;
		$actual_checksum = is_file($path) ? hash_file("sha256", $path, true) : false;
		if (!is_string($expected_checksum) || !is_string($actual_checksum) || !hash_equals($expected_checksum, $actual_checksum)) return null;
		$size = is_file($path) ? filesize($path) : false;
		$handle = $size === false ? false : @fopen($path, "rb");
		if (!is_resource($handle)) return null;
		try {
			$meta = $this->parse_index_shard_header($handle, $field, $shard, (int) $size);
			if ($meta === null) return null;
			$entries = [];
			$seen_ids = [];
			$total = 0;
			for ($i = 0; $i < $meta["key_count"]; $i++) {
				fseek($handle, self::INDEX_SHARD_HEADER_SIZE + ($i * self::INDEX_SHARD_ENTRY_SIZE));
				$entry = fread($handle, self::INDEX_SHARD_ENTRY_SIZE);
				if (strlen($entry) !== self::INDEX_SHARD_ENTRY_SIZE) return null;
				$location = unpack("Joffset/Ncount", substr($entry, 32, 12));
				if ((int) $location["offset"] + (int) $location["count"] > $meta["id_count"]) return null;
				$raw_key = substr($entry, 0, 32);
				if ($this->index_shard_number($raw_key) !== $shard) return null;
				$entries[] = ["key" => bin2hex($raw_key), "offset" => (int) $location["offset"], "count" => (int) $location["count"]];
			}
			$values = [];
			foreach ($entries as $entry) {
				fseek($handle, $meta["ids_offset"] + ($entry["offset"] * 8));
				$bytes = fread($handle, $entry["count"] * 8);
				if (strlen($bytes) !== $entry["count"] * 8) return null;
				$ids = $entry["count"] === 0 ? [] : array_values(unpack("J*", $bytes));
				foreach ($ids as $id) {
					$id = (int) $id;
					if ($id <= 0 || $id > (int) $this->header["maxid"] || isset($seen_ids[$id])) return null;
					$seen_ids[$id] = true;
					$total++;
				}
				$values[$entry["key"]] = array_map("intval", $ids);
			}
			return $total === $meta["id_count"] ? $values : null;
		} finally {
			fclose($handle);
		}
	}

	private function persist_index_shard(array $field, int $shard, array $values): string {
		$name = (string) $field["name"];
		$this->close_index_cache($name, $shard);
		$path = $this->index_shard_path($name, $shard);
		$tmp = $path . ".tmp." . getmypid() . "." . bin2hex(random_bytes(4));
		try {
			$this->write_index_shard_file($tmp, $field, $shard, $values);
			if (!rename($tmp, $path)) throw new Exception("Failed to activate index shard: " . $path);
			return hash_file("sha256", $path, true);
		} finally {
			if (is_file($tmp)) @unlink($tmp);
		}
	}

	private function persist_index_manifest(array $field, int $count, array $checksums): void {
		$path = $this->index_manifest_path((string) $field["name"]);
		$tmp = $path . ".tmp." . getmypid() . "." . bin2hex(random_bytes(4));
		try {
			if (file_put_contents($tmp, $this->index_manifest_payload($field, $count, $checksums), LOCK_EX) === false || !rename($tmp, $path)) {
				throw new Exception("Failed to write index manifest: " . $path);
			}
		} finally {
			if (is_file($tmp)) @unlink($tmp);
		}
	}

	private function apply_index_changes(?array $before, ?array $after): void {
		$fields = $this->indexed_fields();
		if (count($fields) === 0) return;
		foreach ($fields as $field) {
			if (empty($this->index_ready[$field["name"]])) {
				$this->rebuild_indexes();
				return;
			}
		}
		$touched = [];
		foreach ($fields as $field) {
			$name = (string) $field["name"];
			if ($before !== null && $after !== null) {
				$before_key = $this->index_key($field, $before[$name] ?? null, true);
				$after_key = $this->index_key($field, $after[$name] ?? null, true);
				if (hash_equals($before_key, $after_key)) continue;
			}
			foreach ([["row" => $before, "remove" => true], ["row" => $after, "remove" => false]] as $change) {
				if ($change["row"] === null) continue;
				$raw_key = $this->index_key($field, $change["row"][$name] ?? null, true);
				$shard = $this->index_shard_number($raw_key);
				$key = $name . ":" . $shard;
				if (!isset($touched[$key])) {
					$values = $this->read_index_shard_values($field, $shard);
					if ($values === null) {
						$this->rebuild_indexes();
						return;
					}
					$touched[$key] = ["field" => $field, "shard" => $shard, "values" => $values];
				}
				$hex_key = bin2hex($raw_key);
				$id = (int) $change["row"]["id"];
				$ids = $touched[$key]["values"][$hex_key] ?? [];
				if ($change["remove"]) {
					$ids = array_values(array_filter($ids, static fn($candidate) => (int) $candidate !== $id));
					if (count($ids) === 0) unset($touched[$key]["values"][$hex_key]);
					else $touched[$key]["values"][$hex_key] = $ids;
				} elseif (!in_array($id, $ids, true)) {
					$ids[] = $id;
					$touched[$key]["values"][$hex_key] = $ids;
				}
			}
		}
		$delta = ($after === null ? 0 : 1) - ($before === null ? 0 : 1);
		if (count($touched) === 0 && $delta === 0) {
			$this->clear_indexes_dirty();
			return;
		}
		foreach ($touched as $change) {
			$name = (string) $change["field"]["name"];
			$this->index_ready[$name]["checksums"][$change["shard"]] = $this->persist_index_shard($change["field"], $change["shard"], $change["values"]);
		}
		foreach ($fields as $field) {
			$name = (string) $field["name"];
			$count = (int) $this->index_ready[$name]["count"] + $delta;
			$checksums = $this->index_ready[$name]["checksums"];
			$this->persist_index_manifest($field, $count, $checksums);
			$this->index_ready[$name] = ["count" => $count, "maxid" => (int) $this->header["maxid"], "checksums" => $checksums];
		}
		$this->clear_indexes_dirty();
	}

	private function apply_index_insert(array $row): void {
		$this->apply_index_changes(null, $row);
	}

	private function apply_index_delete(array $row): void {
		$this->apply_index_changes($row, null);
	}

	private function apply_index_update(array $before, array $after): void {
		$this->apply_index_changes($before, $after);
	}

	private function open_index_shard(array $field, int $shard): ?array {
		$name = (string) $field["name"];
		if (isset($this->index_cache[$name][$shard])) return $this->index_cache[$name][$shard];
		$path = $this->index_shard_path($name, $shard);
		$expected_checksum = $this->index_ready[$name]["checksums"][$shard] ?? null;
		$actual_checksum = is_file($path) ? hash_file("sha256", $path, true) : false;
		if (!is_string($expected_checksum) || !is_string($actual_checksum) || !hash_equals($expected_checksum, $actual_checksum)) return null;
		$size = is_file($path) ? filesize($path) : false;
		$handle = $size === false ? false : @fopen($path, "rb");
		$meta = is_resource($handle) ? $this->parse_index_shard_header($handle, $field, $shard, (int) $size) : null;
		if (!is_resource($handle) || $meta === null) {
			if (is_resource($handle)) fclose($handle);
			return null;
		}
		$state = $meta + ["handle" => $handle];
		$this->index_cache[$name][$shard] = $state;
		return $state;
	}

	private function lookup_index_ids(array $field, $value): ?array {
		$raw_key = $this->index_key($field, $value, true);
		$shard = $this->index_shard_number($raw_key);
		$state = $this->open_index_shard($field, $shard);
		if ($state === null) return null;
		$handle = $state["handle"];
		$low = 0;
		$high = (int) $state["key_count"] - 1;
		while ($low <= $high) {
			$middle = $low + intdiv($high - $low, 2);
			if (fseek($handle, self::INDEX_SHARD_HEADER_SIZE + ($middle * self::INDEX_SHARD_ENTRY_SIZE)) !== 0) return null;
			$entry = fread($handle, self::INDEX_SHARD_ENTRY_SIZE);
			if (!is_string($entry) || strlen($entry) !== self::INDEX_SHARD_ENTRY_SIZE) return null;
			$comparison = strcmp(substr($entry, 0, 32), $raw_key);
			if ($comparison < 0) {
				$low = $middle + 1;
				continue;
			}
			if ($comparison > 0) {
				$high = $middle - 1;
				continue;
			}
			$location = unpack("Joffset/Ncount", substr($entry, 32, 12));
			$offset = (int) $location["offset"];
			$count = (int) $location["count"];
			if ($offset < 0 || $count < 0 || $offset + $count > (int) $state["id_count"]
				|| fseek($handle, (int) $state["ids_offset"] + ($offset * 8)) !== 0) return null;
			$bytes = fread($handle, $count * 8);
			if (!is_string($bytes) || strlen($bytes) !== $count * 8) return null;
			$ids = $count === 0 ? [] : array_values(unpack("J*", $bytes));
			foreach ($ids as &$id) {
				$id = (int) $id;
				if ($id <= 0 || $id > (int) $this->header["maxid"]) return null;
			}
			unset($id);
			return $ids;
		}
		return [];
	}

	private function text_filter_candidate_ids(array $itemname, array $value, string $and_or, $sortitem, $max): ?array {
		if ($this->text_search_disabled || $and_or !== "AND") {
			return null;
		}

		$field_map = [];
		$offset = 1;
		foreach ($this->format as $field) {
			$name = (string) ($field["name"] ?? "");
			$size = (int) ($field["size"] ?? 0);
			$field_map[$name] = $field + ["data_offset" => $offset];
			$offset += $size;
		}

		$selected = null;
		$selected_score = -1;
		foreach ($itemname as $i => $names) {
			$names = is_array($names) ? array_values($names) : [$names];
			$ranges = [];
			foreach ($names as $name) {
				if (!is_string($name) || !isset($field_map[$name]) || ($field_map[$name]["type"] ?? "") !== "T") {
					$ranges = [];
					break;
				}
				$start = (int) $field_map[$name]["data_offset"];
				$ranges[] = ["start" => $start, "end" => $start + (int) $field_map[$name]["size"]];
			}
			if (count($ranges) === 0 || !array_key_exists($i, $value) || (!is_scalar($value[$i]) && $value[$i] !== null)) {
				continue;
			}

			$search = strtoupper((string) $value[$i]);
			if ($search === "") {
				continue;
			}
			$patterns = [$search => $search];
			$tokens = preg_split("/[\s,]+/", $search);
			if (is_array($tokens)) {
				foreach ($tokens as $token) {
					if ($token !== "") $patterns[$token] = $token;
				}
			}
			$patterns = array_values(array_filter($patterns, static function (string $pattern) use ($ranges): bool {
				$length = strlen($pattern);
				foreach ($ranges as $range) {
					if ($length <= $range["end"] - $range["start"]) return true;
				}
				return false;
			}));
			if (count($patterns) === 0) {
				return [];
			}

			$score = min(array_map("strlen", $patterns));
			if ($score > $selected_score) {
				$selected = [
					"ranges" => $ranges,
					"patterns" => $patterns,
					"value" => $value[$i],
					"grouped" => is_array($itemname[$i]),
				];
				$selected_score = $score;
			}
		}

		if ($selected === null) return null;
		$stop_after = $sortitem === null && $max !== null && count($itemname) === 1 ? max(0, (int) $max) : null;
		return $this->scan_text_candidate_ids(
			$selected["ranges"],
			$selected["patterns"],
			$selected["value"],
			$selected["grouped"],
			$stop_after
		);
	}

	private function text_scan_match_position(int $position, int $pattern_length, array $ranges, int $recordsize): array {
		$record_start = intdiv($position, $recordsize) * $recordsize;
		$within = $position - $record_start;
		foreach ($ranges as $range) {
			$start = (int) $range["start"];
			$end = (int) $range["end"];
			if ($within < $start) {
				return [false, $record_start + $start];
			}
			if ($within < $end) {
				if ($within + $pattern_length <= $end) {
					return [true, $record_start + $recordsize + (int) $ranges[0]["start"]];
				}
				continue;
			}
		}
		return [false, $record_start + $recordsize + (int) $ranges[0]["start"]];
	}

	private function text_scan_record_matches(string $block, int $record_start, array $ranges, $search_value, bool $grouped): bool {
		foreach ($ranges as $range) {
			$stored = trim(substr($block, $record_start + (int) $range["start"], (int) $range["end"] - (int) $range["start"]), " ");
			if ($grouped && empty($stored)) continue;
			if ($this->is_filter_text_partial_match($stored, $search_value)) return true;
		}
		return false;
	}

	private function scan_text_candidate_ids(array $ranges, array $patterns, $search_value, bool $grouped, ?int $stop_after): ?array {
		$recordsize = (int) ($this->header["recordsize"] ?? 0);
		$headersize = (int) ($this->header["headersize"] ?? 0);
		$id_size = (int) ($this->format[0]["size"] ?? 0);
		$data_bytes = (int) $this->eof - $headersize;
		if ($recordsize <= 1 || $id_size <= 0 || $headersize < 0 || $data_bytes < 0 || $data_bytes % $recordsize !== 0) {
			return null;
		}
		$total_records = intdiv($data_bytes, $recordsize);
		if ($total_records === 0) return [];
		$records_per_block = max(1, intdiv(self::TEXT_SCAN_BLOCK_BYTES, $recordsize));
		$candidates = [];
		$current = ftell($this->hf);

		try {
			$record_index = $stop_after === null ? 0 : $total_records;
			while (($stop_after === null && $record_index < $total_records) || ($stop_after !== null && $record_index > 0)) {
				if ($stop_after === null) {
					$block_start_record = $record_index;
					$block_records = min($records_per_block, $total_records - $record_index);
					$record_index += $block_records;
				} else {
					$block_start_record = max(0, $record_index - $records_per_block);
					$block_records = $record_index - $block_start_record;
					$record_index = $block_start_record;
				}
				$block_bytes = $block_records * $recordsize;
				$block_offset = $headersize + ($block_start_record * $recordsize);
				if (fseek($this->hf, $block_offset) !== 0) return null;
				$block = "";
				while (strlen($block) < $block_bytes) {
					$part = fread($this->hf, $block_bytes - strlen($block));
					if (!is_string($part) || $part === "") return null;
					$block .= $part;
				}
				$search_block = strtoupper($block);

				foreach ($patterns as $pattern) {
					$pattern_length = strlen($pattern);
					$search_position = (int) $ranges[0]["start"];
					while (($position = strpos($search_block, $pattern, $search_position)) !== false) {
						[$valid, $next_position] = $this->text_scan_match_position($position, $pattern_length, $ranges, $recordsize);
						$advance_position = $next_position;
						if ($valid) {
							$record_start = intdiv($position, $recordsize) * $recordsize;
							if (($block[$record_start] ?? "") === " ") {
								$id = (int) substr($block, $record_start + 1, $id_size);
								if ($id > 0 && $id <= (int) $this->header["maxid"]) {
									if (isset($candidates[$id]) || $this->text_scan_record_matches($block, $record_start, $ranges, $search_value, $grouped)) {
										$candidates[$id] = true;
										if (count($candidates) > self::TEXT_SCAN_MAX_CANDIDATES) return null;
									} else {
										$advance_position = $position + 1;
									}
								}
							}
						}
						$search_position = max($position + 1, $advance_position);
						if ($search_position >= $block_bytes) break;
					}
				}
				unset($search_block, $block);
				if ($stop_after !== null && count($candidates) > $stop_after) break;
			}
		} finally {
			if (is_int($current)) fseek($this->hf, $current);
		}

		$ids = array_map("intval", array_keys($candidates));
		rsort($ids, SORT_NUMERIC);
		return $ids;
	}

	private function indexed_candidate_ids(array $itemname, array $value, array $patterns, string $and_or, bool $numeric_only = false): ?array {
		if ($this->index_disabled || $and_or !== "AND" || is_file($this->index_dirty_path())) {
			return null;
		}
		$field_map = [];
		foreach ($this->indexed_fields() as $field) {
			$field_map[$field["name"]] = $field;
		}
		$candidates = null;
		foreach ($itemname as $i => $name) {
			if (is_array($name) || ($patterns[$i] ?? "=") !== "=" || !isset($field_map[$name]) || empty($this->index_ready[$name])) {
				continue;
			}
			if ($numeric_only && ($field_map[$name]["type"] ?? null) !== "N") {
				continue;
			}
			$ids = $this->lookup_index_ids($field_map[$name], $value[$i] ?? null);
			if ($ids === null) {
				$this->index_ready[$name] = false;
				$this->close_index_cache($name);
				if (!$this->read_only) {
					try { $this->mark_indexes_dirty(); } catch (Throwable $e) {}
				}
				return null;
			}
			$candidates = $candidates === null ? $ids : array_values(array_intersect($candidates, $ids));
		}
		if ($candidates === null) return null;
		rsort($candidates, SORT_NUMERIC);
		return $candidates;
	}

	/*
	 * ヘッダ文字を作成
	 */

	private function makeHeader($maxid, $format_txt, $format) {

		$ft_size = strlen($format_txt);

		// データ形式のVersion
		$ver = 1;

		// １レコードのサイズ
		$recordsize = 1; //先頭のフラグ分
		foreach ($format as $f) {
			if(!is_numeric($f["size"])){
				throw new Exception("Size should be number. Check fmt file : " . print_r($f,true));
			}
			$recordsize += $f["size"];
		}

		// 固定長データを作成
		$header = "";
		$header .= sprintf("%04d", $ver);
		$header .= sprintf("%016d", $maxid);
		$header .= sprintf("%08d", $recordsize);
		$header .= sprintf("%016d", 4 + 16 + 8 + 16 + $ft_size);
		$header .= $format_txt;

		return $header;
	}

	/*
	 * datファイルのヘッダを読み込む
	 */

	private function getHeader() {
		rewind($this->hf);
		$arr = array();
		$arr["ver"] = (int) fread($this->hf, 4);
		$arr["maxid"] = (int) fread($this->hf, 16);
		$arr["recordsize"] = (int) fread($this->hf, 8);
		$arr["headersize"] = (int) fread($this->hf, 16);
		$ft_size = $arr["headersize"] - 4 - 16 - 8 - 16;
		$arr["format_txt"] = trim(fread($this->hf, $ft_size));
		return $arr;
	}

	private function write_operation_log(string $operation, ?array $before, ?array $after, array $meta = []): string {
		if ($this->read_only || (string) getenv("FBP_FFM_LOG_DISABLE") === "1") {
			return "";
		}

		$txid = $this->generate_operation_txid();
		$entry = [
			"version" => 1,
			"txid" => $txid,
			"timestamp" => date("c"),
			"source" => $this->get_operation_log_source(),
			"pid" => getmypid(),
			"class" => $this->get_operation_log_classname(),
			"table" => $this->info_tablename ?: $this->filename,
			"operation" => $operation,
			"id" => $this->get_operation_log_id($before, $after),
			"before" => $this->normalize_operation_log_row($before),
			"after" => $this->normalize_operation_log_row($after),
			"dat_path" => $this->get_classes_relative_path($this->path_dat),
			"fmt_path" => $this->path_fmt === null ? "" : $this->get_classes_relative_path($this->path_fmt),
			"header" => [
				"maxid" => (int) ($this->header["maxid"] ?? 0),
				"recordsize" => (int) ($this->header["recordsize"] ?? 0),
				"headersize" => (int) ($this->header["headersize"] ?? 0),
				"format_hash" => hash("sha256", (string) ($this->header["format_txt"] ?? "")),
			],
			"request" => $this->get_operation_log_request_context(),
			"actor" => $this->get_operation_log_actor(),
			"meta" => $meta,
			"status" => "committed",
		];

		$json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false) {
			throw new Exception("Failed to encode fixed file operation log");
		}

		$log_dir = $this->get_operation_log_dir();
		$path = $log_dir . "/" . date("Ymd") . ".jsonl";
		$fh = fopen($path, "ab");
		if ($fh === false) {
			throw new Exception("Can't open fixed file operation log:" . $path);
		}
		try {
			if (!flock($fh, LOCK_EX)) {
				throw new Exception("Can't lock fixed file operation log:" . $path);
			}
			if (fwrite($fh, $json . "\n") === false) {
				throw new Exception("Can't write fixed file operation log:" . $path);
			}
			fflush($fh);
			flock($fh, LOCK_UN);
		} finally {
			fclose($fh);
		}
		return $txid;
	}

	private function snapshot_dat_file(string $operation): array {
		if (!is_file($this->path_dat)) {
			return [
				"created" => false,
				"reason" => "dat_not_found",
			];
		}
		$dir = $this->get_operation_log_dir() . "/snapshots/" . date("Ymd");
		if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
			throw new Exception("Can't make fixed file snapshot directory:" . $dir);
		}

		$class = preg_replace('/[^a-zA-Z0-9_\\-]/', "_", $this->get_operation_log_classname());
		$table = preg_replace('/[^a-zA-Z0-9_\\-]/', "_", $this->info_tablename ?: $this->filename);
		$base = date("His") . "_" . $operation . "_" . $class . "_" . $table;
		$path = $dir . "/" . $base . ".dat";
		$seq = 2;
		while (is_file($path)) {
			$path = $dir . "/" . $base . "_" . $seq . ".dat";
			$seq++;
		}
		if (!copy($this->path_dat, $path)) {
			throw new Exception("Can't create fixed file snapshot:" . $this->path_dat);
		}
		return [
			"created" => true,
			"path" => $this->get_classes_relative_path($path),
			"size" => filesize($path),
			"sha256" => hash_file("sha256", $path),
		];
	}

	private function make_unique_backup_path(): string {
		$base = $this->datadir . $this->filename . "-" . date("Ymd_His");
		$path = $base . ".bak";
		$seq = 2;
		while (is_file($path)) {
			$path = $base . "_" . $seq . ".bak";
			$seq++;
		}
		return $path;
	}

	private function make_unique_tmp_path(): string {
		$base = $this->datadir . $this->filename . ".change_format." . getmypid() . "." . bin2hex(random_bytes(4));
		$path = $base . ".tmp";
		$seq = 2;
		while (is_file($path)) {
			$path = $base . "_" . $seq . ".tmp";
			$seq++;
		}
		return $path;
	}

	private function get_operation_log_dir(): string {
		if ($this->operation_log_dir !== null) {
			return $this->operation_log_dir;
		}
		$classes_dir = $this->get_classes_dir_from_path($this->datadir);
		$log_dir = $classes_dir . "/log/ffm";
		if (!is_dir($log_dir) && !mkdir($log_dir, 0777, true) && !is_dir($log_dir)) {
			throw new Exception("Can't make fixed file operation log directory:" . $log_dir);
		}
		$this->operation_log_dir = $log_dir;
		return $this->operation_log_dir;
	}

	private function get_classes_dir_from_path(string $path): string {
		$normalized = rtrim(str_replace("\\", "/", $path), "/");
		$marker = "/classes/data";
		$pos = strpos($normalized, $marker);
		if ($pos !== false) {
			return substr($normalized, 0, $pos + strlen("/classes"));
		}
		return rtrim(dirname($normalized), "/");
	}

	private function get_classes_relative_path(string $path): string {
		$classes_dir = $this->get_classes_dir_from_path($this->datadir);
		$normalized_path = str_replace("\\", "/", $path);
		$normalized_classes = rtrim(str_replace("\\", "/", $classes_dir), "/") . "/";
		if (strpos($normalized_path, $normalized_classes) === 0) {
			return substr($normalized_path, strlen($normalized_classes));
		}
		return basename($path);
	}

	private function generate_operation_txid(): string {
		try {
			$rand = bin2hex(random_bytes(8));
		} catch (Throwable $e) {
			$rand = substr(str_replace(".", "", uniqid("", true)), -16);
		}
		return "FFM-" . date("Ymd-His") . "-" . $rand;
	}

	private function get_operation_log_source(): string {
		if (PHP_SAPI === "cli") {
			return "cli";
		}
		$class = (string) ($_GET["class"] ?? $_POST["class"] ?? "");
		if (substr($class, -4) === "_api") {
			return "api";
		}
		return "web";
	}

	private function get_operation_log_classname(): string {
		if (!empty($this->info_classname)) {
			return (string) $this->info_classname;
		}
		$dir = basename(rtrim($this->datadir, "/"));
		return $dir === "" ? "" : $dir;
	}

	private function get_operation_log_id(?array $before, ?array $after): ?int {
		$id = null;
		if (is_array($after) && isset($after["id"])) {
			$id = $after["id"];
		} else if (is_array($before) && isset($before["id"])) {
			$id = $before["id"];
		}
		if ($id === null || $id === "") {
			return null;
		}
		return (int) $id;
	}

	private function normalize_operation_log_row(?array $row): ?array {
		if ($row === null) {
			return null;
		}
		unset($row["_id_enc"]);
		return $row;
	}

	private function get_operation_log_request_context(): array {
		return [
			"class" => (string) ($_GET["class"] ?? $_POST["class"] ?? ""),
			"function" => (string) ($_GET["function"] ?? $_POST["function"] ?? ""),
			"method" => (string) ($_SERVER["REQUEST_METHOD"] ?? (PHP_SAPI === "cli" ? "CLI" : "")),
		];
	}

	private function get_operation_log_actor(): array {
		$actor = [];
		if ($this->ctl !== null) {
			if (method_exists($this->ctl, "get_login_user_id")) {
				$actor["login_user_id"] = $this->ctl->get_login_user_id();
			}
			if (method_exists($this->ctl, "get_login_id")) {
				$actor["login_id"] = $this->ctl->get_login_id();
			}
			if (method_exists($this->ctl, "get_login_type")) {
				$actor["login_type"] = $this->ctl->get_login_type();
			}
		}
		return $actor;
	}

	public function get_prohibition_items() {
		return $this->prohibition_item_name;
	}

	public function check_hf() {
		if (is_resource($this->hf) && get_resource_type($this->hf) === "stream") {
			return;
		} else {
			throw new Exception("HF is invalid");
		}
	}

	public function set_info($tablename, $classname) {
		$this->info_tablename = $tablename;
		$this->info_classname = $classname;
	}

	public function get_identifier() {
		return $this->info_classname . "/" . $this->info_tablename;
	}
	
	// Filterで、0の値を有効にするか (Default:false 0は検索から排除)
	// 本当は全てtrueにしておきたいが、過去のプロジェクトで[0 => ""] で全検索させている部分があるので、互換性を保つために必要
	// 例：private $status = array(0 => "", 1 => "Estimate",3 => "Invoice");
	public function set_flg_filter_zero($flg){
		$this->flg_filter_zero = $flg;
	}
	
	public function iterate_filter($func) {
		$this->seek_end();

		$arr = [];
		$c_all = 0;   // scanned
		$c_true = 0;  // selected
		$stop = false;

		while (($d = $this->before()) != null) {
			
			// 暗号化
			if($this->ctl != null){
				$d["_id_enc"] = $this->ctl->encrypt($d["id"]);
			}

			$result = $func($d, $c_true, $c_all,$stop);

			// KEEP
			if ($result === true) {
				$arr[] = $d;
				$c_true++;
				$c_all++;
				continue;
			}
			
			// STOP 判定を最優先
			if ($stop === true) {
				break;
			}

			// SKIP（不正値も含めて安全側に倒す）
			$c_all++;
		}


		return $arr;
	}
}
