<?php

require_once __DIR__ . "/lib/Dirs.php";
require_once __DIR__ . "/lib/PollingSecurity.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

function polling_stop_respond(int $status, array $payload): void {
	http_response_code($status);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit();
}

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
	polling_stop_respond(405, ["success" => false, "message" => "Method Not Allowed"]);
}

$data = json_decode((string) file_get_contents("php://input"), true);
$polling_id = is_array($data) ? ($data["polling_id"] ?? null) : null;
$polling_token = is_array($data) ? ($data["polling_token"] ?? null) : null;

if (!PollingSecurity::isValidPollingId($polling_id) || !PollingSecurity::isValidOwnerToken($polling_token)) {
	polling_stop_respond(400, ["success" => false, "message" => "Invalid Request"]);
}

session_start();
if (!PollingSecurity::isOwnedByCurrentSession($polling_id, $polling_token)) {
	session_write_close();
	polling_stop_respond(403, ["success" => false, "message" => "Forbidden"]);
}

$dirs = new Dirs();
if (!PollingSecurity::removeClientDirectory($dirs->pollingdir, $polling_id)) {
	session_write_close();
	polling_stop_respond(409, ["success" => false, "message" => "Polling data could not be removed"]);
}
PollingSecurity::unregisterOwner($polling_id);
session_write_close();

polling_stop_respond(200, ["success" => true]);
