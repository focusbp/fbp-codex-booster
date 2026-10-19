<?php
namespace Fbp\ImageEffects;
use GdImage;
use InvalidArgumentException;

/** Foot-only contact/cast shadow inferred from a confirmed full-body binary mask. */
final class FootShadow {
    public static function create(GdImage $mask,array $options,bool $fullBody): array {
        if(array_diff(array_keys($options),['opacity','blur','length','angle']))throw new InvalidArgumentException('Unknown foot shadow setting.');
        $s=array_replace(['opacity'=>.3,'blur'=>5,'length'=>8,'angle'=>200],$options);
        foreach(['opacity'=>[0,1],'blur'=>[0,100],'length'=>[0,50],'angle'=>[-1e9,1e9]] as $k=>[$lo,$hi])
            if((!is_int($s[$k])&&!is_float($s[$k])) || !is_finite((float)$s[$k]) || $s[$k]<$lo || $s[$k]>$hi)throw new InvalidArgumentException('Invalid foot shadow '.$k);
        $w=imagesx($mask);$h=imagesy($mask);if($w>24000000/$h)throw new InvalidArgumentException('Mask too large.');
        $layer=imagecreatetruecolor($w,$h);imagealphablending($layer,false);imagesavealpha($layer,true);imagefill($layer,0,0,0x7f000000);
        $empty=static fn($reason)=>['layer'=>$layer,'report'=>['supported'=>false,'reason'=>$reason,'footprints'=>[],'settings'=>$s]];
        if(!$fullBody)return $empty('full_body_not_confirmed');
        $mask=ImageEffects::mask($mask);$left=$w;$right=-1;$top=$h;$bottom=-1;
        for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++)if(imagecolorat($mask,$x,$y)&255){$left=min($left,$x);$right=max($right,$x);$top=min($top,$y);$bottom=max($bottom,$y);}
        if($right<0)return $empty('empty_mask');
        if($left===0 || $right===$w-1 || $top===0 || $bottom===$h-1)return $empty('clipped_subject');
        $band=max($top,$bottom-(int)round(($bottom-$top+1)*.28));$groups=[];
        for($x=$left;$x<=$right;$x++)for($y=$band;$y<=$bottom;$y++)if(imagecolorat($mask,$x,$y)&255){
            $k=count($groups)-1;if($k<0 || $x>$groups[$k][1]+1)$groups[]=[$x,$x];else $groups[$k][1]=$x;break;
        }
        $groups=array_values(array_filter($groups,static fn($g)=>$g[1]-$g[0]+1>=max(3,($right-$left+1)*.04)));
        if(count($groups)!==2)return $empty('two_feet_not_separated');
        $footprints=[];
        foreach($groups as [$a,$b]){
            $edge=[];$floor=0;
            for($x=$a;$x<=$b;$x++)for($y=$bottom;$y>=$band;$y--)if(imagecolorat($mask,$x,$y)&255){$edge[]=[$x,$y];$floor=max($floor,$y);break;}
            $edge=array_values(array_filter($edge,static fn($p)=>$p[1]>=$floor-($b-$a+1)*.8));
            if(count($edge)<3)return $empty('uncertain_contact');
            $trim=(int)floor(count($edge)*.12);$edge=array_slice($edge,$trim,count($edge)-2*$trim);
            $n=count($edge);$sx=0.;$sy=0.;$sxx=0.;$sxy=0.;
            foreach($edge as [$x,$y]){$sx+=$x;$sy+=$y;$sxx+=$x*$x;$sxy+=$x*$y;}
            $slope=($n*$sxy-$sx*$sy)/max(1,$n*$sxx-$sx*$sx);$intercept=($sy-$slope*$sx)/$n;
            foreach($edge as &$p)$p[1]=max(1,min($h-2,(int)round($intercept+$slope*$p[0])));unset($p);
            $footprints[]=$edge;
        }
        $core=imagecreatetruecolor($w,$h);$soft=imagecreatetruecolor($w,$h);
        $rx=max(2,(int)round($w*.006));$ry=max(1,(int)round($h*.002));
        $dx=cos(deg2rad(fmod($s['angle'],360)))*$s['length']/100*$w;
        $dy=sin(deg2rad(fmod($s['angle'],360)))*$s['length']/100*$w;
        for($step=8;$step>=0;$step--){
            $t=$step/8;$v=(int)round(255*(1-$t*.85)**2);$gray=($v<<16)|($v<<8)|$v;
            foreach($footprints as $edge)foreach($edge as $i=>[$x,$y])if($i%2===0){
                imagefilledellipse($soft,(int)round($x+$dx*$t),(int)round($y+$dy*$t),2*$rx,2*$ry,$gray);
                if($step===0)imagefilledellipse($core,$x,$y,2*$rx,2*$ry,0xffffff);
            }
        }
        require_once __DIR__.'/SoftBlur.php';$soft=SoftBlur::apply($soft,$s['blur']);
        for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++){
            $f=$s['opacity']*min(1,((imagecolorat($core,$x,$y)&255)*.65+(imagecolorat($soft,$x,$y)&255)*.6)/255);
            imagesetpixel($layer,$x,$y,((int)round(127*(1-$f)))<<24);
        }
        return ['layer'=>$layer,'report'=>['supported'=>true,'reason'=>'','footprints'=>$footprints,'settings'=>$s]];
    }
}
