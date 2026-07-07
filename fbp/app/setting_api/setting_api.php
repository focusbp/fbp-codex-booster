<?php

require_once dirname(__FILE__) . "/../setting/setting.php";

class setting_api {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function get(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$setting_app = new setting($ctl);
		$this->respond_json([
			"ok" => true,
			"setting" => $setting_app->api_get_setting($ctl),
		]);
	}

	function update(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$payload = $this->read_json_request();
		$data = $payload["data"] ?? null;
		if (!is_array($data)) {
			$this->respond_error(400, "invalid_arguments", "data must be an object");
		}

		$setting_app = new setting($ctl);
		$result = $setting_app->api_update_setting($ctl, $data);
		$this->respond_json(array_merge([
			"ok" => true,
		], $result));
	}

	private function read_json_request(): array {
		$raw = file_get_contents("php://input");
		if (!is_string($raw) || trim($raw) === "") {
			$this->respond_error(400, "invalid_arguments", "json body is required");
		}

		$payload = json_decode($raw, true);
		if (!is_array($payload)) {
			$this->respond_error(400, "invalid_arguments", "json body must be an object");
		}
		return $payload;
	}

	private function respond_json(array $payload): void {
		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	private function respond_error(int $http_code, string $error_code, string $error, array $extra = []): void {
		http_response_code($http_code);
		$payload = array_merge([
			"ok" => false,
			"error_code" => $error_code,
			"error" => $error,
		], $extra);
		$this->respond_json($payload);
	}
}
