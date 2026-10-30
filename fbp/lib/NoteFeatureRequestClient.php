<?php
/** Signed app-to-support request. The remote service resolves the project from app identity. */
class NoteFeatureRequestClient {
    function __construct(private Controller $ctl) {}
    protected function endpoint():string {
        $override=$_SERVER['FBP_NOTE_FEATURE_REQUEST_URL']??getenv('FBP_NOTE_FEATURE_REQUEST_URL');
        $url=trim((string)($this->ctl->db('setting','setting')->get(1)['note_sync_url']??''));
        if($url==='') $url=trim(is_string($override)?$override:'');
        if($url===''){$report=$this->ctl->get_server_error_report_settings();$parts=parse_url((string)($report['url']??''));if(!$parts||empty($parts['host']))throw new RuntimeException('プロジェクトサポートへの接続設定を確認してください。');$path=preg_replace('#/(?:server_error_api\*report|task_api\*task_note_sync_peer)$#','/app.php',$parts['path']??'/app.php');$url='https://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'').$path;}
        $parts=parse_url($url);if(!$parts||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))throw new RuntimeException('プロジェクトサポートの接続先を確認してください。');return $url;
    }
    function submit(array $payload):array {
        $url=$this->endpoint();$s=$this->ctl->db('setting','setting')->get(1)??[];$key=(string)($s['api_key']??'');$secret=(string)($s['api_secret']??'');if($key===''||$secret==='')throw new RuntimeException('アプリのAPI認証設定を確認してください。');
        $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$query=http_build_query(['class'=>'task_api','function'=>'task_note_feature_peer','body_hash'=>hash('sha256',$body)]);$ts=(string)time();$signature=hash_hmac('sha256',"POST\n".parse_url($url,PHP_URL_PATH)."\n$query\n$ts",$secret);
        $this->ctl->close_all_db();$c=curl_init($url.'?'.$query);curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-API-KEY: '.$key,'X-API-TS: '.$ts,'X-API-SIGN: '.$signature]]);$raw=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$v=is_string($raw)?json_decode($raw,true):null;
        if($status!==200||!is_array($v)||($v['ok']??false)!==true||empty($v['task_id'])||($v['request_key']??'')!==($payload['request_key']??''))throw new RuntimeException('依頼の受付を確認できませんでした。同じ画面から再試行してください。');
        $link=parse_url((string)($v['support_url']??''));$endpoint=parse_url($url);if(($link['scheme']??'')!=='https'||($link['host']??'')!==$endpoint['host']||isset($link['user'])||isset($link['pass']))throw new RuntimeException('進捗URLを確認できませんでした。同じ画面から再試行してください。');return $v;
    }
}
