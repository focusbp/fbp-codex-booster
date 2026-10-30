<?php
/** Production-side reservation protocol. No connection to a developer environment. */
class NoteSyncClient {
    private string $url;
    private array $credentials;
    public function __construct(private Controller $ctl) {
        $report=$ctl->get_server_error_report_settings();
        $configured=$_SERVER['FBP_NOTE_SYNC_URL']??getenv('FBP_NOTE_SYNC_URL');
        $url=trim((string)($ctl->db('setting','setting')->get(1)['note_sync_url']??''));
        if ($url==='') $url=trim(is_string($configured)?$configured:'');
        if ($url==='') $url=(string)($report['url']??'');
        $parts=parse_url($url);
        if (!$parts || ($parts['scheme']??'')!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']))
            throw new RuntimeException('ノート同期の接続先が設定されていません。');
        $path=$parts['path']??'/app.php';
        $path=preg_replace('#/(?:server_error_api\*report|task_api\*task_note_sync_peer)$#','/app.php',$path);
        $this->url='https://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'').$path;
        $setting=$ctl->get_setting();
        $this->credentials=['key'=>(string)($setting['api_key']??''),'secret'=>(string)($setting['api_secret']??'')];
        if ($this->credentials['key']==='' || $this->credentials['secret']==='') throw new RuntimeException('ノート同期の認証設定を確認してください。');
    }
    protected function request(array $payload): array {
        $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $query=http_build_query(['class'=>'task_api','function'=>'task_note_sync_peer','body_hash'=>hash('sha256',$body)]);
        $ts=(string)time();
        $signature=hash_hmac('sha256',"POST\n".parse_url($this->url,PHP_URL_PATH)."\n$query\n$ts",$this->credentials['secret']);
        $curl=curl_init($this->url.'?'.$query);
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-API-KEY: '.$this->credentials['key'],'X-API-TS: '.$ts,'X-API-SIGN: '.$signature]]);
        $raw=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_HTTP_CODE);
        $data=is_string($raw)?json_decode($raw,true):null;
        if ($status!==200 || ($data['ok']??false)!==true || !is_array($data['request']??null)) {
            $message=($status===409 && is_string($data['error']??null))?$data['error']:'同期受付を確認できません。同じ画面から再試行してください。';
            throw new RuntimeException($message);
        }
        return $data['request'];
    }
    public function create(string $title,string $key): array {
        if (ExcelDefinitionGuard::isDeveloper($this->ctl->dirs->datadir)) throw new ExcelDefinitionReadOnly('本番向けの同期受付はProduction Mode専用です。Developer Modeではローカルの定義を変更してください。');
        if (!preg_match('/^[a-f0-9]{48}$/D',$key)) throw new RuntimeException('追加受付番号が不正です。');
        // Controller keeps the historical prefix-free name on app-* hosts;
        // management inventory and reservation receipts use the full appcode.
        $app=trim((string)$this->ctl->get_appcode());
        if (!str_starts_with($app,'app-')) $app='app-'.$app;
        if (!preg_match('/^app-[A-Za-z0-9][A-Za-z0-9_-]*$/D',$app)) throw new RuntimeException('アプリ識別名が不正です。');
        $base=['appcode'=>$app,'request_key'=>$key];
        // Saving and releasing local FFM locks before a remote call also permits
        // the synchronizer to fetch definitions while a browser is still open.
        $this->ctl->close_all_db();
        $receipt=$this->request($base+['action'=>'reserve']);
        $name='sheet_'.substr($key,0,24);
        if (($receipt['appcode']??'')!==$app || ($receipt['request_key']??'')!==$key || ($receipt['tb_name']??'')!==$name)
            throw new RuntimeException('同期受付のノート情報が一致しません。');
        if (($receipt['state']??'')==='failed') throw new RuntimeException('ノート同期が停止しています。プロジェクトサポートで確認してください。');
        $this->ctl->db('settings','excel_grid');
        require_once __DIR__.'/../app/db/db.php';
        $schema=new db($this->ctl);
        $definitions=$this->ctl->db('db','db');
        $note=$definitions->select('tb_name',$name)[0]??[];
        if ($note && !ExcelGrid::isSpreadsheet($note)) throw new RuntimeException('同じ内部名に別のノートが存在します。');
        if (!$note && ($receipt['state']??'')!=='preparing') throw new RuntimeException('作成済みノートを確認できません。');
        if (!$note) $note=$schema->create_spreadsheet_note($this->ctl,$title,$name);
        if ((int)($receipt['note_id']??0)>0 && (int)$receipt['note_id']!==(int)$note['id']) throw new RuntimeException('同期受付と本番のノートIDが一致しません。');
        if (!ExcelGrid::config($this->ctl,$name)) ExcelGrid::saveConfig($this->ctl,$name,[]);
        $this->ctl->close_all_db();
        $ready=$this->request($base+['action'=>'ready','note_id'=>(int)$note['id'],'tb_name'=>$name]);
        if (($ready['state']??'')==='failed') throw new RuntimeException('ノート同期が停止しています。プロジェクトサポートで確認してください。');
        $note['_note_sync_state']=$ready['state']??'queued';
        return $note;
    }
    public function convert(int $id,string $name,array $screens,int $revision,string $key): array {
        if (ExcelDefinitionGuard::isDeveloper($this->ctl->dirs->datadir)) throw new ExcelDefinitionReadOnly('本番向けの同期受付はProduction Mode専用です。Developer Modeではローカルの定義を変更してください。');
        if (!preg_match('/^[a-f0-9]{48}$/D',$key)) throw new RuntimeException('変換受付番号が不正です。');
        $app=trim((string)$this->ctl->get_appcode());if (!str_starts_with($app,'app-')) $app='app-'.$app;
        if (!preg_match('/^app-[A-Za-z0-9][A-Za-z0-9_-]*$/D',$app)) throw new RuntimeException('アプリ識別名が不正です。');
        $base=['appcode'=>$app,'request_key'=>$key];
        $this->ctl->close_all_db();
        $receipt=$this->request($base+['action'=>'reserve_conversion','note_id'=>$id,'tb_name'=>$name]);
        if (($receipt['appcode']??'')!==$app || ($receipt['request_key']??'')!==$key || ($receipt['operation']??'')!=='convert'
            || (int)($receipt['note_id']??0)!==$id || ($receipt['tb_name']??'')!==$name) throw new RuntimeException('同期受付のノート情報が一致しません。');
        if (($receipt['state']??'')==='failed') throw new RuntimeException('ノート同期が停止しています。プロジェクトサポートで確認してください。');
        $note=ExcelStandardLayout::apply($this->ctl,$id,$name,$screens,$revision,$key);
        $this->ctl->close_all_db();
        $ready=$this->request($base+['action'=>'ready','note_id'=>$id,'tb_name'=>$name]);
        if (($ready['state']??'')==='failed') throw new RuntimeException('変換済みです。同期状況をプロジェクトサポートで確認してください。');
        return $note;
    }

}
