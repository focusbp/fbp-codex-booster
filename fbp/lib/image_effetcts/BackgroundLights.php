<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;

/** Ordered elliptical Gaussian lights, applied to a background before subject composition. */
final class BackgroundLights
{
    private static function number($v,float $min,float $max,string $name): float
    {
        if((!is_int($v) && !is_float($v)) || !is_finite((float)$v) || $v<$min || $v>$max)
            throw new InvalidArgumentException($name.' is outside its finite numeric range.');
        return (float)$v;
    }

    public static function validate(array $config): array
    {
        if(array_keys($config)!==['lights'] || !is_array($config['lights']) || !array_is_list($config['lights']) || count($config['lights'])>32)
            throw new InvalidArgumentException('Config requires 0..32 lights only.');
        $result=[];
        foreach($config['lights'] as $light){
            if(!is_array($light) || array_diff(array_keys($light),['center','radius','angle','color','strength','falloff','blend']))
                throw new InvalidArgumentException('Unknown light field.');
            $s=array_replace(['center'=>[50,50],'radius'=>[30,30],'angle'=>0,'color'=>'#FFFFFF','strength'=>.3,'falloff'=>2,'blend'=>'screen'],$light);
            foreach(['center'=>[-100,200],'radius'=>[.1,400]] as $key=>[$min,$max]){
                if(!is_array($s[$key]) || !array_is_list($s[$key]) || count($s[$key])!==2) throw new InvalidArgumentException($key.' requires [x,y].');
                foreach($s[$key] as $v)self::number($v,$min,$max,$key);
            }
            $a=self::number($s['angle'],-1e9,1e9,'angle');$s['angle']=fmod(fmod($a,360)+360,360);
            self::number($s['strength'],0,1,'strength');self::number($s['falloff'],.25,8,'falloff');
            if(!is_string($s['color']) || !preg_match('/^#[0-9a-fA-F]{6}$/D',$s['color']))throw new InvalidArgumentException('Use #RRGGBB.');
            if(!in_array($s['blend'],['screen','normal','multiply'],true))throw new InvalidArgumentException('Invalid blend.');
            $s['color']=strtoupper($s['color']);$result[]=$s;
        }
        return ['lights'=>$result];
    }

    public static function apply(GdImage $background,array $config): GdImage
    {
        $config=self::validate($config);$w=imagesx($background);$h=imagesy($background);
        if($w>24000000/$h)throw new InvalidArgumentException('Canvas must contain 1..24000000 pixels.');
        $result=imagecreatetruecolor($w,$h);imagealphablending($result,false);imagesavealpha($result,true);
        imagecopy($result,$background,0,0,0,0,$w,$h);
        foreach($config['lights'] as $s){
            if($s['strength']==0)continue;
            $cx=$s['center'][0]/100*max(1,$w-1);$cy=$s['center'][1]/100*max(1,$h-1);
            $rx=$s['radius'][0]/100*$w;$ry=$s['radius'][1]/100*$h;
            $co=cos(deg2rad($s['angle']));$si=sin(deg2rad($s['angle']));
            $c=hexdec(substr($s['color'],1));$colors=[($c>>16)&255,($c>>8)&255,$c&255];
            for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++){
                $dx=$x-$cx;$dy=$y-$cy;
                $distance=hypot(($dx*$co+$dy*$si)/$rx,(-$dx*$si+$dy*$co)/$ry);
                $f=$s['strength']*exp(-.5*($distance**$s['falloff']));
                $old=imagecolorat($result,$x,$y);$new=$old&0x7f000000;
                foreach([16,8,0] as $i=>$shift){
                    $v=($old>>$shift)&255;$c=$colors[$i];
                    if($s['blend']==='screen')$target=255-(255-$v)*(255-$c)/255;
                    elseif($s['blend']==='multiply')$target=$v*$c/255;
                    else $target=$c;
                    $new|=max(0,min(255,(int)floor($v+($target-$v)*$f+.5)))<<$shift;
                }
                imagesetpixel($result,$x,$y,$new);
            }
        }
        return $result;
    }
}
