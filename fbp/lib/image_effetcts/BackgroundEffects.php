<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;

/** JSON primitives and reproducible procedural background layers. PHP/GD only. */
final class BackgroundEffects
{
    private static function num($v, float $lo, float $hi): float
    {
        if ((!is_int($v) && !is_float($v)) || !is_finite((float)$v) || $v<$lo || $v>$hi)
            throw new InvalidArgumentException('Expected finite number in '.$lo.'..'.$hi);
        return (float)$v;
    }
    private static function pair($v,float $lo,float $hi): array
    {
        if(!is_array($v) || !array_is_list($v) || count($v)!==2)throw new InvalidArgumentException('Expected pair');
        foreach($v as $n)self::num($n,$lo,$hi);return $v;
    }
    private static function color($v): string
    {
        if(!is_string($v) || !preg_match('/^#[0-9a-fA-F]{6}$/D',$v))throw new InvalidArgumentException('Use #RRGGBB');
        return strtoupper($v);
    }
    private static function shape(array $e): array
    {
        $kind=$e['type']??null;
        if(!in_array($kind,['line','circle','ellipse'],true))throw new InvalidArgumentException('Unknown shape');
        $allowed=array_merge(['type','center','color','opacity','angle','blur'],$kind==='line'?['length','thickness','start','end']:['radius']);
        if(array_diff(array_keys($e),$allowed))throw new InvalidArgumentException('Unknown shape field');
        $p=['type'=>$kind,'center'=>self::pair($e['center']??[50,50],-200,300),'color'=>self::color($e['color']??'#FFFFFF'),
            'opacity'=>self::num($e['opacity']??.5,0,1),'angle'=>self::num($e['angle']??0,-360000,360000),'blur'=>self::num($e['blur']??0,0,100)];
        if($kind==='line'){
            $p['length']=self::num($e['length']??30,.01,500);$p['thickness']=self::num($e['thickness']??.3,.01,100);
            if(array_key_exists('start',$e) || array_key_exists('end',$e)){
                if(array_intersect(array_keys($e),['center','angle','length']))throw new InvalidArgumentException('Use start/end OR center/angle/length');
                unset($p['center'],$p['angle'],$p['length']);$p['start']=self::pair($e['start']??null,-200,300);$p['end']=self::pair($e['end']??null,-200,300);
            }
        }else $p['radius']=$kind==='circle'?self::num($e['radius']??10,.01,200):self::pair($e['radius']??[10,6],.01,200);
        return $p;
    }
    /** Expand presets to portable, editable primitive JSON. Omitted seed is random. */
    public static function resolve(array $config): array
    {
        if(in_array(null,$config,true))throw new InvalidArgumentException('Null fields are not supported');
        if(array_diff(array_keys($config),['seed','effects']))throw new InvalidArgumentException('Use seed and effects only');
        $seed=$config['seed']??random_int(0,2147483647);
        if(!is_int($seed) || $seed<0 || $seed>2147483647)throw new InvalidArgumentException('Integer seed 0..2147483647 required');
        $effects=$config['effects']??null;
        if(!is_array($effects) || !array_is_list($effects) || count($effects)>256)throw new InvalidArgumentException('effects requires 0..256 entries');
        $state=$seed;$result=[];
        $rand=static function(float $a,float $b)use(&$state):float{$state=(1664525*$state+1013904223)%4294967296;return $a+($b-$a)*$state/4294967296;};
        $append=static function(array $p)use(&$result):void{
            $result[]=self::shape($p);if(count($result)>256)throw new InvalidArgumentException('Expanded effects exceed 256 shapes; reduce count');
        };
        foreach($effects as $e){
            if(!is_array($e))throw new InvalidArgumentException('Effect must be an object');
            if(in_array(null,$e,true))throw new InvalidArgumentException('Null fields are not supported');
            $kind=$e['type']??null;
            if(in_array($kind,['line','circle','ellipse'],true)){$append($e);continue;}
            if(!in_array($kind,['spotlight','lightning','cloud','fireworks'],true) || array_diff(array_keys($e),['type','count','region','size','angle','colors','opacity','blur','impact']))throw new InvalidArgumentException('Unknown preset or preset field');
            $impact=self::num($e['impact']??1,.25,2);
            $count=$e['count']??1;if(!is_int($count) || $count<1 || $count>8)throw new InvalidArgumentException('Integer count 1..8 required');
            $region=$e['region']??[10,10,90,75];
            if(!is_array($region) || !array_is_list($region) || count($region)!==4)throw new InvalidArgumentException('region requires [left,top,right,bottom]');
            foreach($region as $v)self::num($v,0,100);
            if($region[0]>$region[2] || $region[1]>$region[3])throw new InvalidArgumentException('Inverted region');
            $size=self::pair($e['size']??[15,30],1,70);$angle=self::pair($e['angle']??[-20,20],-360,360);
            if($size[0]>$size[1] || $angle[0]>$angle[1])throw new InvalidArgumentException('Inverted range');
            $colors=$e['colors']??($kind==='fireworks'?['#FFD66B','#FF73A8','#A3DFFF']:['#DCE8FF','#FFFFFF','#B4CEFF']);
            if(!is_array($colors) || !array_is_list($colors) || count($colors)<1 || count($colors)>16)throw new InvalidArgumentException('colors requires 1..16 colors');
            $colors=array_map([self::class,'color'],$colors);$opacity=self::num($e['opacity']??.65,0,1);$blur=self::num($e['blur']??($kind==='fireworks'?2.4:1.2),0,20)*$impact;
            for($i=0;$i<$count;$i++){
                $cx=$rand($region[0],$region[2]);$cy=$rand($region[1],$region[3]);$s=$rand(...$size)*$impact;$a=$rand(...$angle);$c=$colors[(int)$rand(0,count($colors))];
                $point=static function(float $x,float $y)use($cx,$cy,$a):array{$co=cos(deg2rad($a));$si=sin(deg2rad($a));return [$cx+$x*$co-$y*$si,$cy+$x*$si+$y*$co];};
                $add=static function(string $type,array $center,float $alpha,float $b,array $extra)use($append,$c):void{$append(array_merge(['type'=>$type,'center'=>$center,'color'=>$c,'opacity'=>$alpha,'blur'=>$b],$extra));};
                $line=static function(array $p,array $q,float $width,float $alpha,float $b)use($append,$c,$impact):void{$append(['type'=>'line','start'=>$p,'end'=>$q,'color'=>$c,'opacity'=>$alpha,'blur'=>$b,'thickness'=>$width*$impact]);};
                if($kind==='spotlight')$add('ellipse',[$cx,$cy],$opacity,max(.1,$blur+$s*.2),['radius'=>[$s,$s*$rand(.35,.8)],'angle'=>$a]);
                elseif($kind==='cloud'){
                    for($n=0;$n<9;$n++){
                        $x=($n-4)*$s*.22;$y=$rand(-$s*.12,$s*.12);
                        $add('ellipse',$point($x,$y),$opacity*.5,$blur+$s*.06,['radius'=>[$s*$rand(.2,.35),$s*$rand(.12,.25)],'angle'=>$a]);
                    }
                }elseif($kind==='fireworks'){
                    for($n=0;$n<18;$n++){
                        $theta=deg2rad($n*20+$rand(-5,5));$r=$s*$rand(.65,1);
                        $p=$point(cos($theta)*$r*.28,sin($theta)*$r*.28);$q=$point(cos($theta)*$r,sin($theta)*$r);
                        $line($p,$q,.22,$opacity*.7,$blur*.6);$add('circle',$q,$opacity,$blur*.8,['radius'=>$rand(.2,.5)*$impact]);
                    }
                    $add('circle',[$cx,$cy],$opacity*.6,$blur,['radius'=>.5*$impact]);
                }else{
                    $pts=[$point(0,-$s*.8)];for($n=1;$n<=8;$n++)$pts[]=$point($rand(-$s*.14,$s*.14),-$s*.8+$n*$s*.2);
                    for($n=0;$n<8;$n++){$line($pts[$n],$pts[$n+1],.65,$opacity*.45,$blur);$line($pts[$n],$pts[$n+1],.12,$opacity,0);}
                    foreach([3,5] as $n){$q=[$pts[$n][0]+$rand(-$s*.45,$s*.45),$pts[$n][1]+$s*.25];$line($pts[$n],$q,.14,$opacity*.7,$blur*.25);}
                }
            }
        }
        return ['seed'=>$seed,'effects'=>$result];
    }
    /** Transparent layer and its resolved JSON. Long edge renders at <=640px, then scales. */
    public static function render(int $width,int $height,array $config): array
    {
        if(min($width,$height)<1 || $width>24000000/$height)throw new InvalidArgumentException('Canvas requires 1..24000000 pixels');
        $resolved=self::resolve($config);$scale=min(1,640/max($width,$height));$w=max(1,(int)round($width*$scale));$h=max(1,(int)round($height*$scale));$unit=min($w,$h)/100;
        $image=imagecreatetruecolor($w,$h);imagealphablending($image,false);imagesavealpha($image,true);imagefill($image,0,0,0x7f000000);$work=0;
        foreach($resolved['effects'] as $p){
            if(isset($p['start'])){
                $x0=$p['start'][0]*($w-1)/100;$y0=$p['start'][1]*($h-1)/100;$x1=$p['end'][0]*($w-1)/100;$y1=$p['end'][1]*($h-1)/100;
                $cx=($x0+$x1)/2;$cy=($y0+$y1)/2;$angle=atan2($y1-$y0,$x1-$x0);$rx=hypot($x1-$x0,$y1-$y0)/2;
            }else{$cx=$p['center'][0]*($w-1)/100;$cy=$p['center'][1]*($h-1)/100;$angle=deg2rad($p['angle']);$rx=($p['length']??0)*$unit/2;}
            $co=cos($angle);$si=sin($angle);$blur=$p['blur']*$unit;
            if($p['type']==='line'){$ry=$p['thickness']*$unit/2;$bound=$rx+$ry;}
            else{$r=$p['type']==='circle'?[$p['radius'],$p['radius']]:$p['radius'];$rx=$r[0]*$unit;$ry=$r[1]*$unit;$bound=max($rx,$ry);}
            $bound+=max(1,$blur)*5;$x0=max(0,(int)floor($cx-$bound));$x1=min($w,(int)ceil($cx+$bound)+1);$y0=max(0,(int)floor($cy-$bound));$y1=min($h,(int)ceil($cy+$bound)+1);
            if($x1<=$x0 || $y1<=$y0 || $p['opacity']==0)continue;
            $work+=($x1-$x0)*($y1-$y0);if($work>50000000)throw new InvalidArgumentException('Effect render work exceeds budget');
            $rgb=hexdec(substr($p['color'],1));$colors=[($rgb>>16)&255,($rgb>>8)&255,$rgb&255];
            for($y=$y0;$y<$y1;$y++)for($x=$x0;$x<$x1;$x++){
                $dx=$x-$cx;$dy=$y-$cy;$u=$dx*$co+$dy*$si;$v=-$dx*$si+$dy*$co;
                $d=$p['type']==='line'?hypot(max(0,abs($u)-$rx),$v)-$ry:(hypot($u/$rx,$v/$ry)-1)*min($rx,$ry);
                $alpha=$p['opacity']*($blur?(.5-.5*tanh($d/max(.5,$blur)*1.6)):max(0,min(1,.5-$d)));
                if($alpha<.00001)continue;
                $old=imagecolorat($image,$x,$y);$oa=1-(($old>>24)&127)/127;$na=$alpha+$oa*(1-$alpha);
                $new=((int)round(127*(1-$na)))<<24;
                foreach([16,8,0] as $i=>$shift)$new|=((int)round(($colors[$i]*$alpha+(($old>>$shift)&255)*$oa*(1-$alpha))/$na))<<$shift;
                imagesetpixel($image,$x,$y,$new);
            }
        }
        if($w!==$width || $h!==$height){
            $large=imagecreatetruecolor($width,$height);imagealphablending($large,false);imagesavealpha($large,true);imagefill($large,0,0,0x7f000000);
            imagecopyresampled($large,$image,0,0,0,0,$width,$height,$w,$h);$image=$large;
        }
        return ['layer'=>$image,'config'=>$resolved];
    }
}
