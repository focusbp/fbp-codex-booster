<?php

const FBP_FRAMEWORK_PRIMARY_COLOR_DEFAULT = "#4BA3FF";
const FBP_FRAMEWORK_PRIMARY_HOVER_COLOR_DEFAULT = "#0f4c81";
const FBP_FRAMEWORK_MENU_LINK_COLOR_DEFAULT = "#007dff";
const FBP_FRAMEWORK_MENU_TEXT_COLOR_DEFAULT = "#4BA3FF";

function fbp_normalize_hex_color($value): string {
	$value = trim((string) $value);
	if ($value === "") {
		return "";
	}
	if (preg_match('/^#?([0-9a-fA-F]{6})$/', $value, $matches)) {
		return "#" . strtoupper($matches[1]);
	}
	return "";
}

function fbp_framework_primary_color(array $setting): string {
	$color = fbp_normalize_hex_color($setting["framework_primary_color"] ?? "");
	return $color !== "" ? $color : FBP_FRAMEWORK_PRIMARY_COLOR_DEFAULT;
}

function fbp_normalize_framework_theme_setting(array $setting): array {
	$setting["framework_primary_color"] = fbp_normalize_hex_color($setting["framework_primary_color"] ?? "");
	$setting["framework_menu_text_color"] = fbp_normalize_hex_color($setting["framework_menu_text_color"] ?? "");
	return $setting;
}

function fbp_hex_to_rgb(string $hex): array {
	$hex = ltrim(fbp_normalize_hex_color($hex), "#");
	if ($hex === "") {
		$hex = ltrim(FBP_FRAMEWORK_PRIMARY_COLOR_DEFAULT, "#");
	}
	return [
		hexdec(substr($hex, 0, 2)),
		hexdec(substr($hex, 2, 2)),
		hexdec(substr($hex, 4, 2)),
	];
}

function fbp_framework_theme_text_color(string $hex): string {
	[$r, $g, $b] = fbp_hex_to_rgb($hex);
	$luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
	return $luminance > 165 ? "#111827" : "#FFFFFF";
}

function fbp_mix_hex_color(string $hex, string $target, float $ratio): string {
	[$r1, $g1, $b1] = fbp_hex_to_rgb($hex);
	[$r2, $g2, $b2] = fbp_hex_to_rgb($target);
	$ratio = max(0, min(1, $ratio));
	$r = (int) round($r1 + (($r2 - $r1) * $ratio));
	$g = (int) round($g1 + (($g2 - $g1) * $ratio));
	$b = (int) round($b1 + (($b2 - $b1) * $ratio));
	return sprintf("#%02X%02X%02X", $r, $g, $b);
}

function fbp_framework_theme_from_setting(array $setting): array {
	$custom_color = fbp_normalize_hex_color($setting["framework_primary_color"] ?? "");
	$custom_menu_text_color = fbp_normalize_hex_color($setting["framework_menu_text_color"] ?? "");
	$primary_color = $custom_color !== "" ? $custom_color : FBP_FRAMEWORK_PRIMARY_COLOR_DEFAULT;
	$menu_text_color = $custom_menu_text_color !== "" ? $custom_menu_text_color : FBP_FRAMEWORK_MENU_TEXT_COLOR_DEFAULT;
	$is_custom = $custom_color !== "";
	[$r, $g, $b] = fbp_hex_to_rgb($primary_color);
	return [
		"primary_color" => $primary_color,
		"primary_text_color" => $is_custom ? fbp_framework_theme_text_color($primary_color) : "#FFFFFF",
		"primary_hover_color" => $is_custom ? fbp_mix_hex_color($primary_color, "#000000", 0.35) : FBP_FRAMEWORK_PRIMARY_HOVER_COLOR_DEFAULT,
		"primary_soft_color" => "rgba($r, $g, $b, 0.18)",
		"menu_link_color" => $is_custom ? $primary_color : FBP_FRAMEWORK_MENU_LINK_COLOR_DEFAULT,
		"menu_text_color" => $menu_text_color,
	];
}
