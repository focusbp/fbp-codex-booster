<?php
namespace canvas3d;
require_once __DIR__.'/Scene_class.php';

/** All writes use the Controller-managed FFM lock and atomic file replacement. */
final class Store {
    private string $root;
    public function __construct(string $dataDir,bool $create = false) {
        $base=realpath($dataDir);
        if($base===false||!is_dir($base)) throw new \RuntimeException('Data directory unavailable');
        $this->root=$base.'/_3dcanvas';
        $this->safeDirectory($this->root,$create);
    }
    private function safeDirectory(string $path,bool $create=false): void {
        if(is_link($path)) throw new \InvalidArgumentException('Symbolic links are not supported');
        if(!is_dir($path)&&(!$create||(!@mkdir($path,0700)&&!is_dir($path)))) throw new \RuntimeException('Model directory unavailable');
        if(is_link($path)) throw new \InvalidArgumentException('Symbolic links are not supported');
    }
    private function directory(string $id,bool $create=false): string {
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$id)) throw new \InvalidArgumentException('Invalid model id');
        $this->safeDirectory($this->root);$path=$this->root.'/'.$id;$this->safeDirectory($path,$create);return $path;
    }
    public function read(string $id): Scene_class {
        $path=$this->directory($id).'/scene.json';
        if(is_link($path)||!is_file($path)||filesize($path)>Scene_class::MAX_BYTES) throw new \RuntimeException('Model file unavailable');
        $json=file_get_contents($path);if($json===false) throw new \RuntimeException('Cannot read model');
        return Scene_class::from_json($json);
    }
    public function create(Scene $scene): string {
        $data=Scene_class::from_array($scene->to_array())->with_revision(1);
        $id=bin2hex(random_bytes(16));$dir=$this->directory($id,true);
        try{$this->write($dir,$data);}catch(\Throwable $e){@rmdir($dir);throw $e;}return $id;
    }
    /** Caller must hold the writable _3dcanvas/lock FFM for this entire call. */
    public function save(string $id,Scene $scene,int $expected): int {
        $current=$this->read($id)->to_array();
        if($expected<1||$current['revision']!==$expected) throw new \RuntimeException('モデルが更新されています。再読込みしてください。');
        $next=Scene_class::from_array($scene->to_array())->with_revision($expected+1);
        $this->write($this->directory($id),$next);return $expected+1;
    }
    private function write(string $dir,Scene_class $scene): void {
        $dest=$dir.'/scene.json';if(is_link($dest)) throw new \InvalidArgumentException('Invalid model file');
        $json=json_encode($scene->to_array(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        // Include pretty-print overhead in the on-disk size limit too.
        if(strlen($json)>Scene_class::MAX_BYTES) $json=json_encode($scene->to_array(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $tmp=$dir.'/.scene-'.bin2hex(random_bytes(12));$h=fopen($tmp,'x');
        if(!$h) throw new \RuntimeException('Cannot create model file');
        try{
            chmod($tmp,0600);$offset=0;while($offset<strlen($json)){$n=fwrite($h,substr($json,$offset));if(!$n)throw new \RuntimeException('Cannot write model');$offset+=$n;}
            if(!fflush($h)) throw new \RuntimeException('Cannot flush model');
            if(function_exists('fsync')&&!fsync($h)) throw new \RuntimeException('Cannot sync model');
            fclose($h);$h=null;
            if(!rename($tmp,$dest)) throw new \RuntimeException('Cannot replace model');
        } finally {if(is_resource($h))fclose($h);if(is_file($tmp))unlink($tmp);}
    }
}
