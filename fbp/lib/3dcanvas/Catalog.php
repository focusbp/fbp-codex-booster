<?php
namespace canvas3d;

/** Fixed framework-owned assets only. No scene-controlled paths or URLs. */
final class Catalog {
    public static function textures(): array {
        static $manifest;
        if($manifest===null)$manifest=json_decode(file_get_contents(__DIR__.'/texture/catalog.json'),true,32,JSON_THROW_ON_ERROR);
        return $manifest;
    }
    public static function texture(string $id): array {
        $all=self::textures();if(!isset($all[$id]))throw new \InvalidArgumentException('Unknown texture');return $all[$id];
    }
    public static function texture_file(string $id,string $map): string {
        $entry=self::texture($id);
        if(!in_array($map,['color','normal','roughness'],true)||!isset($entry['maps'][$map]))throw new \InvalidArgumentException('Unknown texture map');
        return self::fixed_file('texture',$entry['maps'][$map]);
    }
    public static function parts(): array {
        static $manifest;
        if($manifest===null)$manifest=json_decode(file_get_contents(__DIR__.'/parts_json/catalog.json'),true,32,JSON_THROW_ON_ERROR);
        return $manifest;
    }
    public static function part(string $id): array {
        $all=self::parts();if(!isset($all[$id]))throw new \InvalidArgumentException('Unknown part');
        $part=json_decode(file_get_contents(self::fixed_file('parts_json',$all[$id]['file'])),true,32,JSON_THROW_ON_ERROR);
        if(($part['schema_version']??null)!==1||!is_array($part['objects']??null))throw new \RuntimeException('Invalid part definition');return $part;
    }
    private static function fixed_file(string $folder,string $relative): string {
        if(!preg_match('~\A[a-zA-Z0-9_/-]+\.(png|json)\z~D',$relative)||str_contains($relative,'..'))throw new \RuntimeException('Invalid asset definition');
        $root=realpath(__DIR__.'/'.$folder);$file=realpath(__DIR__.'/'.$folder.'/'.$relative);
        if(!$root||!$file||!str_starts_with($file,$root.'/')||!is_file($file))throw new \RuntimeException('Asset unavailable');return $file;
    }
}
