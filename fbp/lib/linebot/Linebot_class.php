<?php

class Linebot_class implements linebot {

	private $channelSecret;
	private $accessToken;
	private $logfile;
	private $lastError = null;
	private $replyQueue = [];
	private $events = [];
	private $eventIndex = 0;
	private $replyToken;
	private $userid;

	public function __construct(string $channelSecret, string $accessToken, ?string $logfile = null) {
		$this->channelSecret = $channelSecret;
		$this->accessToken = $accessToken;
		$this->logfile = $logfile;
	}

	public function getLastError(): ?string {
		return $this->lastError;
	}

	/**
	 * Webhookを受けて、エラーやイベント無しならここでHTTPレスポンスを返して終了。
	 * 正常でイベントがある場合のみ、署名検証済みの配列データを返す。
	 * 戻り値: array|null （null のときは既にレスポンスを返している）
	 */
	public function handle_webhook(): ?array {
		$this->lastError = null;

		if ($this->channelSecret === '') {
			return $this->respond(500, 'Server misconfiguration: empty channel secret');
		}

		// 生ボディ取得
		$input = file_get_contents('php://input');
		if ($input === false) {
			$this->events = [];
			return $this->respond(400, 'Failed to read input');
		}

		// 署名取得
		$signature = $this->getHeader('X-Line-Signature');
		if ($signature === '') {
			$this->events = [];
			return $this->respond(403, 'Missing signature');
		}

		// 署名検証
		$expected = base64_encode(hash_hmac('sha256', $input, $this->channelSecret, true));
		if (!hash_equals($expected, $signature)) {
			$this->events = [];
			return $this->respond(403, 'Invalid signature');
		}

		// JSON デコード（例外安全）
		$data = json_decode($input, true);
		if (!is_array($data)) {
			$errMsg = function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSON decode error';
			$this->events = [];
			return $this->respond(400, 'Invalid JSON: ' . $errMsg);
		}

		// ログ
		$this->log(is_array($data) ? print_r($data, true) : (string) $input);

		// イベント有無
		if (empty($data['events']) || !is_array($data['events'])) {
			$this->events = [];
			return $this->respond(200, 'OK'); // イベント無しは200
		}

		// replyTokenを設定
		$this->events = $data['events'] ?? [];
		$this->eventIndex = 0;

		// 正常・イベントあり → 呼び出し側で処理継続
		http_response_code(200);
		return $data;
	}

	public function nextEvent(): ?array {
		if ($this->eventIndex >= count($this->events)) {
			return null;
		}
		$event = $this->events[$this->eventIndex++];
		$this->replyToken = $event['replyToken'] ?? null;
		$this->userid = (string)($event["source"]["userId"] ?? "");
		$this->replyQueue = [];
		return $event;
	}

	public function getUserID(): ?string {
		return $this->userid;
	}

	/** ここでHTTPレスポンスを返して null を返す（呼び出し側で return; しやすい形） */
	private function respond(int $status, string $body): ?array {
		$this->lastError = ($status === 200) ? null : $body;
		http_response_code($status);
		echo $body;

		// empty data
		$data["events"] = [];
		return $data;
	}

	/** 大文字小文字に頑健なヘッダ取得（getallheaders→$_SERVERの順） */
	private function getHeader(string $name): string {
		// getallheaders() を優先
		if (function_exists('getallheaders')) {
			foreach (getallheaders() as $k => $v) {
				if (strcasecmp($k, $name) === 0) {
					return is_array($v) ? (string) reset($v) : (string) $v;
				}
			}
		}
		// $_SERVER フォールバック
		$serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
		return isset($_SERVER[$serverKey]) ? (string) $_SERVER[$serverKey] : '';
	}

	public function log($text): void {
		if ($this->logfile) {

			if (is_array($text)) {
				$text = print_r($text, true);
			}

			file_put_contents($this->logfile, '[' . date('c') . "]\n" . $text . "\n\n", FILE_APPEND);
		}
	}

	public function set_text(string $text) {
		$this->replyQueue[] = ['type' => 'text', 'text' => $text];
	}

	/** スタンプを返信キューに追加（fluent） */
	public function set_sticker(string $packageId, string $stickerId) {
		$this->replyQueue[] = [
		    'type' => 'sticker',
		    'packageId' => (string) $packageId,
		    'stickerId' => (string) $stickerId,
		];
	}

	/** Flex Messageを返信キューに追加 */
	public function set_flex(string $altText, array $contents): bool {
		$altText = trim($altText);
		$containerType = (string)($contents['type'] ?? '');
		if ($altText === '' || mb_strlen($altText) > 1500) {
			$this->log('[set_flex] invalid altText');
			return false;
		}
		if (!in_array($containerType, ['bubble', 'carousel'], true)) {
			$this->log('[set_flex] invalid container type');
			return false;
		}

		$this->replyQueue[] = [
			'type' => 'flex',
			'altText' => $altText,
			'contents' => $contents,
		];
		return true;
	}

	public function send_reply(): bool {
		if (count($this->replyQueue) > 5) {
			$this->log('Too many messages for a single reply (max 5)');
			return false;
		}

		$url = 'https://api.line.me/v2/bot/message/reply';
		$body = [
		    'replyToken' => $this->replyToken,
		    'messages' => $this->replyQueue,
		];

		[$status, $resp] = $this->postJson($url, $body);
		$this->log("[send_reply] status=$status body=$resp");

		$ok = $status >= 200 && $status < 300;

		if (!$ok) {
			$this->log("Reply failed (HTTP $status)");
		}

		$this->replyQueue = [];

		return $ok;
	}

	/** 1:1のプロフィール取得 */
	public function getUserProfile(string $userId): ?array {
		$url = 'https://api.line.me/v2/bot/profile/' . rawurlencode($userId);
		[$status, $resp] = $this->get($url);
		if ($status >= 200 && $status < 300) {
			$this->log("[getUserProfile] status=$status body=$resp");
			return json_decode($resp, true);
		} else {
			$this->log("[getUserProfile ERROR] status=$status body=$resp");
			return null;
		}
	}

	/** （必要なら）グループ・ルーム内メンバー取得 */
	public function getGroupMemberProfile(string $groupId, string $userId): ?array {
		$url = 'https://api.line.me/v2/bot/group/' . rawurlencode($groupId) . '/member/' . rawurlencode($userId);
		[$status, $resp] = $this->get($url);
		return ($status >= 200 && $status < 300) ? json_decode($resp, true) : null;
	}

	public function getRoomMemberProfile(string $roomId, string $userId): ?array {
		$url = 'https://api.line.me/v2/bot/room/' . rawurlencode($roomId) . '/member/' . rawurlencode($userId);
		[$status, $resp] = $this->get($url);
		return ($status >= 200 && $status < 300) ? json_decode($resp, true) : null;
	}

	/* ------------ 内部ユーティリティ ------------ */

	private function get(string $url): array {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
		    CURLOPT_RETURNTRANSFER => true,
		    CURLOPT_HTTPHEADER => [
			'Authorization: Bearer ' . $this->accessToken,
			'User-Agent: LineBotPHP/1.0',
		    ],
		    CURLOPT_CONNECTTIMEOUT => 5,
		    CURLOPT_TIMEOUT => 10,
		]);
		$resp = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
		if ($resp === false) {
			$err = curl_error($ch);
			$this->log("[GET ERROR] $url : $err");
			$resp = '';
		}
			return [$status, $resp];
	}

	private function postJson(string $url, array $payload): array {
		$ch = curl_init($url);
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		curl_setopt_array($ch, [
		    CURLOPT_RETURNTRANSFER => true,
		    CURLOPT_POST => true,
		    CURLOPT_POSTFIELDS => $json,
		    CURLOPT_HTTPHEADER => [
			'Authorization: Bearer ' . $this->accessToken,
			'Content-Type: application/json',
			'User-Agent: LineBotPHP/1.0',
		    ],
		    CURLOPT_CONNECTTIMEOUT => 5,
		    CURLOPT_TIMEOUT => 10,
		]);
		$resp = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
		if ($resp === false) {
			$err = curl_error($ch);
			$this->log("[POST ERROR] $url : $err");
			$resp = '';
		}
			return [$status, $resp];
	}

	public function send_push(string $userId): bool {
		if ($userId === '') {
			$this->log('[push] empty userId');
			return false;
		}
		if (count($this->replyQueue) === 0) {
			$this->log('[push] empty queue');
			return false;
		}
		if (count($this->replyQueue) > 5) {
			$this->log('[push] Too many messages for a single push (max 5)');
			return false;
		}

		$url = 'https://api.line.me/v2/bot/message/push';
		$body = [
		    'to' => $userId,
		    'messages' => $this->replyQueue,
		];

		[$status, $resp] = $this->postJson($url, $body);
		$this->log("[push] status=$status body=$resp");

		$ok = $status >= 200 && $status < 300;
		if (!$ok) {
			$this->log("Push failed (HTTP $status)");
		}

		// 送信後はキューをクリア（replyと同じ挙動）
		$this->replyQueue = [];

		return $ok;
	}

	public function set_location(string $title, string $address, float $latitude, float $longitude): void {
		if ($title === '' || $address === '') {
			$this->log('[set_replyLocation] empty title/address');
			return;
		}
		if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
			$this->log('[set_replyLocation] invalid lat/lng range');
			return;
		}
		$this->replyQueue[] = [
		    'type' => 'location',
		    'title' => $title,
		    'address' => $address,
		    'latitude' => round($latitude, 6),
		    'longitude' => round($longitude, 6),
		];
	}

	/**
	 * "35.709479169778156, 139.78123441448142" のような文字列から
	 * 緯度経度を抽出して返信キューに追加（reply/push共通）
	 */
	public function set_location_from_string(string $title, string $address, string $latlng): void {
		// 全角コンマ対策＋余分な空白除去
		$latlng = str_replace('，', ',', trim($latlng));
		$parts = array_map('trim', explode(',', $latlng));
		if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
			$this->log("[set_LocationFromString] invalid latlng format: $latlng");
			return;
		}

		// 数値化（不正値は NaN → 範囲チェックで弾かれる）
		$lat = (float) $parts[0];
		$lng = (float) $parts[1];

		$this->set_location($title, $address, $lat, $lng);
	}
	
	public function clear_queue(): void{
		$this->replyQueue = [];
	}
}
