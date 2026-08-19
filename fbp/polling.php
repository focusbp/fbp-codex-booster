<?php

require_once __DIR__ . "/lib/Dirs.php";
require_once __DIR__ . "/lib/PollingSecurity.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

function polling_respond(int $status, array $payload): void {
	http_response_code($status);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit();
}

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
	polling_respond(405, ["success" => false, "message" => "Method Not Allowed"]);
}

$polling_id = $_POST["polling_id"] ?? null;
$polling_token = $_POST["polling_token"] ?? null;
$nickname = $_POST["nickname"] ?? null;
$status_text = $_POST["status_text"] ?? null;
$info_data = $_POST["info_data"] ?? [];

if (!PollingSecurity::isValidPollingId($polling_id)
	|| !PollingSecurity::isValidOwnerToken($polling_token)
	|| !is_string($nickname)
	|| !is_string($status_text)) {
	polling_respond(400, ["success" => false, "message" => "Invalid Request"]);
}

session_start();
if (!PollingSecurity::isOwnedByCurrentSession($polling_id, $polling_token)) {
	session_write_close();
	polling_respond(403, ["success" => false, "message" => "Forbidden"]);
}

$dirs = new Dirs();
$slot_status = PollingSecurity::acquireClientSlot(
	$dirs->pollingdir,
	$polling_id,
	PollingSecurity::DEFAULT_MAX_CONNECTIONS
);
if ($slot_status === "full") {
	PollingSecurity::setOwnerState($polling_id, $polling_token, "waiting");
	session_write_close();
	polling_respond(200, [
		"success" => false,
		"message" => "Wait",
		"retry_after_seconds" => PollingSecurity::DEFAULT_RECONNECT_WAIT_SECONDS,
	]);
}
if ($slot_status === "error") {
	PollingSecurity::setOwnerState($polling_id, $polling_token, "failed");
	session_write_close();
	polling_respond(500, ["success" => false, "message" => "Polling Error"]);
}
PollingSecurity::setOwnerState($polling_id, $polling_token, "connected");
// ロングポーリング中に同じ利用者のapp.phpリクエストを塞がないよう、受付完了後すぐにセッションロックを解放する。
session_write_close();

while (ob_get_level() > 0) {
	ob_end_flush();
}
ob_implicit_flush(true);

$clientDir = PollingSecurity::getClientDir($dirs->pollingdir, $polling_id);
$remove_folder_after_exit = true;
register_shutdown_function(function () use ($dirs, $polling_id, &$remove_folder_after_exit) {
	if ($remove_folder_after_exit) {
		PollingSecurity::removeClientDirectory($dirs->pollingdir, $polling_id);
	}
});

try {
	if (is_link($clientDir)) {
		throw new RuntimeException("Invalid polling directory.");
	}

	$infoFile = $clientDir . "/info.json";
	if (!is_file($infoFile)) {
		$info = json_encode([
			"polling_id" => $polling_id,
			"nickname" => $nickname,
			"status_text" => $status_text,
			"info_data" => $info_data,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if ($info === false || file_put_contents($infoFile, $info, LOCK_EX) === false) {
			throw new RuntimeException("Failed to create polling information.");
		}
	}

	$timeout = PollingSecurity::POLLING_REQUEST_TIMEOUT_SECONDS;
	$startTime = time();

	while (true) {
		if (!is_dir($clientDir)) {
			polling_respond(200, ["success" => false, "message" => "Abort"]);
		}

		if (connection_aborted()) {
			exit();
		}

		$files = glob($clientDir . "/msg_*.json");
		if (!empty($files)) {
			sort($files, SORT_STRING);
			$file = $files[0];
			$claimedFile = substr($file, 0, -5) . ".tmp";
			if (rename($file, $claimedFile)) {
				$data = file_get_contents($claimedFile);
				unlink($claimedFile);
				$data_decoded = json_decode((string) $data, true);
				if (is_array($data_decoded)) {
					$remove_folder_after_exit = false;
					polling_respond(200, ["success" => true, "data" => $data_decoded]);
				}
			}
		}

		if (time() - $startTime > $timeout) {
			polling_respond(200, [
				"success" => false,
				"message" => "Timeout",
				"retry_after_seconds" => PollingSecurity::DEFAULT_RECONNECT_WAIT_SECONDS,
			]);
		}

		usleep(500000);
		echo " ";
		flush();
	}
} catch (Throwable $e) {
	polling_respond(500, ["success" => false, "message" => "Polling Error"]);
}
