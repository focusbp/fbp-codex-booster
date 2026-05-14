<?php

class public_pages {

	private const PAGE_SIZE = 20;

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function index(Controller $ctl) {
		$this->shop($ctl);
	}

	function account(Controller $ctl) {
		$context = $this->resolve_public_context($ctl);
		if ($context["userid"] === "") {
			$this->show_error_page($ctl, [], "LINEからアクセスしてください。");
			return;
		}
		$line_member = $context["line_member"];
		if ($line_member !== [] && $this->line_member_registration_complete($line_member)) {
			$this->assign_common($ctl, "会員情報", $line_member, ["row" => $line_member]);
			$ctl->show_public_pages("account.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
			return;
		}
		$row = $line_member !== [] ? $line_member : [
			"userid" => $context["userid"],
			"line_name" => "",
			"name" => "",
		];
		$this->assign_common($ctl, "会員登録", $line_member);
		$ctl->assign("row", $row);
		$ctl->show_public_pages("account_register.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function account_save(Controller $ctl) {
		$context = $this->resolve_public_context($ctl);
		$userid = $context["userid"];
		if ($userid === "") {
			$ctl->res_error_message("name", "LINEからアクセスしてください。");
			return;
		}
		$row = $context["line_member"];
		$is_new = $row === [];
		$row["userid"] = $userid;
		$row["name"] = trim((string) ($ctl->POST("name") ?? ""));
		$row["updated_at"] = time();
		if ($row["name"] === "") {
			$ctl->res_error_message("name", "氏名を入力してください。");
			return;
		}
		if ($is_new) {
			$row["created_at"] = time();
			$ctl->db("line_member")->insert($row);
		} else {
			$ctl->db("line_member")->update($row);
		}
		$this->set_public_line_member($ctl, $userid);
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "shop"));
	}

	function account_edit_dialog(Controller $ctl) {
		$context = $this->resolve_public_context($ctl);
		if ($context["userid"] === "" || $context["line_member"] === []) {
			$ctl->res_error_message("name", "LINEからアクセスしてください。");
			return;
		}
		$ctl->assign("row", $context["line_member"]);
		$ctl->show_multi_dialog("public_pages_account_edit", "account_edit.tpl", "会員情報変更", 520);
	}

	function account_update(Controller $ctl) {
		$context = $this->resolve_public_context($ctl);
		$userid = $context["userid"];
		if ($userid === "" || $context["line_member"] === []) {
			$ctl->res_error_message("name", "LINEからアクセスしてください。");
			return;
		}
		$row = $context["line_member"];
		$row["name"] = trim((string) ($ctl->POST("name") ?? ""));
		$row["updated_at"] = time();
		if ($row["name"] === "") {
			$ctl->res_error_message("name", "氏名を入力してください。");
			return;
		}
		$ctl->db("line_member")->update($row);
		$this->set_public_line_member($ctl, $userid);
		$ctl->close_multi_dialog("public_pages_account_edit");
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "account"));
	}

	function shop(Controller $ctl) {
		$line_member = $this->require_public_line_member($ctl);
		if ($line_member === null) {
			return;
		}
		$this->assign_common($ctl, "モール", $line_member, [
			"products" => $this->public_products($ctl),
		]);
		$ctl->show_public_pages("shop.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function product_detail(Controller $ctl) {
		$line_member = $this->require_public_line_member($ctl);
		if ($line_member === null) {
			return;
		}
		$id = (int) $ctl->decrypt((string) ($ctl->GET("id") ?? ""));
		$product = $this->public_product($ctl, $id);
		if ($product === []) {
			$this->show_error_page($ctl, $line_member, "商品が見つかりません。");
			return;
		}
		$this->assign_common($ctl, (string) ($product["name"] ?? "商品詳細"), $line_member, [
			"product" => $product,
			"variants" => $this->public_variants($ctl, $id),
			"cart_add_url" => $ctl->get_APP_URL("public_pages", "cart_add"),
		]);
		$ctl->show_public_pages("product_detail.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function cart_add(Controller $ctl) {
		$line_member = $this->require_public_line_member($ctl);
		if ($line_member === null) {
			return;
		}
		$product_id = (int) ($ctl->POST("product_id") ?? 0);
		$variant_id = (int) ($ctl->POST("variant_id") ?? 0);
		$quantity = max(1, (int) ($ctl->POST("quantity") ?? 1));
		$product = $this->public_product($ctl, $product_id);
		$variant = $this->public_variant($ctl, $product_id, $variant_id);
		if ($product === [] || $variant === []) {
			$ctl->res_error_message("cart", "商品を選択してください。");
			return;
		}
		$cart = $this->get_cart($ctl);
		$shop_id = (int) ($product["shop_id"] ?? 0);
		if ((int) ($cart["shop_id"] ?? 0) > 0 && (int) $cart["shop_id"] !== $shop_id) {
			$ctl->res_error_message("cart", "カートには同じショップの商品だけ入れられます。");
			return;
		}
		$key = (string) $variant_id;
		$cart["shop_id"] = $shop_id;
		$cart["items"][$key] = [
			"product_id" => $product_id,
			"variant_id" => $variant_id,
			"quantity" => (($cart["items"][$key]["quantity"] ?? 0) + $quantity),
		];
		$this->save_cart($ctl, $cart);
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "cart"));
	}

	function cart(Controller $ctl) {
		$line_member = $this->require_public_line_member($ctl);
		if ($line_member === null) {
			return;
		}
		$this->assign_common($ctl, "カート", $line_member, $this->build_cart_summary($ctl));
		$ctl->show_public_pages("cart.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function cart_remove(Controller $ctl) {
		$line_member = $this->require_public_line_member($ctl);
		if ($line_member === null) {
			return;
		}
		$variant_id = (int) ($ctl->POST("variant_id") ?? 0);
		$cart = $this->get_cart($ctl);
		unset($cart["items"][(string) $variant_id]);
		if (count($cart["items"]) === 0) {
			$cart = ["shop_id" => 0, "items" => []];
		}
		$this->save_cart($ctl, $cart);
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "cart"));
	}

	private function assign_common(Controller $ctl, string $page_title, array $line_member, array $extra = []): void {
		$base = [
			"page_title" => $page_title,
			"app_name" => "LINEモール",
			"line_member" => $line_member,
			"shop_url" => $ctl->get_APP_URL("public_pages", "shop"),
			"account_url" => $ctl->get_APP_URL("public_pages", "account"),
		];
		foreach (array_merge($base, $extra) as $key => $value) {
			$ctl->assign($key, $value);
		}
	}

	private function require_public_line_member(Controller $ctl): ?array {
		$context = $this->resolve_public_context($ctl);
		if ($context["userid"] === "") {
			$this->show_error_page($ctl, [], "LINEからアクセスしてください。");
			return null;
		}
		if ($context["line_member"] === [] || !$this->line_member_registration_complete($context["line_member"])) {
			$this->account($ctl);
			return null;
		}
		return $context["line_member"];
	}

	private function line_member_registration_complete(array $line_member): bool {
		return (int) ($line_member["id"] ?? 0) > 0
			&& trim((string) ($line_member["name"] ?? "")) !== "";
	}

	private function resolve_public_context(Controller $ctl): array {
		$userid = trim((string) ($ctl->GET("user_id") ?? $ctl->GET("userid") ?? $ctl->POST("user_id") ?? $ctl->POST("userid") ?? ""));
		if ($userid !== "") {
			$line_member = $this->set_public_line_member($ctl, $userid);
			return ["userid" => $userid, "line_member" => $line_member];
		}
		$userid = trim((string) ($ctl->get_session("public_line_userid") ?? ""));
		$line_member = $ctl->get_session("public_line_member");
		return [
			"userid" => $userid,
			"line_member" => is_array($line_member) ? $line_member : [],
		];
	}

	private function set_public_line_member(Controller $ctl, string $userid): array {
		$rows = $ctl->db("line_member")->select("userid", $userid);
		$line_member = is_array($rows) && count($rows) > 0 ? $rows[0] : [];
		$ctl->set_session("public_line_userid", $userid);
		$ctl->set_session("public_line_member", $line_member);
		return $line_member;
	}

	private function public_products(Controller $ctl): array {
		$rows = [];
		foreach ($ctl->db("product")->getall("updated_at", SORT_DESC) as $product) {
			if ((int) ($product["status"] ?? 0) !== 1) {
				continue;
			}
			if (!$this->is_public_product_available($product)) {
				continue;
			}
			$shop = $ctl->db("shop")->get((int) ($product["shop_id"] ?? 0));
			if (!is_array($shop) || (int) ($shop["status"] ?? 0) !== 1) {
				continue;
			}
			$product["shop_name"] = (string) ($shop["shop_name"] ?? "");
			$product["detail_url"] = $ctl->get_APP_URL("public_pages", "product_detail", ["id" => $ctl->encrypt((int) ($product["id"] ?? 0))]);
			$rows[] = $product;
		}
		return $rows;
	}

	private function public_product(Controller $ctl, int $id): array {
		$product = $ctl->db("product")->get($id);
		if (!is_array($product) || (int) ($product["status"] ?? 0) !== 1) {
			return [];
		}
		if (!$this->is_public_product_available($product)) {
			return [];
		}
		$shop = $ctl->db("shop")->get((int) ($product["shop_id"] ?? 0));
		if (!is_array($shop) || (int) ($shop["status"] ?? 0) !== 1) {
			return [];
		}
		$product["shop_name"] = (string) ($shop["shop_name"] ?? "");
		$product["shipping_fee"] = (int) ($shop["shipping_fee"] ?? 0);
		return $product;
	}

	private function is_public_product_available(array $product): bool {
		if ((int) ($product["product_type"] ?? 0) !== 1) {
			return true;
		}
		$event_date = (int) ($product["event_date"] ?? 0);
		if ($event_date <= 0) {
			return false;
		}
		$event_day = strtotime(date("Y-m-d", $event_date));
		if ($event_day === false) {
			return false;
		}
		return $event_day >= strtotime("today");
	}

	private function public_variants(Controller $ctl, int $product_id): array {
		$rows = [];
		foreach ($ctl->db("product_variant")->select("parent_id", $product_id, "sort") as $variant) {
			if ((int) ($variant["is_active"] ?? 0) !== 1) {
				continue;
			}
			$rows[] = $variant;
		}
		return $rows;
	}

	private function public_variant(Controller $ctl, int $product_id, int $variant_id): array {
		$variant = $ctl->db("product_variant")->get($variant_id);
		if (!is_array($variant)
			|| (int) ($variant["parent_id"] ?? 0) !== $product_id
			|| (int) ($variant["is_active"] ?? 0) !== 1) {
			return [];
		}
		return $variant;
	}

	private function get_cart(Controller $ctl): array {
		$cart = $ctl->get_session("mall_cart");
		if (!is_array($cart)) {
			return ["shop_id" => 0, "items" => []];
		}
		$cart["shop_id"] = (int) ($cart["shop_id"] ?? 0);
		$cart["items"] = is_array($cart["items"] ?? null) ? $cart["items"] : [];
		return $cart;
	}

	private function save_cart(Controller $ctl, array $cart): void {
		$ctl->set_session("mall_cart", $cart);
	}

	private function build_cart_summary(Controller $ctl): array {
		$cart = $this->get_cart($ctl);
		$items = [];
		$subtotal = 0;
		foreach ($cart["items"] as $item) {
			$product = $this->public_product($ctl, (int) ($item["product_id"] ?? 0));
			$variant = $this->public_variant($ctl, (int) ($item["product_id"] ?? 0), (int) ($item["variant_id"] ?? 0));
			if ($product === [] || $variant === []) {
				continue;
			}
			$quantity = max(1, (int) ($item["quantity"] ?? 1));
			$line_amount = (int) ($variant["price"] ?? 0) * $quantity;
			$subtotal += $line_amount;
			$items[] = [
				"product" => $product,
				"variant" => $variant,
				"quantity" => $quantity,
				"line_amount" => $line_amount,
			];
		}
		$shipping_fee = count($items) > 0 ? (int) ($items[0]["product"]["shipping_fee"] ?? 0) : 0;
		return [
			"cart_items" => $items,
			"cart_subtotal" => $subtotal,
			"cart_shipping_fee" => $shipping_fee,
			"cart_total" => $subtotal + $shipping_fee,
			"cart_remove_url" => $ctl->get_APP_URL("public_pages", "cart_remove"),
		];
	}

	private function show_error_page(Controller $ctl, array $line_member, string $message): void {
		$this->assign_common($ctl, "エラー", $line_member, ["error_message" => $message]);
		$ctl->show_public_pages("error.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}
}
