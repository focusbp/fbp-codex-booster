<?php
/** Read format definitions without opening application data or changing its schema. */
final class DspTargetCatalog {
    public static function customTargets(?string $appDir, ?string $frameworkDir = null): array {
        $targets=[];
        foreach([$appDir,$frameworkDir] as $index=>$directory) {
            $root=$directory!==null?realpath($directory):false;
            if($root===false)continue;
            foreach(glob($root.'/*/fmt/*.fmt')?:[] as $file) {
            $class=basename(dirname(dirname($file)));$table=basename($file,'.fmt');
            if($class==='common'||$class==='_dsp'||!self::validName($class)||!self::validName($table))continue;
            // Dirs::get_class_dir selects the entire application class before framework fallback.
            if($index===1&&$appDir!==null&&is_file($appDir.'/'.$class.'/'.$class.'.php'))continue;
            $classFile=realpath($root.'/'.$class.'/'.$class.'.php');$format=realpath($file);
            if($classFile===false||$format===false||!str_starts_with($classFile,$root.'/')||!str_starts_with($format,$root.'/'))continue;
            $key='db:'.$class.'/'.$table;
            $targets[$key]=['target'=>$key,'note_id'=>0,'db_class'=>$class,'db_table'=>$table,'note_name'=>$class.'/'.$table];
            }
        }
        ksort($targets,SORT_STRING);
        return array_values($targets);
    }
    private static function validName(string $value): bool {
        return strlen($value)<=100&&preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$value)===1;
    }
}
