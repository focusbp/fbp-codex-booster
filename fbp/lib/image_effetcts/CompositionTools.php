<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/** Rendering helpers for the generic compositor, independent of Controller and DB. */
final class CompositionTools
{
    public static function color($value): int
    {
        if ($value === 'transparent') return 0x7f000000;
        if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) throw new InvalidArgumentException('Color must be #RRGGBB or transparent.');
        return hexdec(substr($value, 1));
    }

    public static function canvas(int $w, int $h, $color): GdImage
    {
        if ($w < 1 || $h < 1 || $w > 24000000 / $h) throw new InvalidArgumentException('Canvas exceeds 24 million pixels.');
        $image = imagecreatetruecolor($w, $h);
        imagealphablending($image, false); imagesavealpha($image, true);
        imagefill($image, 0, 0, self::color($color));
        return $image;
    }

    /** Crop, clockwise rotation, then reflection. Coordinates are normalized source pixels. */
    public static function transform(GdImage $image, array $layer): GdImage
    {
        if (isset($layer['crop'])) {
            [$x, $y, $w, $h] = $layer['crop'];
            if ($x + $w > imagesx($image) || $y + $h > imagesy($image)) throw new InvalidArgumentException('Crop exceeds normalized source.');
            $crop = imagecrop($image, ['x'=>$x,'y'=>$y,'width'=>$w,'height'=>$h]);
            if (!$crop) throw new RuntimeException('Cannot crop image.');
            imagedestroy($image); $image = $crop;
        }
        $angle = $layer['rotate'] ?? 0;
        if ($angle != 0) {
            $r = deg2rad($angle); $w = imagesx($image); $h = imagesy($image);
            $rw = (int) ceil(abs($w*cos($r))+abs($h*sin($r))); $rh = (int) ceil(abs($w*sin($r))+abs($h*cos($r)));
            if ($rw > 24000000 / max(1, $rh)) throw new InvalidArgumentException('Rotated image exceeds pixel limit.');
            $rotated = imagerotate($image, -$angle, 0x7f000000);
            if (!$rotated) throw new RuntimeException('Cannot rotate image.');
            imagedestroy($image); $image = $rotated;
        }
        $flip = $layer['flip'] ?? 'none';
        if ($flip !== 'none') imageflip($image, ['horizontal'=>IMG_FLIP_HORIZONTAL,'vertical'=>IMG_FLIP_VERTICAL,'both'=>IMG_FLIP_BOTH][$flip]);
        imagealphablending($image, false); imagesavealpha($image, true);
        return $image;
    }

    public static function font($path, string $root): string
    {
        if (!is_string($path) || str_contains($path, '://') || str_contains($path, "\0")) throw new InvalidArgumentException('Local font path required.');
        $path = realpath(str_starts_with($path, '/') ? $path : $root.'/'.$path);
        if (!$path || !is_file($path) || !is_readable($path) || filesize($path)>40*1024*1024 || !in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['ttf','otf','ttc'],true)) throw new InvalidArgumentException('Readable TTF/OTF/TTC font up to 40MB required.');
        return $path;
    }

    /** Widths are outward radii beyond the thickened body, ordered inner to outer. */
    public static function outlines($outlines): array
    {
        if (!is_array($outlines) || !array_is_list($outlines) || count($outlines)>4) throw new InvalidArgumentException('outlines must contain up to four strokes.');
        $previous=0;
        foreach($outlines as $stroke){
            if(!is_array($stroke)||array_diff(array_keys($stroke),['color','width'])||!isset($stroke['color'],$stroke['width']))throw new InvalidArgumentException('Each outline requires only color and width.');
            if(!is_int($stroke['width'])||$stroke['width']<1||$stroke['width']>32||$stroke['width']<=$previous)throw new InvalidArgumentException('Outline widths must strictly increase, integers 1..32px.');
            if($stroke['color']==='transparent')throw new InvalidArgumentException('Outline color must be opaque #RRGGBB.');
            self::color($stroke['color']);$previous=$stroke['width'];
        }
        return $outlines;
    }

    /** Layout first, then render: never silently truncate or shrink supplied copy. */
    public static function text(GdImage $canvas, array $layer, bool $draw = true): void
    {
        $bodyWidth=$layer['body_width']??0;
        if(!is_int($bodyWidth)||$bodyWidth<0||$bodyWidth>32)throw new InvalidArgumentException('body_width must be an integer 0..32px.');
        $outlines=self::outlines($layer['outlines']??[]);
        $margin=$bodyWidth+($outlines ? $outlines[count($outlines)-1]['width'] : 0);
        $work=$bodyWidth>0?(2*$bodyWidth+1)**2:0;foreach($outlines as $stroke)$work+=(2*($bodyWidth+$stroke['width'])+1)**2;
        if($work*mb_strlen($layer['text'],'UTF-8')>250000)throw new InvalidArgumentException('Expanded text exceeds rendering budget; reduce copy length, body width or outline width.');
        $lines = explode("\n", str_replace(["\r\n","\r"], "\n", $layer['text']));
        if (count($lines)>100) throw new InvalidArgumentException('Maximum 100 text lines.');
        $size = $layer['font_size']; $font = $layer['font'];
        $sample = imagettfbbox($size, 0, $font, '国Ag');
        if (!$sample) throw new InvalidArgumentException('Cannot measure supplied font.');
        $ascent = max(0,-min($sample[1],$sample[3],$sample[5],$sample[7]));
        $descent = max(0,max($sample[1],$sample[3],$sample[5],$sample[7]));
        $measured = [];
        foreach ($lines as $line) {
            $box = $line === '' ? [0,0,0,0,0,0,0,0] : imagettfbbox($size, 0, $font, $line);
            if (!$box) throw new InvalidArgumentException('Cannot measure text.');
            // FreeType's rasterized antialias fringe can extend one pixel beyond its bbox.
            $minX=min($box[0],$box[2],$box[4],$box[6])-1;$maxX=max($box[0],$box[2],$box[4],$box[6])+1;
            $ascent=max($ascent,-min($box[1],$box[3],$box[5],$box[7]));$descent=max($descent,max($box[1],$box[3],$box[5],$box[7]));
            if ($maxX-$minX+1+2*$margin > $layer['width']) throw new InvalidArgumentException('Text body and outlines exceed width; add line breaks or change font size.');
            $measured[]=[$line,$minX,$maxX];
        }
        $ascent++;$descent++;
        $step=max((int)ceil($size*$layer['line_height']),$ascent+$descent+1+2*$margin);
        $height=$ascent+$descent+1+2*$margin+(count($lines)-1)*$step;
        if ($height>$layer['height']) throw new InvalidArgumentException('Text exceeds height.');
        if (!$draw) return;
        $y=$layer['y']+$ascent+$margin;
        imagealphablending($canvas,true);
        $color=self::color($layer['color']);
        foreach($measured as [$line,$minX,$maxX]) {
            $space=$layer['width']-($maxX-$minX+1)-2*$margin;
            $x=$layer['x']+$margin-$minX+($layer['align']==='center'?intdiv($space,2):($layer['align']==='right'?$space:0));
            if($line!==''){
                foreach(array_reverse($outlines) as $stroke)self::expandedText($canvas,$size,$x,$y,self::color($stroke['color']),$font,$line,$bodyWidth+$stroke['width']);
                self::expandedText($canvas,$size,$x,$y,$color,$font,$line,$bodyWidth);
            }
            $y+=$step;
        }
    }

    /** Circular dilation of the selected font: synthetic bold, also usable on a bold font. */
    private static function expandedText(GdImage $canvas, int $size, int $x, int $y, int $color, string $font, string $text, int $radius): void
    {
        for($dy=-$radius;$dy<=$radius;$dy++)for($dx=-$radius;$dx<=$radius;$dx++)if($dx*$dx+$dy*$dy<=$radius*$radius){
            if(imagettftext($canvas,$size,0,$x+$dx,$y+$dy,$color,$font,$text)===false)throw new RuntimeException('Text expansion rendering failed.');
        }
    }

    public static function encode(GdImage $image, string $format, int $quality, int $minimum, ?int $maximum, string $matte): array
    {
        $flat = null;
        if($format==='jpeg') {
            if($matte==='transparent') throw new InvalidArgumentException('JPEG requires an opaque matte.');
            $flat=self::canvas(imagesx($image),imagesy($image),$matte);imagealphablending($flat,true);
            imagecopy($flat,$image,0,0,0,0,imagesx($image),imagesy($image));$image=$flat;
        }
        $encode = static function(int $q) use($image,$format): string {
            if($format==='png') return ImageEffects::pngData($image);
            ob_start();
            try {
                $ok=$format==='jpeg'?imagejpeg($image,null,$q):imagewebp($image,null,$q);
                $bytes=ob_get_contents();
            } finally { ob_end_clean(); }
            if(!$ok || !$bytes) throw new RuntimeException('Image encoding failed.');
            return $bytes;
        };
        try {
            $chosen=$quality;$bytes=$encode($quality);
            if($maximum!==null && strlen($bytes)>$maximum && $format!=='png') {
                $best=null;$lo=$minimum;$hi=$quality-1;
                while($lo<=$hi){$q=intdiv($lo+$hi,2);$candidate=$encode($q);if(strlen($candidate)<=$maximum){$best=$candidate;$chosen=$q;$lo=$q+1;}else $hi=$q-1;}
                if($best!==null)$bytes=$best;
            }
            if($maximum!==null && strlen($bytes)>$maximum) throw new InvalidArgumentException('Cannot meet max_bytes at fixed dimensions and minimum quality. Reduce dimensions or minimum quality.');
            return ['bytes'=>$bytes,'quality'=>$format==='png'?null:$chosen];
        } finally { if($flat)imagedestroy($flat); }
    }
}
