<?php

require_once __DIR__ . '/shared.fields_render.php';

/**
 * {fields_edit
 *    field_group="group_line_member"      // 既存互換: 配列 or 変数名
 *    db="line_member"                     // 新: DB/モデル名 等
 *    fields=["name","furigana",...]       // 新: 文字列配列（Smarty配列/JSON/カンマ区切り対応）
 *    data="line_member"                   // 表示する行データ（配列 or 変数名）
 *    ctl=$ctl                             // 新: コントローラ（オブジェクト or 変数名）。省略時はテンプレ変数 ctl
 *    template="__item_edit.tpl"
 *    base_template_dir=$base_template_dir
 *    item_margin_top="10px"
 *    field_class_prefix="field_"
 *    value_span_class="row_value"         // ※editでも値表示部分を包むクラスがあるなら
 * }
 */
function smarty_function_fields_form_direct(array $params, Smarty_Internal_Template $template) {
	// 基本パラメータ
	$tplName = $params['template'] ?? '__item_edit.tpl';
	$baseDir = $params['base_template_dir'] ?? $template->getTemplateVars('base_template_dir') ?? '';
	$itemMargin = $params['item_margin_top'] ?? '10px';
	$fieldPref = $params['field_class_prefix'] ?? 'field_';
	$valueClass = $params['value_span_class'] ?? 'row_value';
	$show_parent = $params["show_parent"] ?? false;
	
	$keep_row = $template->getTemplateVars("row");

	// row/data 解決（文字列ならテンプレ変数参照）
	$dataParam = $params['data'] ?? null;
	$row = is_string($dataParam) ? $template->getTemplateVars($dataParam) : $dataParam;

	// -------- 1) field_group 直接指定（従来互換） --------
	$group = null;
	if (isset($params['field_group'])) {
		$groupParam = $params['field_group'];
		$group = is_string($groupParam) ? $template->getTemplateVars($groupParam) : $groupParam;
		if (!is_array($group)) {
			trigger_error('{fields_edit} "field_group" must resolve to array.', E_USER_WARNING);
			return '';
		}
	}

	// -------- 2) db + fields 指定（新ルート） --------
	// field_group が未提供の場合のみ、db+fields ルートを使う
	if ($group === null) {
		$db = $params['db'] ?? null;
		$fields = $params['fields'] ?? null;
		if ($db === null || $fields === null) {
			trigger_error('{fields_edit} requires either "field_group" or ("db" and "fields").', E_USER_WARNING);
			return '';
		}

		// fields 正規化（Smarty配列/JSON/カンマ区切りどれでも）
		$fieldsList = normalize_fields_list_for_fields_form_direct($fields);
		if (empty($fieldsList)) {
			trigger_error('{fields_edit} "fields" is empty.', E_USER_WARNING);
			return '';
		}
		
		// parent_id が$fieldListに入っていれば強制的に親のドロップダウンを表示する
		if(in_array("parent_id", $fieldsList)){
			$show_parent = true;
			foreach($fieldsList as $key=>$f){
				if($f == "parent_id"){
					unset($fieldsList[$key]);
				}
			}
		}

		// ctl 解決（引数優先→テンプレ変数 ctl）
		$ctlParam = $params['ctl'] ?? $template->getTemplateVars('_ctl') ?? null;
		$ctl = is_string($ctlParam) ? $template->getTemplateVars($ctlParam) : $ctlParam;

		if (!is_object($ctl)) {
			trigger_error('{fields_edit} controller "ctl" not found (pass ctl=$ctl or assign it as template var).', E_USER_WARNING);
			return '';
		}

		// 可能なら「配列を返す」メソッドを優先使用（例: get_field_settings）
		if (method_exists($ctl, 'get_field_settings')) {
			// 期待戻り値: $group と同等の「フィールド定義配列」
			$group = $ctl->get_field_settings($db, $fieldsList);
		} elseif (method_exists($ctl, 'assign_field_settings')) {
			// 既存の assign_* 型しかない場合は一時変数名でアサイン→取得
			$tmpVar = '__tmp_group_' . uniqid();
			// 典型的なシグネチャ: assign_field_settings($varName, $db, $fieldsArray)
			$ctl->assign_field_settings($tmpVar, $db, $fieldsList,false,$show_parent);
			$group = $template->getTemplateVars($tmpVar);
			// 汚さないように消す（null 代入で実質 unset）
			$template->assign($tmpVar, null);
		} else {
			trigger_error('{fields_edit} Neither get_field_settings nor assign_field_settings exists on $ctl.', E_USER_WARNING);
			return '';
		}

		if (!is_array($group)) {
			trigger_error('{fields_edit} resolved group is not array (from controller).', E_USER_WARNING);
			return '';
		}
	}

	// include ファイル
	$file = $baseDir ? rtrim($baseDir, '/') . '/' . $tplName : $tplName;

	// 出力生成
	$out = '';
	foreach ($group as $field) {
		$paramName = (is_array($field) && isset($field['parameter_name'])) ? (string) $field['parameter_name'] : '';

		// サブテンプレート変数
			// パーツ描画
			$inner = fields_render_partial_template($template, $file, [
				'row' => $row,
				'field' => $field,
			]);

		// ラップ
		$out .= '<div class="'
			. htmlspecialchars($fieldPref, ENT_QUOTES, 'UTF-8')
			. htmlspecialchars($paramName, ENT_QUOTES, 'UTF-8')
			. '"' . (!empty($field['standard_screen_hidden']) ? ' hidden' : '')
            . ' style="margin-top:' . htmlspecialchars($itemMargin, ENT_QUOTES, 'UTF-8') . ';">'
			. '<span class="' . htmlspecialchars($valueClass, ENT_QUOTES, 'UTF-8') . '">'
			. $inner
			. '</span>';

		$out .= '<input type="hidden" name="_field_names[]" value="'
			. htmlspecialchars($paramName, ENT_QUOTES, 'UTF-8')
			. '">';

		$out .= '</div>';
		
	}
	
	//戻す
	$template->assign("row", $keep_row);

	return $out;
}

/**
 * fields パラメータを配列へ正規化
 * - 既に配列 → そのまま
 * - JSON 文字列 ["a","b"] → decode
 * - カンマ区切り "a,b,c" → explode
 */
function normalize_fields_list_for_fields_form_direct($fields) {
	if (is_array($fields))
		return array_values($fields);

	if (is_string($fields)) {
		$s = trim($fields);

		// 先頭が [ なら JSON とみなす
		if (strlen($s) > 0 && $s[0] === '[') {
			$arr = json_decode($s, true);
			if (is_array($arr))
				return array_values($arr);
		}

		// カンマ区切り
		if (strpos($s, ',') !== false) {
			$arr = array_map('trim', explode(',', $s));
			// ⬇ PHP 7.3 対応: アロー関数→無名関数へ
			$arr = array_filter($arr, function ($v) {
				return $v !== '';
			});
			return array_values($arr);
		}

		// 単一文字列1件だけ
		return [$s];
	}

	return [];
}
