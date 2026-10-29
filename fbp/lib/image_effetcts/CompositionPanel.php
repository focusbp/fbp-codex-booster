<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;

/** Backgrounds and buttons; all coordinates are canvas pixels. */
final class CompositionPanel
{
    private static function object(array $v, array $keys): void
    {
        if (array_diff(array_keys($v), $keys) || in_array(null, $v, true)) throw new InvalidArgumentException('Invalid panel setting.');
    }

    private static function number($v, float $min, float $max, bool $integer = false): float
    {
        if ((!is_int($v) && !is_float($v)) || ($integer && !is_int($v)) || !is_finite((float)$v) || $v < $min || $v > $max) throw new InvalidArgumentException('Panel number outside allowed range.');
        return (float)$v;
    }

    public static function validate(array $p, int $w, int $h): array
    {
        self::object($p, ['type','x','y','width','height','fill','radius','stroke','stroke_width','opacity','gradient','shadow']);
        foreach (['x','y'] as $k) $p[$k] = (int)self::number($p[$k] ?? 0, 0, ($k === 'x' ? $w : $h)-1, true);
        foreach (['width','height'] as $k) $p[$k] = (int)self::number($p[$k] ?? 0, 1, $k === 'width' ? $w : $h, true);
        if ($p['x']+$p['width'] > $w || $p['y']+$p['height'] > $h) throw new InvalidArgumentException('Panel exceeds canvas.');
        $half = min($p['width'],$p['height'])/2;
        $p['radius'] = self::number($p['radius'] ?? 0, 0, $half);
        $p['stroke_width'] = self::number($p['stroke_width'] ?? 0, 0, min(64,$half));
        $p['opacity'] = self::number($p['opacity'] ?? 1, 0, 1);
        foreach (['fill'=>'transparent','stroke'=>'#000000'] as $k=>$default) {
            $p[$k] = $p[$k] ?? $default; CompositionTools::color($p[$k]);
        }
        if (isset($p['gradient'])) {
            $g=$p['gradient'];
            if (!is_array($g)) throw new InvalidArgumentException('Panel gradient must be an object.');
            self::object($g,['from','to','direction']);
            foreach (['from','to'] as $k) {
                CompositionTools::color($g[$k] ?? null);
                if ($g[$k] === 'transparent') throw new InvalidArgumentException('Gradient endpoints must be opaque colors.');
            }
            $g['direction']=$g['direction']??'vertical';
            if (!in_array($g['direction'],['vertical','horizontal'],true)) throw new InvalidArgumentException('Invalid gradient direction.');
            $p['gradient']=$g;
        }
        if (isset($p['shadow'])) {
            $s=$p['shadow'];
            if (!is_array($s)) throw new InvalidArgumentException('Panel shadow must be an object.');
            self::object($s,['x','y','blur','color','opacity']);
            foreach (['x','y'] as $k) $s[$k]=(int)self::number($s[$k]??0,-128,128,true);
            $s['blur']=self::number($s['blur']??8,0,64);
            $s['opacity']=self::number($s['opacity']??0.2,0,1);
            $s['color']=$s['color']??'#000000';CompositionTools::color($s['color']);
            $p['shadow']=$s;
        }
        return $p;
    }

    /** Signed distance to a rounded box; negative values are inside. */
    private static function distance(float $x, float $y, array $p): float
    {
        $r=$p['radius'];
        $qx=abs($x-$p['width']/2)-($p['width']/2-$r);
        $qy=abs($y-$p['height']/2)-($p['height']/2-$r);
        return hypot(max($qx,0),max($qy,0))+min(max($qx,$qy),0)-$r;
    }

    private static function pixel(GdImage $im, int $x, int $y, int $color, float $opacity): void
    {
        if ($opacity <= 0 || ($color >> 24) === 127) return;
        $alpha=(int)round(127*(1-min(1,$opacity)));
        if ($alpha < 127) imagesetpixel($im,$x,$y,($alpha<<24)|($color&0xffffff));
    }

    public static function draw(GdImage $im, array $p): void
    {
        imagealphablending($im,true);
        $s=$p['shadow']??null;
        if ($s) {
            $c=CompositionTools::color($s['color']);$b=$s['blur'];
            for($y=max(0,(int)floor($p['y']+$s['y']-$b));$y<min(imagesy($im),$p['y']+$s['y']+$p['height']+$b);$y++) {
                for($x=max(0,(int)floor($p['x']+$s['x']-$b));$x<min(imagesx($im),$p['x']+$s['x']+$p['width']+$b);$x++) {
                    $d=self::distance($x+0.5-$p['x']-$s['x'],$y+0.5-$p['y']-$s['y'],$p);
                    $a=$b>0 ? max(0,min(1,0.5-$d/(2*$b))) : max(0,min(1,0.5-$d));
                    self::pixel($im,$x,$y,$c,$a*$s['opacity']*$p['opacity']);
                }
            }
        }
        $fill=CompositionTools::color($p['fill']);$stroke=CompositionTools::color($p['stroke']);
        $g=$p['gradient']??null;
        $from=$g ? CompositionTools::color($g['from']) : 0;$to=$g ? CompositionTools::color($g['to']) : 0;
        for($y=0;$y<$p['height'];$y++)for($x=0;$x<$p['width'];$x++) {
            $d=self::distance($x+0.5,$y+0.5,$p);
            $coverage=max(0,min(1,0.5-$d));if(!$coverage)continue;
            $c=$fill;
            if($g) {
                $t=$g['direction']==='vertical' ? $y/max(1,$p['height']-1) : $x/max(1,$p['width']-1);
                $c=0;foreach([16,8,0] as $shift)$c|=((int)round((($from>>$shift)&255)*(1-$t)+(($to>>$shift)&255)*$t))<<$shift;
            }
            // Border is drawn inward; neither fill nor stroke changes the panel bounds.
            $edge=$p['stroke_width']>0 ? max(0,min(1,$d+$p['stroke_width']+0.5)) : 0;
            if($edge>0 && ($stroke>>24)!==127) {
                if(($c>>24)===127) { $c=$stroke;$coverage*=$edge; }
                else { $mixed=0;foreach([16,8,0] as $shift)$mixed|=((int)round((($c>>$shift)&255)*(1-$edge)+(($stroke>>$shift)&255)*$edge))<<$shift;$c=$mixed; }
            }
            self::pixel($im,$p['x']+$x,$p['y']+$y,$c,$coverage*$p['opacity']);
        }
    }
}
