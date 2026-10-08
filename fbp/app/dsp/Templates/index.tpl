<p>{t key="dsp.default_help"}</p>
<p>{t key="dsp.creation_help"}</p>
<form id="dsp_filter">
<div class="dsp-search-fields">
{fields_form_original name="note_id" type="dropdown" title={t key="dsp.note"} options_arr=$dsp_note_filters value=$filter.note_id|default:'' item_margin_top="0"}
{fields_form_original name="operation" type="dropdown" title={t key="dsp.operation"} options_arr=$dsp_operation_filters value=$filter.operation|default:'' item_margin_top="0"}
{fields_form_original name="mode" type="dropdown" title={t key="dsp.mode"} options_arr=$dsp_mode_filters value=$filter.mode|default:'' item_margin_top="0"}
{fields_form_original name="keyword" type="text" title={t key="dsp.keyword"} value=$filter.keyword|default:'' item_margin_top="0"}
</div>
<div class="dsp-search-actions">
<button class="ajax-link" data-class="dsp" data-function="page" data-form="dsp_filter">{t key="common.search"}</button>
</div>
</form>
<div class="dsp-list-actions"><button class="ajax-link" data-class="dsp" data-function="add">{t key="common.add"}</button></div>
<div class="dsp-table-container"><table class="fbp_table" id="dsp_list"><thead><tr><th>{t key="dsp.note"}</th><th>{t key="dsp.operation"}</th><th>{t key="dsp.mode"}</th><th>{t key="dsp.conditions"}</th><th></th></tr></thead><tbody>
{foreach $items as $item}<tr><td>{$item.note_name|escape}<small> ({$item.table_name|escape})</small></td><td>{$dsp_operations[$item.operation]|escape}</td><td>{$dsp_modes[$item.mode]|escape}</td><td>{$item.conditions|escape|nl2br}</td><td>
<button class="ajax-link" data-class="dsp" data-function="edit" data-id="{$item.id}">{t key="common.edit"}</button>
<button class="ajax-link" data-class="dsp" data-function="delete" data-id="{$item.id}">{t key="common.delete"}</button>
</td></tr>{foreachelse}<tr><td colspan="5">{t key="dsp.empty"}</td></tr>{/foreach}
</tbody></table></div>

{literal}<style>
#tabs-dsp .dsp-search-fields { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; align-items:end; }
#tabs-dsp .dsp-search-fields > div { min-width:0; }
#tabs-dsp .dsp-search-fields input, #tabs-dsp .dsp-search-fields select { width:100%; box-sizing:border-box; }
#tabs-dsp .dsp-search-actions, #tabs-dsp .dsp-list-actions { display:flex; justify-content:flex-end; clear:both; margin-top:12px; }
#tabs-dsp .dsp-search-actions button, #tabs-dsp .dsp-list-actions button { float:none; }
#tabs-dsp .dsp-table-container { display:block; clear:both; width:100%; overflow-x:auto; margin-top:12px; }
#tabs-dsp #dsp_list { width:100%; }
@media (max-width:700px) { #tabs-dsp .dsp-search-fields { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>{/literal}
