<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;

/** 2D approximation along a projected sole contact line; does not reconstruct hidden surfaces. */
final class SurfaceReflection
{
    public static function create(GdImage $image,GdImage $mask,array $line,bool $fullBody,float $opacity=.4,int $depth=480,float $scale=.75): GdImage
    {
        $w=imagesx($image);$h=imagesy($image);
        if(!$fullBody || $w!==imagesx($mask) || $h!==imagesy($mask) || $w>24000000/$h
            || !is_finite($opacity) || $opacity<0 || $opacity>1 || $depth<1 || !is_finite($scale) || $scale<.1 || $scale>2
            || !array_is_list($line) || count($line)<2 || count($line)>$w)
            throw new InvalidArgumentException('Invalid surface reflection parameters.');
        $last=-1;
        foreach($line as $p){
            if(!is_array($p) || !array_is_list($p) || count($p)!==2 || !is_int($p[0]) || !is_int($p[1])
                || $p[0]<0 || $p[0]>=$w || $p[0]<=$last || $p[1]<1 || $p[1]>=$h-1)
                throw new InvalidArgumentException('Contact points must increase in X within the image.');
            $last=$p[0];
        }
        $mask=ImageEffects::mask($mask);
        $layer=imagecreatetruecolor($w,$h);imagealphablending($layer,false);imagesavealpha($layer,true);imagefill($layer,0,0,0x7f000000);
        $segment=0;
        for($x=$line[0][0];$x<=$last;$x++){
            while($segment<count($line)-2 && $x>$line[$segment+1][0])$segment++;
            [$ax,$ay]=$line[$segment];[$bx,$by]=$line[$segment+1];
            $floor=(int)floor($ay+($by-$ay)*($x-$ax)/($bx-$ax)+.5);
            for($dy=1;$dy<=min($depth,$h-1-$floor);$dy++){
                $sy=$floor-max(1,(int)floor($dy/$scale+.5));if($sy<0)break;
                if(!(imagecolorat($mask,$x,$sy)&255))continue;
                $alpha=(int)floor(127*(1-$opacity*(1-$dy/$depth)**2)+.5);
                imagesetpixel($layer,$x,$floor+$dy,($alpha<<24)|(imagecolorat($image,$x,$sy)&0xffffff));
            }
        }
        return $layer;
    }
}
