<?php

class McpActionRequest {
	private $tool;
	private $arguments;

	function __construct(array $tool, array $arguments) {
		$this->tool = $tool;
		$this->arguments = $arguments;
	}

	function tool(): array {
		return $this->tool;
	}

	function arguments(): array {
		return $this->arguments;
	}

	function get(string $key, $default = null) {
		return array_key_exists($key, $this->arguments) ? $this->arguments[$key] : $default;
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
