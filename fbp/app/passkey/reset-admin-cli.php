<?php
/** Local emergency recovery entry point. No HTTP/bootstrap work before this guard. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
if (count($argv)!==2 || !preg_match('/^[1-9][0-9]*$/D', $argv[1])) {
    fwrite(STDERR, "Usage: php reset-admin-cli.php <admin-user-id>\n");
    exit(2);
}
$root=__DIR__;
while (!is_file($root.'/fbp/cli.php')) {
    $parent=dirname($root);
    if ($parent===$root) {fwrite(STDERR,"Runtime framework not found.\n");exit(1);}
    $root=$parent;
}
if (str_contains($root,'/projects/') || str_contains($root,'/NetBeansProjects/')) {
    fwrite(STDERR,"Run from the deployed application, not the source workspace.\n");exit(1);
}
fwrite(STDOUT, "対象環境: ".realpath($root)."\n管理者ユーザーID: ".$argv[1]."\n全ドメインのパスキー・既存セッション・再設定トークンを無効にし、ID／Passwordログインを再開します。\nユーザーと現在のパスワードは維持します。\n実行する場合は YES と入力してください: ");
$answer=fgets(STDIN);
if ($answer===false || rtrim($answer,"\r\n")!=='YES') {
    fwrite(STDOUT,"中止しました。変更はありません。\n");exit(0);
}
define('PASSKEY_ADMIN_RECOVERY_CONFIRMED',true);
define('PASSKEY_ADMIN_RECOVERY_USER_ID',(int)$argv[1]);
$argv=[$root.'/fbp/cli.php','app_call','--json='.json_encode(['class'=>'passkey','function'=>'emergency_cli_recovery'])];
$argc=count($argv);
register_shutdown_function(static function () {
    if (!defined('PASSKEY_ADMIN_RECOVERY_COMPLETED')) {
        fwrite(STDERR,"復旧は完了していません。エラーを確認して再実行してください。\n");
        exit(1);
    }
});
chdir($root.'/fbp');
require $root.'/fbp/cli.php';
