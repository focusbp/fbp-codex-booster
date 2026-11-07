<?php
/** A server-only scope for the session revocation guard; never set from request data. */
final class PasskeyAccess {
    private static int $depth=0;
    public static function active(): bool {return self::$depth>0;}
    public static function run(callable $callback) {
        self::$depth++;
        try {return $callback();} finally {self::$depth--;}
    }
}
