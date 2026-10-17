<form class="excel-editor-form" data-saved-type="{$excel_column.type|escape}" data-existing="{if $excel_column.generated}1{else}0{/if}" onsubmit="return false;">
<input type="hidden" name="db_id" value="{$db_id}"><input type="hidden" name="excel_token" value="{$excel_token}">
<label>タイトル<input type="text" name="title" value="{$excel_column.title|escape}" maxlength="255"></label><p class="error_title error_message"></p>
<label>型{html_options name="type" options=$excel_types selected=$excel_column.type}</label><p class="error_type error_message"></p>
<p class="excel-type-warning" style="display:none">型を変更すると既存データが破損する場合があります</p>
{if $excel_column.mode == 'function'}
<input type="hidden" name="mode" value="function"><p class="excel-support-function">プロジェクトサポートで自動計算を設定済みです。</p>
{else}
<div class="excel-mode-settings"><label>入力方法{html_options name="mode" options=['input'=>'入力','calculation'=>'計算'] selected=$excel_column.mode}</label><p class="error_mode error_message"></p></div>
{/if}
<div class="excel-calculation-settings">
<label>計算式<input type="text" name="formula" value="{$excel_column.formula|escape}" placeholder="例: (A + B) * 1.1" maxlength="1024"></label><p class="error_formula error_message"></p>
<div class="excel-digits-settings"><label>小数桁数<input name="digits" type="number" min="0" max="99" value="{$excel_column.digits}"></label><p class="error_digits error_message"></p></div>
<label>丸め方法{html_options name="rounding" options=['round'=>'四捨五入','truncate'=>'切り捨て','away'=>'切り上げ'] selected=$excel_column.rounding}</label><p class="error_rounding error_message"></p>
</div>
<div class="excel-dropdown-settings">
 <div class="excel-options-list" aria-label="選択肢一覧">
 {foreach $excel_option_rows as $option}<div class="excel-option-row" data-value="{$option.value|escape}">
  <span class="excel-option-drag" draggable="true" tabindex="0" role="button" aria-label="選択肢を移動" title="ドラッグで移動">⠿</span>
  <input type="text" value="{$option.label|escape}" aria-label="選択肢の名称" maxlength="255">
  <button type="button" class="excel-option-delete">削除</button>
 </div>{/foreach}
 </div>
 <label for="excel-option-label">選択肢の追加</label>
 <div class="excel-option-add-row"><input type="text" id="excel-option-label" name="option_label" aria-label="追加する選択肢" maxlength="255" autocomplete="off"><button type="button" class="excel-option-add">登録</button></div>
 <p class="error_option_label error_message"></p><p class="error_options error_message" role="alert"></p>
</div>
<p class="error_excel_error error_message" role="alert"></p>
<div class="button_row"><button class="ajax-link excel-confirm" data-class="db_exe" data-function="excel_column_save">確定</button></div>
</form>
{include file="excel_editor_style.tpl"}
<script>{literal}
(function(){
 const form=document.querySelector('.excel-editor-form');if(!form)return;
 const list=form.querySelector('.excel-options-list'),label=form.elements.option_label,error=form.querySelector('.error_options');
 const supportFunction=form.elements.mode.value==='function';
 const update=()=>{
  const type=form.elements.type.value,numeric=['number','float'].includes(type),mode=form.elements.mode;
  form.querySelector('.excel-type-warning').style.display=form.dataset.existing==='1'&&type!==form.dataset.savedType?'':'none';
  if(!supportFunction&&!numeric&&mode.value!=='input'){
   mode.value='input';if(typeof refresh_original_searchable_select_display==='function')refresh_original_searchable_select_display($(mode));
  }
  const modeSettings=form.querySelector('.excel-mode-settings');if(modeSettings)modeSettings.style.display=numeric?'':'none';
  form.querySelector('.excel-calculation-settings').style.display=!supportFunction&&numeric&&mode.value==='calculation'?'':'none';
  form.querySelector('.excel-digits-settings').style.display=type==='number'?'none':'';
  if(type==='number')form.elements.digits.value='0';
  form.querySelector('.excel-dropdown-settings').style.display=type==='dropdown'?'':'none';
 };
 $(form.elements.mode).on('change',update);$(form.elements.type).on('change',update);update();
 let pending=false,dragged=null,sequence=0,confirmAfterSave=false;const queue=[];
 const request=(action,value='',text='',order=[])=>{
  if(pending){if(!queue.some(item=>JSON.stringify(item)===JSON.stringify([action,value,text,order])))queue.push([action,value,text,order]);return;}
  error.textContent='';
  if((action==='add'||action==='edit')&&!text.trim()){error.textContent='選択肢を入力してください。';return;}
  const fd=new FormData(form);fd.set('class','db_exe');fd.set('function','excel_column_save');fd.set('option_action',action);fd.set('option_value',value);fd.set('option_label',text);fd.set('option_order',JSON.stringify(order));
  pending=true;const requestId=++sequence;form.setAttribute('aria-busy','true');
  appcon('app.php',fd).always(()=>{if(pending&&sequence===requestId){pending=false;queue.length=0;confirmAfterSave=false;form.removeAttribute('aria-busy');error.textContent='保存結果を確認できません。項目設定を開き直してください。';}});
 };
 const move=(source,target)=>{
  const order=Array.from(list.children,r=>r.dataset.value),from=order.indexOf(source),to=order.indexOf(target);
  if(from<0||to<0||from===to)return;order.splice(to,0,order.splice(from,1)[0]);request('move','','',order);
 };
 const bind=row=>{
  const handle=row.querySelector('.excel-option-drag'),input=row.querySelector('input');input.defaultValue=input.value;
  input.addEventListener('change',()=>{if(input.value!==input.defaultValue)request('edit',row.dataset.value,input.value);});
  row.querySelector('.excel-option-delete').onclick=()=>request('delete',row.dataset.value);
  input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.isComposing){e.preventDefault();e.stopPropagation();input.blur();}});
  handle.addEventListener('dragstart',e=>{if(pending){e.preventDefault();return;}dragged=row.dataset.value;e.dataTransfer.setData('text/plain',dragged);});
  handle.addEventListener('dragend',()=>{dragged=null;list.querySelectorAll('.excel-option-over').forEach(r=>r.classList.remove('excel-option-over'));});
  row.addEventListener('dragover',e=>{if(dragged!==null&&!pending){e.preventDefault();row.classList.add('excel-option-over');}});
  row.addEventListener('dragleave',()=>row.classList.remove('excel-option-over'));
  row.addEventListener('drop',e=>{if(dragged===null)return;e.preventDefault();move(dragged,row.dataset.value);dragged=null;});
  handle.addEventListener('keydown',e=>{if(!['ArrowUp','ArrowDown'].includes(e.key))return;e.preventDefault();e.stopPropagation();const target=e.key==='ArrowUp'?row.previousElementSibling:row.nextElementSibling;if(target)move(row.dataset.value,target.dataset.value);});
  let touchTarget=null;
  handle.addEventListener('touchstart',()=>{if(!pending){dragged=row.dataset.value;touchTarget=null;}},{passive:true});
  handle.addEventListener('touchmove',e=>{if(dragged===null)return;e.preventDefault();const t=e.touches[0];touchTarget=document.elementFromPoint(t.clientX,t.clientY)?.closest('.excel-option-row');},{passive:false});
  handle.addEventListener('touchend',()=>{if(dragged!==null&&touchTarget&&list.contains(touchTarget))move(dragged,touchTarget.dataset.value);dragged=null;touchTarget=null;});
  handle.addEventListener('touchcancel',()=>{dragged=null;touchTarget=null;});
 };
 Array.from(list.children).forEach(bind);
 form.querySelector('.excel-option-add').onclick=()=>request('add','',label.value);
 label.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.isComposing){e.preventDefault();e.stopPropagation();form.querySelector('.excel-option-add').click();}});
 form.addEventListener('click',e=>{if(e.target.closest('.excel-confirm')&&(pending||queue.length)){e.preventDefault();e.stopImmediatePropagation();confirmAfterSave=true;}},true);
 form.addEventListener('excel-options-result',e=>{
  pending=false;form.removeAttribute('aria-busy');
  if(!e.detail.ok){queue.length=0;confirmAfterSave=false;return;}
  if(typeof e.detail.type==='string'){form.dataset.savedType=e.detail.type;form.dataset.existing=e.detail.generated?'1':'0';update();}
  const focused=document.activeElement,focusedValue=focused?.closest('.excel-option-row')?.dataset.value,selection=focused?.tagName==='INPUT'?[focused.selectionStart,focused.selectionEnd]:null;
  const drafts=new Map(Array.from(list.children,r=>{const input=r.querySelector('input');return [r.dataset.value,{saved:input.defaultValue,draft:input.value}];}));
  list.replaceChildren();
  e.detail.rows.forEach(option=>{
   const row=document.createElement('div');row.className='excel-option-row';row.dataset.value=option.value;
   const handle=document.createElement('span');handle.className='excel-option-drag';handle.draggable=true;handle.tabIndex=0;handle.setAttribute('role','button');handle.setAttribute('aria-label','選択肢を移動');handle.title='ドラッグで移動';handle.textContent='⠿';
   const input=document.createElement('input');input.type='text';input.maxLength=255;input.setAttribute('aria-label','選択肢の名称');input.value=option.label;
   const remove=document.createElement('button');remove.type='button';remove.className='excel-option-delete';remove.textContent='削除';
   row.append(handle,input,remove);list.append(row);bind(row);
   const draft=drafts.get(option.value);if(draft&&draft.saved===option.label)input.value=draft.draft;
   if(focusedValue===option.value){input.focus();if(selection)input.setSelectionRange(...selection);}
  });
  if(e.detail.action==='add'){label.value='';label.focus();}
  if(queue.length)request(...queue.shift());else if(confirmAfterSave){confirmAfterSave=false;form.querySelector('.excel-confirm').click();}
 });
})();
{/literal}</script>
<style>{literal}
.excel-editor-form .excel-type-warning{font-size:11px;color:#6b7280;line-height:1.5;margin:-4px 0 10px}.excel-dropdown-settings{background:#eff6ff;border:1px solid #dbeafe;border-radius:8px;padding:12px;margin-top:12px}.excel-options-list{max-height:210px;overflow:auto;margin:6px 0 14px}.excel-option-row{display:grid;grid-template-columns:18px minmax(0,1fr) 44px;gap:5px;align-items:center;padding:5px 0;border-bottom:1px solid #e5e7eb}.excel-editor-form .excel-option-row input{margin:0}.excel-editor-form .excel-option-row button,.excel-editor-form .excel-option-add-row button{margin:0;padding:5px 2px;font-size:11px;min-width:0;width:44px;box-sizing:border-box}.excel-option-add-row{display:grid;grid-template-columns:minmax(0,1fr) 44px;gap:5px;align-items:center}.excel-editor-form .excel-option-add-row input{margin:0}.excel-option-drag{cursor:grab;color:#9ca3af;touch-action:none;user-select:none}.excel-option-over{box-shadow:inset 0 2px #4f7dc1}.excel-editor-form[aria-busy=true] .excel-options-list{opacity:.65}
{/literal}</style>
