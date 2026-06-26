<?php

class McpActionRequest {
	private $tool;
	private $arguments;
	private $subject;

	function __construct(array $tool, array $arguments, ?McpSubject $subject = null) {
		$this->tool = $tool;
		$this->arguments = $arguments;
		$this->subject = $subject;
	}

	function tool(): array {
		return $this->tool;
	}

	function arguments(): array {
		return $this->arguments;
	}

	function subject(): ?McpSubject {
		return $this->subject;
	}

	function subjectType(): string {
		return $this->subject instanceof McpSubject ? $this->subject->type() : "";
	}

	function subjectId(): int {
		return $this->subject instanceof McpSubject ? $this->subject->id() : 0;
	}

	function subjectLabel(): string {
		return $this->subject instanceof McpSubject ? $this->subject->label() : "";
	}

	function get(string $key, $default = null) {
		return array_key_exists($key, $this->arguments) ? $this->arguments[$key] : $default;
	}

	function has(string $key): bool {
		return array_key_exists($key, $this->arguments);
	}

	function filled(string $key): bool {
		if (!$this->has($key)) {
			return false;
		}
		$value = $this->arguments[$key];
		if (is_array($value) || is_object($value)) {
			return false;
		}
		return trim((string) $value) !== "";
	}

	function string(string $key, string $default = ""): string {
		$value = $this->get($key, $default);
		if (is_array($value) || is_object($value)) {
			return $default;
		}
		return trim((string) $value);
	}

	function int(string $key, int $default = 0): int {
		$value = $this->get($key, $default);
		if (is_array($value) || is_object($value)) {
			return $default;
		}
		return (int) $value;
	}

	function bool(string $key, bool $default = false): bool {
		$value = $this->get($key, $default);
		if (is_bool($value)) {
			return $value;
		}
		if (is_array($value) || is_object($value)) {
			return $default;
		}
		$value = strtolower(trim((string) $value));
		if (in_array($value, ["1", "true", "yes", "on"], true)) {
			return true;
		}
		if (in_array($value, ["0", "false", "no", "off"], true)) {
			return false;
		}
		return $default;
	}
}

class McpInputValidator {
	static function schema(string $type, string $description = "", array $options = []): array {
		if ($type === "time") {
			return self::timeSchema($description, $options);
		}
		if ($type === "date") {
			return self::dateSchema($description, $options);
		}
		if ($type === "year_month") {
			return self::yearMonthSchema($description, $options);
		}
		if ($type === "integer") {
			return self::integerSchema($description, $options);
		}
		if ($type === "decimal" || $type === "number") {
			return self::decimalSchema($description, $options);
		}
		if ($type === "enum") {
			return self::enumSchema($description, $options);
		}
		return self::stringSchema($description, $options);
	}

	static function stringSchema(string $description = "", array $options = []): array {
		$schema = ["type" => "string"];
		if ($description !== "") {
			$schema["description"] = $description;
		}
		if (isset($options["minLength"])) {
			$schema["minLength"] = (int) $options["minLength"];
		}
		if (isset($options["maxLength"])) {
			$schema["maxLength"] = (int) $options["maxLength"];
		}
		return $schema;
	}

	static function timeSchema(string $description = "", array $options = []): array {
		$schema = [
			"type" => "string",
			"pattern" => "^([01]?[0-9]|2[0-3]):[0-5][0-9]$",
			"description" => "Time only in HH:MM format. Do not include a date, a range, or memo text.",
		];
		if ($description !== "") {
			$schema["description"] = $description . " " . $schema["description"];
		}
		return $schema;
	}

	static function dateSchema(string $description = "", array $options = []): array {
		$schema = [
			"type" => "string",
			"pattern" => "^\\d{4}-\\d{2}-\\d{2}$",
			"description" => "Date only in YYYY-MM-DD format. Do not include a time or relative words.",
		];
		if ($description !== "") {
			$schema["description"] = $description . " " . $schema["description"];
		}
		return $schema;
	}

	static function yearMonthSchema(string $description = "", array $options = []): array {
		$schema = [
			"type" => "string",
			"pattern" => "^\\d{4}-\\d{2}$",
			"description" => "Year and month only in YYYY-MM format. Do not include a day.",
		];
		if ($description !== "") {
			$schema["description"] = $description . " " . $schema["description"];
		}
		return $schema;
	}

	static function integerSchema(string $description = "", array $options = []): array {
		$schema = ["type" => "integer"];
		if ($description !== "") {
			$schema["description"] = $description;
		}
		foreach (["minimum", "maximum"] as $key) {
			if (isset($options[$key])) {
				$schema[$key] = (int) $options[$key];
			}
		}
		return $schema;
	}

	static function decimalSchema(string $description = "", array $options = []): array {
		$schema = ["type" => "number"];
		if ($description !== "") {
			$schema["description"] = $description;
		}
		foreach (["minimum", "maximum"] as $key) {
			if (isset($options[$key])) {
				$schema[$key] = (float) $options[$key];
			}
		}
		return $schema;
	}

	static function enumSchema(string $description = "", array $options = []): array {
		$schema = [
			"type" => "string",
			"enum" => array_values($options["values"] ?? []),
		];
		if ($description !== "") {
			$schema["description"] = $description;
		}
		return $schema;
	}

	static function string(McpActionRequest $request, string $key, array $options = []): string {
		$value = self::raw($request, $key, $options);
		if (is_array($value) || is_object($value)) {
			self::fail($key, "must be a string.");
		}
		$value = trim((string) $value);
		if ($value === "" && !empty($options["required"])) {
			self::missing($key);
		}
		if (isset($options["maxLength"]) && strlen($value) > (int) $options["maxLength"]) {
			self::fail($key, "must be " . (int) $options["maxLength"] . " bytes or less.");
		}
		return $value;
	}

	static function time(McpActionRequest $request, string $key, array $options = []): string {
		$value = self::raw($request, $key, $options);
		if (is_array($value) || is_object($value)) {
			self::fail($key, "must be a time string in HH:MM format.");
		}
		$value = trim((string) $value);
		if ($value === "") {
			if (!empty($options["required"])) {
				self::missing($key);
			}
			return (string) ($options["default"] ?? "");
		}
		if (preg_match('/\\d{4}[-\\/]\\d{1,2}[-\\/]\\d{1,2}/', $value)) {
			self::fail($key, "must contain only a time in HH:MM format. Do not include a date.", "09:30");
		}
		if (preg_match('/(\\d{1,2})\\s*時\\s*(\\d{1,2})\\s*分?/u', $value, $m)) {
			$value = $m[1] . ":" . $m[2];
		}
		$value = str_replace("：", ":", $value);
		if (!preg_match('/^(\\d{1,2}):(\\d{2})$/', $value, $m)) {
			self::fail($key, "must be time only in HH:MM format. Use a separate field for end time and do not move time into memo.", "09:30");
		}
		$hour = (int) $m[1];
		$minute = (int) $m[2];
		if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
			self::fail($key, "must be a valid 24-hour time in HH:MM format.", "09:30");
		}
		return sprintf("%02d:%02d", $hour, $minute);
	}

	static function date(McpActionRequest $request, string $key, array $options = []): string {
		$value = self::raw($request, $key, $options);
		if (is_array($value) || is_object($value)) {
			self::fail($key, "must be a date string in YYYY-MM-DD format.");
		}
		$value = trim((string) $value);
		if ($value === "") {
			if (!empty($options["required"])) {
				self::missing($key);
			}
			return (string) ($options["default"] ?? "");
		}
		$value = str_replace("/", "-", $value);
		if (preg_match('/^\\d{4}-\\d{1,2}-\\d{1,2}\\s+\\d{1,2}:/', $value)) {
			self::fail($key, "must contain only a date in YYYY-MM-DD format. Do not include a time.", "2026-06-27");
		}
		if (!preg_match('/^(\\d{4})-(\\d{1,2})-(\\d{1,2})$/', $value, $m)) {
			self::fail($key, "must be a date in YYYY-MM-DD format. Do not use relative words such as today or tomorrow.", "2026-06-27");
		}
		$year = (int) $m[1];
		$month = (int) $m[2];
		$day = (int) $m[3];
		if (!checkdate($month, $day, $year)) {
			self::fail($key, "must be a valid calendar date.", "2026-06-27");
		}
		return sprintf("%04d-%02d-%02d", $year, $month, $day);
	}

	static function yearMonth(McpActionRequest $request, string $key, array $options = []): string {
		$value = self::raw($request, $key, $options);
		if (is_array($value) || is_object($value)) {
			self::fail($key, "must be a year-month string in YYYY-MM format.");
		}
		$value = trim((string) $value);
		if ($value === "") {
			if (!empty($options["required"])) {
				self::missing($key);
			}
			return (string) ($options["default"] ?? "");
		}
		$value = str_replace("/", "-", $value);
		if (preg_match('/^\\d{4}-\\d{1,2}-\\d{1,2}$/', $value)) {
			self::fail($key, "must contain only year and month in YYYY-MM format. Do not include a day.", "2026-06");
		}
		if (!preg_match('/^(\\d{4})-(\\d{1,2})$/', $value, $m)) {
			self::fail($key, "must be year and month in YYYY-MM format.", "2026-06");
		}
		$year = (int) $m[1];
		$month = (int) $m[2];
		if ($month < 1 || $month > 12) {
			self::fail($key, "must use a month from 01 to 12.", "2026-06");
		}
		return sprintf("%04d-%02d", $year, $month);
	}

	static function integer(McpActionRequest $request, string $key, array $options = []): int {
		$value = self::raw($request, $key, $options);
		if ($value === "" || $value === null) {
			if (!empty($options["required"])) {
				self::missing($key);
			}
			return (int) ($options["default"] ?? 0);
		}
		if (is_array($value) || is_object($value)) {
			self::fail($key, "must be an integer.");
		}
		$text = str_replace(",", "", trim((string) $value));
		if (!preg_match('/^-?\\d+$/', $text)) {
			self::fail($key, "must be an integer without units or memo text.", "120");
		}
		$int = (int) $text;
		self::assertRange($key, $int, $options);
		return $int;
	}

	static function decimal(McpActionRequest $request, string $key, array $options = []): float {
		$value = self::raw($request, $key, $options);
		if ($value === "" || $value === null) {
			if (!empty($options["required"])) {
				self::missing($key);
			}
			return (float) ($options["default"] ?? 0);
		}
		if (is_array($value) || is_object($value)) {
			self::fail($key, "must be a number.");
		}
		$text = str_replace(",", "", trim((string) $value));
		if (!preg_match('/^-?\\d+(\\.\\d+)?$/', $text)) {
			self::fail($key, "must be a number without units or memo text.", "12.5");
		}
		$number = (float) $text;
		self::assertRange($key, $number, $options);
		return $number;
	}

	static function enum(McpActionRequest $request, string $key, array $options = []): string {
		$values = array_values($options["values"] ?? []);
		$aliases = $options["aliases"] ?? [];
		$value = self::string($request, $key, $options);
		if ($value === "" && empty($options["required"])) {
			return (string) ($options["default"] ?? "");
		}
		if (isset($aliases[$value])) {
			$value = (string) $aliases[$value];
		}
		if (!in_array($value, $values, true)) {
			self::fail($key, "must be one of: " . implode(", ", $values) . ".");
		}
		return $value;
	}

	private static function raw(McpActionRequest $request, string $key, array $options = []) {
		if (!$request->has($key)) {
			if (!empty($options["required"])) {
				self::missing($key);
			}
			return $options["default"] ?? "";
		}
		return $request->get($key);
	}

	private static function missing(string $key): void {
		throw new Exception("ToolError: Missing required argument `" . $key . "`.");
	}

	private static function fail(string $key, string $message, string $example = ""): void {
		if ($example !== "") {
			$message .= " Example: " . $example . ".";
		}
		throw new Exception("ToolError: Invalid argument `" . $key . "`. " . $message);
	}

	private static function assertRange(string $key, $value, array $options): void {
		if (isset($options["minimum"]) && $value < $options["minimum"]) {
			self::fail($key, "must be greater than or equal to " . $options["minimum"] . ".");
		}
		if (isset($options["maximum"]) && $value > $options["maximum"]) {
			self::fail($key, "must be less than or equal to " . $options["maximum"] . ".");
		}
	}
}

class McpActionResult {
	private $message;
	private $data;
	private $files;
	private $meta;

	function __construct(string $message = "Done.", array $data = [], array $files = [], array $meta = []) {
		$this->message = $message === "" ? "Done." : $message;
		$this->data = $data;
		$this->files = $files;
		$this->meta = $meta;
	}

	static function success(string $message = "Done.", array $data = [], array $meta = []): McpActionResult {
		return new McpActionResult($message, $data, [], $meta);
	}

	static function file(string $message, array $file, array $data = [], array $meta = []): McpActionResult {
		$result = new McpActionResult($message, $data, [], $meta);
		$result->addFile($file);
		return $result;
	}

	function addFile(array $file): void {
		$normalized = [
			"filename" => (string) ($file["filename"] ?? ""),
			"mime_type" => (string) ($file["mime_type"] ?? ""),
			"download_url" => (string) ($file["download_url"] ?? ""),
		];
		foreach (["expires_at", "size", "summary"] as $key) {
			if (array_key_exists($key, $file)) {
				$normalized[$key] = $file[$key];
			}
		}
		$this->files[] = $normalized;
	}

	function message(): string {
		return $this->message;
	}

	function toStructuredContent(): array {
		$out = [
			"ok" => true,
			"message" => $this->message,
			"data" => $this->data,
		];
		if (count($this->files) > 0) {
			$out["files"] = $this->files;
		}
		if (count($this->meta) > 0) {
			$out["_meta"] = $this->meta;
		}
		return $out;
	}
}

interface McpActionInterface {
	public function getInputSchema(Controller $ctl, array $tool): array;
	public function execute(Controller $ctl, McpActionRequest $request): McpActionResult;
}
