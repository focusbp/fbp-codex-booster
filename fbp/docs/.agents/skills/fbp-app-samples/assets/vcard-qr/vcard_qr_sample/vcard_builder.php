<?php
final class sample_vcard_builder {
    // vCard 3.0: FN, N and VERSION are supplied; optional fields are omitted when empty.
    static function build(array $contact): string {
        $name = trim((string) ($contact['name'] ?? ''));
        if ($name === '') throw new InvalidArgumentException('Contact name is required');
        $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:' . self::text($name),
            'N:' . self::text((string) ($contact['family_name'] ?? $name)) . ';' . self::text((string) ($contact['given_name'] ?? '')) . ';;;'];
        foreach (['phone' => 'TEL;TYPE=WORK,VOICE', 'email' => 'EMAIL;TYPE=INTERNET', 'organization' => 'ORG', 'note' => 'NOTE'] as $key => $property) {
            $value = trim((string) ($contact[$key] ?? ''));
            if ($value !== '') $lines[] = $property . ':' . self::text($value);
        }
        $lines[] = 'END:VCARD';
        $output = '';
        foreach ($lines as $line) {
            if (!mb_check_encoding($line, 'UTF-8')) throw new InvalidArgumentException('Use UTF-8');
            // Fold at 75 octets without splitting UTF-8. Continuation space counts as one octet.
            while (strlen($line) > 75) {
                $part = mb_strcut($line, 0, 75, 'UTF-8');
                $output .= $part . "\r\n";
                $line = ' ' . substr($line, strlen($part));
            }
            $output .= $line . "\r\n";
        }
        // A business-card QR should remain small enough to print and scan comfortably.
        if (strlen($output) > 1500) throw new LengthException('Shorten contact data before encoding a QR');
        return $output;
    }

    private static function text(string $value): string {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) throw new InvalidArgumentException('Invalid control character');
        return str_replace(["\\", "\r\n", "\r", "\n", ';', ','], ["\\\\", '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
    }
}
