<?php

class restore {

	private $dir;
	private $zipfile;

	function __construct(Controller $ctl) {
		$this->dir = realpath(dirname(__FILE__) . "/../../../classes");
		$this->zipfile = dirname(__FILE__) . "/../../../classes/log/restore.zip";

		$log_dir = dirname(__FILE__) . "/../../../classes/log";
		if (!is_dir($log_dir)) {
			mkdir($log_dir);
		}
	}

	function download_zip(Controller $ctl) {

		$setting = $ctl->get_setting();
		$ctl->assign("setting", $setting);
		if (empty($setting["project_release_code"])) {
			$ctl->assign("message", $ctl->t("release.validation.project_release_code_required"));
			$ctl->assign("flg", false);
		} else {
			$ctl->assign("message", $ctl->t("restore.download_project_message", ["code" => $setting["project_release_code"]]));
			$ctl->assign("flg", true);
		}

		$ctl->show_multi_dialog("download", "download.tpl", $ctl->t("restore.dialog.download_package"));
	}

	function download_zip_exe(Controller $ctl) {

		$zip = new ZipArchive();
		$setting = $ctl->get_setting();
		$formatter = $ctl->create_ValueFormatter();
		$timezone = !empty($setting["timezone"]) ? (string) $setting["timezone"] : date_default_timezone_get();

		if ($zip->open($this->zipfile, ZipArchive::CREATE) !== TRUE) {
			throw new Exception("Can't open zipfile:" . $this->zipfile);
		}

		// delete release information
		if (is_file("$this->dir/info.json")) {
			unlink("$this->dir/info.json");
		}

		// info
		$post = $ctl->POST();
		$info = [
		    "project_release_code" => $setting["project_release_code"],
		    "datetime" => $formatter->format_datetime(time()),
		    "timezone" => $timezone,
		    "memo" => $ctl->POST("memo"),
		    "type" => "restore"
		];
		$json = json_encode($info);
		$zip->addFromString("info.json", $json);

		// app
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->dir),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $name => $file) {
			// Skip directories (they would be added automatically)
			if (!$file->isDir()) {
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($this->dir) + 1);
				if ($this->isExcludedRestoreArchivePath($relativePath)) {
					continue;
				}
				$zip->addFile($filePath, $relativePath);
			}
		}

		$zip->close();

		// Output zip file data
		readfile($this->zipfile);

		// Remove the zip file from the server after download
		unlink($this->zipfile);
	}

	function restore(Controller $ctl) {
		$setting = $ctl->get_setting();
		$ctl->assign("setting", $setting);
		if (empty($setting["project_release_code"])) {
			$ctl->assign("message", $ctl->t("release.validation.project_release_code_required"));
			$ctl->assign("flg", false);
		} else {
			$ctl->assign("message", $ctl->t("restore.restore_project_message", ["code" => $setting["project_release_code"]]));
			$ctl->assign("flg", true);
		}
		$ctl->show_multi_dialog("Restore", "restore.tpl", $ctl->t("restore.dialog.restore"), 600, true, true);
	}

	function restore_confirm(Controller $ctl) {

		$setting = $ctl->get_setting();
		$saved_release_file = "restore.zip";

		// Upload file to server 
		$ctl->save_posted_file('restore_file', $saved_release_file);
		$zipfile = $ctl->get_saved_filepath($saved_release_file);

		rename($zipfile, $this->zipfile); //移動
		// Create a new zip archive
		$zip = new ZipArchive();

		// Open the zip file
		if ($zip->open($this->zipfile) === TRUE) {
			// check
			$json = $zip->getFromName('info.json');
			$info = json_decode($json, true);
			$zip->close();
			if ($setting["project_release_code"] == $info["project_release_code"] && $info["type"] == "restore") {

				$ctl->assign("info", $info);
				$ctl->assign("flg", true);
			} else {
				$ctl->assign("message", $ctl->t("restore.validation.invalid_restore_file"));
				$ctl->assign("info", $info);
				$ctl->assign("flg", false);
				unlink($this->zipfile);
			}
		} else {
			$ctl->assign("message", $ctl->t("release.validation.cannot_open_uploaded_file"));
			$ctl->assign("flg", false);
		}
		$ctl->show_multi_dialog("Restore", "restore_confirm.tpl", $ctl->t("restore.dialog.restore"), 600, true, true);
	}

	function restore_exe(Controller $ctl) {
		$excludedPaths = $this->getRestoreExcludedPaths($ctl);

		// Delete files
		//app
		$this->deleteDirectoryContents("$this->dir/app"); //配下のみ削除
		$this->deleteDirectoryContents("$this->dir/data", $excludedPaths); //配下のみ削除
		// Create a new zip archive
		$zip = new ZipArchive();

		// Open the zip file
		if ($zip->open($this->zipfile) === TRUE) {
			// Iterate through each file in the archive
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$filename = $zip->getNameIndex($i);

				// Only extract files from /app and /data directories
				if ((strpos($filename, 'app/') === 0 || strpos($filename, 'data/') === 0) && !$this->isExcludedRestorePath($filename, $excludedPaths)) {
					// Create the path for extraction
					$filePath = $this->dir . '/' . $filename;

					// Ensure the directory structure is created
					if (!is_dir(dirname($filePath))) {
						mkdir(dirname($filePath), 0777, true);
					}

					// Extract the file
					copy('zip://' . $this->zipfile . '#' . $filename, $filePath);
				}
			}

			unlink($this->zipfile);

			$ctl->assign("success", $ctl->t("restore.success"));
			
			// cron再設定
			$ctl->cron_set();
		} else {
			$ctl->assign("fail", $ctl->t("release.validation.cannot_open_file", ["file" => $this->zipfile]));
		}
		$ctl->show_multi_dialog("Restore", "restore_done.tpl", $ctl->t("restore.dialog.restore"), 600, true, true);
	}

	function reload(Controller $ctl) {
		$ctl->res_reload();
	}

	// 指定したディレクトリの配下を削除する関数
	function deleteDirectoryContents($dir, $excludedPaths = []) {
		if (!is_dir($dir)) {
			return;
		}
		$items = array_diff(scandir($dir), ['.', '..']);
		foreach ($items as $item) {
			$path = "$dir/$item";
			$relativePath = ltrim(str_replace($this->dir . "/", "", $path), "/");
			if ($this->isExcludedRestorePath($relativePath, $excludedPaths)) {
				continue;
			}
			if (is_dir($path)) {
				$this->deleteDirectoryContents($path, $excludedPaths);
				rmdir($path); // 空のディレクトリを削除
			} else {
				unlink($path); // ファイルを削除
			}
		}
	}

	private function isExcludedRestorePath($relativePath, ?array $excludedPaths = null) {
		$targetPath = ltrim(str_replace("\\", "/", $relativePath), "/");
		$excludedList = $excludedPaths ?? [];
		foreach ($excludedList as $excludedPath) {
			$normalizedExcludedPath = ltrim(str_replace("\\", "/", $excludedPath), "/");
			if ($targetPath === rtrim($normalizedExcludedPath, "/") || strpos($targetPath, $normalizedExcludedPath) === 0) {
				return true;
			}
		}
		return false;
	}

	private function isExcludedRestoreArchivePath($relativePath) {
		$targetPath = ltrim(str_replace("\\", "/", (string) $relativePath), "/");
		return $targetPath === "log/ffm" || strpos($targetPath, "log/ffm/") === 0;
	}

	private function getRestoreExcludedPaths(Controller $ctl) {
		$excludedPaths = [];
		if ((string) $ctl->POST("restore_user_data") !== "1") {
			$excludedPaths[] = "data/user/";
		}
		if ((string) $ctl->POST("restore_setting") !== "1") {
			$excludedPaths[] = "data/setting/";
		}
		return $excludedPaths;
	}
}
