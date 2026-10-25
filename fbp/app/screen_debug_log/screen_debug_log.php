<?php

class screen_debug_log {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	// Retired endpoint: stale pages must never create new logs.
	function capture(Controller $ctl) {
		$this->respond_error(410, "feature_retired", "Screen debug capture has been retired");
	}

	function get(Controller $ctl) {
		if ($ctl->verify_release_api_request() !== true) {
			exit;
		}
		$key = trim((string) ($ctl->GET("key") ?? $ctl->GET("code") ?? $ctl->GET("id") ?? ""));
		if ($key === "") {
			$this->respond_error(400, "key_required", "key is required");
		}
		$key = $this->normalize_lookup_key($key);

		$ffm = $ctl->db("screen_debug_log", "screen_debug_log");
		$item = [];
		if (ctype_digit($key)) {
			$item = $ffm->get((int) $key);
		} else {
			$list = $ffm->select("public_code", $key, false, "AND", "id", SORT_DESC, 1);
			if (!empty($list)) {
				$item = $list[0];
			}
		}
		if (empty($item)) {
			$this->respond_error(404, "not_found", "screen debug log was not found");
		}
		$item = $this->decode_json_fields($item);
		$this->respond_json([
			"ok" => true,
			"item" => $item,
		]);
	}

	private function normalize_lookup_key($key) {
		$key = trim((string) $key);
		if (preg_match('/^\[ScreenLog:([^:\]]+):(SD-[A-Z0-9]{4}-[A-Z0-9]{4})\]$/', $key, $matches)) {
			return $matches[2];
		}
		return $key;
	}

	private function decode_json_fields(array $item) {
		foreach (["get_json", "post_json", "files_json"] as $field) {
			$decoded = json_decode((string) ($item[$field] ?? ""), true);
			$item[$field . "_decoded"] = is_array($decoded) ? $decoded : null;
		}
		return $item;
	}

	private function respond_json(array $payload) {
		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}

	private function respond_error($status, $code, $message) {
		http_response_code((int) $status);
		$this->respond_json([
			"ok" => false,
			"error_code" => $code,
			"error" => $message,
		]);
	}
}
