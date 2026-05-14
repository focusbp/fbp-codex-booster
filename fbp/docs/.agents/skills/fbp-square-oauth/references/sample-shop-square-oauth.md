# Sample Shop Square OAuth Code

Use this as a starting point inside an Original Screen class such as `shop_original_management`.
Adjust names to the target class and scope helper.

## Setting fields

Add `square_application_secret` to `fbp/app/setting/fmt/setting.fmt`:

```text
square_application_id,100,T
square_application_secret,150,T
square_access_token,100,T
square_location_id,100,T
```

Add it to `setting::$sensitive_keys`:

```php
private $sensitive_keys = [
	// ...
	"square_application_secret",
	"square_access_token",
];
```

Add masked inputs to the SQUARE section:

```smarty
<tr>
	<td>Application Secret</td>
	<td><input type="password" name="square_application_secret" value="" placeholder="{$masked_setting.square_application_secret|escape}"></td>
</tr>
<tr>
	<td>{t key="setting.access_token"}</td>
	<td><input type="password" name="square_access_token" value="" placeholder="{$masked_setting.square_access_token|escape}"></td>
</tr>
```

The existing `setting.update()` pattern skips empty sensitive values if the key is in `$sensitive_keys`.

## Shop list button

```smarty
<button type="button" class="ajax-link button_link" data-class="shop_original_management" data-function="square_connect_dialog" data-id="{$row.id}">
	SQUARE連携
</button>
```

## Pre-connect dialog

`Templates/square_connect_dialog.tpl`:

```smarty
<form id="shop_original_management_square_connect_form">
	<input type="hidden" name="id" value="{$row.id|escape}">
	<div class="original_screen_confirm_message">
		<p>１．Squareのアカウントを作成してください。<a href="https://squareup.com/" target="_blank" rel="noopener">https://squareup.com/</a></p>
		<p>２．認証手続きを行ってください</p>
		<p>３．Squareにログインしたまま下記のSQUARE連携のボタンをクリックしてください</p>
	</div>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" data-class="shop_original_management" data-function="square_connect" data-form="shop_original_management_square_connect_form">SQUARE連携</button>
	</div>
</form>
```

## Helper methods

```php
private function square_application_id(Controller $ctl): string {
	$env = trim((string) getenv("SQUARE_APPLICATION_ID"));
	if ($env !== "") {
		return $env;
	}
	$setting = $ctl->get_setting();
	return trim((string) ($setting["square_application_id"] ?? ""));
}

private function square_application_secret(Controller $ctl): string {
	$env = trim((string) getenv("SQUARE_APPLICATION_SECRET"));
	if ($env !== "") {
		return $env;
	}
	$setting = $ctl->get_setting();
	return trim((string) ($setting["square_application_secret"] ?? ""));
}

private function square_base_url(Controller $ctl): string {
	return $ctl->get_session("testserver") ? "https://connect.squareupsandbox.com" : "https://connect.squareup.com";
}

private function square_redirect_url(Controller $ctl): string {
	$base = $ctl->get_APP_URL("base", "page");
	$prefix = "/base*page";
	if (substr($base, -strlen($prefix)) === $prefix) {
		$base = substr($base, 0, -strlen($prefix));
	}
	return $base . "/fbp/app.php?class=shop_original_management&function=square_oauth_callback";
}

private function square_oauth_scope(): string {
	return "MERCHANT_PROFILE_READ PAYMENTS_WRITE CUSTOMERS_READ CUSTOMERS_WRITE";
}

private function square_oauth_state(Controller $ctl, int $shop_id): string {
	$state = base64_encode(json_encode([
		"shop_id" => $shop_id,
		"nonce" => bin2hex(random_bytes(16)),
		"iat" => time(),
	]));
	$ctl->set_session("shop_square_oauth_state", $state);
	return $state;
}

private function validate_square_oauth_state(Controller $ctl, string $state): int {
	if ($state === "" || $state !== (string) $ctl->get_session("shop_square_oauth_state")) {
		return 0;
	}
	$ctl->set_session("shop_square_oauth_state", "");
	$data = json_decode(base64_decode($state), true);
	if (!is_array($data) || (int) ($data["shop_id"] ?? 0) <= 0) {
		return 0;
	}
	return (int) $data["shop_id"];
}
```

## HTTP helpers

```php
private function square_json_request(string $url, array $payload, array $headers = []): array {
	$headers = array_merge(["Content-Type: application/json", "Square-Version: 2026-01-22"], $headers);
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		CURLOPT_TIMEOUT => 30,
	]);
	$body = curl_exec($ch);
	$error = curl_error($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($body === false || $error !== "") {
		throw new Exception("Squareとの通信に失敗しました。");
	}
	$data = json_decode((string) $body, true);
	if ($status < 200 || $status >= 300) {
		throw new Exception(is_array($data) ? $this->square_error_message($data) : "Square連携に失敗しました。");
	}
	return is_array($data) ? $data : [];
}

private function square_get_request(string $url, string $access_token): array {
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => [
			"Authorization: Bearer " . $access_token,
			"Accept: application/json",
			"Square-Version: 2026-01-22",
		],
		CURLOPT_TIMEOUT => 30,
	]);
	$body = curl_exec($ch);
	$error = curl_error($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($body === false || $error !== "") {
		throw new Exception("Squareとの通信に失敗しました。");
	}
	$data = json_decode((string) $body, true);
	if ($status < 200 || $status >= 300) {
		throw new Exception(is_array($data) ? $this->square_error_message($data) : "Square連携に失敗しました。");
	}
	return is_array($data) ? $data : [];
}

private function square_error_message(array $data): string {
	$errors = $data["errors"] ?? [];
	if (!is_array($errors) || $errors === []) {
		return "";
	}
	$messages = [];
	foreach ($errors as $error) {
		if (is_array($error)) {
			$messages[] = trim((string) (($error["detail"] ?? "") ?: ($error["code"] ?? "")));
		}
	}
	return implode(" ", array_filter($messages));
}

private function square_first_location_id(Controller $ctl, string $access_token): string {
	$data = $this->square_get_request($this->square_base_url($ctl) . "/v2/locations", $access_token);
	$locations = is_array($data["locations"] ?? null) ? $data["locations"] : [];
	foreach ($locations as $location) {
		if ((string) ($location["status"] ?? "") === "ACTIVE" && trim((string) ($location["id"] ?? "")) !== "") {
			return trim((string) $location["id"]);
		}
	}
	return trim((string) ($locations[0]["id"] ?? ""));
}
```

## OAuth actions

```php
function square_connect_dialog(Controller $ctl) {
	$id = (int) ($ctl->POST("id") ?? 0);
	$row = $this->get_shop($ctl, $id);
	if ($row === []) {
		$ctl->show_notification_text("対象ショップが見つかりません。");
		return;
	}
	$ctl->assign("row", $row);
	$ctl->show_multi_dialog("shop_original_management_square_connect", "square_connect_dialog.tpl", "Square連携", 640);
}

function square_connect(Controller $ctl) {
	$id = (int) ($ctl->POST("id") ?? 0);
	$row = $this->get_shop($ctl, $id);
	if ($row === []) {
		$ctl->show_notification_text("対象ショップが見つかりません。");
		return;
	}
	$application_id = $this->square_application_id($ctl);
	if ($application_id === "") {
		$ctl->show_notification_text("SquareアプリケーションIDが未設定です。");
		return;
	}
	$params = [
		"client_id" => $application_id,
		"scope" => $this->square_oauth_scope(),
		"state" => $this->square_oauth_state($ctl, $id),
		"redirect_uri" => $this->square_redirect_url($ctl),
	];
	if (!$ctl->get_session("testserver")) {
		$params["session"] = "false";
	}
	$ctl->res_redirect($this->square_base_url($ctl) . "/oauth2/authorize?" . http_build_query($params, "", "&", PHP_QUERY_RFC1738));
}

function square_oauth_callback(Controller $ctl) {
	$state = trim((string) ($ctl->GET("state") ?? ""));
	$code = trim((string) ($ctl->GET("code") ?? ""));
	$error = trim((string) ($ctl->GET("error") ?? ""));
	$shop_id = $this->validate_square_oauth_state($ctl, $state);
	if ($shop_id <= 0 || !$this->scope->can_access_shop($ctl, $shop_id)) {
		$ctl->show_notification_text("Square連携の状態を確認できませんでした。");
		return;
	}
	if ($error !== "" || $code === "") {
		$ctl->show_notification_text("Square認可コードを取得できませんでした。");
		return;
	}

	try {
		$token = $this->square_json_request($this->square_base_url($ctl) . "/oauth2/token", [
			"client_id" => $this->square_application_id($ctl),
			"client_secret" => $this->square_application_secret($ctl),
			"code" => $code,
			"grant_type" => "authorization_code",
			"redirect_uri" => $this->square_redirect_url($ctl),
		]);
		$access_token = trim((string) ($token["access_token"] ?? ""));
		if ($access_token === "") {
			throw new Exception("Squareアクセストークンを取得できませんでした。");
		}

		$row = $this->get_shop($ctl, $shop_id);
		$expires_at = strtotime((string) ($token["expires_at"] ?? ""));
		$row["square_access_token"] = $access_token;
		$row["square_refresh_token"] = trim((string) ($token["refresh_token"] ?? ""));
		$row["square_token_expires_at"] = $expires_at === false ? 0 : $expires_at;
		$row["square_merchant_id"] = trim((string) ($token["merchant_id"] ?? ""));
		$row["square_location_id"] = $this->square_first_location_id($ctl, $access_token);
		$row["updated_at"] = time();
		$ctl->db("shop")->update($row);

		$this->remember_main_area($ctl);
		$ctl->res_redirect($ctl->get_APP_URL("base", "page"));
	} catch (Throwable $e) {
		$ctl->show_notification_text("Square連携に失敗しました。 " . $e->getMessage());
	}
}
```

## Public payment token refresh

```php
private function refresh_shop_square_token_if_needed(Controller $ctl, array $shop): array {
	$expires_at = (int) ($shop["square_token_expires_at"] ?? 0);
	$refresh_token = trim((string) ($shop["square_refresh_token"] ?? ""));
	if ($refresh_token === "" || $expires_at <= 0 || $expires_at > time() + 86400) {
		return $shop;
	}
	$token = $this->square_json_request($this->square_base_url($ctl) . "/oauth2/token", [
		"client_id" => $this->square_application_id($ctl),
		"client_secret" => $this->square_application_secret($ctl),
		"grant_type" => "refresh_token",
		"refresh_token" => $refresh_token,
	]);
	$access_token = trim((string) ($token["access_token"] ?? ""));
	if ($access_token === "") {
		throw new Exception("Squareアクセストークンを更新できませんでした。");
	}
	$new_expires_at = strtotime((string) ($token["expires_at"] ?? ""));
	$shop["square_access_token"] = $access_token;
	$shop["square_refresh_token"] = trim((string) (($token["refresh_token"] ?? "") ?: $refresh_token));
	$shop["square_token_expires_at"] = $new_expires_at === false ? 0 : $new_expires_at;
	$shop["updated_at"] = time();
	if ((int) ($shop["id"] ?? 0) > 0) {
		$ctl->db("shop")->update($shop);
	}
	return $shop;
}
```
