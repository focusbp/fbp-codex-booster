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
	        "mcp_functions.dat",
	        "mcp_tools.dat",
	        "mcp_tool_fields.dat",
	    ],
	];
	private $root_file_copy_list = [
		".htaccess",
		"robots.txt",
	];
	private $projectRoot;
	private $appdir;
	private $datadir;
	private $zipfile;
	private $extractdir;
	private $public_assets_dir;
	private $releaseInfo = [];

		function __construct(?string $projectRoot = null, ?string $zipFile = null) {
		if ($projectRoot === null || $projectRoot === "") {
			$projectRoot = dirname(__FILE__) . "/../../../";
		}
		$projectRoot = rtrim($projectRoot, "/");
		$resolvedProjectRoot = realpath($projectRoot);
		if ($resolvedProjectRoot !== false && $resolvedProjectRoot !== "") {
			$projectRoot = $resolvedProjectRoot;
		}
		$this->projectRoot = $projectRoot;

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
		$info["deploy_email_templates"] = $this->deployEmailTemplates($info);
		$this->releaseInfo = $info;
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
			if ($f === "email_format" && !$this->deployEmailTemplates($this->releaseInfo)) continue;
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
		$this->addRootFilesToZip($zip);
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

			$info["deploy_email_templates"] = $this->deployEmailTemplates($info);
			$this->releaseInfo = $info;
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

		$metadata = $zip->getFromName("info.json");
		$info = $metadata === false ? [] : json_decode($metadata, true);
		if (!is_array($info)) throw new RuntimeException("Invalid release metadata.");
		$deployEmail = $this->deployEmailTemplates($info);
		$stageDir = $this->createReleaseStageDirectory();
		try {
			$rootEntries = $this->extractReleaseZipToDirectory($zip, $ctl, $zipFile, $stageDir);
			if (!is_dir($stageDir . "/app")) {
				throw new Exception($ctl->t("release.validation.cannot_open_file", ["file" => basename($zipFile)]));
			}
			$this->deployStagedDirectory($stageDir . "/app", $this->appdir, $ctl, $zipFile, false);
			foreach ($this->db_copy_list as $f) {
				if ($f === "email_format" && !$deployEmail) continue;
				$this->deployStagedDirectory($stageDir . "/data/$f", "$this->datadir/$f", $ctl, $zipFile, false);
			}
			$this->deployStagedDirectory($stageDir . "/data/public_pages/assets", $this->public_assets_dir, $ctl, $zipFile, true);
			$this->copyStagedDirectoryFiles($stageDir . "/data/_common/fmt", $this->datadir . "/_common/fmt");
			$this->copyStagedDirectoryFiles($stageDir . "/data/mcp_manage", $this->datadir . "/mcp_manage");
			$this->deleteDirectory($this->datadir . "/templates_c");
			$this->extractRootFiles($zip, $ctl, $zipFile, $rootEntries);
		} finally {
			$zip->close();
			$this->deleteDirectory($stageDir);
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

	private function addRootFilesToZip(ZipArchive $zip): void {
		foreach ($this->root_file_copy_list as $fileName) {
			$fileName = basename((string) $fileName);
			if ($fileName === ".htaccess" && !empty($this->releaseInfo["skip_htaccess"])) {
				continue;
			}
			if ($fileName === ".htaccess" && array_key_exists("htaccess_subpath", $this->releaseInfo)) {
				$zip->addFromString("project_root/.htaccess", $this->renderHtaccessForRelease());
				continue;
			}
			$filePath = $this->projectRoot . "/" . $fileName;
			if ($fileName === "" || !is_file($filePath)) {
				continue;
			}
			$zip->addFile($filePath, "project_root/" . $fileName);
		}
	}

	private function renderHtaccessForRelease(): string {
		$templatePath = $this->projectRoot . "/fbp/app/setting/Templates/htaccess.tpl";
		if (!is_file($templatePath)) {
			return "";
		}
		$template = file_get_contents($templatePath);
		$ssl = ((int) ($this->releaseInfo["htaccess_ssl"] ?? 0) === 1)
			? 'RewriteCond %{HTTPS} off' . "\n" . 'RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R]'
			: "";
		$replacements = [
			'{$class}' => (string) ($this->releaseInfo["htaccess_rewrite_rule_root"] ?? "login"),
			'{$function}' => (string) ($this->releaseInfo["htaccess_rewrite_rule_function"] ?? "page"),
			'{$subpath}' => (string) ($this->releaseInfo["htaccess_subpath"] ?? ""),
			'{$default_class_name}' => (string) ($this->releaseInfo["htaccess_default_class_name"] ?? ""),
			'{$ssl}' => $ssl,
		];
		return str_replace(array_keys($replacements), array_values($replacements), $template);
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

	private function createReleaseStageDirectory(): string {
		$stageDir = $this->extractdir . "/log/.release_stage_" . bin2hex(random_bytes(8));
		if (!mkdir($stageDir, 0700, true) && !is_dir($stageDir)) {
			throw new Exception("Cannot create release staging directory.");
		}
		return $stageDir;
	}

	private function extractReleaseZipToDirectory(ZipArchive $zip, Controller $ctl, string $zipFile, string $targetDir): array {
		$entries = [];
		$rootEntries = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$filename = $zip->getNameIndex($i);
			if (!is_string($filename) || $filename === "" || $filename === "info.json" || $this->isExcludedArchivePath($filename)) {
				continue;
			}
			if (strpos($filename, "project_root/") === 0) {
				$rootEntries[] = $filename;
				continue;
			}
			$entries[] = $filename;
		}
		if (count($entries) === 0 && count($rootEntries) === 0) {
			return [];
		}
		if (count($entries) > 0 && !$zip->extractTo($targetDir, $entries)) {
			throw new Exception($ctl->t("release.validation.cannot_open_file", ["file" => basename($zipFile)]));
		}
		return $rootEntries;
	}

	private function deployStagedDirectory(string $stagedDir, string $targetDir, Controller $ctl, string $zipFile, bool $createWhenAbsent): void {
		if (!is_dir($stagedDir)) {
			$this->deleteDirectory($targetDir);
			if ($createWhenAbsent && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
				throw new Exception($ctl->t("release.validation.cannot_open_file", ["file" => basename($zipFile)]));
			}
			return;
		}
		if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
			throw new Exception($ctl->t("release.validation.cannot_open_file", ["file" => basename($zipFile)]));
		}
		$this->copyStagedDirectoryFiles($stagedDir, $targetDir);
		$this->removeFilesMissingFromStage($stagedDir, $targetDir);
	}

	private function copyStagedDirectoryFiles(string $sourceDir, string $targetDir): void {
		if (!is_dir($sourceDir)) {
			return;
		}
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $file) {
			if (!$file->isFile()) {
				continue;
			}
			$relativePath = substr($file->getPathname(), strlen($sourceDir) + 1);
			$targetPath = $targetDir . "/" . $relativePath;
			$targetParent = dirname($targetPath);
			if (!is_dir($targetParent)) {
				if (!mkdir($targetParent, 0777, true) && !is_dir($targetParent)) {
					throw new RuntimeException("Cannot create release target directory.");
				}
			}
			$tempPath = $targetPath . ".release_new_" . bin2hex(random_bytes(8));
			if (!copy($file->getPathname(), $tempPath) || !rename($tempPath, $targetPath)) {
				@unlink($tempPath);
				throw new RuntimeException("Cannot install staged release file.");
			}
		}
	}

	private function removeFilesMissingFromStage(string $sourceDir, string $targetDir): void {
		foreach (array_diff(scandir($targetDir), [".", ".."]) as $item) {
			$sourcePath = $sourceDir . "/" . $item;
			$targetPath = $targetDir . "/" . $item;
			if (!file_exists($sourcePath)) {
				if (is_dir($targetPath)) {
					$this->deleteDirectory($targetPath);
				} else {
					unlink($targetPath);
				}
				continue;
			}
			if (is_dir($targetPath) && is_dir($sourcePath)) {
				$this->removeFilesMissingFromStage($sourcePath, $targetPath);
			}
		}
	}

	private function extractRootFiles(ZipArchive $zip, Controller $ctl, string $zipFile, array $entries): void {
		foreach ($entries as $entry) {
			$fileName = substr((string) $entry, strlen("project_root/"));
			if ($fileName === "" || basename($fileName) !== $fileName || !in_array($fileName, $this->root_file_copy_list, true)) {
				continue;
			}
			$contents = $zip->getFromName((string) $entry);
			if ($contents === false) {
				throw new Exception($ctl->t("release.validation.cannot_open_file", ["file" => basename($zipFile)]));
			}
			file_put_contents($this->projectRoot . "/" . $fileName, $contents);
		}
	}

	private function isExcludedArchivePath(string $relativePath): bool {
		$path = ltrim(str_replace("\\", "/", $relativePath), "/");
		return $path === "log/ffm" || strpos($path, "log/ffm/") === 0;
	}

	private function deployEmailTemplates(array $info): bool {
		if (!array_key_exists("deploy_email_templates", $info)) return true;
		$value = $info["deploy_email_templates"];
		if (in_array($value, [true, 1, "1"], true)) return true;
		if (in_array($value, [false, 0, "0"], true)) return false;
		throw new RuntimeException("Invalid deploy_email_templates flag.");
	}

	private function endsWith(string $haystack, string $needle): bool {
		$length = strlen($needle);
		if ($length === 0) {
			return true;
		}
		return substr($haystack, -$length) === $needle;
	}
}
