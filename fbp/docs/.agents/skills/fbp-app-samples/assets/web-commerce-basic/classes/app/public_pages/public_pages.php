<?php

class public_pages {

	private const CART_SESSION = "shop_cart";
	private const MEMBER_SESSION = "shop_member_id";
	private const CHECKOUT_SESSION = "shop_checkout";
	private const LAST_ORDER_SESSION = "shop_last_order_id";
	private const SHIPPING_FEE = 500;
	private const PAGE_SIZE = 12;

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function index(Controller $ctl): void {
		$this->shop($ctl);
	}

	function shop(Controller $ctl): void {
		$state = $this->searchState($ctl, true);
		$this->assignFrame($ctl, "Shop", [
			"keyword" => $state["keyword"],
			"category_id" => $state["category_id"],
			"categories" => $this->activeCategories($ctl),
		]);
		$this->assignProductList($ctl, $state, false);
		$ctl->show_public_pages("shop.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function submit_shop_search(Controller $ctl): void {
		$state = $this->normalizeSearchState($ctl->POST());
		$ctl->set_session("shop_search_state", $state);
		$this->assignFrame($ctl, "Shop");
		$this->assignProductList($ctl, $state, false);
		$ctl->reload_area("#shop_product_list_area", "_product_list.tpl");
	}

	function shop_more(Controller $ctl): void {
		$state = $this->searchState($ctl, false);
		$this->assignProductList($ctl, $state, true);
		$ctl->reload_area("#shop_product_list_area", "_product_list.tpl");
	}

	function product_detail(Controller $ctl): void {
		$product = $this->productFromRequest($ctl);
		if ($product === []) {
			$this->showError($ctl, "Product was not found.");
			return;
		}
		$this->assignFrame($ctl, (string) ($product["name"] ?? "Product"), [
			"product" => $product,
			"variants" => $this->activeVariants($ctl, (int) ($product["id"] ?? 0)),
			"product_id_enc" => $ctl->encrypt((string) ($product["id"] ?? 0)),
			"add_to_cart_url" => $ctl->get_APP_URL("public_pages", "add_to_cart"),
		]);
		$ctl->show_public_pages("product_detail.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function add_to_cart(Controller $ctl): void {
		$product = $this->productFromPost($ctl);
		$variantId = (int) $ctl->POST("variant_id");
		$quantity = max(0, (int) $ctl->POST("quantity"));
		if ($product === [] || $variantId <= 0 || $quantity <= 0) {
			$ctl->res_error_message("quantity", "Select a product option and quantity.");
			return;
		}
		$variant = $this->variant($ctl, $variantId);
		if ($variant === [] || (int) ($variant["parent_id"] ?? 0) !== (int) ($product["id"] ?? 0)) {
			$ctl->res_error_message("quantity", "Selected option is not available.");
			return;
		}
		$cart = $this->cart($ctl);
		$cart[$variantId] = (int) ($cart[$variantId] ?? 0) + $quantity;
		$error = $this->cartStockError($ctl, $cart);
		if ($error !== "") {
			$ctl->res_error_message("quantity", $error);
			return;
		}
		$this->setCart($ctl, $cart);
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "cart_page"));
	}

	function cart_page(Controller $ctl): void {
		$this->assignCartPage($ctl);
		$ctl->show_public_pages("cart.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function update_cart(Controller $ctl): void {
		$cart = [];
		$quantities = $ctl->POST("quantity") ?? [];
		if (is_array($quantities)) {
			foreach ($quantities as $variantId => $quantity) {
				$variantId = (int) $variantId;
				$quantity = max(0, (int) $quantity);
				if ($variantId > 0 && $quantity > 0) {
					$cart[$variantId] = $quantity;
				}
			}
		}
		$error = $this->cartStockError($ctl, $cart);
		if ($error !== "") {
			$ctl->res_error_message("cart", $error);
			return;
		}
		$this->setCart($ctl, $cart);
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "cart_page"));
	}

	function register(Controller $ctl): void {
		$this->assignFrame($ctl, "Create Account", [
			"row" => $this->emptyMember(),
			"errors" => [],
			"submit_url" => $ctl->get_APP_URL("public_pages", "register_save"),
		]);
		$ctl->show_public_pages("register.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function register_save(Controller $ctl): void {
		$row = $this->memberPost($ctl);
		$errors = $this->validateMember($ctl, $row, true);
		if ($errors !== []) {
			$this->assignFrame($ctl, "Create Account", [
				"row" => $row,
				"errors" => $errors,
				"submit_url" => $ctl->get_APP_URL("public_pages", "register_save"),
			]);
			$ctl->show_public_pages("register.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
			return;
		}
		$row["password_hash"] = password_hash((string) $ctl->POST("password"), PASSWORD_DEFAULT);
		$row["status"] = "active";
		$row["created_at"] = time();
		$row["updated_at"] = time();
		$id = (int) $ctl->db("shop_member")->insert($row);
		$ctl->set_session(self::MEMBER_SESSION, $id);
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "account"));
	}

	function login(Controller $ctl): void {
		$this->assignFrame($ctl, "Login", [
			"email" => "",
			"error" => "",
			"submit_url" => $ctl->get_APP_URL("public_pages", "login_exe"),
		]);
		$ctl->show_public_pages("login.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function login_exe(Controller $ctl): void {
		$email = trim((string) $ctl->POST("email"));
		$password = (string) $ctl->POST("password");
		$member = $this->memberByEmail($ctl, $email);
		if ($member === [] || !password_verify($password, (string) ($member["password_hash"] ?? ""))) {
			$this->assignFrame($ctl, "Login", [
				"email" => $email,
				"error" => "Email or password is incorrect.",
				"submit_url" => $ctl->get_APP_URL("public_pages", "login_exe"),
			]);
			$ctl->show_public_pages("login.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
			return;
		}
		if ((string) ($member["status"] ?? "active") !== "active") {
			$this->showError($ctl, "This account is not active.");
			return;
		}
		$ctl->set_session(self::MEMBER_SESSION, (int) ($member["id"] ?? 0));
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "account"));
	}

	function logout(Controller $ctl): void {
		$ctl->set_session(self::MEMBER_SESSION, 0);
		$ctl->res_redirect($ctl->get_APP_URL("public_pages", "shop"));
	}

	function account(Controller $ctl): void {
		$member = $this->requireMember($ctl);
		if ($member === []) {
			return;
		}
		$this->assignFrame($ctl, "Account", ["member" => $member]);
		$ctl->show_public_pages("account.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function checkout(Controller $ctl): void {
		$member = $this->requireMember($ctl);
		if ($member === []) {
			return;
		}
		$summary = $this->cartSummary($ctl);
		if ($summary["rows"] === []) {
			$this->showError($ctl, "Your cart is empty.");
			return;
		}
		$row = $this->defaultCheckout($member);
		$this->assignCheckout($ctl, $member, $row, []);
		$ctl->show_public_pages("checkout.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function start_square_payment(Controller $ctl): void {
		$member = $this->requireMember($ctl);
		if ($member === []) {
			return;
		}
		$summary = $this->cartSummary($ctl);
		if ($summary["rows"] === []) {
			$this->showError($ctl, "Your cart is empty.");
			return;
		}
		$row = $this->checkoutPost($ctl, $member);
		$errors = $this->validateCheckout($row);
		if ($errors !== []) {
			$this->assignCheckout($ctl, $member, $row, $errors);
			$ctl->show_public_pages("checkout.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
			return;
		}
		$stockError = $this->cartStockError($ctl, $this->cart($ctl));
		if ($stockError !== "") {
			$this->showError($ctl, $stockError);
			return;
		}
		if ((int) ($summary["total_amount"] ?? 0) <= 0) {
			$this->showError($ctl, "Payment amount must be greater than zero.");
			return;
		}
		$ctl->set_session(self::CHECKOUT_SESSION, $row);
		$callback = [
			"name" => $row["buyer_name"],
			"email" => $row["buyer_email"],
			"address" => trim($row["shipping_zip"] . " " . $row["shipping_address"]),
		];
		$ctl->show_square_dialog("public_pages", "square_payment_callback", $callback, "", (string) ((int) $summary["total_amount"]));
	}

	function square_payment_callback(Controller $ctl): void {
		$param = $ctl->get_square_callback_parameter_array() ?? [];
		$member = $this->currentMember($ctl);
		if ($member === []) {
			$ctl->show_square_dialog("public_pages", "square_payment_callback", $param, "Purchase session was not found.");
			return;
		}
		$row = $this->normalizeCheckout($ctl->get_session(self::CHECKOUT_SESSION) ?? []);
		$cart = $this->cart($ctl);
		$summary = $this->cartSummary($ctl);
		if ($row["buyer_name"] === "" || $cart === [] || $summary["rows"] === []) {
			$ctl->show_square_dialog("public_pages", "square_payment_callback", $param, "Checkout session was not found.");
			return;
		}
		$amount = (int) ($summary["total_amount"] ?? 0);
		if ($amount <= 0) {
			$ctl->show_square_dialog("public_pages", "square_payment_callback", $param, "Payment amount must be greater than zero.");
			return;
		}
		$stockError = $this->cartStockError($ctl, $cart);
		if ($stockError !== "") {
			$ctl->show_square_dialog("public_pages", "square_payment_callback", $param, $stockError, (string) $amount);
			return;
		}

		try {
			$squareCustomerId = trim((string) ($member["square_customer_id"] ?? ""));
			if ($squareCustomerId === "") {
				$squareCustomerId = (string) $ctl->square_regist_customer($row["buyer_name"], $row["buyer_email"], $row["shipping_address"]);
				if ($squareCustomerId === "") {
					throw new Exception((string) ($ctl->square_get_error() ?: "Square customer registration failed."));
				}
			}
			$squareCardId = (string) $ctl->square_regist_card($squareCustomerId);
			if ($squareCardId === "") {
				throw new Exception((string) ($ctl->square_get_error() ?: "Square card registration failed."));
			}
			$paid = $ctl->square_payment($squareCustomerId, $squareCardId, $amount);
			if (!$paid) {
				throw new Exception((string) ($ctl->square_get_error() ?: "Square payment failed."));
			}
			$member["square_customer_id"] = $squareCustomerId;
			$member["square_card_id"] = $squareCardId;
			$member["updated_at"] = time();
			$ctl->db("shop_member")->update($member);
			$orderId = $this->createOrder($ctl, $member, $row, $summary);
			$this->decrementStock($ctl, $summary["rows"]);
			$this->setCart($ctl, []);
			$ctl->set_session(self::CHECKOUT_SESSION, []);
			$ctl->set_session(self::LAST_ORDER_SESSION, $orderId);
			$ctl->close_square_dialog();
			$ctl->res_redirect($ctl->get_APP_URL("public_pages", "thanks"));
		} catch (Throwable $e) {
			$message = trim($e->getMessage());
			if ($message === "") {
				$message = (string) ($ctl->square_get_error() ?: "Square payment failed.");
			}
			$ctl->show_square_dialog("public_pages", "square_payment_callback", $param, $message, (string) $amount);
		}
	}

	function thanks(Controller $ctl): void {
		$member = $this->requireMember($ctl);
		if ($member === []) {
			return;
		}
		$order = $this->lastOrder($ctl, $member);
		if ($order === []) {
			$this->showError($ctl, "Order was not found.");
			return;
		}
		$this->assignFrame($ctl, "Thank You", [
			"order" => $order,
			"items" => $this->orderItems($ctl, (int) ($order["id"] ?? 0)),
		]);
		$ctl->show_public_pages("thanks.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function history(Controller $ctl): void {
		$member = $this->requireMember($ctl);
		if ($member === []) {
			return;
		}
		$this->assignFrame($ctl, "Order History", [
			"orders" => $this->memberOrders($ctl, (int) ($member["id"] ?? 0)),
		]);
		$ctl->show_public_pages("history.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function assignFrame(Controller $ctl, string $title, array $extra = []): void {
		$member = $this->currentMember($ctl);
		$base = [
			"app_name" => "Web Commerce Basic",
			"page_title" => $title,
			"member" => $member,
			"shop_url" => $ctl->get_APP_URL("public_pages", "shop"),
			"cart_url" => $ctl->get_APP_URL("public_pages", "cart_page"),
			"login_url" => $ctl->get_APP_URL("public_pages", "login"),
			"register_url" => $ctl->get_APP_URL("public_pages", "register"),
			"logout_url" => $ctl->get_APP_URL("public_pages", "logout"),
			"account_url" => $ctl->get_APP_URL("public_pages", "account"),
			"history_url" => $ctl->get_APP_URL("public_pages", "history"),
			"cart_count" => $this->cartSummary($ctl)["item_count"],
		];
		foreach (array_merge($base, $extra) as $key => $value) {
			$ctl->assign($key, $value);
		}
	}

	private function normalizeSearchState($input): array {
		if (!is_array($input)) {
			$input = [];
		}
		return [
			"keyword" => trim((string) ($input["keyword"] ?? "")),
			"category_id" => (int) ($input["category_id"] ?? 0),
		];
	}

	private function searchState(Controller $ctl, bool $fromGet): array {
		if ($fromGet) {
			$state = $this->normalizeSearchState([
				"keyword" => $ctl->GET("keyword") ?? "",
				"category_id" => $ctl->GET("category_id") ?? 0,
			]);
			if ($state["keyword"] !== "" || $state["category_id"] > 0) {
				$ctl->set_session("shop_search_state", $state);
				return $state;
			}
		}
		return $this->normalizeSearchState($ctl->get_session("shop_search_state") ?? []);
	}

	private function assignProductList(Controller $ctl, array $state, bool $isMore): void {
		$max = $isMore ? $ctl->increment_post_value("max", self::PAGE_SIZE) : self::PAGE_SIZE;
		$rows = $this->products($ctl, $state["keyword"], $state["category_id"]);
		$isLast = count($rows) <= $max;
		if (!$isLast) {
			$rows = array_slice($rows, 0, $max);
		}
		$ctl->assign("products", $rows);
		$ctl->assign("max", $max);
		$ctl->assign("is_last", $isLast);
	}

	private function products(Controller $ctl, string $keyword, int $categoryId): array {
		$rows = [];
		foreach ($ctl->db("shop_product")->getall("sort", SORT_ASC) as $product) {
			if ((string) ($product["status"] ?? "") !== "active") {
				continue;
			}
			if ($categoryId > 0 && (int) ($product["category_id"] ?? 0) !== $categoryId) {
				continue;
			}
			$text = (string) ($product["name"] ?? "") . " " . (string) ($product["description"] ?? "");
			if ($keyword !== "" && mb_stripos($text, $keyword) === false) {
				continue;
			}
			$variants = $this->activeVariants($ctl, (int) ($product["id"] ?? 0));
			if ($variants === []) {
				continue;
			}
			$product["detail_url"] = $ctl->get_APP_URL("public_pages", "product_detail", ["id" => $ctl->encrypt((string) ($product["id"] ?? 0))]);
			$product["price_from"] = min(array_map(function ($row) {
				return (int) ($row["price"] ?? 0);
			}, $variants));
			$product["image_url"] = $this->productImageUrl($ctl, $product);
			$rows[] = $product;
		}
		return $rows;
	}

	private function productFromRequest(Controller $ctl): array {
		$id = (int) $ctl->decrypt((string) ($ctl->GET("id") ?? ""));
		return $this->product($ctl, $id);
	}

	private function productFromPost(Controller $ctl): array {
		$id = (int) $ctl->decrypt((string) ($ctl->POST("product_id_enc") ?? ""));
		return $this->product($ctl, $id);
	}

	private function product(Controller $ctl, int $id): array {
		if ($id <= 0) {
			return [];
		}
		$row = $ctl->db("shop_product")->get($id);
		if (!is_array($row) || $row === [] || (string) ($row["status"] ?? "") !== "active") {
			return [];
		}
		$row["image_url"] = $this->productImageUrl($ctl, $row);
		return $row;
	}

	private function activeVariants(Controller $ctl, int $productId): array {
		$rows = [];
		foreach ($ctl->db("shop_product_variant")->getall("sort", SORT_ASC) as $variant) {
			if ((int) ($variant["parent_id"] ?? 0) !== $productId) {
				continue;
			}
			if ((string) ($variant["is_active"] ?? "1") !== "1") {
				continue;
			}
			if ((int) ($variant["stock_quantity"] ?? 0) <= 0) {
				continue;
			}
			$rows[] = $variant;
		}
		return $rows;
	}

	private function variant(Controller $ctl, int $id): array {
		if ($id <= 0) {
			return [];
		}
		$row = $ctl->db("shop_product_variant")->get($id);
		return is_array($row) ? $row : [];
	}

	private function activeCategories(Controller $ctl): array {
		$rows = [];
		foreach ($ctl->db("shop_product_category")->getall("sort", SORT_ASC) as $row) {
			if ((string) ($row["is_active"] ?? "1") === "1") {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	private function productImageUrl(Controller $ctl, array $product): string {
		$fileId = (int) ($product["image_file"] ?? 0);
		if ($fileId <= 0) {
			return "";
		}
		$file = $ctl->get_file_info($fileId);
		$path = trim((string) ($file["path_th"] ?? $file["path"] ?? ""));
		return $path !== "" ? $ctl->get_APP_URL("db_exe", "view_image", ["path" => $path]) : "";
	}

	private function cart(Controller $ctl): array {
		$cart = $ctl->get_session(self::CART_SESSION);
		return is_array($cart) ? $cart : [];
	}

	private function setCart(Controller $ctl, array $cart): void {
		$ctl->set_session(self::CART_SESSION, $cart);
	}

	private function cartRows(Controller $ctl): array {
		$rows = [];
		foreach ($this->cart($ctl) as $variantId => $quantity) {
			$variant = $this->variant($ctl, (int) $variantId);
			$product = $this->product($ctl, (int) ($variant["parent_id"] ?? 0));
			if ($variant === [] || $product === []) {
				continue;
			}
			$quantity = max(1, (int) $quantity);
			$unitPrice = (int) ($variant["price"] ?? 0);
			$taxRate = (int) ($product["tax_rate"] ?? 10);
			$rows[] = [
				"variant_id" => (int) $variantId,
				"product_id" => (int) ($product["id"] ?? 0),
				"product" => $product,
				"variant" => $variant,
				"quantity" => $quantity,
				"unit_price" => $unitPrice,
				"tax_rate" => $taxRate,
				"line_amount" => $unitPrice * $quantity,
			];
		}
		return $rows;
	}

	private function cartSummary(Controller $ctl): array {
		$rows = $this->cartRows($ctl);
		$subtotal = 0;
		$tax = 0;
		$count = 0;
		foreach ($rows as $row) {
			$subtotal += (int) $row["line_amount"];
			$tax += (int) floor(((int) $row["line_amount"]) * ((int) $row["tax_rate"]) / 100);
			$count += (int) $row["quantity"];
		}
		$shippingFee = $rows === [] ? 0 : self::SHIPPING_FEE;
		return [
			"rows" => $rows,
			"subtotal_amount" => $subtotal,
			"shipping_fee" => $shippingFee,
			"tax_amount" => $tax,
			"total_amount" => $subtotal + $shippingFee + $tax,
			"item_count" => $count,
		];
	}

	private function cartStockError(Controller $ctl, array $cart): string {
		foreach ($cart as $variantId => $quantity) {
			$variant = $this->variant($ctl, (int) $variantId);
			if ($variant === []) {
				return "A cart item is no longer available.";
			}
			if ((int) $quantity > (int) ($variant["stock_quantity"] ?? 0)) {
				return (string) ($variant["name"] ?? "Product option") . " does not have enough stock.";
			}
		}
		return "";
	}

	private function assignCartPage(Controller $ctl): void {
		$summary = $this->cartSummary($ctl);
		$this->assignFrame($ctl, "Cart", [
			"cart_rows" => $summary["rows"],
			"subtotal_amount" => $summary["subtotal_amount"],
			"shipping_fee" => $summary["shipping_fee"],
			"tax_amount" => $summary["tax_amount"],
			"total_amount" => $summary["total_amount"],
			"update_url" => $ctl->get_APP_URL("public_pages", "update_cart"),
			"checkout_url" => $ctl->get_APP_URL("public_pages", "checkout"),
		]);
	}

	private function emptyMember(): array {
		return [
			"name" => "",
			"email" => "",
			"tel" => "",
			"zip" => "",
			"address" => "",
		];
	}

	private function memberPost(Controller $ctl): array {
		return [
			"name" => trim((string) $ctl->POST("name")),
			"email" => trim((string) $ctl->POST("email")),
			"tel" => trim((string) $ctl->POST("tel")),
			"zip" => trim((string) $ctl->POST("zip")),
			"address" => trim((string) $ctl->POST("address")),
		];
	}

	private function validateMember(Controller $ctl, array $row, bool $new): array {
		$errors = [];
		if ($row["name"] === "") {
			$errors["name"] = "Enter your name.";
		}
		if ($row["email"] === "" || !filter_var($row["email"], FILTER_VALIDATE_EMAIL)) {
			$errors["email"] = "Enter a valid email.";
		} else {
			$member = $this->memberByEmail($ctl, $row["email"]);
			if ($new && $member !== []) {
				$errors["email"] = "This email is already registered.";
			}
		}
		$password = (string) $ctl->POST("password");
		if ($new && strlen($password) < 8) {
			$errors["password"] = "Enter at least 8 characters.";
		}
		return $errors;
	}

	private function memberByEmail(Controller $ctl, string $email): array {
		if ($email === "") {
			return [];
		}
		$rows = $ctl->db("shop_member")->select("email", $email);
		return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
	}

	private function currentMember(Controller $ctl): array {
		$id = (int) ($ctl->get_session(self::MEMBER_SESSION) ?? 0);
		if ($id <= 0) {
			return [];
		}
		$row = $ctl->db("shop_member")->get($id);
		return is_array($row) ? $row : [];
	}

	private function requireMember(Controller $ctl): array {
		$member = $this->currentMember($ctl);
		if ($member !== []) {
			return $member;
		}
		$this->assignFrame($ctl, "Login", [
			"email" => "",
			"error" => "Please login or create an account first.",
			"submit_url" => $ctl->get_APP_URL("public_pages", "login_exe"),
		]);
		$ctl->show_public_pages("login.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
		return [];
	}

	private function defaultCheckout(array $member): array {
		return [
			"buyer_name" => (string) ($member["name"] ?? ""),
			"buyer_email" => (string) ($member["email"] ?? ""),
			"buyer_tel" => (string) ($member["tel"] ?? ""),
			"shipping_zip" => (string) ($member["zip"] ?? ""),
			"shipping_address" => (string) ($member["address"] ?? ""),
			"memo" => "",
		];
	}

	private function checkoutPost(Controller $ctl, array $member): array {
		return $this->normalizeCheckout([
			"buyer_name" => $ctl->POST("buyer_name") ?? $member["name"] ?? "",
			"buyer_email" => $ctl->POST("buyer_email") ?? $member["email"] ?? "",
			"buyer_tel" => $ctl->POST("buyer_tel") ?? $member["tel"] ?? "",
			"shipping_zip" => $ctl->POST("shipping_zip") ?? "",
			"shipping_address" => $ctl->POST("shipping_address") ?? "",
			"memo" => $ctl->POST("memo") ?? "",
		]);
	}

	private function normalizeCheckout($input): array {
		if (!is_array($input)) {
			$input = [];
		}
		return [
			"buyer_name" => trim((string) ($input["buyer_name"] ?? "")),
			"buyer_email" => trim((string) ($input["buyer_email"] ?? "")),
			"buyer_tel" => trim((string) ($input["buyer_tel"] ?? "")),
			"shipping_zip" => trim((string) ($input["shipping_zip"] ?? "")),
			"shipping_address" => trim((string) ($input["shipping_address"] ?? "")),
			"memo" => trim((string) ($input["memo"] ?? "")),
		];
	}

	private function validateCheckout(array $row): array {
		$errors = [];
		if ($row["buyer_name"] === "") {
			$errors["buyer_name"] = "Enter your name.";
		}
		if ($row["buyer_email"] === "" || !filter_var($row["buyer_email"], FILTER_VALIDATE_EMAIL)) {
			$errors["buyer_email"] = "Enter a valid email.";
		}
		if ($row["buyer_tel"] === "") {
			$errors["buyer_tel"] = "Enter your phone number.";
		}
		if ($row["shipping_address"] === "") {
			$errors["shipping_address"] = "Enter the shipping address.";
		}
		return $errors;
	}

	private function assignCheckout(Controller $ctl, array $member, array $row, array $errors): void {
		$summary = $this->cartSummary($ctl);
		$this->assignFrame($ctl, "Checkout", [
			"row" => $row,
			"errors" => $errors,
			"cart_rows" => $summary["rows"],
			"subtotal_amount" => $summary["subtotal_amount"],
			"shipping_fee" => $summary["shipping_fee"],
			"tax_amount" => $summary["tax_amount"],
			"total_amount" => $summary["total_amount"],
			"submit_url" => $ctl->get_APP_URL("public_pages", "start_square_payment"),
			"back_url" => $ctl->get_APP_URL("public_pages", "cart_page"),
		]);
	}

	private function createOrder(Controller $ctl, array $member, array $row, array $summary): int {
		$order = [
			"shop_member_id" => (int) ($member["id"] ?? 0),
			"order_status" => "paid",
			"payment_status" => "paid",
			"square_customer_id" => (string) ($member["square_customer_id"] ?? ""),
			"square_card_id" => (string) ($member["square_card_id"] ?? ""),
			"buyer_name" => $row["buyer_name"],
			"buyer_email" => $row["buyer_email"],
			"buyer_tel" => $row["buyer_tel"],
			"shipping_zip" => $row["shipping_zip"],
			"shipping_address" => $row["shipping_address"],
			"subtotal_amount" => $summary["subtotal_amount"],
			"shipping_fee" => $summary["shipping_fee"],
			"tax_amount" => $summary["tax_amount"],
			"total_amount" => $summary["total_amount"],
			"ordered_at" => time(),
			"paid_at" => time(),
			"cancelled_at" => 0,
			"memo" => $row["memo"],
			"created_at" => time(),
			"updated_at" => time(),
		];
		$orderId = (int) $ctl->db("shop_customer_order")->insert($order);
		$sort = 1;
		foreach ($summary["rows"] as $cartRow) {
			$item = [
				"parent_id" => $orderId,
				"sort" => $sort++,
				"product_id" => (int) $cartRow["product_id"],
				"product_variant_id" => (int) $cartRow["variant_id"],
				"product_name" => (string) ($cartRow["product"]["name"] ?? ""),
				"variant_name" => (string) ($cartRow["variant"]["name"] ?? ""),
				"unit_price" => (int) $cartRow["unit_price"],
				"quantity" => (int) $cartRow["quantity"],
				"tax_rate" => (int) $cartRow["tax_rate"],
				"line_amount" => (int) $cartRow["line_amount"],
			];
			$ctl->db("shop_customer_order_item")->insert($item);
		}
		return $orderId;
	}

	private function decrementStock(Controller $ctl, array $rows): void {
		foreach ($rows as $row) {
			$variant = $this->variant($ctl, (int) $row["variant_id"]);
			if ($variant === []) {
				continue;
			}
			$variant["stock_quantity"] = max(0, (int) ($variant["stock_quantity"] ?? 0) - (int) $row["quantity"]);
			$ctl->db("shop_product_variant")->update($variant);
		}
	}

	private function lastOrder(Controller $ctl, array $member): array {
		$orderId = (int) ($ctl->get_session(self::LAST_ORDER_SESSION) ?? 0);
		if ($orderId <= 0) {
			return [];
		}
		$order = $ctl->db("shop_customer_order")->get($orderId);
		if (!is_array($order) || (int) ($order["shop_member_id"] ?? 0) !== (int) ($member["id"] ?? 0)) {
			return [];
		}
		return $order;
	}

	private function memberOrders(Controller $ctl, int $memberId): array {
		$rows = [];
		foreach ($ctl->db("shop_customer_order")->getall("id", SORT_DESC) as $row) {
			if ((int) ($row["shop_member_id"] ?? 0) === $memberId) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	private function orderItems(Controller $ctl, int $orderId): array {
		$rows = [];
		foreach ($ctl->db("shop_customer_order_item")->getall("sort", SORT_ASC) as $row) {
			if ((int) ($row["parent_id"] ?? 0) === $orderId) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	private function showError(Controller $ctl, string $message): void {
		$this->assignFrame($ctl, "Error", ["message" => $message]);
		$ctl->show_public_pages("error.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}
}
