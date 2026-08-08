<?php

class webhook_line {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function receive(Controller $ctl) {
		if ($this->is_line_webhook_request()) {
			$this->receive_line($ctl);
			return;
		}
		$ctl->deny_forbidden_access();
	}

	protected function is_line_webhook_request(): bool {
		if (!empty($_SERVER["HTTP_X_LINE_SIGNATURE"])) {
			return true;
		}
		if (!empty($_SERVER["HTTP_X_LINE_DELIVERY_TAG"])) {
			return true;
		}
		return false;
	}

	public function receive_line(Controller $ctl) {
		$linebot = $ctl->create_linebot();
		$linebot->handle_webhook();

		while ($event = $linebot->nextEvent()) {
			$userid = (string)$linebot->getUserID();
			if ($userid === "") {
				continue;
			}
			$userprofile = $linebot->getUserProfile($userid);
			if ($userprofile == null) {
				continue;
			}
			$displayname = (string)($userprofile["displayName"] ?? "");

			$line_member = $this->resolve_member_by_rule($ctl, $event, $userid, $displayname);
			if ($line_member === null) {
				continue;
			}

			if (($event["type"] ?? "") == "message") {
				$msgType = (string)($event["message"]["type"] ?? "");
				switch ($msgType) {
					case "text":
						$text = (string)($event["message"]["text"] ?? "");
						$rule = $this->find_rule_by_text($ctl, "0", $text);
						$matched_rule = ($rule !== null);
						if ($rule !== null) {
							$handled = $this->execute_rule_action($ctl, $linebot, $rule, $event, $line_member, $text, $userid, $displayname);
							if ($handled === null) {
								break;
							}
							if ($handled) {
								break;
							}
						}
						$rule = $matched_rule ? null : $this->find_rule_by_unmatch($ctl, "0");
						if ($rule !== null) {
							$handled = $this->execute_rule_action($ctl, $linebot, $rule, $event, $line_member, $text, $userid, $displayname);
							if ($handled === null) {
								break;
							}
							if ($handled) {
								break;
							}
						}
						$setting = (array)($ctl->get_setting() ?? []);
						$line_forward_unknown_to_manager = (int)($setting["line_forward_unknown_to_manager"] ?? 0);
						if ($line_forward_unknown_to_manager === 0) {
							$this->forward_to_managers($ctl, $linebot, $line_member, $text);
						}
						break;

					case "image":
						$rule = $this->find_rule_by_data_type($ctl, "0", "image");
						if ($rule !== null) {
							$handled = $this->execute_rule_action($ctl, $linebot, $rule, $event, $line_member, "", $userid, $displayname);
							if ($handled) {
								break;
							}
						}
						$this->default_line_image_response($linebot);
						break;

					case "sticker":
						$rule = $this->find_rule_by_data_type($ctl, "0", "sticker");
						if ($rule !== null) {
							$handled = $this->execute_rule_action($ctl, $linebot, $rule, $event, $line_member, "", $userid, $displayname);
							if ($handled) {
								break;
							}
						}
						$this->default_line_sticker_response($linebot);
						break;

					default:
						$this->default_line_unsupported_response($linebot);
						break;
				}
			} else if (($event["type"] ?? "") === "follow") {
				$rule = $this->find_rule_by_data_type($ctl, "0", "follow");
				if ($rule !== null) {
					$handled = $this->execute_rule_action($ctl, $linebot, $rule, $event, $line_member, "", $userid, $displayname);
					if ($handled) {
						continue;
					}
				}
				$this->default_line_follow_response($ctl, $linebot, $displayname);
			}
		}
	}

	public function resolve_channel_from_request(Controller $ctl): string {
		$channel = trim((string) ($ctl->POST("channel") ?? ""));
		if ($channel === "") {
			$channel = trim((string) ($ctl->GET("channel") ?? ""));
		}
		return $this->normalize_channel($channel);
	}

	public function find_rule_by_text(Controller $ctl, string $channel, string $text): ?array {
		$rules = $ctl->db("webhook_rule", "webhook_rule")->select("channel", $this->normalize_channel($channel), true, "AND", "sort", SORT_ASC);
		foreach ($rules as $rule) {
			if ((int) ($rule["enabled"] ?? 0) !== 1) {
				continue;
			}
			$match_type = (string) ($rule["match_type"] ?? "exact");
			if (!in_array($match_type, ["exact", "contains", "regex"], true)) {
				continue;
			}
			$keyword = (string) ($rule["keyword"] ?? "");
			if ($this->is_match($match_type, $keyword, $text)) {
				return $rule;
			}
		}
		return null;
	}

	public function find_rule_by_data_type(Controller $ctl, string $channel, string $data_type): ?array {
		return $this->find_rule($ctl, $this->normalize_channel($channel), $data_type, "data_type");
	}

	public function find_rule_by_unmatch(Controller $ctl, string $channel): ?array {
		return $this->find_rule($ctl, $this->normalize_channel($channel), "[unmatch]", "unmatch");
	}

	protected function normalize_channel(string $channel): string {
		$channel = trim($channel);
		if ($channel === "" || $channel === "line") {
			return "0";
		}
		return $channel;
	}

	protected function find_rule(Controller $ctl, string $channel, string $text, ?string $required_match_type = null): ?array {
		$rules = $ctl->db("webhook_rule", "webhook_rule")->select("channel", $channel, true, "AND", "sort", SORT_ASC);
		foreach ($rules as $rule) {
			if ((int) ($rule["enabled"] ?? 0) !== 1) {
				continue;
			}
			$keyword = (string) ($rule["keyword"] ?? "");
			$match_type = (string) ($rule["match_type"] ?? "exact");
			if ($required_match_type !== null && $match_type !== $required_match_type) {
				continue;
			}
			if ($this->is_match($match_type, $keyword, $text)) {
				return $rule;
			}
		}
		return null;
	}

	protected function is_match(string $match_type, string $keyword, string $text): bool {
		if ($keyword === "") {
			return false;
		}
		if ($match_type === "data_type") {
			$left = $this->normalize_data_type_token($keyword);
			$right = $this->normalize_data_type_token($text);
			return $left !== "" && $left === $right;
		}
		if ($match_type === "contains") {
			return mb_strpos($text, $keyword) !== false;
		}
		if ($match_type === "regex") {
			$pattern = "/" . str_replace("/", "\\/", $keyword) . "/u";
			$res = @preg_match($pattern, $text);
			return $res === 1;
		}
		return $text === $keyword;
	}

	protected function normalize_data_type_token(string $value): string {
		$value = trim(mb_strtolower($value));
		if ($value === "") {
			return "";
		}
		if (substr($value, 0, 1) === "[" && substr($value, -1) === "]") {
			$value = substr($value, 1, -1);
		}
		return trim($value);
	}

	protected function resolve_member_by_rule(Controller $ctl, array $event, string $userid, string $displayname): ?array {
		$rule = $this->find_rule_by_data_type($ctl, "0", "getting_member");
		if ($rule === null) {
			return $this->resolve_member_by_default($ctl, $userid, $displayname);
		}

		$action_class = trim((string)($rule["action_class"] ?? ""));
		$appobj = $this->create_action_instance($ctl, $action_class);
		if ($appobj == null || !method_exists($appobj, "run")) {
			return null;
		}

		$context = [
			"event" => $event,
			"text" => "",
			"userid" => $userid,
			"displayname" => $displayname,
			"line_member" => null,
			"rule" => $rule,
			"data_type" => "getting_member",
		];
		$ctl->set_session("line_webhook_context", $context);
		try {
			$result = $appobj->run($ctl);
		} catch (Exception $e) {
			$ctl->set_session("line_webhook_context", null);
			$ctl->log("[webhook] getting_member failed: " . $action_class . " / " . $e->getMessage());
			return null;
		}
		$ctl->set_session("line_webhook_context", null);

		if ($result === null || !is_array($result)) {
			return null;
		}
		if (isset($result["line_member"]) && is_array($result["line_member"])) {
			return $result["line_member"];
		}
		return null;
	}

	protected function resolve_member_by_default(Controller $ctl, string $userid, string $displayname): ?array {
		$userid = trim($userid);
		if ($userid === "") {
			return null;
		}

		$list = $ctl->db("line_member")->select("userid", $userid);
		if (is_array($list) && count($list) > 0) {
			return $list[0];
		}

		$insert_row = [];
		$insert_row["userid"] = $userid;
		$insert_row["line_name"] = trim($displayname);
		$insert_row["name"] = trim($displayname);
		$insert_row["member_type"] = 0;
		$id = (int)$ctl->db("line_member")->insert($insert_row);
		if ($id <= 0) {
			return null;
		}

		$line_member = $ctl->db("line_member")->get($id);
		if (empty($line_member) || !is_array($line_member)) {
			return null;
		}
		return $line_member;
	}

	protected function create_action_instance(Controller $ctl, string $class) {
		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $class)) {
			return null;
		}
		try {
			$dir = new Dirs();
			$classfile = $dir->get_class_dir($class) . "/$class.php";
			include_once($classfile);
			if (!class_exists($class)) {
				return null;
			}
			$reflectionClass = new ReflectionClass($class);
			$constructor = $reflectionClass->getConstructor();
			if ($constructor && count($constructor->getParameters()) > 0) {
				return new $class($ctl);
			}
			return new $class();
		} catch (Exception $e) {
			$ctl->log("[webhook] failed to load action class: " . $class . " / " . $e->getMessage());
			return null;
		}
	}

	protected function execute_rule_action(
		Controller $ctl,
		linebot $linebot,
		array $rule,
		array $event,
		array $line_member,
		string $text,
		string $userid,
		string $displayname
	): ?bool {
		$action_class = trim((string)($rule["action_class"] ?? ""));
		if ($action_class === "") {
			return false;
		}

		$appobj = $this->create_action_instance($ctl, $action_class);
		if ($appobj == null || !method_exists($appobj, "run")) {
			return false;
		}

		$context = [
			"event" => $event,
			"text" => $text,
			"userid" => $userid,
			"displayname" => $displayname,
			"line_member" => $line_member,
			"rule" => $rule,
			"data_type" => (string)($event["type"] ?? ""),
		];
		$ctl->set_session("line_webhook_context", $context);
		try {
			$result = $appobj->run($ctl);
		} catch (Exception $e) {
			$ctl->set_session("line_webhook_context", null);
			$ctl->log("[webhook] action run failed: " . $action_class . " / " . $e->getMessage());
			return false;
		}
		$ctl->set_session("line_webhook_context", null);

		if (is_string($result) && $result !== "") {
			$linebot->set_text($result);
			$linebot->send_reply();
			return true;
		}
		if ($result === null) {
			return null;
		}
		if (!is_array($result)) {
			return true;
		}

		$has_reply = false;
		if (!empty($result["reply_text"])) {
			$linebot->set_text((string)$result["reply_text"]);
			$has_reply = true;
		}
		if (!empty($result["reply_sticker"]) && is_array($result["reply_sticker"])) {
			$package_id = (string)($result["reply_sticker"]["package_id"] ?? "");
			$sticker_id = (string)($result["reply_sticker"]["sticker_id"] ?? "");
			if ($package_id !== "" && $sticker_id !== "") {
				$linebot->set_sticker($package_id, $sticker_id);
				$has_reply = true;
			}
		}
		if (!empty($result["reply_flex"]) && is_array($result["reply_flex"])) {
			$alt_text = (string)($result["reply_flex"]["alt_text"] ?? "");
			$contents = $result["reply_flex"]["contents"] ?? null;
			if (is_array($contents) && $linebot->set_flex($alt_text, $contents)) {
				$has_reply = true;
			}
		}
		if ($has_reply) {
			$linebot->send_reply();
		}

		if (!empty($result["forward_to_managers"])) {
			$forward_text = $text;
			if (!empty($result["manager_message"])) {
				$forward_text = (string)$result["manager_message"];
			}
			$this->forward_to_managers($ctl, $linebot, $line_member, $forward_text);
		}

		if (isset($result["handled"])) {
			return (bool)$result["handled"];
		}
		return true;
	}

	protected function forward_to_managers(Controller $ctl, linebot $linebot, array $line_member, string $text) {
		$manager_list = $ctl->db("line_member")->select("member_type", 1);
		foreach ($manager_list as $manager) {
			$manager_userid = (string)($manager["userid"] ?? "");
			if ($manager_userid === "") {
				continue;
			}
			$linebot->clear_queue();
			$rep = (string)($line_member["line_name"] ?? "") . "(" . (string)($line_member["name"] ?? "") . ")様からメッセージがありました。メッセージ：" . $text;
			$linebot->set_text($rep);
			$linebot->send_push($manager_userid);
		}
	}

	protected function default_line_image_response(linebot $linebot) {
		$linebot->set_text("画像をありがとうございます！");
		$linebot->send_reply();
	}

	protected function default_line_sticker_response(linebot $linebot) {
		$linebot->set_sticker("11537", "52002734");
		$linebot->send_reply();
	}

	protected function default_line_unsupported_response(linebot $linebot) {
		$linebot->set_text("そのメッセージタイプには対応していません🙏");
		$linebot->send_reply();
	}

	protected function default_line_follow_response(Controller $ctl, linebot $linebot, string $displayname) {
		$setting = $ctl->get_setting();
		$greeting_message = trim((string)($setting["line_bot_greeting_message"] ?? ""));
		if ($displayname !== "") {
			$message = "友だち追加ありがとうございます！ " . $displayname . " さん、よろしくお願いします。";
			if ($greeting_message !== "") {
				$message .= "\n\n" . $greeting_message;
			}
			$linebot->set_text($message);
		} else {
			$linebot->set_text("友だち追加ありがとうございます！");
		}
		$linebot->send_reply();

		$system_name = trim((string)($setting["system_name"] ?? ""));
		if ($system_name === "") {
			$system_name = "システム";
		}

		$manager_list = $ctl->db("line_member")->select("member_type", 1);
		foreach ($manager_list as $manager) {
			$manager_userid = (string)($manager["userid"] ?? "");
			if ($manager_userid === "") {
				continue;
			}
			$linebot->clear_queue();
			$rep = $displayname . " さんが" . $system_name . "公式LINEに友達追加しました";
			$linebot->set_text($rep);
			$linebot->send_push($manager_userid);
		}
	}
}
