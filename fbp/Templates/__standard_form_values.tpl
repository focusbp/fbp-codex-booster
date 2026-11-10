{* Preserve display-only hidden/read-only values, including checkbox arrays. Not authorization. *}
{if isset($row[$name]) && is_array($row[$name])}
    {foreach $row[$name] as $standard_form_value}
        <input type="hidden" name="{$name|escape}[]" value="{$standard_form_value|escape}">
    {/foreach}
{else}
    <input type="hidden" name="{$name|escape}" value="{$row[$name]|default:''|escape}">
{/if}
