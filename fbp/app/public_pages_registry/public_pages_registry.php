<?php

class public_pages_registry {

	private $fmt_registry;
	private $enabled_opt = [
		0 => "Hide",
		1 => "Show",
	];
	private $menu_opt = [
		0 => "Hide",
		1 => "Show",
	];

	function __construct(Controller $ctl) {
		$this->fmt_registry = $ctl->db("public_pages_registry", "public_pages_registry");
		$ctl->assign("admin_link_role_opt", [
			"" => $ctl->t("public_pages_registry.role_both"),
			"admin" => "admin",
			"user" => "user",
		]);
		$ctl->assign("link_requires_params_opt", [
			0 => $ctl->t("public_pages_registry.link_fixed"),
			1 => $ctl->t("public_pages_registry.link_parameters"),
		]);
		$ctl->assign("enabled_opt", [
			0 => $ctl->t("common.hide"),
			1 => $ctl->t("common.show"),
		]);
		$ctl->assign("menu_opt", [
			0 => $ctl->t("common.hide"),
			1 => $ctl->t("common.show"),
		]);
	}

	function page(Controller $ctl) {
		$items = $this->fmt_registry->getall("sort", SORT_ASC);
		foreach ($items as &$item) {
			$function_name = trim((string) ($item["function_name"] ?? ""));
			$item["route_url"] = $function_name !== "" && (int) ($item["link_requires_params"] ?? 0) !== 1 ? $ctl->get_APP_URL("public_pages", $function_name) : "";
		}
		unset($item);
		$ctl->assign("items", $items);
		$ctl->reload_area("#tabs-public-pages", "index.tpl");
	}

	function add(Controller $ctl) {
		$post = $ctl->POST();
		if (!isset($post["enabled"])) {
			$post["enabled"] = 1;
		}
		$ctl->assign("post", $post);
		$ctl->show_multi_dialog("public_pages_registry_add", "add.tpl", $ctl->t("public_pages_registry.dialog.add"), 920, true, true);
	}

	function add_exe(Controller $ctl) {
		$post = $this->normalize_post($ctl->POST());
		$errors = $this->validate_registry($ctl, $post, "add");
		if (count($errors) > 0) {
			$ctl->clear_error_message();
			foreach ($errors as $field => $message) {
				$ctl->res_error_message($field, $message);
			}
			return;
		}

		$post["sort"] = $this->next_sort();
		$post["menu_sort"] = $this->normalize_menu_sort($post["menu_sort"] ?? null);
		$post["updated_at"] = time();
		$this->fmt_registry->insert($post);

		$ctl->close_multi_dialog("public_pages_registry_add");
		$ctl->invoke("page");
		$ctl->invoke("show_menu", null, "base");
	}

	function edit(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->fmt_registry->get($id);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("public_pages_registry_edit_" . $id, "edit.tpl", $ctl->t("public_pages_registry.dialog.edit"), 920, true, true);
	}

	function edit_exe(Controller $ctl) {
		$post = $this->normalize_post($ctl->POST());
		$id = (int) ($post["id"] ?? 0);
		$errors = $this->validate_registry($ctl, $post, "edit");
		if (count($errors) > 0) {
			$ctl->clear_error_message();
			foreach ($errors as $field => $message) {
				$ctl->res_error_message($field, $message);
			}
			return;
		}

		$data = $this->fmt_registry->get($id);
		if (empty($data)) {
			return;
		}
		$data["function_name"] = $post["function_name"];
		$data["title"] = $post["title"];
		$data["enabled"] = (int) $post["enabled"];
		$data["show_in_menu"] = (int) $post["show_in_menu"];
		$data["show_in_admin_links"] = $post["show_in_admin_links"];
		$data["admin_link_role"] = $post["admin_link_role"];
		$data["link_requires_params"] = $post["link_requires_params"];
		$data["menu_label"] = $post["menu_label"];
		$data["updated_at"] = time();
		$this->fmt_registry->update($data);

		$ctl->close_multi_dialog("public_pages_registry_edit_" . $id);
		$ctl->invoke("page");
		$ctl->invoke("show_menu", null, "base");
	}

	function delete(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->fmt_registry->get($id);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("public_pages_registry_delete_" . $id, "delete.tpl", $ctl->t("public_pages_registry.dialog.delete"), 520, true, true);
	}

	function delete_exe(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$this->fmt_registry->delete($id);
		$ctl->close_multi_dialog("public_pages_registry_delete_" . $id);
		$ctl->invoke("page");
		$ctl->invoke("show_menu", null, "base");
	}

	function sort(Controller $ctl) {
		$post = $ctl->POST();
		$logArr = explode(',', (string) ($post["log"] ?? ""));
		$c = 0;
		foreach ($logArr as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$d = $this->fmt_registry->get($id);
			if (empty($d)) {
				continue;
			}
			$d["sort"] = $c;
			$d["updated_at"] = time();
			$this->fmt_registry->update($d);
			$c++;
		}
	}

	private function validate_registry(Controller $ctl, array $post, string $mode): array {
		$errors = [];
		$id = (int) ($post["id"] ?? 0);
		$function_name = trim((string) ($post["function_name"] ?? ""));
		$title = trim((string) ($post["title"] ?? ""));

		if ($function_name === "") {
			$errors["function_name"] = $ctl->t("public_pages_registry.validation.function_name_required");
		} elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $function_name)) {
			$errors["function_name"] = $ctl->t("public_pages_registry.validation.function_name_format");
		}
		$is_unique = $ctl->validate_duplicate(
			"public_pages_registry",
			["function_name"],
			[$function_name],
			$mode === "edit" ? $id : 0,
			"public_pages_registry"
		);
		if (!$is_unique) {
			$errors["function_name"] = $ctl->t("public_pages_registry.validation.function_name_exists");
		}
		if ($title === "") {
			$errors["title"] = $ctl->t("public_pages_registry.validation.title_required");
		}
		$menu_sort = $this->normalize_menu_sort($post["menu_sort"] ?? null);
		if ($menu_sort < 0) {
			$errors["menu_sort"] = $ctl->t("public_pages_registry.validation.menu_sort");
		}
		if (!in_array($post["admin_link_role"], ["", "admin", "user"], true)) {
			$errors["admin_link_role"] = $ctl->t("public_pages_registry.validation.admin_link_role");
		}
		foreach (["show_in_admin_links", "link_requires_params"] as $field) {
			if (!in_array($post[$field], ["0", "1"], true)) {
				$errors[$field] = $ctl->t("public_pages_registry.validation.link_setting");
			}
		}
		return $errors;
	}

	private function normalize_post(array $post): array {
		return [
			"id" => (int) ($post["id"] ?? 0),
			"function_name" => trim((string) ($post["function_name"] ?? "")),
			"title" => trim((string) ($post["title"] ?? "")),
			"template_name" => trim((string) ($post["template_name"] ?? "")),
			"route_type" => "standard",
			"uses_common_layout" => 1,
			"generated_by_wizard" => 0,
			"delete_allowed" => 1,
			"enabled" => isset($post["enabled"]) ? (int) $post["enabled"] : 1,
			"show_in_menu" => isset($post["show_in_menu"]) ? (int) $post["show_in_menu"] : 0,
			"show_in_admin_links" => (string) ($post["show_in_admin_links"] ?? "1"),
			"admin_link_role" => trim((string) ($post["admin_link_role"] ?? "")),
			"link_requires_params" => (string) ($post["link_requires_params"] ?? "0"),
			"menu_label" => trim((string) ($post["menu_label"] ?? "")),
			"menu_sort" => $this->normalize_menu_sort($post["menu_sort"] ?? null),
			"notes" => "",
		];
	}

	private function next_sort(): int {
		$list = $this->fmt_registry->getall("sort", SORT_DESC);
		if (count($list) === 0) {
			return 0;
		}
		return (int) ($list[0]["sort"] ?? 0) + 1;
	}

	private function normalize_menu_sort($value): int {
		if ($value === null || $value === "") {
			return $this->next_sort();
		}
		return max(0, (int) $value);
	}

}
