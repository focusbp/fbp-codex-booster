<?php

// Session-bound, per-page download grants. Never reuse a mutable form CSRF value.
final class pdf_delivery_access {
    const TTL = 900;
    const LIMIT = 64;

    static function issue(array &$session, string $subject, string $document, int $now): string {
        if ($subject === '' || $document === '') {
            throw new InvalidArgumentException('An authenticated subject and document are required');
        }
        $grants = $session['pdf_delivery_grants'] ?? [];
        foreach ($grants as $key => $grant) {
            if ($grant['expires'] <= $now) unset($grants[$key]);
        }
        $token = bin2hex(random_bytes(32));
        $grants[$token] = ['subject' => $subject, 'document' => $document, 'expires' => $now + self::TTL];
        $session['pdf_delivery_grants'] = array_slice($grants, -self::LIMIT, null, true);
        return $token;
    }

    static function resolve(array $session, ?string $subject, $token, int $now): ?string {
        if ($subject === null || $subject === '' || !is_string($token) || !preg_match('/\A[0-9a-f]{64}\z/D', $token)) return null;
        $grant = $session['pdf_delivery_grants'][$token] ?? null;
        if (!$grant || $grant['expires'] <= $now || !hash_equals($grant['subject'], $subject)) return null;
        return $grant['document'];
    }
}
