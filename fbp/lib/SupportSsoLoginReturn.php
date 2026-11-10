<?php
/** Fixed local continuation, never a browser-supplied redirect URL. */
final class SupportSsoLoginReturn {
    public static function parameters(array $input): array {
        $out=[];
        foreach(['project_code'=>2048,'request'=>64] as $key=>$limit){
            $value=$input[$key]??'';
            if(!is_string($value)||strlen($value)>$limit||preg_match('/[\x00-\x20\x7f]/',$value))throw new DomainException('サポートの再認証情報を確認できません。');
            if($key==='request'&&$value!==''&&!preg_match('/^[a-f0-9]{64}$/D',$value))throw new DomainException('サポートの受付情報を確認できません。');
            if($value!=='')$out[$key]=$value;
        }
        return $out;
    }
    private static function key(Controller $ctl): string {return 'support_sso_login_return_'.hash('sha256',$ctl->get_APP_URL());}
    public static function remember(Controller $ctl,array $parameters): void {
        $_SESSION[self::key($ctl)]=['parameters'=>self::parameters($parameters),'expires'=>time()+900];
    }
    public static function consume(Controller $ctl): string {
        $key=self::key($ctl);$pending=$_SESSION[$key]??null;
        unset($_SESSION[$key]);
        if(!is_array($pending)||(int)($pending['expires']??0)<time()||!$ctl->is_app_admin())return '';
        try{$params=self::parameters($pending['parameters']??[]);}catch(DomainException $e){return '';}
        return $ctl->get_APP_URL('project_support_login','start',$params);
    }
}
