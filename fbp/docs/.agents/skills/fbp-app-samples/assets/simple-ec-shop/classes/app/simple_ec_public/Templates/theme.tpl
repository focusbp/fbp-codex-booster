<style>
body.publicsite-body {
{foreach $sec_theme as $name=>$color}
    --sec-{$name}: {$color};
{/foreach}
    background: var(--sec-bg);
    color: var(--sec-fg);
}
</style>
