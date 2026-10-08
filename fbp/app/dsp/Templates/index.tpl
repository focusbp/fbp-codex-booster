<p>{t key="dsp.default_help"}</p>
<p>{t key="dsp.creation_help"}</p>
<form id="dsp_filter">
{fields_form_original name="note_id" type="dropdown" title={t key="dsp.note"} options_arr=$dsp_note_filters value=$filter.note_id|default:''}
{fields_form_original name="operation" type="dropdown" title={t key="dsp.operation"} options_arr=$dsp_operation_filters value=$filter.operation|default:''}
{fields_form_original name="mode" type="dropdown" title={t key="dsp.mode"} options_arr=$dsp_mode_filters value=$filter.mode|default:''}
{fields_form_original name="keyword" type="text" title={t key="dsp.keyword"} value=$filter.keyword|default:''}
<button class="ajax-link" data-class="dsp" data-function="page" data-form="dsp_filter">{t key="common.search"}</button>
</form>
<button class="ajax-link" data-class="dsp" data-function="add">{t key="common.add"}</button>
<div style="overflow-x:auto"><table class="fbp_table" id="dsp_list"><thead><tr><th>{t key="dsp.note"}</th><th>{t key="dsp.operation"}</th><th>{t key="dsp.mode"}</th><th>{t key="dsp.conditions"}</th><th></th></tr></thead><tbody>
{foreach $items as $item}<tr><td>{$item.note_name|escape}<small> ({$item.table_name|escape})</small></td><td>{$dsp_operations[$item.operation]|escape}</td><td>{$dsp_modes[$item.mode]|escape}</td><td>{$item.conditions|escape|nl2br}</td><td>
<button class="ajax-link" data-class="dsp" data-function="edit" data-id="{$item.id}">{t key="common.edit"}</button>
<button class="ajax-link" data-class="dsp" data-function="delete" data-id="{$item.id}">{t key="common.delete"}</button>
</td></tr>{foreachelse}<tr><td colspan="5">{t key="dsp.empty"}</td></tr>{/foreach}
</tbody></table></div>
