<?php
namespace Fbp\ImageEffects;
use GdImage;
use InvalidArgumentException;

/** Geometric two-contact detector. No image colours, AI, or photo-specific coordinates. */
final class FootContacts {
    public static function detect(GdImage $mask): array {
        $w=imagesx($mask);$h=imagesy($mask);
        if($w>24000000/$h)throw new InvalidArgumentException('Mask too large.');
        $no=static fn($reason)=>['supported'=>false,'reason'=>$reason,'footprints'=>[],'feet'=>[]];
        $mask=ImageEffects::mask($mask);$left=$w;$right=-1;$top=$h;$bottom=-1;
        for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++)if(imagecolorat($mask,$x,$y)&255){$left=min($left,$x);$right=max($right,$x);$top=min($top,$y);$bottom=max($bottom,$y);}
        if($right<0)return $no('empty_mask');
        if($left===0 || $right===$w-1 || $top===0 || $bottom===$h-1)return $no('clipped_subject');
        $band=max($top,$bottom-(int)round(($bottom-$top+1)*.28));$groups=[];
        for($x=$left;$x<=$right;$x++)for($y=$band;$y<=$bottom;$y++)if(imagecolorat($mask,$x,$y)&255){
            $k=count($groups)-1;if($k<0 || $x>$groups[$k][1]+1)$groups[]=[$x,$x];else $groups[$k][1]=$x;break;
        }
        $groups=array_values(array_filter($groups,static fn($g)=>$g[1]-$g[0]+1>=max(3,($right-$left+1)*.04)));
        if(count($groups)!==2)return $no('two_feet_not_separated');
        $footprints=[];$feet=[];
        foreach($groups as [$a,$b]){
            $edge=[];$floor=0;
            for($x=$a;$x<=$b;$x++)for($y=$bottom;$y>=$band;$y--)if(imagecolorat($mask,$x,$y)&255){$edge[]=[$x,$y];$floor=max($floor,$y);break;}
            $edge=array_values(array_filter($edge,static fn($p)=>$p[1]>=$floor-($b-$a+1)*.8));
            if(count($edge)<3)return $no('uncertain_contact');
            $range=[$edge[0][0],$edge[count($edge)-1][0]];
            $trim=(int)floor(count($edge)*.12);$fit=array_slice($edge,$trim,count($edge)-2*$trim);
            $n=count($fit);$sx=0.;$sy=0.;$sxx=0.;$sxy=0.;
            foreach($fit as [$x,$y]){$sx+=$x;$sy+=$y;$sxx+=$x*$x;$sxy+=$x*$y;}
            $slope=($n*$sxy-$sx*$sy)/max(1,$n*$sxx-$sx*$sx);$intercept=($sy-$slope*$sx)/$n;
            $line=[];foreach($range as $x)$line[]=[$x,max(1,min($h-2,(int)round($intercept+$slope*$x)))];
            $footprints[]=array_map(static fn($p)=>[$p[0],max(1,min($h-2,(int)round($intercept+$slope*$p[0])))],$fit);
            $feet[]=['x_range'=>$range,'contact_line'=>$line,'floor_y'=>$floor];
        }
        return ['supported'=>true,'reason'=>'','footprints'=>$footprints,'feet'=>$feet];
    }
}
