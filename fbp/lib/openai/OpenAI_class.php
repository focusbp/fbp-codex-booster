<?php

namespace openai;

/**
 * OpenAI Responses API を手軽に扱うユーティリティ
 * - PHP 7.3 対応（typed properties 未使用）
 * - 依存: cURL, JSON 拡張
 */
class OpenAI_class implements \openai\OpenAI {

	/** @var string */
	private $apiKey;

	/** @var string */
	private $baseUrl;

	/** @var string */
	private $model;

	/** @var string|null */
	private $toolsDir;

	/** @var array<string, FunctionTool> name => instance */
	private $tools = [];

	/** @var string|null 最新の Responses API response_id（ツール往復用） */
	private $responseId = null;

	/** ログ出力先ファイル（null なら無効） */
	private $logfile = null;

	/** ログの最大文字数（超えたら切詰め） */
	private $logTruncate = 10000; // 10KB 目安

	/** JSON を整形して出すか */
	private $logPrettyJson = true;
	private $ctl = null;
	private $message_recorder = null;
	private $status_manager = null;
	private $network_logger = null;
	public $curl_timeout = 60;
	private $messages_history_max = 10;
	private $instructions = "";
	private $tokenUsageTracker = null;

	/**
	 * @param string      $apiKey            OpenAI API Key
	 * @param string|null $toolsDir          FunctionTool クラス群を置くディレクトリ（null可）
	 * @param string      $model             使用モデル（例: 'gpt-4.1-mini' など）
	 * @param string      $baseUrl           API ベースURL（通常は https://api.openai.com/v1）
	 */
	public function __construct(
		$apiKey,
		$toolsDir = null,
		$model = 'gpt-5',
		$logfile = null,
		$instructions = "",
		$databases = [],
		\openai\Recorder $message_recorder = null,
		\openai\StatusManager $status_manager = null,
		\openai\Logger $network_logger = null,
		\Controller $ctl = null
	) {
		$this->apiKey = $apiKey;
		$this->toolsDir = $toolsDir;
		$this->model = $model;
		if (!empty($logfile)) {
			$this->logfile = $logfile;
		}
		$this->ctl = $ctl;

		if ($this->toolsDir && is_dir($this->toolsDir)) {
			$this->loadToolsFromDirectory($this->toolsDir);
		}

		$this->message_recorder = $message_recorder;
		$this->status_manager = $status_manager;
		$this->network_logger = $network_logger;

		$this->baseUrl = 'https://api.openai.com/v1';

		$this->instructions = $instructions;
	}

	public function set_messages_history_max($max) {
		$this->messages_history_max = $max;
	}

	/** 会話履歴をクリア（response_id も破棄） */
	public function clear_messages(): void {
		$this->message_recorder->write([]);
		$this->network_logger->clear_log();
		$this->status_manager->set_status("");
		$this->responseId = null;
	}

	/** 現在の会話履歴（読み取り用） */
	public function get_messages(): array {
		$messages = $this->readMessages();
		$ret = $this->limit_message_history($messages, $this->messages_history_max);

		// インストラクションを付ける
		$ret_add_instruction = array_merge(
			[[
			'role' => "system",
			'content' => $this->instructions,
			    ]],
			$ret
		);

		return $ret_add_instruction;
	}

	/**  system メッセージを追加 */
	public function add_system(string $content): void {
		$this->appendMessage('system', $content);
	}

	/**  user メッセージを追加 */
	public function add_user(string $content): void {
		$this->appendMessage('user', $content);
	}

	public function add_assistant(string $content): void {
		$this->appendMessage('assistant', $content);
	}

	/**
	 * チャット履歴を上限件数でトリミングする
	 * - user / assistant のみをカウント対象にして、末尾から $max 件だけ残す
	 * - system / developer / tool / function 等はカウントせず必ず残す
	 * - 配列の元の順序（古い→新しい）は維持される
	 *
	 * @param array $messages [ ['role'=>'user','content'=>'...'], ... ]
	 * @param int   $max      カウント対象（user/assistant）の最大件数（0以下なら全削除）
	 * @param array $countedRoles  カウント対象ロール（既定: ['user','assistant']）
	 * @return array トリミング済みの $messages
	 */
	function limit_message_history(array $messages, int $max, array $countedRoles = ['user', 'assistant']): array {
		if ($max <= 0) {
			return $messages;
		}

		$keepFlags = [];
		$remaining = $max;

		// 末尾（新しい方）から走査して、user/assistant を $max 件だけ「残す」フラグを立てる
		for ($i = count($messages) - 1; $i >= 0; $i--) {
			$m = $messages[$i];
			$role = isset($m['role']) ? (string) $m['role'] : '';

			if (in_array($role, $countedRoles, true)) {
				if ($remaining > 0) {
					$keepFlags[$i] = true;   // カウント対象だが枠内 → 残す
					$remaining--;
				} else {
					$keepFlags[$i] = false;  // カウント対象で枠外 → 落とす
				}
			} else {
				$keepFlags[$i] = true;       // カウントしないロールは常に残す
			}
		}

		// フラグに従って順序を保ったまま再構成
		$out = [];
		foreach ($messages as $idx => $m) {
			if (!empty($keepFlags[$idx])) {
				$out[] = $m;
			}
		}

		return $out;
	}

	/**
	 * メッセージを送り、必要なら Function Calling も自動で処理し、最終回答を返す。
	 * - 会話履歴はクラス内に保持されます。
	 *
	 * @param mixed $input string|array|null
	 *   - string: ユーザーの発話として追加
	 *   - array:  1) ['role'=>'user','content'=>'...'] の単発  2) 上記配列の配列（複数）
	 *   - null:   直前までの履歴で再実行
	 * @param array $options 例: ['auto_tools' => true]
	 * @return \openai\Response
	 */
	public function respond($input = null): ?\openai\Response {

		if (empty($input)) {
			return null;
		}

		// CHANGE: トラッカー初期化（1回のrespondごとに集計するならreset）
		if (!$this->tokenUsageTracker instanceof \token_usage_tracker) {
			$this->tokenUsageTracker = new \token_usage_tracker();
		}

		$this->appendMessage('user', $input);
		$messages = $this->get_messages();

		$toolDefs = $this->buildToolDefinitions();

		$request = [
		    'model' => $this->model,
		    'input' => $messages,
		    'parallel_tool_calls' => false,
		];
		$request['tools'] = $toolDefs;

		$check_flg = false;

		// 1回目
		$this->ctl->close_all_db();
		$resp = $this->post('/responses', $request, "Thinking your request: " . $input);
		
		// 使用量保存
		$this->tokenUsageTracker->addFromResponse($resp);
		
		$this->responseId = $this->extractResponseId($resp);
		$check = $this->appendAssistantMessagesFromResponse($resp);
		if ($check) {
			$check_flg = true;
		}

		// ツール呼び出しループ
		while ($this->hasToolCalls($resp)) {
			$items = [];
			$names = [];
			foreach ($this->extractToolCalls($resp) as $call) {
				$name = (string) ($call['name'] ?? '');
				$callId = (string) ($call['call_id'] ?? '');

				if ($callId === '') {
					// call_id が無いと返信できないのでスキップ
					continue;
				}

				$tool = $this->getToolByName($name);

				$argsRaw = $call['arguments'] ?? '{}';
				if (!is_string($argsRaw)) {
					$argsRaw = json_encode($argsRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				}
				$args = json_decode($argsRaw, true);
				if (!is_array($args)) {
					$args = [];
				}

				$output = '';
				try {
					if (!$tool) {
						$output = 'error: tool not found: ' . $name;
						$names[] = $name . "(" . $output . ") ";
					} else {
						$result = $tool->execute($this->ctl, $args);
						//$this->log("FUNCTION:$name\n" . print_r($result, true));

						$output = is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
						// ステータス表示用
						if (is_array($result)) {
							$result_keys = array_keys($result);
							if (!empty($result["fields"])) {
								$field_keys = [];
								foreach ($result["fields"] as $f) {
									$field_keys[] = $f["field_name"];
								}
								$result_keys = array_merge($result_keys, $field_keys);
							}
						} elseif (is_object($result)) {
							// 念のためオブジェクトもケア（戻り値が stdClass などの場合）
							$result_keys = array_keys(get_object_vars($result));
						} else {
							// 想定外（文字列とかnullとか）でも壊れないように
							$result_keys = [];
						}
						$names[] = $name . " Data Fields:" . implode(",", $result_keys) . ") ";
					}
				} catch (\Throwable $e) {
					$output = 'error: ' . $e->getMessage() . " : " . $e->getTraceAsString();
					$names[] = $name . "(" . $output . ") ";
				}


				$items[] = [
				    'type' => 'function_call_output',
				    'call_id' => $callId, // ← 受け取った call_id をそのまま
				    'output' => $output, // ← 文字列で返す（配列は json_encode 済み）
				];
			}

			$nextReq = [
			    'model' => $this->model, // ← 必須
			    'previous_response_id' => $this->responseId,
			    'input' => $items, // ← ここに function_call_output
			    'tools' => $toolDefs,
			];

			$this->ctl->close_all_db();
			$resp = $this->post('/responses', $nextReq, "Function: " . implode(",", $names));
			
			// 使用量保存
			$this->tokenUsageTracker->addFromResponse($resp);

			$this->responseId = $this->extractResponseId($resp);
			$check = $this->appendAssistantMessagesFromResponse($resp);
			if ($check) {
				$check_flg = true;
			}
		}

		if (!$check_flg) {
			$this->appendMessage("assistant", "完了しました");
		}

		$this->set_status_msg("END");
		
		//データベースにusageを記録
		$this->store_usage();

		// 履歴込みでラップ
		return new \openai\Response_class($resp);
	}
	
	/**
	 * 利用料を保存する
	 */
	private function store_usage(){
		
		$year = date("Y");
		$month = date("m");
		$usage_list = $this->ctl->db("usage","assistants")->select(["year","month"],[$year,$month],true,"AND",null,SORT_DESC,1);
		if(count($usage_list) == 0){
			$usage = [
			    "year" => $year,
			    "month" => $month,
			    "in" => 0,
			    "out" => 0,
			    "total" => 0,
			    "count" => 0,
			];
			$this->ctl->db("usage","assistants")->insert($usage);
		}else{
			$usage = $usage_list[0];
		}
		$usage["in"] += $this->getTokenUsageTotals("in");
		$usage["out"] += $this->getTokenUsageTotals("out");
		$usage["total"] += $this->getTokenUsageTotals("total");
		$usage["count"] += 1;
		$this->ctl->db("usage","assistants")->update($usage);	
	}



	private function set_status_msg($msg) {
		$this->status_manager->set_status($msg);
	}

	/* =========================
	  内部: Function Calling 周り
	  ========================= */

	private function loadToolsFromDirectory(string $dir): void {
		$rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
		foreach ($rii as $file) {
			if ($file->isDir())
				continue;
			if (substr($file->getFilename(), -4) !== '.php')
				continue;

			// 事前・事後の差分で新規クラスだけ拾う
			$before = get_declared_classes();
			require_once $file->getPathname();
			$after = get_declared_classes();
			$newCls = array_diff($after, $before);

			foreach ($newCls as $fqcn) {
				$impl = class_implements($fqcn) ?: [];
				if (!in_array(FunctionTool::class, $impl, true)) {
					throw new \RuntimeException("class {$fqcn} must implement \\openai\\FunctionTool");
				}

				$ref = new \ReflectionClass($fqcn);

				/** @var FunctionTool $inst */
				$inst = $ref->newInstance();
				// ツール名が重複したら後勝ち（上書き）
				$this->tools[$inst->name()] = $inst;
			}
		}
	}

	function buildToolDefinitions(): array {
		$defs = [];

		foreach ($this->tools as $tool) {
			$schema = $tool->parameters();
			if (!is_array($schema))
				$schema = [];

			// 1) トップは必ず object
			$schema['type'] = 'object';

			// 2) properties 正規化
			$propKeys = [];
			if (!isset($schema['properties'])) {
				$schema['properties'] = new \stdClass(); // 空 {}
			} elseif ($schema['properties'] instanceof \stdClass) {
				$propKeys = []; // 空オブジェクト
			} elseif (is_array($schema['properties'])) {
				// ★ここで array_values() や array_filter()（デフォルト）禁止！
				$propKeys = array_keys($schema['properties']);
				if (empty($propKeys)) {
					$schema['properties'] = new \stdClass(); // 空の場合のみ {}
				}
			} else {
				$schema['properties'] = new \stdClass();
			}

			// 3) required を properties のキーに強制同期
			$schema['required'] = $propKeys; // ← これで 'payload' ずれを根絶
			// 4) 追加プロパティは不可
			if (!isset($schema['additionalProperties'])) {
				$schema['additionalProperties'] = false;
			}

			$defs[] = [
			    'type' => 'function',
			    'name' => (string) $tool->name(),
			    'description' => (string) $tool->description(),
			    'parameters' => $schema, // ← schema 全体を渡す
			    'strict' => true,
			];
		}

		return $defs;
	}

	private function extractResponseId(array $resp): ?string {
		return isset($resp['id']) ? $resp['id'] : (isset($resp['response']['id']) ? $resp['response']['id'] : null);
	}

	/**
	 * Responses API の応答から assistant 側のメッセージを抽出し、会話履歴へ追記
	 */
	private function appendAssistantMessagesFromResponse(array $resp): bool {
		$check_flg = false;
		if (!isset($resp['output']) || !is_array($resp['output'])) {
			$this->appendMessage("assistant", print_r($resp, true));
			return true;
		}

		foreach ($resp['output'] as $item) {
			if (!isset($item['type']) || $item['type'] !== 'message') {
				continue;
			}

			// 新旧両対応の正規化（既存ヘルパを利用）
			$msg = $this->normalizeMessageItem($item);
			if (!$msg) {
				continue;
			}

			$role = isset($msg['role']) ? $msg['role'] : 'assistant';

			// content を平文寄りに連結
			$content = '';
			if (isset($msg['content']) && is_array($msg['content'])) {
				foreach ($msg['content'] as $block) {
					if (is_array($block)) {
						if (isset($block['text'])) {
							$content .= is_string($block['text']) ? $block['text'] : json_encode($block['text'], JSON_UNESCAPED_UNICODE);
						} elseif (isset($block['content'])) {
							$content .= is_string($block['content']) ? $block['content'] : json_encode($block['content'], JSON_UNESCAPED_UNICODE);
						}
					} elseif (is_string($block)) {
						$content .= $block;
					}
				}
			} elseif (isset($msg['content']) && is_string($msg['content'])) {
				$content = $msg['content'];
			} else {
				$content = json_encode($msg, JSON_UNESCAPED_UNICODE);
			}

			// ★ セッションに追記（systemは通常来ないが来ても保存OK）
			$this->appendMessage($role, $content);
			$check_flg = true;
		}
		return $check_flg;
	}

	/* =========================
	  内部: HTTP ヘルパ
	  ========================= */

	private function get(string $path, array $query = []): array {
		if (!empty($query)) {
			$path .= (strpos($path, '?') === false ? '?' : '&') . http_build_query($query);
		}
		return $this->request('GET', $path, null);
	}

	private function post(string $path, array $json, $status_message = ''): array {
		$this->set_status_msg($status_message);
		$res = $this->request('POST', $path, $json, ['Content-Type: application/json']);
		return $res;
	}

	private function delete(string $path): array {
		return $this->request('DELETE', $path, null);
	}

	private function request(string $method, string $path, $data = null, array $headers = []): array {
		$url = $this->baseUrl . $path;
		$ch = curl_init($url);

		$defaultHeaders = [
		    'Authorization: Bearer ' . $this->apiKey,
		    'Accept: application/json',
		];
		// 送信ヘッダ（後勝ちでマージ）
		$finalHeaders = array_merge($defaultHeaders, $headers);

		if (($method === 'POST' || $method === 'PATCH') && $data !== null) {
			$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($json === false) {
				throw new \RuntimeException('Failed to json_encode request payload');
			}
			$postFields = $json;
			// Content-Type を重複なしで付与
			$hasCT = false;
			foreach ($finalHeaders as $h) {
				if (stripos($h, 'Content-Type:') === 0) {
					$hasCT = true;
					break;
				}
			}
			if (!$hasCT) {
				$finalHeaders[] = 'Content-Type: application/json';
			}
		}

		$opts = [
		    CURLOPT_RETURNTRANSFER => true,
		    CURLOPT_CUSTOMREQUEST => $method,
		    CURLOPT_HTTPHEADER => $finalHeaders,
		    CURLOPT_TIMEOUT => $this->curl_timeout,
		];
		if (isset($postFields)) {
			$opts[CURLOPT_POSTFIELDS] = $postFields;
		}

		// ---- 送信ログ
		$requestTools = is_array($data) && isset($data["tools"]) ? $data["tools"] : [];
		$requestInput = is_array($data) && isset($data["input"]) ? $data["input"] : null;
		$this->log([
		    'direction' => 'server ---> chatGPT',
		    'tools' => $this->log_tools($requestTools),
		    'data' => $this->readable_output($requestInput),
		]);

		curl_setopt_array($ch, $opts);

		session_write_close();
		$raw = curl_exec($ch);
		session_start();

		if ($raw === false) {
				$err = curl_error($ch);
				throw new \RuntimeException('cURL error: ' . $err);
			}

			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			$resp = json_decode($raw, true);

		// ---- 受信ログ（JSONが取れればJSON、なければ文字列）
		$responseOutput = is_array($resp) && isset($resp["output"]) ? $resp["output"] : null;
		$this->log([
		    'direction' => 'server <--- chatGPT',
		    'status' => $status ?? null,
		    'data' => $this->readable_output($responseOutput),
		]);

		return is_array($resp) ? $resp : ['raw' => $raw];
	}

	private function readable_output($output) {
		if (is_array($output)) {
			foreach ($output as $key => &$arr) {

				if ($arr["type"] == "reasoning") {
					if (is_array($arr["summary"])) {
						if (count($arr["summary"]) == 0) {
							unset($output[$key]);
							continue;
						}
					}
				}

				if (is_string($arr["output"])) {
					try {
						$decoded = json_decode($arr["output"], true);
						unset($arr["output"]);
						$arr["payload"] = $decoded;
					} catch (Exception $e) {
						//
					}
				}
				if (is_string($arr["arguments"])) {
					try {
						$decoded = json_decode($arr["arguments"], true);
						$arr["arguments"] = $decoded;
					} catch (Exception $e) {
						//
					}
				}
			}
			return $output;
		}
		try {
			$decoded = json_decode($output, true);
			return $decoded;
		} catch (\Throwable $e) {
			// 失敗したらそのまま文字列で出す
			return $output;
		}
	}

	private function log_tools($tools) {
		$function_arr = [];

		if (!is_array($tools)) {
			return;
		}

		foreach ($tools as $tool) {
			if ($tool["type"] == "function") {
				$function_arr[] = $tool["name"];
			}
		}
		return [
		    "function" => implode(",", $function_arr),
		];
	}

	/** output[] の1要素から message ペイロードを取り出す（新旧両対応） */
	private function normalizeMessageItem(array $item): ?array {
		if (isset($item['message']) && is_array($item['message'])) {
			return $item['message']; // 旧式
		}
		if (isset($item['content'])) {
			return [
			    'role' => isset($item['role']) ? $item['role'] : 'assistant',
			    'content' => $item['content'], // 新式
			];
		}
		return null;
	}

	/**
	 * 履歴を取得（system含む・時系列のまま）
	 *
	 * @return array
	 */
	private function readMessages(): array {
		return $this->message_recorder->read();
	}

	/**
	 * 履歴を書き戻し（丸ごと上書き）
	 *
	 * @param array $messages
	 * @return void
	 */
	private function writeMessages(array $messages): void {
		$this->message_recorder->write($messages);
	}

	/**
	 * 1件を履歴に追記
	 *
	 * @param string $role
	 * @param mixed  $content
	 * @return void
	 */
	private function appendMessage(string $role, $content): void {
		$this->message_recorder->append($role, $content);
	}

	/**
	 * ツール名から FunctionTool を取得する
	 * 見つからない場合は null を返す
	 *
	 * @param string $name
	 * @return \openai\FunctionTool|null
	 */
	private function getToolByName(string $name): ?\openai\FunctionTool {
		if ($name === '') {
			return null;
		}

		// 1) 連想配列で名前→インスタンスを持っている場合（推奨）
		if (isset($this->tools[$name]) && $this->tools[$name] instanceof \openai\FunctionTool) {
			return $this->tools[$name];
		}

		// 2) 念のためフォールバック（配列キーが名前でない実装向け）
		if (is_array($this->tools)) {
			foreach ($this->tools as $tool) {
				if ($tool instanceof \openai\FunctionTool) {
					// クラス側の name() と一致するかチェック
					if (method_exists($tool, 'name') && $tool->name() === $name) {
						return $tool;
					}
				}
			}
		}

		// 該当なし
		return null;
	}

	/**
	 * レスポンス中の function_call を抽出
	 * 返り値: [ ['name'=>..., 'arguments'=> (JSON文字列), 'call_id'=> ... ], ... ]
	 */
	private function extractToolCalls(array $resp): array {
		$calls = [];
		foreach (($resp['output'] ?? []) as $out) {
			if (($out['type'] ?? null) === 'function_call') {
				$calls[] = [
				    'name' => $out['name'] ?? null,
				    'arguments' => $out['arguments'] ?? '{}', // JSON文字列想定
				    'call_id' => $out['call_id'] ?? null,
				];
			}
		}
		return $calls;
	}

	public function log($text): void {

		if (is_array($text) || is_object($text)) {
			// 配列は JSON で見やすく
			$json = json_encode($text, $this->logPrettyJson ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : 0
			);
			$text = $json !== false ? $json : print_r($text, true);
		} elseif (!is_string($text)) {
			$text = (string) $text;
		}

		// 長すぎるログは切り詰める
		if ($this->logTruncate > 0 && strlen($text) > $this->logTruncate) {
			$text = mb_strcut($text, -1 * $this->logTruncate, null, 'UTF-8');
		}

		$this->network_logger->add_log($text);
	}

	public function respondStream($messages, array $options = []): void {
		$req = [
		    'model' => $this->model,
		    'stream' => true,
		    'input' => $messages,
		];

		$this->sseHeaders(); // text/event-stream 等（上の stream.php と同様）

		$this->curlStream('/responses', $req);
	}

	private function sseHeaders(): void {
		header('Content-Type: text/event-stream; charset=utf-8');
		header('Cache-Control: no-cache');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no');
		@ob_end_flush();
		@ob_implicit_flush(true);
	}

	private function curlStream(string $path, array $req): void {
		$ch = curl_init($this->baseUrl . $path);
		curl_setopt_array($ch, [
		    CURLOPT_RETURNTRANSFER => false,
		    CURLOPT_POST => true,
		    CURLOPT_HTTPHEADER => [
			'Authorization: Bearer ' . $this->apiKey,
			'Content-Type: application/json',
			'Accept: text/event-stream',
		    ],
		    CURLOPT_POSTFIELDS => json_encode($req, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		    CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
			    echo $chunk;
			    @ob_flush();
			    flush();
			    return strlen($chunk);
		    },
		    CURLOPT_TIMEOUT => 0,
		]);
			curl_exec($ch);
	}

	private function extractFunctionCalls(array $resp): array {
		$calls = [];
		if (!isset($resp['output']) || !is_array($resp['output']))
			return $calls;

		foreach ($resp['output'] as $item) {
			if (!is_array($item))
				continue;
			if (($item['type'] ?? '') !== 'function_call')
				continue;

			$name = isset($item['name']) ? (string) $item['name'] : '';
			$args = isset($item['arguments']) ? (string) $item['arguments'] : '{}';
			$callId = isset($item['call_id']) ? (string) $item['call_id'] : '';
			if ($name !== '' && $callId !== '') {
				$calls[] = ['name' => $name, 'arguments' => $args, 'call_id' => $callId];
			}
		}
		return $calls;
	}

	private function hasToolCalls(array $resp): bool {
		return count($this->extractFunctionCalls($resp)) > 0;
	}

	/** 単独ファイルを完全に削除する */
	public function deleteUploadedFile(string $fileId): void {
		// OpenAI APIの /files/{file_id} DELETE
		try {
			$this->delete("/files/{$fileId}");
		} catch (\Throwable $e) {
			//
		}
	}

	/**
	 * OpenAI /files の全ファイル実体を削除するユーティリティ。
	 * 注意: 取り消し不可。
	 *
	 * @param string|null $purpose 特定 purpose のみ削除（例: "assistants"）。null なら全件対象。
	 * @return array { ok: bool, deleted: int, errors: array[] }
	 */
	public function delete_all_files(?string $purpose = null): array {
		$this->log('delete_all_files: start');
		$deleted = 0;
		$errors = [];
		$after = null;
		$loop = 0;
		try {
			do {
				$qs = $after ? ['after' => $after] : [];
				if ($purpose !== null) {
					$qs['purpose'] = $purpose;
				}
				$res = $this->get('/files', $qs);
				$data = (isset($res['data']) && is_array($res['data'])) ? $res['data'] : [];
				foreach ($data as $row) {
					if (!isset($row['id']))
						continue;
					$fid = (string) $row['id'];
					try {
						$this->deleteUploadedFile($fid); // /files/{id} DELETE
						$deleted++;
					} catch (\Throwable $e) {
						$errors[] = ['file_id' => $fid, 'error' => $e->getMessage()];
					}
				}
				$hasMore = isset($res['has_more']) ? (bool) $res['has_more'] : false;
				$after = $hasMore && isset($res['last_id']) ? (string) $res['last_id'] : null;
				$loop++;
			} while (!empty($after) && $loop < 100); // 念のため無限ループ防止
		} catch (\Throwable $e) {
			$errors[] = ['file_id' => null, 'error' => $e->getMessage()];
		}
		$this->log(['delete_all_files: done', 'deleted' => $deleted, 'errors' => $errors]);
		return ['ok' => empty($errors), 'deleted' => $deleted, 'errors' => $errors];
	}
	
	public function getTokenUsageTotals($name): int {
	    if ($this->tokenUsageTracker instanceof \token_usage_tracker) {
		    $usage = $this->tokenUsageTracker->getTotals();
		    if($name == "in"){
			    return $usage["input_tokens"];
		    }else if($name == "out"){
			    return $usage["output_tokens"];
		    }else{
			    return $usage["total_tokens"];
		    }
	    }
	    return 0;
	}
}
