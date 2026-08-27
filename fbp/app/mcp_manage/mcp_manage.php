<?php

class mcp_manage {
	private $ffm_server;
	private $ffm_functions;
	private $ffm_logs;
	private $ffm_tokens;

	function __construct(Controller $ctl) {
		$this->ffm_server = $ctl->db("mcp_server_config", "mcp_manage");
		$this->ffm_functions = $ctl->db("mcp_functions", "mcp_manage");
		$this->ffm_logs = $ctl->db("mcp_call_logs", "mcp_manage");
		$this->ffm_tokens = $ctl->db("mcp_oauth_tokens", "mcp_manage");
		$this->assign_options($ctl);
	}

	function page(Controller $ctl) {
		$server = $this->server($ctl);
		$items = $this->ffm_functions->getall("sort", SORT_ASC);
		foreach ($items as &$item) {
			$name = (string) ($item["function_name"] ?? "");
			$item["class_name"] = McpFunctionLoader::validateName($name) ? McpFunctionLoader::className($name) : "";
			$item["ready_status"] = $this->function_ready_status($ctl, $item);
		}
		unset($item);
		$ctl->assign("server", $server);
		$ctl->assign("items", $items);
		$ctl->assign("mcp_endpoint_url", $ctl->get_APP_URL("mcp_server", "rpc"));
		$ctl->assign("mcp_authorize_url", $ctl->get_APP_URL("mcp_server", "authorize"));
		$ctl->assign("mcp_token_url", $ctl->get_APP_URL("mcp_server", "token"));
		$ctl->assign("mcp_resource_metadata_url", $ctl->get_APP_URL("mcp_server", "oauth_protected_resource"));
		$ctl->reload_area("#tabs-mcp-server", "index.tpl");
	}

	function edit_server(Controller $ctl) {
		$ctl->assign("server", $this->server($ctl));
		$ctl->show_multi_dialog("mcp_server_edit", "server_edit.tpl", $ctl->t("mcp_manage.dialog.server_edit"), 820, true, true);
	}

	function edit_server_exe(Controller $ctl) {
		$server = $this->server($ctl);
		$post = $this->normalize_server_post($ctl->POST(), $server);
		$errors = $this->validate_server($ctl, $post);
		if (count($errors) > 0) {
			$this->respond_errors($ctl, $errors);
			return;
		}
		$post["id"] = (int) $server["id"];
		$post["server_key"] = (string) ($server["server_key"] ?? "default");
		$post["sort"] = (int) ($server["sort"] ?? 0);
		$post["created_at"] = (int) ($server["created_at"] ?? time());
		$post["updated_at"] = time();
		$this->ffm_server->update($post);
		$ctl->close_multi_dialog("mcp_server_edit");
		$this->page($ctl);
	}

	function add_function(Controller $ctl) {
		$this->assign_function_form($ctl, [
			"id" => 0, "enabled" => 1, "required_scope" => "mcp.read",
			"read_only" => 1, "requires_confirmation" => 0, "destructive" => 0,
		]);
		$ctl->show_multi_dialog("mcp_function_add", "function_edit.tpl", $ctl->t("mcp_manage.dialog.function_add"), 820, true, true);
	}

	function edit_function(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$item = $this->ffm_functions->get($id);
		if (!is_array($item) || empty($item["id"])) return;
		$this->assign_function_form($ctl, $item);
		$ctl->show_multi_dialog("mcp_function_edit_" . $id, "function_edit.tpl", $ctl->t("mcp_manage.dialog.function_edit"), 820, true, true);
	}

	function save_function(Controller $ctl) {
		$post = $this->normalize_function_post($ctl->POST());
		$errors = $this->validate_function($ctl, $post);
		if (count($errors) > 0) {
			$this->respond_errors($ctl, $errors);
			return;
		}
		$now = time();
		$id = (int) $post["id"];
		if ($id > 0) {
			$current = $this->ffm_functions->get($id);
			if (!is_array($current) || empty($current["id"])) return;
			$post["sort"] = (int) ($current["sort"] ?? 0);
			$post["created_at"] = (int) ($current["created_at"] ?? $now);
			$post["updated_at"] = $now;
			$this->ffm_functions->update($post);
			$ctl->close_multi_dialog("mcp_function_edit_" . $id);
		} else {
			unset($post["id"]);
			$post["sort"] = $this->next_function_sort();
			$post["created_at"] = $now;
			$post["updated_at"] = $now;
			$this->ffm_functions->insert($post);
			$ctl->close_multi_dialog("mcp_function_add");
		}
		$this->page($ctl);
	}

	function delete_function(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$item = $this->ffm_functions->get($id);
		if (!is_array($item) || empty($item["id"])) return;
		$ctl->assign("data", $item);
		$ctl->show_multi_dialog("mcp_function_delete_" . $id, "function_delete.tpl", $ctl->t("mcp_manage.dialog.function_delete"), 520, true, true);
	}

	function delete_function_exe(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		if ($id > 0) $this->ffm_functions->delete($id);
		$ctl->close_multi_dialog("mcp_function_delete_" . $id);
		$this->page($ctl);
	}

	function sort_functions(Controller $ctl) {
		$sort = 0;
		foreach (explode(",", (string) ($ctl->POST("log") ?? "")) as $id) {
			$item = $this->ffm_functions->get((int) $id);
			if (!is_array($item) || empty($item["id"])) continue;
			$item["sort"] = $sort++;
			$item["updated_at"] = time();
			$this->ffm_functions->update($item);
		}
	}

	function logs(Controller $ctl) {
		$server = $this->server($ctl);
		$ctl->assign("logs", $this->ffm_logs->select("server_id", $server["id"], true, "AND", "id", SORT_DESC, 100));
		$ctl->show_multi_dialog("mcp_call_logs", "logs.tpl", $ctl->t("mcp_manage.dialog.logs"), 1100, true, true);
	}

	function oauth_tokens(Controller $ctl) {
		$server = $this->server($ctl);
		$tokens = $this->ffm_tokens->select("server_id", $server["id"], true, "AND", "id", SORT_DESC, 100);
		$user_ffm = $ctl->db("user", "user");
		foreach ($tokens as &$token) {
			$subject_type = trim((string) ($token["subject_type"] ?? ""));
			if ($subject_type === "" && (int) ($token["user_id"] ?? 0) > 0) $subject_type = "fbp_user";
			$token["subject_type"] = $subject_type;
			$token["subject_id"] = (int) ($token["subject_id"] ?? 0) > 0 ? (int) $token["subject_id"] : (int) ($token["user_id"] ?? 0);
			$token["subject_display"] = (string) ($token["subject_label"] ?? "");
			$token["user_status_valid"] = true;
			if ($subject_type === "fbp_user") {
				$user = $user_ffm->get((int) ($token["user_id"] ?? $token["subject_id"] ?? 0));
				$token["subject_display"] = is_array($user) ? (string) ($user["name"] ?? $user["login_id"] ?? "") : "";
				$token["user_status_valid"] = is_array($user) && (int) ($user["status"] ?? 1) === 0;
			}
		}
		unset($token);
		$ctl->assign("tokens", $tokens);
		$ctl->show_multi_dialog("mcp_oauth_tokens", "oauth_tokens.tpl", $ctl->t("mcp_manage.dialog.oauth_tokens"), 1100, true, true);
	}

	function revoke_oauth_token(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$token = $this->ffm_tokens->get($id);
		if (!empty($token)) {
			$token["revoked"] = 1;
			$token["updated_at"] = time();
			$this->ffm_tokens->update($token);
			$this->notify_token_revoked($ctl, $token);
		}
		$this->oauth_tokens($ctl);
	}

	private function server(Controller $ctl): array {
		$list = $this->ffm_server->getall("sort", SORT_ASC);
		foreach ($list as $server) {
			if ((string) ($server["server_key"] ?? "") === "default") return $server;
		}
		if (count($list) > 0) return $list[0];
		$setting = $ctl->get_setting();
		$title = trim((string) ($setting["system_name"] ?? "")) ?: "FBP MCP Server";
		$now = time();
		$id = (int) $this->ffm_server->insert([
			"enabled" => 0, "server_key" => "default", "title" => $title,
			"description" => "MCP server for this FBP app.", "auth_mode" => "oauth2",
			"subject_type" => "fbp_user", "subject_provider_class" => "",
			"default_scope" => "mcp.read mcp.write", "sort" => 0,
			"created_at" => $now, "updated_at" => $now,
		]);
		return $this->ffm_server->get($id);
	}

	private function normalize_server_post(array $post, array $current): array {
		$subject_type = trim((string) ($post["subject_type"] ?? "fbp_user"));
		return [
			"enabled" => (int) ($post["enabled"] ?? 0),
			"title" => trim((string) ($post["title"] ?? "")),
			"description" => trim((string) ($post["description"] ?? "")),
			"auth_mode" => trim((string) ($post["auth_mode"] ?? "oauth2")),
			"subject_type" => $subject_type,
			"subject_provider_class" => $subject_type === "fbp_user" ? "" : trim((string) ($post["subject_provider_class"] ?? "")),
			"default_scope" => trim((string) ($post["default_scope"] ?? "")),
		];
	}

	private function validate_server(Controller $ctl, array $post): array {
		$errors = [];
		if ($post["title"] === "") $errors["title"] = $ctl->t("mcp_manage.validation.title_required");
		if (!in_array($post["auth_mode"], ["oauth2", "noauth"], true)) $errors["auth_mode"] = $ctl->t("mcp_manage.validation.auth_mode");
		if (!in_array($post["subject_type"], ["fbp_user", "custom"], true)) $errors["subject_type"] = $ctl->t("mcp_manage.validation.subject_type");
		if ($post["subject_type"] === "custom") {
			$error = $this->validate_subject_provider_class($ctl, $post["subject_provider_class"]);
			if ($error !== "") $errors["subject_provider_class"] = $error;
		}
		return $errors;
	}

	private function assign_function_form(Controller $ctl, array $item): void {
		$name = (string) ($item["function_name"] ?? "");
		$item["class_name"] = McpFunctionLoader::validateName($name) ? McpFunctionLoader::className($name) : "";
		$ctl->assign("data", $item);
		$ctl->assign("function_form_id", "mcp_function_form_" . ((int) ($item["id"] ?? 0) ?: "new"));
	}

	private function normalize_function_post(array $post): array {
		return [
			"id" => (int) ($post["id"] ?? 0), "enabled" => (int) ($post["enabled"] ?? 0),
			"function_name" => trim((string) ($post["function_name"] ?? "")),
			"title" => trim((string) ($post["title"] ?? "")),
			"description" => trim((string) ($post["description"] ?? "")),
			"required_scope" => trim((string) ($post["required_scope"] ?? "")),
			"requires_confirmation" => (int) ($post["requires_confirmation"] ?? 0),
			"read_only" => (int) ($post["read_only"] ?? 0),
			"destructive" => (int) ($post["destructive"] ?? 0),
			"handler_config" => trim((string) ($post["handler_config"] ?? "")),
		];
	}

	private function validate_function(Controller $ctl, array $post): array {
		$errors = [];
		$name = $post["function_name"];
		if (!McpFunctionLoader::validateName($name)) $errors["function_name"] = $ctl->t("mcp_manage.validation.function_name");
		foreach ($this->ffm_functions->select("function_name", $name) as $item) {
			if ((int) ($item["id"] ?? 0) !== (int) $post["id"]) {
				$errors["function_name"] = $ctl->t("mcp_manage.validation.function_name_duplicate");
				break;
			}
		}
		if ($post["title"] === "") $errors["title"] = $ctl->t("mcp_manage.validation.title_required");
		if ($post["handler_config"] !== "" && json_decode($post["handler_config"], true) === null && json_last_error() !== JSON_ERROR_NONE) {
			$errors["handler_config"] = $ctl->t("mcp_manage.validation.handler_config");
		}
		if (!isset($errors["function_name"])) {
			try { McpFunctionLoader::load($name, $ctl); } catch (Throwable $e) { $errors["function_name"] = $e->getMessage(); }
		}
		return $errors;
	}

	private function function_ready_status(Controller $ctl, array $item): string {
		if ((int) ($item["enabled"] ?? 0) !== 1) return "disabled";
		try {
			McpFunctionLoader::load((string) ($item["function_name"] ?? ""), $ctl);
			return "ready";
		} catch (Throwable $e) {
			return $e->getMessage();
		}
	}

	private function next_function_sort(): int {
		$list = $this->ffm_functions->getall("sort", SORT_DESC);
		return count($list) === 0 ? 0 : (int) ($list[0]["sort"] ?? 0) + 1;
	}

	private function validate_subject_provider_class(Controller $ctl, string $class): string {
		if ($class === "") return $ctl->t("mcp_manage.validation.subject_provider_class_required");
		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) return $ctl->t("mcp_manage.validation.subject_provider_class");
		try { $this->load_subject_provider($class, $ctl); } catch (Throwable $e) { return $e->getMessage(); }
		return "";
	}

	private function load_subject_provider(string $class, Controller $ctl): McpSubjectProviderInterface {
		if (!class_exists($class, false)) {
			try {
				$dir = new Dirs();
				include_once($dir->get_class_dir($class) . "/" . $class . ".php");
			} catch (Throwable $e) {
				throw new Exception($ctl->t("mcp_manage.validation.subject_provider_class_not_found"));
			}
		}
		if (!class_exists($class, false)) throw new Exception($ctl->t("mcp_manage.validation.subject_provider_class_not_found"));
		$reflection = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();
		$provider = $constructor && count($constructor->getParameters()) > 0 ? new $class($ctl) : new $class();
		if (!($provider instanceof McpSubjectProviderInterface)) throw new Exception($ctl->t("mcp_manage.validation.subject_provider_class_interface"));
		return $provider;
	}

	private function notify_token_revoked(Controller $ctl, array $token): void {
		$server = $this->ffm_server->get((int) ($token["server_id"] ?? 0));
		if (!is_array($server)) return;
		$type = trim((string) ($token["subject_type"] ?? ""));
		if ($type === "" && (int) ($token["user_id"] ?? 0) > 0) $type = "fbp_user";
		$id = (int) ($token["subject_id"] ?? 0);
		if ($id <= 0 && $type === "fbp_user") $id = (int) ($token["user_id"] ?? 0);
		if ($type === "" || $id <= 0) return;
		$subject = new McpSubject($type, $id, (string) ($token["subject_label"] ?? ""), (int) ($token["user_id"] ?? 0));
		try {
			$provider = $type === "fbp_user" ? new McpFbpUserSubjectProvider() : $this->load_subject_provider((string) ($server["subject_provider_class"] ?? ""), $ctl);
			$provider->onTokenRevoked($ctl, $server, $subject, $token);
		} catch (Throwable $e) {
		}
	}

	private function assign_options(Controller $ctl): void {
		$ctl->assign("enabled_opt", [0 => $ctl->t("common.disabled"), 1 => $ctl->t("common.enabled")]);
		$ctl->assign("auth_mode_opt", ["oauth2" => "OAuth 2.0", "noauth" => "No auth"]);
		$ctl->assign("subject_type_opt", ["fbp_user" => $ctl->t("mcp_manage.subject_type.fbp_user"), "custom" => $ctl->t("mcp_manage.subject_type.custom")]);
		$ctl->assign("yes_no_opt", [0 => $ctl->t("common.no"), 1 => $ctl->t("common.yes")]);
	}

	private function respond_errors(Controller $ctl, array $errors): void {
		$ctl->clear_error_message();
		foreach ($errors as $field => $message) $ctl->res_error_message($field, $message);
	}
}
