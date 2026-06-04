<?php

class bcp_export {

	private $project_root;
	private $temp_dir;

	function __construct(Controller $ctl) {
		$this->project_root = $this->resolve_project_root();
		$this->temp_dir = $this->resolve_temp_dir();
	}

	function page(Controller $ctl) {
		$this->require_app_admin($ctl);

		$setting = $ctl->get_setting();
		$ctl->assign("setting", $setting);
		$ctl->assign("download_filename", $this->build_download_filename($setting));
		$ctl->show_main_area("index.tpl", $ctl->t("bcp_export.title"));
	}

	function download_zip(Controller $ctl) {
		$this->require_app_admin($ctl);

		$zip_file = "";
		try {
			$zip_file = $this->create_export_zip($ctl);
			$download_name = $this->build_download_filename($ctl->get_setting());
			$this->send_zip_and_delete($zip_file, $download_name);
		} catch (Throwable $e) {
			if ($zip_file !== "" && is_file($zip_file)) {
				unlink($zip_file);
			}
			error_log("[FBP BCP Export] " . $e->getMessage());
			$this->respond_download_error($ctl, $ctl->t("bcp_export.download_failed"));
		}
	}

	private function require_app_admin(Controller $ctl): void {
		if (!$ctl->is_app_admin()) {
			$ctl->deny_forbidden_access();
		}
	}

	private function resolve_project_root(): string {
		$root = realpath(dirname(__FILE__) . "/../../..");
		return $root !== false ? $root : dirname(__FILE__) . "/../../..";
	}

	private function resolve_temp_dir(): string {
		return rtrim($this->project_root, "/") . "/tmp/bcp_export";
	}

	private function assert_project_root(): void {
		if (!is_dir($this->project_root)) {
			throw new Exception("Project root was not found.");
		}
		if (!is_file($this->project_root . "/.htaccess")) {
			throw new Exception("Project root .htaccess was not found.");
		}
	}

	private function create_export_zip(Controller $ctl): string {
		if (function_exists("set_time_limit")) {
			@set_time_limit(0);
		}

		$this->assert_project_root();
		$this->prepare_temp_dir();
		$this->cleanup_old_temp_files();

		$setting = $ctl->get_setting();
		$zip_file = $this->temp_dir . "/" . $this->build_temp_zip_filename($setting);
		$zip = new ZipArchive();

		if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new Exception("Cannot create BCP export zip.");
		}

		$file_count = 0;
		$total_size = 0;
		$checksums = [];

		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($this->project_root, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ($files as $file) {
				if ($file->isDir() || $file->isLink()) {
					continue;
				}
				$file_path = $file->getRealPath();
				if ($file_path === false || !$file->isReadable()) {
					continue;
				}
				$relative_path = $this->relative_path($file_path);
				if ($this->is_excluded_path($relative_path, $file_path)) {
					continue;
				}
				if (!$zip->addFile($file_path, $relative_path)) {
					throw new Exception("Cannot add file to BCP export zip.");
				}
				$hash = hash_file("sha256", $file_path);
				if ($hash === false) {
					throw new Exception("Cannot calculate file checksum.");
				}
				$checksums[] = $hash . "  " . $relative_path;
				$file_count++;
				$size = filesize($file_path);
				if ($size !== false) {
					$total_size += $size;
				}
			}

			$readme = $this->build_readme_text($ctl);
			$zip->addFromString("README_BCP_EXPORT.txt", $readme);
			$checksums[] = hash("sha256", $readme) . "  README_BCP_EXPORT.txt";

			$manifest = $this->build_manifest_json($setting, $file_count, $total_size);
			$zip->addFromString("BCP_EXPORT_MANIFEST.json", $manifest);
			$checksums[] = hash("sha256", $manifest) . "  BCP_EXPORT_MANIFEST.json";

			sort($checksums, SORT_STRING);
			$zip->addFromString("CHECKSUMS.sha256", implode("\n", $checksums) . "\n");
		} catch (Throwable $e) {
			$zip->close();
			if (is_file($zip_file)) {
				unlink($zip_file);
			}
			throw $e;
		}

		if (!$zip->close()) {
			if (is_file($zip_file)) {
				unlink($zip_file);
			}
			throw new Exception("Cannot close BCP export zip.");
		}
		return $zip_file;
	}

	private function prepare_temp_dir(): void {
		if (!is_dir($this->temp_dir) && !mkdir($this->temp_dir, 0700, true)) {
			throw new Exception("Cannot create BCP export temp directory.");
		}
		if (!is_writable($this->temp_dir)) {
			throw new Exception("BCP export temp directory is not writable.");
		}
	}

	private function cleanup_old_temp_files(): void {
		if (!is_dir($this->temp_dir)) {
			return;
		}
		$expire = time() - 86400;
		foreach (glob($this->temp_dir . "/bcp-system-*.zip") ?: [] as $path) {
			if (is_file($path) && filemtime($path) !== false && filemtime($path) < $expire) {
				unlink($path);
			}
		}
	}

	private function relative_path(string $file_path): string {
		return str_replace("\\", "/", substr($file_path, strlen($this->project_root) + 1));
	}

	private function is_excluded_path(string $relative_path, string $file_path): bool {
		$relative_path = trim(str_replace("\\", "/", $relative_path), "/");
		if ($relative_path === "") {
			return true;
		}

		$parts = explode("/", $relative_path);
		if (in_array(".svn", $parts, true) || in_array(".git", $parts, true)) {
			return true;
		}
		if (in_array("templates_c", $parts, true)) {
			return true;
		}
		if ($relative_path === "classes/log/tmp" || strpos($relative_path, "classes/log/tmp/") === 0) {
			return true;
		}

		$temp_real = realpath($this->temp_dir);
		if ($temp_real !== false && strpos($file_path, rtrim($temp_real, "/") . "/") === 0) {
			return true;
		}

		return false;
	}

	private function build_manifest_json(array $setting, int $file_count, int $total_size): string {
		$manifest = [
			"type" => "bcp_export",
			"created_at" => date(DATE_ATOM),
			"project_release_code" => (string) ($setting["project_release_code"] ?? ""),
			"root_marker" => ".htaccess",
			"file_count" => $file_count,
			"total_size" => $total_size,
			"excluded" => [
				".svn/",
				".git/",
				"templates_c/",
				"classes/log/tmp/",
				"tmp/bcp_export/",
				"temporary export zip",
			],
		];
		return json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
	}

	private function build_readme_text(Controller $ctl): string {
		return implode("\n", [
			"BCP Export",
			"",
			"This archive contains the application files under the directory that contains .htaccess.",
			"It is intended for emergency migration to another server.",
			"",
			"Basic restore outline:",
			"1. Prepare a PHP and Apache environment compatible with FBP.",
			"2. Extract this archive into the application document root.",
			"3. Enable .htaccess / mod_rewrite.",
			"4. Set writable permissions for classes/data and classes/log as needed.",
			"5. Verify login and core screens.",
			"6. Reconfigure cron jobs if the system uses them.",
			"",
			"Use CHECKSUMS.sha256 to verify extracted files when needed.",
			"",
		]);
	}

	private function build_download_filename(array $setting): string {
		return "bcp-system-" . $this->safe_project_code($setting) . "-" . date("Ymd-His") . ".zip";
	}

	private function build_temp_zip_filename(array $setting): string {
		return "bcp-system-" . $this->safe_project_code($setting) . "-" . date("Ymd-His") . "-" . $this->random_suffix() . ".zip";
	}

	private function safe_project_code(array $setting): string {
		$code = trim((string) ($setting["project_release_code"] ?? ""));
		if ($code === "") {
			$code = "system";
		}
		$code = preg_replace('/[^A-Za-z0-9._-]/', '_', $code);
		return $code !== "" ? $code : "system";
	}

	private function random_suffix(): string {
		try {
			return bin2hex(random_bytes(4));
		} catch (Throwable $e) {
			return substr(sha1(uniqid("", true)), 0, 8);
		}
	}

	private function send_zip_and_delete(string $zip_file, string $download_name): void {
		if (!is_file($zip_file)) {
			throw new Exception("BCP export zip was not created.");
		}

		register_shutdown_function(static function () use ($zip_file) {
			if (is_file($zip_file)) {
				unlink($zip_file);
			}
		});

		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		$ascii_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $download_name);
		if ($ascii_name === "" || $ascii_name === null) {
			$ascii_name = "bcp-export.zip";
		}

		header("Content-Type: application/zip");
		header('Content-Disposition: attachment; filename="' . addcslashes($ascii_name, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($download_name));
		header("Content-Length: " . filesize($zip_file));
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Pragma: public");

		readfile($zip_file);
		unlink($zip_file);
		exit;
	}

	private function respond_download_error(Controller $ctl, string $message): void {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		http_response_code(500);
		header("Content-Type: application/json; charset=UTF-8");
		header("X-FBP-Download-Error: 1");
		header("X-FBP-Download-Error-Title: " . rawurlencode($ctl->t("bcp_export.title")));
		echo json_encode([
			"title" => $ctl->t("bcp_export.title"),
			"message" => $message,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}
}
