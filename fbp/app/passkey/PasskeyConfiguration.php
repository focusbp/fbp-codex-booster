<?php
/** Explicit, authenticated deployment of per-application RP settings. */
final class PasskeyConfiguration {
    public static function normalize(array $input): array {
        $code=$input['app_code']??null;
        if(!is_string($code)||!preg_match('/^app-[a-z0-9-]+$/D',$code))throw new InvalidArgumentException('Invalid app_code.');
        $out=['app_code'=>$code];
        foreach(['production','test']as$environment){
            $row=$input[$environment]??null;
            if(!is_array($row)||!is_bool($row['enabled']??null)||!is_array($row['hosts']??null)||!is_array($row['tls_peers']??null)||count($row['hosts'])>100||count($row['tls_peers'])>32)throw new InvalidArgumentException('Invalid environment configuration.');
            $hosts=[];
            foreach($row['hosts']as$host){
                if(!is_string($host)||strlen($host)>253||!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$/D',$host))throw new InvalidArgumentException('Invalid allowed host.');
                foreach(explode('.',$host)as$label)if(strlen($label)>63)throw new InvalidArgumentException('Invalid DNS label.');
                $hosts[]=$host;
            }
            $peers=[];foreach($row['tls_peers']as$peer){if(!is_string($peer)||filter_var($peer,FILTER_VALIDATE_IP)===false)throw new InvalidArgumentException('Invalid TLS peer.');$peers[]=$peer;}
            $hosts=array_values(array_unique($hosts));sort($hosts);$peers=array_values(array_unique($peers));sort($peers);
            if($row['enabled']&&$hosts===[])throw new InvalidArgumentException('Enabled environment requires explicit hosts.');
            $out[$environment]=['enabled'=>$row['enabled'],'hosts'=>$hosts,'tls_peers'=>$peers];
        }
        return $out;
    }
    public static function save(Controller $ctl,array $input): array {
        $config=self::normalize($input);$dirs=new Dirs();
        // Independent authentication remains independent even with a valid release key.
        if(realpath($dirs->get_class_dir('login'))!==realpath($dirs->appdir_fw.'/login')||realpath($dirs->get_class_dir('passkey'))!==realpath($dirs->appdir_fw.'/passkey'))throw new RuntimeException('App-owned authentication is excluded.');
        $directory=$dirs->appdir_user.'/passkey';
        if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new RuntimeException('Cannot create configuration directory.');
        $file=$directory.'/config.php';$tmp=$ctl->get_temp_dir().'/passkey-config-'.bin2hex(random_bytes(8)).'.php';
        try{
            $text="<?php\n// Explicit application-owned RP configuration.\nreturn ".var_export($config,true).";\n";
            if(file_put_contents($tmp,$text)!==strlen($text)||!chmod($tmp,0600)||!rename($tmp,$file))throw new RuntimeException('Cannot save configuration.');
            if(function_exists('opcache_invalidate'))opcache_invalidate($file,true);
        }finally{if(is_file($tmp))unlink($tmp);}
        $saved=require $file;
        if(self::normalize($saved)!==$config)throw new RuntimeException('Configuration read-back failed.');
        return $config;
    }
}
