<?php

class mcp_service_action implements McpActionInterface {
	public function getInputSchema(Controller $ctl, array $tool): array {
		return [
			"type" => "object",
			"properties" => [
				"operation" => [
					"type" => "string",
					"enum" => ["list", "create_item", "update_item", "delete_item"],
				],
				"item_id" => McpInputValidator::integerSchema("Target item id."),
				"title" => McpInputValidator::stringSchema("Item title.", ["maxLength" => 200]),
				"body" => ["type" => "string"],
				"status" => McpInputValidator::enumSchema("Item status.", ["values" => ["active", "archived"]]),
				"limit" => McpInputValidator::integerSchema("Maximum rows to return.", ["minimum" => 1, "maximum" => 100]),
				"confirm" => ["type" => "boolean", "description" => "Required true for delete_item."],
			],
			"required" => ["operation"],
			"additionalProperties" => false,
		];
	}

	public function execute(Controller $ctl, McpActionRequest $request): McpActionResult {
		$member = $this->resolveMember($ctl, $request);
		$memberId = (int) ($member["id"] ?? 0);
		if ($memberId <= 0) {
			throw new Exception("Service member could not be resolved.");
		}

		$operation = McpInputValidator::enum($request, "operation", [
			"required" => true,
			"values" => ["list", "create_item", "update_item", "delete_item"],
		]);
		if ($operation === "list") {
			return $this->listItems($ctl, $memberId, McpInputValidator::integer($request, "limit", [
				"default" => 20,
				"minimum" => 1,
				"maximum" => 100,
			]));
		}
		if ($operation === "create_item") {
			return $this->createItem($ctl, $memberId, $request);
		}
		if ($operation === "update_item") {
			return $this->updateItem($ctl, $memberId, $request);
		}
		if ($operation === "delete_item") {
			if (!$request->bool("confirm", false)) {
				throw new Exception("ToolError: delete_item requires confirm=true.");
			}
			return $this->deleteItem($ctl, $memberId, McpInputValidator::integer($request, "item_id", ["required" => true, "minimum" => 1]));
		}
		throw new Exception("Unknown operation: " . $operation);
	}

	private function resolveMember(Controller $ctl, McpActionRequest $request): array {
		$subjectType = $request->subjectType();
		$subjectId = $request->subjectId();
		if ($subjectType === "" || $subjectId <= 0) {
			throw new Exception("MCP subject is required.");
		}

		$db = $ctl->db("service_member");
		if ($subjectType === "service_member") {
			$row = $db->get($subjectId);
			if (is_array($row) && !empty($row)) {
				return $row;
			}
		}

		$rows = $db->select(["subject_type", "subject_id"], [$subjectType, $subjectId], true, "AND");
		if (!empty($rows)) {
			return $rows[0];
		}

		if ($subjectType === "fbp_user") {
			$rows = $db->select("fbp_user_id", $subjectId);
			if (!empty($rows)) {
				return $rows[0];
			}
		}

		$label = $request->subjectLabel();
		if ($label === "") {
			$label = $subjectType . "#" . $subjectId;
		}
		$insert = [
			"display_name" => $label,
			"email" => "",
			"password_hash" => "",
			"status" => "1",
			"subject_type" => $subjectType,
			"subject_id" => $subjectId,
			"fbp_user_id" => $subjectType === "fbp_user" ? $subjectId : 0,
			"created_at" => date("Y-m-d H:i:s"),
			"updated_at" => date("Y-m-d H:i:s"),
		];
		$id = $db->insert($insert);
		return $db->get((int) $id);
	}

	private function listItems(Controller $ctl, int $memberId, int $limit): McpActionResult {
		$rows = $ctl->db("service_item")->select("parent_id", $memberId, true, "AND", "id", SORT_DESC, $limit);
		$items = array_values($rows);
		return McpActionResult::success("Items loaded.", ["items" => $items, "count" => count($items)]);
	}

	private function createItem(Controller $ctl, int $memberId, McpActionRequest $request): McpActionResult {
		$title = McpInputValidator::string($request, "title", ["required" => true, "maxLength" => 200]);
		$insert = [
			"parent_id" => $memberId,
			"title" => $title,
			"body" => $request->string("body"),
			"status" => $this->normalizeStatus(McpInputValidator::enum($request, "status", [
				"default" => "active",
				"values" => ["active", "archived"],
			])),
			"created_at" => date("Y-m-d H:i:s"),
			"updated_at" => date("Y-m-d H:i:s"),
		];
		$id = $ctl->db("service_item")->insert($insert);
		$item = $ctl->db("service_item")->get((int) $id);
		return McpActionResult::success("Item created.", ["id" => (int) $id, "item" => $item]);
	}

	private function updateItem(Controller $ctl, int $memberId, McpActionRequest $request): McpActionResult {
		$id = $this->ownedItemId($ctl, $memberId, McpInputValidator::integer($request, "item_id", ["required" => true, "minimum" => 1]));
		if ($id <= 0) {
			throw new Exception("Item was not found or is not owned by this member.");
		}
		$row = $ctl->db("service_item")->get($id);
		if ($request->has("title")) {
			$row["title"] = McpInputValidator::string($request, "title", ["required" => true, "maxLength" => 200]);
		}
		if ($request->has("body")) {
			$row["body"] = $request->string("body");
		}
		if ($request->has("status")) {
			$row["status"] = $this->normalizeStatus(McpInputValidator::enum($request, "status", [
				"required" => true,
				"values" => ["active", "archived"],
			]));
		}
		$row["updated_at"] = date("Y-m-d H:i:s");
		$ctl->db("service_item")->update($row);
		$item = $ctl->db("service_item")->get($id);
		return McpActionResult::success("Item updated.", ["id" => $id, "item" => $item]);
	}

	private function deleteItem(Controller $ctl, int $memberId, int $id): McpActionResult {
		$id = $this->ownedItemId($ctl, $memberId, $id);
		if ($id <= 0) {
			throw new Exception("Item was not found or is not owned by this member.");
		}
		$ctl->db("service_item")->delete($id);
		return McpActionResult::success("Item deleted.", ["id" => $id, "deleted" => true]);
	}

	private function ownedItemId(Controller $ctl, int $memberId, int $id): int {
		if ($id <= 0) {
			return 0;
		}
		$row = $ctl->db("service_item")->get($id);
		if (!is_array($row) || (int) ($row["parent_id"] ?? 0) !== $memberId) {
			return 0;
		}
		return $id;
	}

	private function normalizeStatus(string $status): string {
		if ($status === "archived" || $status === "2") {
			return "2";
		}
		return "1";
	}
}
