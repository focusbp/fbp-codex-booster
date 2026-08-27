<?php

class McpFunctionRequest extends McpActionRequest {
}

class McpFunctionResult extends McpActionResult {
	static function success(string $message = "Done.", array $data = [], array $meta = []): McpFunctionResult {
		return new McpFunctionResult($message, $data, [], $meta);
	}

	static function file(string $message, array $file, array $data = [], array $meta = []): McpFunctionResult {
		$result = new McpFunctionResult($message, $data, [], $meta);
		$result->addFile($file);
		return $result;
	}
}

interface McpFunctionInterface {
	public function getInputSchema(Controller $ctl, array $function): array;
	public function getOutputSchema(Controller $ctl, array $function): array;
	public function execute(Controller $ctl, McpFunctionRequest $request): McpActionResult;
}

class McpFunctionLoader {
	static function validateName(string $function_name): bool {
		return preg_match('/^[a-z][a-z0-9_]*$/', trim($function_name)) === 1;
	}

	static function className(string $function_name): string {
		$function_name = trim($function_name);
		if (!self::validateName($function_name)) {
			throw new Exception("MCP function name is invalid.");
		}
		return "mcp_" . $function_name;
	}

	static function load(string $function_name, ?Controller $ctl = null): McpFunctionInterface {
		$class = self::className($function_name);
		if (!class_exists($class, false)) {
			$dir = new Dirs();
			$class_file = $dir->get_class_dir($class) . "/" . $class . ".php";
			include_once($class_file);
		}
		if (!class_exists($class, false)) {
			throw new Exception("MCP function class not found: " . $class);
		}
		$reflection = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();
		if ($constructor && count($constructor->getParameters()) > 0 && $ctl !== null) {
			$function = new $class($ctl);
		} else {
			$function = new $class();
		}
		if (!($function instanceof McpFunctionInterface)) {
			throw new Exception("MCP function class must implement McpFunctionInterface: " . $class);
		}
		return $function;
	}
}
