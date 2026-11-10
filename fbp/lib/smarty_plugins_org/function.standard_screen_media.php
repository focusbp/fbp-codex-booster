<?php
require_once __DIR__ . '/../StandardScreenMedia.php';

/** Select the private route only in a server-created Standard Screen context. */
function smarty_function_standard_screen_media(array $params, Smarty_Internal_Template $template): string {
    $ctl = $params['ctl'];
    $context = $template->getTemplateVars('_standard_screen_media_context');
    $field = $params['field'] ?? [];
    $result = null;
    if ($ctl->get_classname() === 'db_exe' && is_array($context)
        && in_array($field['type'] ?? '', ['file','image'], true)) {
        $result = StandardScreenMedia::display($ctl, $context, $field, $params['row'] ?? []);
    }
    $template->assign('managed_media', $result);
    return '';
}
