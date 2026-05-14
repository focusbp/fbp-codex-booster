<?php

class screen_debug_log {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function capture(Controller $ctl) {
		$screen_key = trim((string) ($ctl->POST("screen_key") ?? $ctl->GET("screen_key") ?? ""));
		$context = $ctl->get_session("screen_debug_context");
		if (!is_array($context)
			|| $screen_key === ""
			|| (string) ($context["screen_key"] ?? "") !== $screen_key) {
			$ctl->show_notification_text($ctl->t("screen_debug_log.context_not_found"), 4, "#B42318", "#FFF", 16, 520);
			return;
		}

		$row = [
			"public_code" => $this->generate_public_code(),
			"screen_key" => (string) ($context["screen_key"] ?? ""),
			"appcode" => (string) ($context["appcode"] ?? ""),
			"app_class" => (string) ($context["app_class"] ?? ""),
			"app_function" => (string) ($context["app_function"] ?? ""),
			"template" => (string) ($context["template"] ?? ""),
			"dialog_id" => (string) ($context["dialog_id"] ?? ""),
			"url" => (string) ($context["url"] ?? ""),
			"get_json" => $this->to_json($context["get"] ?? []),
			"post_json" => $this->to_json($context["post"] ?? []),
			"files_json" => $this->to_json($context["files"] ?? []),
			"user_id" => (string) ($context["user_id"] ?? ""),
			"user_name" => (string) ($context["user_name"] ?? ""),
			"user_agent" => (string) ($context["user_agent"] ?? ""),
			"created_at" => date("Y-m-d H:i:s"),
		];
		$row["id"] = (int) $ctl->db("screen_debug_log", "screen_debug_log")->insert($row);

		$ctl->set_response_value("screen_debug_log_captured", [
			"screen_key" => $screen_key,
			"code" => $this->format_display_code($row["appcode"], $row["public_code"]),
			"label" => $ctl->t("screen_debug_log.screen_id"),
			"copy_title" => $ctl->t("screen_debug_log.copy_screen_id"),
		]);
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

	private function generate_public_code() {
		$chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
		$parts = [];
		for ($p = 0; $p < 2; $p++) {
			$s = "";
			for ($i = 0; $i < 4; $i++) {
				$s .= $chars[random_int(0, strlen($chars) - 1)];
			}
			$parts[] = $s;
		}
		return "SD-" . implode("-", $parts);
	}

	private function format_display_code($appcode, $public_code) {
		return "[ScreenLog:" . $this->normalize_appcode($appcode) . ":" . (string) $public_code . "]";
	}

	private function normalize_appcode($appcode) {
		$appcode = trim((string) $appcode);
		if ($appcode !== "" && strpos($appcode, "app-") !== 0) {
			return "app-" . $appcode;
		}
		return $appcode;
	}

	private function normalize_lookup_key($key) {
		$key = trim((string) $key);
		if (preg_match('/^\[ScreenLog:([^:\]]+):(SD-[A-Z0-9]{4}-[A-Z0-9]{4})\]$/', $key, $matches)) {
			return $matches[2];
		}
		return $key;
	}

	private function to_json($value) {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false) {
			return "";
		}
		return $json;
	}

	private function files_meta($files) {
		if (!is_array($files)) {
			return [];
		}
		$out = [];
		foreach ($files as $key => $file) {
			$out[$key] = $this->file_meta_item($file);
		}
		return $out;
	}

	private function file_meta_item($file) {
		if (!is_array($file)) {
			return [];
		}
		if (isset($file["name"]) && is_array($file["name"])) {
			$out = [];
			foreach ($file["name"] as $idx => $name) {
				$out[$idx] = [
					"name" => $name,
					"type" => $file["type"][$idx] ?? "",
					"size" => $file["size"][$idx] ?? 0,
					"error" => $file["error"][$idx] ?? 0,
				];
			}
			return $out;
		}
		return [
			"name" => $file["name"] ?? "",
			"type" => $file["type"] ?? "",
			"size" => $file["size"] ?? 0,
			"error" => $file["error"] ?? 0,
		];
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
