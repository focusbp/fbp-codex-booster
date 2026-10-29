{include file="_layout.tpl"}
<div class="note-feature-dialog">
<form id="note_feature_picker" onsubmit="return false;">
<input type="hidden" name="db_id" value="{$note.id}"><input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_db_id"></p><p class="error_message error_token"></p>
<label for="note_feature_id">追加したい機能</label><select id="note_feature_id" name="feature_id">{foreach $features as $feature}<option value="{$feature.id|escape}">{$feature.title|escape}</option>{/foreach}</select><p class="error_message error_feature_id"></p>
<div style="display:flex;justify-content:flex-end;margin-top:16px;"><button type="button" class="ajax-link" data-class="note_features" data-function="prepare" data-form="note_feature_picker">次へ</button></div></form>

</div>
