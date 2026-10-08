<form id="dsp_definition_form">
<input type="hidden" name="id" value="{$data.id|escape}">
{fields_form_original name="note_id" type="dropdown" title={t key="dsp.note"} options_arr=$dsp_notes value=$data.note_id}
{fields_form_original name="operation" type="dropdown" title={t key="dsp.operation"} options_arr=$dsp_operations value=$data.operation}
{fields_form_original name="mode" type="dropdown" title={t key="dsp.mode"} options_arr=$dsp_modes value=$data.mode}
{fields_form_original name="conditions" type="textarea" title={t key="dsp.conditions"} value=$data.conditions}
<p>{t key="dsp.conditions_help"}</p>
<button class="ajax-link" data-class="dsp" data-function="save" data-form="dsp_definition_form">{t key="common.save"}</button>
</form>
{literal}<script>
(function(){var f=document.getElementById('dsp_definition_form');var mode=f.querySelector('[name="mode"]');var area=f.querySelector('.field_conditions');if(!mode||!area)return;function sync(){area.style.display=mode.value==='custom'?'':'none';}$(mode).on('change',sync);sync();})();
</script>{/literal}
