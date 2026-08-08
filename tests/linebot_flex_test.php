<?php

require_once __DIR__ . "/../fbp/interface/linebot/linebot.php";
require_once __DIR__ . "/../fbp/lib/linebot/Linebot_class.php";
require_once __DIR__ . "/../fbp/app/webhook_line/webhook_line.php";

class Controller {
	public array $sessions = [];

	function set_session(string $key, $value): void {
		$this->sessions[$key] = $value;
	}

	function log(string $message): void {
	}
}

class LinebotFlexTestDouble extends Linebot_class {
	public int $replyCount = 0;

	public function send_reply(): bool {
		$this->replyCount++;
		return true;
	}
}

class LinebotFlexActionDouble {
	private array $contents;

	function __construct(array $contents) {
		$this->contents = $contents;
	}

	function run(Controller $ctl): array {
		return [
			"reply_flex" => [
				"alt_text" => "カード通知",
				"contents" => $this->contents,
			],
			"handled" => true,
		];
	}
}

class WebhookLineFlexTestDouble extends webhook_line {
	private object $action;

	function __construct(object $action) {
		$this->action = $action;
	}

	protected function create_action_instance(Controller $ctl, string $class) {
		return $this->action;
	}

	function execute_flex_result(Controller $ctl, linebot $linebot): ?bool {
		return $this->execute_rule_action(
			$ctl,
			$linebot,
			["action_class" => "linebot_flex_action_double"],
			["type" => "message"],
			["id" => 1],
			"注文",
			"test-user",
			"Test User"
		);
	}
}

function linebot_flex_assert(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$linebot = new Linebot_class("test-secret", "test-token");
$contents = [
	"type" => "bubble",
	"body" => [
		"type" => "box",
		"layout" => "vertical",
		"contents" => [
			["type" => "text", "text" => "テスト"],
		],
	],
];

linebot_flex_assert($linebot->set_flex("テスト通知", $contents), "valid Flex Message was rejected");

$property = (new ReflectionClass($linebot))->getProperty("replyQueue");
$property->setAccessible(true);
$queue = $property->getValue($linebot);

linebot_flex_assert(count($queue) === 1, "Flex Message was not queued");
linebot_flex_assert(($queue[0]["type"] ?? "") === "flex", "message type is not flex");
linebot_flex_assert(($queue[0]["altText"] ?? "") === "テスト通知", "altText changed");
linebot_flex_assert(($queue[0]["contents"] ?? []) === $contents, "contents changed");
linebot_flex_assert(!$linebot->set_flex("", $contents), "empty altText was accepted");
linebot_flex_assert(!$linebot->set_flex("不正", ["type" => "box"]), "invalid container was accepted");

$replyLinebot = new LinebotFlexTestDouble("test-secret", "test-token");
$webhook = new WebhookLineFlexTestDouble(new LinebotFlexActionDouble($contents));
$handled = $webhook->execute_flex_result(new Controller(), $replyLinebot);
$replyQueue = $property->getValue($replyLinebot);

linebot_flex_assert($handled === true, "Flex action was not handled");
linebot_flex_assert($replyLinebot->replyCount === 1, "Flex action did not send one reply");
linebot_flex_assert(($replyQueue[0]["type"] ?? "") === "flex", "webhook did not queue Flex Message");
linebot_flex_assert(($replyQueue[0]["altText"] ?? "") === "カード通知", "webhook altText changed");

printf("linebot Flex Message test passed\n");
