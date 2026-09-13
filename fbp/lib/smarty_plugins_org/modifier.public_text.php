<?php

/**
 * Safely render public-facing plain text with line breaks.
 *
 * Legacy text pasted from HTML sources can contain <br> elements. Normalize
 * only those elements to newlines, then escape all remaining markup before
 * rendering the newlines as HTML breaks.
 */
function smarty_modifier_public_text($value): string {
	$text = (string) $value;
	$text = preg_replace('~<br\\s*/?\\s*>~i', "\n", $text) ?? $text;
	return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}
