<?php

class mcp_server {
	private const FUNCTION_LIST_TOOL = "function_list";
	private const FUNCTION_CALL_TOOL = "function_call";

	private $ffm_server;
	private $ffm_functions;
	private $ffm_logs;
	private $ffm_auth_codes;
	private $ffm_tokens;

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
		$this->ffm_server = $ctl->db("mcp_server_config", "mcp_manage");
		$this->ffm_functions = $ctl->db("mcp_functions", "mcp_manage");
		$this->ffm_logs = $ctl->db("mcp_call_logs", "mcp_manage");
		$this->ffm_auth_codes = $ctl->db("mcp_oauth_auth_codes", "mcp_manage");
		$this->ffm_tokens = $ctl->db("mcp_oauth_tokens", "mcp_manage");
	}

	function rpc(Controller $ctl) {
		if ((string) ($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
			$server = $this->current_server($ctl);
			if ((string) ($server["auth_mode"] ?? "oauth2") !== "noauth") {
				$this->respond_oauth_http_challenge($ctl, $server, "missing_access_token");
			}
			http_response_code(405);
			header("Allow: POST");
			$this->respond_json(["ok" => false, "error" => "method_not_allowed"]);
		}
		$request = $this->read_json_request();
		if (!is_array($request)) {
			$this->respond_json($this->json_error(null, -32700, "Parse error"));
		}
		$response = $this->handle_json_rpc($ctl, $request);
		$this->respond_json($response);
	}

	function sse(Controller $ctl) {
		$server = $this->current_server($ctl);
		if ($_SERVER["REQUEST_METHOD"] === "GET") {
			header("Content-Type: text/event-stream; charset=UTF-8");
			header("Cache-Control: no-cache");
			echo "event: endpoint\n";
			echo "data: " . $this->mcp_url($ctl, "rpc", $server) . "\n\n";
			exit;
		}
		$this->rpc($ctl);
	}

	function health(Controller $ctl) {
		$server = $this->current_server($ctl);
		$this->respond_json([
			"ok" => true,
			"enabled" => (int) ($server["enabled"] ?? 0) === 1,
			"server_key" => (string) ($server["server_key"] ?? "default"),
			"title" => (string) ($server["title"] ?? "FBP MCP Server"),
			"auth_mode" => (string) ($server["auth_mode"] ?? "oauth2"),
		]);
	}

	function cli_function_check(Controller $ctl) {
		if (!defined("CLI_APP_CALL") || CLI_APP_CALL !== true) {
			throw new Exception("CLI only.");
		}
		$name = trim((string) ($ctl->POST("function_name") ?? ""));
		$function = $this->find_function_by_name($name);
		if ($function === null || !$this->is_function_ready($function, $ctl)) {
			throw new Exception("MCP function is not available: " . $name);
		}
		$handler = McpFunctionLoader::load($name, $ctl);
		$result = [
			"function_name" => $name,
			"class_name" => McpFunctionLoader::className($name),
			"input_schema" => $handler->getInputSchema($ctl, $function),
			"output_schema" => $handler->getOutputSchema($ctl, $function),
			"result" => $handler->execute($ctl, new McpFunctionRequest($function, is_array($ctl->POST("arguments")) ? $ctl->POST("arguments") : [], null))->toStructuredContent(),
		];
		echo json_encode(["ok" => true, "mcp_function" => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$ctl->stop_res = true;
	}

	function oauth_protected_resource(Controller $ctl) {
		$server = $this->current_server($ctl);
		$resource = $this->resource_url($ctl, $server);
		$this->respond_json([
			"resource" => $resource,
			"authorization_servers" => [
				$this->oauth_issuer($ctl, $server),
			],
			"scopes_supported" => $this->supported_scopes($server),
			"bearer_methods_supported" => ["header"],
		]);
	}

	function oauth_authorization_server(Controller $ctl) {
		$server = $this->current_server($ctl);
		$this->respond_json([
			"issuer" => $this->oauth_issuer($ctl, $server),
			"authorization_endpoint" => $this->mcp_url($ctl, "authorize", $server),
			"token_endpoint" => $ctl->get_APP_URL("mcp_server", "token"),
			"response_types_supported" => ["code"],
			"grant_types_supported" => ["authorization_code", "refresh_token"],
			"code_challenge_methods_supported" => ["S256", "plain"],
			"scopes_supported" => $this->supported_scopes($server),
			"client_id_metadata_document_supported" => true,
			"token_endpoint_auth_methods_supported" => ["none"],
		]);
	}

	function authorize(Controller $ctl) {
		$server = $this->current_server($ctl);
		if ((int) ($server["enabled"] ?? 0) !== 1) {
			http_response_code(503);
			$ctl->assign("message", "MCP server is disabled.");
			$ctl->display("message.tpl");
			return;
		}

		$provider = $this->subject_provider($ctl, $server);
		$subject = $provider->currentSubject($ctl, $server);
		if ($subject === null) {
			header("Location: " . $provider->loginUrl($ctl, $server, $this->current_request_url()), true, 302);
			exit;
		}

		$params = $this->oauth_authorize_params($ctl);
		$error = $this->validate_authorize_params($params, $ctl);
		if ($error !== "") {
			http_response_code(400);
			$ctl->assign("message", $error);
			$ctl->display("message.tpl");
			return;
		}

		$scope = $params["scope"] !== "" ? $params["scope"] : (string) ($server["default_scope"] ?? "");
		$ctl->assign("server", $server);
		$ctl->assign("subject", $subject->toArray());
		$ctl->assign("subject_label", $provider->subjectLabel($ctl, $subject));
		$ctl->assign("oauth_params", $params);
		$ctl->assign("scope", $scope);
		$ctl->display("authorize.tpl");
	}

	function authorize_confirm(Controller $ctl) {
		$server = $this->current_server($ctl);
		if ((int) ($server["enabled"] ?? 0) !== 1) {
			http_response_code(503);
			$this->respond_json(["ok" => false, "error" => "mcp_server_disabled"]);
		}
		$provider = $this->subject_provider($ctl, $server);
		$subject = $provider->currentSubject($ctl, $server);
		if ($subject === null) {
			http_response_code(401);
			$this->respond_json(["ok" => false, "error" => "login_required"]);
		}

		$params = $this->oauth_authorize_params($ctl);
		$error = $this->validate_authorize_params($params, $ctl);
		if ($error !== "") {
			http_response_code(400);
			$this->respond_json(["ok" => false, "error" => $error]);
		}
		$scope = $params["scope"] !== "" ? $params["scope"] : (string) ($server["default_scope"] ?? "");
		$code = $this->random_token();
		$subject_label = $provider->subjectLabel($ctl, $subject);
		$row = [
			"server_id" => (int) $server["id"],
			"user_id" => $subject->userId(),
			"subject_type" => $subject->type(),
			"subject_id" => $subject->id(),
			"subject_label" => $subject_label,
			"client_id" => $params["client_id"],
			"redirect_uri" => $params["redirect_uri"],
			"scope" => $scope,
			"resource" => $this->normalize_oauth_resource($ctl, $params["resource"] ?? ""),
			"code_hash" => hash("sha256", $code),
			"code_challenge" => $params["code_challenge"],
			"code_challenge_method" => $params["code_challenge_method"],
			"expires_at" => time() + 600,
			"consumed" => 0,
			"created_at" => time(),
			"updated_at" => time(),
		];
		$this->ffm_auth_codes->insert($row);
		$provider->onAuthorizeConfirmed($ctl, $server, $subject, $params, $scope);

		$redirect = $this->append_query($params["redirect_uri"], [
			"code" => $code,
			"state" => $params["state"],
		]);
		header("Location: " . $redirect, true, 302);
		exit;
	}

	function token(Controller $ctl) {
		$params = $this->read_oauth_params();
		$grant_type = (string) ($params["grant_type"] ?? "");
		if ($grant_type === "authorization_code") {
			$this->token_from_authorization_code($ctl, $params);
		}
		if ($grant_type === "refresh_token") {
			$this->token_from_refresh_token($ctl, $params);
		}
		http_response_code(400);
		$this->respond_json(["error" => "unsupported_grant_type"]);
	}

	function revoke(Controller $ctl) {
		$params = $this->read_oauth_params();
		$token = (string) ($params["token"] ?? "");
		if ($token !== "") {
			$hash = hash("sha256", $token);
			foreach ($this->ffm_tokens->select("access_token_hash", $hash) as $row) {
				$row["revoked"] = 1;
				$row["updated_at"] = time();
				$this->ffm_tokens->update($row);
				$this->notify_token_revoked($ctl, $row);
			}
			foreach ($this->ffm_tokens->select("refresh_token_hash", $hash) as $row) {
				$row["revoked"] = 1;
				$row["updated_at"] = time();
				$this->ffm_tokens->update($row);
				$this->notify_token_revoked($ctl, $row);
			}
		}
		$this->respond_json(["ok" => true]);
	}

	private function handle_json_rpc(Controller $ctl, array $request): array {
		$id = $request["id"] ?? null;
		$method = (string) ($request["method"] ?? "");
		$params = is_array($request["params"] ?? null) ? $request["params"] : [];
		$server = $this->current_server($ctl);

		if ($method === "") {
			return $this->json_error($id, -32600, "Invalid Request");
		}
		if ($method === "initialize") {
			return $this->json_result($id, [
				"protocolVersion" => "2024-11-05",
				"capabilities" => [
					"tools" => ["listChanged" => false],
				],
				"serverInfo" => [
					"name" => (string) ($server["title"] ?? "FBP MCP Server"),
					"version" => "0.1.0",
				],
			]);
		}
		if ($method === "notifications/initialized") {
			return $this->json_result($id, new stdClass());
		}
		if ((int) ($server["enabled"] ?? 0) !== 1) {
			return $this->json_error($id, -32000, "MCP server is disabled.");
		}
		if ($method === "tools/list") {
			return $this->json_result($id, ["tools" => $this->build_tool_descriptors($ctl, $server)]);
		}
		if ($method === "tools/call") {
			return $this->json_result($id, $this->handle_tool_call($ctl, $server, $params));
		}
		if ($method === "ping") {
			return $this->json_result($id, new stdClass());
		}
		return $this->json_error($id, -32601, "Method not found");
	}

	private function build_tool_descriptors(Controller $ctl, array $server): array {
		return [
			$this->build_function_list_descriptor($server),
			$this->build_function_call_descriptor($server),
		];
	}

	private function build_function_list_descriptor(array $server): array {
		return $this->gateway_descriptor($server, self::FUNCTION_LIST_TOOL, "List available functions", "List the registered application functions available to the authenticated subject, including their input and output schemas.", [
			"type" => "object",
			"properties" => [],
			"additionalProperties" => false,
		], [
			"type" => "object",
			"properties" => [
				"functions" => ["type" => "array", "items" => ["type" => "object", "additionalProperties" => true]],
				"count" => ["type" => "integer", "minimum" => 0],
			],
			"required" => ["functions", "count"],
			"additionalProperties" => false,
		]);
	}

	private function build_function_call_descriptor(array $server): array {
		return $this->gateway_descriptor($server, self::FUNCTION_CALL_TOOL, "Call an application function", "Call one function returned by function_list. The framework enforces that function's scope, confirmation requirement, schema validation, and audit logging.", [
			"type" => "object",
			"properties" => [
				"function_name" => ["type" => "string", "pattern" => "^[a-z][a-z0-9_]*$", "description" => "Name returned by function_list."],
				"arguments" => ["type" => "object", "description" => "Arguments validated against the selected function schema.", "additionalProperties" => true],
			],
			"required" => ["function_name", "arguments"],
			"additionalProperties" => false,
		], ["type" => "object", "additionalProperties" => true]);
	}

	private function gateway_descriptor(array $server, string $name, string $title, string $description, array $input_schema, array $output_schema): array {
		$descriptor = [
			"name" => $name,
			"title" => $title,
			"description" => $description,
			"inputSchema" => $this->normalize_input_schema($input_schema),
			"outputSchema" => $this->normalize_input_schema($output_schema),
			"annotations" => ["readOnlyHint" => $name === self::FUNCTION_LIST_TOOL, "destructiveHint" => false, "openWorldHint" => false],
		];
		if ((string) ($server["auth_mode"] ?? "oauth2") === "noauth") {
			$descriptor["securitySchemes"] = [["type" => "noauth"]];
		} else {
			$descriptor["securitySchemes"] = [["type" => "oauth2", "scopes" => $this->split_scopes((string) ($server["default_scope"] ?? ""))]];
		}
		return $descriptor;
	}

	private function build_function_descriptor(Controller $ctl, array $server, array $function): array {
		$name = (string) ($function["function_name"] ?? "");
		$handler = McpFunctionLoader::load($name, $ctl);
		$descriptor = [
			"name" => $name,
			"title" => (string) ($function["title"] ?? $name),
			"description" => (string) ($function["description"] ?? ""),
			"inputSchema" => $this->normalize_input_schema($handler->getInputSchema($ctl, $function)),
			"annotations" => [
				"readOnlyHint" => (int) ($function["read_only"] ?? 0) === 1,
				"destructiveHint" => (int) ($function["destructive"] ?? 0) === 1,
				"openWorldHint" => false,
			],
		];
		$output_schema = $handler->getOutputSchema($ctl, $function);
		if (count($output_schema) > 0) {
			$descriptor["outputSchema"] = $output_schema;
		}
		$scope = $this->tool_scope($server, $function);
		if ((string) ($server["auth_mode"] ?? "oauth2") === "noauth") {
			$descriptor["securitySchemes"] = [["type" => "noauth"]];
		} else {
			$descriptor["securitySchemes"] = [["type" => "oauth2", "scopes" => $this->split_scopes($scope)]];
		}
		return $descriptor;
	}

	private function build_tool_descriptor(Controller $ctl, array $server, array $tool): array {
		$name = (string) ($tool["tool_name"] ?? "");
		$scope = $this->tool_scope($server, $tool);
		$descriptor = [
			"name" => $name,
			"title" => (string) ($tool["title"] ?? $name),
			"description" => (string) ($tool["description"] ?? ""),
			"inputSchema" => $this->build_tool_input_schema($ctl, $tool),
			"annotations" => [
				"readOnlyHint" => (int) ($tool["read_only"] ?? 0) === 1,
				"destructiveHint" => (int) ($tool["destructive"] ?? 0) === 1,
				"openWorldHint" => false,
			],
		];
		if ((string) ($server["auth_mode"] ?? "oauth2") === "noauth") {
			$descriptor["securitySchemes"] = [["type" => "noauth"]];
		} else {
			$descriptor["securitySchemes"] = [[
				"type" => "oauth2",
				"scopes" => $this->split_scopes($scope),
			]];
		}
		return $descriptor;
	}

	private function handle_tool_call(Controller $ctl, array $server, array $params): array {
		$name = (string) ($params["name"] ?? "");
		$args = is_array($params["arguments"] ?? null) ? $params["arguments"] : [];
		if ($name === self::FUNCTION_LIST_TOOL) return $this->handle_function_list($ctl, $server);
		if ($name === self::FUNCTION_CALL_TOOL) return $this->handle_gateway_function_call($ctl, $server, $args);
		return $this->tool_error("Tool is not available.");
	}

	private function handle_function_list(Controller $ctl, array $server): array {
		$auth = $this->authorize_scope($ctl, $server, "mcp.read");
		if (!$auth["ok"]) return $this->tool_auth_error($ctl, $server, $auth["error"]);
		$functions = [];
		foreach ($this->registered_functions() as $function) {
			if (!$this->is_function_ready($function, $ctl)) continue;
			if ((string) ($server["auth_mode"] ?? "oauth2") !== "noauth" && !$this->scope_allows((string) ($auth["scope"] ?? ""), $this->tool_scope($server, $function))) continue;
			$functions[] = $this->build_function_descriptor($ctl, $server, $function);
		}
		$result = ["functions" => $functions, "count" => count($functions)];
		return ["content" => [["type" => "text", "text" => count($functions) . " function(s) available."]], "structuredContent" => $result];
	}

	private function handle_gateway_function_call(Controller $ctl, array $server, array $args): array {
		$name = trim((string) ($args["function_name"] ?? ""));
		$arguments = $args["arguments"] ?? null;
		if (!McpFunctionLoader::validateName($name) || !is_array($arguments)) return $this->tool_error("function_name and arguments object are required.");
		$function = $this->find_function_by_name($name);
		if ($function === null || !$this->is_function_ready($function, $ctl)) return $this->tool_error("Function is not available.");
		return $this->handle_function_call($ctl, $server, $function, $arguments);
	}

	private function handle_function_call(Controller $ctl, array $server, array $function, array $args): array {
		$auth = $this->authorize_tool_call($ctl, $server, $function);
		if (!$auth["ok"]) {
			return $this->tool_auth_error($ctl, $server, $auth["error"]);
		}
		$subject = $this->subject_from_row($auth);
		try {
			if ((int) ($function["requires_confirmation"] ?? 0) === 1 && empty($args["confirm"])) {
				throw new Exception("This tool requires confirm=true.");
			}
			$handler = McpFunctionLoader::load((string) ($function["function_name"] ?? ""), $ctl);
			$result = $handler->execute($ctl, new McpFunctionRequest($function, $args, $subject))->toStructuredContent();
			$this->safe_log_call($server, $function, $subject, "tools/call", $args, "ok", "");
			return [
				"content" => $this->tool_content_from_result($result),
				"structuredContent" => $result,
			];
		} catch (Throwable $e) {
			$this->safe_log_call($server, $function, $subject, "tools/call", $args, "error", $e->getMessage());
			return $this->tool_error($e->getMessage());
		}
	}

	private function tool_content_from_result(array $result): array {
		$content = [[
			"type" => "text",
			"text" => (string) ($result["message"] ?? "Done."),
		]];
		$data = is_array($result["data"] ?? null) ? $result["data"] : [];
		$images = is_array($data["mcp_content_images"] ?? null) ? $data["mcp_content_images"] : [];
		foreach ($images as $image) {
			if (!is_array($image)) {
				continue;
			}
			$base64 = trim((string) ($image["data_base64"] ?? ($image["data"] ?? "")));
			$mimeType = trim((string) ($image["mime_type"] ?? ($image["mimeType"] ?? "")));
			if ($base64 === "" || $mimeType === "") {
				continue;
			}
			$content[] = [
				"type" => "image",
				"data" => $base64,
				"mimeType" => $mimeType,
			];
		}
		return $content;
	}

	private function execute_note_tool(Controller $ctl, array $tool, array $args, ?McpSubject $subject = null): array {
		$tool_type = (string) ($tool["tool_type"] ?? "");
		if ($tool_type === "app_action") {
			return $this->execute_app_action_tool($ctl, $tool, $args, $subject);
		}
		if ($tool_type !== "note_crud") {
			throw new Exception("Unsupported tool type.");
		}
		$operation = (string) ($tool["operation"] ?? "");
		$table = (string) ($tool["target_note"] ?? "");
		if ($table === "") {
			throw new Exception("Target note is not configured.");
		}
		$ffm = $ctl->db($table);
		if ($operation === "list") {
			return $this->execute_list($ctl, $ffm, $tool, $args);
		}
		if ($operation === "get") {
			return $this->execute_get($ctl, $ffm, $tool, $args);
		}
		if ($operation === "create") {
			return $this->execute_create($ctl, $ffm, $tool, $args);
		}
		if ($operation === "update") {
			return $this->execute_update($ctl, $ffm, $tool, $args);
		}
		if ($operation === "delete") {
			return $this->execute_delete($ctl, $ffm, $tool, $args);
		}
		throw new Exception("Unsupported operation.");
	}

	private function execute_app_action_tool(Controller $ctl, array $tool, array $args, ?McpSubject $subject = null): array {
		$action = $this->load_app_action($ctl, $tool);
		$result = $action->execute($ctl, new McpActionRequest($tool, $args, $subject));
		return $result->toStructuredContent();
	}

	private function execute_list(Controller $ctl, FFM $ffm, array $tool, array $args): array {
		$limit = max(1, min((int) ($tool["max_limit"] ?? 20), (int) ($args["limit"] ?? ($tool["max_limit"] ?? 20))));
		$query = trim((string) ($args["query"] ?? ""));
		$table = (string) ($tool["target_note"] ?? "");
		$field_map = $this->note_field_map($ctl, $table);
		$output_fields = $this->tool_fields((int) $tool["id"], "output");
		$search_fields = $this->tool_fields((int) $tool["id"], "search");
		if (count($search_fields) === 0) {
			$search_fields = $output_fields;
		}
		$rows = $ffm->getall("id", SORT_DESC);
		$items = [];
		foreach ($rows as $row) {
			if ($query !== "" && !$this->row_matches_query($ctl, $row, $search_fields, $query, $field_map)) {
				continue;
			}
			$items[] = $this->project_row($ctl, $row, $output_fields, $field_map);
			if (count($items) >= $limit) {
				break;
			}
		}
		return [
			"message" => count($items) . " item(s) found.",
			"count" => count($items),
			"items" => $items,
		];
	}

	private function execute_get(Controller $ctl, FFM $ffm, array $tool, array $args): array {
		$id = $this->required_id($args);
		$row = $ffm->get($id);
		if (empty($row)) {
			throw new Exception("Item not found.");
		}
		$table = (string) ($tool["target_note"] ?? "");
		$item = $this->project_row($ctl, $row, $this->tool_fields((int) $tool["id"], "output"), $this->note_field_map($ctl, $table));
		return [
			"message" => "Item loaded.",
			"item" => $item,
		];
	}

	private function enable_strict_field_length(FFM $ffm): void {
		if (method_exists($ffm, "set_strict_field_length")) {
			$ffm->set_strict_field_length(true);
		}
	}

	private function execute_create(Controller $ctl, FFM $ffm, array $tool, array $args): array {
		$table = (string) ($tool["target_note"] ?? "");
		$input_fields = $this->tool_field_rows((int) $tool["id"], "input");
		$uploads = [];
		$data = $this->build_input_data($ctl, $table, $input_fields, $args, true, $uploads);
		$this->enable_strict_field_length($ffm);
		$id = $ffm->insert($data);
		$row = $ffm->get((int) $id);
		if (!is_array($row)) {
			$row = ["id" => (int) $id];
		}
		if (count($uploads) > 0) {
			try {
				$row = $this->save_mcp_uploads($ctl, $ffm, $table, $row, $uploads);
			} catch (Throwable $e) {
				$ffm->delete((int) $id);
				throw $e;
			}
		}
		$post_action = $this->run_note_post_action_hook($ctl, $table, "add", is_array($row) ? $row : []);
		$row_after_hook = $ffm->get((int) $id);
		if (is_array($row_after_hook)) {
			$row = $row_after_hook;
		}
		return [
			"message" => "Item created.",
			"id" => (int) $id,
			"item" => $this->project_row($ctl, $row, $this->tool_fields((int) $tool["id"], "output"), $this->note_field_map($ctl, $table)),
			"post_action_hook" => $post_action,
		];
	}

	private function execute_update(Controller $ctl, FFM $ffm, array $tool, array $args): array {
		$table = (string) ($tool["target_note"] ?? "");
		$id = $this->required_id($args);
		$row = $ffm->get($id);
		if (empty($row)) {
			throw new Exception("Item not found.");
		}
		$before_row = $row;
		$uploads = [];
		$data = $this->build_input_data($ctl, $table, $this->tool_field_rows((int) $tool["id"], "input"), $args, false, $uploads);
		foreach ($data as $key => $value) {
			$row[$key] = $value;
		}
		$this->enable_strict_field_length($ffm);
		if (count($data) > 0) {
			$ffm->update($row);
			$row = $ffm->get($id);
		}
		if (is_array($row) && count($uploads) > 0) {
			$row = $this->save_mcp_uploads($ctl, $ffm, $table, $row, $uploads);
		}
		$post_action = $this->run_note_post_action_hook($ctl, $table, "edit", is_array($row) ? $row : [], is_array($before_row) ? $before_row : null);
		$row_after_hook = $ffm->get($id);
		if (is_array($row_after_hook)) {
			$row = $row_after_hook;
		}
		return [
			"message" => "Item updated.",
			"id" => $id,
			"item" => $this->project_row($ctl, $row, $this->tool_fields((int) $tool["id"], "output"), $this->note_field_map($ctl, $table)),
			"post_action_hook" => $post_action,
		];
	}

	private function execute_delete(Controller $ctl, FFM $ffm, array $tool, array $args): array {
		$id = $this->required_id($args);
		if (empty($args["confirm"])) {
			throw new Exception("Delete requires confirm=true.");
		}
		$row = $ffm->get($id);
		if (empty($row)) {
			throw new Exception("Item not found.");
		}
		$ffm->delete($id);
		$post_action = $this->run_note_post_action_hook($ctl, (string) ($tool["target_note"] ?? ""), "delete", is_array($row) ? $row : []);
		return [
			"message" => "Item deleted.",
			"id" => $id,
			"post_action_hook" => $post_action,
		];
	}

	private function run_note_post_action_hook(Controller $ctl, string $table, string $from, array $data, ?array $before_data = null): array {
		$db_setting = $this->note_db_setting($ctl, $table);
		$class = trim((string) ($db_setting["post_action_class"] ?? ""));
		if ($class === "") {
			return [
				"executed" => false,
				"class" => "",
			];
		}
		$hook = $this->load_post_action_hook($ctl, $class);
		$post = [
			"class" => $class,
			"function" => "run",
			"id" => $ctl->encrypt((string) ($data["id"] ?? 0)),
			"_post_action_table" => $table,
			"_post_action_from" => $from,
			"_post_action_source" => "mcp_server",
		];
		if (is_array($before_data)) {
			$post["_post_action_before"] = json_encode($before_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		if (is_array($data)) {
			$post["_post_action_after"] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$previous_post = $_POST;
		try {
			$_POST = $post;
			$hook->run($ctl);
		} finally {
			$_POST = $previous_post;
		}
		return [
			"executed" => true,
			"class" => $class,
		];
	}

	private function note_db_setting(Controller $ctl, string $table): array {
		$table = trim($table);
		if ($table === "") {
			return [];
		}
		$list = $ctl->db("db", "db")->select("tb_name", $table);
		if (count($list) === 0) {
			return [];
		}
		return is_array($list[0]) ? $list[0] : [];
	}

	private function load_post_action_hook(Controller $ctl, string $class): CodegenActionInterface {
		$class = trim($class);
		if ($class === "" || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
			throw new Exception("Post-Action Hook class is invalid.");
		}
		if (!class_exists($class, false)) {
			$dir = new Dirs();
			$class_file = $dir->get_class_dir($class) . "/" . $class . ".php";
			include_once($class_file);
		}
		if (!class_exists($class, false)) {
			throw new Exception("Post-Action Hook class not found: " . $class);
		}
		$reflection = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();
		if ($constructor && count($constructor->getParameters()) > 0) {
			$hook = new $class($ctl);
		} else {
			$hook = new $class();
		}
		if (!($hook instanceof CodegenActionInterface)) {
			throw new Exception("Post-Action Hook class must implement CodegenActionInterface: " . $class);
		}
		return $hook;
	}

	private function build_tool_input_schema(Controller $ctl, array $tool): array {
		if ((string) ($tool["tool_type"] ?? "") === "app_action") {
			$schema = $this->load_app_action($ctl, $tool)->getInputSchema($ctl, $tool);
			return $this->normalize_input_schema($schema);
		}
		return $this->build_input_schema($ctl, $tool);
	}

	private function build_input_schema(Controller $ctl, array $tool): array {
		$operation = (string) ($tool["operation"] ?? "");
		$schema = [
			"type" => "object",
			"properties" => [],
			"required" => [],
			"additionalProperties" => false,
		];
		if ($operation === "list") {
			$schema["properties"]["query"] = ["type" => "string", "description" => "Optional text search."];
			$schema["properties"]["limit"] = ["type" => "integer", "minimum" => 1, "maximum" => (int) ($tool["max_limit"] ?? 20)];
		} elseif ($operation === "get") {
			$schema["properties"]["id"] = ["type" => "integer"];
			$schema["required"][] = "id";
		} elseif (in_array($operation, ["create", "update"], true)) {
			if ($operation === "update") {
				$schema["properties"]["id"] = ["type" => "integer"];
				$schema["required"][] = "id";
			}
			$field_map = $this->note_field_map($ctl, (string) ($tool["target_note"] ?? ""));
			foreach ($this->tool_field_rows((int) ($tool["id"] ?? 0), "input") as $field) {
				$name = (string) ($field["field_name"] ?? "");
				if ($name === "") {
					continue;
				}
				$db_field = $field_map[$name] ?? [];
				$schema["properties"][$name] = $this->json_schema_for_db_field($ctl, $db_field);
				if ($operation === "create" && (int) ($field["required"] ?? 0) === 1) {
					$schema["required"][] = $name;
				}
			}
		} elseif ($operation === "delete") {
			$schema["properties"]["id"] = ["type" => "integer"];
			$schema["properties"]["confirm"] = ["type" => "boolean", "description" => "Must be true to delete."];
			$schema["required"] = ["id", "confirm"];
		}
		if ((int) ($tool["requires_confirmation"] ?? 0) === 1 && !isset($schema["properties"]["confirm"])) {
			$schema["properties"]["confirm"] = ["type" => "boolean", "description" => "Must be true to execute this write operation."];
			$schema["required"][] = "confirm";
		}
		return $schema;
	}

	private function normalize_input_schema(array $schema): array {
		if (($schema["type"] ?? "") !== "object") {
			$schema["type"] = "object";
		}
		if (!isset($schema["properties"]) || (!is_array($schema["properties"]) && !is_object($schema["properties"]))) {
			$schema["properties"] = new stdClass();
		} elseif (is_array($schema["properties"]) && count($schema["properties"]) === 0) {
			$schema["properties"] = new stdClass();
		}
		if (!isset($schema["required"]) || !is_array($schema["required"])) {
			$schema["required"] = [];
		}
		if (!array_key_exists("additionalProperties", $schema)) {
			$schema["additionalProperties"] = false;
		}
		return $schema;
	}

	private function json_schema_for_db_field(Controller $ctl, array $field): array {
		$type = (string) ($field["type"] ?? "text");
		$title = (string) ($field["parameter_title"] ?? ($field["parameter_name"] ?? ""));
		$description = trim((string) ($field["parameter_description"] ?? ""));
		if ($description === "" && $title !== "") {
			$description = $title;
		}
		$schema = ["type" => "string"];
		if (in_array($type, ["number", "int", "integer"], true)) {
			$schema = ["type" => "number"];
		} elseif ($type === "checkbox") {
			$schema = ["type" => "array", "items" => ["type" => "string"]];
		} elseif (in_array($type, ["file", "image"], true)) {
			$schema = $this->json_schema_for_upload_field($type);
		}
		if ($title !== "") {
			$schema["title"] = $title;
		}
		if ($description !== "") {
			$schema["description"] = $description;
		}
		$schema = $this->apply_table_reference_schema($ctl, $field, $schema);
		return $this->apply_constant_array_schema($ctl, $field, $schema);
	}

	private function json_schema_for_upload_field(string $type): array {
		$kind = $type === "image" ? "image" : "file";
		return [
			"type" => "object",
			"properties" => [
				"filename" => ["type" => "string", "description" => "Original " . $kind . " filename."],
				"mime_type" => ["type" => "string", "description" => "Optional MIME type."],
				"content_base64" => ["type" => "string", "description" => "Base64-encoded " . $kind . " content without a data URL prefix."],
				"data_url" => ["type" => "string", "description" => "Optional data URL such as data:image/png;base64,...."],
			],
			"anyOf" => [
				["required" => ["content_base64"]],
				["required" => ["data_url"]],
			],
			"additionalProperties" => false,
		];
	}

	private function apply_table_reference_schema(Controller $ctl, array $field, array $schema): array {
		$array_name = trim((string) ($field["constant_array_name"] ?? ""));
		if ($array_name === "" || strpos($array_name, "table/") !== 0) {
			return $schema;
		}
		$reference = $this->parse_table_reference($ctl, $array_name);
		if (count($reference) === 0) {
			return $schema;
		}
		$schema["x-fbp-reference"] = $reference;

		$target = (string) ($reference["table"] ?? "");
		$label_field = (string) ($reference["labelField"] ?? "");
		$description = "Reference to " . $target . ". Use " . $target . " id as the value.";
		if ($label_field !== "") {
			$description .= " Display field: " . $label_field . ".";
		}
		return $this->append_schema_description($schema, $description);
	}

	private function parse_table_reference(Controller $ctl, string $array_name): array {
		$path = substr($array_name, 6);
		$parts = explode("/", $path);
		$table = trim((string) ($parts[0] ?? ""));
		if ($table === "") {
			return [];
		}
		$label_field = trim((string) ($parts[1] ?? ""));
		$reference = [
			"type" => "table",
			"table" => $table,
			"valueField" => "id",
			"constantArray" => $array_name,
		];
		if ($label_field !== "") {
			$reference["labelField"] = $label_field;
		}
		$title = $this->table_title($ctl, $table);
		if ($title !== "") {
			$reference["title"] = $title;
		}
		return $reference;
	}

	private function table_title(Controller $ctl, string $table): string {
		$rows = $ctl->db("db", "db")->select("tb_name", $table);
		if (count($rows) === 0) {
			return "";
		}
		$title = trim((string) ($rows[0]["menu_name"] ?? ""));
		return $title === "" ? $table : $title;
	}

	private function apply_constant_array_schema(Controller $ctl, array $field, array $schema): array {
		$array_name = trim((string) ($field["constant_array_name"] ?? ""));
		if ($array_name === "" || strpos($array_name, "table/") === 0) {
			return $schema;
		}
		if (!$ctl->is_constant_array($array_name)) {
			return $schema;
		}
		$options = $ctl->get_constant_array($array_name, false);
		if (!is_array($options) || count($options) === 0) {
			return $schema;
		}

		$enum = [];
		$labels = [];
		$enum_options = [];
		$description_parts = [];
		foreach ($options as $key => $label) {
			$value = (string) $key;
			$label = $this->single_line((string) $label);
			$enum[] = $value;
			$labels[] = $label;
			$enum_options[] = [
				"value" => $value,
				"label" => $label,
			];
			$description_parts[] = $value . " = " . $label;
		}
		if (count($enum) === 0) {
			return $schema;
		}

		if (($schema["type"] ?? "") === "array") {
			$schema["items"]["enum"] = $enum;
			$schema["items"]["x-enumLabels"] = $labels;
			$schema["items"]["x-fbp-enum"] = $enum_options;
			$schema["items"]["x-fbp-constantArray"] = $array_name;
		} else {
			$schema["type"] = "string";
			$schema["enum"] = $enum;
			$schema["x-enumLabels"] = $labels;
			$schema["x-fbp-enum"] = $enum_options;
			$schema["x-fbp-constantArray"] = $array_name;
		}

		$option_description = "Allowed values (" . $array_name . "): " . implode("; ", $description_parts) . ".";
		return $this->append_schema_description($schema, $option_description);
	}

	private function append_schema_description(array $schema, string $description): array {
		$current = trim((string) ($schema["description"] ?? ""));
		$schema["description"] = $current === "" ? $description : $current . "\n" . $description;
		return $schema;
	}

	private function single_line(string $value): string {
		return trim(preg_replace('/\s+/', ' ', $value));
	}

	private function authorize_tool_call(Controller $ctl, array $server, array $tool): array {
		return $this->authorize_scope($ctl, $server, $this->tool_scope($server, $tool));
	}

	private function authorize_scope(Controller $ctl, array $server, string $required_scope): array {
		if ((string) ($server["auth_mode"] ?? "oauth2") === "noauth") {
			return ["ok" => true, "user_id" => 0, "subject_type" => "anonymous", "subject_id" => 0, "subject_label" => "", "scope" => ""];
		}
		$token = $this->bearer_token();
		if ($token === "") {
			return ["ok" => false, "error" => "missing_access_token"];
		}
		$token_row = $this->find_valid_token($ctl, (int) $server["id"], $token);
		if ($token_row === null) {
			return ["ok" => false, "error" => "invalid_access_token"];
		}
		if (!$this->scope_allows((string) ($token_row["scope"] ?? ""), $required_scope)) {
			return ["ok" => false, "error" => "insufficient_scope"];
		}
		return [
			"ok" => true,
			"user_id" => (int) ($token_row["user_id"] ?? 0),
			"subject_type" => $this->row_subject_type($token_row),
			"subject_id" => $this->row_subject_id($token_row),
			"subject_label" => (string) ($token_row["subject_label"] ?? ""),
			"scope" => (string) ($token_row["scope"] ?? ""),
		];
	}

	private function find_valid_token(Controller $ctl, int $server_id, string $token): ?array {
		$hash = hash("sha256", $token);
		$list = $this->ffm_tokens->select("access_token_hash", $hash);
		$server = $this->get_server_by_id($server_id);
		$expected_resource = empty($server) ? "" : $this->resource_url($ctl, $server);
		$provider = empty($server) ? new McpFbpUserSubjectProvider() : $this->subject_provider($ctl, $server);
		foreach ($list as $row) {
			if ((int) ($row["server_id"] ?? 0) !== $server_id) {
				continue;
			}
			if ((int) ($row["revoked"] ?? 0) === 1 || (int) ($row["expires_at"] ?? 0) <= time()) {
				continue;
			}
			$resource = (string) ($row["resource"] ?? "");
			if ($resource !== "" && $expected_resource !== "" && $resource !== $expected_resource) {
				continue;
			}
			$subject = $this->subject_from_row($row);
			if (!($subject instanceof McpSubject) || !$provider->validateSubject($ctl, $server, $subject)) {
				continue;
			}
			return $row;
		}
		return null;
	}

	private function token_from_authorization_code(Controller $ctl, array $params): void {
		$code = (string) ($params["code"] ?? "");
		$redirect_uri = (string) ($params["redirect_uri"] ?? "");
		$client_id = (string) ($params["client_id"] ?? "");
		if ($code === "" || $redirect_uri === "") {
			http_response_code(400);
			$this->respond_json(["error" => "invalid_request"]);
		}
		$list = $this->ffm_auth_codes->select("code_hash", hash("sha256", $code));
		foreach ($list as $auth_code) {
			if ((int) ($auth_code["consumed"] ?? 0) === 1 || (int) ($auth_code["expires_at"] ?? 0) <= time()) {
				continue;
			}
			if ((string) ($auth_code["redirect_uri"] ?? "") !== $redirect_uri) {
				continue;
			}
			if ($client_id !== "" && (string) ($auth_code["client_id"] ?? "") !== $client_id) {
				continue;
			}
			$expected_resource = (string) ($auth_code["resource"] ?? "");
			$request_resource = trim((string) ($params["resource"] ?? ""));
			if ($request_resource !== "" && $expected_resource !== "" && $request_resource !== $expected_resource) {
				http_response_code(400);
				$this->respond_json(["error" => "invalid_target"]);
			}
			if (!$this->verify_pkce($auth_code, (string) ($params["code_verifier"] ?? ""))) {
				http_response_code(400);
				$this->respond_json(["error" => "invalid_grant"]);
			}
			$server = $this->get_server_by_id((int) ($auth_code["server_id"] ?? 0));
			$provider = $this->subject_provider($ctl, $server);
			$subject = $this->subject_from_row($auth_code);
			if (!($subject instanceof McpSubject) || !$provider->validateSubject($ctl, $server, $subject)) {
				http_response_code(400);
				$this->respond_json(["error" => "invalid_grant"]);
			}
			$auth_code["consumed"] = 1;
			$auth_code["updated_at"] = time();
			$this->ffm_auth_codes->update($auth_code);
			$this->issue_token_response((int) ($auth_code["server_id"] ?? 0), $subject, (string) ($auth_code["client_id"] ?? ""), (string) ($auth_code["scope"] ?? ""), (string) ($auth_code["resource"] ?? ""));
		}
		http_response_code(400);
		$this->respond_json(["error" => "invalid_grant"]);
	}

	private function token_from_refresh_token(Controller $ctl, array $params): void {
		$refresh_token = (string) ($params["refresh_token"] ?? "");
		if ($refresh_token === "") {
			http_response_code(400);
			$this->respond_json(["error" => "invalid_request"]);
		}
		$list = $this->ffm_tokens->select("refresh_token_hash", hash("sha256", $refresh_token));
		foreach ($list as $token_row) {
			if ((int) ($token_row["revoked"] ?? 0) === 1) {
				continue;
			}
			$server = $this->get_server_by_id((int) ($token_row["server_id"] ?? 0));
			$provider = $this->subject_provider($ctl, $server);
			$subject = $this->subject_from_row($token_row);
			if (!($subject instanceof McpSubject) || !$provider->validateSubject($ctl, $server, $subject)) {
				continue;
			}
			$token_row["revoked"] = 1;
			$token_row["updated_at"] = time();
			$this->ffm_tokens->update($token_row);
			$provider->onTokenRevoked($ctl, $server, $subject, $token_row);
			$this->issue_token_response((int) ($token_row["server_id"] ?? 0), $subject, (string) ($token_row["client_id"] ?? ""), (string) ($token_row["scope"] ?? ""), (string) ($token_row["resource"] ?? ""));
		}
		http_response_code(400);
		$this->respond_json(["error" => "invalid_grant"]);
	}

	private function issue_token_response(int $server_id, McpSubject $subject, string $client_id, string $scope, string $resource): void {
		$access_token = $this->random_token();
		$refresh_token = $this->random_token();
		$row = [
			"server_id" => $server_id,
			"user_id" => $subject->userId(),
			"subject_type" => $subject->type(),
			"subject_id" => $subject->id(),
			"subject_label" => $subject->label(),
			"client_id" => $client_id,
			"scope" => $scope,
			"resource" => $resource,
			"access_token_hash" => hash("sha256", $access_token),
			"refresh_token_hash" => hash("sha256", $refresh_token),
			"expires_at" => time() + 3600,
			"revoked" => 0,
			"created_at" => time(),
			"updated_at" => time(),
		];
		$this->ffm_tokens->insert($row);
		$this->respond_json([
			"access_token" => $access_token,
			"token_type" => "Bearer",
			"expires_in" => 3600,
			"refresh_token" => $refresh_token,
			"scope" => $scope,
		]);
	}

	private function current_server(Controller $ctl): array {
		return $this->get_server();
	}

	private function request_server_key(Controller $ctl): string {
		$uri_params = null;
		foreach (["server", "server_key"] as $key) {
			$value = $ctl->GET($key);
			if ($value === null || $value === "") {
				$value = $ctl->POST($key);
			}
			if (($value === null || $value === "") && $uri_params === null) {
				$uri_params = $this->oauth_request_uri_params();
			}
			if (($value === null || $value === "") && is_array($uri_params)) {
				$value = $uri_params[$key] ?? "";
			}
			if (!is_array($value)) {
				$value = $this->normalize_server_key((string) $value);
				if ($value !== "") {
					return $value;
				}
			}
		}
		$resource = $ctl->GET("resource");
		if ($resource === null || $resource === "") {
			$resource = $ctl->POST("resource");
		}
		if (!is_array($resource)) {
			$value = $this->server_key_from_resource((string) $resource);
			if ($value !== "") {
				return $value;
			}
		}
		return "default";
	}

	private function server_key_from_resource(string $resource): string {
		$resource = trim($resource);
		if ($resource === "") {
			return "";
		}
		$params = [];
		$query = (string) parse_url($resource, PHP_URL_QUERY);
		if ($query !== "") {
			parse_str($query, $query_params);
			if (is_array($query_params)) {
				$params = array_merge($params, $query_params);
			}
		}
		$path = (string) parse_url($resource, PHP_URL_PATH);
		if (preg_match('#\*[A-Za-z0-9_]+&(.*)$#', $path, $matches)) {
			parse_str($matches[1], $path_params);
			if (is_array($path_params)) {
				$params = array_merge($params, $path_params);
			}
		}
		foreach (["server", "server_key"] as $key) {
			$value = $params[$key] ?? "";
			if (!is_array($value)) {
				$value = $this->normalize_server_key((string) $value);
				if ($value !== "") {
					return $value;
				}
			}
		}
		return "";
	}

	private function normalize_server_key(string $server_key): string {
		$server_key = trim($server_key);
		return preg_match('/^[A-Za-z0-9_-]+$/', $server_key) ? $server_key : "";
	}

	private function get_server(string $server_key = "default"): array {
		$list = $this->ffm_server->getall("sort", SORT_ASC);
		if (count($list) > 0) {
			foreach ($list as $server) {
				if ((string) ($server["server_key"] ?? "") === "default") {
					return $server;
				}
			}
			return $list[0];
		}
		return [
			"id" => 0,
			"enabled" => 0,
			"server_key" => "default",
			"title" => "FBP MCP Server",
			"description" => "",
			"auth_mode" => "oauth2",
			"subject_type" => "fbp_user",
			"subject_provider_class" => "",
			"default_scope" => "mcp.read mcp.write",
		];
	}

	private function get_server_by_id(int $server_id): array {
		if ($server_id <= 0) {
			return [];
		}
		$server = $this->ffm_server->get($server_id);
		return is_array($server) ? $server : [];
	}

	private function subject_provider(Controller $ctl, array $server): McpSubjectProviderInterface {
		$subject_type = trim((string) ($server["subject_type"] ?? ""));
		$class = trim((string) ($server["subject_provider_class"] ?? ""));
		if ($subject_type === "" || $subject_type === "fbp_user") {
			return new McpFbpUserSubjectProvider();
		}
		if ($class === "" || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
			throw new Exception("MCP subject provider class is invalid.");
		}
		if (!class_exists($class, false)) {
			$dir = new Dirs();
			$class_file = $dir->get_class_dir($class) . "/" . $class . ".php";
			include_once($class_file);
		}
		if (!class_exists($class, false)) {
			throw new Exception("MCP subject provider class not found: " . $class);
		}
		$reflection = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();
		if ($constructor && count($constructor->getParameters()) > 0) {
			$provider = new $class($ctl);
		} else {
			$provider = new $class();
		}
		if (!($provider instanceof McpSubjectProviderInterface)) {
			throw new Exception("MCP subject provider must implement McpSubjectProviderInterface: " . $class);
		}
		return $provider;
	}

	private function row_subject_type(array $row): string {
		$type = trim((string) ($row["subject_type"] ?? ""));
		if ($type !== "") {
			return $type;
		}
		return (int) ($row["user_id"] ?? 0) > 0 ? "fbp_user" : "anonymous";
	}

	private function row_subject_id(array $row): int {
		$id = (int) ($row["subject_id"] ?? 0);
		if ($id > 0) {
			return $id;
		}
		if ($this->row_subject_type($row) === "fbp_user") {
			return (int) ($row["user_id"] ?? 0);
		}
		return 0;
	}

	private function subject_from_row(array $row): ?McpSubject {
		$type = $this->row_subject_type($row);
		$id = $this->row_subject_id($row);
		if ($type === "" || ($type !== "anonymous" && $id <= 0)) {
			return null;
		}
		$user_id = (int) ($row["user_id"] ?? 0);
		if ($type === "fbp_user" && $user_id <= 0) {
			$user_id = $id;
		}
		return new McpSubject($type, $id, (string) ($row["subject_label"] ?? ""), $user_id);
	}

	private function notify_token_revoked(Controller $ctl, array $token_row): void {
		$server = $this->get_server_by_id((int) ($token_row["server_id"] ?? 0));
		if (empty($server)) {
			return;
		}
		$subject = $this->subject_from_row($token_row);
		if (!($subject instanceof McpSubject)) {
			return;
		}
		try {
			$this->subject_provider($ctl, $server)->onTokenRevoked($ctl, $server, $subject, $token_row);
		} catch (Throwable $e) {
			// Revocation itself must not fail because of an optional provider hook.
		}
	}

	private function find_tool_by_name(int $server_id, string $name): ?array {
		foreach ($this->ffm_tools->select(["server_id", "tool_name"], [$server_id, $name]) as $tool) {
			return $tool;
		}
		return null;
	}

	private function registered_functions(): array {
		return $this->ffm_functions->getall("sort", SORT_ASC);
	}

	private function find_function_by_name(string $name): ?array {
		foreach ($this->ffm_functions->select("function_name", $name) as $function) {
			return $function;
		}
		return null;
	}

	private function is_function_ready(array $function, ?Controller $ctl = null): bool {
		if ((int) ($function["enabled"] ?? 0) !== 1) {
			return false;
		}
		try {
			McpFunctionLoader::load((string) ($function["function_name"] ?? ""), $ctl);
			return true;
		} catch (Throwable $e) {
			return false;
		}
	}

	private function is_tool_ready(array $tool, ?Controller $ctl = null): bool {
		if ((int) ($tool["enabled"] ?? 0) !== 1) {
			return false;
		}
		$tool_type = (string) ($tool["tool_type"] ?? "");
		if ($tool_type === "app_action") {
			return $this->app_action_class_is_valid($tool, $ctl);
		}
		if ($tool_type !== "note_crud") {
			return false;
		}
		$operation = (string) ($tool["operation"] ?? "");
		if (in_array($operation, ["list", "get"], true)) {
			return count($this->tool_fields((int) ($tool["id"] ?? 0), "output")) > 0;
		}
		if (in_array($operation, ["create", "update"], true)) {
			return count($this->tool_fields((int) ($tool["id"] ?? 0), "input")) > 0;
		}
		return $operation === "delete";
	}

	private function app_action_class_is_valid(array $tool, ?Controller $ctl = null): bool {
		try {
			$this->load_app_action($ctl, $tool);
			return true;
		} catch (Throwable $e) {
			return false;
		}
	}

	private function load_app_action(?Controller $ctl, array $tool): McpActionInterface {
		$class = trim((string) ($tool["action_class"] ?? ""));
		if ($class === "" || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
			throw new Exception("App Action class is invalid.");
		}
		if (!class_exists($class, false)) {
			$dir = new Dirs();
			$class_file = $dir->get_class_dir($class) . "/" . $class . ".php";
			include_once($class_file);
		}
		if (!class_exists($class, false)) {
			throw new Exception("App Action class not found: " . $class);
		}
		$reflection = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();
		if ($constructor && count($constructor->getParameters()) > 0 && $ctl !== null) {
			$action = new $class($ctl);
		} else {
			$action = new $class();
		}
		if (!($action instanceof McpActionInterface)) {
			throw new Exception("App Action class must implement McpActionInterface: " . $class);
		}
		return $action;
	}

	private function tool_fields(int $tool_id, string $role): array {
		$fields = [];
		foreach ($this->tool_field_rows($tool_id, $role) as $row) {
			$name = (string) ($row["field_name"] ?? "");
			if ($name !== "") {
				$fields[] = $name;
			}
		}
		return $fields;
	}

	private function tool_field_rows(int $tool_id, string $role): array {
		return $this->ffm_fields->select(["tool_id", "role"], [$tool_id, $role], true, "AND", "sort", SORT_ASC);
	}

	private function note_field_map(Controller $ctl, string $tb_name): array {
		$map = [];
		$db_list = $ctl->db("db", "db")->select("tb_name", $tb_name);
		if (count($db_list) === 0) {
			return $map;
		}
		$db_id = (int) ($db_list[0]["id"] ?? 0);
		foreach ($ctl->db("db_fields", "db")->select("db_id", $db_id) as $field) {
			$name = (string) ($field["parameter_name"] ?? "");
			if ($name !== "") {
				$map[$name] = $field;
			}
		}
		return $map;
	}

	private function build_input_data(Controller $ctl, string $table, array $field_rows, array $args, bool $require_required, array &$uploads): array {
		$data = [];
		$field_map = $this->note_field_map($ctl, $table);
		foreach ($field_rows as $field) {
			$name = (string) ($field["field_name"] ?? "");
			if ($name === "") {
				continue;
			}
			$db_field = $field_map[$name] ?? [];
			if (!array_key_exists($name, $args)) {
				if ($require_required && (int) ($field["required"] ?? 0) === 1) {
					throw new Exception("Required field missing: " . $name);
				}
				continue;
			}
			if ($this->is_upload_db_field($db_field)) {
				$upload = $this->normalize_mcp_upload_argument($name, $args[$name], $db_field);
				if ($upload === null) {
					if ($require_required && (int) ($field["required"] ?? 0) === 1) {
						throw new Exception("Required file missing: " . $name);
					}
					continue;
				}
				$uploads[$name] = $upload;
				continue;
			}
			$data[$name] = $args[$name];
		}
		if (count($data) === 0 && count($uploads) === 0) {
			throw new Exception("No writable fields were provided.");
		}
		return $data;
	}

	private function is_upload_db_field(array $field): bool {
		return in_array((string) ($field["type"] ?? ""), ["file", "image"], true);
	}

	private function normalize_mcp_upload_argument(string $field_name, $value, array $db_field): ?array {
		if ($value === null || $value === "") {
			return null;
		}

		$filename = "";
		$mime_type = "";
		$encoded = "";
		if (is_array($value)) {
			$filename = (string) ($value["filename"] ?? ($value["name"] ?? ""));
			$mime_type = (string) ($value["mime_type"] ?? ($value["content_type"] ?? ""));
			$data_url = trim((string) ($value["data_url"] ?? ""));
			if ($data_url !== "") {
				$parsed = $this->parse_mcp_data_url($field_name, $data_url);
				$mime_type = $mime_type !== "" ? $mime_type : $parsed["mime_type"];
				$encoded = $parsed["content_base64"];
			} else {
				foreach (["content_base64", "base64", "data_base64", "content"] as $key) {
					if (isset($value[$key]) && !is_array($value[$key]) && !is_object($value[$key])) {
						$encoded = trim((string) $value[$key]);
						break;
					}
				}
			}
		} elseif (!is_object($value)) {
			$encoded = trim((string) $value);
		}

		if ($encoded !== "" && strpos($encoded, "data:") === 0) {
			$parsed = $this->parse_mcp_data_url($field_name, $encoded);
			$mime_type = $mime_type !== "" ? $mime_type : $parsed["mime_type"];
			$encoded = $parsed["content_base64"];
		}
		if ($encoded === "") {
			throw new Exception("File content is required for field: " . $field_name);
		}
		$content = base64_decode(preg_replace('/\s+/', '', $encoded), true);
		if ($content === false || $content === "") {
			throw new Exception("Invalid base64 file content for field: " . $field_name);
		}
		if ($mime_type === "") {
			$mime_type = $this->detect_mime_from_buffer($content);
		}
		$filename = $this->sanitize_mcp_filename($filename);
		if ($filename === "") {
			$filename = $field_name . $this->extension_for_mime($mime_type, (string) ($db_field["type"] ?? ""));
		} elseif (pathinfo($filename, PATHINFO_EXTENSION) === "") {
			$filename .= $this->extension_for_mime($mime_type, (string) ($db_field["type"] ?? ""));
		}
		return [
			"field" => $db_field,
			"filename" => $filename,
			"mime_type" => $mime_type,
			"content" => $content,
		];
	}

	private function parse_mcp_data_url(string $field_name, string $data_url): array {
		if (!preg_match('#^data:([^;,]*)(;base64)?,(.*)$#s', $data_url, $matches)) {
			throw new Exception("Invalid data URL for field: " . $field_name);
		}
		if (($matches[2] ?? "") !== ";base64") {
			throw new Exception("Only base64 data URLs are supported for field: " . $field_name);
		}
		return [
			"mime_type" => trim((string) ($matches[1] ?? "")),
			"content_base64" => trim((string) ($matches[3] ?? "")),
		];
	}

	private function sanitize_mcp_filename(string $filename): string {
		$filename = str_replace("\0", "", $filename);
		$filename = str_replace(["\r", "\n"], "", $filename);
		$filename = str_replace("\\", "/", $filename);
		$parts = explode("/", $filename);
		$base = trim((string) end($parts));
		if ($base === "." || $base === "..") {
			return "";
		}
		return $base;
	}

	private function detect_mime_from_buffer(string $content): string {
		if (class_exists("finfo")) {
			$fi = new finfo(FILEINFO_MIME_TYPE);
			$mime = $fi->buffer($content);
			if (is_string($mime) && $mime !== "") {
				return $mime;
			}
		}
		return "application/octet-stream";
	}

	private function extension_for_mime(string $mime_type, string $field_type): string {
		$mime_type = strtolower(trim($mime_type));
		$map = [
			"image/jpeg" => ".jpg",
			"image/png" => ".png",
			"image/gif" => ".gif",
			"image/webp" => ".webp",
			"image/svg+xml" => ".svg",
			"application/pdf" => ".pdf",
			"text/plain" => ".txt",
			"text/csv" => ".csv",
			"application/json" => ".json",
		];
		if (isset($map[$mime_type])) {
			return $map[$mime_type];
		}
		return $field_type === "image" ? ".img" : ".bin";
	}

	private function save_mcp_uploads(Controller $ctl, FFM $ffm, string $table, array $row, array $uploads): array {
		$new_file_ids = [];
		$old_file_ids = [];
		$updated_row = $row;
		try {
			foreach ($uploads as $name => $upload) {
				$old_file_id = (int) ($row[$name] ?? 0);
				$new_file_id = $this->store_mcp_upload($ctl, $table, (int) ($row["id"] ?? 0), $upload);
				if ($old_file_id > 0) {
					$old_file_ids[] = $old_file_id;
				}
				$new_file_ids[] = $new_file_id;
				$updated_row[$name] = $new_file_id;
			}
			$ffm->update($updated_row);
			foreach ($old_file_ids as $old_file_id) {
				$ctl->delete_file($old_file_id);
			}
		} catch (Throwable $e) {
			foreach ($new_file_ids as $new_file_id) {
				$ctl->delete_file((int) $new_file_id);
			}
			throw $e;
		}
		$fresh = $ffm->get((int) ($updated_row["id"] ?? 0));
		return is_array($fresh) ? $fresh : $updated_row;
	}

	private function store_mcp_upload(Controller $ctl, string $table, int $row_id, array $upload): int {
		$field = is_array($upload["field"] ?? null) ? $upload["field"] : [];
		$file = [
			"filename" => (string) ($upload["filename"] ?? "upload.bin"),
		];
		$file_id = 0;
		try {
			$ffm_upload = $ctl->db("file", "upload");
			$ffm_upload->insert($file);
			$file_id = (int) ($file["id"] ?? 0);
			$file["path"] = "upload_file_" . $file["id"];
			$file["path_th"] = $file["path"] . "_th";
			$file["table_identifer"] = $ctl->db($table)->get_identifier();
			$file["row_id"] = $row_id;
			$ffm_upload->update($file);

			$ctl->save_file($file["path"], (string) ($upload["content"] ?? ""));
			if ((string) ($field["type"] ?? "") === "image") {
				$image_width = $field["image_width"] ?? null;
				$image_width_thumbnail = $field["image_width_thumbnail"] ?? null;
				if ($image_width !== null) {
					$ctl->resize_saved_image($file["path"], $file["path"], $image_width);
				}
				if ($image_width_thumbnail !== null) {
					$ctl->resize_saved_image($file["path"], $file["path_th"], $image_width_thumbnail);
				}
			}
			return (int) $file["id"];
		} catch (Throwable $e) {
			if ($file_id > 0) {
				$ctl->delete_file($file_id);
			}
			throw $e;
		}
	}

	private function project_row(Controller $ctl, array $row, array $fields, array $field_map): array {
		$item = ["id" => (int) ($row["id"] ?? 0)];
		foreach ($fields as $field) {
			$db_field = $field_map[$field] ?? [];
			if ($this->is_upload_db_field($db_field)) {
				$item[$field] = $this->project_upload_value($ctl, $db_field, $row[$field] ?? "");
			} else {
				$item[$field] = $row[$field] ?? "";
			}
		}
		return $item;
	}

	private function project_upload_value(Controller $ctl, array $field, $value) {
		$file_id = (int) $value;
		if ($file_id <= 0) {
			return null;
		}
		$file = $ctl->db("file", "upload")->get($file_id);
		if (!is_array($file) || empty($file["id"])) {
			return [
				"id" => $file_id,
				"missing" => true,
			];
		}
		$path = (string) ($file["path"] ?? "");
		$filename = (string) ($file["filename"] ?? "");
		$out = [
			"id" => (int) ($file["id"] ?? 0),
			"filename" => $filename,
		];
		$filepath = $path === "" ? "" : $ctl->get_saved_filepath($path);
		if ($filepath !== "" && is_file($filepath)) {
			$out["size"] = filesize($filepath);
			$out["mime_type"] = $this->detect_mime_for_path($filepath);
			$out["download_url"] = $ctl->get_public_media_url($path, $filename, "file");
		} else {
			$out["missing"] = true;
		}
		if ((string) ($field["type"] ?? "") === "image" && $path !== "") {
			$out["view_url"] = $ctl->get_public_media_url($path, $filename, "image");
			$path_th = (string) ($file["path_th"] ?? "");
			if ($path_th !== "" && $ctl->is_saved_file($path_th)) {
				$out["thumbnail_url"] = $ctl->get_public_media_url($path_th, $filename, "image");
			}
		}
		return $out;
	}

	private function detect_mime_for_path(string $filepath): string {
		if (class_exists("finfo")) {
			$fi = new finfo(FILEINFO_MIME_TYPE);
			$mime = $fi->file($filepath);
			if (is_string($mime) && $mime !== "") {
				return $mime;
			}
		}
		$mime = function_exists("mime_content_type") ? mime_content_type($filepath) : "";
		if (is_string($mime) && $mime !== "") {
			return $mime;
		}
		return "application/octet-stream";
	}

	private function row_matches_query(Controller $ctl, array $row, array $fields, string $query, array $field_map): bool {
		$query = mb_strtolower($query, "UTF-8");
		foreach ($fields as $field) {
			$value = mb_strtolower($this->searchable_field_value($ctl, $row, $field, $field_map[$field] ?? []), "UTF-8");
			if ($value !== "" && mb_strpos($value, $query, 0, "UTF-8") !== false) {
				return true;
			}
		}
		return false;
	}

	private function searchable_field_value(Controller $ctl, array $row, string $field, array $db_field): string {
		if (!$this->is_upload_db_field($db_field)) {
			$value = $row[$field] ?? "";
			return is_array($value) || is_object($value) ? "" : (string) $value;
		}
		$file_id = (int) ($row[$field] ?? 0);
		if ($file_id <= 0) {
			return "";
		}
		$file = $ctl->db("file", "upload")->get($file_id);
		return is_array($file) ? (string) ($file["filename"] ?? "") : "";
	}

	private function required_id(array $args): int {
		$id = (int) ($args["id"] ?? 0);
		if ($id <= 0) {
			throw new Exception("id is required.");
		}
		return $id;
	}

	private function tool_scope(array $server, array $tool): string {
		$scope = trim((string) ($tool["required_scope"] ?? ""));
		if ($scope !== "") {
			return $scope;
		}
		return trim((string) ($server["default_scope"] ?? ""));
	}

	private function supported_scopes(?array $server = null): array {
		$scopes = [];
		$server = $server ?? $this->get_server();
		foreach ($this->split_scopes((string) ($server["default_scope"] ?? "")) as $scope) {
			$scopes[$scope] = true;
		}
		foreach ($this->registered_functions() as $function) {
			foreach ($this->split_scopes((string) ($function["required_scope"] ?? "")) as $scope) {
				$scopes[$scope] = true;
			}
		}
		return array_values(array_keys($scopes));
	}

	private function split_scopes(string $scope): array {
		$result = [];
		foreach (preg_split('/\s+/', trim($scope)) as $s) {
			if ($s !== "") {
				$result[] = $s;
			}
		}
		return $result;
	}

	private function scope_allows(string $granted, string $required): bool {
		$required_scopes = $this->split_scopes($required);
		if (count($required_scopes) === 0) {
			return true;
		}
		$granted_map = array_flip($this->split_scopes($granted));
		foreach ($required_scopes as $scope) {
			if (!isset($granted_map[$scope])) {
				return false;
			}
		}
		return true;
	}

	private function oauth_authorize_params(Controller $ctl): array {
		$params = [
			"server" => $this->request_server_key($ctl),
			"response_type" => $this->oauth_request_param($ctl, "response_type"),
			"client_id" => $this->oauth_request_param($ctl, "client_id"),
			"redirect_uri" => $this->oauth_request_param($ctl, "redirect_uri"),
			"scope" => $this->oauth_request_param($ctl, "scope"),
			"state" => $this->oauth_request_param($ctl, "state"),
			"code_challenge" => $this->oauth_request_param($ctl, "code_challenge"),
			"code_challenge_method" => $this->oauth_request_param($ctl, "code_challenge_method"),
			"resource" => $this->oauth_request_param($ctl, "resource"),
		];
		if ($params["response_type"] === "" && $params["client_id"] !== "" && $params["redirect_uri"] !== "") {
			$params["response_type"] = "code";
		}
		return $params;
	}

	private function oauth_request_param(Controller $ctl, string $key): string {
		$value = $ctl->GET($key);
		if ($value === null || $value === "") {
			$value = $ctl->POST($key);
		}
		if ($value === null || $value === "") {
			$uri_params = $this->oauth_request_uri_params();
			$value = $uri_params[$key] ?? "";
		}
		if (is_array($value)) {
			return "";
		}
		return trim((string) $value);
	}

	private function oauth_request_uri_params(): array {
		$params = [];
		$query = (string) ($_SERVER["QUERY_STRING"] ?? "");
		if ($query !== "") {
			parse_str($query, $query_params);
			if (is_array($query_params)) {
				$params = array_merge($params, $query_params);
			}
		}
		$path = (string) parse_url((string) ($_SERVER["REQUEST_URI"] ?? ""), PHP_URL_PATH);
		if (preg_match('#\*[A-Za-z0-9_]+&(.*)$#', $path, $matches)) {
			parse_str($matches[1], $path_params);
			if (is_array($path_params)) {
				$params = array_merge($params, $path_params);
			}
		}
		return $params;
	}

	private function validate_authorize_params(array $params, ?Controller $ctl = null): string {
		if ($params["response_type"] !== "code") {
			return "response_type must be code.";
		}
		if ($params["client_id"] === "") {
			return "client_id is required.";
		}
		if ($params["redirect_uri"] === "" || !preg_match('#^https?://#i', $params["redirect_uri"])) {
			return "redirect_uri must be http or https URL.";
		}
		if ($params["code_challenge_method"] !== "" && !in_array($params["code_challenge_method"], ["S256", "plain"], true)) {
			return "Unsupported code_challenge_method.";
		}
		if ($ctl !== null && ($params["resource"] ?? "") !== "" && $params["resource"] !== $this->resource_url($ctl, $this->current_server($ctl))) {
			return "resource is invalid.";
		}
		return "";
	}

	private function read_json_request() {
		$raw = file_get_contents("php://input");
		$content_type = (string) ($_SERVER["CONTENT_TYPE"] ?? "");
		if (stripos($content_type, "application/json") !== false && is_string($raw) && trim($raw) !== "") {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				return $decoded;
			}
			return null;
		}
		if (isset($_POST["payload"])) {
			$decoded = json_decode((string) $_POST["payload"], true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
		if (isset($_POST["jsonrpc"]) || isset($_POST["method"])) {
			return $_POST;
		}
		return null;
	}

	private function read_oauth_params(): array {
		$raw = file_get_contents("php://input");
		$content_type = (string) ($_SERVER["CONTENT_TYPE"] ?? "");
		if (stripos($content_type, "application/json") !== false && is_string($raw) && trim($raw) !== "") {
			$decoded = json_decode($raw, true);
			return is_array($decoded) ? $decoded : [];
		}
		return $_POST;
	}

	private function bearer_token(): string {
		$header = (string) ($_SERVER["HTTP_AUTHORIZATION"] ?? $_SERVER["Authorization"] ?? "");
		if ($header === "" && function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$header = (string) ($headers["Authorization"] ?? $headers["authorization"] ?? "");
		}
		if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
			return trim($m[1]);
		}
		return "";
	}

	private function verify_pkce(array $auth_code, string $verifier): bool {
		$challenge = (string) ($auth_code["code_challenge"] ?? "");
		if ($challenge === "") {
			return true;
		}
		if ($verifier === "") {
			return false;
		}
		$method = (string) ($auth_code["code_challenge_method"] ?? "plain");
		if ($method === "S256") {
			return hash_equals($challenge, $this->base64url(hash("sha256", $verifier, true)));
		}
		return hash_equals($challenge, $verifier);
	}

	private function random_token(): string {
		return $this->base64url(random_bytes(32));
	}

	private function base64url(string $data): string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	private function append_query(string $url, array $params): string {
		$query = [];
		foreach ($params as $key => $value) {
			if ($value !== "") {
				$query[$key] = $value;
			}
		}
		if (count($query) === 0) {
			return $url;
		}
		return $url . (strpos($url, "?") === false ? "?" : "&") . http_build_query($query);
	}

	private function log_call(array $server, array $tool, ?McpSubject $subject, string $method, array $request, string $status, string $error): void {
		$request_json = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($request_json === false) {
			$request_json = "";
		}
		if (mb_strlen($request_json, "UTF-8") > 1800) {
			$request_json = mb_substr($request_json, 0, 1800, "UTF-8") . "...";
		}
		$row = [
			"server_id" => (int) ($server["id"] ?? 0),
			"tool_id" => (int) ($tool["id"] ?? 0),
			"user_id" => $subject instanceof McpSubject ? $subject->userId() : 0,
			"subject_type" => $subject instanceof McpSubject ? $subject->type() : "",
			"subject_id" => $subject instanceof McpSubject ? $subject->id() : 0,
			"subject_label" => $subject instanceof McpSubject ? $subject->label() : "",
			"method" => $method,
			"tool_name" => (string) ($tool["function_name"] ?? ($tool["tool_name"] ?? "")),
			"request_json" => $request_json,
			"result_status" => $status,
			"error_message" => $error,
			"created_at" => time(),
		];
		$this->ffm_logs->insert($row);
	}

	/**
	 * Tool execution must always return an MCP response even if an integration
	 * has closed a Controller-managed FFM handle while calling an external API.
	 */
	private function safe_log_call(array $server, array $tool, ?McpSubject $subject, string $method, array $request, string $status, string $error): void {
		try {
			$this->log_call($server, $tool, $subject, $method, $request, $status, $error);
		} catch (Throwable $e) {
			error_log("MCP call log failure: " . $e->getMessage());
		}
	}

	private function tool_error(string $message): array {
		return [
			"isError" => true,
			"content" => [[
				"type" => "text",
				"text" => $message,
			]],
			"structuredContent" => [
				"ok" => false,
				"error" => $message,
			],
		];
	}

	private function tool_auth_error(Controller $ctl, array $server, string $error): array {
		return [
			"isError" => true,
			"content" => [[
				"type" => "text",
				"text" => "Authentication required: " . $error,
			]],
			"structuredContent" => [
				"ok" => false,
				"error" => $error,
			],
			"_meta" => [
				"mcp/www_authenticate" => [
					$this->www_authenticate_value($ctl, $server, $error),
				],
			],
		];
	}

	private function oauth_issuer(Controller $ctl, ?array $server = null): string {
		return $this->app_base_url($ctl);
	}

	private function oauth_protected_resource_url(Controller $ctl, ?array $server = null): string {
		return $this->mcp_url($ctl, "oauth_protected_resource", $server ?? $this->current_server($ctl));
	}

	private function normalize_oauth_resource(Controller $ctl, string $resource): string {
		$resource = trim($resource);
		if ($resource === "") {
			return $this->resource_url($ctl, $this->current_server($ctl));
		}
		return $resource;
	}

	private function resource_url(Controller $ctl, array $server): string {
		return $this->mcp_url($ctl, "rpc", $server);
	}

	private function mcp_url(Controller $ctl, string $function, array $server): string {
		return $ctl->get_APP_URL("mcp_server", $function);
	}

	private function app_base_url(Controller $ctl): string {
		$resource = $ctl->get_APP_URL("mcp_server", "rpc");
		$suffix = "/mcp_server*rpc";
		if (substr($resource, -strlen($suffix)) === $suffix) {
			return substr($resource, 0, -strlen($suffix));
		}
		return rtrim(preg_replace('#/mcp_server\*rpc(?:[?&].*)?$#', "", $resource), "/");
	}

	private function current_request_url(): string {
		$scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
		$host = (string) ($_SERVER["HTTP_HOST"] ?? "");
		$uri = (string) ($_SERVER["REQUEST_URI"] ?? "");
		return $scheme . "://" . $host . $uri;
	}

	private function respond_oauth_http_challenge(Controller $ctl, array $server, string $error): void {
		http_response_code(401);
		header('WWW-Authenticate: ' . $this->www_authenticate_value($ctl, $server, $error));
		$this->respond_json([
			"ok" => false,
			"error" => $error,
			"resource_metadata" => $this->oauth_protected_resource_url($ctl, $server),
		]);
	}

	private function www_authenticate_value(Controller $ctl, array $server, string $error): string {
		return 'Bearer resource_metadata="' . $this->oauth_protected_resource_url($ctl, $server) . '", error="' . $error . '", error_description="OAuth authorization is required."';
	}

	private function json_result($id, $result): array {
		return [
			"jsonrpc" => "2.0",
			"id" => $id,
			"result" => $result,
		];
	}

	private function json_error($id, int $code, string $message): array {
		return [
			"jsonrpc" => "2.0",
			"id" => $id,
			"error" => [
				"code" => $code,
				"message" => $message,
			],
		];
	}

	private function respond_json(array $payload): void {
		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}

}
