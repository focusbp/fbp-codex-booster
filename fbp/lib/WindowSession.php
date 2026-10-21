<?php

/** Bounds per-tab state without touching session-wide authentication/integration data. */
final class WindowSession {
    public const MAX_WINDOWS = 20;
    public const MAX_BYTES = 4 * 1024 * 1024;
    private const META = '_FBP_WINDOW_STATE';

    public static function valid($id): bool {
        return is_string($id) && preg_match('/^WID_[A-Za-z0-9._-]+$/D', $id) === 1;
    }

    public static function wasRemoved(array $session, string $id): bool {
        return isset($session[self::META]['removed'][$id]);
    }

    public static function touch(array &$session, string $id): void {
        if (!self::valid($id)) return;
        $session[self::META]['used'][$id] = microtime(true);
    }

    public static function restore(array &$session, string $id): void {
        unset($session[self::META]['removed'][$id]);
    }

    public static function prune(array &$session, array $protected, int $maxWindows = self::MAX_WINDOWS, int $maxBytes = self::MAX_BYTES): void {
        $used = $session[self::META]['used'] ?? [];
        $windows = [];
        $bytes = 0;
        foreach ($session as $id => $value) {
            if (!self::valid($id) || !is_array($value)) continue;
            // Serialize one window at a time; never duplicate the entire session.
            $size = strlen(serialize($value));
            $windows[$id] = ['used' => $used[$id] ?? 0, 'bytes' => $size];
            $bytes += $size;
        }
        uasort($windows, static function ($a, $b) { return $a['used'] <=> $b['used']; });
        $count = count($windows);
        foreach ($windows as $id => $window) {
            if ($count <= $maxWindows && $bytes <= $maxBytes) break;
            if (in_array($id, $protected, true)) continue;
            unset($session[$id], $session[self::META]['used'][$id]);
            $session[self::META]['removed'][$id] = microtime(true);
            $bytes -= $window['bytes'];
            $count--;
        }
        // Do not let the bookkeeping become another unbounded session cache.
        foreach (array_keys($session[self::META]['used'] ?? []) as $id) {
            if (!isset($session[$id])) unset($session[self::META]['used'][$id]);
        }
        $removed = $session[self::META]['removed'] ?? [];
        asort($removed);
        $session[self::META]['removed'] = array_slice($removed, -256, null, true);
    }
}
