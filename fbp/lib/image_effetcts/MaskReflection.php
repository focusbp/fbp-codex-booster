<?php
namespace Fbp\ImageEffects;
use GdImage;
use InvalidArgumentException;

/** Separate half-mask bottom lines, with X fixed and no vertical compression. */
final class MaskReflection {
    public static function create(GdImage $image,GdImage $mask,float $opacity=.4,int $depth=480): array {
        $w=imagesx($image);$h=imagesy($image);
        if($w!==imagesx($mask) || $h!==imagesy($mask) || $w>24000000/$h || !is_finite($opacity) || $opacity<0 || $opacity>1 || $depth<1)
            throw new InvalidArgumentException('Matching images, opacity 0..1 and positive depth required.');
        $layer=imagecreatetruecolor($w,$h);imagealphablending($layer,false);imagesavealpha($layer,true);imagefill($layer,0,0,0x7f000000);
        $reflectedMask=imagecreatetruecolor($w,$h);$mask=ImageEffects::mask($mask);$split=intdiv($w,2);
        $report=['supported'=>false,'reason'=>'empty_mask','method'=>'mask-halves-column-reflection',
            'scale_x'=>1,'scale_y'=>1,'opacity'=>$opacity,'max_depth'=>$depth,'split_x'=>$split,
            'halves'=>[],'reflected_pixels'=>0,'preserves_source_x'=>true,'mode'=>'none',
            'thresholds'=>['max_angle_degrees'=>60,'horizontal_angle_degrees'=>3,
                'horizontal_height_tolerance'=>max(2,(int)floor($h*.005+.5)),
                'max_fit_error'=>max(2,$h*.01),'min_bottom_span'=>max(2,(int)floor($w*.005+.5))]];
        $hasMask=false;$geometry=[];
        foreach([['left',0,$split-1],['right',$split,$w-1]] as [$side,$a,$b]){
            $edge=[];$top=$h;
            for($x=$a;$x<=$b;$x++){
                $first=null;$last=null;
                for($y=0;$y<$h;$y++)if(imagecolorat($mask,$x,$y)&255){if($first===null)$first=$y;$last=$y;}
                if($last!==null){$edge[]=[$x,$last];$top=min($top,$first);}
            }
            $half=['side'=>$side,'x_range'=>[$a,$b],'supported'=>false,'reason'=>'empty_half','contact_line'=>[],'reflected_pixels'=>0];
            $geometry[]=[$edge,$top];
            if(!$edge){$report['halves'][]=$half;continue;}$hasMask=true;
            $bottom=max(array_column($edge,1));
            if($bottom===0 || $bottom===$h-1){$half['reason']='no_reflection_space';$report['halves'][]=$half;continue;}
            $band=$bottom-max(2,(int)floor(($bottom-$top+1)*.10+.5));
            $bottomEdge=array_values(array_filter($edge,static fn($p)=>$p[1]>=$band));
            if(count($bottomEdge)<3){$half['reason']='insufficient_bottom_edge';$report['halves'][]=$half;continue;}
            $trim=(int)floor(count($bottomEdge)*.12);$fit=array_slice($bottomEdge,$trim,count($bottomEdge)-2*$trim);
            $n=count($fit);$sx=0.;$sy=0.;$sxx=0.;$sxy=0.;
            foreach($fit as [$x,$y]){$sx+=$x;$sy+=$y;$sxx+=$x*$x;$sxy+=$x*$y;}
            $slope=($n*$sxy-$sx*$sy)/max(1,$n*$sxx-$sx*$sx);$fittedIntercept=($sy-$slope*$sx)/$n;
            $error=0.;foreach($fit as [$x,$y])$error+=($y-$slope*$x-$fittedIntercept)**2;$error=sqrt($error/$n);
            $angle=rad2deg(atan($slope));$half['detected_angle_degrees']=$angle;$half['fit_error']=$error;
            if(abs($angle)>60 || $fit[$n-1][0]-$fit[0][0]<$report['thresholds']['min_bottom_span'] || $error>$report['thresholds']['max_fit_error']){
                $half['reason']='abnormal_bottom_line';$report['halves'][]=$half;continue;
            }
            $intercept=-INF;
            foreach($edge as [$x,$y])$intercept=max($intercept,$y-$slope*$x);
            if($intercept-$fittedIntercept>max(3,($bottom-$top+1)*.05)){
                $half['reason']='abnormal_bottom_line';$report['halves'][]=$half;continue;
            }
            $line=[];foreach([$bottomEdge[0][0],$bottomEdge[count($bottomEdge)-1][0]] as $x)$line[]=[$x,$intercept+$slope*$x];
            $half=array_merge($half,['supported'=>true,'reason'=>'','floor_y'=>$bottom,'slope'=>$slope,'intercept'=>$intercept,
                'angle_degrees'=>$angle,'contact_line'=>$line,'bottom_edge_points'=>count($bottomEdge)]);
            $report['halves'][]=$half;
        }
        $valid=array_keys(array_filter($report['halves'],static fn($half)=>$half['supported']));
        if(count($valid)===2){
            $report['mode']='split';[$left,$right]=$report['halves'];
            if(abs($left['angle_degrees'])<=3 && abs($right['angle_degrees'])<=3
                && abs($left['floor_y']-$right['floor_y'])<=$report['thresholds']['horizontal_height_tolerance']){
                $report['mode']='horizontal';$floor=max($left['floor_y'],$right['floor_y']);
                foreach($report['halves'] as &$half){
                    $half['slope']=0;$half['intercept']=$floor;$half['angle_degrees']=0;
                    foreach($half['contact_line'] as &$point)$point[1]=$floor;unset($point);
                }unset($half);
            }
        }elseif($valid)$report['mode']=$report['halves'][$valid[0]]['side'].'_only';
        foreach($report['halves'] as $i=>&$half){
            if(!$half['supported'])continue;
            [$edge,$top]=$geometry[$i];$slope=$half['slope'];$intercept=$half['intercept'];
            foreach($edge as [$x,$last]){
                $floor=(int)floor($intercept+$slope*$x+.5);
                for($dy=1;$dy<=min($depth,$h-1-$floor,$floor-$top);$dy++){
                    $sy=$floor-$dy;$y=$floor+$dy;
                    if($sy<0 || $sy>=$h || !(imagecolorat($mask,$x,$sy)&255))continue;
                    $alpha=(int)floor(127*(1-$opacity*(1-$dy/$depth)**2)+.5);if($alpha===127)continue;
                    imagesetpixel($reflectedMask,$x,$y,0xffffff);
                    imagesetpixel($layer,$x,$y,($alpha<<24)|(imagecolorat($image,$x,$sy)&0xffffff));$half['reflected_pixels']++;
                }
            }
            $report['reflected_pixels']+=$half['reflected_pixels'];$report['supported']=true;
        }unset($half);
        $report['reason']=$report['supported']?'':($hasMask?'insufficient_bottom_edges':'empty_mask');
        return ['layer'=>$layer,'mask'=>$reflectedMask,'report'=>$report];
    }
}
