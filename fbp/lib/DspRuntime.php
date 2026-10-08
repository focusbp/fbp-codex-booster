<?php

require_once __DIR__ . '/DspException.php';
require_once __DIR__ . '/DspReadDecision.php';
require_once __DIR__ . '/../interface/DspInterface.php';

/** Registry discovery and exception containment; no project-specific authentication model. */
final class DspRuntime {
    private static array $registries = [];
    private static array $loading = [];
    private static array $evaluating = [];
    private static array $preparing = [];
    private static array $pure = [];

    public static function isPreparing(?Controller $ctl): bool {
        return $ctl !== null && isset(self::$preparing[spl_object_id($ctl)]);
    }
    public static function forbidsRead(?Controller $ctl): bool {
        return $ctl !== null && isset(self::$pure[spl_object_id($ctl)]) && !self::isPreparing($ctl);
    }
    public static function preparedInvoke(?Controller $ctl, callable $callback): mixed {
        $key = $ctl === null ? 0 : spl_object_id($ctl);
        self::$pure[$key] = (self::$pure[$key] ?? 0) + 1;
        try { return $callback(); }
        finally { if (--self::$pure[$key] === 0) unset(self::$pure[$key]); }
    }
    public static function prepare(?Controller $ctl, DspPreparedInterface $policy): void {
        if ($ctl === null) throw new DspException("context", "controller_required");
        $key = spl_object_id($ctl);
        self::invoke($ctl, "prepare", [], static function () use ($ctl, $key, $policy) {
            self::$preparing[$key] = true;
            try { $policy->prepareContext(static fn($table, $class = "common") => $ctl->db($table, $class)->dsp_context_snapshot()); }
            finally { unset(self::$preparing[$key]); }
        });
    }

    public static function isEvaluating(?Controller $ctl): bool {
        return $ctl !== null && isset(self::$evaluating[spl_object_id($ctl)]);
    }

    public static function channelForEntry(?string $class, string $function): string {
        if ($class === 'mcp_server') return 'mcp';
        if ($class === 'cron' && $function === 'exec') return 'cron';
        if ($class !== null && (str_ends_with($class, '_api') || $class === 'api')) return 'api';
        if ($class !== null && str_starts_with($class, 'public_')) return 'public';
        return 'admin';
    }

    public static function resolve(string $datadir, string $table, ?Controller $ctl, string $channel, ?string $databaseClass = null): ?DspInterface {
        $path = str_replace('\\', '/', realpath($datadir) ?: $datadir);
        $marker = '/classes/data/';
        $pos = strrpos($path . '/', $marker);
        if ($pos === false) return null;
        $root = substr($path, 0, $pos) . '/classes/dsp';
        $registry = $root . '/registry.php';
        if (!is_file($registry)) return null;
        try {
            if (!array_key_exists($registry, self::$registries)) {
                $entries = (static function ($file) { return require $file; })($registry);
                if (!is_array($entries)) throw new UnexpectedValueException('Invalid DSP registry');
                self::$registries[$registry] = $entries;
            }
            $relative = trim(substr($path, $pos + strlen($marker)), '/');
            // Exact physical partition, then class/table (common maps to _common).
            $logical = $databaseClass ?? ($relative === '_common' ? 'common' : $relative);
            $key = $relative . '/' . $table;
            $entries = self::$registries[$registry];
            $entry = $entries[$key] ?? $entries[$logical . '/' . $table] ?? null;
            if ($entry === null && !array_key_exists($key, $entries) && !array_key_exists($logical . '/' . $table, $entries)) return null;
            if ($ctl === null) throw new DspException('context', 'controller_required');
            $binding = $registry . ':' . $key;
            if (isset(self::$loading[$binding])) throw new DspException('dependencies', 'circular_policy_dependency');
            self::$loading[$binding] = true;
            if (!is_array($entry) || !isset($entry['file'], $entry['class'])) throw new UnexpectedValueException('Invalid DSP entry');
            $file = realpath($root . '/' . $entry['file']);
            if ($file === false || !str_starts_with($file, realpath($root) . '/')) throw new UnexpectedValueException('DSP file unavailable');
            require_once $file;
            $class = $entry['class'];
            if (!is_string($class) || !is_subclass_of($class, DspInterface::class)) throw new UnexpectedValueException('Invalid DSP class');
            // Dependencies must be opened before the target's mutation reads its before-image.
            foreach ($entry['dependencies'] ?? [] as $dependency) {
                $ctl->db($dependency['table'], $dependency['class'] ?? null, $dependency['separated_by'] ?? null);
            }
            try { return new $class($ctl, $channel); }
            finally { unset(self::$loading[$binding]); }
        } catch (Throwable $e) {
            if (isset($binding)) unset(self::$loading[$binding]);
            $failure = $e instanceof DspException ? $e : new DspException('load', 'policy_load_error', [], $e);
            self::record($failure, ['operation' => 'load', 'table' => $table, 'channel' => $channel]);
            throw $failure;
        }
    }

    public static function invoke(?Controller $ctl, string $operation, array $target, callable $callback): mixed {
        $previous = $ctl !== null ? $ctl->get_prohibit_new_db() : false;
        $key = $ctl !== null ? spl_object_id($ctl) : null;
        if ($ctl !== null) {
            $ctl->set_prohibit_new_db(true);
            self::$evaluating[$key] = (self::$evaluating[$key] ?? 0) + 1;
        }
        try {
            return $callback();
        } catch (Throwable $e) {
            $failure = $e instanceof DspException ? $e : new DspException('evaluation', 'policy_evaluation_error', [], $e);
            self::record($failure, ['operation' => $operation] + $target);
            throw $failure;
        } finally {
            if ($ctl !== null) {
                if (--self::$evaluating[$key] === 0) unset(self::$evaluating[$key]);
                $ctl->set_prohibit_new_db($previous);
            }
        }
    }

    private static function record(DspException $e, array $target): void {
        // Only identifiers and reason codes. Never serialize rows or authentication credentials.
        error_log('[DSP] ' . json_encode($target + $e->getDetails() + ['trace_id' => bin2hex(random_bytes(8))], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
