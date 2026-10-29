{include file="excel_preview.tpl"}
{if $testserver || $setting.show_developer_panel == 1}
<div class="db_edit_button_area"><button class="ajax-link" invoke-class="db" invoke-function="edit" data-id="{$db_id}" data-mode="database" title="ノート設定" aria-label="ノート設定"><span class="material-symbols-outlined">description</span></button></div>
{/if}
<div class="excel-grid" data-db-id="{$db_id}" data-move-token="{$excel_move_token}" data-column-move-token="{$excel_column_move_token}" data-column-width-token="{$excel_column_width_token}" data-min-column-width="{$excel_min_column_width}" data-max-column-width="{$excel_max_column_width}">
 <div class="excel-toolbar">
  <div>{foreach $additionals as $a}<button class="ajax-link {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}">{if $a.button_type == 0}{$a.button_title|escape}{else}<span class="material-symbols-outlined">{$a.button_title|escape}</span>{/if}</button>{/foreach}</div>
 </div>
 <p class="excel-width-status" role="alert" hidden></p>
 <div class="excel-scroll">
  <table class="excel-table" role="grid" style="width:{$excel_table_width}px"><colgroup><col style="width:66px">{foreach $excel_fields as $field}<col data-column="{$field.parameter_name}" style="width:{$field.column_width}px">{/foreach}</colgroup><thead><tr><th class="excel-corner"></th>{foreach $excel_fields as $field}<th data-column="{$field.parameter_name}">{if !$excel_developer_mode}<span class="excel-col-drag" draggable="true" tabindex="0" role="button" aria-label="{$field.parameter_name|upper}列を移動" title="ドラッグで列を移動">⠿</span>{/if}<button class="ajax-link excel-letter" data-class="db_exe" data-function="excel_column" data-db_id="{$db_id}" data-column="{$field.parameter_name}">{$field.parameter_name|upper}</button>{if !$excel_developer_mode}<span class="excel-col-resize" tabindex="0" role="separator" aria-orientation="vertical" aria-label="{$field.parameter_name|upper}列の幅" aria-valuemin="{$excel_min_column_width}" aria-valuemax="{$excel_max_column_width}" aria-valuenow="{$field.column_width}" title="ドラッグで幅を変更、ダブルクリックで自動調整"></span>{/if}</th>{/foreach}</tr>
  <tr class="excel-titles"><th class="excel-number" aria-label="タイトル"></th>{foreach $excel_fields as $field}<th><button class="ajax-link excel-title" data-class="db_exe" data-function="excel_column" data-db_id="{$db_id}" data-column="{$field.parameter_name}">{$excel_columns[$field.parameter_name].title|default:''|escape}</button></th>{/foreach}</tr></thead>
  <tbody>{foreach $excel_rows as $row}<tr data-row-id="{$row.id}"><th class="excel-number"><span class="excel-drag" draggable="true" tabindex="0" role="button" title="ドラッグで移動" aria-label="行を移動">⠿</span><button class="ajax-link" data-class="db_exe" data-function="excel_row_menu" data-db_id="{$db_id}" data-row_id="{$row.id}">{$row@iteration}</button></th>
   {foreach $excel_fields as $field}<td tabindex="0" class="excel-cell {if $field.type == 'number' || $field.type == 'float'}excel-numeric{/if} {if $field.excel_mode != 'input'}excel-computed{/if}" data-column="{$field.parameter_name}" data-row-id="{$row.id}" data-mode="{$field.excel_mode}" aria-label="{$field.parameter_name|upper} {$field.parameter_title|escape}">
    {if isset($row._errors[$field.parameter_name])}<span class="excel-error">ERROR</span>{else}{fields_view_direct field_group=[$field] data=$row}{/if}
   </td>{/foreach}</tr>{/foreach}
   <tr><th class="excel-number"><button type="button" class="excel-add-row" data-token="{$excel_add_row_token}" title="行を追加" aria-label="行を追加">＋</button></th>{foreach $excel_fields as $field}<td tabindex="0" class="excel-cell excel-empty {if $field.excel_mode != 'input'}excel-computed{/if}" data-column="{$field.parameter_name}" data-row-id="0" data-mode="{$field.excel_mode}"></td>{/foreach}</tr>
  </tbody></table>
 </div>
 {if !$excel_developer_mode}<div style="text-align:right;margin:12px 0"><a href="#" class="ajax-link" data-class="db_exe" data-function="excel_convert" data-db_id="{$db_id}">データベース形式に変換する</a></div>{/if}
</div>
<style>{literal}
.excel-grid{clear:both;--grid-line:#e5e7eb;color:#303641}.excel-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:8px 0 14px}.excel-current{font-size:13px;font-weight:600;margin-left:14px}.excel-scroll{overflow:auto;max-height:72vh;border:1px solid var(--grid-line);background:#fff}.excel-table{border-collapse:separate;border-spacing:0;table-layout:fixed}.excel-table th,.excel-table td{box-sizing:border-box;border-right:1px solid var(--grid-line);border-bottom:1px solid var(--grid-line);height:36px;padding:6px 10px;font-size:13px;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.excel-table thead th{position:sticky;top:0;background:#f7f8fa;z-index:3;padding:0;height:30px;color:#697180}.excel-table thead .excel-titles th{top:30px;background:#fff;height:38px;color:#353b45}.excel-table .excel-number,.excel-table .excel-corner{position:sticky;left:0;min-width:66px;width:66px;max-width:66px;background:#f7f8fa;z-index:2;padding:0;text-align:center}.excel-table thead .excel-number,.excel-table .excel-corner{z-index:5}.excel-grid .excel-letter,.excel-grid .excel-title,.excel-grid .excel-number button{border:0;background:transparent;box-shadow:none;color:inherit;cursor:pointer;padding:6px;margin:0;width:100%;font-size:12px;min-height:28px}.excel-grid .excel-title{font-weight:600;text-align:left;padding:8px 10px;height:38px}.excel-grid .excel-number button{width:38px}.excel-col-resize{position:absolute;right:0;top:0;bottom:0;width:8px;cursor:col-resize;touch-action:none;z-index:4}.excel-col-resize:hover,.excel-col-resize:focus{background:#cfe2ff;outline:none}.excel-grid.excel-resizing{user-select:none;cursor:col-resize}.excel-width-status{font-size:12px;color:#b42318;margin:4px 0 8px}.excel-col-drag{position:absolute;left:3px;top:5px;color:#b1b7c0;cursor:grab;touch-action:none;z-index:1;padding:2px}.excel-table th.excel-col-over{box-shadow:inset 3px 0 #4f7dc1}.excel-drag{cursor:grab;touch-action:none;display:inline-block;color:#b1b7c0;padding:4px;user-select:none}.excel-cell{cursor:pointer}.excel-cell:focus{outline:2px solid #4f7dc1;outline-offset:-2px}.excel-table .excel-numeric{text-align:right}.excel-table .excel-computed{background:#f5f7fa}.excel-cell p{margin:0!important;padding:0!important}.excel-error{color:#b42318;font-size:11px}.excel-empty{height:36px}.excel-table tr.excel-drag-over td{border-top:2px solid #4f7dc1}
{/literal}</style>
<script>{literal}
(function(){
 const root=document.querySelector('.excel-grid'); if(!root)return;
 root.addEventListener('click',e=>{const el=e.target.closest('.excel-cell,.excel-letter,.excel-title,.excel-number');if(el){const r=el.getBoundingClientRect();window.fbpExcelAnchor={left:r.left,top:r.top,bottom:r.bottom};}},true);
 const send=(fn,values)=>{if(fn==='excel_cell'){const el=root.querySelector('.excel-cell[data-column="'+values.column+'"][data-row-id="'+values.row_id+'"]');if(el)window.fbpExcelAnchor=el.getBoundingClientRect().toJSON();}const fd=new FormData();fd.append('class','db_exe');fd.append('function',fn);fd.append('db_id',root.dataset.dbId);Object.entries(values).forEach(([k,v])=>fd.append(k,v));appcon('app.php',fd);};
 const addRow=root.querySelector('.excel-add-row');
 addRow.addEventListener('click',()=>{
  if(addRow.disabled)return;addRow.disabled=true;
  const scrollLeft=root.querySelector('.excel-scroll').scrollLeft,fd=new FormData();
  fd.set('class','db_exe');fd.set('function','excel_row_add');fd.set('db_id',root.dataset.dbId);fd.set('excel_token',addRow.dataset.token);
  appcon('app.php',fd).always(()=>{
   addRow.disabled=false;const current=document.querySelector('.excel-grid');
   if(current&&current!==root&&current.dataset.dbId===root.dataset.dbId){const area=current.querySelector('.excel-scroll');area.scrollLeft=scrollLeft;area.scrollTop=area.scrollHeight;}
  });
 });
 const table=root.querySelector('.excel-table'),minWidth=Number(root.dataset.minColumnWidth),maxWidth=Number(root.dataset.maxColumnWidth);
 const widthStatus=root.querySelector('.excel-width-status');let resizing=null,pendingWidth=null;
 const columnElement=name=>table.querySelector('col[data-column="'+name+'"]');
 const columnWidth=name=>parseInt(columnElement(name).style.width,10);
 const applyWidth=(name,width)=>{
  width=Math.max(minWidth,Math.min(maxWidth,Math.round(width)));columnElement(name).style.width=width+'px';
  root.querySelector('th[data-column="'+name+'"] .excel-col-resize').setAttribute('aria-valuenow',String(width));
  table.style.width=Array.from(table.querySelectorAll('colgroup col')).reduce((sum,col)=>sum+parseInt(col.style.width,10),0)+'px';
  return width;
 };
 const widthError=message=>{widthStatus.textContent=message;widthStatus.hidden=false;};
 const saveWidth=(name,width,previous)=>{
  if(width===previous)return;
  if(pendingWidth){applyWidth(name,previous);return;}
  widthStatus.hidden=true;
  const request={name,previous};pendingWidth=request;
  const fd=new FormData();fd.set('class','db_exe');fd.set('function','excel_column_width');fd.set('db_id',root.dataset.dbId);fd.set('column',name);fd.set('column_width',String(width));fd.set('excel_token',root.dataset.columnWidthToken);
  appcon('app.php',fd).always(()=>{if(pendingWidth===request){applyWidth(name,previous);pendingWidth=null;widthError('列幅の保存結果を確認できません。表を再表示してください。');}});
 };
 root.addEventListener('excel-column-width-result',e=>{
  const result=e.detail,request=pendingWidth;pendingWidth=null;
  if(result.ok){applyWidth(result.column,result.column_width);root.dataset.columnWidthToken=result.token;root.dataset.columnMoveToken=result.move_token;widthStatus.hidden=true;}
  else{if(request)applyWidth(request.name,request.previous);widthError(result.error);}
 });
 const autoWidth=name=>{
  const context=document.createElement('canvas').getContext('2d');let width=minWidth;
  const title=root.querySelector('th[data-column="'+name+'"]');
  const index=Array.from(title.parentElement.children).indexOf(title);
  const elements=[table.querySelector('.excel-titles').children[index].querySelector('.excel-title'),...root.querySelectorAll('.excel-cell[data-column="'+name+'"]')];
  elements.forEach(el=>{const style=getComputedStyle(el);context.font=style.font||style.fontSize+' '+style.fontFamily;el.innerText.split(/\r?\n/).forEach(line=>width=Math.max(width,Math.ceil(context.measureText(line).width)+24));});
  return Math.min(maxWidth,width);
 };
 root.querySelectorAll('.excel-col-resize').forEach(handle=>{
  const name=handle.closest('th').dataset.column;
  handle.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();});
  handle.addEventListener('pointerdown',e=>{
   if(e.button!==0||pendingWidth||resizing)return;e.preventDefault();e.stopPropagation();
   resizing={name,pointerId:e.pointerId,x:e.clientX,width:columnWidth(name)};root.classList.add('excel-resizing');handle.setPointerCapture(e.pointerId);
  });
  handle.addEventListener('pointermove',e=>{if(resizing&&resizing.pointerId===e.pointerId)applyWidth(name,resizing.width+e.clientX-resizing.x);});
  handle.addEventListener('pointerup',e=>{if(!resizing||resizing.pointerId!==e.pointerId)return;const before=resizing.width;resizing=null;root.classList.remove('excel-resizing');saveWidth(name,columnWidth(name),before);});
  const cancel=e=>{if(resizing&&resizing.pointerId===e.pointerId){applyWidth(name,resizing.width);resizing=null;root.classList.remove('excel-resizing');}};
  handle.addEventListener('pointercancel',cancel);handle.addEventListener('lostpointercapture',cancel);
  handle.addEventListener('dblclick',e=>{e.preventDefault();e.stopPropagation();if(pendingWidth)return;const before=columnWidth(name);saveWidth(name,applyWidth(name,autoWidth(name)),before);});
  handle.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight'].includes(e.key))return;e.preventDefault();e.stopPropagation();if(pendingWidth)return;const before=columnWidth(name);saveWidth(name,applyWidth(name,before+(e.key==='ArrowRight'?1:-1)*(e.shiftKey?1:10)),before);});
 });
 root.querySelectorAll('.excel-cell').forEach(cell=>{
  const open=()=>send('excel_cell',{column:cell.dataset.column,row_id:cell.dataset.rowId});
  cell.addEventListener('click',open);
  cell.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key==='F2'){e.preventDefault();open();return;}const tr=cell.parentElement;const index=Array.from(tr.children).indexOf(cell);let next;if(e.key==='ArrowRight')next=cell.nextElementSibling;if(e.key==='ArrowLeft')next=cell.previousElementSibling;if(e.key==='ArrowDown')next=tr.nextElementSibling?.children[index];if(e.key==='ArrowUp')next=tr.previousElementSibling?.children[index];if(next?.classList.contains('excel-cell')){e.preventDefault();next.focus();}});
 });
 let draggedColumn=null;
 const columnMove=(name,target,after='0')=>{if(!resizing&&!pendingWidth)send('excel_column_move',{column:name,target_column:target,after,excel_token:root.dataset.columnMoveToken});};
 root.querySelectorAll('.excel-col-drag').forEach(h=>{
  h.addEventListener('dragstart',e=>{if(resizing||pendingWidth){e.preventDefault();return;}draggedColumn=h.closest('th').dataset.column;e.dataTransfer.setData('text/plain',draggedColumn);});
  h.addEventListener('dragend',()=>{draggedColumn=null;root.querySelectorAll('.excel-col-over').forEach(el=>el.classList.remove('excel-col-over'));});
  h.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight'].includes(e.key))return;e.preventDefault();const th=h.closest('th'),target=e.key==='ArrowLeft'?th.previousElementSibling:th.nextElementSibling;if(target?.dataset.column)columnMove(th.dataset.column,target.dataset.column,e.key==='ArrowRight'?'1':'0');});
  let target=null;
  h.addEventListener('touchstart',()=>{if(resizing||pendingWidth)return;draggedColumn=h.closest('th').dataset.column;target=null;},{passive:true});
  h.addEventListener('touchmove',e=>{e.preventDefault();const p=e.touches[0];target=document.elementFromPoint(p.clientX,p.clientY)?.closest('th[data-column]')?.dataset.column||null;},{passive:false});
  h.addEventListener('touchend',()=>{if(draggedColumn&&target&&draggedColumn!==target)columnMove(draggedColumn,target);draggedColumn=null;target=null;});
 });
 root.querySelectorAll('thead tr:first-child th[data-column]').forEach(th=>{
  th.addEventListener('dragover',e=>{if(!draggedColumn)return;e.preventDefault();th.classList.add('excel-col-over');});
  th.addEventListener('dragleave',()=>th.classList.remove('excel-col-over'));
  th.addEventListener('drop',e=>{if(!draggedColumn)return;e.preventDefault();th.classList.remove('excel-col-over');if(draggedColumn!==th.dataset.column)columnMove(draggedColumn,th.dataset.column);draggedColumn=null;});
 });
 let dragged=null;
 root.querySelectorAll('.excel-drag').forEach(h=>h.addEventListener('dragstart',e=>{dragged=h.closest('tr').dataset.rowId;e.dataTransfer.setData('text/plain',dragged);}));
 root.querySelectorAll('.excel-drag').forEach(h=>{
  h.addEventListener('keydown',e=>{if(!['ArrowUp','ArrowDown'].includes(e.key))return;e.preventDefault();const tr=h.closest('tr'),target=e.key==='ArrowUp'?tr.previousElementSibling:tr.nextElementSibling;if(target?.dataset.rowId)send('excel_move',{row_id:tr.dataset.rowId,target_id:target.dataset.rowId,after:e.key==='ArrowDown'?'1':'0',excel_token:root.dataset.moveToken});});
  let target=null;
  h.addEventListener('touchstart',e=>{dragged=h.closest('tr').dataset.rowId;target=null;},{passive:true});
  h.addEventListener('touchmove',e=>{e.preventDefault();const p=e.touches[0];target=document.elementFromPoint(p.clientX,p.clientY)?.closest('tr[data-row-id]')?.dataset.rowId||null;},{passive:false});
  h.addEventListener('touchend',()=>{if(dragged&&target&&dragged!==target)send('excel_move',{row_id:dragged,target_id:target,excel_token:root.dataset.moveToken});dragged=null;target=null;});
 });
 root.querySelectorAll('tbody tr[data-row-id]').forEach(tr=>{tr.addEventListener('dragover',e=>{e.preventDefault();tr.classList.add('excel-drag-over');});tr.addEventListener('dragleave',()=>tr.classList.remove('excel-drag-over'));tr.addEventListener('drop',e=>{e.preventDefault();tr.classList.remove('excel-drag-over');if(dragged&&dragged!==tr.dataset.rowId)send('excel_move',{row_id:dragged,target_id:tr.dataset.rowId,excel_token:root.dataset.moveToken});dragged=null;});});
})();
{/literal}</script>
