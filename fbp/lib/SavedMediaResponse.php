<?php

/** Binary inline responses only; callers must authorize the record first. */
final class SavedMediaResponse {
	public static function send(string $root, string $name, array $options = []): void {
		$head = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
		foreach (['ETag', 'Last-Modified', 'Expires', 'Pragma', 'Content-Encoding', 'Content-Range', 'Content-Length'] as $header) header_remove($header);
		header('Cache-Control: private, no-store, max-age=0');
		header('X-Content-Type-Options: nosniff');
		if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
			header('Allow: GET, HEAD');
			self::error(405, $head);
		}
		$base = realpath($root);
		if ($name === '' || strpos($name, "\0") !== false || strpos($name, '\\') !== false
			|| $name[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $name)) self::error(404, $head);
		$path = realpath($root . '/' . $name);
		if ($base === false || $path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0
			|| !is_file($path) || !is_readable($path)) self::error(404, $head);
		// Content detection, not the request filename; active formats such as SVG are excluded.
		$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
		$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp', 'image/x-icon',
			'video/mp4', 'video/webm', 'video/quicktime', 'video/ogg', 'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/x-wav'];
		if (!in_array($mime, $allowed, true)) self::error(415, $head);
		$fp = fopen($path, 'rb');
		if ($fp === false) self::error(404, $head);
		$size = fstat($fp)['size'];
		$start = 0;
		$end = $size - 1;
		$status = 200;
		$range = $_SERVER['HTTP_RANGE'] ?? '';
		// We emit no validators: an If-Range cannot match, so send the whole representation.
		if (!$head && $range !== '' && !isset($_SERVER['HTTP_IF_RANGE'])
			&& preg_match('/^bytes=(\d*)-(\d*)$/D', trim($range), $m) && ($m[1] !== '' || $m[2] !== '')) {
			if ($m[1] === '') {
				$length = self::boundedInteger($m[2]);
				$start = max(0, $size - $length);
			} else {
				$start = self::boundedInteger($m[1]);
				if ($m[2] !== '') $end = min($end, self::boundedInteger($m[2]));
			}
			if ($size === 0 || $start >= $size || $end < $start) {
				fclose($fp);
				header('Content-Range: bytes */' . $size);
				self::error(416, $head);
			}
			$status = 206;
		}
		// Unsupported/malformed/multiple ranges are ignored (full 200).
		if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
		@ini_set('zlib.output_compression', '0');
		while (ob_get_level() > 0) { if (!@ob_end_clean()) break; }
		if (($options['cache'] ?? false) === true) {
			$maxAge = max(0, min(31536000, (int) ($options['max_age'] ?? 3600)));
			header('Cache-Control: public, max-age=' . $maxAge);
		}
		http_response_code($status);
		header('Content-Type: ' . $mime);
		header('Content-Disposition: inline');
		header('Accept-Ranges: bytes');
		$remaining = max(0, $end - $start + 1);
		header('Content-Length: ' . $remaining);
		if ($status === 206) header("Content-Range: bytes $start-$end/$size");
		if (!$head) {
			fseek($fp, $start);
			while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
				$chunk = fread($fp, min(65536, $remaining));
				if ($chunk === false || $chunk === '') break;
				echo $chunk;
				$remaining -= strlen($chunk);
			}
		}
		fclose($fp);
		exit;
	}

	private static function boundedInteger(string $digits): int {
		$digits = ltrim($digits, '0');
		$max = (string) PHP_INT_MAX;
		if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) return PHP_INT_MAX;
		return (int) $digits;
	}

	private static function error(int $status, bool $head): void {
		http_response_code($status);
		header('Content-Type: text/plain; charset=UTF-8');
		header('Content-Length: 0');
		exit;
	}
}
