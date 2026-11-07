<?php
namespace canvas3d;
require_once __DIR__.'/../../interface/canvas3d/Scene.php';

/** Versioned, bounded JSON schema shared by builders, storage and appcon. No executable data. */
final class Scene_class implements Scene {
    public const MAX_BYTES = 2097152;
    public const MAX_OBJECTS = 1500;
    private array $data;

    public function __construct() {
        $this->data = ['schema_version'=>1, 'revision'=>0, 'units'=>'mm', 'background'=>'#edf2f5',
            'objects'=>[], 'views'=>[], 'animations'=>[]];
    }
    public static function from_array(array $data): self {
        $s = new self();
        self::keys($data, ['schema_version','revision','units','background','objects','views','animations']);
        if (($data['schema_version']??null)!==1 || ($data['units']??null)!=='mm') self::fail('Unsupported scene format');
        $rev=$data['revision']??0;
        if (!is_int($rev)||$rev<0) self::fail('Invalid revision');
        $s->data['revision']=$rev;
        $s->set_background($data['background']??'#edf2f5');
        foreach (['objects','views','animations'] as $key) if (!isset($data[$key])||!is_array($data[$key])) self::fail('Missing '.$key);
        if (count($data['objects'])>self::MAX_OBJECTS) self::fail('Too many objects');
        // Permit parent declarations after children, then validate the complete hierarchy.
        foreach ($data['objects'] as $o) {
            if (!is_array($o)||!isset($o['id'],$o['type'])) self::fail('Invalid object');
            $id=$o['id']; $type=$o['type']; unset($o['id'],$o['type']); $s->add($type,$id,$o);
        }
        foreach ($data['views'] as $k=>$v) { if (!is_array($v)) self::fail('Invalid view'); $s->set_view((string)$k,$v); }
        foreach ($data['animations'] as $a) {
            if (!is_array($a)||!isset($a['id'])) self::fail('Invalid animation');
            $id=$a['id'];unset($a['id']);$s->animate($id,$a);
        }
        $s->to_array();return $s;
    }
    public static function from_json(string $json): self {
        if (strlen($json)>self::MAX_BYTES) self::fail('Scene exceeds 2MB');
        $data=json_decode($json,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($data)) self::fail('Expected scene object');
        return self::from_array($data);
    }
    public static function keys(array $a,array $allowed): void {
        foreach ($a as $k=>$v) if (!in_array($k,$allowed,true)) self::fail('Unknown option: '.$k);
    }
    private static function fail(string $message): void {throw new \InvalidArgumentException($message);}
    public static function identifier($id): string {
        if (!is_string($id)||!preg_match('/\A[a-zA-Z][a-zA-Z0-9_-]{0,63}\z/D',$id)) self::fail('Invalid identifier');
        return $id;
    }
    private static function number($v,float $lo,float $hi): float {
        if ((!is_int($v)&&!is_float($v))||!is_finite((float)$v)||$v<$lo||$v>$hi) self::fail('Number out of range');
        return (float)$v;
    }
    private static function vector($a,int $n,float $lo,float $hi): array {
        if (!is_array($a)||array_keys($a)!==range(0,$n-1)) self::fail('Invalid vector');
        return array_map(fn($x)=>self::number($x,$lo,$hi),$a);
    }
    private static function color($v): string {
        if (!is_string($v)||!preg_match('/\A#[a-fA-F0-9]{6}\z/D',$v)) self::fail('Expected #RRGGBB');
        return strtolower($v);
    }
    private static function boolean($v): bool {if (!is_bool($v)) self::fail('Expected boolean');return $v;}
    private static function material($m): array {
        if (!is_array($m)) self::fail('Invalid material');
        self::keys($m,['color','roughness','metalness','opacity','emissive','emissive_intensity','texture','repeat','texture_rotation']);
        $r=['color'=>self::color($m['color']??'#dce5eb'),
            'roughness'=>self::number($m['roughness']??0.6,0,1), 'metalness'=>self::number($m['metalness']??0,0,1),
            'opacity'=>self::number($m['opacity']??1,0,1), 'emissive'=>self::color($m['emissive']??'#000000'),
            'emissive_intensity'=>self::number($m['emissive_intensity']??0,0,5)];
        if (isset($m['texture'])) {
            if (!in_array($m['texture'],['checker','wood','tile'],true)) self::fail('Unknown texture');
            $r['texture']=$m['texture'];$r['repeat']=self::vector($m['repeat']??[1,1],2,0.01,100);
            $r['texture_rotation']=self::number($m['texture_rotation']??0,-360,360);
        }
        return $r;
    }
    public function add(string $type,string $id,array $options=[]): Scene {
        self::identifier($id);
        $types=['group','box','sphere','cylinder','cone','plane','circle','torus','rounded_box','extrude'];
        if (!in_array($type,$types,true)) self::fail('Unknown primitive');
        if (count($this->data['objects'])>=self::MAX_OBJECTS) self::fail('Too many objects');
        foreach ($this->data['objects'] as $o) if ($o['id']===$id) self::fail('Duplicate object id');
        $shapeKeys=match($type) {
            'box','rounded_box'=>['size','radius'], 'sphere','circle'=>['radius'],
            'cylinder','cone'=>['radius','height'], 'plane'=>['size'], 'torus'=>['radius','tube'],
            'extrude'=>['points','depth'], default=>[]
        };
        self::keys($options,array_merge(['parent','position','rotation','scale','visible','material'],$shapeKeys));
        $o=['type'=>$type,'id'=>$id,'position'=>self::vector($options['position']??[0,0,0],3,-1000000,1000000),
            'rotation'=>self::vector($options['rotation']??[0,0,0],3,-3600,3600),
            'scale'=>self::vector($options['scale']??[1,1,1],3,0.001,1000), 'visible'=>self::boolean($options['visible']??true)];
        if (isset($options['parent'])) $o['parent']=self::identifier($options['parent']);
        if ($type!=='group') $o['material']=self::material($options['material']??[]);
        if (in_array($type,['box','rounded_box'],true)) $o['size']=self::vector($options['size']??[100,100,100],3,0.1,1000000);
        if ($type==='plane') $o['size']=self::vector($options['size']??[100,100],2,0.1,1000000);
        if (in_array($type,['sphere','circle','cylinder','cone','torus','rounded_box'],true))
            $o['radius']=self::number($options['radius']??($type==='rounded_box'?10:50),0.1,1000000);
        if ($type==='rounded_box'&&$o['radius']>min($o['size'])/2) self::fail('Corner radius exceeds size');
        if (in_array($type,['cylinder','cone'],true)) $o['height']=self::number($options['height']??100,0.1,1000000);
        if ($type==='torus') {
            $o['tube']=self::number($options['tube']??10,0.1,1000000);
            if ($o['tube']>=$o['radius']) self::fail('Tube must be smaller than radius');
        }
        if ($type==='extrude') {
            $p=$options['points']??null;
            if (!is_array($p)||count($p)<3||count($p)>128||array_keys($p)!==range(0,count($p)-1)) self::fail('Expected 3..128 polygon points');
            $o['points']=array_map(fn($v)=>self::vector($v,2,-1000000,1000000),$p);
            $o['depth']=self::number($options['depth']??100,0.1,1000000);
            $area=0;foreach($o['points'] as $i=>$a){$b=$o['points'][($i+1)%count($p)];$area+=$a[0]*$b[1]-$b[0]*$a[1];}
            if(abs($area)<0.01) self::fail('Degenerate polygon');
        }
        $this->data['objects'][]=$o;return $this;
    }
    public function add_group(string $id,array $options=[]): Scene {return $this->add('group',$id,$options);}
    public function add_box(string $id,array $options=[]): Scene {return $this->add('box',$id,$options);}
    public function add_sphere(string $id,array $options=[]): Scene {return $this->add('sphere',$id,$options);}
    public function add_cylinder(string $id,array $options=[]): Scene {return $this->add('cylinder',$id,$options);}
    public function add_cone(string $id,array $options=[]): Scene {return $this->add('cone',$id,$options);}
    public function add_plane(string $id,array $options=[]): Scene {return $this->add('plane',$id,$options);}
    public function add_circle(string $id,array $options=[]): Scene {return $this->add('circle',$id,$options);}
    public function add_torus(string $id,array $options=[]): Scene {return $this->add('torus',$id,$options);}
    public function add_rounded_box(string $id,array $options=[]): Scene {return $this->add('rounded_box',$id,$options);}
    public function add_extrude(string $id,array $options=[]): Scene {return $this->add('extrude',$id,$options);}
    public function set_background(string $color): Scene {$this->data['background']=self::color($color);return $this;}
    public function set_view(string $name,array $options): Scene {
        self::identifier($name);self::keys($options,['position','target','fov']);
        if(count($this->data['views'])>=16&&!isset($this->data['views'][$name])) self::fail('Too many views');
        $p=self::vector($options['position']??[5000,5000,5000],3,-1000000,1000000);
        $t=self::vector($options['target']??[0,0,0],3,-1000000,1000000);
        if($p===$t) self::fail('Camera position equals target');
        $this->data['views'][$name]=['position'=>$p,'target'=>$t,'fov'=>self::number($options['fov']??45,15,100)];return $this;
    }
    public function animate(string $id,array $options): Scene {
        self::identifier($id);self::keys($options,['property','axis','amplitude','period','phase']);
        if(count($this->data['animations'])>=128) self::fail('Too many animations');
        if(!in_array($options['property']??null,['rotation','position'],true)||!in_array($options['axis']??null,['x','y','z'],true)) self::fail('Invalid animation channel');
        foreach($this->data['animations'] as $a) if($a['id']===$id&&$a['property']===$options['property']&&$a['axis']===$options['axis']) self::fail('Duplicate animation channel');
        $this->data['animations'][]=['id'=>$id,'property'=>$options['property'],'axis'=>$options['axis'],
            'amplitude'=>self::number($options['amplitude']??10,-10000,10000),
            'period'=>self::number($options['period']??3,0.2,3600), 'phase'=>self::number($options['phase']??0,-360,360)];return $this;
    }
    public function with_revision(int $revision): self {
        if($revision<0) self::fail('Invalid revision');$copy=clone $this;$copy->data['revision']=$revision;return $copy;
    }
    public function to_array(): array {
        $objects=array_column($this->data['objects'],null,'id');
        foreach($objects as $id=>$o) {
            $seen=[$id=>true];$p=$o['parent']??null;
            while($p!==null){
                if(isset($seen[$p])||!isset($objects[$p])||$objects[$p]['type']!=='group') self::fail('Invalid group hierarchy');
                $seen[$p]=true;if(count($seen)>32) self::fail('Hierarchy exceeds 32 levels');$p=$objects[$p]['parent']??null;
            }
        }
        foreach($this->data['animations'] as $a) if(!isset($objects[$a['id']])) self::fail('Animation target missing');
        $data=$this->data;
        if(strlen(json_encode($data,JSON_THROW_ON_ERROR))>self::MAX_BYTES) self::fail('Scene exceeds 2MB');
        return $data;
    }
}
