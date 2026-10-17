<form class="spreadsheet-note-form" onsubmit="return false;">
<input type="hidden" name="note_token" value="{$note_token|escape}">
<label for="spreadsheet-note-name">メニュー名</label>
<input type="text" id="spreadsheet-note-name" name="menu_name" autocomplete="off" style="display:block;box-sizing:border-box;width:100%;margin-top:8px;">
<p class="error_menu_name error_message" role="alert"></p>
<div style="text-align:right;margin-top:16px;"><button type="button" class="ajax-link" data-class="base" data-function="spreadsheet_note_create">作成</button></div>
</form>
