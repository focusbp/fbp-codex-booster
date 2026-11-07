{t key="user.email.account_recovery_greeting" name=$data.name}

{t key="user.email.account_recovery_intro"}

{t key="user.login_id"}: {$data.login_id}
{t key="user.email.account_recovery_link_label"}:
{$data.reset_url nofilter}

{t key="user.email.account_recovery_expires" datetime=$data.reset_expires_at}

{t key="user.email.account_recovery_ignore"}

{t key="user.email.account_recovery_regards"}
{$setting.system_name}
