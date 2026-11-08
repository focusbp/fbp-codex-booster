<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ワンタイムコードでログイン</title></head>
<body>
<main id="onetimecode-app" data-window="{$onetimecode_window|escape}" data-cookie-path="{$onetimecode_cookie_path|escape}">
<h1>ワンタイムコードでログイン</h1>
{if $onetimecode_error}<p role="alert">コードを確認できませんでした。新しいコードを取得してお試しください。</p>{/if}
<form method="post" action="{$onetimecode_url|escape}">
<input type="hidden" name="csrf_token" value="{$onetimecode_csrf|escape}">
<input type="hidden" name="_windowcode" value="{$onetimecode_window|escape}">
<label>ワンタイムコード <input name="code" type="password" autocomplete="off" required maxlength="43"></label>
<button type="submit">ログイン</button>
</form>
</main>
<script>
{literal}
(()=>{
const root=document.getElementById('onetimecode-app');
function selectWindow(){
 try{sessionStorage.setItem('windowID',root.dataset.window);}catch(e){}
 document.cookie='windowID='+encodeURIComponent(root.dataset.window)+'; Path='+root.dataset.cookiePath+'; Secure; SameSite=Lax';
}
selectWindow();
root.querySelector('form').addEventListener('submit',selectWindow);
})();
{/literal}
</script>
</body></html>
