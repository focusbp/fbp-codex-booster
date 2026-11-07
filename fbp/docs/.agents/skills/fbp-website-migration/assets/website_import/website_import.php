<?php
/** Copy/adapt into an app. CLI only; never call on login, page render or normal release. */
class website_import {
    private const ARTICLE_TABLE='wm_article';
    private const MEDIA_TABLE='wm_media';
    public function __construct() {
        if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
    }

    private static function canonical($value) {
        if (is_array($value)) {
            if (!array_is_list($value)) ksort($value);
            foreach ($value as $k=>$v) $value[$k]=self::canonical($v);
        }
        return $value;
    }
    private static function json(array $value): string {
        return json_encode(self::canonical($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS|JSON_THROW_ON_ERROR);
    }
    private static function fingerprint(array $record): string {
        unset($record['record_hash']);return hash('sha256',self::json($record));
    }
    private static function articleRow(array $record): array {
        $row=[];
        foreach (['external_key','original_url','source_type','source_status','kind','slug','title','featured_media_url'] as $field) $row[$field]=(string)$record[$field];
        $row['status']=(int)$record['status'];$row['published_at']=(int)$record['published_at'];
        $row['categories']=json_encode($record['categories'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $row['asset_urls']=json_encode($record['asset_urls'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $row['body']=$record['body_html'];$row['snapshot']=$record['snapshot']?1:0;$row['record_hash']=$record['record_hash'];
        return $row;
    }
    private static function mediaRow(array $record): array {
        return ['external_key'=>$record['external_key'],'original_url'=>$record['original_url'],
            'source_file'=>$record['file'],'source_hash'=>$record['sha256'],'size'=>(int)$record['size'],
            'mime'=>$record['mime'],'status'=>(int)$record['status'],'record_hash'=>$record['record_hash']];
    }
    private static function same(array $existing,array $desired): bool {
        foreach ($desired as $key=>$value) if ((string)($existing[$key]??'')!==(string)$value) return false;
        return true;
    }
    public function run(Controller $ctl): void {
        if (PHP_SAPI!=='cli') { http_response_code(404);return; }
        $path=$ctl->POST('manifest');
        if (!is_string($path)||!is_file($path)) throw new InvalidArgumentException('Manifest file required');
        $base=realpath(dirname($path));$sha=hash_file('sha256',$path);
        $data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if (($data['schema']??'')!=='fbp.website.migration'||($data['version']??0)!==1||empty($data['articles'])) throw new RuntimeException('Unsupported/empty manifest');
        if (!empty($data['missing_assets'])) throw new RuntimeException('Resolve missing media first');
        $apply=$ctl->POST('apply')===true;
        if ($apply && (!is_string($ctl->POST('confirm_manifest_sha256'))||!hash_equals($sha,$ctl->POST('confirm_manifest_sha256'))||$ctl->POST('reviewed')!==true)) {
            throw new RuntimeException('Apply requires the reviewed manifest SHA-256 and explicit reviewed=true');
        }
        $paths=[];
        foreach (['articles','media','supporting_assets'] as $section) {
            $rows=$data[$section]??[];
            if (($data['counts'][$section]??-1)!==count($rows)) throw new RuntimeException('Manifest count mismatch');
            $seen=[];
            foreach ($rows as $record) {
                if (empty($record['external_key'])||isset($seen[$record['external_key']])) throw new RuntimeException('Duplicate/missing source key');
                $seen[$record['external_key']]=true;
                if (!hash_equals((string)($record['record_hash']??''),self::fingerprint($record))) throw new RuntimeException('Record hash mismatch');
                if ($section==='articles') continue;
                $relative=$record['file'];
                if (!is_string($relative)||str_starts_with($relative,'/')||str_contains($relative,'\\')||in_array('..',explode('/',$relative),true)) throw new RuntimeException('Invalid file path');
                $file=realpath($base.'/'.$relative);
                if (!$file||!str_starts_with($file,$base.'/')||!is_file($file)||filesize($file)!==$record['size']||!hash_equals($record['sha256'],hash_file('sha256',$file))) throw new RuntimeException('Missing/changed media');
                if ($section==='media') {
                    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);
                    if (!in_array($mime,['image/png','image/jpeg','image/gif','image/webp','application/pdf'],true)||$mime!==$record['mime']) throw new RuntimeException('Unsupported/mismatched media MIME');
                    $paths[$record['external_key']]=$file;
                }
            }
        }
        // Open participating stores before conflict checks/writes. No application flock.
        $articles=$ctl->db(self::ARTICLE_TABLE);$media=$ctl->db(self::MEDIA_TABLE);$uploads=$ctl->db('file','upload');
        $existingArticles=[];$existingMedia=[];
        foreach ($data['articles'] as $record) {
            $found=$articles->select('external_key',$record['external_key']);
            if (count($found)>1||($found&&!self::same($found[0],self::articleRow($record)))) throw new RuntimeException('Existing article differs; no overwrite');
            if ($found) $existingArticles[$record['external_key']]=$found[0];
        }
        foreach ($data['media'] as $record) {
            $found=$media->select('external_key',$record['external_key']);
            if (count($found)>1||($found&&!self::same($found[0],self::mediaRow($record)))) throw new RuntimeException('Existing media differs; no overwrite');
            if ($found) {
                $info=$ctl->get_file_info($found[0]['file'],false);
                if (empty($info['fullpath'])||!is_file($info['fullpath'])||!hash_equals($record['sha256'],hash_file('sha256',$info['fullpath']))) throw new RuntimeException('Existing upload differs');
                $existingMedia[$record['external_key']]=$found[0];
            }
        }
        $result=['schema'=>'fbp.website.import','version'=>1,'ok'=>true,'applied'=>$apply,'manifest_sha256'=>$sha,
            'articles_created'=>0,'media_created'=>0,'articles_existing'=>count($existingArticles),'media_existing'=>count($existingMedia),
            'articles_planned'=>count($data['articles'])-count($existingArticles),'media_planned'=>count($data['media'])-count($existingMedia),
            'article_ids'=>[],'media_ids'=>[]];
        if ($apply) {
            foreach ($data['media'] as $record) {
                $key=$record['external_key'];
                if (isset($existingMedia[$key])) {$result['media_ids'][$key]=(int)$existingMedia[$key]['id'];continue;}
                $row=self::mediaRow($record);$id=$media->insert($row);if (!$id) throw new RuntimeException('Media insert failed');
                if (!self::same($media->get($id),$row)) throw new RuntimeException('Media field truncation/conversion detected');
                $file=['filename'=>basename(parse_url($record['original_url'],PHP_URL_PATH)?:$record['file']),
                    'table_identifer'=>$media->get_identifier(),'row_id'=>$id];
                $fid=$uploads->insert($file);if (!$fid) throw new RuntimeException('Upload metadata insert failed');
                $file['id']=$fid;$file['path']='upload_file_'.$fid;$uploads->update($file);
                $ctl->save_file($file['path'],file_get_contents($paths[$key]));
                $info=$ctl->get_file_info($fid,false);
                if (!hash_equals($record['sha256'],hash_file('sha256',$info['fullpath']))) throw new RuntimeException('Saved upload verification failed');
                $update=['id'=>$id,'file'=>$fid];$media->update($update);$result['media_ids'][$key]=$id;$result['media_created']++;
            }
            foreach ($data['articles'] as $record) {
                $key=$record['external_key'];
                if (isset($existingArticles[$key])) {$result['article_ids'][$key]=(int)$existingArticles[$key]['id'];continue;}
                $row=self::articleRow($record);$id=$articles->insert($row);if (!$id) throw new RuntimeException('Article insert failed');
                if (!self::same($articles->get($id),$row)) throw new RuntimeException('Article field truncation/conversion detected');
                $result['article_ids'][$key]=$id;$result['articles_created']++;
            }
        }
        $ctl->console_log(json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    public function inventory(Controller $ctl): void {
        if (PHP_SAPI!=='cli') { http_response_code(404);return; }
        $result=['schema'=>'fbp.website.inventory','version'=>1,'articles'=>[],'media'=>[]];
        foreach ($ctl->db(self::ARTICLE_TABLE)->getall() as $row) {
            $neutral=[];
            foreach (['external_key','original_url','source_type','source_status','kind','slug','title','featured_media_url'] as $key) $neutral[$key]=(string)($row[$key]??'');
            $neutral['status']=(int)$row['status'];$neutral['published_at']=(int)$row['published_at'];
            $neutral['categories']=json_decode($row['categories'],true,512,JSON_THROW_ON_ERROR);
            $neutral['asset_urls']=json_decode($row['asset_urls'],true,512,JSON_THROW_ON_ERROR);
            $neutral['body_html']=$row['body'];$neutral['snapshot']=(bool)$row['snapshot'];
            $result['articles'][]=['external_key'=>$neutral['external_key'],'record_hash'=>self::fingerprint($neutral)];
        }
        foreach ($ctl->db(self::MEDIA_TABLE)->getall() as $row) {
            $neutral=['external_key'=>$row['external_key'],'original_url'=>$row['original_url'],'file'=>$row['source_file'],
                'sha256'=>$row['source_hash'],'size'=>(int)$row['size'],'mime'=>$row['mime'],'status'=>(int)$row['status']];
            $info=$ctl->get_file_info($row['file'],false);
            $result['media'][]=['external_key'=>$neutral['external_key'],'record_hash'=>self::fingerprint($neutral),
                'actual_sha256'=>!empty($info['fullpath'])&&is_file($info['fullpath'])?hash_file('sha256',$info['fullpath']):null];
        }
        $ctl->console_log(json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
}
