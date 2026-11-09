{include file="_layout.tpl"}
<div class="note-feature-dialog">
{if $missing}
<p>実装時に次の設定の確認が必要です。依頼はこのまま登録できます。</p><ul>{foreach $missing as $label}<li>{$label|escape}</li>{/foreach}</ul>
{if $can_configure}<button type="button" class="ajax-link" data-class="note_features" data-function="configure" data-db_id="{$note.id}" data-token="{$token|escape}" data-feature_id="{$feature.id|escape}">システム設定を開く</button>{else}<p>管理者に設定を依頼してください。</p>{/if}
{/if}
<form id="note_feature_editor" onsubmit="return false;">
<input type="hidden" name="db_id" value="{$note.id}"><input type="hidden" name="token" value="{$token|escape}"><input type="hidden" name="feature_id" value="{$feature.id|escape}">
<p class="error_message error_db_id"></p><p class="error_message error_token"></p><p class="error_message error_feature_id"></p>
<p class="error_message error_options"></p>
{if $feature.id == 'single_shop'}<p>商品欄に商品名と価格を追記すると、複数の商品を指定できます。</p>{/if}
<label for="note_feature_request_text">依頼内容（編集できます）</label><textarea id="note_feature_request_text" name="request_text" rows="9" style="width:100%;box-sizing:border-box;">{$default_request|escape}</textarea><p class="error_message error_request_text"></p>
<p>「{$note.title|escape}」の情報と依頼内容を、プロジェクトサポートの新しいタスクとして登録します。</p><p class="error_message error_request"></p>
<div style="display:flex;justify-content:flex-end;margin-top:16px;"><button type="button" class="ajax-link" data-class="note_features" data-function="submit" data-form="note_feature_editor">依頼を登録</button></div></form>

</div>
