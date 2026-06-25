<?php

class mcp_manage {

	private $ffm_server;
	private $ffm_tools;
	private $ffm_fields;
	private $ffm_logs;
	private $ffm_tokens;

	function __construct(Controller $ctl) {
		$this->ffm_server = $ctl->db("mcp_server_config", "mcp_manage");
		$this->ffm_tools = $ctl->db("mcp_tools", "mcp_manage");
		$this->ffm_fields = $ctl->db("mcp_tool_fields", "mcp_manage");
		$this->ffm_logs = $ctl->db("mcp_call_logs", "mcp_manage");
		$this->ffm_tokens = $ctl->db("mcp_oauth_tokens", "mcp_manage");
		$this->assign_options($ctl);
	}

	function page(Controller $ctl) {
		$server = $this->selected_server($ctl);
			$tools = $this->ffm_tools->select("server_id", $server["id"], true, "AND", "sort", SORT_ASC);
			foreach ($tools as &$tool) {
				$tool["ready_status"] = $this->tool_ready_status($ctl, $tool);
				$tool["field_summary"] = $this->field_summary((int) $tool["id"]);
			}
		unset($tool);

		$ctl->assign("servers", $this->server_options($ctl));
		$ctl->assign("server", $server);
		$ctl->assign("items", $tools);
		$ctl->assign("mcp_endpoint_url", $this->mcp_url($ctl, "rpc", $server));
		$ctl->assign("mcp_authorize_url", $this->mcp_url($ctl, "authorize", $server));
		$ctl->assign("mcp_token_url", $ctl->get_APP_URL("mcp_server", "token"));
		$ctl->assign("mcp_resource_metadata_url", $this->mcp_url($ctl, "oauth_protected_resource", $server));
		$ctl->reload_area("#tabs-mcp-server", "index.tpl");
	}

	private function mcp_url(Controller $ctl, string $function, array $server): string {
		$params = [];
		$server_key = (string) ($server["server_key"] ?? "default");
		if ($server_key !== "" && $server_key !== "default") {
			$params["server"] = $server_key;
		}
		return $ctl->get_APP_URL("mcp_server", $function, $params);
	}

	function edit_server(Controller $ctl) {
		$server = $this->selected_server($ctl);
		$ctl->assign("server", $server);
		$ctl->show_multi_dialog("mcp_server_edit", "server_edit.tpl", $ctl->t("mcp_manage.dialog.server_edit"), 820, true, true);
	}

	function add_server(Controller $ctl) {
		$ctl->assign("server", [
			"id" => 0,
			"enabled" => 0,
			"server_key" => "",
			"title" => "",
			"description" => "MCP server for this FBP app.",
			"auth_mode" => "oauth2",
			"default_scope" => "mcp.read mcp.write",
		]);
		$ctl->show_multi_dialog("mcp_server_add", "server_edit.tpl", $ctl->t("mcp_manage.dialog.server_edit"), 820, true, true);
	}

	function edit_server_exe(Controller $ctl) {
		$post = $this->normalize_server_post($ctl->POST());
		$id = (int) ($post["id"] ?? 0);
		$server = $id > 0 ? $this->ffm_server->get($id) : [];
		$errors = $this->validate_server($ctl, $post, $id);
		if (count($errors) > 0) {
			$this->respond_errors($ctl, $errors);
			return;
		}

		if (!is_array($server)) {
			$server = [];
		}
		$server["enabled"] = $post["enabled"];
		$server["server_key"] = $post["server_key"];
		$server["title"] = $post["title"];
		$server["description"] = $post["description"];
		$server["auth_mode"] = $post["auth_mode"];
		$server["default_scope"] = $post["default_scope"];
		$server["updated_at"] = time();
		if ($id > 0) {
			$this->ffm_server->update($server);
		} else {
			$server["sort"] = $this->next_server_sort();
			$server["created_at"] = time();
			$id = (int) $this->ffm_server->insert($server);
		}

		$ctl->close_multi_dialog("mcp_server_edit");
		$ctl->close_multi_dialog("mcp_server_add");
		$_POST["server_id"] = $id;
		$this->page($ctl);
	}

	function add_tool(Controller $ctl) {
		$server = $this->selected_server($ctl);
		$ctl->assign("server", $server);
		$ctl->show_multi_dialog("mcp_tool_add", "tool_type_select.tpl", $ctl->t("mcp_manage.dialog.tool_add"), 620, true, true);
	}

	function add_tool_form(Controller $ctl) {
		$server = $this->selected_server($ctl);
		$tool_type = $this->normalize_tool_type((string) ($ctl->POST("tool_type") ?? "note_crud"));
		if ($tool_type === "") {
			$ctl->show_notification_text($ctl->t("mcp_manage.validation.tool_type"));
			return;
		}
		$post = [
			"server_id" => $server["id"],
			"enabled" => 1,
			"tool_type" => $tool_type,
		];
		if ($tool_type === "note_crud") {
			$post["operation"] = "list";
			$post["required_scope"] = $this->scope_for_operation("list");
			$post["description"] = $this->description_for_operation("", "list");
			$post["max_limit"] = 20;
		} else {
			$post["operation"] = "action";
			$post["required_scope"] = "mcp.read";
			$post["read_only"] = 1;
			$post["max_limit"] = 20;
		}
		$this->assign_tool_form($ctl, $post);
		$ctl->show_multi_dialog("mcp_tool_add", "tool_edit.tpl", $ctl->t("mcp_manage.dialog.tool_add"), 980, true, true);
	}

	function add_tool_exe(Controller $ctl) {
		$server = $this->selected_server($ctl);
		$post = $this->normalize_tool_post($ctl->POST(), true);
		$post["server_id"] = (int) $server["id"];
		$errors = $this->validate_tool($ctl, $post, "add");
		if (count($errors) > 0) {
			$this->respond_errors($ctl, $errors);
			return;
		}

		$post["sort"] = $this->next_tool_sort((int) $server["id"]);
		$now = time();
		$post["created_at"] = $now;
		$post["updated_at"] = $now;
			$id = (int) $this->ffm_tools->insert($post);
			if ((string) ($post["tool_type"] ?? "") === "note_crud") {
				$this->save_tool_fields_from_post($ctl, $id, (string) $post["target_note"], $now);
			}

		$ctl->close_multi_dialog("mcp_tool_add");
		$_POST["server_id"] = (int) $server["id"];
		$this->page($ctl);
	}

	function edit_tool(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->ffm_tools->get($id);
		$this->assign_tool_form($ctl, is_array($data) ? $data : []);
		$ctl->show_multi_dialog("mcp_tool_edit_" . $id, "tool_edit.tpl", $ctl->t("mcp_manage.dialog.tool_edit"), 980, true, true);
	}

	function edit_tool_exe(Controller $ctl) {
		$raw_post = $ctl->POST();
		$id = (int) ($raw_post["id"] ?? 0);
		$data = $this->ffm_tools->get($id);
		if (empty($data)) {
			return;
		}
		$raw_post["tool_type"] = (string) ($data["tool_type"] ?? "note_crud");
		$raw_post["server_id"] = (int) ($data["server_id"] ?? 0);
		$post = $this->normalize_tool_post($raw_post, false);
		$id = (int) ($post["id"] ?? 0);
		$errors = $this->validate_tool($ctl, $post, "edit");
		if (count($errors) > 0) {
			$this->respond_errors($ctl, $errors);
			return;
		}

		foreach ([
			"enabled",
			"tool_name",
			"title",
			"description",
			"tool_type",
			"operation",
			"target_note",
			"action_class",
			"required_scope",
			"requires_confirmation",
			"read_only",
			"destructive",
			"max_limit",
		] as $key) {
			$data[$key] = $post[$key];
		}
		$now = time();
		$data["updated_at"] = $now;
		$this->ffm_tools->update($data);
		if ((string) ($post["tool_type"] ?? "") === "note_crud") {
			$this->save_tool_fields_from_post($ctl, $id, (string) $post["target_note"], $now);
		}

		$ctl->close_multi_dialog("mcp_tool_edit_" . $id);
		$_POST["server_id"] = (int) ($data["server_id"] ?? 0);
		$this->page($ctl);
	}

	function delete_tool(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->ffm_tools->get($id);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("mcp_tool_delete_" . $id, "tool_delete.tpl", $ctl->t("mcp_manage.dialog.tool_delete"), 520, true, true);
	}

	function delete_tool_exe(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->ffm_tools->get($id);
		$server_id = is_array($data) ? (int) ($data["server_id"] ?? 0) : 0;
		foreach ($this->ffm_fields->select("tool_id", $id) as $field) {
			$this->ffm_fields->delete((int) $field["id"]);
		}
		$this->ffm_tools->delete($id);
		$ctl->close_multi_dialog("mcp_tool_delete_" . $id);
		$_POST["server_id"] = $server_id;
		$this->page($ctl);
	}

	function fields(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$tool = $this->ffm_tools->get($id);
		if (empty($tool)) {
			return;
		}
		$ctl->assign("tool", $tool);
		$ctl->assign("form_id", "mcp_tool_fields_form_" . $id);
		$ctl->assign("field_area_id", "mcp_tool_fields_area_dialog_" . $id);
		$ctl->assign("selected_target_note", (string) ($tool["target_note"] ?? ""));
		$ctl->assign("field_rows", $this->field_rows_for_tool($ctl, (string) ($tool["target_note"] ?? ""), $id));
		$ctl->show_multi_dialog("mcp_tool_fields_" . $id, "tool_fields.tpl", $ctl->t("mcp_manage.dialog.tool_fields"), 1100, true, true);
	}

	function fields_exe(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$tool = $this->ffm_tools->get($id);
		if (empty($tool)) {
			return;
		}
		$now = time();
		$this->save_tool_fields_from_post($ctl, $id, (string) ($tool["target_note"] ?? ""), $now);

		$tool["updated_at"] = $now;
		$this->ffm_tools->update($tool);
		$ctl->close_multi_dialog("mcp_tool_fields_" . $id);
		$_POST["server_id"] = (int) ($tool["server_id"] ?? 0);
		$this->page($ctl);
	}

	function tool_fields_area(Controller $ctl) {
		$form_key = preg_replace('/[^a-zA-Z0-9_-]/', "", (string) ($ctl->POST("form_key") ?? ""));
		if ($form_key === "") {
			$form_key = "new";
		}
		$tool_id = (int) ($ctl->POST("tool_id") ?? 0);
		$target_note = trim((string) ($ctl->POST("target_note") ?? ""));
		$field_rows = $this->field_rows_for_tool($ctl, $target_note, $tool_id);

		$ctl->assign("selected_target_note", $target_note);
		$ctl->assign("field_rows", $field_rows);
		$ctl->reload_area("#mcp_tool_fields_area_" . $form_key, $ctl->fetch("_tool_fields_matrix.tpl"));
	}

	function logs(Controller $ctl) {
		$server = $this->selected_server($ctl);
		$logs = $this->ffm_logs->select("server_id", $server["id"], true, "AND", "id", SORT_DESC, 100);
		$ctl->assign("logs", $logs);
		$ctl->show_multi_dialog("mcp_call_logs", "logs.tpl", $ctl->t("mcp_manage.dialog.logs"), 1100, true, true);
	}

	function oauth_tokens(Controller $ctl) {
		$server = $this->selected_server($ctl);
		$tokens = $this->ffm_tokens->select("server_id", $server["id"], true, "AND", "id", SORT_DESC, 100);
		$user_ffm = $ctl->db("user", "user");
		foreach ($tokens as &$token) {
			$user = $user_ffm->get((int) ($token["user_id"] ?? 0));
			$token["user_name"] = is_array($user) ? (string) ($user["name"] ?? $user["login_id"] ?? "") : "";
			$token["user_status_valid"] = is_array($user) && (int) ($user["status"] ?? 1) === 0;
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
			$_POST["server_id"] = (int) ($token["server_id"] ?? 0);
		}
		$this->oauth_tokens($ctl);
	}

	function sort(Controller $ctl) {
		$logArr = explode(',', (string) ($ctl->POST("log") ?? ""));
		$c = 0;
		foreach ($logArr as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$d = $this->ffm_tools->get($id);
			if (empty($d)) {
				continue;
			}
			$d["sort"] = $c;
			$d["updated_at"] = time();
			$this->ffm_tools->update($d);
			$c++;
		}
	}

	private function assign_options(Controller $ctl): void {
		$ctl->assign("enabled_opt", [
			0 => $ctl->t("common.disabled"),
			1 => $ctl->t("common.enabled"),
		]);
		$ctl->assign("auth_mode_opt", [
			"oauth2" => "OAuth 2.0",
			"noauth" => "No auth",
		]);
			$ctl->assign("tool_type_opt", [
				"note_crud" => $ctl->t("mcp_manage.note_crud"),
				"app_action" => $ctl->t("mcp_manage.app_action"),
			]);
		$ctl->assign("operation_opt", [
			"list" => "list",
			"get" => "get",
			"create" => "create",
			"update" => "update",
			"delete" => "delete",
		]);
		$ctl->assign("yes_no_opt", [
			0 => $ctl->t("common.no"),
			1 => $ctl->t("common.yes"),
		]);
	}

	private function ensure_default_server(Controller $ctl): array {
		$list = $this->ffm_server->getall("sort", SORT_ASC);
		if (count($list) > 0) {
			foreach ($list as $server) {
				if ((string) ($server["server_key"] ?? "") === "default") {
					return $server;
				}
			}
			return $list[0];
		}
		$setting = $ctl->get_setting();
		$title = trim((string) ($setting["system_name"] ?? ""));
		if ($title === "") {
			$title = "FBP MCP Server";
		}
		$row = [
			"enabled" => 0,
			"server_key" => "default",
			"title" => $title,
			"description" => "MCP server for this FBP app.",
			"auth_mode" => "oauth2",
			"default_scope" => "mcp.read mcp.write",
			"sort" => 0,
			"created_at" => time(),
			"updated_at" => time(),
		];
		$id = $this->ffm_server->insert($row);
		return $this->ffm_server->get((int) $id);
	}

	private function selected_server(Controller $ctl): array {
		$id = (int) ($ctl->POST("server_id") ?? ($ctl->GET("server_id") ?? 0));
		if ($id > 0) {
			$server = $this->ffm_server->get($id);
			if (is_array($server) && !empty($server["id"])) {
				return $server;
			}
		}
		$server_key = trim((string) ($ctl->POST("server") ?? ($ctl->POST("server_key") ?? ($ctl->GET("server") ?? ($ctl->GET("server_key") ?? "")))));
		if ($server_key !== "") {
			foreach ($this->ffm_server->select("server_key", $server_key) as $server) {
				return $server;
			}
		}
		return $this->ensure_default_server($ctl);
	}

	private function server_options(Controller $ctl): array {
		$options = [];
		foreach ($this->ffm_server->getall("sort", SORT_ASC) as $server) {
			$id = (int) ($server["id"] ?? 0);
			if ($id <= 0) {
				continue;
			}
			$key = (string) ($server["server_key"] ?? "");
			$title = (string) ($server["title"] ?? "");
			$options[$id] = ($key !== "" ? $key : "server-" . $id) . ($title !== "" ? " / " . $title : "");
		}
		if (count($options) === 0) {
			$server = $this->ensure_default_server($ctl);
			$options[(int) $server["id"]] = (string) ($server["server_key"] ?? "default") . " / " . (string) ($server["title"] ?? "");
		}
		return $options;
	}

	private function next_server_sort(): int {
		$list = $this->ffm_server->getall("sort", SORT_DESC);
		if (count($list) === 0) {
			return 0;
		}
		return (int) ($list[0]["sort"] ?? 0) + 1;
	}

	private function normalize_server_post(array $post): array {
		return [
			"id" => (int) ($post["id"] ?? 0),
			"enabled" => isset($post["enabled"]) ? (int) $post["enabled"] : 0,
			"server_key" => trim((string) ($post["server_key"] ?? "default")),
			"title" => trim((string) ($post["title"] ?? "")),
			"description" => trim((string) ($post["description"] ?? "")),
			"auth_mode" => trim((string) ($post["auth_mode"] ?? "oauth2")),
			"default_scope" => trim((string) ($post["default_scope"] ?? "")),
		];
	}

	private function validate_server(Controller $ctl, array $post, int $current_id = 0): array {
		$errors = [];
		if ($post["server_key"] === "" || !preg_match('/^[a-zA-Z0-9_-]+$/', $post["server_key"])) {
			$errors["server_key"] = $ctl->t("mcp_manage.validation.server_key");
		}
		foreach ($this->ffm_server->select("server_key", $post["server_key"]) as $server) {
			if ((int) ($server["id"] ?? 0) !== $current_id) {
				$errors["server_key"] = $ctl->t("mcp_manage.validation.server_key");
				break;
			}
		}
		if ($post["title"] === "") {
			$errors["title"] = $ctl->t("mcp_manage.validation.title_required");
		}
		if (!in_array($post["auth_mode"], ["oauth2", "noauth"], true)) {
			$errors["auth_mode"] = $ctl->t("mcp_manage.validation.auth_mode");
		}
		return $errors;
	}

	private function normalize_tool_type(string $tool_type): string {
		$tool_type = trim($tool_type);
		if ($tool_type === "custom_action") {
			$tool_type = "app_action";
		}
		return in_array($tool_type, ["note_crud", "app_action"], true) ? $tool_type : "";
	}

	private function normalize_tool_post(array $post, bool $auto_required_scope): array {
		$tool_type = $this->normalize_tool_type((string) ($post["tool_type"] ?? "note_crud"));
		if ($tool_type === "app_action") {
			return $this->normalize_app_action_post($post);
		}

		$operation = trim((string) ($post["operation"] ?? "list"));
		$read_only = in_array($operation, ["list", "get"], true) ? 1 : 0;
		$destructive = $operation === "delete" ? 1 : (int) ($post["destructive"] ?? 0);
		$target_note = trim((string) ($post["target_note"] ?? ""));
		$required_scope = $auto_required_scope
			? $this->scope_for_operation($operation)
			: trim((string) ($post["required_scope"] ?? ""));
		$description = $auto_required_scope
			? $this->description_for_operation($target_note, $operation)
			: trim((string) ($post["description"] ?? ""));
		return [
			"id" => (int) ($post["id"] ?? 0),
			"server_id" => (int) ($post["server_id"] ?? 0),
			"enabled" => isset($post["enabled"]) ? (int) $post["enabled"] : 0,
				"tool_name" => $this->auto_tool_name($target_note, $operation),
				"title" => $this->auto_tool_title($target_note, $operation),
				"description" => $description,
				"tool_type" => "note_crud",
				"operation" => $operation,
				"target_note" => $target_note,
				"action_class" => "",
				"required_scope" => $required_scope,
				"requires_confirmation" => $operation === "delete" ? 1 : (int) ($post["requires_confirmation"] ?? 0),
				"read_only" => $read_only,
			"destructive" => $destructive,
			"max_limit" => max(1, min(200, (int) ($post["max_limit"] ?? 20))),
			];
		}

	private function normalize_app_action_post(array $post): array {
		$tool_name = trim((string) ($post["tool_name"] ?? ""));
		$title = trim((string) ($post["title"] ?? ""));
		if ($title === "") {
			$title = $tool_name;
		}
		return [
			"id" => (int) ($post["id"] ?? 0),
			"server_id" => (int) ($post["server_id"] ?? 0),
			"enabled" => isset($post["enabled"]) ? (int) $post["enabled"] : 0,
			"tool_name" => $tool_name,
			"title" => $title,
			"description" => trim((string) ($post["description"] ?? "")),
			"tool_type" => "app_action",
			"operation" => "action",
			"target_note" => "",
			"action_class" => trim((string) ($post["action_class"] ?? "")),
			"required_scope" => trim((string) ($post["required_scope"] ?? "mcp.read")),
			"requires_confirmation" => (int) ($post["requires_confirmation"] ?? 0),
			"read_only" => (int) ($post["read_only"] ?? 1),
			"destructive" => (int) ($post["destructive"] ?? 0),
			"max_limit" => 20,
		];
	}

	private function validate_tool(Controller $ctl, array $post, string $mode): array {
		$errors = [];
		$id = (int) ($post["id"] ?? 0);
		if ($post["tool_name"] === "" || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $post["tool_name"])) {
			$errors["tool_name"] = $ctl->t("mcp_manage.validation.tool_name");
		}
		foreach ($this->ffm_tools->select("server_id", (int) ($post["server_id"] ?? 0)) as $tool) {
			if ((int) ($tool["id"] ?? 0) === ($mode === "edit" ? $id : 0)) {
				continue;
			}
			if ((string) ($tool["tool_name"] ?? "") === (string) $post["tool_name"]) {
				$errors["tool_name"] = $ctl->t("mcp_manage.validation.tool_name_exists");
				break;
			}
		}
		if ($post["title"] === "") {
			$errors["title"] = $ctl->t("mcp_manage.validation.title_required");
		}
		if (!in_array($post["tool_type"], ["note_crud", "app_action"], true)) {
			$errors["tool_type"] = $ctl->t("mcp_manage.validation.tool_type");
		}
		if ($post["tool_type"] === "note_crud" && !in_array($post["operation"], ["list", "get", "create", "update", "delete"], true)) {
			$errors["operation"] = $ctl->t("mcp_manage.validation.operation");
		}
		if ($post["tool_type"] === "note_crud" && $post["target_note"] === "") {
			$errors["target_note"] = $ctl->t("mcp_manage.validation.target_note_required");
		}
		if ($post["tool_type"] === "app_action") {
			$action_error = $this->validate_action_class($ctl, (string) ($post["action_class"] ?? ""));
			if ($action_error !== "") {
				$errors["action_class"] = $action_error;
			}
		}
		return $errors;
	}

	private function next_tool_sort(int $server_id): int {
		$list = $this->ffm_tools->select("server_id", $server_id, true, "AND", "sort", SORT_DESC);
		if (count($list) === 0) {
			return 0;
		}
		return (int) ($list[0]["sort"] ?? 0) + 1;
	}

	private function assign_tool_form(Controller $ctl, array $data): void {
		$id = (int) ($data["id"] ?? 0);
		$form_key = $id > 0 ? (string) $id : "new";
		$data["tool_type"] = $this->normalize_tool_type((string) ($data["tool_type"] ?? "note_crud"));
		if ($data["tool_type"] === "") {
			$data["tool_type"] = "note_crud";
		}
		$target_note = trim((string) ($data["target_note"] ?? ""));
		if ($data["tool_type"] === "note_crud") {
			$operation = trim((string) ($data["operation"] ?? "list"));
			$data["operation"] = $operation === "" ? "list" : $operation;
			$data["tool_name"] = $this->auto_tool_name($target_note, (string) $data["operation"]);
			$data["title"] = $this->auto_tool_title($target_note, (string) $data["operation"]);
			$data["action_class"] = "";
			if ($id <= 0 && trim((string) ($data["required_scope"] ?? "")) === "") {
				$data["required_scope"] = $this->scope_for_operation((string) $data["operation"]);
			}
			if ($id <= 0 && trim((string) ($data["description"] ?? "")) === "") {
				$data["description"] = $this->description_for_operation($target_note, (string) $data["operation"]);
			}
		} else {
			$data["operation"] = "action";
			$data["target_note"] = "";
			$data["max_limit"] = 20;
			if (trim((string) ($data["required_scope"] ?? "")) === "") {
				$data["required_scope"] = "mcp.read";
			}
			if (!isset($data["read_only"]) || $data["read_only"] === "") {
				$data["read_only"] = 1;
			}
		}

		$ctl->assign("data", $data);
		$ctl->assign("form_key", $form_key);
		$ctl->assign("form_id", "mcp_tool_edit_form_" . $form_key);
		$ctl->assign("field_area_id", "mcp_tool_fields_area_" . $form_key);
		$ctl->assign("selected_target_note", $target_note);
		$ctl->assign("field_rows", $data["tool_type"] === "note_crud" ? $this->field_rows_for_tool($ctl, $target_note, $id) : []);
		$this->assign_note_options($ctl);
	}

	private function validate_action_class(Controller $ctl, string $class): string {
		$class = trim($class);
		if ($class === "") {
			return $ctl->t("mcp_manage.validation.action_class_required");
		}
		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
			return $ctl->t("mcp_manage.validation.action_class");
		}
		try {
			$this->load_app_action($class, $ctl);
		} catch (Throwable $e) {
			return $e->getMessage();
		}
		return "";
	}

	private function load_app_action(string $class, Controller $ctl): McpActionInterface {
		if (!class_exists($class, false)) {
			try {
				$dir = new Dirs();
				$class_file = $dir->get_class_dir($class) . "/" . $class . ".php";
				include_once($class_file);
			} catch (Throwable $e) {
				throw new Exception($ctl->t("mcp_manage.validation.action_class_not_found"));
			}
		}
		if (!class_exists($class, false)) {
			throw new Exception($ctl->t("mcp_manage.validation.action_class_not_found"));
		}
		$reflection = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();
		if ($constructor && count($constructor->getParameters()) > 0) {
			$action = new $class($ctl);
		} else {
			$action = new $class();
		}
		if (!($action instanceof McpActionInterface)) {
			throw new Exception($ctl->t("mcp_manage.validation.action_class_interface"));
		}
		return $action;
	}

	private function auto_tool_name(string $target_note, string $operation): string {
		$operation = $this->safe_operation_for_name($operation);
		$note = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $target_note));
		$note = trim($note, "_");
		if ($note === "" || !preg_match('/^[a-zA-Z]/', $note)) {
			$note = "note";
		}
		if ($operation === "list" && substr($note, -1) !== "s") {
			$note .= "s";
		}
		return $operation . "_" . $note;
	}

	private function auto_tool_title(string $target_note, string $operation): string {
		$labels = [
			"list" => "List",
			"get" => "Get",
			"create" => "Create",
			"update" => "Update",
			"delete" => "Delete",
		];
		$safe_operation = $this->safe_operation_for_name($operation);
		$note = trim((string) preg_replace('/[^a-zA-Z0-9_ -]+/', ' ', $target_note));
		$note = preg_replace('/\s+/', ' ', $note);
		if ($note === "") {
			$note = "note";
		}
		if ($safe_operation === "list" && substr($note, -1) !== "s") {
			$note .= "s";
		}
		return ($labels[$safe_operation] ?? ucfirst($safe_operation)) . " " . $note;
	}

	private function safe_operation_for_name(string $operation): string {
		return in_array($operation, ["list", "get", "create", "update", "delete"], true) ? $operation : "list";
	}

	private function scope_for_operation(string $operation): string {
		return in_array($operation, ["list", "get"], true) ? "mcp.read" : "mcp.write";
	}

	private function description_for_operation(string $target_note, string $operation): string {
		$note = trim(str_replace("_", " ", $target_note));
		if ($note === "") {
			$note = "the selected note";
		}
		$safe_operation = $this->safe_operation_for_name($operation);
		$descriptions = [
			"list" => "Use this to list or search records in " . $note . ".",
			"get" => "Use this to retrieve one record from " . $note . ".",
			"create" => "Use this to create a record in " . $note . ".",
			"update" => "Use this to update a record in " . $note . ".",
			"delete" => "Use this to delete a record from " . $note . ".",
		];
		return $descriptions[$safe_operation];
	}

	private function assign_note_options(Controller $ctl): void {
		$options = ["" => ""];
		foreach ($ctl->db("db", "db")->getall("sort", SORT_ASC) as $db) {
			$tb_name = (string) ($db["tb_name"] ?? "");
			if ($tb_name === "") {
				continue;
			}
			$label = trim((string) ($db["menu_name"] ?? ""));
			$options[$tb_name] = $label === "" ? $tb_name : $label . " (" . $tb_name . ")";
		}
		$ctl->assign("note_options", $options);
	}

	private function note_field_rows(Controller $ctl, string $tb_name): array {
		if ($tb_name === "") {
			return [];
		}
		$db_list = $ctl->db("db", "db")->select("tb_name", $tb_name);
		if (count($db_list) === 0) {
			return [];
		}
		$db_id = (int) ($db_list[0]["id"] ?? 0);
		return $ctl->db("db_fields", "db")->select("db_id", $db_id, true, "AND", "sort", SORT_ASC);
	}

	private function field_rows_for_tool(Controller $ctl, string $target_note, int $tool_id): array {
		$field_rows = $this->note_field_rows($ctl, $target_note);
		$selected = $tool_id > 0 ? $this->selected_fields_by_role($tool_id) : [
			"input" => [],
			"output" => [],
			"search" => [],
			"required" => [],
		];
		foreach ($field_rows as &$field) {
			$name = (string) ($field["parameter_name"] ?? "");
			$field["mcp_input"] = isset($selected["input"][$name]) ? 1 : 0;
			$field["mcp_output"] = isset($selected["output"][$name]) ? 1 : 0;
			$field["mcp_search"] = isset($selected["search"][$name]) ? 1 : 0;
			$field["mcp_required"] = isset($selected["required"][$name]) ? 1 : 0;
		}
		unset($field);
		return $field_rows;
	}

	private function valid_note_field_map(Controller $ctl, string $tb_name): array {
		$valid = [];
		foreach ($this->note_field_rows($ctl, $tb_name) as $field) {
			$name = (string) ($field["parameter_name"] ?? "");
			if ($name !== "") {
				$valid[$name] = true;
			}
		}
		return $valid;
	}

	private function filter_valid_fields(array $field_names, array $valid_fields): array {
		$result = [];
		foreach ($field_names as $field_name) {
			if (isset($valid_fields[$field_name])) {
				$result[] = $field_name;
			}
		}
		return $result;
	}

	private function selected_fields_by_role(int $tool_id): array {
		$selected = [
			"input" => [],
			"output" => [],
			"search" => [],
			"required" => [],
		];
		foreach ($this->ffm_fields->select("tool_id", $tool_id, true, "AND", "sort", SORT_ASC) as $field) {
			$name = (string) ($field["field_name"] ?? "");
			$role = (string) ($field["role"] ?? "");
			if ($name === "" || !isset($selected[$role])) {
				continue;
			}
			$selected[$role][$name] = true;
			if ($role === "input" && (int) ($field["required"] ?? 0) === 1) {
				$selected["required"][$name] = true;
			}
		}
		return $selected;
	}

	private function normalize_post_array($value): array {
		if (!is_array($value)) {
			return [];
		}
		$result = [];
		foreach ($value as $v) {
			$v = trim((string) $v);
			if ($v !== "" && !in_array($v, $result, true)) {
				$result[] = $v;
			}
		}
		return $result;
	}

	private function insert_role_fields(array $field_names, string $role, array $required_fields, int $now, int $tool_id): void {
		$sort = 0;
		foreach ($field_names as $field_name) {
			$row = [
				"tool_id" => $tool_id,
				"field_name" => $field_name,
				"role" => $role,
				"required" => isset($required_fields[$field_name]) ? 1 : 0,
				"readonly" => 0,
				"sort" => $sort,
				"created_at" => $now,
				"updated_at" => $now,
			];
			$this->ffm_fields->insert($row);
			$sort++;
		}
	}

	private function save_tool_fields_from_post(Controller $ctl, int $tool_id, string $target_note, int $now): void {
		if ($tool_id <= 0) {
			return;
		}
		foreach ($this->ffm_fields->select("tool_id", $tool_id) as $field) {
			$this->ffm_fields->delete((int) $field["id"]);
		}

		$valid_fields = $this->valid_note_field_map($ctl, $target_note);
		$input_fields = $this->filter_valid_fields($this->normalize_post_array($ctl->POST("input_fields")), $valid_fields);
		$output_fields = $this->filter_valid_fields($this->normalize_post_array($ctl->POST("output_fields")), $valid_fields);
		$search_fields = $this->filter_valid_fields($this->normalize_post_array($ctl->POST("search_fields")), $valid_fields);
		$required_fields = array_flip($this->filter_valid_fields($this->normalize_post_array($ctl->POST("required_fields")), $valid_fields));

		$this->insert_role_fields($input_fields, "input", $required_fields, $now, $tool_id);
		$this->insert_role_fields($output_fields, "output", [], $now, $tool_id);
		$this->insert_role_fields($search_fields, "search", [], $now, $tool_id);
	}

	private function field_summary(int $tool_id): string {
		$tool = $this->ffm_tools->get($tool_id);
		if (is_array($tool) && (string) ($tool["tool_type"] ?? "") === "app_action") {
			return "App Action";
		}
		$count = [
			"input" => 0,
			"output" => 0,
			"search" => 0,
		];
		foreach ($this->ffm_fields->select("tool_id", $tool_id) as $field) {
			$role = (string) ($field["role"] ?? "");
			if (isset($count[$role])) {
				$count[$role]++;
			}
		}
		return "input:" . $count["input"] . " / output:" . $count["output"] . " / search:" . $count["search"];
	}

	private function tool_ready_status(Controller $ctl, array $tool): string {
		if ((int) ($tool["enabled"] ?? 0) !== 1) {
			return "disabled";
		}
		$tool_type = (string) ($tool["tool_type"] ?? "");
		if ($tool_type === "app_action") {
			$action_class = trim((string) ($tool["action_class"] ?? ""));
			if ($action_class === "") {
				return "no_action_class";
			}
			try {
				$this->load_app_action($action_class, $ctl);
			} catch (Throwable $e) {
				return "action_class_error";
			}
			return "ready";
		}
		if ($tool_type !== "note_crud") {
			return "unsupported_tool_type";
		}
		$operation = (string) ($tool["operation"] ?? "");
		$selected = $this->selected_fields_by_role((int) ($tool["id"] ?? 0));
		if (in_array($operation, ["list", "get"], true) && count($selected["output"]) === 0) {
			return "no_output_fields";
		}
		if (in_array($operation, ["create", "update"], true) && count($selected["input"]) === 0) {
			return "no_input_fields";
		}
		return "ready";
	}

	private function respond_errors(Controller $ctl, array $errors): void {
		$ctl->clear_error_message();
		foreach ($errors as $field => $message) {
			$ctl->res_error_message($field, $message);
		}
	}

}
