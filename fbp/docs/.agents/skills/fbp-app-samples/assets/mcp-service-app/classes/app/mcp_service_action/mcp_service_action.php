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
				"item_id" => ["type" => "integer"],
				"title" => ["type" => "string"],
				"body" => ["type" => "string"],
				"status" => ["type" => "string", "enum" => ["active", "archived"]],
				"limit" => ["type" => "integer", "minimum" => 1, "maximum" => 100],
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

		$operation = $request->string("operation", "list");
		if ($operation === "list") {
			return $this->listItems($ctl, $memberId, $request->int("limit", 20));
		}
		if ($operation === "create_item") {
			return $this->createItem($ctl, $memberId, $request);
		}
		if ($operation === "update_item") {
			return $this->updateItem($ctl, $memberId, $request);
		}
		if ($operation === "delete_item") {
			return $this->deleteItem($ctl, $memberId, $request->int("item_id", 0));
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
		return McpActionResult::success("Items loaded.", ["items" => array_values($rows)]);
	}

	private function createItem(Controller $ctl, int $memberId, McpActionRequest $request): McpActionResult {
		$title = $request->string("title");
		if ($title === "") {
			throw new Exception("title is required.");
		}
		$insert = [
			"parent_id" => $memberId,
			"title" => $title,
			"body" => $request->string("body"),
			"status" => $this->normalizeStatus($request->string("status", "active")),
			"created_at" => date("Y-m-d H:i:s"),
			"updated_at" => date("Y-m-d H:i:s"),
		];
		$id = $ctl->db("service_item")->insert($insert);
		return McpActionResult::success("Item created.", ["id" => (int) $id]);
	}

	private function updateItem(Controller $ctl, int $memberId, McpActionRequest $request): McpActionResult {
		$id = $this->ownedItemId($ctl, $memberId, $request->int("item_id", 0));
		if ($id <= 0) {
			throw new Exception("Item was not found or is not owned by this member.");
		}
		$row = $ctl->db("service_item")->get($id);
		foreach (["title", "body", "status"] as $field) {
			if ($request->has($field)) {
				$row[$field] = $field === "status"
					? $this->normalizeStatus($request->string($field))
					: $request->string($field);
			}
		}
		$row["updated_at"] = date("Y-m-d H:i:s");
		$ctl->db("service_item")->update($row);
		return McpActionResult::success("Item updated.", ["id" => $id]);
	}

	private function deleteItem(Controller $ctl, int $memberId, int $id): McpActionResult {
		$id = $this->ownedItemId($ctl, $memberId, $id);
		if ($id <= 0) {
			throw new Exception("Item was not found or is not owned by this member.");
		}
		$ctl->db("service_item")->delete($id);
		return McpActionResult::success("Item deleted.", ["id" => $id]);
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
