<div id="passkey-manager" data-endpoint="{$passkey_manager.endpoint|escape}" data-csrf="{$passkey_manager.csrf|escape}" data-window="{$passkey_manager.window|escape}" data-cookie-path="{$passkey_manager.cookie_path|escape}">
<p class="pk-message" role="status" aria-live="polite"></p>
<section class="pk-list">
<p>不要なパスキーを削除できます。</p>
{if $passkey_manager.password_disabled}<p>ID/Passwordログインは停止中です。各登録先の最後のパスキーは削除できません。</p>{/if}
{foreach $passkey_manager.keys as $key}
<div class="pk-key" data-key-id="{$key.id|escape}">
<strong>{$key.label|escape}</strong>
<p class="pk-details">パスキー #{$key.id|escape} ／ {$key.rp_id|escape}<br>{if $key.current}このサイト用{else}別の登録先用{/if}<br>登録：{$key.created_at|escape}<br>最終利用：{$key.last_used_at|default:'未使用'|escape}</p>
{if $key.status === 0}<div class="button_row"><button type="button" data-pk-revoke>このパスキーを削除</button></div>{else}<p class="pk-revoked">失効済み</p>{/if}
</div>
{foreachelse}<p>パスキーはまだ登録されていません。</p>{/foreach}
</section>
<section class="pk-confirm" hidden>
<p>このパスキーを削除すると、この鍵でログインできなくなります。削除の前に本人確認を行ってください。</p>
{if $passkey_manager.password_disabled}
<div class="button_row"><button type="button" data-pk-cancel>キャンセル</button><button type="button" data-pk-confirm>パスキーで本人確認して削除する</button></div>
{else}
<form class="pk-password-form">
<label>現在のパスワード<input name="password" type="password" autocomplete="current-password" required></label>
<p class="error error_password"></p>
<div class="button_row"><button type="button" data-pk-cancel>キャンセル</button><button type="submit">本人確認して削除する</button></div>
</form>
{/if}
</section>
</div>
<style>{literal}
#passkey-manager [hidden]{display:none!important}
#passkey-manager .pk-message:empty{display:none}
#passkey-manager .pk-message{white-space:pre-line;margin:12px 0}
#passkey-manager .pk-key{border-top:1px solid #ddd;padding:18px 0;overflow-wrap:anywhere}
#passkey-manager .pk-details{font-size:13px;color:#666;line-height:1.8;margin:10px 0}
#passkey-manager .button_row{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:12px;margin-top:18px}
#passkey-manager button{float:none;margin:0;white-space:normal;max-width:100%}
#passkey-manager input{display:block;width:100%;box-sizing:border-box;margin-top:8px}
#passkey-manager .pk-confirm{margin:20px 0}
{/literal}</style>
<script>{literal}
(()=>{'use strict';
const root=document.getElementById('passkey-manager');
if(!root)return;
const list=root.querySelector('.pk-list'),confirm=root.querySelector('.pk-confirm'),message=root.querySelector('.pk-message'),password=root.querySelector('[name=password]'),passwordError=root.querySelector('.error_password');
let pending=null,busy=false;
function report(text,error=false){message.textContent=text;message.classList.toggle('error',error);}
function reset(){pending=null;if(password)password.value='';if(passwordError)passwordError.textContent='';confirm.hidden=true;list.hidden=false;}
async function api(action,values={}){
 document.cookie='windowID='+encodeURIComponent(root.dataset.window)+'; Path='+root.dataset.cookiePath+'; Secure; SameSite=Lax';
 const data=new FormData();data.set('action',action);data.set('csrf',root.dataset.csrf);data.set('_windowcode',root.dataset.window);
 for(const[k,v]of Object.entries(values))data.set(k,v);
 const response=await fetch(root.dataset.endpoint,{method:'POST',body:data,credentials:'same-origin',cache:'no-store'});
 const result=await response.json();if(!result.ok)throw new Error(result.message||'操作できませんでした。');return result;
}
const decode=s=>Uint8Array.from(atob(s.replace(/-/g,'+').replace(/_/g,'/')),c=>c.charCodeAt(0));
const encode=b=>{let s='';for(const n of new Uint8Array(b))s+=String.fromCharCode(n);return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');};
async function verifyPasskey(){
 const{options:o}=await api('confirm_options');o.challenge=decode(o.challenge);
 if(o.allowCredentials)o.allowCredentials=o.allowCredentials.map(c=>({...c,id:decode(c.id)}));
 const c=await navigator.credentials.get({publicKey:o}),r=c.response;
 await api('confirm_verify',{credential:JSON.stringify({id:c.id,rawId:encode(c.rawId),type:c.type,response:{clientDataJSON:encode(r.clientDataJSON),authenticatorData:encode(r.authenticatorData),signature:encode(r.signature),userHandle:r.userHandle?encode(r.userHandle):null}})});
}
async function remove(){
 if(busy||!pending)return;busy=true;const key=pending;report('');if(passwordError)passwordError.textContent='';
 root.querySelectorAll('button').forEach(b=>b.disabled=true);
 try{
  if(password){const value=password.value;password.value='';await api('confirm',{password:value});}else await verifyPasskey();
  await api('revoke',{id:key.dataset.keyId});
  key.querySelector('[data-pk-revoke]')?.remove();
  const status=document.createElement('p');status.className='pk-revoked';status.textContent='失効済み';key.append(status);
  reset();report('パスキーを削除しました。');
 }catch(e){
  const text=e.name==='NotAllowedError'?'本人確認がキャンセルされたか、時間切れになりました。もう一度お試しください。':e.message;
  if(passwordError)passwordError.textContent=text;else report(text,true);
 }finally{busy=false;root.querySelectorAll('button').forEach(b=>b.disabled=false);}
}
root.querySelectorAll('[data-pk-revoke]').forEach(button=>button.addEventListener('click',()=>{if(busy)return;pending=button.closest('[data-key-id]');list.hidden=true;confirm.hidden=false;report('');(password||root.querySelector('[data-pk-confirm]')).focus();}));
root.querySelector('[data-pk-cancel]').addEventListener('click',()=>{if(!busy){reset();report('');}});
root.querySelector('.pk-password-form')?.addEventListener('submit',event=>{event.preventDefault();remove();});
root.querySelector('[data-pk-confirm]')?.addEventListener('click',()=>remove());
})();
{/literal}</script>
