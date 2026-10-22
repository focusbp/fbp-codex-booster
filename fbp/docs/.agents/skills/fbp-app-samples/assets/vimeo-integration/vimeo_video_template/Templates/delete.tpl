<p>この動画をVimeoから削除します。動画を参照している他の画面でも再生できなくなります。</p>
<form onsubmit="return false;">
    <input type="hidden" name="delete_nonce" value="{$delete_nonce|escape}">
    <div class="button_row" style="text-align:right;">
        <button type="button" class="ajax-link" data-class="vimeo_video_template" data-function="delete_video">動画を削除</button>
    </div>
</form>
