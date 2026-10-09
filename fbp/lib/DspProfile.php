<?php

/** Opt-in aggregate timings for administrator standard-screen diagnostics. */
final class DspProfile {
    private static int $started = 0;
    private static array $metrics = [];
    private static float $bootstrapMs = 0.0;
    public static function begin(): void {
        self::$started = hrtime(true); self::$metrics = [];
        self::$bootstrapMs = max(0, (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000);
    }
    public static function start(): int { return self::$started ? hrtime(true) : 0; }
    public static function record(string $stage, int $start, ?int $rows = null): void {
        if (!$start || !self::$started) return;
        $metric = self::$metrics[$stage] ?? ['count' => 0, 'ms' => 0.0, 'rows' => 0];
        $metric['count']++;
        $metric['ms'] += (hrtime(true) - $start) / 1e6;
        $metric['rows'] += $rows ?? 0;
        self::$metrics[$stage] = $metric;
    }
    public static function report(): ?array {
        if (!self::$started) return null;
        $metrics = self::$metrics;
        foreach ($metrics as &$metric) $metric['ms'] = round($metric['ms'], 3);
        return ['total_ms' => round((hrtime(true) - self::$started) / 1e6, 3), 'bootstrap_ms' => round(self::$bootstrapMs, 3), 'metrics' => $metrics];
    }
}
