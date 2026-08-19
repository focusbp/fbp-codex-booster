<?php

interface Controller {
}

require_once __DIR__ . "/../fbp/lib/Controller_class.php";

function polling_security_assert(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$polling_id = PollingSecurity::generatePollingId();
$other_polling_id = PollingSecurity::generatePollingId();
$owner_token = PollingSecurity::generateOwnerToken();

polling_security_assert($polling_id !== $other_polling_id, "Polling IDs are not unique.");
polling_security_assert(PollingSecurity::isValidPollingId($polling_id), "Generated polling ID is invalid.");
polling_security_assert(PollingSecurity::isValidOwnerToken($owner_token), "Generated owner token is invalid.");
polling_security_assert(PollingSecurity::DEFAULT_MAX_CONNECTIONS === 10, "Default polling connection limit changed.");
polling_security_assert(PollingSecurity::DEFAULT_RECONNECT_WAIT_SECONDS === 10, "Default reconnect wait changed.");
polling_security_assert(PollingSecurity::POLLING_REQUEST_TIMEOUT_SECONDS === 60, "Polling request timeout changed.");

foreach (["", "../outside", str_repeat("a", 31), str_repeat("a", 33), strtoupper($polling_id)] as $invalid_id) {
	polling_security_assert(!PollingSecurity::isValidPollingId($invalid_id), "Invalid polling ID was accepted: " . $invalid_id);
	try {
		PollingSecurity::getClientDir("/tmp/polling", $invalid_id);
		throw new RuntimeException("Invalid polling ID was converted to a path: " . $invalid_id);
	} catch (InvalidArgumentException $e) {
		// Expected.
	}
}

$_SESSION = [];
PollingSecurity::registerOwner($polling_id, $owner_token, "WID_test");
polling_security_assert(
	PollingSecurity::getCurrentSessionOwnerState($polling_id) === "pending",
	"New polling owner state is not pending."
);
polling_security_assert(
	PollingSecurity::isOwnedByCurrentSession($polling_id, $owner_token),
	"Registered polling owner was not authenticated."
);
polling_security_assert(
	!PollingSecurity::isOwnedByCurrentSession($polling_id, PollingSecurity::generateOwnerToken()),
	"Incorrect polling owner token was accepted."
);
polling_security_assert(
	PollingSecurity::setOwnerState($polling_id, $owner_token, "waiting"),
	"Polling owner state was not updated."
);
polling_security_assert(
	PollingSecurity::getCurrentSessionOwnerState($polling_id) === "waiting",
	"Updated polling owner state was not returned."
);
$owner_session = $_SESSION;
$_SESSION = [];
polling_security_assert(
	!PollingSecurity::isOwnedByCurrentSession($polling_id, $owner_token),
	"Polling credentials were accepted in another session."
);
$_SESSION = $owner_session;
PollingSecurity::unregisterOwner($polling_id);
polling_security_assert(
	!PollingSecurity::isOwnedByCurrentSession($polling_id, $owner_token),
	"Unregistered polling owner was still authenticated."
);

$_SESSION = [];
$controller_reflection = new ReflectionClass(Controller_class::class);
$controller = $controller_reflection->newInstanceWithoutConstructor();
foreach (["class" => "test", "windowcode" => "WID_test", "arr" => []] as $property_name => $value) {
	$property = $controller_reflection->getProperty($property_name);
	$property->setValue($controller, $value);
}
$controller->polling_start("tester", "waiting");
$response_property = $controller_reflection->getProperty("arr");
$polling_response = $response_property->getValue($controller)["polling"] ?? null;
polling_security_assert(is_array($polling_response), "polling_start did not create polling response data.");
polling_security_assert(
	PollingSecurity::isOwnedByCurrentSession($polling_response["polling_id"], $polling_response["polling_token"]),
	"polling_start did not register its owner credentials."
);

$test_root = __DIR__ . "/.polling-security-" . bin2hex(random_bytes(8));
$client_dir = PollingSecurity::getClientDir($test_root, $polling_id);
$protected_id = PollingSecurity::generatePollingId();
$protected_dir = PollingSecurity::getClientDir($test_root, $protected_id);
$slot_ids = [
	PollingSecurity::generatePollingId(),
	PollingSecurity::generatePollingId(),
	PollingSecurity::generatePollingId(),
];

try {
	polling_security_assert(
		PollingSecurity::acquireClientSlot($test_root, $slot_ids[0], 2) === "created",
		"First polling slot was not created."
	);
	polling_security_assert(
		PollingSecurity::acquireClientSlot($test_root, $slot_ids[1], 2) === "created",
		"Second polling slot was not created."
	);
	polling_security_assert(
		PollingSecurity::acquireClientSlot($test_root, $slot_ids[2], 2) === "full",
		"Polling connection limit was not enforced."
	);
	polling_security_assert(
		PollingSecurity::acquireClientSlot($test_root, $slot_ids[0], 2) === "existing",
		"Existing polling client consumed another slot."
	);
	foreach (array_slice($slot_ids, 0, 2) as $slot_id) {
		polling_security_assert(
			PollingSecurity::removeClientDirectory($test_root, $slot_id),
			"Polling slot directory was not removed."
		);
	}

	mkdir($client_dir, 0750, true);
	file_put_contents($client_dir . "/info.json", "{}");
	file_put_contents($client_dir . "/msg_" . bin2hex(random_bytes(16)) . ".json", "{}");
	file_put_contents($client_dir . "/msg_" . bin2hex(random_bytes(16)) . ".tmp", "{}");
	polling_security_assert(
		PollingSecurity::removeClientDirectory($test_root, $polling_id),
		"Polling client directory was not removed."
	);
	polling_security_assert(!file_exists($client_dir), "Polling client directory still exists.");

	mkdir($protected_dir, 0750);
	file_put_contents($protected_dir . "/unrelated.txt", "keep");
	polling_security_assert(
		!PollingSecurity::removeClientDirectory($test_root, $protected_id),
		"Directory containing an unrelated file was removed."
	);
	polling_security_assert(
		file_get_contents($protected_dir . "/unrelated.txt") === "keep",
		"Unrelated file was modified."
	);
} finally {
	@unlink($protected_dir . "/unrelated.txt");
	@rmdir($protected_dir);
	@rmdir($client_dir);
	foreach ($slot_ids as $slot_id) {
		@rmdir(PollingSecurity::getClientDir($test_root, $slot_id));
	}
	@unlink($test_root . "/.capacity.lock");
	@rmdir($test_root);
}

$wait_session_id = "fbppollingwait" . bin2hex(random_bytes(8));
session_id($wait_session_id);
session_start();
try {
	$_SESSION = [];
	$wait_polling_id = PollingSecurity::generatePollingId();
	$wait_owner_token = PollingSecurity::generateOwnerToken();
	$_SESSION["WID_wait"]["_polling_id"] = $wait_polling_id;
	PollingSecurity::registerOwner($wait_polling_id, $wait_owner_token, "WID_wait");
	PollingSecurity::setOwnerState($wait_polling_id, $wait_owner_token, "waiting");

	$wait_controller = $controller_reflection->newInstanceWithoutConstructor();
	foreach (["class" => "test", "windowcode" => "WID_wait", "arr" => []] as $property_name => $value) {
		$property = $controller_reflection->getProperty($property_name);
		$property->setValue($wait_controller, $value);
	}
	$wait_controller->dirs = (object) ["pollingdir" => __DIR__ . "/.polling-wait-missing"];
	$wait_started_at = microtime(true);
	polling_security_assert(!$wait_controller->polling_wait(5), "Capacity-waiting polling unexpectedly connected.");
	polling_security_assert(
		microtime(true) - $wait_started_at < 1,
		"Capacity-waiting polling did not release immediately."
	);
	polling_security_assert(
		session_status() === PHP_SESSION_ACTIVE,
		"polling_wait did not restore the PHP session."
	);
} finally {
	$_SESSION = [];
	session_destroy();
}

echo "polling security test passed\n";
