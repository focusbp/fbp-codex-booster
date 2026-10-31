<?php
require_once __DIR__.'/../sample_reconciliation/Graph.php';
/** Canonical header adapter. Add a tested bank-specific adapter for different layouts. */
class SampleStatementCsvParser {
    private const ALIASES=[
        'date'=>['入出金日','取引日','お取引日','日付'], 'description'=>['摘要','内容','お取引内容'],
        'deposit'=>['入金額','お預り金額','お預入金額'], 'withdrawal'=>['出金額','お支払金額','お引出金額'],
        'balance'=>['残高','お取引後残高'], 'external_id'=>['取引ID','明細ID']
    ];
    private static function amount(string $s,bool $signed=false):int {
        $s=trim(str_replace([',','，','円','￥','¥'],'',mb_convert_kana($s,'n','UTF-8')));
        if($s==='')return 0;
        if($signed&&str_starts_with($s,'-'))return -SampleReconciliationGraph::money(substr($s,1));
        return SampleReconciliationGraph::money($s);
    }
    public static function parse(string $bytes,string $scope,string $account,bool $excludeInterest):array {
        if($bytes===''||strlen($bytes)>2*1024*1024)throw new InvalidArgumentException('2MB以下のCSVを選択してください。');
        if(trim($scope)===''||strlen($scope)>120||trim($account)===''||strlen($account)>120)throw new InvalidArgumentException('管理範囲と口座識別子を各120バイト以内で入力してください。');
        if(!mb_check_encoding($bytes,'UTF-8')){
            if(!mb_check_encoding($bytes,'SJIS-win'))throw new InvalidArgumentException('UTF-8またはShift_JISのCSVを使用してください。');
            $bytes=mb_convert_encoding($bytes,'UTF-8','SJIS-win');
        }
        $bytes=preg_replace('/^\xEF\xBB\xBF/','',$bytes);
        $stream=fopen('php://memory','r+');fwrite($stream,$bytes);rewind($stream);
        try{
            $header=fgetcsv($stream,0,',','"','');if(!$header)throw new InvalidArgumentException('ヘッダ行がありません。');
            $columns=[];
            foreach(self::ALIASES as $key=>$aliases)foreach($header as $i=>$name)if(in_array(trim($name),$aliases,true)){
                if(isset($columns[$key]))throw new InvalidArgumentException('同じ意味の列が複数あります。銀行別の変換ルールを指定してください。');$columns[$key]=$i;
            }
            foreach(['date','description','deposit','withdrawal','balance'] as $key)if(!isset($columns[$key]))throw new InvalidArgumentException('必要な列がありません：入出金日・摘要・入金額・出金額・残高が必要です。');
            $rows=[];$line=1;
            while(($cells=fgetcsv($stream,0,',','"',''))!==false){
                $line++;if(count($cells)===1&&trim((string)$cells[0])==='')continue;
                if(count($cells)!==count($header))throw new InvalidArgumentException($line.'行目の列数が違います。');
                if(count($rows)>=5000)throw new InvalidArgumentException('一度に取り込めるのは5000行までです。');
                $value=fn($key)=>isset($columns[$key])?trim((string)($cells[$columns[$key]]??'')):'';
                $date=str_replace('-','/',$value('date'));
                if(!preg_match('~^\d{4}/\d{1,2}/\d{1,2}$~D',$date))throw new InvalidArgumentException($line.'行目の日付はYYYY/MM/DDで指定してください。');
                $dt=DateTimeImmutable::createFromFormat('!Y/n/j',$date);$errors=DateTimeImmutable::getLastErrors();
                if(!$dt||($errors&&($errors['warning_count']||$errors['error_count'])))throw new InvalidArgumentException($line.'行目の日付が不正です。');
                $deposit=self::amount($value('deposit'));$withdrawal=self::amount($value('withdrawal'));$balance=self::amount($value('balance'),true);
                if($deposit>0&&$withdrawal>0)throw new InvalidArgumentException($line.'行目に入金と出金の両方が入っています。');
                if(!$deposit&&!$withdrawal)continue;
                $description=$value('description');$external=$value('external_id');
                if(strlen($description)>1500||strlen($external)>300)throw new InvalidArgumentException($line.'行目の摘要または取引IDが長すぎます。');
                $rows[]=['scope_key'=>$scope,'import_account_key'=>$account,'currency'=>'JPY','occurred_on'=>$dt->getTimestamp(),
                    'counterparty'=>mb_strcut($description,0,300,'UTF-8'),'description'=>$description,'amount'=>$deposit,'withdrawal_amount'=>$withdrawal,'balance_amount'=>$balance,
                    'external_transaction_id'=>$external,'eligibility'=>$withdrawal>0||($excludeInterest&&$description==='利息')?2:0,'source_row'=>$line];
            }
            if(!$rows)throw new InvalidArgumentException('入出金の明細がありません。');
            // Fingerprint logical rows, independent of filename/encoding and checkbox policy.
            $logical=array_map(fn($r)=>array_intersect_key($r,array_flip(['occurred_on','description','amount','withdrawal_amount','balance_amount','external_transaction_id'])),$rows);
            $fileHash=hash('sha256',json_encode($logical,JSON_THROW_ON_ERROR));
            foreach($rows as $i=>&$row){
                $identity=$row['external_transaction_id']!==''?'id:'.$row['external_transaction_id']:'file:'.$fileHash.':'.$i;
                $row['source_key']=hash('sha256',json_encode([$scope,$account,$identity],JSON_THROW_ON_ERROR));
                $row['source_facts']=hash('sha256',json_encode([$scope,$account,$row['occurred_on'],$row['description'],$row['amount'],$row['withdrawal_amount'],$row['balance_amount']],JSON_THROW_ON_ERROR));
            }unset($row);
            return $rows;
        }finally{fclose($stream);}
    }
    public static function plan(array $incoming,array $existing):array {
        $keys=[];$facts=[];
        foreach($existing as $old){if(!empty($old['source_key']))$keys[$old['source_key']]=$old;if(!empty($old['source_facts']))$facts[$old['source_facts']]=true;}
        $out=['rows'=>[],'insert_count'=>0,'duplicate_count'=>0,'conflict_count'=>0];$seen=[];
        foreach($incoming as $row){
            $status='追加';
            if(isset($seen[$row['source_key']]))$status='重複ID';
            elseif(isset($keys[$row['source_key']]))$status=$keys[$row['source_key']]['source_facts']===$row['source_facts']?'取込済み':'既存IDの内容違い';
            elseif($row['external_transaction_id']===''&&isset($facts[$row['source_facts']]))$status='別ファイルに類似明細あり';
            $seen[$row['source_key']]=true;$row['import_status']=$status;$out['rows'][]=$row;
            if($status==='追加')$out['insert_count']++;
            elseif($status==='取込済み')$out['duplicate_count']++;
            else $out['conflict_count']++;
        }
        return $out;
    }
}
