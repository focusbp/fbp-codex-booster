
//------------------------------------
// FOCUS Business Platform
// Ver 4
//------------------------------------

// 自動翻訳を停止
$(document).ready(function () {
	// <html>タグにtranslate="no"属性を追加する
	$('html').attr('translate', 'no');
});

function escapeHtml(str) {
	return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
}

function escapeAttributeSelectorValue(str) {
	return String(str).replace(/\\/g, "\\\\").replace(/"/g, '\\"');
}

function normalizeErrorScope(scope, dialog_id, classname) {
	if (scope !== undefined && scope !== null && scope !== "") {
		return String(scope);
	}
	if (dialog_id !== undefined && dialog_id !== null && dialog_id !== "") {
		return String(dialog_id);
	}
	if (classname !== undefined && classname !== null && classname !== "") {
		return String(classname);
	}
	return "";
}

function clearScopedErrorMessages(scope) {
	if (scope === "") {
		return false;
	}
	var escapedScope = escapeAttributeSelectorValue(scope);
	var scopedElements = $('[data-error-scope="' + escapedScope + '"] .error_message');
	if (scopedElements.length > 0) {
		scopedElements.html("");
		return true;
	}
	if (scope.charAt(0) === "#" && $(scope + " .error_message").length > 0) {
		$(scope + " .error_message").html("");
		return true;
	}
	return false;
}

function findErrorMessageTarget(scope, dialog_id, field) {
	var escapedField = escapeAttributeSelectorValue(field);
	var target = $();

	if (scope !== "") {
		var escapedScope = escapeAttributeSelectorValue(scope);
		target = $('[data-error-scope="' + escapedScope + '"] [data-error-for="' + escapedField + '"]');
		if (target.length === 0 && scope.charAt(0) === "#") {
			target = $(scope + ' [data-error-for="' + escapedField + '"]');
		}
		if (target.length === 0) {
			target = $('[data-error-scope="' + escapedScope + '"] .error_' + field);
		}
	}

	if (target.length === 0 && dialog_id !== "") {
		target = $(dialog_id + " .error_" + field);
	}
	if (target.length === 0) {
		target = $(".error_" + field);
	}
	return target;
}

function normalizeErrorMessageArray(errormessage) {
	if (Array.isArray(errormessage)) {
		return errormessage;
	}
	if (errormessage && typeof errormessage === "object") {
		return Object.values(errormessage);
	}
	return [];
}

function ensureClassStylesheet(classname) {
	if (classname === undefined || classname === null || classname === "") {
		return;
	}

	var id = "dynamic-appcss-" + classname;
	if (document.getElementById(id)) {
		return;
	}

	var appcssLinks = document.querySelectorAll('link[rel="stylesheet"][href*="appcss.php"]');
	for (var i = 0; i < appcssLinks.length; i++) {
		var href = appcssLinks[i].getAttribute("href") || "";
		if (href.indexOf("class=" + encodeURIComponent(classname)) !== -1 || href.indexOf("css_class=" + encodeURIComponent(classname)) !== -1) {
			return;
		}
	}

	var href = "appcss.php?class=" + encodeURIComponent(classname) + "&css_class=" + encodeURIComponent(classname) + "&ts=" + Date.now();
	var link = document.createElement("link");
	link.id = id;
	link.rel = "stylesheet";
	link.href = href;
	document.head.appendChild(link);
}

function get_client_localized_text(key) {
	var lang = get_server_language_code();
	var texts = {
		year_month_set: {ja: "設定", en: "Set", zh: "设置"},
		year_month_clear: {ja: "クリア", en: "Clear", zh: "清除"},
		year_month_error: {ja: "入力エラー", en: "Error", zh: "输入错误"},
		date_picker_clear: {ja: "クリア", en: "Clear", zh: "清除"},
		color_picker_set: {ja: "設定", en: "Set", zh: "设置"},
		color_picker_clear: {ja: "クリア", en: "Clear", zh: "清除"},
		select_unselected: {ja: "未選択", en: "Unselected", zh: "未选择"},
		select_no_results: {ja: "該当なし", en: "No results", zh: "无匹配项"},
		select_search_placeholder: {ja: "検索", en: "Search", zh: "搜索"},
		year_month_year: {ja: "年", en: "Year", zh: "年"},
		year_month_month: {ja: "月", en: "Month", zh: "月"},
		original_time_hour: {ja: "時", en: "Hour", zh: "小时"},
		original_time_minute: {ja: "分", en: "Minute", zh: "分钟"},
		original_time_meridiem: {ja: "AM/PM", en: "AM/PM", zh: "上午/下午"},
		original_time_clear: {ja: "クリア", en: "Clear", zh: "清除"},
		original_time_set: {ja: "設定", en: "Set", zh: "设置"}
	};
	if (texts[key] === undefined) {
		return "";
	}
	return texts[key][lang] || texts[key].en;
}

// embed_app iframe auto-height support (child side).
(function setupEmbedAppAutoHeight() {
	if (window.parent === window) {
		return;
	}

	var scheduled = false;
	function calcHeight() {
		var body = document.body;
		var doc = document.documentElement;
		var h1 = body ? body.scrollHeight : 0;
		var h2 = body ? body.offsetHeight : 0;
		var h3 = doc ? doc.scrollHeight : 0;
		var h4 = doc ? doc.offsetHeight : 0;
		return Math.max(h1, h2, h3, h4, 180);
	}

	function postHeightNow() {
		window.parent.postMessage({type: "embed_app:height", height: calcHeight()}, "*");
	}

	function schedulePost() {
		if (scheduled) {
			return;
		}
		scheduled = true;
		setTimeout(function () {
			scheduled = false;
			postHeightNow();
		}, 50);
	}

	window.addEventListener("message", function (event) {
		var data = event.data || {};
		if (data.type === "embed_app:request_height") {
			schedulePost();
		}
	});

	window.addEventListener("load", schedulePost);
	window.addEventListener("resize", schedulePost);
	document.addEventListener("DOMContentLoaded", schedulePost);

	if (typeof ResizeObserver !== "undefined") {
		var ro = new ResizeObserver(schedulePost);
		if (document.body) {
			ro.observe(document.body);
		}
		if (document.documentElement) {
			ro.observe(document.documentElement);
		}
	}

	if (typeof MutationObserver !== "undefined") {
		var target = document.body || document.documentElement;
		if (target) {
			var mo = new MutationObserver(schedulePost);
			mo.observe(target, {childList: true, subtree: true});
		}
	}

	setTimeout(schedulePost, 0);
	setTimeout(schedulePost, 300);
	setTimeout(schedulePost, 1200);
})();

// クッキーのパス
function cookieOpt() {
	const path = location.pathname;

	// ディレクトリ部分を取得（末尾のファイル/セグメントを落とす）
	let dir = path.replace(/\/[^/]*$/, ''); // 例: /miclub/fbp
	dir = dir.replace(/\/$/, '');           // 末尾 / を一旦除去 → /miclub/fbp or /miclub or ''

	// 最後が /fbp なら取り除いて一つ上へ
	dir = dir.replace(/\/fbp$/, '');

	// Cookie 用のパス（空や / はルートに正規化）
	const cookiePath = (dir === '' || dir === '/') ? '/' : (dir + '/');

	// 要求どおりのオプションオブジェクトを返す
	return {
		path: cookiePath,
		sameSite: 'Lax',
		secure: location.protocol === 'https:'
	};
}

//----------------------------
// 年・月 Picker
//----------------------------
var year_month_picker = false;
function reposition_fixed_panel_below_input(panel, inputElement, panelHeightOverride = null) {
	var rect = inputElement.getBoundingClientRect();
	var margin = 8;
	var viewportTop = 0;
	var viewportLeft = 0;
	var viewportBottom = $(window).innerHeight();
	var viewportRight = $(window).innerWidth();
	var panelHeight = panelHeightOverride !== null ? panelHeightOverride : (panel.outerHeight() || 0);
	var panelWidth = panel.outerWidth() || 0;
	var spaceBelow = viewportBottom - rect.bottom;
	var spaceAbove = rect.top;
	var top;
	if (spaceBelow >= panelHeight + margin) {
		top = rect.bottom;
	} else if (spaceAbove >= panelHeight + margin) {
		top = rect.top - panelHeight;
	} else if (spaceBelow >= spaceAbove) {
		top = viewportBottom - panelHeight - margin;
	} else {
		top = viewportTop + margin;
	}

	var left = rect.left;
	if (left + panelWidth > viewportRight - margin) {
		left = viewportRight - panelWidth - margin;
	}
	if (left < viewportLeft + margin) {
		left = viewportLeft + margin;
	}
	if (top + panelHeight > viewportBottom - margin) {
		top = viewportBottom - panelHeight - margin;
	}
	if (top < viewportTop + margin) {
		top = viewportTop + margin;
	}
	var dialogZ = parseInt($(inputElement).closest(".multi_dialog").css("z-index"), 10);
	if (isNaN(dialogZ)) {
		dialogZ = 10000;
	}

	panel.css({
		position: "fixed",
		top: top,
		left: left,
		"z-index": dialogZ + 2
	});
}

function get_original_time_picker_mode() {
	var timeFormat = get_server_time_format();
	return {
		format: timeFormat,
		usesMeridiem: /[gaA]/.test(timeFormat),
		uppercaseMeridiem: /A/.test(timeFormat),
		padHour: /h/.test(timeFormat)
	};
}

function parse_original_time_value(value, mode) {
	if (typeof parse_time_string_by_format === "function") {
		var parsedByFormat = parse_time_string_by_format(value, mode.format);
		if (parsedByFormat) {
			var parsedHour24 = parseInt(parsedByFormat.hour, 10);
			var parsedMinute = String(parsedByFormat.minute).padStart(2, "0");
			if (mode.usesMeridiem) {
				var parsedMeridiem = parsedHour24 >= 12 ? "PM" : "AM";
				var parsedHour12 = parsedHour24 % 12;
				if (parsedHour12 === 0) {
					parsedHour12 = 12;
				}
				return {
					hour: String(parsedHour12),
					minute: parsedMinute,
					meridiem: parsedMeridiem
				};
			}
			return {
				hour: String(parsedHour24).padStart(2, "0"),
				minute: parsedMinute,
				meridiem: "AM"
			};
		}
	}

	if (mode.usesMeridiem) {
		var match12 = value.match(/^\s*(\d{1,2}):(\d{2})\s*([AaPp][Mm])\s*$/);
		if (match12) {
			return {
				hour: String(parseInt(match12[1], 10)),
				minute: match12[2],
				meridiem: match12[3].toUpperCase()
			};
		}
	}

	var match24 = value.match(/^\s*(\d{1,2}):(\d{2})\s*$/);
	if (match24) {
		var hour24 = parseInt(match24[1], 10);
		if (mode.usesMeridiem) {
			var meridiem = hour24 >= 12 ? "PM" : "AM";
			var hour12 = hour24 % 12;
			if (hour12 === 0) {
				hour12 = 12;
			}
			return {
				hour: String(hour12),
				minute: match24[2],
				meridiem: meridiem
			};
		}
		return {
			hour: String(hour24).padStart(2, "0"),
			minute: match24[2],
			meridiem: "AM"
		};
	}

	return {
		hour: mode.usesMeridiem ? "9" : "09",
		minute: "00",
		meridiem: "AM"
	};
}

function format_original_time_value(hourValue, minuteValue, meridiemValue, mode) {
	var hour24 = parseInt(hourValue, 10);
	if (mode.usesMeridiem) {
		if (meridiemValue === "PM" && hour24 < 12) {
			hour24 += 12;
		}
		if (meridiemValue === "AM" && hour24 === 12) {
			hour24 = 0;
		}
	}
	if (typeof format_php_datetime === "function") {
		return format_php_datetime({
			year: "2000",
			month: "01",
			day: "01",
			hour: String(hour24).padStart(2, "0"),
			minute: minuteValue
		}, mode.format);
	}
	if (mode.usesMeridiem) {
		var displayHour = mode.padHour ? String(hourValue).padStart(2, "0") : String(parseInt(hourValue, 10));
		var suffix = mode.uppercaseMeridiem ? meridiemValue.toUpperCase() : meridiemValue.toLowerCase();
		return displayHour + ":" + minuteValue + " " + suffix;
	}
	return String(hourValue).padStart(2, "0") + ":" + minuteValue;
}

function close_original_time_picker_panel() {
	$(".fbp-original-time-panel").remove();
	$(document).off("mousedown.originalTimePicker");
}

function normalize_hex_color(value) {
	if (typeof value !== "string") {
		return "";
	}
	var normalized = value.trim().replace(/^#/, "");
	if (/^[0-9a-fA-F]{3}$/.test(normalized)) {
		normalized = normalized.charAt(0) + normalized.charAt(0) +
			normalized.charAt(1) + normalized.charAt(1) +
			normalized.charAt(2) + normalized.charAt(2);
	}
	if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
		return "";
	}
	return "#" + normalized.toUpperCase();
}

function hsv_to_rgb(h, s, v) {
	var hue = ((h % 360) + 360) % 360;
	var saturation = Math.max(0, Math.min(1, s));
	var value = Math.max(0, Math.min(1, v));
	var c = value * saturation;
	var x = c * (1 - Math.abs((hue / 60) % 2 - 1));
	var m = value - c;
	var r1 = 0;
	var g1 = 0;
	var b1 = 0;

	if (hue < 60) {
		r1 = c; g1 = x; b1 = 0;
	} else if (hue < 120) {
		r1 = x; g1 = c; b1 = 0;
	} else if (hue < 180) {
		r1 = 0; g1 = c; b1 = x;
	} else if (hue < 240) {
		r1 = 0; g1 = x; b1 = c;
	} else if (hue < 300) {
		r1 = x; g1 = 0; b1 = c;
	} else {
		r1 = c; g1 = 0; b1 = x;
	}

	return {
		r: Math.round((r1 + m) * 255),
		g: Math.round((g1 + m) * 255),
		b: Math.round((b1 + m) * 255)
	};
}

function rgb_to_hex(rgb) {
	function component_to_hex(component) {
		return Math.max(0, Math.min(255, component)).toString(16).toUpperCase().padStart(2, "0");
	}
	return "#" + component_to_hex(rgb.r) + component_to_hex(rgb.g) + component_to_hex(rgb.b);
}

function hex_to_rgb(hex) {
	var normalized = normalize_hex_color(hex);
	if (normalized === "") {
		return null;
	}
	return {
		r: parseInt(normalized.substr(1, 2), 16),
		g: parseInt(normalized.substr(3, 2), 16),
		b: parseInt(normalized.substr(5, 2), 16)
	};
}

function rgb_to_hsv(rgb) {
	var r = rgb.r / 255;
	var g = rgb.g / 255;
	var b = rgb.b / 255;
	var max = Math.max(r, g, b);
	var min = Math.min(r, g, b);
	var delta = max - min;
	var h = 0;

	if (delta !== 0) {
		if (max === r) {
			h = 60 * (((g - b) / delta) % 6);
		} else if (max === g) {
			h = 60 * (((b - r) / delta) + 2);
		} else {
			h = 60 * (((r - g) / delta) + 4);
		}
	}
	if (h < 0) {
		h += 360;
	}

	return {
		h: h,
		s: max === 0 ? 0 : delta / max,
		v: max
	};
}

function close_original_colorpicker_panel() {
	$(".fbp-original-colorpicker-panel").remove();
	$(document).off("mousedown.originalColorPicker");
	$(document).off("mousemove.originalColorPicker");
	$(document).off("mouseup.originalColorPicker");
}

function sort_select_options($select, direction) {
	var currentValue = $select.val();
	var localeCode = get_server_locale_code() || undefined;
	var options = $select.find("option").toArray();
	var blankOptions = [];
	var normalOptions = [];

	for (var i = 0; i < options.length; i++) {
		var option = options[i];
		if ($(option).val() === "") {
			blankOptions.push(option);
		} else {
			normalOptions.push(option);
		}
	}

	normalOptions.sort(function (a, b) {
		var textA = ($(a).text() || "").trim();
		var textB = ($(b).text() || "").trim();
		return textA.localeCompare(textB, localeCode, {
			numeric: true,
			sensitivity: "base"
		});
	});

	if (direction === "desc") {
		normalOptions.reverse();
	}

	$select.empty();
	for (var blankIndex = 0; blankIndex < blankOptions.length; blankIndex++) {
		$select.append(blankOptions[blankIndex]);
	}
	for (var optionIndex = 0; optionIndex < normalOptions.length; optionIndex++) {
		$select.append(normalOptions[optionIndex]);
	}
	$select.val(currentValue).trigger("change");
}

function get_original_select_display_text($select) {
	var $selectedOption = $select.find("option:selected").first();
	if ($selectedOption.length === 0) {
		$selectedOption = $select.find("option").first();
	}
	if ($selectedOption.val() === "") {
		return "";
	}
	var text = ($selectedOption.text() || "").trim();
	return text;
}

function close_original_searchable_select_panel() {
	$(".fbp-original-select-panel").remove();
	$(".fbp-original-select-source").data("originalSearchableSelectOpen", false);
	$(document).off("mousedown.originalSearchableSelect");
}

function refresh_original_searchable_select_display($select) {
	var $wrap = $select.next(".fbp-original-select-wrap");
	if ($wrap.length === 0) {
		return;
	}
	var $button = $wrap.find(".fbp-original-select-button");
	$button.find(".fbp-original-select-label").text(get_original_select_display_text($select));
	$button.prop("disabled", $select.prop("disabled"));
}

function build_original_searchable_select_options($select, $panel, filterText) {
	var normalizedFilter = String(filterText || "").toLowerCase();
	var currentValue = $select.val();
	var $list = $panel.find(".fbp-original-select-options");
	$list.empty();

	var matchedCount = 0;
	$select.find("option").each(function () {
		var $option = $(this);
		var optionValue = $option.val();
		var optionText = ($option.text() || "").trim();
		if (optionText === "" && optionValue === "") {
			optionText = get_client_localized_text("select_unselected");
		}
		if (normalizedFilter !== "" && optionText.toLowerCase().indexOf(normalizedFilter) === -1) {
			return;
		}

		var $item = $('<button type="button" class="fbp-original-select-option"></button>');
		$item.attr("data-value", optionValue);
		$item.text(optionText);
		if (optionValue === currentValue) {
			$item.addClass("is-selected");
		}
		if ($option.prop("disabled")) {
			$item.prop("disabled", true);
		}
		$list.append($item);
		matchedCount++;
	});

	if (matchedCount === 0) {
		$list.append('<div class="fbp-original-select-empty">' + escapeHtml(get_client_localized_text("select_no_results")) + '</div>');
	}
}

function open_original_searchable_select($select) {
	close_original_searchable_select_panel();
	refresh_original_searchable_select_display($select);

	var $panel = $(
		'<div class="fbp-original-select-panel">' +
			'<div class="fbp-original-select-toolbar">' +
				'<input type="text" class="fbp-original-select-search" autocomplete="off" placeholder="' + escapeHtml(get_client_localized_text("select_search_placeholder")) + '">' +
				'<div class="fbp-original-select-sort-controls">' +
					'<button type="button" class="fbp-original-select-sort-button" data-sort-direction="asc">▲</button>' +
					'<button type="button" class="fbp-original-select-sort-button" data-sort-direction="desc">▼</button>' +
				'</div>' +
			'</div>' +
			'<div class="fbp-original-select-options"></div>' +
		'</div>'
	);

	$("body").append($panel);
	build_original_searchable_select_options($select, $panel, "");
	setTimeout(function () {
		reposition_fixed_panel_below_input($panel, $select.next(".fbp-original-select-wrap").find(".fbp-original-select-button").get(0));
		$panel.find(".fbp-original-select-search").trigger("focus");
	}, 0);

	$panel.find(".fbp-original-select-search").on("input", function () {
		build_original_searchable_select_options($select, $panel, $(this).val());
	});

	$panel.find(".fbp-original-select-sort-button").on("click", function (e) {
		e.preventDefault();
		var direction = $(this).data("sort-direction");
		var currentSearch = $panel.find(".fbp-original-select-search").val() || "";
		sort_select_options($select, direction);
		build_original_searchable_select_options($select, $panel, currentSearch);
		refresh_original_searchable_select_display($select);
	});

	$panel.find(".fbp-original-select-options").on("click", ".fbp-original-select-option", function () {
		if ($(this).prop("disabled")) {
			return;
		}
		$select.val($(this).attr("data-value")).trigger("change");
		refresh_original_searchable_select_display($select);
		close_original_searchable_select_panel();
	});

	setTimeout(function () {
		$(document).on("mousedown.originalSearchableSelect", function (e) {
			if ($(e.target).closest(".fbp-original-select-panel").length > 0) {
				return;
			}
			if ($(e.target).closest(".fbp-original-select-wrap").length > 0) {
				return;
			}
			close_original_searchable_select_panel();
		});
	}, 0);
}

function destroy_original_searchable_select($select) {
	var $wrap = $select.next(".fbp-original-select-wrap");
	if ($wrap.length > 0) {
		if ($wrap.find(".fbp-original-select-button").is(":focus")) {
			close_original_searchable_select_panel();
		}
		$wrap.remove();
	}
	$select.removeClass("fbp-original-select-source").show();
	$select.removeData("originalSearchableSelectInitialized");
}

function get_original_searchable_select_width($select) {
	var inlineWidth = $select[0].style.width;
	if (inlineWidth && inlineWidth !== "") {
		return inlineWidth;
	}
	var computedWidth = $select.css("width");
	if (computedWidth && computedWidth !== "0px" && computedWidth !== "auto") {
		return computedWidth;
	}
	return "100%";
}

function ensure_original_searchable_select($select) {
	if ($select.prop("multiple") || $select.children().length < 3) {
		destroy_original_searchable_select($select);
		return;
	}
	if ($select.data("originalSearchableSelectInitialized")) {
		refresh_original_searchable_select_display($select);
		return;
	}

	var wrapWidth = get_original_searchable_select_width($select);
	var $wrap = $('<div class="fbp-original-select-wrap"></div>');
	$wrap.css("width", wrapWidth);
	var $button = $(
		'<button type="button" class="fbp-original-select-button">' +
			'<span class="fbp-original-select-label"></span>' +
			'<span class="fbp-original-select-arrow">▼</span>' +
		'</button>'
	);
	$wrap.append($button);
	$select.addClass("fbp-original-select-source").hide().after($wrap);
	$select.data("originalSearchableSelectInitialized", true);
	refresh_original_searchable_select_display($select);

	$button.on("click", function (e) {
		e.preventDefault();
		if ($button.prop("disabled")) {
			return;
		}
		var isOpen = $(".fbp-original-select-panel").length > 0 && $select.data("originalSearchableSelectOpen") === true;
		close_original_searchable_select_panel();
		$(".fbp-original-select-source").data("originalSearchableSelectOpen", false);
		if (!isOpen) {
			$select.data("originalSearchableSelectOpen", true);
			open_original_searchable_select($select);
		}
	});

	$select.on("change.originalSearchableSelect", function () {
		refresh_original_searchable_select_display($select);
	});
}

function ensure_original_colorpicker_input(input) {
	if (input.data("originalColorpickerInitialized")) {
		return;
	}
	input.data("originalColorpickerInitialized", true);
	input.attr("autocomplete", "off");

	if (!input.parent().hasClass("fbp-original-colorpicker-wrap")) {
		input.wrap('<div class="fbp-original-colorpicker-wrap" style="position:relative;display:flex;align-items:center;gap:8px;"></div>');
	}
	if (input.siblings(".fbp-original-colorpicker-trigger").length === 0) {
		input.after('<button type="button" class="fbp-original-colorpicker-trigger" aria-label="Open color picker" style="flex:0 0 28px;width:28px;height:28px;border:1px solid #cbd5e1;border-radius:6px;padding:0;cursor:pointer;background:#ffffff;"></button>');
	}
	update_original_colorpicker_preview(input);
}

function update_original_colorpicker_preview(input) {
	var normalized = normalize_hex_color(input.val());
	var preview = input.siblings(".fbp-original-colorpicker-trigger");
	var color = normalized === "" ? "#FFFFFF" : normalized;
	var background = normalized === "" ? "linear-gradient(135deg,#f8fafc 0%,#f8fafc 45%,#e2e8f0 45%,#e2e8f0 55%,#f8fafc 55%,#f8fafc 100%)" : color;
	preview.css({
		background: background,
		"box-shadow": normalized === "" ? "inset 0 0 0 1px #e2e8f0" : "inset 0 0 0 1px rgba(15,23,42,0.08)"
	});
}

function open_original_colorpicker_panel(input) {
	close_original_colorpicker_panel();
	ensure_original_colorpicker_input(input);

	var rgb = hex_to_rgb(input.val()) || {r: 59, g: 130, b: 246};
	var hsv = rgb_to_hsv(rgb);
	var draftHex = rgb_to_hex(rgb);
	var panel = $(
		'<div class="fbp-original-colorpicker-panel" style="background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 10px 30px rgba(15,23,42,0.18);padding:14px;min-width:286px;max-width:286px;">' +
			'<div style="display:flex;gap:12px;align-items:flex-start;">' +
				'<div class="fbp-original-colorpicker-sv" style="position:relative;width:180px;height:180px;border-radius:8px;overflow:hidden;cursor:crosshair;background:red;">' +
					'<div style="position:absolute;inset:0;background:linear-gradient(to right,#fff 0%,rgba(255,255,255,0) 100%);"></div>' +
					'<div style="position:absolute;inset:0;background:linear-gradient(to top, #000 0%, rgba(0,0,0,0) 100%);"></div>' +
					'<div class="fbp-original-colorpicker-sv-knob" style="position:absolute;width:12px;height:12px;margin-left:-6px;margin-top:-6px;border:2px solid #fff;border-radius:50%;box-shadow:0 0 0 1px rgba(15,23,42,0.25);pointer-events:none;"></div>' +
				'</div>' +
				'<div class="fbp-original-colorpicker-hue" style="position:relative;width:22px;height:180px;border-radius:8px;overflow:hidden;cursor:row-resize;background:linear-gradient(to bottom,#ff0000 0%,#ff00ff 16.66%,#0000ff 33.33%,#00ffff 50%,#00ff00 66.66%,#ffff00 83.33%,#ff0000 100%);">' +
					'<div class="fbp-original-colorpicker-hue-knob" style="position:absolute;left:0;width:100%;height:4px;margin-top:-2px;background:#fff;box-shadow:0 0 0 1px rgba(15,23,42,0.2);pointer-events:none;"></div>' +
				'</div>' +
				'<div class="fbp-original-colorpicker-preview-block" style="display:flex;flex-direction:column;gap:8px;align-items:stretch;">' +
					'<div class="fbp-original-colorpicker-preview" style="width:42px;height:42px;border:1px solid #cbd5e1;border-radius:8px;"></div>' +
				'</div>' +
			'</div>' +
			'<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">' +
				'<button type="button" class="fbp-original-colorpicker-clear">' + escapeHtml(get_client_localized_text("color_picker_clear")) + '</button>' +
				'<button type="button" class="fbp-original-colorpicker-set">' + escapeHtml(get_client_localized_text("color_picker_set")) + '</button>' +
			'</div>' +
		'</div>'
	);

	$("body").append(panel);

	function render() {
		var hueColor = rgb_to_hex(hsv_to_rgb(hsv.h, 1, 1));
		var knobLeft = hsv.s * 180;
		var knobTop = (1 - hsv.v) * 180;
		var hueTop = (hsv.h / 360) * 180;
		draftHex = rgb_to_hex(hsv_to_rgb(hsv.h, hsv.s, hsv.v));

		panel.find(".fbp-original-colorpicker-sv").css("background", hueColor);
		panel.find(".fbp-original-colorpicker-sv-knob").css({
			left: Math.max(0, Math.min(180, knobLeft)),
			top: Math.max(0, Math.min(180, knobTop))
		});
		panel.find(".fbp-original-colorpicker-hue-knob").css("top", Math.max(0, Math.min(180, hueTop)));
		panel.find(".fbp-original-colorpicker-preview").css("background", draftHex);
	}

	function bind_drag(selector, moveHandler) {
		panel.find(selector).on("mousedown", function (event) {
			event.preventDefault();
			moveHandler(event);
			$(document).off("mousemove.originalColorPicker").on("mousemove.originalColorPicker", function (moveEvent) {
				moveHandler(moveEvent);
			});
			$(document).off("mouseup.originalColorPicker").on("mouseup.originalColorPicker", function () {
				$(document).off("mousemove.originalColorPicker");
				$(document).off("mouseup.originalColorPicker");
			});
		});
	}

	bind_drag(".fbp-original-colorpicker-sv", function (event) {
		var svRect = panel.find(".fbp-original-colorpicker-sv").get(0).getBoundingClientRect();
		var localX = Math.max(0, Math.min(svRect.width, event.clientX - svRect.left));
		var localY = Math.max(0, Math.min(svRect.height, event.clientY - svRect.top));
		hsv.s = localX / svRect.width;
		hsv.v = 1 - (localY / svRect.height);
		render();
	});

	bind_drag(".fbp-original-colorpicker-hue", function (event) {
		var hueRect = panel.find(".fbp-original-colorpicker-hue").get(0).getBoundingClientRect();
		var localY = Math.max(0, Math.min(hueRect.height, event.clientY - hueRect.top));
		hsv.h = (localY / hueRect.height) * 360;
		if (hsv.h >= 360) {
			hsv.h = 359.999;
		}
		render();
	});

	panel.find(".fbp-original-colorpicker-clear").on("click", function () {
		input.val("").trigger("change");
		update_original_colorpicker_preview(input);
		close_original_colorpicker_panel();
	});

	panel.find(".fbp-original-colorpicker-set").on("click", function () {
		input.val(draftHex).trigger("change");
		update_original_colorpicker_preview(input);
		close_original_colorpicker_panel();
	});

	render();
	setTimeout(function () {
		reposition_fixed_panel_below_input(panel, input.get(0));
	}, 0);

	setTimeout(function () {
		$(document).on("mousedown.originalColorPicker", function (e) {
			if ($(e.target).closest(".fbp-original-colorpicker-panel").length > 0) {
				return;
			}
			if ($(e.target).closest(".fbp-original-colorpicker-wrap").length > 0 || $(e.target).closest(".colorpicker").length > 0) {
				return;
			}
			close_original_colorpicker_panel();
		});
	}, 0);
}

function open_original_time_picker_panel(input) {
	close_original_time_picker_panel();

	var current = input.val();
	var mode = get_original_time_picker_mode();
	var parsed = parse_original_time_value(current, mode);
	var hourValue = parsed.hour;
	var minuteValue = parsed.minute;
	var meridiemValue = parsed.meridiem;
	var minuteNumber = parseInt(minuteValue, 10);
	var minuteStep = "10";
	if (!isNaN(minuteNumber)) {
		if (minuteNumber % 10 === 0) {
			minuteStep = "10";
		} else if (minuteNumber % 5 === 0) {
			minuteStep = "5";
		} else {
			minuteStep = "1";
		}
	}

	var hourOptions = "";
	var hourStart = mode.usesMeridiem ? 1 : 0;
	var hourEnd = mode.usesMeridiem ? 12 : 23;
	for (var h = hourStart; h <= hourEnd; h++) {
		var hh = mode.usesMeridiem ? String(h) : String(h).padStart(2, "0");
		hourOptions += '<option value="' + hh + '">' + hh + '</option>';
	}
	var meridiemSelectHtml = "";
	if (mode.usesMeridiem) {
		meridiemSelectHtml =
			'<div style="width:84px;">' +
				'<div style="font-size:12px;color:#64748b;margin-bottom:4px;">' + escapeHtml(get_client_localized_text("original_time_meridiem")) + '</div>' +
				'<select class="fbp-original-time-meridiem" style="width:100%;">' +
					'<option value="AM">AM</option>' +
					'<option value="PM">PM</option>' +
				'</select>' +
			'</div>';
	}
	var panel = $(
		'<div class="fbp-original-time-panel" style="background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 10px 30px rgba(15,23,42,0.18);padding:14px;min-width:260px;max-width:320px;">' +
			'<div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:10px;font-size:12px;color:#475569;gap:8px;">' +
				'<label style="display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="radio" name="fbp_original_time_step" value="10" checked>10</label>' +
				'<label style="display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="radio" name="fbp_original_time_step" value="5">5</label>' +
				'<label style="display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="radio" name="fbp_original_time_step" value="1">1</label>' +
			'</div>' +
			'<div style="display:flex;gap:10px;align-items:end;margin-bottom:12px;">' +
				'<div style="flex:1;">' +
					'<div style="font-size:12px;color:#64748b;margin-bottom:4px;">' + escapeHtml(get_client_localized_text("original_time_hour")) + '</div>' +
					'<select class="fbp-original-time-hour" style="width:100%;">' + hourOptions + '</select>' +
				'</div>' +
				'<div style="flex:1;">' +
					'<div style="font-size:12px;color:#64748b;margin-bottom:4px;">' + escapeHtml(get_client_localized_text("original_time_minute")) + '</div>' +
					'<select class="fbp-original-time-minute" style="width:100%;"></select>' +
				'</div>' +
				meridiemSelectHtml +
			'</div>' +
			'<div style="display:flex;gap:8px;justify-content:flex-end;">' +
				'<button type="button" class="fbp-original-time-clear">' + escapeHtml(get_client_localized_text("original_time_clear")) + '</button>' +
				'<button type="button" class="fbp-original-time-set">' + escapeHtml(get_client_localized_text("original_time_set")) + '</button>' +
			'</div>' +
		'</div>'
	);

	$("body").append(panel);
	panel.find(".fbp-original-time-hour").val(hourValue);
	panel.find(".fbp-original-time-meridiem").val(meridiemValue);

	function rebuild_minute_options(step, selectedMinute) {
		var minuteOptions = "";
		for (var m = 0; m < 60; m += step) {
			var mm = String(m).padStart(2, "0");
			minuteOptions += '<option value="' + mm + '">' + mm + '</option>';
		}
		var minuteSelect = panel.find(".fbp-original-time-minute");
		minuteSelect.html(minuteOptions);
		var normalizedMinute = String(Math.floor(parseInt(selectedMinute || "0", 10) / step) * step).padStart(2, "0");
		minuteSelect.val(normalizedMinute);
	}

	panel.find('input[name="fbp_original_time_step"][value="' + minuteStep + '"]').prop("checked", true);
	rebuild_minute_options(parseInt(minuteStep, 10), minuteValue);

	panel.find('input[name="fbp_original_time_step"]').on("change", function () {
		minuteStep = $(this).val();
		rebuild_minute_options(parseInt(minuteStep, 10), panel.find(".fbp-original-time-minute").val());
	});

	setTimeout(function () {
		reposition_fixed_panel_below_input(panel, input.get(0));
	}, 0);

	panel.find(".fbp-original-time-clear").on("click", function () {
		input.val("").trigger("change");
		close_original_time_picker_panel();
	});
	panel.find(".fbp-original-time-set").on("click", function () {
		var selectedHour = panel.find(".fbp-original-time-hour").val();
		var selectedMinute = panel.find(".fbp-original-time-minute").val();
		var selectedMeridiem = panel.find(".fbp-original-time-meridiem").val() || "AM";
		var value = format_original_time_value(selectedHour, selectedMinute, selectedMeridiem, mode);
		input.val(value).trigger("change");
		close_original_time_picker_panel();
	});

	setTimeout(function () {
		$(document).on("mousedown.originalTimePicker", function (e) {
			if ($(e.target).closest(".fbp-original-time-panel").length > 0) {
				return;
			}
			if ($(e.target).closest(".timepicker").length > 0) {
				return;
			}
			close_original_time_picker_panel();
		});
	}, 0);
}

function get_locale_weekday_names(locale) {
	var weekdayNames = [];
	for (var dayIndex = 0; dayIndex < 7; dayIndex++) {
		var date = new Date(Date.UTC(2024, 0, 7 + dayIndex));
		weekdayNames.push(new Intl.DateTimeFormat(locale, {
			weekday: "short",
			timeZone: "UTC"
		}).format(date));
	}
	return weekdayNames;
}

function close_original_datepicker_panel() {
	$(".fbp-original-datepicker-panel").remove();
	$(document).off("mousedown.originalDatepicker");
}

function open_original_datepicker_panel(input) {
	close_original_datepicker_panel();

	var dateFormat = get_server_date_format();
	var locale = get_server_locale_code();
	var currentValue = input.val();
	var parsed = extract_date_parts_by_format(currentValue, dateFormat);
	var baseDate = new Date();
	var selectedYear = null;
	var selectedMonth = null;
	var selectedDay = null;

	if (parsed && parsed.Y && (parsed.m || parsed.n) && (parsed.d || parsed.j)) {
		selectedYear = parseInt(parsed.Y, 10);
		selectedMonth = parseInt(parsed.m || parsed.n, 10);
		selectedDay = parseInt(parsed.d || parsed.j, 10);
		baseDate = new Date(selectedYear, selectedMonth - 1, selectedDay);
	}

	var viewYear = baseDate.getFullYear();
	var viewMonth = baseDate.getMonth() + 1;
	var maxYear = new Date().getFullYear() + 30;
	var monthNames = [];
	for (var monthIndex = 0; monthIndex < 12; monthIndex++) {
		monthNames.push(new Intl.DateTimeFormat(locale, {
			month: "long",
			timeZone: "UTC"
		}).format(new Date(Date.UTC(2000, monthIndex, 1))));
	}
	var weekdayNames = get_locale_weekday_names(locale);
	var panel = $('<div class="fbp-original-datepicker-panel" style="background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 10px 30px rgba(15,23,42,0.18);padding:14px;min-width:372px;max-width:372px;"></div>');

	function renderCalendar() {
		panel.empty();
		var yearOptions = "";
		for (var yearOption = 1930; yearOption <= maxYear; yearOption++) {
			yearOptions += '<option value="' + yearOption + '"' + (yearOption === viewYear ? ' selected="selected"' : '') + '>' + yearOption + '</option>';
		}
		var monthOptions = "";
		for (var monthOption = 1; monthOption <= 12; monthOption++) {
			monthOptions += '<option value="' + monthOption + '"' + (monthOption === viewMonth ? ' selected="selected"' : '') + '>' + escapeHtml(monthNames[monthOption - 1]) + '</option>';
		}

		var header = $(
			'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:8px;">' +
				'<button type="button" class="fbp-original-datepicker-prev" style="min-width:36px;margin-top:0;margin-left:0;">&lt;</button>' +
				'<div style="flex:1;display:flex;gap:8px;align-items:center;">' +
					'<select class="fbp-original-datepicker-year" style="flex:1;min-width:0;">' + yearOptions + '</select>' +
					'<select class="fbp-original-datepicker-month" style="flex:1;min-width:0;">' + monthOptions + '</select>' +
				'</div>' +
				'<button type="button" class="fbp-original-datepicker-next" style="min-width:36px;margin-top:0;margin-left:0;">&gt;</button>' +
			'</div>'
		);
		panel.append(header);

		var weekRow = $('<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px;"></div>');
		for (var weekdayIndex = 0; weekdayIndex < 7; weekdayIndex++) {
			weekRow.append('<div style="text-align:center;font-size:12px;color:#64748b;padding:4px 0;">' + escapeHtml(weekdayNames[weekdayIndex]) + '</div>');
		}
		panel.append(weekRow);

		var daysGrid = $('<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-right:20px;"></div>');
		var firstDayOfMonth = new Date(viewYear, viewMonth - 1, 1);
		var startWeekday = firstDayOfMonth.getDay();
		var daysInMonth = new Date(viewYear, viewMonth, 0).getDate();
		var totalCells = Math.ceil((startWeekday + daysInMonth) / 7) * 7;
		var today = new Date();
		var todayYear = today.getFullYear();
		var todayMonth = today.getMonth() + 1;
		var todayDay = today.getDate();

		for (var cellIndex = 0; cellIndex < totalCells; cellIndex++) {
			var dayNumber = cellIndex - startWeekday + 1;
			if (dayNumber < 1 || dayNumber > daysInMonth) {
				daysGrid.append('<div style="height:34px;"></div>');
				continue;
			}

			var isSelected = selectedYear === viewYear && selectedMonth === viewMonth && selectedDay === dayNumber;
			var isToday = todayYear === viewYear && todayMonth === viewMonth && todayDay === dayNumber;
			var borderColor = isSelected ? '#2563eb' : (isToday ? '#f59e0b' : '#cbd5e1');
			var backgroundColor = isSelected ? '#dbeafe' : (isToday ? '#fef3c7' : '#fff');
			var dayButton = $('<button type="button" class="fbp-original-datepicker-day" style="width:100%;height:34px;padding:0;box-sizing:border-box;border:1px solid ' + borderColor + ';background:' + backgroundColor + ';border-radius:8px;color:#0f172a;">' + dayNumber + '</button>');
			dayButton.attr("data-day", dayNumber);
			daysGrid.append(dayButton);
		}
		panel.append(daysGrid);
		panel.append(
			'<div style="display:flex;justify-content:flex-end;margin-top:12px;">' +
				'<button type="button" class="fbp-original-datepicker-clear">' + escapeHtml(get_client_localized_text("date_picker_clear")) + '</button>' +
			'</div>'
		);
	}

	function moveMonth(diff) {
		viewMonth += diff;
		if (viewMonth < 1) {
			viewMonth = 12;
			viewYear -= 1;
		} else if (viewMonth > 12) {
			viewMonth = 1;
			viewYear += 1;
		}
		renderCalendar();
		bindCalendarEvents();
		setTimeout(function () {
			reposition_fixed_panel_below_input(panel, input.get(0));
		}, 0);
	}

	function bindCalendarEvents() {
		panel.find(".fbp-original-datepicker-prev").off("click").on("click", function () {
			moveMonth(-1);
		});
		panel.find(".fbp-original-datepicker-next").off("click").on("click", function () {
			moveMonth(1);
		});
		panel.find(".fbp-original-datepicker-year").off("change").on("change", function () {
			viewYear = parseInt($(this).val(), 10);
			renderCalendar();
			bindCalendarEvents();
		});
		panel.find(".fbp-original-datepicker-month").off("change").on("change", function () {
			viewMonth = parseInt($(this).val(), 10);
			renderCalendar();
			bindCalendarEvents();
		});
		panel.find(".fbp-original-datepicker-clear").off("click").on("click", function () {
			input.val("").trigger("change");
			close_original_datepicker_panel();
		});
		panel.find(".fbp-original-datepicker-day").off("click").on("click", function () {
			var pickedDay = parseInt($(this).attr("data-day"), 10);
			input.val(format_php_datetime({
				year: String(viewYear),
				month: String(viewMonth).padStart(2, "0"),
				day: String(pickedDay).padStart(2, "0"),
				hour: "00",
				minute: "00"
			}, dateFormat)).trigger("change");
			close_original_datepicker_panel();
		});
	}

	renderCalendar();
	bindCalendarEvents();
	$("body").append(panel);
	setTimeout(function () {
		reposition_fixed_panel_below_input(panel, input.get(0));
	}, 0);

	setTimeout(function () {
		$(document).on("mousedown.originalDatepicker", function (e) {
			if ($(e.target).closest(".fbp-original-datepicker-panel").length > 0) {
				return;
			}
			if ($(e.target).closest(".datepicker").length > 0 || $(e.target).closest(".datepicker_clear").length > 0) {
				return;
			}
			close_original_datepicker_panel();
		});
	}, 0);
}
(function ($) {
	$.fn.year_month_picker = function () {
		return this.each(function () {
			var input = $(this);
			if (input.data("yearMonthPickerInitialized")) {
				return;
			}
			input.data("yearMonthPickerInitialized", true);
			if (!input.parent().hasClass("year_month_picker_wrap")) {
				input.wrap('<div class="year_month_picker_wrap" style="position:relative;display:inline;"></div>');
			}
			input.attr('autocomplete', 'off');

			// フォーカスがあたった時の処理
			input.on("focus.yearMonthPicker", function () {
				input.blur();

				$(".year_month_picker_panel").remove();
				$(".selectorclose").remove();

				var html = '';
				html += '<div class="year_month_picker_panel" style="background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 10px 30px rgba(15,23,42,0.18);padding:14px;min-width:260px;">';
				html += '<div style="display:flex;gap:10px;align-items:end;margin-bottom:12px;">';
				html += '<div style="flex:1;">';
				html += '<div style="font-size:12px;color:#64748b;margin-bottom:4px;">' + escapeHtml(get_client_localized_text("year_month_year")) + '</div>';
				html += '<input type="text" class="picker_year_input" style="width:100%;">';
				html += '</div>';
				html += '<div style="flex:1;">';
				html += '<div style="font-size:12px;color:#64748b;margin-bottom:4px;">' + escapeHtml(get_client_localized_text("year_month_month")) + '</div>';
				html += '<select class="picker_month_select" style="width:100%;">';
				html += '<option value="01">1</option>';
				html += '<option value="02">2</option>';
				html += '<option value="03">3</option>';
				html += '<option value="04">4</option>';
				html += '<option value="05">5</option>';
				html += '<option value="06">6</option>';
				html += '<option value="07">7</option>';
				html += '<option value="08">8</option>';
				html += '<option value="09">9</option>';
				html += '<option value="10">10</option>';
				html += '<option value="11">11</option>';
				html += '<option value="12">12</option>';
				html += '</select>';
				html += "</div>";
				html += "</div>";
				html += '<div style="display:flex;gap:8px;justify-content:flex-end;">';
				html += '<button type="button" class="picker_blank" style="margin-top:0;">' + escapeHtml(get_client_localized_text("year_month_clear")) + '</button>';
				html += '<button type="button" class="picker_set" style="margin-top:0;">' + escapeHtml(get_client_localized_text("year_month_set")) + '</button>';
				html += '</div>';
				html += '<p class="picker_error" style="margin:8px 0 0 0;color:#dc2626;font-size:12px;"></p>';
				html += '</div>';
				$("body").append(html);
				var panel = $(".year_month_picker_panel");
				var year;
				var month;
				var hiduke = new Date();
				var yearMonthFormat = get_server_year_month_format();
				var monthNames = get_locale_short_month_names(get_server_locale_code());
				panel.find(".picker_month_select option").each(function () {
					var optionMonth = parseInt($(this).val(), 10);
					if (yearMonthFormat.indexOf("M") !== -1 && !isNaN(optionMonth)) {
						$(this).text(monthNames[optionMonth - 1] || optionMonth);
					}
				});
				if (input.val() == "") {
					year = hiduke.getFullYear();
					month = hiduke.getMonth() + 1;
				} else {
					var parsedYearMonth = parse_year_month_by_format(input.val(), yearMonthFormat);
					if (parsedYearMonth) {
						year = parsedYearMonth.year;
						month = parsedYearMonth.month;
					}
					if (year == undefined || isNaN(year))
						year = hiduke.getFullYear();
					if (month == undefined || isNaN(month))
						month = hiduke.getMonth() + 1;
				}
				var pad_month = ('0' + month).slice(-2);
				panel.find(".picker_year_input").val(year);
				panel.find(".picker_month_select").val(pad_month);
				reposition_fixed_panel_below_input(panel, input.get(0));
				panel.addClass('detect_outside_click');
				setTimeout(function () {
					year_month_picker = true;
				}, 200);

				panel.find(".picker_blank").off("click.yearMonthPicker").on("click.yearMonthPicker", function () {
					input.val("");
					panel.remove();
					$(".selectorclose").remove();
					input.change();
					year_month_picker = false;
					return false;
				});
				panel.find(".picker_set").off("click.yearMonthPicker").on("click.yearMonthPicker", function () {
					year = panel.find(".picker_year_input").val();
					month = panel.find(".picker_month_select").val();

					//年のバリデート
					var flg = true;
					if (year.length != 4) {
						flg = false;
					}
					if (year.match(/[^0-9]+/)) {
						flg = false;
					}
					if (flg) {
						input.val(format_php_datetime({
							year: year,
							month: month,
							day: "01",
							hour: "00",
							minute: "00"
						}, yearMonthFormat));
						input.change();
						panel.remove();
						$(".selectorclose").remove();
					} else {
						panel.find(".picker_error").html(escapeHtml(get_client_localized_text("year_month_error")));
					}
					year_month_picker = false;
					return false;
				});
			});
		});
	};
})(jQuery);

function get_formdata_with_strtotime(form) {
	$(form).find("input").each(function (index, element) {
		if ($(this).data("strtotime") == "1") {
			var val = $(this).val();
			var time = parse_date_string_to_timestamp(val, get_server_date_format());
			if (time === "") {
				time = "";
			}
			$(this).val(time);
			$(this).attr("data-before", val);
		}
			if ($(this).hasClass("year_month_picker")) {
				var yearMonthVal = $(this).val();
				var compactYearMonth = $(this).data("year_month_compact") == "1";
				if (compactYearMonth) {
					var parsedYearMonth = parse_year_month_by_format(yearMonthVal, get_server_year_month_format());
					if (parsedYearMonth) {
						$(this).val(parsedYearMonth.year + parsedYearMonth.month);
					}
				}
				$(this).attr("data-before-year-month", yearMonthVal);
			}
	});
	var fd = new FormData(form);

	//戻す
	$(form).find("input").each(function (index, element) {
		if ($(this).data("strtotime") == "1") {
			var val = $(this).attr("data-before");
			$(this).val(val);
		}
		if ($(this).hasClass("year_month_picker")) {
			var yearMonthVal = $(this).attr("data-before-year-month");
			if (yearMonthVal !== undefined) {
				$(this).val(yearMonthVal);
			}
		}
	});

	return fd;
}


var userAgent = window.navigator.userAgent.toLowerCase();
// ajax
$("body").on("click", ".ajax-link", function (event) {

	event.preventDefault();
	if (dialog_link_flg) {
		dialog_link_flg = false;
		setTimeout(function () {
			dialog_link_flg = true;
		}, 50);

		// formを探す
		// 1) 指定されたフォーム
		var form = $(this).data("form");
		if (form !== undefined) {
			form = $("#" + form).get(0);
		} else {
			form = null
			// 2) 親要素のフォームを探す
			var fc = $(this).closest("form");
			if (fc.length > 0) {
				form = fc.get(0);
			} else {
				// 3) .getting_dialog_id の中のフォームを探す
				var dialogParent = $(this).closest(".getting_dialog_id");
				if (dialogParent.length > 0) {
					var fdi = dialogParent.find("form");
					if (fdi.length > 0) {
						form = fdi.first().get(0); // .getting_dialog_id 内の最初のフォームを使用
					}
				}
			}
		}

		// 日付を数値ににしてFormDataを取得 (カスタムプラグインのため)
		var fd;
		if (form !== null) {
			fd = get_formdata_with_strtotime(form);
		} else {
			fd = new FormData();
		}

		// dialog_idを取得
		let tag = $(this).parents(".getting_dialog_id");
		let dialog_id = tag.attr('id');
		if (dialog_id !== undefined) {
			fd.append("_dialog_id", "#" + dialog_id);
		}

		var url = $(this).data("url");
		if (url === undefined) {
			url = "app.php";
		}
		var datalist = $(this).data();
		for (key in datalist) {
			fd.append(key, datalist[key]);
		}

		// data-_chatid を取得
		let chattag = $(this).parents(".chat-html");
		let chatid = chattag.data("_chatid");
		if (chatid !== undefined && chatid !== null && chatid !== "") {
			// chatid が "#" で始まっていなければ追加
			if (!chatid.startsWith("#")) {
				chatid = "#" + chatid;
			}

			fd.append("_chatid", chatid);

			// dialog_id が undefined の場合、_dialog_id も chatid にセット
			if (dialog_id === undefined) {
				fd.append("_dialog_id", chatid);
			}
		}

		appcon(url, fd);
	}
});

$("body").on("click", ".screen_debug_copy_icon", function (event) {
	event.preventDefault();
	event.stopPropagation();
	var $box = $(this).closest(".screen_debug_captured");
	var code = String($box.attr("data-screen_debug_code") || "").trim();
	if (code === "") {
		return;
	}
	copyScreenDebugCode(code, function () {
		$(event.currentTarget).addClass("copied");
		setTimeout(function () {
			$(event.currentTarget).removeClass("copied");
		}, 700);
	});
});

function copyScreenDebugCode(code, copied) {
	var copyText = String(code || "").trim();
	if (copyText === "") {
		return;
	}
	if (navigator.clipboard && navigator.clipboard.writeText) {
		navigator.clipboard.writeText(copyText).then(copied).catch(function () {
			copyTextFallback(copyText);
			copied();
		});
		return;
	}
	copyTextFallback(copyText);
	copied();
}

function copyTextFallback(text) {
	var ta = document.createElement("textarea");
	ta.value = text;
	ta.style.position = "fixed";
	ta.style.left = "-9999px";
	document.body.appendChild(ta);
	ta.focus();
	ta.select();
	try {
		document.execCommand("copy");
	} catch (err) {
		// noop
	}
	document.body.removeChild(ta);
}

function renderScreenDebugLogCaptured(payload) {
	var screenKey = String(payload["screen_key"] || "").trim();
	var code = String(payload["code"] || "").trim();
	var label = String(payload["label"] || "Screen ID").trim();
	var copyTitle = String(payload["copy_title"] || "Copy").trim();
	if (screenKey === "" || code === "") {
		return;
	}
	$(".screen_debug_icon").filter(function () {
		return String($(this).attr("data-screen_key") || "").trim() === screenKey;
	}).each(function () {
		var $icon = $(this);
		var $scope = $icon.closest(".multi_dialog");
		if ($scope.length === 0) {
			$scope = $icon.closest(".work_area");
		}
		$icon.removeClass("ajax-link");
		$icon.addClass("screen_debug_captured");
		$icon.removeAttr("data-class");
		$icon.removeAttr("data-function");
		$icon.removeAttr("data-screen_key");
		$icon.attr("data-screen_debug_code", code);
		$icon.attr("title", code);
		$icon.empty();
		$("<span>").addClass("screen_debug_captured_text").text(code).appendTo($icon);
		copyScreenDebugCode(code, function () {});
		$scope.find(".screen_debug_id_value").each(function () {
			if ($(this).is("input, textarea")) {
				$(this).val(code);
			} else {
				$(this).text(code);
			}
		});
	});
}

// ajax-formボタン
$("body").on("click", ".form_button", function (event) {

	var formobj = $(this).parents("form");
	var fd = new FormData(formobj.get(0));
	fd.append($(this).attr("name"), $(this).attr("value"));
	var url = formobj.attr("action");
	if (url === undefined) {
		url = "app.php";
	}
	appcon(url, fd);

	event.preventDefault();
});

// ダイアログを表示する
var dialog_link_flg = true;
function set_dialog_link() {
	$("body").on("click", ".dialog-link", function (event) {

		event.preventDefault();

		if (dialog_link_flg) {
			dialog_link_flg = false;
			setTimeout(function () {
				dialog_link_flg = true;
			}, 50);
			var fd = new FormData();
			var url = $(this).data("url");
			if (url === undefined) {
				url = "app.php";
			}
			var datalist = $(this).data();
			for (key in datalist) {
				fd.append(key, datalist[key]);
			}

			appcon(url, fd);
		}
	});
}
set_dialog_link();

/*
 * ダイアログ処理
 */
$("#dialog").dialog({
	autoOpen: false,
	width: get_dialog_width(),
	resizable: true,
	modal: true,
	show: {effect: 'fade', duration: 200},
	hide: {effect: 'fade', duration: 10},
	position: {my: "center top", at: "center top", of: window},
	buttons: [
		{
			text: "Ok",
			class: "dialog-button-ok",
			click: function () {
				var formobj = $(this).find("#dialogform");
				if (formobj != null) {
					var fd = new FormData(formobj.get(0));
					var url = formobj.attr("action");
					if (url != undefined) {
						appcon(url, fd);
					} else {
						$(this).dialog("close");
					}
				}
			}
		},
		{
			text: "Cancel",
			class: "dialog-button-cancel",
			click: function () {
				$(this).dialog("close");
			}
		}
	],
	close: function () {
		//個別に非表示にしたボタンを表示させる
		$(".ui-dialog-buttonset").show();
		$(".dialog-button-ok").show();
		$(".dialog-button-cancel").show();
		$(".ui-dialog-buttonpane").show();
	}
});

/*
 * ダイアログの横幅の自動設定
 */
function get_dialog_width(userwidth) {

	var w = window.innerWidth;
	if (w < userwidth) {
		return w * 0.9;
	}

	return userwidth;
}

/*
 * ダイアログの縦幅の自動設定
 */
function get_dialog_height() {

	var h = window.innerHeight * 0.8;
	return h;
}


/* 
 * アプリ用汎用通信関数
 */
var myChart = new Array(); //チャート用オブジェクト
var waitTimer;
var flg_reloadarea_fade = true;

function get_server_timezone() {
	var timezone = $("#server_timezone").text();
	if (timezone === undefined || timezone === null || timezone === "") {
		return "UTC";
	}
	return timezone;
}

function get_server_language_code() {
	var languageCode = $("#server_language_code").text();
	if (languageCode) {
		return languageCode;
	}
	var legacyLang = $("#lang_default").text();
	if (legacyLang === "jp") {
		return "ja";
	}
	if (legacyLang) {
		return legacyLang;
	}
	return "en";
}

function get_server_locale_code() {
	var localeCode = $("#server_locale_code").text();
	if (localeCode) {
		return localeCode;
	}
	var languageCode = get_server_language_code();
	if (languageCode === "ja") {
		return "ja-JP";
	}
	if (languageCode === "zh") {
		return "zh-CN";
	}
	return "en-US";
}

function get_server_date_format() {
	var format = $("#server_date_format").text();
	return format ? format : "Y/m/d";
}

function get_server_datetime_format() {
	var format = $("#server_datetime_format").text();
	return format ? format : "Y/m/d H:i";
}

function get_server_year_month_format() {
	var format = $("#server_year_month_format").text();
	return format ? format : "Y/m";
}

function get_server_time_format() {
	return extract_time_format_from_php_datetime_format(get_server_datetime_format());
}

function extract_time_format_from_php_datetime_format(format) {
	var match = format.match(/([HhGg][^YmdnjiAa]*i(?:[^YmdnjiAa]*[Aa])?)/);
	if (match && match[1]) {
		return match[1].trim();
	}
	return "H:i";
}

function php_date_format_to_datepicker_format(format) {
	return format
			.replace(/Y/g, "yy")
			.replace(/m/g, "mm")
			.replace(/d/g, "dd")
			.replace(/n/g, "m")
			.replace(/j/g, "d");
}

function get_locale_short_month_names(locale) {
	var monthNames = [];
	for (var monthIndex = 0; monthIndex < 12; monthIndex++) {
		monthNames.push(new Intl.DateTimeFormat(locale, {
			month: "short",
			timeZone: "UTC"
		}).format(new Date(Date.UTC(2000, monthIndex, 1))));
	}
	return monthNames;
}

function build_placeholder_from_php_date_format(format) {
	return format
			.replace(/Y/g, "yyyy")
			.replace(/M/g, "mmm")
			.replace(/m/g, "mm")
			.replace(/d/g, "dd")
			.replace(/n/g, "m")
			.replace(/j/g, "d")
			.replace(/H/g, "hh")
			.replace(/h/g, "hh")
			.replace(/G/g, "h")
			.replace(/g/g, "h")
			.replace(/i/g, "mm")
			.replace(/A/g, "AM/PM")
			.replace(/a/g, "am/pm");
}

function php_time_format_to_timepicker_format(format) {
	return format
			.replace(/H/g, "H")
			.replace(/G/g, "H")
			.replace(/h/g, "hh")
			.replace(/g/g, "h")
			.replace(/i/g, "mm")
			.replace(/A/g, "p")
			.replace(/a/g, "a");
}

function extract_date_parts_by_format(value, format) {
	var tokens = [];
	var pattern = format.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/Y|M|m|d|n|j|H|i/g, function (token) {
		tokens.push(token);
		if (token === "Y") {
			return "(\\d{4})";
		}
		if (token === "M") {
			var monthNames = get_locale_short_month_names(get_server_locale_code()).map(function (name) {
				return name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			});
			return "(" + monthNames.join("|") + ")";
		}
		return "(\\d{1,2})";
	});
	var match = new RegExp("^" + pattern + "$").exec(value);
	if (!match) {
		return null;
	}
	var parts = {};
	for (var i = 0; i < tokens.length; i++) {
		parts[tokens[i]] = match[i + 1];
	}
	return parts;
}

function extract_time_parts_by_format(value, format) {
	var tokens = [];
	var pattern = format.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/H|G|h|g|i|A|a/g, function (token) {
		tokens.push(token);
		if (token === "A" || token === "a") {
			return "([aApP][mM])";
		}
		return "(\\d{1,2})";
	});
	var match = new RegExp("^" + pattern + "$").exec(value);
	if (!match) {
		return null;
	}
	var parts = {};
	for (var i = 0; i < tokens.length; i++) {
		parts[tokens[i]] = match[i + 1];
	}
	return parts;
}

function format_php_datetime(parts, format) {
	var localeMonthNames = null;
	return format.replace(/Y|M|m|d|n|j|H|G|h|g|i|A|a/g, function (token) {
		var hour24 = parseInt(parts.hour, 10);
		var hour12 = hour24 % 12;
		if (hour12 === 0) {
			hour12 = 12;
		}
		if (token === "Y") {
			return parts.year;
		}
		if (token === "M") {
			if (localeMonthNames === null) {
				localeMonthNames = get_locale_short_month_names(get_server_locale_code());
			}
			return localeMonthNames[Math.max(0, parseInt(parts.month, 10) - 1)] || parts.month;
		}
		if (token === "m") {
			return parts.month;
		}
		if (token === "d") {
			return parts.day;
		}
		if (token === "n") {
			return String(parseInt(parts.month, 10));
		}
		if (token === "j") {
			return String(parseInt(parts.day, 10));
		}
		if (token === "H") {
			return parts.hour;
		}
		if (token === "G") {
			return String(hour24);
		}
		if (token === "h") {
			return ('0' + hour12).slice(-2);
		}
		if (token === "g") {
			return String(hour12);
		}
		if (token === "i") {
			return parts.minute;
		}
		if (token === "A") {
			return hour24 >= 12 ? "PM" : "AM";
		}
		if (token === "a") {
			return hour24 >= 12 ? "pm" : "am";
		}
		return token;
	});
}

function parse_date_string_to_timestamp(value, format) {
	var parts = extract_date_parts_by_format(value, format);
	if (!parts || !parts.Y) {
		return "";
	}
	var year = parseInt(parts.Y, 10);
	var month = parseInt(parts.m || parts.n, 10);
	var day = parseInt(parts.d || parts.j, 10);
	if ([year, month, day].some(function (part) {
			return Number.isNaN(part);
		})) {
		return "";
	}
	return Math.floor(new Date(year, month - 1, day).getTime() / 1000);
}

function parse_year_month_by_format(value, format) {
	var parts = extract_date_parts_by_format(value, format);
	if (!parts || !parts.Y) {
		var normalized = String(value || "").replace(/[^0-9]/g, "");
		if (normalized.length >= 6) {
			var fallbackYear = normalized.slice(0, 4);
			var fallbackMonth = normalized.slice(4, 6);
			var fallbackMonthNumber = parseInt(fallbackMonth, 10);
			if (!Number.isNaN(fallbackMonthNumber) && fallbackMonthNumber >= 1 && fallbackMonthNumber <= 12) {
				return {
					year: fallbackYear,
					month: ('0' + fallbackMonthNumber).slice(-2)
				};
			}
		}
		return null;
	}
	var month = parts.m || parts.n;
	if (!month && parts.M) {
		var monthNames = get_locale_short_month_names(get_server_locale_code());
		var monthIndex = monthNames.findIndex(function (name) {
			return String(name).toLowerCase() === String(parts.M).toLowerCase();
		});
		if (monthIndex >= 0) {
			month = String(monthIndex + 1).padStart(2, "0");
		}
	}
	if (!month) {
		return null;
	}
	return {
		year: parts.Y,
		month: ('0' + parseInt(month, 10)).slice(-2)
	};
}

function parse_time_string_by_format(value, format) {
	var parts = extract_time_parts_by_format(value, format);
	if (!parts) {
		return null;
	}
	var hour = parseInt(parts.H || parts.G || parts.h || parts.g, 10);
	var minute = parseInt(parts.i, 10);
	if ([hour, minute].some(function (part) {
			return Number.isNaN(part);
		})) {
		return null;
	}
	if (parts.h || parts.g) {
		var meridiem = (parts.A || parts.a || "").toLowerCase();
		if (meridiem === "pm" && hour < 12) {
			hour += 12;
		}
		if (meridiem === "am" && hour === 12) {
			hour = 0;
		}
	}
	return {
		hour: hour,
		minute: minute
	};
}

function get_datetime_parts_in_timezone(timestamp, timezone) {
	var formatter = new Intl.DateTimeFormat("en-CA", {
		timeZone: timezone,
		year: "numeric",
		month: "2-digit",
		day: "2-digit",
		hour: "2-digit",
		minute: "2-digit",
		hourCycle: "h23"
	});
	var rawParts = formatter.formatToParts(new Date(timestamp * 1000));
	var parts = {};
	rawParts.forEach(function (part) {
		if (part.type !== "literal") {
			parts[part.type] = part.value;
		}
	});
	return {
		year: parts.year,
		month: parts.month,
		day: parts.day,
		hour: parts.hour,
		minute: parts.minute
	};
}

function datetime_strings_to_timestamp_in_timezone(dateText, timeText, timezone) {
	var dateParts = extract_date_parts_by_format(dateText, get_server_date_format());
	var timeParts = parse_time_string_by_format(timeText, get_server_time_format());

	if (!dateParts || !timeParts) {
		return "";
	}

	var year = parseInt(dateParts.Y, 10);
	var month = parseInt(dateParts.m || dateParts.n, 10);
	var day = parseInt(dateParts.d || dateParts.j, 10);
	var hour = parseInt(timeParts.hour, 10);
	var minute = parseInt(timeParts.minute, 10);

	if ([year, month, day, hour, minute].some(function (value) {
			return Number.isNaN(value);
		})) {
		return "";
	}

	var guessMs = Date.UTC(year, month - 1, day, hour, minute, 0);
	for (var i = 0; i < 2; i++) {
		var actualParts = get_datetime_parts_in_timezone(Math.floor(guessMs / 1000), timezone);
		var desiredMs = Date.UTC(year, month - 1, day, hour, minute, 0);
		var actualMs = Date.UTC(parseInt(actualParts.year, 10), parseInt(actualParts.month, 10) - 1, parseInt(actualParts.day, 10), parseInt(actualParts.hour, 10), parseInt(actualParts.minute, 10), 0);
		guessMs += desiredMs - actualMs;
	}

	return Math.floor(guessMs / 1000);
}

function appcon(url, fd, nextfunction) {

	// 同期処理のため
	var dfd = $.Deferred();

	// chat用loading
	$("#loading").show();
	$(".class_style_ui_chat #msg").val("");
	;
	fd.append("_call_from", "appcon");

	// Server timezone
	fd.append("_timezone", get_server_timezone());

	if (url == ".php") {
		alert("appcon: URLが不正です:" + url);
		return;
	}

	// debug_window
	var debugarr = [];
	const priorityKeys = ["class", "function"];
	priorityKeys.forEach(k => {
		if (fd.has(k)) {
			debugarr[k] = fd.get(k);
		}
	});
	fd.forEach((value, key) => {
		if (key === "class" || key === "function")
			return;
		debugarr[key] = value;
	});
	append_debug_window("POST ----> Server", debugarr, "table");

	// Fade
	var fadeflg = true;
	fadeflg = !fd.has("max");

	$("#download_view").show();
	$('#download_message').html("Sending data...");
	$('#download_progress').css({'width': 5 + '%'});

	set_windowID();

	// 送信
	$.ajax({
		async: true,
		url: url,
		type: 'POST',
		dataType: 'html',
		data: fd,
		processData: false,
		contentType: false,
		xhr: function () {
			var XHR = $.ajaxSettings.xhr();
			//進行状況表示
			if (XHR.upload) {
				XHR.upload.addEventListener('progress', function (e) {
					if (e.total > 0) {
						var load = (100 * e.loaded / e.total | 0) + 10;
						$('#download_message').html("uploading...   " + Math.round(e.loaded / 1000) + " / " + Math.round(e.total / 1000) + "kbyte");
						$('#download_progress').css({'width': load + '%'});

						if (load >= 100) {
							$('#download_message').html("Waiting for the server response...   ");
							if (waitTimer == null) {
								waitTimer = setInterval(function () {
									load += 10;
									if (load >= 110) {
										load = 0;
									}
									$('#download_progress').css({'width': load + '%'});
								}, 1000);
							}
						}
					}
				});
			}

			XHR.addEventListener('progress', function (e) {
				clearInterval(waitTimer);
				if (e.total > 0) {
					var load = (100 * e.loaded / e.total | 0) + 10;
					$('#download_message').html("downloading...   " + Math.round(e.loaded / 1000) + " / " + Math.round(e.total / 1000) + "kbyte");
					$('#download_progress').css({'width': load + '%'});
				}
			});

			return XHR;
		},
	}).done(function (data) {

		// chat用loading
		$("#loading").hide();

		clearInterval(waitTimer);
		waitTimer = null;

		$("#download_view").hide();
		$('#download_progress').css({'width': '0%'});

		if (data == "") {
			if (nextfunction) {
				nextfunction(res);
			}
			dfd.resolve();
			return;
		}

		try {
			var res;

			res = JSON.parse(data);

			if (res["error"] != null) {
				notification("", res["error"], 800, 5);
				return;
			}

			// リロード
			if (res["reload"] != null) {
				var href = location.href;
				location.assign(href);
				return;
			}

			// リダイレクト
			if (res["location"] != null) {
				location.assign(res["location"]);
				return;
			}

			if (res["close_all_dialog"] != null) {
				var exception = res["close_all_dialog"]["exception"];
				$("#multi_dialog .multi_dialog").each(function (index, element) {
					if ($(this).data("dialog_name") != exception) {
						$(this).remove();
					}
				});
			}

			if (res["close_second_work_area"] != null) {
				remove_second_work_area();
			}

			var chatform = res['chat_form'];
			for (key in chatform) {
				var htmlstr = chatform[key];
				//console.log(htmlstr);
			}

				// Error Message
				var errormessage = normalizeErrorMessageArray(res['errormessage']);
				if (res["clear_error_message"] == "true" || errormessage.length > 0) {
					var cleared = false;
					var clearScope = normalizeErrorScope(res["clear_error_scope"], "", "");
					if (clearScope !== "") {
						cleared = clearScopedErrorMessages(clearScope);
					}
					if (!cleared && errormessage.length > 0) {
						var scopedCount = 0;
						for (key in errormessage) {
							var scopeForClear = normalizeErrorScope(
									errormessage[key]["scope"],
									errormessage[key]["dialog_id"],
									errormessage[key]["classname"]
									);
							if (scopeForClear !== "" && clearScopedErrorMessages(scopeForClear)) {
								scopedCount++;
							}
						}
						cleared = scopedCount > 0;
					}
					if (!cleared) {
						$(".error_message").html("");
					}
				}
				for (key in errormessage) {
					let message = errormessage[key]["message"];
					let dialog_id = errormessage[key]["dialog_id"];
					let classname = errormessage[key]["classname"];
					let field = errormessage[key]["field"];
					let scope = normalizeErrorScope(errormessage[key]["scope"], dialog_id, classname);

					if (dialog_id == null) {
						dialog_id = "";
					}

					var target = findErrorMessageTarget(scope, dialog_id, field);
					if (target.length === 0) {
						append_debug_window("There is no tag for error_" + field + " to show the error message:" + message);
					} else {
						target.html(message).hide();
						translate();
						target.fadeIn(500, set_multidialog_height(dialog_id));
					}
				}

			var reloadarea = res['reloadarea'];
			for (key in reloadarea) {
				var htmlstr = reloadarea[key];
				if ($(key).prop("tagName") == "TEXTAREA") {
					//テキストエリアはvalueに入れる
					$(key).val(htmlstr);
				} else {
					//その他はinnerHtmlに入れる
					if ($(key).length > 0) {
						$(key).html(htmlstr);
					} else {
						append_debug_window("There is no tags for " + key);
					}
				}
				if (flg_reloadarea_fade) {
					flg_reloadarea_fade = false;
					$(key).css({opacity: '0.8'}).animate({opacity: '1'});
					setTimeout(function () {
						flg_reloadarea_fade = true;
					}, 5000);
				}


				// デフォルトのJSを動かす
				var p = $(key).parents(".getting_dialog_id");
				if (p.length > 0) {
					var dialog_id = "#" + $(p).attr("id");
					multi_dialog_functions["__all__"](dialog_id, true);
					ajax_auto_exe(dialog_id);
					if ($(dialog_id).data("ui-resizable")) {
						set_multidialog_height(dialog_id);
					}
				} else {
					multi_dialog_functions["__all__"]("body", true);
					ajax_auto_exe("body");
				}

				// クラスのJSを動かす
				var dialog_id = "#" + $(key).parents(".getting_dialog_id").attr("id");
				var classname = $(key).parents(".multi_dialog_contents").attr("data-classname");
				if (classname === undefined) {
					classname = $("#page_classname").data("class");
				}
				var func = multi_dialog_functions[classname];
				if (func) {
					func(dialog_id + " ");
				}
			}

			var appendarea = res['appendarea'];
			for (key in appendarea) {
				var htmlstr = appendarea[key];
				$(key).html($(key).html() + htmlstr);
				$(key).css({opacity: '0.5'})
				$(key).animate({opacity: '1'}, 'slow');

				$(key).ready(function () {

					// デフォルトのJSを動かす
					var p = $(key).parents(".multi_dialog");
					if (p.length > 0) {
						var dialog_id = "#" + $(key).parents(".multi_dialog").attr("id");
						multi_dialog_functions["__all__"](dialog_id, true);
					} else {
						multi_dialog_functions["__all__"]("body", true);
					}

					// クラスのJSを動かす
					var dialog_id = "#" + $(key).parents(".multi_dialog").attr("id");
					var classname = $(key).parents(".multi_dialog_contents").attr("data-classname");
					var func = multi_dialog_functions[classname];
					if (func) {
						func(dialog_id + " ");
					}

				});
			}


			if (res["console_log"] != null) {
				console.log("  (Server log)");
				for (var md of res["console_log"]) {
					var color_title = "color:" + md["color"] + ";font-weight:bold;"
					console.log("%c" + "  " + md["log"], color_title);
				}
			}

			if (res["screen_debug_log_captured"] != null) {
				renderScreenDebugLogCaptured(res["screen_debug_log_captured"]);
			}

			// 通知(notification)
			if (res["notifications"] != null) {
				for (var md of res["notifications"]) {
					var html = md["html"];
					var width = md["width"];
					var time = md["time"];
					var classname = res["class"];
					ensureClassStylesheet(classname);

					width = get_dialog_width(width);

					notification(classname, html, width, time);
				}
			}

			// Sidemenu
			if (res["sidemenu"] != null) {
				for (var md of res["sidemenu"]) {
					var html = md["html"];
					var width = md["width"];
					var time = md["time"];
					var from = md["from"];
					var classname = res["class"];
					ensureClassStylesheet(classname);

					sidemenu(classname, html, width, time, from);
				}
			}

			// Close sidemenu
			if (res["close_sidemenu"] == true) {
				if (sidemenu_from == 'right') {
					var document_width = $(document).width();
					$('#sidemenu').removeClass('detect_outside_click').animate({'left': document_width + 'px'}, 200);
				} else {
					var sidemenu_width = $('#sidemenu').width();
					$('#sidemenu').removeClass('detect_outside_click').animate({'left': '-' + sidemenu_width + 'px'}, 200);
				}
			}

			// Close Dialog by ID
			if (res["close_dialog_by_id"] != null) {
				for (var md of res["close_dialog_by_id"]) {
					var dialog_id = md["dialog_id"];
					$(dialog_id).fadeOut().remove();
				}
			}

			// Second work area
			if (res["second_work_area"] != null) {
				for (var md of res["second_work_area"]) {
					var html = md["html"];
					var width = md["width"];
					var screen_debug_key = md["screen_debug_key"] || "";
					var classname = res["class"];

					second_work_area(classname, html, width, screen_debug_key);
				}
			}

			// chat
			if (res["chat"] != null) {
				var currentTime = Date.now();
				var c = 0;
				for (var md of res["chat"]) {

					if (md["type"] == "clear") {
						if (md["chatid"] == "all") {
							$("#chat_history").html("");
						} else {
							$(md["chatid"]).remove();
						}
					} else if (md["type"] == "clear_after") {
						$(md["chatid"]).parent().nextAll('.lang_check_area').remove();
					} else {
						var lang_check_area = document.createElement('div');
						var multi_dialog_tag = document.createElement('div');
						var html = md["html"];

						if (md["overwrite"]) {
							let chatid = md['chatid'];
							$(chatid).remove();
						}

						if (md["type"] == "text") {
							$(multi_dialog_tag).addClass("chat-text");
							$(multi_dialog_tag).addClass("lang");
						} else {
							$(multi_dialog_tag).addClass("chat-html");
						}

						var chat_dialog_id = 'chat-html-' + currentTime.toString() + c;

						$(lang_check_area).addClass("lang_check_area");
						$(lang_check_area).attr("data-classname", res["class"]);
						$(multi_dialog_tag).attr('id', chat_dialog_id);
						$(multi_dialog_tag).attr('data-_chatID', 'chat-html-' + currentTime.toString() + c);
						$(multi_dialog_tag).css("float", md["align"]);
						$(multi_dialog_tag).append(html);
						$(lang_check_area).append(multi_dialog_tag);

						(function (element) {
							setTimeout(() => {
								$(element).hide();
								$("#chat_history").append(element);
								translate();
								multi_dialog_functions["__all__"]("body", true);
								$(element).fadeIn(100);
								// move to the bottom of document
								$('html, body').animate({scrollTop: $(document).height()}, 'slow');
							}, c * 100);
						})(lang_check_area);

						c++;
					}
				}


			}

			if (res["popup"] != null) {
				for (var md of res["popup"]) {
					var html = md["html"];
					var width = md["width"];
					var height = md["height"];
					var classname = res["class"];

					popup(classname, width, height, html);
				}
			}

			// login_node
			if (res["login_node"] != null) {
				var md = res["login_node"];
				var room_name = md["room_name"];
				var group_name = md["group_name"];
				var name = md["name"];
				websocket_login(room_name, group_name, name);
			}

			// send_to_node
			if (res["send_to_node"] != null) {

				if (websocket_logined != 1) {
					console.log("Can't send data to node.You must login to node first.");
				}

				for (var md of res["send_to_node"]) {
					//console.log(md);
					var data = md["data"];
					var room_name = md["room_name"];
					var group_name = md["group_name"];
					var user_id = res["user_id"];
					websocket_send(room_name, group_name, user_id, data);
				}
			}

			// ajax
			if (res["ajax"] != null) {
				for (var md of res["ajax"]) {
					let ajax_classname = md["class"];
					let ajax_function = md["function"];
					let post_arr = JSON.parse(md["post_arr"]);

					let fd = new FormData();
					fd.append("class", ajax_classname);
					fd.append("function", ajax_function);
					fd.append("multi_dialog_zindex", multi_dialog_zindex);
					multi_dialog_zindex++;
					if (post_arr != null) {
						for (let key in post_arr) {
							if (!Object.prototype.hasOwnProperty.call(post_arr, key))
								continue;

							const v = post_arr[key];

							// Array -> key[] で複数 append
							if (Array.isArray(v)) {
								for (let i = 0; i < v.length; i++) {
									fd.append(key + "[]", v[i]);
								}
								continue;
							}

							// null/undefined は空文字にして送る（必要に応じて変更）
							if (v === null || typeof v === "undefined") {
								fd.append(key, "");
								continue;
							}

							// Object -> JSON文字列で送る（配列以外のオブジェクトが来る可能性がある場合）
							if (typeof v === "object") {
								fd.append(key, JSON.stringify(v));
								continue;
							}

							// scalar -> そのまま
							fd.append(key, v);
						}
					}

					// 直接呼ぶとどんどん深くなるのを防ぐ
					setTimeout(function () {
						appcon("app.php", fd);
					}, 1);
				}
			}

			// long polling
			if (res["polling"] != null) {
				setTimeout(function () {
					var md = res["polling"];
					startLongPolling(md);
				}, 1);
			}

			// badge
			if (res["badge"] != null) {
				for (var md of res["badge"]) {
					console.log(md);
					var id = md["id"];
					var val = md["val"];
					$("#" + id).html(val);
					if (val > 0) {
						$("#" + id).show();
					} else {
						$("#" + id).hide();
					}
				}
			}

			// Google Map
			if (res["map"] != null) {
				setTimeout(function () {
					// Delay 1sec
					var tag_id = res["map"]["tag_id"];
					var lat = res["map"]["lat"];
					var lng = res["map"]["lng"];
					var zoom = res["map"]["zoom"];
					var markerData = res["map_marker"];
					draw_google_map(tag_id, lat, lng, zoom, markerData);

				}, 1000);
			}

			// マルチダイアログ
			if (res["multi_dialog"] != null) {

				for (var md of res["multi_dialog"]) {
					var dialog_name = md["dialog_name"];
					var html = md["html"];
					var title = md["title"];
					var width = md["width"];
						var cmd = md["cmd"];
						var classname = res["class"];
						var testserver = md["testserver"];
						var post_arr = md["post_arr"];
						var mdx = md["multi_dialog_zindex"];
						var forcopy = md["forcopy"];
						var fixed_bar = md["fixed_bar"];
						var options = md["options"];
						var screen_debug_key = md["screen_debug_key"] || "";

					if (cmd == "close") {
						classname = md["class"];
						var dialog_id = "#multi_dialog_" + classname + "_" + dialog_name;
						$(dialog_id).remove();
						refresh_multi_dialog_modal_cover();
						continue;

					} else {

							if (mdx == null) {
								mdx = multi_dialog_zindex;
							}
							ensureClassStylesheet(classname);
							multi_dialog(dialog_name, html, title, width, classname, testserver, mdx, fixed_bar, options, fadeflg, forcopy, screen_debug_key);
							multi_dialog_zindex++;
					}
				}
			}

			// タブの追加
			if (res["add_tab"] != null) {
				for (var md of res["add_tab"]) {
					let dialog_id = "#multi_dialog_" + res["class"] + "_" + md["dialog_name"];
					// 同じtabnameのタブがすでにあるかチェックする
					var flg = true;
					$(dialog_id).find(".multi_dialog_tab_area").find(".md_tab").each(function () {
						if ($(this).data("tabname") == md["tabname"]) {
							flg = false;
						}
					});

					// 同じtabnameがなかったら、タブを追加する
					if (flg) {
						let tab = document.createElement('div');
						$(tab).addClass("md_tab");
						$(tab).attr("data-tabname", md["tabname"]);
						$(tab).html(md["title"]);
						$(dialog_id).find(".multi_dialog_tab_area").append(tab);

						if (md["selected"]) {
							$(tab).addClass("md_tab_select");
						}

						// クリックイベントを登録
						let post_arr = md["post_arr"];
						$(tab).on("click", function (e) {

							// タブの選択を全て解除
							$(dialog_id).find(".multi_dialog_tab_area").find(".md_tab").each(function () {
								$(this).removeClass("md_tab_select");
							});

							let fd = new FormData();
							for (let key in post_arr) {
								fd.append(key, post_arr[key]);
							}
							;

							$(tab).addClass("md_tab_select");

							appcon("app.php", fd);
						});
					}


				}
			}

			// メインエリア 
			if (res["work_area"] != null) {

				var md = res["work_area"];
				var dialog_name = md["dialog_name"];
				var html = md["html"];
				var title = md["title"];
				var screen_debug_key = md["screen_debug_key"] || "";
				var classname = res["class"];
				var testserver = md["testserver"];
				var post_arr = md["post_arr"];

				var multi_dialog_tag = document.createElement('div');
				$(multi_dialog_tag).attr("id", "multi_dialog_" + classname + "_" + dialog_name);
				$(multi_dialog_tag).attr("data-classname", classname);
				$(multi_dialog_tag).addClass("lang_check_area");
				$(multi_dialog_tag).addClass("getting_dialog_id");
				var $workTitle = $('<div class="work_area_title lang"></div>');
				$workTitle.append($('<span class="work_area_title_text"></span>').text(title));
				if (screen_debug_key !== "") {
					$workTitle.append($('<p class="ajax-link screen_debug_icon screen_debug_icon_workarea" data-class="screen_debug_log" data-function="capture" title="お問い合わせ用の画面IDを取得"><span class="material-symbols-outlined">screenshot_monitor</span></p>').attr("data-screen_key", screen_debug_key));
				}
				$(multi_dialog_tag).append($workTitle);
				$(multi_dialog_tag).append(html);
				$("#work_area").hide().html("").append(multi_dialog_tag).show();

				$(multi_dialog_tag).ready(function () {
					var dialog_id = "#multi_dialog_" + classname + "_" + dialog_name;

					// デフォルトのJSを動かす
					multi_dialog_functions["__all__"](dialog_id, true);

					// クラスのJSを動かす
					var func = multi_dialog_functions[classname];
					if (func) {
						func(dialog_id + " ");
					}

					// スクロールイベント
						var tag_ajax_auto = $(multi_dialog_tag).find(".ajax-auto");
						$(window).off("scroll.ajax_auto_work_area");
						if (tag_ajax_auto.length > 0) {
							$(window).on("scroll.ajax_auto_work_area", function () {
								ajax_auto_exe(dialog_id);
							});
						}
						ajax_auto_exe(dialog_id);
					});
				}

			if (res["chartjs"] != null) {
				setTimeout(function () {
					for (var md of res["chartjs"]) {
						var tag_id = md["tag_id"];
						var chart = md["chart"];

						// IDが複数の場合も対応
						var elements = document.querySelectorAll('#' + tag_id);
						if (elements.length === 1) {
							// IDが1つの場合、その要素をcanvasに入れる
							var canvas = elements[0];
						} else if (elements.length > 1) {
							// IDが複数の場合、最後の要素をcanvasに入れる
							var canvas = elements[elements.length - 1];
						} else {
							console.error(`Error: Element with id '${tag_id}' not found.`);
						}

						if (canvas) {
							var ctx = canvas.getContext('2d');
							var mychart = new Chart(ctx, chart);
						}
					}
				}, 100);
			}


			if (nextfunction) {
				nextfunction(res);
			}

		} catch (e) {
			append_debug_window(e, data, "error");
			mdx = multi_dialog_zindex;
			html = "<div class=\"error error_window_message\">" + escapeHtml(data) + "</div>";
			multi_dialog("error", html, "ERROR", 600, "error", testserver, mdx);
			multi_dialog_zindex++;

		}

		dfd.resolve();

	}).fail(function ($xhr, textStatus, errorThrown) {

		var data = $xhr.responseText;
		var statusLine = "AJAX request failed";
		if ($xhr && $xhr.status) {
			statusLine += " (" + $xhr.status + " " + ($xhr.statusText || "") + ")";
		}
		if (textStatus) {
			statusLine += " : " + textStatus;
		}
		if (errorThrown) {
			statusLine += " / " + errorThrown;
		}

		append_debug_window(statusLine, data || "", "error");

		var detail = "";
		if (typeof data === "string" && data !== "") {
			detail = escapeHtml(data);
		} else {
			detail = "No response body";
		}
		var html = "<div class=\"error error_window_message\"><div style=\"font-weight:bold;margin-bottom:8px;\">"
				+ escapeHtml(statusLine)
				+ "</div><pre style=\"white-space:pre-wrap;word-break:break-word;max-height:60vh;overflow:auto;\">"
				+ detail
				+ "</pre></div>";
		multi_dialog("error", html, "AJAX ERROR", 700, "error", $("#testserver").html() == "true", multi_dialog_zindex);
		multi_dialog_zindex++;

		dfd.resolve();
	});

	return dfd.promise();
}

/*
 * 通知（Notification)
 */
function notification(classname, html, width, time) {
	var multi_dialog_tag = document.createElement('div');
	$(multi_dialog_tag).addClass("notification");
	$(multi_dialog_tag).addClass("lang_check_area");
	$(multi_dialog_tag).attr("data-classname", classname);
	$(multi_dialog_tag).css("width", width);
	$(multi_dialog_tag).append(html);
	$(multi_dialog_tag).fadeOut(0);
	$("#multi_dialog").append(multi_dialog_tag);

	// z-index
	$(multi_dialog_tag).css("z-index", "9999999999");

	translate();

	$(multi_dialog_tag).fadeIn(200, function () {
		setTimeout(function () {
			$(multi_dialog_tag).fadeOut(200, function () {
				$(this).remove();
			});
		}, time * 1000);
	});
}



function sidemenu(classname, html, width, time, from) {

	// for closing
	sidemenu_from = from;

	$("#sidemenu").remove();

	var multi_dialog_tag = document.createElement('div');
	$(multi_dialog_tag).addClass("sidemenu");
	$(multi_dialog_tag).attr("id", "sidemenu");
	$(multi_dialog_tag).addClass("lang_check_area");
	$(multi_dialog_tag).attr("data-classname", classname);
	$(multi_dialog_tag).css("width", width);
	$(multi_dialog_tag).append(html);
	// $(multi_dialog_tag).fadeOut(0);
	$(multi_dialog_tag).css("z-index", "9999999999");
	$(multi_dialog_tag).css("background", "white");
	$("#multi_dialog").append(multi_dialog_tag);

	translate();

	if (from == 'right') {
		var document_width = $(document).width();
		$(multi_dialog_tag).css("left", document_width + "px");
		$(multi_dialog_tag).addClass('detect_outside_click').animate({'left': document_width - width + "px"}, time);
		$(multi_dialog_tag).css("box-shadow", "-5px 0px 5px #CCC");
	} else {
		$(multi_dialog_tag).css("left", "-" + width + "px");
		$(multi_dialog_tag).addClass('detect_outside_click').animate({'left': '0'}, time);
		$(multi_dialog_tag).css("box-shadow", "5px 0px 5px #CCC");
	}

}

var flg_second_work_area = 0;
var flg_second_work_area_hidden = 0;
var second_work_area_scroll_restore_top = null;

function bring_second_work_area_to_front() {
	var baseZ = multi_dialog_zindex;
	$("#work_area_second").css("z-index", baseZ);
	$("#work_area_second_show_button").css("z-index", baseZ + 1);
	multi_dialog_zindex += 2;
}

function adjust_second_work_area_layout() {
	if (flg_second_work_area !== 1) {
		return;
	}
	var panelMargin = 10;
	var windowWidth = $(window).width();
	var requestedWidth = parseInt($("#work_area_second").attr("data-panel-width"), 10) || 400;
	var panelWidth = Math.max(260, Math.min(requestedWidth, windowWidth - 20));
	var rightPosition = flg_second_work_area_hidden === 1 ? -1 * (panelWidth + 12) : panelMargin;

	$("#work_area_second").css({
		"top": panelMargin,
		"bottom": panelMargin,
		"width": panelWidth,
		"right": rightPosition
	});
}

$(window).resize(function () {
	adjust_second_work_area_layout();
});

function second_work_area(classname, html, w, screen_debug_key) {
	var wasAlreadyOpen = (flg_second_work_area === 1 && flg_second_work_area_hidden === 0 && $("#work_area_second").is(":visible"));
	flg_second_work_area = 1;
	flg_second_work_area_hidden = 0;
	bring_second_work_area_to_front();
	$("#work_area_second").attr("data-panel-width", w);
	$("#work_area_second").addClass("work_area_second_active");
	$("#work_area_second").html("");

	var action_bar = document.createElement("div");
	$(action_bar).addClass("work_area_second_action_bar");
	var hide_button = document.createElement("div");
	$(hide_button).addClass("work_area_second_action_button");
	$(hide_button).append('<span class="material-symbols-outlined">chevron_right</span>');
	var close_button = document.createElement("div");
	$(close_button).addClass("work_area_second_action_button");
	$(close_button).append('<span class="material-symbols-outlined">close</span>');
	if (String(screen_debug_key || "").trim() !== "") {
		var $screenDebugButton = $('<p class="ajax-link screen_debug_icon screen_debug_icon_workarea" data-class="screen_debug_log" data-function="capture" title="お問い合わせ用の画面IDを取得"><span class="material-symbols-outlined">screenshot_monitor</span></p>');
		$screenDebugButton.attr("data-screen_key", screen_debug_key);
		$(action_bar).append($screenDebugButton);
	}
	$(action_bar).append(hide_button).append(close_button);

	var body = document.createElement("div");
	$(body).addClass("work_area_second_body");
	var multi_dialog_tag = document.createElement("div");
	$(multi_dialog_tag).addClass("lang_check_area");
	$(multi_dialog_tag).attr("data-classname", classname);
	$(multi_dialog_tag).addClass("getting_dialog_id");
	$(multi_dialog_tag).html(html);
	$(body).append(multi_dialog_tag);

	$("#work_area_second").append(action_bar).append(body);

	if ($("#work_area_second_show_button").length === 0) {
		$("body").append('<div id="work_area_second_show_button"><span class="material-symbols-outlined">chevron_left</span></div>');
	}

	$(hide_button).off("click").on("click", function () {
		flg_second_work_area_hidden = 1;
		var panelWidth = $("#work_area_second").outerWidth() || 0;
		$("#work_area_second").stop(true, true).animate({"right": -1 * (panelWidth + 12)}, 260, "swing");
		$("#work_area_second_show_button").stop(true, true).fadeIn(180);
	});
		$(close_button).off("click").on("click", function () {
			var fd = new FormData();
			fd.append("class", "db_exe");
			fd.append("function", "close_second_work_area");
			appcon("app.php", fd);
	});
	$("#work_area_second_show_button").off("click").on("click", function () {
		flg_second_work_area_hidden = 0;
		bring_second_work_area_to_front();
		$(this).stop(true, true).fadeOut(120);
		$("#work_area_second").stop(true, true).animate({"right": "10px"}, 260, "swing");
	});

	adjust_second_work_area_layout();
	var panelWidth = $("#work_area_second").outerWidth() || 0;
	if (wasAlreadyOpen) {
		$("#work_area_second").show().stop(true, true).css("right", "10px");
	} else {
		$("#work_area_second").show().css("right", -1 * (panelWidth + 12)).stop(true, true).animate({"right": "10px"}, 280, "swing");
	}

	var dialog_id = "#work_area_second";
	translate();

	// デフォルトのJSを動かす
	multi_dialog_functions["__all__"](dialog_id, true);

	// クラスのJSを動かす
	var func = multi_dialog_functions[classname];
	if (func) {
		func(dialog_id + " ");
	}

	// ajax-auto でサイドパネルを再描画した際はスクロール位置を復元する
	if (second_work_area_scroll_restore_top !== null) {
		var restoreTop = second_work_area_scroll_restore_top;
		second_work_area_scroll_restore_top = null;
		setTimeout(function () {
			$("#work_area_second .work_area_second_body").scrollTop(restoreTop);
		}, 0);
	}
}


function remove_second_work_area() {
	flg_second_work_area = 0;
	flg_second_work_area_hidden = 0;
	second_work_area_scroll_restore_top = null;
	$("#work_area_second .work_area_second_body").off("scroll.ajax_auto");
	$("#work_area_second").removeClass("work_area_second_active");
	$("#work_area_second").removeAttr("style").html("").hide();
	$("#work_area_second_show_button").remove();
	$(".active_indicator").removeClass("indicator_active");
}


function popup(classname, width, height, html) {


	$('#popup').remove();

	width = get_dialog_width(width);

	var multi_dialog_tag = document.createElement('div');
	$(multi_dialog_tag).addClass("popup");
	$(multi_dialog_tag).attr("id", "popup");
	$(multi_dialog_tag).addClass("lang_check_area");
	$(multi_dialog_tag).attr("data-classname", classname);
	$(multi_dialog_tag).css("width", width);
	$(multi_dialog_tag).css("height", height);
	$(multi_dialog_tag).append(html);
	// $(multi_dialog_tag).fadeOut(0);
	$(multi_dialog_tag).css("z-index", "9999999999");
	$(multi_dialog_tag).css("background", "white");
	$("#multi_dialog").append(multi_dialog_tag);

	translate();
	$('#popup').addClass('detect_outside_click').css('opacity', '1').css('display', 'block');

}



/*
 * マルチダイアログ処理
 */
var multi_dialog_zindex = 100;
var multi_dialog_reflesh_time = Math.floor(Date.now() / 1000);

function ensure_multi_dialog_modal_cover() {
	if ($("#multi_dialog_modal_cover").length === 0) {
		var cover = document.createElement("div");
		$(cover).attr("id", "multi_dialog_modal_cover");
		$(cover).css({
			"display": "none",
			"position": "fixed",
			"left": "0",
			"top": "0",
			"width": "100vw",
			"height": "100vh",
			"background": "rgba(90,90,90,0.45)"
		});
		$("body").append(cover);
	}
}

function refresh_multi_dialog_modal_cover() {
	ensure_multi_dialog_modal_cover();
	var modalDialogs = $("#multi_dialog .multi_dialog[data-modal_flg='1']");
	if (modalDialogs.length === 0) {
		$("#multi_dialog_modal_cover").hide();
		return;
	}
	var highest = 0;
	modalDialogs.each(function () {
		var zi = parseInt($(this).css("z-index"), 10);
		if (isNaN(zi)) {
			zi = 0;
		}
		if (zi > highest) {
			highest = zi;
		}
	});
	if (highest < 100) {
		highest = 100;
	}
	$("#multi_dialog_modal_cover").css("z-index", highest - 1).show();
	modalDialogs.each(function () {
		var zi = parseInt($(this).css("z-index"), 10);
		if (isNaN(zi) || zi <= highest - 1) {
			$(this).css("z-index", highest + 1);
		}
	});
}

function multi_dialog(dialog_name, contents, title, width, getclassname, testserver, mdz, fixed_bar = "", options = [], fadeflg = true, forcopy = "", screen_debug_key = "") {


	var exe_classname = getclassname;
	var dialog_id = "#multi_dialog_" + exe_classname + "_" + dialog_name;
	var is_modal = false;
	if (options != null) {
		if (options["modal_flg"] == 1 || options["modal_flg"] === true || options["modal"] == 1 || options["modal"] === true) {
			is_modal = true;
		}
	}

	var multi_dialog_tag = null;

	var title_display = '<span class="lang">' + title + '</span>';

	function syncCopyButton($dlg, ts, forcopyValue) {
		var tsFlg = (ts === true || ts === 1 || String(ts).toLowerCase() === "true");
		var $titleArea = $dlg.find(".multi_dialog_title_area").first();
		if ($titleArea.length === 0) {
			return;
		}
		var value = String(forcopyValue || "").trim();
		$titleArea.attr("data-forcopy", value);
		var $copy = $titleArea.find(".multi_dialog_copy_title").first();
		if (!tsFlg || value === "") {
			$copy.remove();
			return;
		}
		if ($copy.length === 0) {
			$copy = $('<span class="multi_dialog_copy_title" title="Copy class/function">⧉</span>');
			var $close = $titleArea.find(".multi_dialog_close").first();
			if ($close.length > 0) {
				$close.before($copy);
			} else {
				$titleArea.append($copy);
			}
		}
		$copy.attr("data-copy-value", value);
	}

	function syncScreenDebugButton($dlg, screenKey) {
		var $titleArea = $dlg.find(".multi_dialog_title_area").first();
		if ($titleArea.length === 0) {
			return;
		}
		var key = String(screenKey || "").trim();
		var $icon = $titleArea.find(".screen_debug_icon").first();
		if (key === "") {
			$icon.remove();
			return;
		}
		if ($icon.length === 0) {
			$icon = $('<p class="ajax-link screen_debug_icon" data-class="screen_debug_log" data-function="capture" title="お問い合わせ用の画面IDを取得"><span class="material-symbols-outlined">screenshot_monitor</span></p>');
			var $close = $titleArea.find(".multi_dialog_close").first();
			if ($close.length > 0) {
				$close.after($icon);
			} else {
				$titleArea.append($icon);
			}
		}
		$icon.attr("data-screen_key", key);
		$icon.data("screen_key", key);
	}

	if ($(dialog_id).length) {

		// 一旦非表示(更新してから３以内は非表示にしない)
		var tmp_now = Math.floor(Date.now() / 1000);
		if (fadeflg) {
			if (tmp_now > (multi_dialog_reflesh_time + 3)) {
				$(dialog_id + " .multi_dialog_contents").fadeOut({duration: 0, });
				multi_dialog_reflesh_time = tmp_now;
			}
		}

		// コンテンツを入れかえる
		$(dialog_id + " .multi_dialog_contents").html(contents);

		// FIXED BARを入れる
		$(dialog_id + " .multi_dialog_fixed_bar").html(fixed_bar);

		// タイトルを入れる
		if (title != "") {
			var $innerTitle = $(dialog_id + " .multi_dialog_innder_title");
			if ($innerTitle.length > 0) {
				$innerTitle.html(title_display);
				$innerTitle.show();
			} else {
				var $titleArea = $(dialog_id + " .multi_dialog_title_area").first();
				if ($titleArea.length > 0) {
					var $closeButton = $titleArea.find(".multi_dialog_close").first();
					var $copyButton = $titleArea.find(".multi_dialog_copy_title").first();
					var $screenDebugButton = $titleArea.find(".screen_debug_icon").first();
					$titleArea.contents().not($closeButton).not($copyButton).not($screenDebugButton).remove();
					$titleArea.prepend(title_display);
				}
			}
		} else {
			var $innerTitleHide = $(dialog_id + " .multi_dialog_innder_title");
			if ($innerTitleHide.length > 0) {
				$innerTitleHide.hide();
			} else {
				var $titleAreaHide = $(dialog_id + " .multi_dialog_title_area").first();
				if ($titleAreaHide.length > 0) {
					var $closeButtonHide = $titleAreaHide.find(".multi_dialog_close").first();
					var $copyButtonHide = $titleAreaHide.find(".multi_dialog_copy_title").first();
					var $screenDebugButtonHide = $titleAreaHide.find(".screen_debug_icon").first();
					$titleAreaHide.contents().not($closeButtonHide).not($copyButtonHide).not($screenDebugButtonHide).remove();
				}
			}
		}
		syncCopyButton($(dialog_id), testserver, forcopy);
		syncScreenDebugButton($(dialog_id), screen_debug_key);

		// z-indexの設定
		$(dialog_id).css("z-index", mdz);
		$(dialog_id).attr("data-modal_flg", is_modal ? "1" : "0");

		$(contents).ready(function () {

			if (fadeflg) {
				$(dialog_id + " .multi_dialog_contents").fadeIn({duration: 500});
			}

			// デフォルトのJSを動かす
			multi_dialog_functions["__all__"](dialog_id);

			// クラスのJSを動かす
			var func = multi_dialog_functions[exe_classname];
			if (func) {
				func(dialog_id + " ");
			}

			// 高さを修正
			set_multidialog_height(dialog_id);
			refresh_multi_dialog_modal_cover();

		});

		return;

	} else {
		//-----------------------
		// 新しいウィンドウを開く
		//-----------------------

		// ダイアログ全体
		multi_dialog_tag = document.createElement('div');
		$(multi_dialog_tag).attr("id", "multi_dialog_" + exe_classname + "_" + dialog_name);
		$(multi_dialog_tag).attr("data-dialog_name", dialog_name);
		$(multi_dialog_tag).attr("data-classname", exe_classname);
		$(multi_dialog_tag).addClass("multi_dialog");
		$(multi_dialog_tag).addClass("getting_dialog_id");
		$(multi_dialog_tag).attr("data-modal_flg", is_modal ? "1" : "0");

		// タイトル部分
		var dialog_html = '<div class="multi_dialog_title_area lang_check_area" data-classname="' + exe_classname + '" data-forcopy="">' + title_display + '<div class="multi_dialog_close">X</div></div>';

		// タブ
		var tab_area = document.createElement('div');
		$(tab_area).addClass("multi_dialog_tab_area");
		$(tab_area).addClass("lang_check_area");
		$(tab_area).attr("data-classname", exe_classname);

		// 固定バー
		var fixed_bar_tag = $('<div class="multi_dialog_fixed_bar lang_check_area class_style_' + exe_classname + '"></div>');
		fixed_bar_tag.attr("data-classname", exe_classname);
		fixed_bar_tag.html(fixed_bar);

		// タブのコンテンツのコンテナ
		var container = document.createElement('div');
		$(container).addClass("tab_container");

		// ダイアログに入れていく
		$(multi_dialog_tag).append(dialog_html);
		$(multi_dialog_tag).append(tab_area);
		//$(multi_dialog_tag).append(fixed_bar_tag);
		$(multi_dialog_tag).append(container);

		$(container).fadeOut({duration: 0, });

		// HTMLに入れる
		$("#multi_dialog").append(multi_dialog_tag);
		syncScreenDebugButton($(multi_dialog_tag), screen_debug_key);

		// Draggable
		$(multi_dialog_tag).draggable({
			handle: ".multi_dialog_title_area",
			start: function () {
				$(this).css("z-index", multi_dialog_zindex);
				multi_dialog_zindex++;
			},
			stop: function () {
				var st = $(window).scrollTop();
				if ($(this).offset().top < st) {
					$(this).offset({top: st});
				}
				if ($(this).offset().top > st + $(window).height()) {
					$(this).offset({top: st + $(window).height() - 100});
				}
			}
		});

		// Resizable
		$(multi_dialog_tag).resizable();

		// 拡大アイコンを消す
		$(multi_dialog_tag).find(".ui-resizable-handle").removeClass("ui-icon");

		// Click
		$(multi_dialog_tag).on("click", function (e) {
			$(dialog_id).css("z-index", multi_dialog_zindex);
			multi_dialog_zindex++;
		});

		// クローズイベント
		$(multi_dialog_tag).on("click", ".multi_dialog_close", function (e) {
			$(multi_dialog_tag).resizable("destroy");
			$(multi_dialog_tag).draggable("destroy");
			$(multi_dialog_tag).off("click", ".multi_dialog_close");
			$(multi_dialog_tag).remove();
			refresh_multi_dialog_modal_cover();
		});

		// class/function のコピー
		$(multi_dialog_tag).on("click", ".multi_dialog_copy_title", function (e) {
			e.preventDefault();
			e.stopPropagation();
			var value = String($(this).attr("data-copy-value") || "").trim();
			if (value === "") {
				return;
			}
			var done = function () {
				$(this).addClass("copied");
				setTimeout(() => {
					$(this).removeClass("copied");
				}, 700);
			}.bind(this);
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(value).then(done).catch(function () {
					var ta = document.createElement("textarea");
					ta.value = value;
					ta.style.position = "fixed";
					ta.style.left = "-9999px";
					document.body.appendChild(ta);
					ta.focus();
					ta.select();
					try {
						document.execCommand("copy");
						done();
					} catch (err) {
						// noop
					}
					document.body.removeChild(ta);
				});
				return;
			}
			var ta = document.createElement("textarea");
			ta.value = value;
			ta.style.position = "fixed";
			ta.style.left = "-9999px";
			document.body.appendChild(ta);
			ta.focus();
			ta.select();
			try {
				document.execCommand("copy");
				done();
			} catch (err2) {
				// noop
			}
			document.body.removeChild(ta);
		});

		// ウィンドウのサイズ
		var dialog_window_size = get_dialog_width(width);
		$(multi_dialog_tag).css("width", dialog_window_size);

		var windowwidth = $("body").width();
		//$(multi_dialog_tag).css("top", 120 + Math.random() * 20);
		$(multi_dialog_tag).css("top", 60 + Math.random() * 5);
		$(multi_dialog_tag).css("left", (windowwidth - dialog_window_size) / 2);

		// スクロールとコンテンツを入れる
		var scroll_tag = document.createElement("div");
		var contents_tag = document.createElement("div");
		$(contents_tag).addClass("multi_dialog_contents");
		$(contents_tag).addClass("lang_check_area");
		$(contents_tag).attr("data-classname", exe_classname);
		$(contents_tag).html(contents);
		$(scroll_tag).append(fixed_bar_tag);
		$(scroll_tag).append(contents_tag);
		$(scroll_tag).addClass("multi_dialog_scroll");
		$(container).append(scroll_tag);

		// 表示
		$(container).fadeIn({duration: 200});

		// z-index
		$(dialog_id).css("z-index", multi_dialog_zindex);
		multi_dialog_zindex++;

			$(scroll_tag).ready(function () {
				syncCopyButton($(dialog_id), testserver, forcopy);
				// デフォルトのJSを動かす
			multi_dialog_functions["__all__"](dialog_id + " ");

			// クラスのJSを動かす
			var func = multi_dialog_functions[exe_classname];
			if (func) {
				func(dialog_id + " ");
			}

			// 高さの設定
			set_multidialog_height(dialog_id);
			refresh_multi_dialog_modal_cover();

		});

}
}



function set_multidialog_height(dialog_id) {
	if (!$(dialog_id).data("ui-resizable")) {
		$(dialog_id).resizable();
	}
	$(dialog_id).resizable("disable");

	// いまのスクロール位置を保存
	const $dlg = $(dialog_id);
	const $sc = $dlg.find('.multi_dialog_scroll');
	const winScroll = $(window).scrollTop();
	const innerScroll = $sc.length ? $sc.scrollTop() : 0;

	setTimeout(function () {
		// 一度autoにする（レイアウト再計算）
		$dlg.css("height", "auto");

		const height_dialog = $dlg.outerHeight();
		const windowHeight = window.innerHeight;
		const dialogTop = parseInt($dlg.css("top"), 10) || 0;
		const maxHeight = (windowHeight - dialogTop) * 0.9;

		$dlg.css("max-height", maxHeight);
		$sc.css("max-height", maxHeight - 30);

		if (height_dialog > maxHeight) {
			$dlg.height(maxHeight);
		}

		// ★スクロール位置を復元（ジャンプ防止）
		if ($sc.length)
			$sc.scrollTop(innerScroll);
		$(window).scrollTop(winScroll);

		ajax_auto_exe(dialog_id);
		$(dialog_id).resizable("enable");
	}, 500);
}


// ダウンロードリンク
$("body").on("click", ".download-link", function (e) {

	e.preventDefault();

	var form = $(this).data("form");
	if (form === undefined) {
		var fc = $(this).closest("form");
		if (fc !== undefined) {
			var f = fc.get(0);
			// 日付を数値ににしてFormDataを取得
			var fd = get_formdata_with_strtotime(f);
		} else {
			var fd = new FormData();
		}
	} else {
		var f = $("#" + form).get(0);
		// 日付を数値ににしてFormDataを取得
		var fd = get_formdata_with_strtotime(f);
	}

	var v = ($(this).attr('data-open_new_tab') || '').toString().toLowerCase();
	var open_new_tab = (v === 'true' || v === '1' || v === 'yes');
	fd.append("_download_mode", open_new_tab ? "open_new_tab" : "xhr");

	var url = $(this).data("url");
	if (url == undefined) {
		url = "app.php";
	}
	// data-filename は jQuery.data() のキャッシュ状況に依存せず属性値を優先して取得する
	var filename = $(this).attr("data-filename");
	if (filename === undefined || filename === null || filename === "") {
		filename = $(this).data("filename");
	}
	if (filename === undefined || filename === null) {
		filename = "";
	} else {
		filename = String(filename);
	}
	var datalist = $(this).data();
	for (key in datalist) {
		fd.append(key, datalist[key]);
	}
	
	// CHANGE: invoke-class / invoke-function を fd に反映
	var invokeClass = $(this).attr("invoke-class");
	if (invokeClass !== undefined && invokeClass !== "") {
	  fd.append("class", invokeClass);
	}

	var invokeFunction = $(this).attr("invoke-function");
	if (invokeFunction !== undefined && invokeFunction !== "") {
	  fd.append("function", invokeFunction);
	}

	// Server timezone
	fd.append("_timezone", get_server_timezone());

	// open_new_tab は blob URL を使わず、POSTをそのまま新規タブへ送る
	// （PDFビューア保存時のUUID名化を回避）
	if (open_new_tab) {
		var posted = submit_download_in_new_tab(url, fd);
		if (posted) {
			return;
		}
	}

	// 同期処理
	$.when(
			modal_download(url, fd, filename, open_new_tab)
			).done(function (data) {
	});
});


//--------------------------------------------
// プラグイン用データ送信＆ダウンロード
//--------------------------------------------
function modal_download(url, fd, fileName, open_new_tab = false) { // CHANGE: 引数追加

	var xhr = new XMLHttpRequest();
	xhr.open("POST", url, true);

	xhr.onprogress = function (evt) {
		$("#download_view").show();
		if (evt.total > 0) {
			var load = 100 * evt.loaded / evt.total;
			$('#download_message').html(
					"Downloading...   " +
					Math.round(evt.loaded / 1000) + " / " +
					Math.round(evt.total / 1000) + "kbyte"
					);
			$('#download_progress').css({'width': load + '%'});
		} else {
			$('#download_message').html("");
			$('#download_progress').html(Math.round(evt.loaded / 1000) + " kbyte");
			$('#download_progress').css({'width': '100%'});
		}
	};

	xhr.responseType = 'arraybuffer';

	xhr.onload = function () {
		function decodeDownloadErrorPayload() {
			var fallbackTitle = xhr.getResponseHeader("X-FBP-Download-Error-Title") || "Download Error";
			try {
				fallbackTitle = decodeURIComponent(fallbackTitle);
			} catch (e) {
			}

			if (!xhr.response) {
				return {title: fallbackTitle, message: "Download failed."};
			}

			var text = "";
			try {
				text = new TextDecoder("utf-8", {fatal: false}).decode(new Uint8Array(xhr.response));
			} catch (e) {
				text = "";
			}

			if (text !== "") {
				try {
					var parsed = JSON.parse(text);
					return {
						title: parsed.title || fallbackTitle,
						message: parsed.message || text
					};
				} catch (e) {
				}
			}

			return {title: fallbackTitle, message: text || "Download failed."};
		}

		function showDownloadErrorDialog(title, message) {
			var dialogTitle = title || "Download Error";
			var dialogMessage = message || "Download failed.";
			var html = "<div class=\"error download_error_message\"><div style=\"font-weight:bold;margin-bottom:8px;\">"
					+ escapeHtml(dialogTitle)
					+ "</div><div style=\"white-space:pre-wrap;word-break:break-word;\">"
					+ escapeHtml(dialogMessage)
					+ "</div></div>";
			multi_dialog("error", html, dialogTitle, 600, "error", $("#testserver").html() == "true", multi_dialog_zindex);
			multi_dialog_zindex++;
		}

		var hasDownloadError = xhr.status >= 400 || (xhr.getResponseHeader("X-FBP-Download-Error") || "") === "1";
		if (hasDownloadError) {
			var errorPayload = decodeDownloadErrorPayload();
			showDownloadErrorDialog(errorPayload.title, errorPayload.message);
			$("#download_view").hide();
			$('#download_progress').css({'width': '0%'});
			return;
		}

		var bytes = new Uint8Array(this.response);
		var resolvedFileName = (function () {
			if (fileName !== undefined && fileName !== null && String(fileName) !== "") {
				return String(fileName);
			}
			var cd = xhr.getResponseHeader("Content-Disposition") || "";
			if (cd !== "") {
				var mUtf8 = cd.match(/filename\*=UTF-8''([^;]+)/i);
				if (mUtf8 && mUtf8[1]) {
					try {
						return decodeURIComponent(mUtf8[1].trim());
					} catch (e) {
					}
				}
				var mQuoted = cd.match(/filename=\"([^\"]+)\"/i);
				if (mQuoted && mQuoted[1]) {
					return mQuoted[1].trim();
				}
				var mPlain = cd.match(/filename=([^;]+)/i);
				if (mPlain && mPlain[1]) {
					return mPlain[1].trim().replace(/^\"|\"$/g, "");
				}
			}
			return "download";
		})();

		// CHANGE: 取得した先頭200KBを console.log に出力（1回のみ）
		(function logFirst300() {
			var MAX = 300;
			var len = Math.min(bytes.length, MAX);
			var head = bytes.subarray(0, len);

			// バイナリとして確認
			console.log('[download head bytes]', head);

			// テキストとして確認（デバッグ用）
			try {
				var text = new TextDecoder('utf-8', {fatal: false}).decode(head);
				console.log('[download head text]', text);
			} catch (e) {
				console.log('[download head text decode error]', e);
			}
		})();

		var lower = (resolvedFileName || '').toLowerCase();
		var isPdf = lower.slice(-4) === '.pdf';
		var mime = isPdf ? 'application/pdf' : 'application/octet-stream';
		var blob = new Blob([bytes], {type: mime});
		var blobSource = blob;
		try {
			// open_new_tab でも保存名を引き継ぎやすいよう File(name付き) を優先
			blobSource = new File([bytes], resolvedFileName, {type: mime});
		} catch (e) {
			blobSource = blob;
		}

		// IE系は従来通り
		if (userAgent.indexOf('msie') != -1) {
			window.navigator.msSaveBlob(blob, resolvedFileName);
			$("#download_view").hide();
			$('#download_progress').css({'width': '0%'});
			return;
		}

		var objUrl = (window.URL || window.webkitURL).createObjectURL(blobSource);

		// CHANGE: ここから「必ず一度だけ」実行
		var opened = false;

		if (open_new_tab) {
			// 新しいタブで開く
			var w = null;
			try {
				w = window.open(objUrl, '_blank', 'noopener');
			} catch (e) {
				w = null;
			}

			if (w && typeof w.closed !== 'undefined') {
				opened = true;
			}

			// 表示完了を待ってから解放
			setTimeout(function () {
				(window.URL || window.webkitURL).revokeObjectURL(objUrl);
			}, 60 * 1000);

		} else {
			// ダウンロード
			var a = document.createElement('a');
			a.download = resolvedFileName;
			a.href = objUrl;
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);

			setTimeout(function () {
				(window.URL || window.webkitURL).revokeObjectURL(objUrl);
			}, 10 * 1000);
		}

		$("#download_view").hide();
		$('#download_progress').css({'width': '0%'});
	};

	xhr.send(fd);
	return false;
}

function submit_download_in_new_tab(url, fd) {
	if (!(fd instanceof FormData)) {
		return false;
	}

	var tempForm = document.createElement("form");
	tempForm.method = "POST";
	tempForm.action = url;
	tempForm.target = "_blank";
	tempForm.style.display = "none";

	var hasBinary = false;
	fd.forEach(function (value, key) {
		if ((typeof File !== "undefined" && value instanceof File)
				|| (typeof Blob !== "undefined" && value instanceof Blob)) {
			hasBinary = true;
			return;
		}
		var input = document.createElement("input");
		input.type = "hidden";
		input.name = key;
		input.value = value == null ? "" : String(value);
		tempForm.appendChild(input);
	});

	// バイナリを含む場合は従来のXHR経由にフォールバック
	if (hasBinary) {
		return false;
	}

	document.body.appendChild(tempForm);
	tempForm.submit();
	document.body.removeChild(tempForm);
	return true;
}


//$(function () {
//	$('form').attr('autocomplete', 'off');
//});


//----------------------------
// テキストボックスに３桁表示
//----------------------------
(function ($) {
	$.fn.add_number_format = function () {
		return this.each(function () {
			// Do something to each element here.
			$(this).wrap('<div class="display_number_area"></div>');
			$(this).before('<div class="display_number"></div>');
			$(this).css("text-align", "right");
			var data = $(this).val();
			if (data != "") {
				var sanketa = String(data).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, '$1,');

			} else {
				var sanketa = 0;
			}
			var dn = $(this).parent().find(".display_number");
			dn.html(sanketa);

			$(this).on("keyup", function (e) {
				var data = $(this).val();
				if (data != "") {
					var sanketa = String(data).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, '$1,');

				} else {
					var sanketa = 0;
				}
				var dn = $(this).parent().find(".display_number");
				dn.html(sanketa);
			});
		});
	};

})(jQuery);


//エンターキーでフォーム送信を無効
$(function () {
	$("body").on("keydown", "input", function (e) {
		if ((e.which && e.which === 13) || (e.keyCode && e.keyCode === 13)) {
			return false;
		} else {
			return true;
		}
	});
});

//URLパラメーターを取得
function getURLParam(name) {
	var url = window.location.href;
	name = name.replace(/[\[\]]/g, "\\$&");
	var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
			results = regex.exec(url);
	if (!results)
		return null;
	if (!results[2])
		return '';
	return decodeURIComponent(results[2].replace(/\+/g, " "));
}

//----------------------------
// テキストボックスに文字数表示
//----------------------------
(function ($) {
	$.fn.text_size_limit = function (options) {
		return this.each(function () {

			if ($(this).hasClass("wordcounter")) {

				$(this).removeClass("wordcounter");
				if (!$(this).parent().hasClass("wordcounter_area")) {
					$(this).wrap('<div class="wordcounter_area"></div>');
				}
				$(this).addClass("wordcounter_textarea");
				var $area = $(this).parent(".wordcounter_area");
				$area.find(".wordcounter_display").remove();
				$area.append('<div class="display_number wordcounter_display"></div>');

				var f = function (obj) {
					var max = $(obj).data("counter_max");
					if (max == null) {
						max = $(obj).data("max");
						if (max == null) {
							append_debug_window("Wordcounter needs data-counter_max parameter in tag", null, "error");
						}
					}

					var count = get_utf8_bytes($(obj).val());
					count = max - count;
					var $display = $(obj).parent(".wordcounter_area").find(".wordcounter_display");
					$display.text(count + " bytes");
					if (count <= 0) {
						$display.css("color", "red");
						$display.text("The number of characters is over.");
					} else {
						$display.css("color", "#bbbbbb");
						$display.text(count + " bytes");
					}
				}

				// １回実行
				f(this);

				$(this).on("keyup", function (e) {
					f(this);
				});
			}
		});
	};
})(jQuery);

//----------------------------
// UTFのバイト数を計算
//----------------------------
function get_utf8_bytes(str) {
	var count = 0;
	for (var i = 0; i < str.length; ++i) {
		var cp = str.charCodeAt(i);
		if (cp <= 0x007F) {
			// U+0000 - U+007F
			count += 1;
		} else if (cp <= 0x07FF) {
			// U+0080 - U+07FF
			count += 2;
		} else if (cp <= 0xD7FF) {
			// U+0800 - U+D7FF
			count += 3;
		} else if (cp <= 0xDFFF) {
			// U+10000 - U+10FFFF
			//
			// 0xD800 - 0xDBFF (High Surrogates)
			// 0xDC00 - 0xDFFF (Low Surrogates)
			count += 2;
		} else if (cp <= 0xFFFF) {
			// U+E000 - U+FFFF
			count += 3;
		} else {
			// undefined code point in UTF-16
			// do nothing
		}
	}
	return count;
}




var multi_dialog_functions = {};

function append_function_dialog(classname, func) {
	multi_dialog_functions[classname] = func;
}



function ajax_auto_exe(dialog_id) {

	var parent_obj = $(dialog_id);
	if (parent_obj.length > 0) {
		var parent_height = parent_obj.height() + 200;
		var parent_top = parent_obj.offset().top;
		var parent_position = parent_height + parent_top;
	}
	var tags = $(dialog_id + " .ajax-auto");
	if (dialog_id === "body") {
		// Keep main/body infinite-scroll independent from the side panel.
		tags = tags.not("#work_area_second .ajax-auto");
	}
	tags = tags.filter(":visible");

	//console.log("parent_height=" + parent_height + " parent_top=" + parent_top + " parent_position=" + parent_position);

	tags.each(function (index, element) {
		var top = $(element).offset().top;

		if (top < $(window).scrollTop() + $(window).height()) {

			if (parent_position === undefined || parent_position > top) {
				// Dialogの中に設置した .ajax-auto タグが表示された

				//console.log("ajax_auto_exe");
				//console.log("element offset top:" + top)

				var form = $(element).data("form");
				if (form === undefined) {
					var fd = new FormData();
				} else {
					var fd = new FormData(parent_obj.find("#" + form).get(0));
				}

				var url = $(element).data("url");
				if (url === undefined) {
					url = "app.php";
				}
				var datalist = $(this).data();
				for (key in datalist) {
					fd.append(key, datalist[key]);
				}

				element.remove();

				setTimeout(function () {
					appcon("app.php", fd);
				}, 1);

			}
		}
	});

}

function ajax_auto_exe_in_scroll_container(dialog_id, container_selector) {
	var parent_obj = $(dialog_id);
	var container = $(container_selector).first();
	if (parent_obj.length === 0 || container.length === 0) {
		return;
	}

	var tags = parent_obj.find(".ajax-auto");
	var containerTop = container.scrollTop();
	var visibleBottom = containerTop + container.innerHeight();

	tags.each(function (index, element) {
		var rect = element.getBoundingClientRect();
		var containerRect = container.get(0).getBoundingClientRect();
		var top = (rect.top - containerRect.top) + containerTop;

		if (top < visibleBottom + 60) {
			var form = $(element).data("form");
			var fd;
			if (form === undefined) {
				fd = new FormData();
			} else {
				var formTag = parent_obj.find("#" + form).get(0);
				fd = formTag ? new FormData(formTag) : new FormData();
			}

			var datalist = $(this).data();
			for (key in datalist) {
				fd.append(key, datalist[key]);
			}

			element.remove();
			if (dialog_id == "#work_area_second") {
				second_work_area_scroll_restore_top = container.scrollTop();
			}
			setTimeout(function () {
				appcon("app.php", fd);
			}, 1);
		}
	});
}

function bind_search_box_auto_submit(dialog_id) {
	var root = (dialog_id && dialog_id !== "") ? $(dialog_id) : $("body");
	if (root.length === 0) {
		root = $("body");
	}

	root.find(".search_box").each(function () {
		var box = $(this);
		var form = box.find("form.search_form_flex").first();
		var button = box.find(".search_right button.ajax-link").first();
		if (form.length === 0 || button.length === 0) {
			return;
		}

		form.off(".auto_search");
		var timer = null;
		var triggerSearch = function (delayMs) {
			if (timer) {
				clearTimeout(timer);
				timer = null;
			}
			var execute = function () {
				if (button.prop("disabled")) {
					return;
				}
				button.trigger("click");
			};
			if (delayMs > 0) {
				timer = setTimeout(execute, delayMs);
			} else {
				execute();
			}
		};

		form.on("change.auto_search", "input,select,textarea", function () {
			var tag = (this.tagName || "").toLowerCase();
			var type = (this.type || "").toLowerCase();
			if ((tag === "input" && (type === "text" || type === "search" || type === "number")) || tag === "textarea") {
				triggerSearch(350);
				return;
			}
			triggerSearch(0);
		});

		form.on("input.auto_search", "input[type='text'],input[type='search'],input[type='number'],textarea", function () {
			triggerSearch(350);
		});
	});
}

// デバッグ画面は使わない
$("#show_debug").hide();
$("#debug_window").hide();


function append_debug_window(msg, data = "", flg_type = "") {

	if (msg === undefined) {
		return;
	}

	if (flg_type != "error") {
		if ($("#testserver").html() != "true") {
			return;
		}
	}

	if (flg_type == "table") {
		var color_key = "color:#4BA3FF;";
		var color_sep = "color:black;";
		var color_val = "color:#FCAF3E;";
		var color_title = "color:#4E9A06;font-weight:bold;"
		console.log("%c" + msg, color_title);
		for (let key in data) {
			if (key == "class" || key == "function") {
				console.log("%c" + key + '%c: %c' + data[key], color_key + "font-weight:bold;", color_sep, color_val + "font-weight:bold;");
			} else if (key == "slicedata") {
				console.log("%c" + key + '%c: %c' + "-base64-", color_key, color_sep, color_val);
			} else {
				console.log("%c" + key + '%c: %c' + data[key], color_key, color_sep, color_val);
			}

		}
		return;
	}

	if (flg_type == "error") {
		console.error(msg);
		if (data != "") {
			console.error(data);
		}
	} else {
		console.log(msg);
		if (data != "") {
			console.log(data);
		}
}
}



function set_windowID() {

	var windowID;

	// Set new window ID
	var new_windowID = $("#new_windowID").html();
	if (new_windowID != "") {
		sessionStorage.setItem('windowID', new_windowID);
		$("#new_windowID").html("");
		windowID = new_windowID;
	} else {
		windowID = sessionStorage.getItem('windowID');
		if (!windowID) {

			// 基本的にここには入らないはず！
			// 下記は万一の場合のコード

			// New tab
			windowID = "WID_" + Date.now().toString();  // 現在の時刻をミリ秒で取得して文字列に変換
			sessionStorage.setItem('windowID', windowID);

			// Old windowID
			var old_windowID = Cookies.get("windowID");
			Cookies.set("old_windowID", old_windowID, cookieOpt());
		}
	}
	// CookieにwindowIDをセット
	Cookies.set("windowID", windowID, cookieOpt());
}

$(window).on('focus', function () {
	set_windowID();
});
set_windowID();


//---------------------
// 多言語対応
//---------------------
var lang_list = {};   // lang_list[英語][種類(en/jp)]
function get_lang_list() {

	//初期化
	lang_list = {};

	//サーバからリストをとってくる
	var fd = new FormData();
	fd.append("class", "lang");
	fd.append("function", "list");
	var url = "app.php";
	appcon(url, fd, function (data) {
		if (data !== undefined) {
			lang_list = data["list"];

			translate();
		} else {
			//ログイン画面
			lang_list["base"] = {
				"Login ID":
						{
							en: "Login ID",
							jp: "ログインID"
						},
				"Password":
						{
							en: "Password",
							jp: "パスワード"
						},
				"English":
						{
							en: "English",
							jp: "英語"
						},
				"Japanese":
						{
							en: "Japanese",
							jp: "日本語"
						},
				"Login":
						{
							en: "Login",
							jp: "ログイン"
						},
			}
			translate();
		}
	});
}

function translate() {

	// // 事前準備
	$(".lang").each(function (index) {
		//タグ内に英語が設定されていない場合は lang_en に保持しておく
		var en;
		let lang_prop = '';
		en = $(this).html();
		en = en.trim();

		if ($(this).attr("lang_en") === undefined) {

			if ($(this).prop("tagName") == "SELECT") {
				$(this).removeClass("lang");
				$(this).find("option").each(function (index) {
					en = $(this).html();
					$(this).addClass("lang");
					$(this).attr("lang_prop", "html");
					$(this).attr("lang_en", en)
				});
			}

			if ($(this).prop("tagName") == "INPUT" && $(this).attr("type") == "radio") {

				var parentlabel = $(this).parent("label");
				en = parentlabel.text();
				var inputtag = parentlabel.find("input");
				parentlabel.html("");
				parentlabel.append(inputtag);
				parentlabel.append('<span>');
				var spantag = parentlabel.find("span");
				spantag.addClass("lang");
				spantag.attr("lang_prop", "html");
				spantag.attr("lang_en", en);
				spantag.html(en);
				parentlabel.wrapInner("<span>");
				$(this).removeClass("lang");

			} else {
				if (en == '') {
					en = $(this).val();
					if (en == '') {
						en = $(this).attr('placeholder');
						if (en != '') {
							lang_prop = 'placeholder';
						}
					} else {
						lang_prop = 'val';
					}
				} else {
					lang_prop = 'html';
				}
				$(this).attr("lang_en", en);
				$(this).attr("lang_prop", lang_prop);
			}

		}
	});


	//翻訳実行
	var selected_lang = "jp";
	$(".lang").each(function (index) {

		var en = $(this).attr("lang_en");

		//classnameを取得
		var parent = $(this).parents(".lang_check_area");
		var classname = "";
		if (parent.length > 0) {
			// Multi Windowの場合
			classname = parent.data("classname");
		}

		// 言語セレクターに合わせて変更
		if (lang_list[classname] === undefined || lang_list[classname][en] === undefined) {
			// Nothing to do
		} else {

			// 翻訳する
			var d = lang_list[classname][en];
			var transrated = d[selected_lang];

			if (transrated != "") {
				let lang_prop = $(this).attr("lang_prop");
				if (lang_prop == 'html') {
					$(this).html(transrated);
				} else if (lang_prop == 'val') {
					$(this).val(transrated);
				} else if (lang_prop == 'placeholder') {
					$(this).attr('placeholder', transrated);
				}

			}
		}
	});


}

$(function () {
	get_lang_list();
	Cookies.set("lang", "jp", cookieOpt());

	if ($("#testserver").html() == "true") {
	}

	translate();
});





//-----------------------------------
// マルチダイアログのデフォルト関数
// execute when opening a new window
//-----------------------------------
append_function_dialog("__all__", function (dialog_id, flg_window = false) {

	// World_date_time
	exec_world_datetime();


	// Datepicker
	function initialize_datepicker_input(input) {
		if (input.data("originalDatepickerInitialized")) {
			return;
		}
		input.data("originalDatepickerInitialized", true);

		input.prop('readOnly', true);
		if (!input.parent().hasClass("datepicker_area")) {
			input.wrap('<div class="datepicker_area" style="display:block;"></div>');
		}
	}
	$(dialog_id).off("click.datepickerInit focus.datepickerInit", ".datepicker")
		.on("click.datepickerInit focus.datepickerInit", ".datepicker", function () {
			var c = $(this);
			initialize_datepicker_input(c);
			open_original_datepicker_panel(c);
		});
	$(dialog_id + " .datepicker").each(function () {
		if ($(this).is(":visible")) {
			initialize_datepicker_input($(this));
		}
	});

	// Timepicker
	function initialize_timepicker_input(input) {
		if (input.data("originalTimePickerInitialized")) {
			return;
		}
		input.data("originalTimePickerInitialized", true);
		input.prop("readOnly", true);
	}
	$(dialog_id).off("focus.timepickerInit click.timepickerInit", ".timepicker")
		.on("focus.timepickerInit click.timepickerInit", ".timepicker", function () {
			var c = $(this);
			initialize_timepicker_input(c);
			open_original_time_picker_panel(c);
		});
	$(dialog_id + ' .timepicker').each(function () {
		if ($(this).is(":visible")) {
			initialize_timepicker_input($(this));
		}
	});

	// YearMonth picker
	$(dialog_id).off("focus.yearMonthPickerInit click.yearMonthPickerInit", ".year_month_picker")
		.on("focus.yearMonthPickerInit click.yearMonthPickerInit", ".year_month_picker", function () {
			var c = $(this);
			if (!c.data("yearMonthPickerInitialized")) {
				c.year_month_picker();
				setTimeout(function () {
					c.trigger("focus");
				}, 0);
			}
		});
	$(dialog_id + ' .year_month_picker').each(function () {
		$(this).year_month_picker();
	});

	// RADIO
	$(".checkboxradio").checkboxradio({
		icon: false
	});

	// CHECKBOX (fr_checkbox)
	$(dialog_id + ' .fr_checkbox').each(function () {
		var input = $(this).find("input");
		var unchecked = $(this).find(".unchecked");
		var checked = $(this).find(".checked");
		var unselected = $(this).find(".unselected");

		if ($(this).data("search") == "1") {
			input.hide();

			var fr_checkbox_select = function (i, unselected, unchecked, checked) {
				unselected.removeClass("on");
				unchecked.removeClass("on");
				checked.removeClass("on");
				if (i == "0") {
					unchecked.addClass("on");
				} else if (i == "1") {
					checked.addClass("on");
				} else {
					unselected.addClass("on");
				}
			}

			fr_checkbox_select(input.val(), unselected, unchecked, checked);

			unchecked.on("click", function (e) {
				input.val(0);
				fr_checkbox_select(0, unselected, unchecked, checked);
			});

			checked.on("click", function (e) {
				input.val(1);
				fr_checkbox_select(1, unselected, unchecked, checked);
			});

			unselected.on("click", function (e) {
				input.val("");
				fr_checkbox_select("", unselected, unchecked, checked);
			});


		} else {
			if (input.val() == 1) {
				unchecked.hide();
			} else {
				checked.hide();
			}
			input.hide();

			unchecked.on("click", function (e) {
				input.val(1);
				unchecked.hide();
				checked.show();
			});

			checked.on("click", function (e) {
				input.val(0);
				unchecked.show();
				checked.hide();
			});
		}

	});


	// Autocomplate Off
	$(dialog_id + ' input').prop("autocomplete", "off");

	// Search box auto-submit
	bind_search_box_auto_submit(dialog_id);

	// Scroll Event
	if (flg_window == false) {
		$(dialog_id + " .multi_dialog_scroll").off("scroll");
		$(dialog_id + " .multi_dialog_scroll").on("scroll", function (e) {
			ajax_auto_exe(dialog_id);
		});
		// ajax-linkを一回動かす
		ajax_auto_exe(dialog_id);
	} else {
			if (dialog_id == "#work_area_second") {
				var panelBody = $("#work_area_second .work_area_second_body");
				panelBody.off("scroll.ajax_auto");
				panelBody.on("scroll.ajax_auto", function () {
					ajax_auto_exe_in_scroll_container("#work_area_second", "#work_area_second .work_area_second_body");
				});
				ajax_auto_exe_in_scroll_container("#work_area_second", "#work_area_second .work_area_second_body");
			} else {
				var tag_ajax_auto = $("body").find(".ajax-auto");
				$(window).off("scroll.ajax_auto_body");
				if (tag_ajax_auto.length > 0) {
					$(window).on("scroll.ajax_auto_body", function () {
						ajax_auto_exe("body");
					});
				}
			}
		}

	// color picker
	$(dialog_id).off("focus.originalColorPicker click.originalColorPicker", ".colorpicker")
		.on("focus.originalColorPicker click.originalColorPicker", ".colorpicker", function (e) {
			e.preventDefault();
			var input = $(this);
			ensure_original_colorpicker_input(input);
			open_original_colorpicker_panel(input);
		})
		.off("click.originalColorPickerTrigger", ".fbp-original-colorpicker-trigger")
		.on("click.originalColorPickerTrigger", ".fbp-original-colorpicker-trigger", function (e) {
			e.preventDefault();
			var input = $(this).siblings(".colorpicker").first();
			if (input.length === 0) {
				return;
			}
			ensure_original_colorpicker_input(input);
			open_original_colorpicker_panel(input);
		})
		.off("input.originalColorPicker change.originalColorPicker", ".colorpicker")
		.on("input.originalColorPicker change.originalColorPicker", ".colorpicker", function () {
			update_original_colorpicker_preview($(this));
		});

	$(dialog_id + " .colorpicker").each(function () {
		ensure_original_colorpicker_input($(this));
		update_original_colorpicker_preview($(this));
	});

	// 文字バイトカウンター
	$(dialog_id + " .wordcounter").text_size_limit({"max": -1});

	// 多言語
	translate();

	// ドロップダウンに検索機能
	$(dialog_id + " select").each(function () {
		var $sel = $(this);
		ensure_original_searchable_select($sel);
	});

	//Vimeo
	$(dialog_id + " .vimeo").each(function (index, element) {
		vimeo_player(this);
	});

	fr_file_upload_init();
	fr_email_verify_init();

	// Badge処理
	$(".badge").each(function (index, element) {
		var val = $(this).html();
		if (val === undefined || val == "" || val == 0) {
			$(this).hide();
		}
	});

	// geometry_location
	$(".geometry_location").on("focus", function (e) {
		$("button").each(function (index, element) {
			$(this).data("bgcolor", $(this).css("background-color"));
			$(this).css("background-color", "#CCC");
			$(this).css("pointer-events", "none");
		});
	});

	$(".geometry_location").on("focusout", function (e) {

		$('input[name="geometry_location"]').remove();
		var input_tag = document.createElement('input');
		$(input_tag).attr("name", "geometry_location");
		$(input_tag).attr("type", "hidden");
		$(this).parent().append(input_tag);

		get_geometry_location($(this), $(input_tag));

	});

	// Vimeo thumbnail
	$(".vimeo_thumbnail").each(function () {
		if ($(this).find("img").length == 0) {
			var obj = this;
			var vimeo_id = $(this).data("vimeo_id");
			var fd = new FormData();
			fd.append("class", "_VIMEO");
			fd.append("function", "_THUMBNAIL");
			fd.append("vimeo_id", vimeo_id);
			appcon("app.php", fd, function (data) {
				var img = document.createElement('img');
				$(img).attr("src", data["url"]);
				obj.append(img);
			});
		}
	});

	set_parameters_to_button();

});
// public用に１回動かす
multi_dialog_functions["__all__"]("", true);


// ボタンに自動でajax-linkとclassnameを付与
function set_parameters_to_button() {
	// buttonタグ、.ajax-autoクラス、.ajax-linkクラスを対象に検索
	$('[invoke-function]').each(function () {

		// CHANGE: download-link は例外（何もしない）
		if ($(this).hasClass('download-link')) {
			return true; // continue
		}

		// (1) "getting_dialog_id"をclosestで検索
		var closestElement = $(this).closest('.getting_dialog_id');

		// (2) closest要素のdata-classnameの値を取得
		var dataClassName = closestElement.data('classname');
		if (dataClassName === undefined || dataClassName === null || dataClassName === "") {
			var closestClassNameElement = $(this).closest('[data-classname]');
			dataClassName = closestClassNameElement.data('classname');
		}
		if (dataClassName === undefined || dataClassName === null || dataClassName === "") {
			dataClassName = $("body").attr("data-classname");
		}
		if ((dataClassName === undefined || dataClassName === null || dataClassName === "") && $("#page_classname").length > 0) {
			dataClassName = $("#page_classname").attr("data-class");
		}

		// "invoke-function"属性が設定されている場合のみ処理を実行
		if ($(this).attr('invoke-function') !== undefined) {
			var functionValue = $(this).attr('invoke-function');
			$(this).removeAttr('invoke-function').attr('data-function', functionValue);

			if (!$(this).hasClass('ajax-auto')) {
				$(this).addClass('ajax-link');
			}

			// "data-class"属性を追加し、値を設定
			$(this).attr('data-class', dataClassName);
		}

		// "invoke-class"属性が設定されている場合
		if ($(this).attr('invoke-class') !== undefined) {
			var cvalue = $(this).attr('invoke-class');
			$(this).removeAttr('invoke-class').attr('data-class', cvalue);
		}

	});
}

function summarize_multi_dialog_state(dialog_id, dialog_name, classname) {
	var $dlg = $(dialog_id);
	if ($dlg.length === 0) {
		return null;
	}

	var buttons = [];
	$dlg.find("button:visible, a.ajax-link:visible").each(function (idx) {
		if (idx >= 50) {
			return false;
		}
		var $el = $(this);
		buttons.push({
			tag: this.tagName.toLowerCase(),
			text: String($el.text() || "").trim().slice(0, 80),
			data_class: String($el.data("class") || ""),
			data_function: String($el.data("function") || ""),
			data_form: String($el.data("form") || ""),
			id: String($el.attr("id") || ""),
			name: String($el.attr("name") || "")
		});
	});

	var forms = [];
	$dlg.find("form").each(function (idx) {
		if (idx >= 10) {
			return false;
		}
		var $form = $(this);
		var fields = [];
		$form.find("input, textarea, select").each(function (fidx) {
			if (fidx >= 80) {
				return false;
			}
			var $field = $(this);
			fields.push({
				tag: this.tagName.toLowerCase(),
				type: String($field.attr("type") || ""),
				name: String($field.attr("name") || ""),
				id: String($field.attr("id") || "")
			});
		});
		forms.push({
			id: String($form.attr("id") || ""),
			field_count: fields.length,
			fields: fields
		});
	});

	return {
		dialog_id: dialog_id,
		dialog_name: dialog_name || "",
		classname: classname || "",
		title: String(
			$dlg.find(".multi_dialog_innder_title").first().text()
			|| $dlg.find(".multi_dialog_title_area").first().clone().find(".multi_dialog_close").remove().end().text()
			|| ""
		).trim().slice(0, 120),
		button_count: buttons.length,
		form_count: forms.length,
		buttons: buttons,
		forms: forms
	};
}

// public用に１回動かす
$(function () {
	var exec_classname = $("#page_classname").data("class");
	if (exec_classname) {
		var func = multi_dialog_functions[exec_classname];
		if (func) {
			func("");
		}
	}
});

// public用に１回動かす
ajax_auto_exe("body");



$(function () {
	$("body").on("click", ".window_large", function (e) {
		var p = $(this).parents(".multi_dialog");
		console.log(p);
		//p.css("transform","scale(1,1)");
		p.css("opacity", "1");
	});
	$("body").on("click", ".window_small", function (e) {
		var p = $(this).parents(".multi_dialog");
		console.log(p);
		//p.css("transform","scale(0.4,0.4)");
		p.css("opacity", "0.5");
	});
});


$("body").on("change", "#vimeo_id", function (e) {

	var raw = $("#vimeo_id").val().trim();

	// URL → ID の変換
	// ・末尾の数字だけを抜き出す
	// ・"https://vimeo.com/123456789" → "123456789"
	var vid = raw.replace(/^https?:\/\/vimeo\.com\//, '');

	var formData = new FormData();
	formData.append("class", "upload");
	formData.append("function", "change_to_vimeo_id");
	formData.append("txt", vid);

	appcon("app.php", formData, function (d) {
		$("#vimeo_id").val(d["vimeo_id"]);
	});
});

var sliced_file_id = "";
$("body").on("change", "#sliced_file", function (e) {

	$("#sliced_error").html("Uploading...");

	var k = 0;
	var slice_size = 1024 * 100; // Buffer size

	var f = $("#sliced_file").prop('files')[0];
	if (f == null) {
		return;
	}

	var mode = $("#sliced_file").data("mode");

	var size = f.size;
	var filename = f.name;
	var count = Math.ceil(size / slice_size); // Calculate the number of slices
	sliced_file_id = 0;

	function uploadSlice() {
		if (k < count) {
			var splitData = f.slice(k * slice_size, (k + 1) * slice_size); // Slice the file
			k++;

			var reader = new FileReader();
			reader.onload = function (event) {
				var base64 = event.target.result;
				var formData = new FormData();
				formData.append("slicedata", btoa(base64));
				formData.append("filename", filename);
				formData.append("k", k);
				formData.append("class", "upload");
				formData.append("function", "sliced_data");
				formData.append("sliced_file_id", sliced_file_id)

				appcon("app.php", formData, function (d) {

					sliced_file_id = d["sliced"]["sliced_file_id"];

					$("#sliced_error").html("Uploading... " + k + " / " + count + " " + Math.ceil(100 * k / count).toString() + "% ");
					uploadSlice(); // Upload the next slice
				});
			};
			reader.readAsBinaryString(splitData);
		} else {

			if (mode == "vimeo") {
				$("#sliced_error").html("Sending the file to Vimeo server... ");
				var formData = new FormData();
				formData.append("class", "upload");
				formData.append("function", "send_to_vimeo");
				formData.append("sliced_file_id", sliced_file_id);
				formData.append("title", $("#vimeo_title").val());
				formData.append("description", $("#vimeo_description").val());
				appcon("app.php", formData, function (d) {
					if (d["vimeo"]["result"] == "success") {
						var vimeo_id = d["vimeo"]["vimeo_id"];
						$("#vimeo_id").val(vimeo_id);
						$("#sliced_error").html("Success uploading");
					} else {
						$("#sliced_error").html("Fail uploading");
					}
				});
			} else {
				$("#sliced_file_id", sliced_file_id);
				$("#sliced_error").html("Upload complete!");
			}
		}
	}

	uploadSlice(); // Start the upload process
});



//Viemo player
function vimeo_player(getobj) {

	var obj = getobj;

	var id = "vimeo_player_" + $(obj).data("vimeo_id");

	$(obj).attr("id", id);

	var vimeo_id = $(obj).data("vimeo_id");
	var options = {
		id: vimeo_id,
		responsive: true,
		autopause: true,
	};
	var player = new Vimeo.Player(id, options);
	player.setVolume(1);
	player.on('play', function () {

	});
	player.on('ended', function () {
		player.destroy();
		$(obj).html("");
		vimeo_player(obj);
	});
	player.on('pause', function () {
	});
}

// $('#left-sidebar-show-btn').click(function () {
// 	// $('#menu_area').css('display', 'block').css('width', '0px').css('width', '300px');
// 	multi_dialog_zindex++;
// 	$('#menu_area').addClass('detect_outside_click').css('left', '0').css("z-index",multi_dialog_zindex);
// });

$(document).on('click', '.left-sidebar-hide-btn, #sidemenu .ajax-link', function () {
	// $('#menu_area').css('display', 'block').css('width', '0px').css('width', '300px');
	// $('#menu_area').removeClass('detect_outside_click').css('left', '-360px');
	if (sidemenu_from == 'right') {
		var document_width = $(document).width();
		$('#sidemenu').removeClass('detect_outside_click').animate({'left': document_width + 'px'});
	} else {
		var sidemenu_width = $('#sidemenu').width();
		$('#sidemenu').removeClass('detect_outside_click').animate({'left': '-' + sidemenu_width + 'px'});
	}
});

var sidemenu_from;
$(document).mouseup(function (e) {
	if ($(e.target).closest(".detect_outside_click").length === 0) {
		$('#menu_area').removeClass('detect_outside_click').css('left', '-360px');
		$('#popup').removeClass('detect_outside_click').css('opacity', '0').css('display', 'none');

		if (sidemenu_from == 'right') {
			var document_width = $(document).width();
			$('#sidemenu').removeClass('detect_outside_click').animate({'left': document_width + 'px'}, 200, function () {
				$(this).remove();
			});
		} else {
			var sidemenu_width = $('#sidemenu').width();
			$('#sidemenu').removeClass('detect_outside_click').animate({'left': '-' + sidemenu_width + 'px'}, 200, function () {
				$(this).remove();
			});
		}
	}



	if (year_month_picker) {
		if ($(e.target).closest(".detect_outside_click").length === 0) {
			$(".year_month_picker_panel").hide();
			$(".year_month_picker_panel").removeClass("detect_outside_click");
			year_month_picker = false;
		}
	}
});






// World Date Time
function exec_world_datetime() {
	$(".world_datetime").each(function () {
		var element = $(this);

		// elementをhiddenにする
		element.hide();
		element.removeClass("world_datetime");

		// wrap_datetimeでelementを囲む
		var wrapDiv = $('<div class="wrap_datetime"></div>');
		element.after(wrapDiv);
		wrapDiv.append(element);

		// elementのvalueから日付と時間を分割して設定（サーバータイムゾーン基準）
		var timezone = get_server_timezone();
		if (element.is('input')) {
			if (element.val() > 0) {
				var datetime = get_datetime_parts_in_timezone(parseInt(element.val(), 10), timezone);
			} else {
				var datetime = null;
			}
		} else {
			if (element.html() > 0) {
				var datetime = get_datetime_parts_in_timezone(parseInt(element.html(), 10), timezone);
			} else {
				var datetime = null;
			}
		}

		if (datetime !== null) {
			var year = datetime.year;
			var month = datetime.month;
			var day = datetime.day;
			var hours = datetime.hour;
			var minutes = datetime.minute;
		}
		var dateFormat = get_server_date_format();
		var datetimeFormat = get_server_datetime_format();
		var timeFormat = get_server_time_format();
		var datetimeParts = null;
		if (datetime !== null) {
			datetimeParts = {
				year: year,
				month: month,
				day: day,
				hour: hours,
				minute: minutes
			};
		}

		if (element.is('input')) {
			// テキストボックスを２つ追加する
			var tag_date = $('<input type="text" class="datepicker" placeholder="' + build_placeholder_from_php_date_format(dateFormat) + '">');
			var tag_time = $('<input type="text" class="timepicker" placeholder="' + build_placeholder_from_php_date_format(timeFormat) + '">');
			wrapDiv.append(tag_date);
			wrapDiv.append(tag_time);
			if (datetimeParts !== null) {
				tag_date.val(format_php_datetime(datetimeParts, dateFormat));
				tag_time.val(format_php_datetime(datetimeParts, timeFormat));
			} else {
				tag_date.val("");
				tag_time.val(format_php_datetime({
					year: "2000",
					month: "01",
					day: "01",
					hour: "00",
					minute: "00"
				}, timeFormat));
			}

			// tag_date, tag_timeに変化があったら、elementのvalueを更新（サーバータイムゾーン基準で変換）
			function updateElementValue() {
				var newTimestamp = datetime_strings_to_timestamp_in_timezone(tag_date.val(), tag_time.val(), timezone);
				if (newTimestamp !== "") {
					element.val(newTimestamp);
				} else {
					element.val("");
				}
			}

			tag_date.on('change', updateElementValue);
			tag_time.on('change', updateElementValue);
		} else {
			if (datetime !== null) {
				var tag_p = $(`<p class="datetime_timestamp">${format_php_datetime(datetimeParts, datetimeFormat)}</p>`);
			} else {
				var tag_p = "";
			}
			wrapDiv.append(tag_p);
		}

	});
}

//--------------------
// file uploader with paste and drag & drop support
//--------------------

var fr_file_upload_active_div_id = '';
var fr_file_upload_active_div_number = 0;
function fr_file_upload_init() {
	var fr_file_upload_count = 0;
	$('.fr_image_paste').each(function () {
		let input_name = $(this).attr('name');
		let text = $(this).data('text');
		if (text === undefined || text == '') {
			text = "File Upload";
		}
		let divStyle = $(this).data('div_style');

		let multiple = $(this).data('multiple');
		let maxLength = $(this).data('max_length');

		fr_file_upload_count++;
		let file_upload_html;

		if (multiple === true) {
			file_upload_html = `<div class="fr_file_upload_div" id="fr_file_upload_div_${fr_file_upload_count}" data-number="${fr_file_upload_count}" style="${divStyle}">
			<div class="fr_file_upload_input_div upload__box">
				<div>
					<input type="file" name="${input_name}" multiple="" data-multiple="true" data-max_length="${maxLength}" class="fr_file_input upload_multiple_img" id="fr_file_input_${fr_file_upload_count}">
					<p class="lang" style="margin: 5px 0 0 0; font-size: 12px;">Drag & drop here</p>
				</div>
				<div class="fr_file_preview_div"></div>
                                <div class="upload__img-wrap"></div>
			</div>
		</div>`;
		} else {
			file_upload_html = `<div class="fr_file_upload_div" id="fr_file_upload_div_${fr_file_upload_count}" data-number="${fr_file_upload_count}" style="${divStyle}">
			<div class="fr_file_upload_input_div">
				<div>
					<input type="file" name="${input_name}" class="fr_file_input" id="fr_file_input_${fr_file_upload_count}">
					<p class="lang" style="margin: 5px 0 0 0; font-size: 12px;">Drag & drop here</p>
				</div>
				<div class="fr_file_preview_div"></div>
			</div>
		</div>`;
		}
		imgArray = [];
		$(this).after(file_upload_html);
		$(this).attr('data-name', input_name);
		$(this).attr('data-id', fr_file_upload_count);
		$(this).attr('name', '');
		$(this).attr('id', 'fr_old_file_name_' + fr_file_upload_count);
		$(this).addClass('hidden');
		$(this).removeClass('fr_image_paste');
	});
}
fr_file_upload_init();

var imgWrap = "";
var imgArray = [];
$(document).on('change', '.fr_file_input', function (e) {

	if (!e.target.files.length)
		return;
	var ismultiple = $(this).data('multiple');
	$(this).closest('.fr_file_upload_input_div').children('.fr_file_preview_div').html('');
	if (!ismultiple) {
		if (e.target.files[0].type.startsWith('image/')) {
			const img = document.createElement('img');
			const blob = URL.createObjectURL(e.target.files[0]);
			img.src = blob;
			img.style.width = "100px";
			$(this).closest('.fr_file_upload_input_div').children('.fr_file_preview_div').html(img);
		}
	} else {

		var max_length = $('.upload_multiple_img').data('max_length');
		var imgfiles = e.target.files;
		fr_multiple_img_add(max_length, imgfiles);

	}
});

$('body').on('click', ".upload__img-close", function (e) {
	var file = $(this).parent().data("file");
	for (var i = 0; i < imgArray.length; i++) {
		if (imgArray[i].name === file) {
			imgArray.splice(i, 1);
			break;
		}
	}
	let list = new DataTransfer();
	imgArray.forEach(function (f, index) {
		list.items.add(f);
	});
	let myFileList = list.files;
	document.getElementById("fr_file_input_" + fr_file_upload_active_div_number).files = myFileList;
	$(this).parent().parent().remove();
});

$(document).on('click', '.fr_file_upload_div', function () {
	fr_file_upload_active_div_id = this.id;
	fr_file_upload_active_div_number = $(this).data('number');
	//$('.fr_file_upload_div').css('border', 'solid 1px black');
	//$(this).css('border', 'solid 2px blue');
});



$(document)
		.on('dragover', '.fr_file_upload_div', function (e) {
			$(this).addClass('fr_file_upload_draggin');
			return false;
		}).on('dragleave', '.fr_file_upload_div', function (e) {
	fr_dragging = true;
	$(this).removeClass('fr_file_upload_draggin');
	return false;
}).on('drop', '.fr_file_upload_div', function (e) {
	fr_file_upload_active_div_id = this.id;
	fr_file_upload_active_div_number = $(this).data('number');
	$('#' + fr_file_upload_active_div_id).find('.fr_file_preview_div').html('');
	document.getElementById("fr_file_input_" + fr_file_upload_active_div_number).files = e.originalEvent.dataTransfer.files;
	var ismultiple = $(this).find('.fr_file_input').data('multiple');
	if (!ismultiple) {
		//create image
		if (e.originalEvent.dataTransfer.files[0].type.startsWith('image/')) {
			const img = document.createElement('img');
			const blob = URL.createObjectURL(e.originalEvent.dataTransfer.files[0]);
			img.src = blob;
			img.style.width = "100px";
			$('#' + fr_file_upload_active_div_id).find('.fr_file_preview_div').html(img);
		}
	} else {
		var max_length = $('.upload_multiple_img').data('max_length');
		var imgfiles = e.originalEvent.dataTransfer.files;
		fr_multiple_img_add(max_length, imgfiles);
	}
	$(this).children('.fr_file_upload_btn_div').addClass('hidden');
	$(this).children('.fr_file_upload_input_div').removeClass('hidden');
	return false;
});
// end - file uploader
function fr_multiple_img_add(max_length, imgfiles) {
	$('.upload_multiple_img').each(function () {
		//$(this).on('change', function (e) {
		imgWrap = $(this).closest('.upload__box').find('.upload__img-wrap');
		var maxLength = max_length;

		//var files = e.target.files;
		var files = imgfiles;
		var filesArr = Array.prototype.slice.call(files);
		var iterator = 0;
		let list = new DataTransfer();

		filesArr = imgArray.concat(filesArr);
		filesArr = filesArr.filter((item, pos) => filesArr.indexOf(item) === pos);

		if (filesArr.length > maxLength) {
			alert('You can not select more than ' + maxLength + ' files');
			//return false;
		}
		var len = 0;
		imgWrap.html('');
		imgArray = [];
		filesArr.forEach(function (f, index) {

			len++;
			if (len <= maxLength) {

				list.items.add(f);
				imgArray.push(f);

				var reader = new FileReader();
				reader.onload = function (e) {
					var html = "<div class='upload__img-box'><div style='background-image: url(" + e.target.result + ")' data-number='" + $(".upload__img-close").length + "' data-file='" + f.name + "' class='img-bg'><div class='upload__img-close'></div></div></div>";
					if (!f.type.match('image.*')) {
						html = "<div class='upload__img-box'><div class='img-bg' style=''><p class='upload__noimage'>" + f.name + "</p><div class='upload__img-close'></div></div></div>";
					}

					imgWrap.append(html);
					iterator++;
				}
				reader.readAsDataURL(f);
			}
		});
		let myFileList = list.files;
		document.getElementById("fr_file_input_" + fr_file_upload_active_div_number).files = myFileList;
	});
}


// ----------------------------
// Email verification component
// ----------------------------
var fr_email_verify_count = 0;
var fr_email_verify_active_div_id = '';
var fr_email_verify_active_div_number = 0;
var fr_email_verify_btn_enter = false;
function fr_email_verify_init() {
	$('.fr_verification_mail').each(function () {
		let input_name = $(this).attr('name');

		fr_email_verify_count++;
		let file_upload_html = `<div class="fr_email_veriry_main_div hidden" id="fr_email_verify_main_div_${fr_email_verify_count}" data-number="${fr_email_verify_count}">
			<input type="hidden" name="${input_name}" class="fr_email_veriry_hidden_field" />
			<div class="fr_email_verify_first_div ">
				<div style="display:flex;">
					<span class="ui-icon ui-icon-triangle-1-w fr_email_veriry_email_back_btn" style="transform: scale(2); margin-top:0px;"></span>
					<p class="fr_email_veriry_email_p" style="margin: 0 10px"></p>
				</div>
				<button class="fr_email_verify_send_btn" data-class="user" data-function="fr_verification_mail_send" data-email="" data-key="">Send Verify Mail</button>
			</div>
			<div class="fr_email_verify_second_div hidden">
				<div style="display:flex;">
					<input class="fr_email_verify_text" type="text" style="text-align: center;" />
				</div>
				<button class="fr_email_verify_btn" data-class="user" data-function="fr_verification_mail_send" data-email="" data-key="">Submit</button>
				<p class="fr_email_verify_error_msg hidden">Verification Faild!</p>
			</div>
			<div class="fr_email_verify_third_div hidden">
				<div style="display:flex;">
					<span class="ui-icon ui-icon-circle-check" style="transform: scale(1.5); margin:auto; cursor: pointer !important;"></span>
					<p class="fr_email_veriry_email_p" style="margin: 0 10px"></p>
				</div>
			</div>
		</div>`;
		$(this).after(file_upload_html);
		$(this).attr('data-name', input_name);
		$(this).attr('data-id', fr_email_verify_count);
		$(this).attr('name', '');
		$(this).attr('id', 'fr_email_verify_old_input_' + fr_email_verify_count);
		$(this).addClass('fr_verification_mail_new');
		$(this).removeClass('fr_verification_mail');
	});
}
fr_email_verify_init();

$(document).on('change', '.fr_verification_mail_new', function (e) {
	e.preventDefault();
	let val = $(this).val();
	let main_div_id = '#fr_email_verify_main_div_' + $(this).data('id');
	$(main_div_id).removeClass('hidden');
	$(main_div_id).find('.fr_email_veriry_email_p').html(val);
	$(this).addClass('hidden');
	$(main_div_id).attr('data-email', val);
	$(main_div_id).find('.fr_email_verify_send_btn').attr('data-email', val);
	$(main_div_id).find('.fr_email_verify_btn').attr('data-email', val);
	$('.fr_email_veriry_email_back_btn').css('cursor', 'pointer');
	$(this).val('');
});

$(document).on("keydown", '.fr_verification_mail_new', function (e) {
	if (e.which == 13) {
		fr_email_verify_btn_enter = true;
	}
	setTimeout(() => {
		fr_email_verify_btn_enter = false;
	}, 100);
});

$(document).on('click', '.fr_email_verify_send_btn', function (e) {
	e.preventDefault();
	if (!fr_email_verify_btn_enter) {
		let main_div = $(this).closest('.fr_email_veriry_main_div');
		main_div.children('.fr_email_verify_second_div').removeClass('hidden');
		$(this).parent('.fr_email_verify_first_div').addClass('hidden');
		main_div.find('.fr_email_verify_text').focus();
		let email = $(this).attr('data-email');
		let fd = new FormData();
		fd.append('class', 'user');
		fd.append('function', 'fr_verification_mail_send');
		fd.append('email', email);
		appcon('app.php', fd, function (data) {
			main_div.find('.fr_email_verify_btn').attr('data-key', data.key);
		});
	}
});

$(document).on('click', '.fr_email_verify_btn', function (e) {
	e.preventDefault();
	let main_div = $(this).closest('.fr_email_veriry_main_div');
	let key = $(this).attr('data-key');
	let code = main_div.find('.fr_email_verify_text').val();
	let email = $(this).attr('data-email');
	let fd = new FormData();
	fd.append('class', 'user');
	fd.append('function', 'fr_verification_mail_verify');
	fd.append('key', key);
	fd.append('code', code);
	appcon('app.php', fd, function (data) {
		if (data.status) {
			main_div.children('.fr_email_verify_third_div').removeClass('hidden');
			main_div.find('.fr_email_verify_second_div').addClass('hidden');
			main_div.find('.fr_email_veriry_hidden_field').val(email);
		} else {
			main_div.find('.fr_email_verify_error_msg').removeClass('hidden');
		}
	});
});

$(document).on('click', '.fr_email_veriry_email_back_btn', function (e) {
	let parent = $(this).closest('.fr_email_veriry_main_div');
	let email = parent.attr('data-email');
	let input_id = '#fr_email_verify_old_input_' + $(this).closest('.fr_email_veriry_main_div').data('number');
	$(input_id).removeClass('hidden');
	$(input_id).val(email);
	parent.addClass('hidden');
});

$(document).on('click', '.fr_email_veriry_main_div', function () {
	fr_email_verify_active_div_id = this.id;
	fr_email_verify_active_div_number = $(this).data('number');
	$('.fr_email_veriry_main_div').css('border', 'solid 1px black');
	$(this).css('border', 'solid 2px blue');
});
//end - email verification componentn

function get_geometry_location(address_tag, textbox) {
	address_tag.css("transition", "0.4s");
	var atwidth = address_tag.width() + 20;
	var address = address_tag.val();
	var color = address_tag.css("color");
	geocoder = new google.maps.Geocoder();
	geocoder.geocode({
		'address': address
	}, function (results, status) {
		if (status === google.maps.GeocoderStatus.OK) {
			textbox.val(results[0].geometry.location);

			$('.glocation-error').remove();
			$('.glocation-success').remove();
			var locnotify = $("<p class='glocation-success' style='width:" + atwidth + "px;'>Successed to get geometry location!</p>").insertAfter(address_tag);

			setTimeout(function () {
				locnotify.css("display", "none");
			}, 2000);
		} else {
			console.log("Fail to get geometry.location");
			$('.glocation-error').remove();
			$('.glocation-success').remove();

			var locnotify = $("<p class='glocation-error' style='width:" + atwidth + "px;'>Failed to get geometry.location</p>").insertAfter(address_tag);

			setTimeout(function () {
				locnotify.css("display", "none");
			}, 2000);
		}
		$("button").each(function (index, element) {
			$(this).css("background-color", $(this).data("bgcolor"));
			$(this).css("pointer-events", "auto");
		});
	});
}

var map;
var marker = [];
var infoWindow = [];
function draw_google_map(tag_id, lat, lng, zoom, markerData) {

	if (status_map == 1) {
		draw_google_map_exe(tag_id, lat, lng, zoom, markerData);
	} else {
		var map_interval_pointer = function () {
			if (status_map == 1) {
				clearInterval(map_interval_pointer);
				draw_google_map_exe(tag_id, lat, lng, zoom, markerData);
			}
		}
		setInterval(map_interval_pointer, 1000);

	}
}

function draw_google_map_exe(tag_id, lat, lng, zoom, markerData) {

	if (!$("#" + tag_id).length) {
		console.log("There is no tag ID=" + tag_id + ".");
		return;
	}

	// size check and set
	var width = $("#" + tag_id).height();
	var height = $("#" + tag_id).width();

	if (width == 0 || height == 0) {
		$("#" + tag_id).width(500);
		$("#" + tag_id).height(500);
	}

	// 地図の作成
	map = new google.maps.Map(document.getElementById(tag_id), {// #sampleに地図を埋め込む
		center: {// 地図の中心を指定
			lat: lat, // 緯度
			lng: lng // 経度
		},
		zoom: zoom  // 地図のズームを指定
	});

	var bounds = new google.maps.LatLngBounds();

	// マーカー毎の処理
	if (markerData !== undefined) {
		for (var i = 0; i < markerData.length; i++) {

			var loc = markerData[i]["location"];

			var markerLatLng = new google.maps.LatLng({lat: loc["lat"], lng: loc["lng"]}); // 緯度経度のデータ作成
			marker[i] = new google.maps.Marker({// マーカーの追加
				position: markerLatLng, // マーカーを立てる位置を指定
				map: map // マーカーを立てる地図を指定
			});

			bounds.extend(markerLatLng);

			//吹き出しデータの作成
			infoWindow[i] = new google.maps.InfoWindow({// 吹き出しの追加
				content: '<div class="map_info_window">' + markerData[i]['html'] + '</div>' // 吹き出しに表示する内容
			});

			markerEvent(i); // マーカーにクリックイベントを追加

		}

		if (zoom == 0)
			map.fitBounds(bounds);

	}

	//init autocomplete
	$(".geometry_location").each(function (index, element) {
		// var locinput = document.getElementsByClassName('geometry_location');
		var locinput = $(this)[0];
		var autocomplete = new google.maps.places.Autocomplete(locinput);
		autocomplete.addListener('place_changed', function () {
			var place = autocomplete.getPlace();
			// locinput.value = JSON.stringify(place.address_components);
			$(".geometry_location").focusout();
		});

	});

}

// マーカーにクリックイベントを追加
function markerEvent(i) {
	marker[i].addListener('click', function () { // マーカーをクリックしたとき
		infoWindow[i].open(map, marker[i]); // 吹き出しの表示
	});
}

// first appcon for DISPLAY
$(function () {
	var fd = new FormData();
	fd.append("class", "_DISPLAY");
	fd.append("function", "_ARR");
	appcon("app.php", fd, function (data) {
	});
});

function getMaxZIndex() {
	let maxZIndex = 0;
	const elements = document.querySelectorAll('*');

	elements.forEach((element) => {
		const zIndex = window.getComputedStyle(element).getPropertyValue('z-index');
		if (!isNaN(zIndex) && zIndex !== 'auto') {
			maxZIndex = Math.max(maxZIndex, parseInt(zIndex, 10));
		}
	});

	return maxZIndex;
}


var polling_status = false;
var polling_request = null;
var polling_id = null;
var polling_startTime;
function startLongPolling(md, force = false) {

	//２重起動防止
	if (force === false) {
		if (polling_status !== false) {

			//一度停止する
			stopLongPolling();
			finalizePolling(null, null);

		}
	}

	var new_polling_id = md["polling_id"];
	var nickname = md["nickname"];
	var timeout_seconds = md["timeout_seconds"];
	var timeout_handler_function = md["timeout_handler_function"];
	var timeout_handler_class = md["timeout_handler_class"];

	if (new_polling_id === null) {
		return;
	}

	if (force === false) {
		// 最初の処理
		append_debug_window("Start Polling : " + new_polling_id + " " + nickname);
		polling_startTime = Date.now(); // 現在時刻をミリ秒で取得

		// タイムアウト検出
		let intervalId = setInterval(() => {
			let elapsedSeconds = (Date.now() - polling_startTime) / 1000;

			if (elapsedSeconds > timeout_seconds) {

				if (document.visibilityState !== "visible") {
					// 画面表示中はタイムアウトしない
					clearInterval(intervalId); // インターバルを停止
					stopLongPolling();
					finalizePolling(timeout_handler_class, timeout_handler_function);
				}
			}
		}, 1000); // 10msごとにチェック
	} else {
		// ループ２回目以降の処理
		append_debug_window("Polling : " + new_polling_id + " " + nickname);
	}

	polling_status = true;
	polling_id = new_polling_id;

	polling_request = $.ajax({
		url: "polling.php", // PHPスクリプトのURL
		method: "POST", // POSTメソッドに変更
		data: md,
		success: function (response) {
			var flg_continue = true;
			try {
				var response_json = JSON.parse(response);
				var data = response_json.data;
				if (response_json.success) {
					var fd = new FormData();
					append_debug_window("Polling receives the message following:");
					Object.entries(data).forEach(([key, value]) => {
						console.log("key=" + key + " value=" + value);
						fd.append(key, value);
					});
					appcon("app.php", fd);
				} else {
					if (response_json.message === "Abort") {
						flg_continue = false;
						finalizePolling(timeout_handler_class, timeout_handler_function);

					} else if (response_json.message === "Timeout") {
						// Nothing

					} else {
						append_debug_window("Fail", response);
					}
				}
			} catch (e) {
				console.error("Invalid JSON: ", e);
			}

			// 再びポーリング開始
			if (flg_continue) {
				startLongPolling(md, true);
			}
		},
		error: function (error) {
			append_debug_window("Polling connection has the error.", error);
			if (polling_status) {
				setTimeout(function () {
					startLongPolling(md, true);
				}, 3000); // 3秒後に再接続
			}
		}
	});
}

// サーバーにポーリング停止情報を送る
function stopLongPolling() {
	navigator.sendBeacon("polling_stop.php", JSON.stringify({polling_id: polling_id}));
}

// ポーリングを安全に終了させる
function finalizePolling(timeout_handler_class, timeout_handler_function) {
	polling_status = false;
	if (polling_request !== null) {
		polling_request.abort();  // polling_status = falseなので、errorが発生しても再接続を行わない
	}
	polling_request = null;
	polling_id = null;

	if (timeout_handler_function !== null) {
		var fd = new FormData();
		fd.append("class", timeout_handler_class);
		fd.append("function", timeout_handler_function);
		appcon("app.php", fd)
	}

	// console log
	append_debug_window("Polling stoped.");
}

// ポーリングのタイムアウトを延長させる
$(document).on('mousemove keydown click touchstart', function () {
	polling_startTime = Date.now(); //タイムアウトを更新
});
