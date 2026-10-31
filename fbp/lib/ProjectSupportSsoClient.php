<?php
/** Destination identity is taken from this runtime, never from shared code_dir. */
class ProjectSupportSsoClient {
    function __construct(private Controller $ctl){}
    public function endpoint(): string {
        $s=$this->ctl->get_setting();$u=parse_url((string)($s['project_portal_url']??''));
        if(!$u||($u['scheme']??'')!=='https'||empty($u['host'])||isset($u['user'])||isset($u['pass']))throw new DomainException('プロジェクトサポートの接続設定を確認してください。');
        $path=$u['path']??'';
        if(str_contains(basename($path),'*'))$path=preg_replace('#/fbp$#','',rtrim(dirname($path),'/')).'/fbp/app.php';
        if(!str_ends_with($path,'/app.php'))throw new DomainException('サポートURLの形式を確認してください。');
        return 'https://'.$u['host'].(isset($u['port'])?':'.$u['port']:'').$path;
    }
    public function request(array $p): array {
        $url=$this->endpoint();$s=$this->ctl->db('setting','setting')->get(1)?:[];
        $portal=parse_url((string)($s['project_portal_url']??''));parse_str($portal['query']??'',$params);
        if(!empty($params['project_code']))$p['project_code']=(string)$params['project_code'];
        $runtime=basename(rtrim(realpath((new Dirs())->basedir)?:'','/'));
        if(!preg_match('/^app-[A-Za-z0-9][A-Za-z0-9_-]*$/D',$runtime))throw new DomainException('配布先アプリの識別情報を確認できません。');
        $p['appcode']=$runtime;
        $key=(string)($s['api_key']??'');$secret=(string)($s['api_secret']??'');
        if($key===''||$secret==='')throw new DomainException('アプリのAPI認証設定を確認してください。');
        $body=json_encode($p,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$q=http_build_query(['class'=>'project_support_sso','function'=>'peer','body_hash'=>hash('sha256',$body)]);$ts=(string)time();
        $headers=['Content-Type: application/json','X-API-KEY: '.$key,'X-API-TS: '.$ts,'X-API-SIGN: '.hash_hmac('sha256',"POST\n".parse_url($url,PHP_URL_PATH)."\n$q\n$ts",$secret)];
        // The local test gateway uses Basic auth in addition to the signed API.
        if((int)($s['force_testmode']??0)===1 && (parse_url($url,PHP_URL_HOST).(parse_url($url,PHP_URL_PORT)?':'.parse_url($url,PHP_URL_PORT):''))===($_SERVER['HTTP_HOST']??'') && isset($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']))$headers[]='Authorization: Basic '.base64_encode($_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW']);
        $this->ctl->close_all_db();$c=curl_init($url.'?'.$q);curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15]);
        $raw=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$out=is_string($raw)?json_decode($raw,true):null;
        if($status!==200||!is_array($out)||($out['ok']??false)!==true)throw new DomainException('サポートの認証を開始できません。管理メニューから再試行してください。');
        return $out;
    }
}
