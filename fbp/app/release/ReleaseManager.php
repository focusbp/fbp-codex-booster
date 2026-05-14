<?php

class ReleaseManager {

	// Copy list of the databases
	private $db_copy_list = [
	    "lang",
	    "email_format",
	    "db",
	    "constant_array",
	    "webhook_rule",
	    "embed_app",
	    "public_assets",
	    "db_additionals",
	    "dashboard",
	    "cron",
	    "api_studio"
	];
	private $appdir;
	private $datadir;
	private $zipfile;
	private $extractdir;
	private $public_assets_dir;

		function __construct(?string $projectRoot = null, ?string $zipFile = null) {
		if ($projectRoot === null || $projectRoot === "") {
			$projectRoot = dirname(__FILE__) . "/../../../";
		}
		$projectRoot = rtrim($projectRoot, "/");
		$resolvedProjectRoot = realpath($projectRoot);
		if ($resolvedProjectRoot !== false && $resolvedProjectRoot !== "") {
			$projectRoot = $resolvedProjectRoot;
		}

		$classesRoot = $projectRoot . "/classes";
		$resolvedClassesRoot = realpath($classesRoot);
		if ($resolvedClassesRoot !== false && $resolvedClassesRoot !== "") {
			$classesRoot = $resolvedClassesRoot;
		}
		$this->appdir = $classesRoot . "/app";
		$this->datadir = $classesRoot . "/data";
		$this->extractdir = $classesRoot;
		$this->public_assets_dir = $classesRoot . "/data/public_pages/assets";

		$log_dir = $classesRoot . "/log";
		$this->zipfile = $zipFile !== null && $zipFile !== "" ? $zipFile : $log_dir . "/release.zip";
		if (!is_dir($log_dir)) {
			mkdir($log_dir);
		}
	}

	function create_release_zip(Controller $ctl): string {
		$setting = $ctl->get_setting();
		$timezone = !empty($setting["timezone"]) ? (string) $setting["timezone"] : date_default_timezone_get();
		return $this->create_release_zip_from_info([
		    "project_release_code" => $setting["project_release_code"],
		    "datetime" => date("Y/m/d H:i"),
		    "timezone" => $timezone,
		    "memo" => $ctl->POST("memo"),
		    "type" => "release"
		]);
	}

	function create_release_zip_from_info(array $info): string {
		$zip = new ZipArchive();

		if ($zip->open($this->zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
			throw new Exception("Can't open zipfile:" . $this->zipfile);
		}

		$zip->addFromString("info.json", json_encode($info));

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->appdir),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $file) {
			if ($file->isDir()) {
				continue;
			}
			$filePath = $file->getRealPath();
			if ($filePath === false) {
				continue;
			}
			$relativePath = substr($filePath, strlen($this->extractdir) + 1);
			$zip->addFile($filePath, $relativePath);
		}

		foreach ($this->db_copy_list as $f) {
			try {
				$files = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator("$this->datadir/$f"),
					RecursiveIteratorIterator::LEAVES_ONLY
				);
				foreach ($files as $file) {
					$filePath = $file->getRealPath();
					if ($filePath !== false && $this->endsWith($filePath, ".dat")) {
						$relativePath = substr($filePath, strlen($this->extractdir) + 1);
						$zip->addFile($filePath, $relativePath);
					}
				}
			} catch (Exception $e) {
				continue;
			}
		}

		$this->addCommonFormatFilesToZip($zip);
		$this->addDirectoryFilesToZip($zip, $this->public_assets_dir);
		$zip->close();

		return $this->zipfile;
	}

	function validate_release_zip(Controller $ctl, string $zipFile): array {
		$setting = $ctl->get_setting();
		$zip = new ZipArchive();
		if ($zip->open($zipFile) !== TRUE) {
			throw new Exception("Cannot open uploaded release file.");
		}

		try {
			if ($zip->locateName('info.json') === false) {
				throw new Exception("Uploaded release file is missing info.json.");
			}

			$json = $zip->getFromName('info.json');
			$info = json_decode((string) $json, true);
			if (!is_array($info)) {
				throw new Exception("Uploaded release file has invalid info.json.");
			}

			$project_release_code = (string) ($setting["project_release_code"] ?? "");
			$file_project_release_code = trim((string) ($info["project_release_code"] ?? ""));
			if ($project_release_code === "") {
				throw new Exception("Target server setting 'project_release_code' is empty.");
			}
			if ($file_project_release_code === "") {
				throw new Exception("Release file 'project_release_code' is empty.");
			}
			if ($project_release_code !== $file_project_release_code) {
				throw new Exception("project_release_code mismatch. target='" . $project_release_code . "' file='" . $file_project_release_code . "'.");
			}
			if ((string) ($info["type"] ?? "") !== "release") {
				throw new Exception("Release file type is invalid.");
			}

			$zip->deleteName('info.json');
			return $info;
		} finally {
			$zip->close();
		}
	}

	function apply_release_zip(Controller $ctl, string $zipFile): void {
		$zip = new ZipArchive();
		if ($zip->open($zipFile) !== TRUE) {
			throw new Exception($ctl->t("release.validation.cannot_open_file", ["file" => basename($zipFile)]));
		}

		try {
			$this->deleteDirectoryContents($this->appdir);
			foreach ($this->db_copy_list as $f) {
				$this->deleteDirectory("$this->datadir/$f");
			}
			$this->deleteDirectory($this->public_assets_dir);
			mkdir($this->public_assets_dir, 0777, true);
			$this->deleteDirectory($this->datadir . "/templates_c");

			if (!$zip->extractTo($this->extractdir)) {
				throw new Exception($ctl->t("release.validation.cannot_open_file", ["file" => basename($zipFile)]));
			}
		} finally {
			$zip->close();
		}

		if (is_file($zipFile)) {
			unlink($zipFile);
		}

		$this->makeTableFormatServerSide($ctl);
		$ctl->cron_set();
	}

	private function deleteDirectory($dir): void {
		if (!is_dir($dir)) {
			return;
		}
		$items = array_diff(scandir($dir), ['.', '..']);
		foreach ($items as $item) {
			$path = "$dir/$item";
			if (is_dir($path)) {
				$this->deleteDirectory($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}

	private function deleteDirectoryContents($dir): void {
		if (!is_dir($dir)) {
			return;
		}
		$items = array_diff(scandir($dir), ['.', '..']);
		foreach ($items as $item) {
			$path = "$dir/$item";
			if (is_dir($path)) {
				$this->deleteDirectoryContents($path);
				rmdir($path);
			} else {
				unlink($path);
			}
		}
	}

	private function addDirectoryFilesToZip(ZipArchive $zip, string $dir): void {
		if (!is_dir($dir)) {
			return;
		}
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $file) {
			if ($file->isDir()) {
				continue;
			}
			$filePath = $file->getRealPath();
			if ($filePath === false) {
				continue;
			}
			$relativePath = substr($filePath, strlen($this->extractdir) + 1);
			$zip->addFile($filePath, $relativePath);
		}
	}

	private function addCommonFormatFilesToZip(ZipArchive $zip): void {
		$fmtDir = $this->datadir . "/_common/fmt";
		if (!is_dir($fmtDir)) {
			return;
		}
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($fmtDir),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $file) {
			if ($file->isDir()) {
				continue;
			}
			$filePath = $file->getRealPath();
			if ($filePath === false || !$this->endsWith($filePath, ".fmt")) {
				continue;
			}
			$relativePath = substr($filePath, strlen($this->extractdir) + 1);
			$zip->addFile($filePath, $relativePath);
		}
	}

	private function makeTableFormatServerSide(Controller $ctl): void {
		$dirs = new Dirs();
		$ffm_db = $this->openDbForFormatRegeneration($dirs, "db", "db");
		$ffm_db_fields = $this->openDbForFormatRegeneration($dirs, "db_fields", "db");
		$fmt_root = $dirs->get_class_dir("common") . "/fmt/";

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
				} else if ($field["type"] == "dropdown" && strpos((string) ($field["constant_array_name"] ?? ""), "table/") === 0) {
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

		$ffm_db->close();
		$ffm_db_fields->close();
	}

	private function openDbForFormatRegeneration(Dirs $dirs, string $table, ?string $class = null): fixed_file_manager {
		if ($class === null || $class === "") {
			$class = $table;
		}

		$app_user_fmt = $dirs->appdir_user . "/" . $class . "/fmt/" . $table . ".fmt";
		if (is_file($app_user_fmt)) {
			return new fixed_file_manager($table, $dirs->datadir . "/" . $class . "/", $dirs->appdir_user . "/" . $class . "/fmt");
		}

		$app_fw_fmt = $dirs->appdir_fw . "/" . $class . "/fmt/" . $table . ".fmt";
		if (is_file($app_fw_fmt)) {
			return new fixed_file_manager($table, $dirs->datadir . "/" . $class . "/", $dirs->appdir_fw . "/" . $class . "/fmt");
		}

		return new fixed_file_manager($table, $dirs->datadir . "/common/", $dirs->get_class_dir("common") . "/fmt");
	}

	private function endsWith(string $haystack, string $needle): bool {
		$length = strlen($needle);
		if ($length === 0) {
			return true;
		}
		return substr($haystack, -$length) === $needle;
	}
}
