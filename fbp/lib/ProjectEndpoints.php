<?php
/** Project-owned connection URLs. Empty values retain legacy environment settings. */
class ProjectEndpoints {
    const FIELDS = ['note_sync_url', 'server_error_report_url'];
    static function validate(array $values): array {
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $values)) continue;
            if (!is_string($values[$field])) throw new InvalidArgumentException($field . ': HTTPS URL is required.');
            $url = trim($values[$field]);
            if ($url !== '') {
                $p = parse_url($url);
                if (strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL) || !$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || preg_match('/[\x00-\x20\x7f]/', $url) || ($field === 'note_sync_url' && isset($p['query']))) {
                    throw new InvalidArgumentException($field . ': HTTPS URL is required.');
                }
            }
            $values[$field] = $url;
        }
        return $values;
    }
}
