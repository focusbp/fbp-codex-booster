<div class="base-empty-main">
	{if count($empty_main_sections) > 0}
		{foreach $empty_main_sections as $section}
			<section class="base-empty-main-section">
				<h3>{$section.title|escape}</h3>
				<div class="base-empty-main-grid">
					{foreach $section.items as $item}
						{if !empty($item.project_portal_guide)}<div class="base-project-portal-item">{/if}
						{if $item.type|default:'ajax' == 'external'}
						<a
							class="base-empty-main-card{if $item.badge|default:'' != ''} base-empty-main-card-accent{/if}"
							href="{$item.url|escape}"
							{foreach $item.attributes as $attr_key => $attr_value}
								{$attr_key}="{$attr_value|escape}"
							{/foreach}
						>
							<span>{$item.label|escape}</span>
							{if $item.badge|default:'' != ''}
								<span class="base-empty-main-card-badge">{$item.badge|escape}</span>
							{/if}
						</a>
						{if !empty($item.project_portal_guide)}{include file="project_portal_guide.tpl"}{/if}
						{else}
						<button
							type="button"
							class="ajax-link base-empty-main-card{if $item.badge|default:'' != ''} base-empty-main-card-accent{/if}"
							data-class="{$item.class|escape}"
							data-function="{$item.function|escape}"
							{foreach $item.attributes as $attr_key => $attr_value}
								{$attr_key}="{$attr_value|escape}"
							{/foreach}
						>
							<span>{$item.label|escape}</span>
							{if $item.badge|default:'' != ''}
								<span class="base-empty-main-card-badge">{$item.badge|escape}</span>
							{/if}
						</button>
						{/if}
						{if !empty($item.project_portal_guide)}</div>{/if}
					{/foreach}
				</div>
			</section>
		{/foreach}
	{else}
		<div class="base-empty-main-empty">{$base_empty_i18n.no_items}</div>
	{/if}
</div>
