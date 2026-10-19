<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;

/** Piecewise sRGB interpolation. angle=0 down, 90 right, 180 up, 270 left. */
final class Gradient
{
    public static function validate(array $config): array
    {
        if (array_diff(array_keys($config), ['angle','stops'])) throw new InvalidArgumentException('Unknown gradient field.');
        $angle=array_key_exists('angle',$config)?$config['angle']:0;
        if ((!is_int($angle) && !is_float($angle)) || !is_finite((float)$angle)) throw new InvalidArgumentException('Angle must be finite.');
        $stops=$config['stops']??null;
        if (!is_array($stops) || !array_is_list($stops) || count($stops)<2 || count($stops)>256) throw new InvalidArgumentException('Use 2..256 stops.');
        $previous=-1;$normalized=[];
        foreach($stops as $s){
            if(!is_array($s) || array_diff(array_keys($s),['position','color','easing'])) throw new InvalidArgumentException('Invalid stop fields.');
            $p=$s['position']??null;$color=$s['color']??null;$easing=array_key_exists('easing',$s)?$s['easing']:'linear';
            if((!is_int($p) && !is_float($p)) || !is_finite((float)$p) || $p<0 || $p>100 || $p<=$previous) throw new InvalidArgumentException('Positions must increase within 0..100.');
            if(!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/D',$color)) throw new InvalidArgumentException('Color must be #RRGGBB.');
            if(!in_array($easing,['linear','smooth'],true)) throw new InvalidArgumentException('Invalid easing.');
            $normalized[]=['position'=>$p,'color'=>strtoupper($color),'easing'=>$easing];$previous=$p;
        }
        if($normalized[0]['position']!=0 || $previous!=100) throw new InvalidArgumentException('First/last positions must be 0/100.');
        return ['angle'=>fmod(fmod((float)$angle,360)+360,360),'stops'=>$normalized];
    }

    public static function render(int $width,int $height,array $config): GdImage
    {
        $config=self::validate($config);
        if($width<1 || $height<1 || $width>24000000/$height) throw new InvalidArgumentException('Canvas must contain 1..24000000 pixels.');
        $dx=sin(deg2rad($config['angle']));$dy=cos(deg2rad($config['angle']));
        if(abs($dx)<1e-12)$dx=0.;if(abs($dy)<1e-12)$dy=0.;
        $low=min(0,($width-1)*$dx)+min(0,($height-1)*$dy);
        $span=abs(($width-1)*$dx)+abs(($height-1)*$dy);
        $stops=$config['stops'];$colors=[];
        foreach($stops as $s){$n=hexdec(substr($s['color'],1));$colors[]=[($n>>16)&255,($n>>8)&255,$n&255];}
        $pixel=static function($x,$y)use($dx,$dy,$low,$span,$stops,$colors){
            $p=$span ? max(0,min(100,($x*$dx+$y*$dy-$low)/$span*100)):0;$k=0;
            while($k<count($stops)-2 && $p>$stops[$k+1]['position'])$k++;
            $t=($p-$stops[$k]['position'])/($stops[$k+1]['position']-$stops[$k]['position']);
            if($stops[$k]['easing']==='smooth')$t=$t*$t*(3-2*$t);
            $c=[];for($i=0;$i<3;$i++)$c[]=(int)floor($colors[$k][$i]+($colors[$k+1][$i]-$colors[$k][$i])*$t+.5);
            return ($c[0]<<16)|($c[1]<<8)|$c[2];
        };
        $image=imagecreatetruecolor($width,$height);
        if($dx==0.)for($y=0;$y<$height;$y++)imageline($image,0,$y,$width-1,$y,$pixel(0,$y));
        elseif($dy==0.)for($x=0;$x<$width;$x++)imageline($image,$x,0,$x,$height-1,$pixel($x,0));
        else for($y=0;$y<$height;$y++)for($x=0;$x<$width;$x++)imagesetpixel($image,$x,$y,$pixel($x,$y));
        return $image;
    }
}
