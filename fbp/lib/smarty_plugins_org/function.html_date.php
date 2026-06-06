<?php

if (!function_exists('smarty_function_escape_special_chars')) {
	require_once __DIR__ . "/../../lib_ext/smarty-5.8.0/src/functions.php";
}

function smarty_function_html_date($params, Smarty_Internal_Template $template)
{
    $value = null;
    $extra = '';
	$params["type"] = "text";
	$params["class"] = "datepicker";
	$params["data-strtotime"] = "1";
    foreach ($params as $_key => $_val) {
        switch ($_key) {
            case 'value':
                $value = $_val;
                break;
            default:
                if (!is_array($_val)) {
                    $extra .= ' ' . $_key . '="' . smarty_function_escape_special_chars($_val) . '"';
                } else {
                    trigger_error("html_options: extra attribute '{$_key}' cannot be an array", E_USER_NOTICE);
                }
                break;
        }
    }
	
	$setting = $template->getTemplateVars("setting");
	$date_format = !empty($setting["date_format"]) ? (string) $setting["date_format"] : "Y/m/d";
	$timezone = !empty($setting["timezone"]) ? (string) $setting["timezone"] : date_default_timezone_get();

	if(empty($value)){
		$str = "";
	}else if(is_numeric($value)){
		$moto = date_default_timezone_get();
		date_default_timezone_set($timezone);
		$str = date($date_format, (int) $value);
		date_default_timezone_set($moto);
	}else{
		$str = smarty_function_escape_special_chars((string) $value);
	}
	
    return $str;
}
