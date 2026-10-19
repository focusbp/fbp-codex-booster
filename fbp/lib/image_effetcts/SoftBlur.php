<?php
namespace Fbp\ImageEffects;
use GdImage;
use InvalidArgumentException;
/** Small separable GD blur, downsampled for wider soft effects. */
final class SoftBlur {
    public static function apply(GdImage $image,float $radius): GdImage {
        if(!is_finite($radius) || $radius<0 || $radius>100)throw new InvalidArgumentException('Blur radius must be 0..100.');
        if($radius==0)return $image;
        $w=imagesx($image);$h=imagesy($image);$factor=max(1,$radius/2);
        $small=imagecreatetruecolor(max(1,(int)round($w/$factor)),max(1,(int)round($h/$factor)));
        imagealphablending($small,false);imagesavealpha($small,true);
        imagecopyresampled($small,$image,0,0,0,0,imagesx($small),imagesy($small),$w,$h);
        for($i=0;$i<max(1,(int)round(min(2,$radius)**2));$i++)imagefilter($small,IMG_FILTER_GAUSSIAN_BLUR);
        $out=imagecreatetruecolor($w,$h);imagealphablending($out,false);imagesavealpha($out,true);
        imagecopyresampled($out,$small,0,0,0,0,$w,$h,imagesx($small),imagesy($small));return $out;
    }
}
