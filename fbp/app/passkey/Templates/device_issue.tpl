<div id="device-issue" data-endpoint="{$device_endpoint|escape}" data-csrf="{$device_csrf|escape}" data-window="{$device_window|escape}">
<p>発行したURLを<strong style="color:#d32f2f">新しい端末</strong>で開き、登録メールアドレスに届く4桁の確認コードを入力してください。確認コードは画面には表示されません。URLや確認コードは他の人に渡さないでください。</p>
<p>有効期限は24時間です。再発行すると前のURLは無効になります。</p>
<p id="device-issue-error" role="status"></p>
<div id="device-issued" hidden><p>登録用URL</p>{include file="share_url.tpl"}</div>
<div style="text-align:right"><button type="button" id="device-issue-button">URLを発行して確認コードをメール送信する</button></div>
</div>
<script>{literal}
(()=>{const r=document.getElementById('device-issue'),b=document.getElementById('device-issue-button');b.onclick=async()=>{b.disabled=true;document.getElementById('device-issued').hidden=true;document.getElementById('device-issue-error').textContent='';try{const f=new FormData();for(const[k,v]of Object.entries({action:'transfer_issue',csrf:r.dataset.csrf,_windowcode:r.dataset.window}))f.set(k,v);const x=await(await fetch(r.dataset.endpoint,{method:'POST',body:f,credentials:'same-origin',cache:'no-store'})).json();if(!x.ok)throw Error(x.message);const a=r.querySelector('.fbp-share-url-text');a.href=x.url;a.textContent=x.url;const copy=r.querySelector('.fbp-share-url-copy');copy.setAttribute('data-copy',x.url);copy.title='URLをコピー';copy.setAttribute('aria-label','URLをコピー');copy.querySelector('.material-symbols-outlined').textContent='content_copy';const status=r.querySelector('.fbp-share-url-status');status.textContent='';status.classList.remove('is-error');document.getElementById('device-issued').hidden=false;b.textContent='再発行する';}catch(e){document.getElementById('device-issue-error').textContent=e.message;}finally{b.disabled=false;}};})();
{/literal}</script>
