<?php
namespace Fbp\ImageEffects;
use GdImage;
use InvalidArgumentException;

/** Reflect source pixels using geometry derived entirely from its binary mask. */
final class MaskReflection {
    public static function create(GdImage $image,GdImage $mask,float $opacity=.4,int $depth=480): array {
        $w=imagesx($image);$h=imagesy($image);
        if($w!==imagesx($mask) || $h!==imagesy($mask) || $w>24000000/$h || !is_finite($opacity) || $opacity<0 || $opacity>1 || $depth<1)
            throw new InvalidArgumentException('Matching images, opacity 0..1 and positive depth required.');
        require_once __DIR__.'/FootContacts.php';$contacts=FootContacts::detect($mask);
        $layer=imagecreatetruecolor($w,$h);imagealphablending($layer,false);imagesavealpha($layer,true);imagefill($layer,0,0,0x7f000000);
        $reflectedMask=imagecreatetruecolor($w,$h);
        $report=['supported'=>$contacts['supported'],'reason'=>$contacts['reason'],'method'=>'mask-derived-two-contacts',
            'scale_x'=>1,'scale_y'=>1,'opacity'=>$opacity,'max_depth'=>$depth,'feet'=>[],'preserves_source_x'=>true];
        if(!$contacts['supported'])return ['layer'=>$layer,'mask'=>$reflectedMask,'report'=>$report];
        $mask=ImageEffects::mask($mask);
        foreach($contacts['feet'] as $foot){
            [$a,$b]=$foot['x_range'];[[$ax,$ay],[$bx,$by]]=$foot['contact_line'];
            $maxDepth=min($depth,max(1,(int)round(($b-$a+1)*1.4)));$count=0;
            for($x=$a;$x<=$b;$x++){
                $floor=(int)round($ay+($by-$ay)*($x-$ax)/($bx-$ax));
                for($dy=1;$dy<=min($maxDepth,$h-1-$floor);$dy++){
                    $sy=$floor-$dy;$y=$floor+$dy;if($sy<0)break;
                    if(!(imagecolorat($mask,$x,$sy)&255) || (imagecolorat($mask,$x,$y)&255))continue;
                    $alpha=(int)round(127*(1-$opacity*(1-$dy/$maxDepth)**2));
                    if($alpha===127)continue;
                    imagesetpixel($reflectedMask,$x,$y,0xffffff);
                    imagesetpixel($layer,$x,$y,($alpha<<24)|(imagecolorat($image,$x,$sy)&0xffffff));$count++;
                }
            }
            $report['feet'][]=$foot+['depth'=>$maxDepth,'reflected_pixels'=>$count];
        }
        return ['layer'=>$layer,'mask'=>$reflectedMask,'report'=>$report];
    }
}
