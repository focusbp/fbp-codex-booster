<main class="sample-intake"><h1>{if $completed}受付が完了しました{else}受付結果を確認できません{/if}</h1>
<p>{if $completed}お問い合わせを受け付けました。{else}入力画面からお手続きください。{/if}</p><a href="{$page_url|escape}">入力画面へ</a></main>
