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
	private $db_file_copy_list = [
	    "mcp_manage" => [
	        "mcp_server_config.dat",
	        "mcp_tools.dat",
	        "mcp_tool_fields.dat",
	    ],
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

		$this->addSelectedDataFilesToZip($zip);
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

			$this->extractReleaseZip($zip, $ctl, $zipFile);
		} finally {
			$zip->close();
		}

		if (is_file($zipFile)) {
			unlink($zipFile);
		}

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
			if ($this->isExcludedArchivePath($relativePath)) {
				continue;
			}
			$zip->addFile($filePath, $relativePath);
		}
	}

	private function addSelectedDataFilesToZip(ZipArchive $zip): void {
		foreach ($this->db_file_copy_list as $dirName => $files) {
			$dirName = trim((string) $dirName, "/");
			if ($dirName === "" || !is_array($files)) {
				continue;
			}
			foreach ($files as $fileName) {
				$fileName = basename((string) $fileName);
				if ($fileName === "" || !$this->endsWith($fileName, ".dat")) {
					continue;
				}
				$filePath = $this->datadir . "/" . $dirName . "/" . $fileName;
				if (!is_file($filePath)) {
					continue;
				}
				$relativePath = substr($filePath, strlen($this->extractdir) + 1);
				if ($this->isExcludedArchivePath($relativePath)) {
					continue;
				}
				$zip->addFile($filePath, $relativePath);
			}
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
			if ($this->isExcludedArchivePath($relativePath)) {
				continue;
			}
			$zip->addFile($filePath, $relativePath);
		}
	}

	private function extractReleaseZip(ZipArchive $zip, Controller $ctl, string $zipFile): void {
		$entries = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$filename = $zip->getNameIndex($i);
			if (!is_string($filename) || $filename === "" || $this->isExcludedArchivePath($filename)) {
				continue;
			}
			$entries[] = $filename;
		}
		if (count($entries) === 0) {
			return;
		}
		if (!$zip->extractTo($this->extractdir, $entries)) {
			throw new Exception($ctl->t("release.validation.cannot_open_file", ["file" => basename($zipFile)]));
		}
	}

	private function isExcludedArchivePath(string $relativePath): bool {
		$path = ltrim(str_replace("\\", "/", $relativePath), "/");
		return $path === "log/ffm" || strpos($path, "log/ffm/") === 0;
	}

	private function endsWith(string $haystack, string $needle): bool {
		$length = strlen($needle);
		if ($length === 0) {
			return true;
		}
		return substr($haystack, -$length) === $needle;
	}
}
