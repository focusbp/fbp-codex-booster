<?php

/** Decode bounded RFC822 messages into UTF-8 text and in-memory attachments. */
final class ReceiveMailMime {
    public static function parse(string $raw): array {
        $message = ['subject' => '', 'from' => [], 'to' => [], 'cc' => [], 'date' => '', 'message_id' => '',
            'text' => '', 'html' => '', 'attachments' => [], 'raw' => $raw];
        [$headers] = self::split($raw);
        $message['subject'] = self::header($headers['subject'] ?? '');
        $message['date'] = $headers['date'] ?? '';
        $message['message_id'] = $headers['message-id'] ?? '';
        foreach (['from', 'to', 'cc'] as $key) {
            if (!empty($headers[$key])) {
                foreach (imap_rfc822_parse_adrlist($headers[$key], '') ?: [] as $address) {
                    if (empty($address->host) || $address->host === '.SYNTAX-ERROR.') continue;
                    $message[$key][] = ['email' => ($address->mailbox ?? '') . '@' . $address->host,
                        'name' => self::header($address->personal ?? '')];
                }
            }
        }
        $count = 0;
        self::part($raw, $message, 0, $count);
        return $message;
    }
    private static function split(string $raw): array {
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        $headers = [];
        $head = preg_replace('/\r?\n[ \t]+/', ' ', $parts[0]);
        foreach (preg_split('/\r?\n/', $head) as $line) {
            if (strpos($line, ':') === false) continue;
            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            if (!isset($headers[$name])) $headers[$name] = trim($value);
        }
        return [$headers, $parts[1] ?? ''];
    }
    private static function header(string $value): string {
        // Preserve raw UTF-8 (including decoded RFC2231 filenames); decode only encoded words.
        $value = preg_replace('/(\?=)[ \t]+(?==\?)/', '$1', $value);
        return preg_replace_callback('/=\?[^?\s]+\?[bqBQ]\?[^?]*\?=/', static function ($match) {
            $decoded = @iconv_mime_decode($match[0], ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            return $decoded === false ? $match[0] : $decoded;
        }, $value);
    }
    private static function parameter(string $value, string $name): string {
        // RFC2231 continuation parameters (common for long Japanese attachment names).
        preg_match_all('/(?:^|;)\s*' . preg_quote($name, '/') . '\*([0-9]+)(\*)?\s*=\s*(?:"([^"]*)"|([^;]*))/i', $value, $segments, PREG_SET_ORDER);
        if ($segments) {
            $pieces = [];
            foreach ($segments as $segment) $pieces[(int) $segment[1]] = [$segment[2] === '*', $segment[3] !== '' ? $segment[3] : trim($segment[4] ?? '')];
            ksort($pieces);
            if (array_keys($pieces) !== range(0, count($pieces) - 1)) throw new RuntimeException('Invalid incoming MIME parameter continuation.');
            $result = ''; $charset = 'UTF-8';
            foreach ($pieces as $index => [$encoded, $piece]) {
                if ($index === 0 && $encoded && preg_match("/^([^']*)'[^']*'(.*)$/s", $piece, $first)) {
                    $charset = $first[1] ?: 'UTF-8'; $piece = $first[2];
                }
                $result .= $encoded ? rawurldecode($piece) : $piece;
            }
            return self::header(self::utf8($result, $charset));
        }
        if (preg_match('/(?:^|;)\s*' . preg_quote($name, '/') . '\*\s*=\s*([^;]+)/i', $value, $m)) {
            $encoded = trim($m[1], " \t\"");
            if (preg_match("/^([^']*)'[^']*'(.*)$/s", $encoded, $m)) {
                return self::utf8(rawurldecode($m[2]), $m[1] ?: 'UTF-8');
            }
        }
        if (preg_match('/(?:^|;)\s*' . preg_quote($name, '/') . '\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^;]*))/i', $value, $m)) {
            return self::header(preg_replace('/\\\\(.)/s', '$1', $m[1] !== '' ? $m[1] : trim($m[2] ?? '')));
        }
        return '';
    }
    private static function utf8(string $value, string $charset): string {
        try { return mb_convert_encoding($value, 'UTF-8', $charset); }
        catch (ValueError $e) { throw new RuntimeException('Unsupported incoming mail charset.'); }
    }
    private static function part(string $raw, array &$message, int $depth, int &$count): void {
        if (++$count > 1000) throw new RuntimeException('Incoming MIME part count exceeds limit.');
        if ($depth > 20) throw new RuntimeException('Incoming MIME nesting exceeds limit.');
        [$headers, $body] = self::split($raw);
        $contentType = $headers['content-type'] ?? 'text/plain; charset=US-ASCII';
        $mime = strtolower(trim(explode(';', $contentType, 2)[0]));
        $disposition = $headers['content-disposition'] ?? '';
        $filename = self::parameter($disposition, 'filename') ?: self::parameter($contentType, 'name');
        $attachment = $filename !== '' || preg_match('/^attachment\b/i', $disposition);
        if (str_starts_with($mime, 'multipart/') && !$attachment) {
            $boundary = self::parameter($contentType, 'boundary');
            if ($boundary === '') throw new RuntimeException('Missing incoming MIME boundary.');
            preg_match_all('/(?:^|\r?\n)--' . preg_quote($boundary, '/') . '(--)?[ \t]*(?:\r?\n|$)/', $body, $matches, PREG_OFFSET_CAPTURE);
            $start = null; $closed = false;
            foreach ($matches[0] as $index => [$delimiter, $offset]) {
                if ($start !== null) self::part(substr($body, $start, $offset - $start), $message, $depth + 1, $count);
                if (($matches[1][$index][0] ?? '') === '--') { $closed = true; break; }
                $start = $offset + strlen($delimiter);
            }
            if (!$closed) throw new RuntimeException('Incomplete incoming multipart message.');
            return;
        }
        $encoding = strtolower(trim($headers['content-transfer-encoding'] ?? '7bit'));
        if ($encoding === 'base64') {
            $body = base64_decode(preg_replace('/\s+/', '', $body), true);
            if ($body === false) throw new RuntimeException('Invalid incoming base64 content.');
        } elseif ($encoding === 'quoted-printable') $body = quoted_printable_decode($body);
        elseif (!in_array($encoding, ['7bit', '8bit', 'binary'], true)) throw new RuntimeException('Unsupported incoming transfer encoding.');
        if (!$attachment && in_array($mime, ['text/plain', 'text/html'], true)) {
            $key = $mime === 'text/html' ? 'html' : 'text';
            $text = self::utf8($body, self::parameter($contentType, 'charset') ?: 'US-ASCII');
            $message[$key] .= ($message[$key] === '' ? '' : "\n") . $text;
        } else {
            $filename = basename(str_replace('\\', '/', str_replace("\0", '', $filename))) ?: 'attachment';
            $message['attachments'][] = ['name' => $filename, 'mime_type' => $mime, 'content_id' => trim($headers['content-id'] ?? '', '<>'),
                'inline' => preg_match('/^inline\b/i', $disposition) === 1, 'size' => strlen($body), 'content' => $body];
        }
    }
}
