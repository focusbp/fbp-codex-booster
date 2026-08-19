<?php

final class PollingSecurity {

	public const DEFAULT_MAX_CONNECTIONS = 10;
	public const DEFAULT_RECONNECT_WAIT_SECONDS = 10;
	public const POLLING_REQUEST_TIMEOUT_SECONDS = 60;
	public const POLLING_START_TIMEOUT_SECONDS = 10;

	private const SESSION_KEY = "__fbp_polling_owners";
	private const OWNER_LIFETIME_SECONDS = 86400;

	public static function generatePollingId(): string {
		return bin2hex(random_bytes(16));
	}

	public static function generateOwnerToken(): string {
		return bin2hex(random_bytes(32));
	}

	public static function isValidPollingId($polling_id): bool {
		return is_string($polling_id) && preg_match('/\A[a-f0-9]{32}\z/D', $polling_id) === 1;
	}

	public static function isValidOwnerToken($owner_token): bool {
		return is_string($owner_token) && preg_match('/\A[a-f0-9]{64}\z/D', $owner_token) === 1;
	}

	public static function getClientDir(string $polling_root, $polling_id): string {
		if (!self::isValidPollingId($polling_id)) {
			throw new InvalidArgumentException("Invalid polling ID.");
		}

		return rtrim($polling_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $polling_id;
	}

	public static function registerOwner(string $polling_id, string $owner_token, ?string $windowcode = null): void {
		if (!self::isValidPollingId($polling_id) || !self::isValidOwnerToken($owner_token)) {
			throw new InvalidArgumentException("Invalid polling credentials.");
		}

		self::pruneOwners();
		$_SESSION[self::SESSION_KEY][$polling_id] = [
			"token_hash" => hash("sha256", $owner_token),
			"windowcode" => $windowcode,
			"state" => "pending",
			"created_at" => time(),
		];
	}

	public static function isOwnedByCurrentSession($polling_id, $owner_token): bool {
		if (!self::isValidPollingId($polling_id) || !self::isValidOwnerToken($owner_token)) {
			return false;
		}

		self::pruneOwners();
		$owner = $_SESSION[self::SESSION_KEY][$polling_id] ?? null;
		if (!is_array($owner) || !isset($owner["token_hash"]) || !is_string($owner["token_hash"])) {
			return false;
		}

		return hash_equals($owner["token_hash"], hash("sha256", $owner_token));
	}

	public static function setOwnerState($polling_id, $owner_token, string $state): bool {
		if (!in_array($state, ["pending", "waiting", "connected", "failed"], true)
			|| !self::isOwnedByCurrentSession($polling_id, $owner_token)) {
			return false;
		}

		$_SESSION[self::SESSION_KEY][$polling_id]["state"] = $state;
		return true;
	}

	public static function getCurrentSessionOwnerState($polling_id): ?string {
		if (!self::isValidPollingId($polling_id)) {
			return null;
		}

		self::pruneOwners();
		$state = $_SESSION[self::SESSION_KEY][$polling_id]["state"] ?? null;
		return is_string($state) ? $state : null;
	}

	public static function unregisterOwner($polling_id): void {
		if (!self::isValidPollingId($polling_id)) {
			return;
		}

		unset($_SESSION[self::SESSION_KEY][$polling_id]);
		if (isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === []) {
			unset($_SESSION[self::SESSION_KEY]);
		}
	}

	public static function removeClientDirectory(string $polling_root, $polling_id): bool {
		try {
			$client_dir = self::getClientDir($polling_root, $polling_id);
		} catch (InvalidArgumentException $e) {
			return false;
		}

		if (!file_exists($client_dir)) {
			return true;
		}
		if (!is_dir($client_dir) || is_link($client_dir)) {
			return false;
		}

		$files = scandir($client_dir);
		if ($files === false) {
			return false;
		}

		foreach ($files as $file) {
			if ($file === "." || $file === "..") {
				continue;
			}
			if ($file !== "info.json" && preg_match('/\Amsg_[a-f0-9]{13,64}\.(?:json|tmp)\z/D', $file) !== 1) {
				return false;
			}
			$path = $client_dir . DIRECTORY_SEPARATOR . $file;
			if (!is_file($path) || is_link($path)) {
				return false;
			}
		}

		foreach ($files as $file) {
			if ($file === "." || $file === "..") {
				continue;
			}
			if (!unlink($client_dir . DIRECTORY_SEPARATOR . $file)) {
				return false;
			}
		}

		return rmdir($client_dir);
	}

	/**
	 * @return string created, existing, full, or error
	 */
	public static function acquireClientSlot(string $polling_root, $polling_id, int $max_connections = self::DEFAULT_MAX_CONNECTIONS): string {
		try {
			$client_dir = self::getClientDir($polling_root, $polling_id);
		} catch (InvalidArgumentException $e) {
			return "error";
		}

		$max_connections = max(1, $max_connections);
		if (!is_dir($polling_root) && !mkdir($polling_root, 0750, true) && !is_dir($polling_root)) {
			return "error";
		}

		$lock_path = rtrim($polling_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ".capacity.lock";
		$lock = fopen($lock_path, "c+");
		if ($lock === false) {
			return "error";
		}

		if (!flock($lock, LOCK_EX)) {
			fclose($lock);
			return "error";
		}

		try {
			if (is_dir($client_dir) && !is_link($client_dir)) {
				return "existing";
			}
			if (file_exists($client_dir)) {
				return "error";
			}

			$connection_count = 0;
			$entries = glob(rtrim($polling_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "*");
			foreach ($entries === false ? [] : $entries as $entry) {
				if (is_dir($entry) && !is_link($entry) && self::isValidPollingId(basename($entry))) {
					$connection_count++;
				}
			}
			if ($connection_count >= $max_connections) {
				return "full";
			}

			if (!mkdir($client_dir, 0750) && !is_dir($client_dir)) {
				return "error";
			}
			return "created";
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private static function pruneOwners(): void {
		$owners = $_SESSION[self::SESSION_KEY] ?? null;
		if (!is_array($owners)) {
			$_SESSION[self::SESSION_KEY] = [];
			return;
		}

		$oldest_allowed = time() - self::OWNER_LIFETIME_SECONDS;
		foreach ($owners as $polling_id => $owner) {
			$created_at = is_array($owner) ? ($owner["created_at"] ?? 0) : 0;
			if (!self::isValidPollingId($polling_id) || !is_int($created_at) || $created_at < $oldest_allowed) {
				unset($_SESSION[self::SESSION_KEY][$polling_id]);
			}
		}
	}
}
