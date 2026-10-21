<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/** Explicit RGB ICC -> sRGB normalization through Little CMS, optional CLI dependency. */
final class ColorProfiles
{
    public static function engine(): \FFI
    {
        if (!class_exists(\FFI::class)) throw new RuntimeException('sRGB conversion requires PHP FFI and liblcms2.');
        try {
            return \FFI::cdef('void *cmsOpenProfileFromMem(const void *, unsigned int);
                void *cmsCreate_sRGBProfile(void); unsigned int cmsGetColorSpace(void *);
                int cmsCloseProfile(void *); void *cmsCreateTransform(void *, unsigned int, void *, unsigned int, unsigned int, unsigned int);
                void cmsDoTransform(void *, const void *, void *, unsigned int); void cmsDeleteTransform(void *);', 'liblcms2.so.2');
        } catch (\Throwable $e) { throw new RuntimeException('sRGB conversion requires enabled PHP FFI and liblcms2.so.2.',0,$e); }
    }

    /** Read bounded embedded PNG iCCP / JPEG APP2 profile bytes; do not infer from profile names. */
    public static function extract(string $bytes): ?string
    {
        if (str_starts_with($bytes,"\x89PNG\r\n\x1a\n")) {
            $profile=null;
            for($p=8,$n=strlen($bytes);$p+12<=$n;){
                $len=unpack('N',substr($bytes,$p,4))[1];
                if($len>$n-$p-12)throw new InvalidArgumentException('Malformed PNG chunk.');
                $type=substr($bytes,$p+4,4);$data=substr($bytes,$p+8,$len);
                if($type==='iCCP'){
                    if($profile!==null)throw new InvalidArgumentException('Duplicate ICC profile.');
                    $z=strpos($data,"\0");
                    if($z===false||$z<1||$z>79||substr($data,$z+1,1)!=="\0")throw new InvalidArgumentException('Malformed PNG ICC profile.');
                    $profile=@gzuncompress(substr($data,$z+2),4*1024*1024);
                    if($profile===false)throw new InvalidArgumentException('Invalid or oversized compressed ICC profile.');
                }
                $p+=12+$len;
            }
            return $profile;
        }
        if(!str_starts_with($bytes,"\xff\xd8"))throw new InvalidArgumentException('ICC normalization accepts PNG/JPEG.');
        $parts=[];$count=null;
        for($p=2,$n=strlen($bytes);$p+4<=$n;){
            if(ord($bytes[$p])!==255)throw new InvalidArgumentException('Malformed JPEG marker.');
            while($p<$n && ord($bytes[$p])===255)$p++;
            if($p>=$n)break;$marker=ord($bytes[$p++]);
            if($marker===0xda||$marker===0xd9)break;
            if($marker===1||($marker>=0xd0&&$marker<=0xd7))continue;
            if($p+2>$n)throw new InvalidArgumentException('Malformed JPEG length.');
            $len=unpack('n',substr($bytes,$p,2))[1];
            if($len<2||$p+$len>$n)throw new InvalidArgumentException('Malformed JPEG segment.');
            $data=substr($bytes,$p+2,$len-2);$p+=$len;
            if($marker===0xe2 && str_starts_with($data,"ICC_PROFILE\0")){
                if(strlen($data)<14)throw new InvalidArgumentException('Malformed JPEG ICC sequence.');
                $i=ord($data[12]);$total=ord($data[13]);
                if(!$i||!$total||$i>$total||isset($parts[$i])||($count!==null&&$count!==$total))throw new InvalidArgumentException('Invalid JPEG ICC sequence.');
                $count=$total;$parts[$i]=substr($data,14);
            }
        }
        if(!$parts)return null;
        if(count($parts)!==$count)throw new InvalidArgumentException('Incomplete JPEG ICC profile.');
        ksort($parts);$icc=implode('',$parts);
        if(strlen($icc)>4*1024*1024)throw new InvalidArgumentException('ICC profile exceeds 4MB.');
        return $icc;
    }

    public static function normalize(string $path): GdImage
    {
        if(str_contains($path,'://')||!is_file($path)||!is_readable($path)||filesize($path)>40*1024*1024)throw new InvalidArgumentException('Readable local image up to 40MB required.');
        $bytes=file_get_contents($path);if($bytes===false)throw new RuntimeException('Cannot read ICC input.');
        $icc=self::extract($bytes);$info=@getimagesizefromstring($bytes);
        if(!$info||(($info['channels']??3)!==3 && $info[2]===IMAGETYPE_JPEG))throw new InvalidArgumentException('Only RGB JPEG ICC conversion is supported; CMYK/gray inputs require separate conversion.');
        if($icc!==null && (strlen($icc)<128||strlen($icc)>4*1024*1024||substr($icc,36,4)!=='acsp'||unpack('N',substr($icc,0,4))[1]!==strlen($icc)))throw new InvalidArgumentException('Invalid ICC header.');
        $image=ImageEffects::prepare($path,'',true);
        if($icc===null)return $image; // Untagged RGB is assumed sRGB, explicitly documented.
        $ffi=self::engine();$source=null;$target=null;$transform=null;
        try {
            $source=$ffi->cmsOpenProfileFromMem($icc,strlen($icc));
            if($source===null||\FFI::isNull($source))throw new InvalidArgumentException('Cannot open embedded ICC profile.');
            if($ffi->cmsGetColorSpace($source)!==0x52474220)throw new InvalidArgumentException('Only RGB ICC profiles are supported.');
            $target=$ffi->cmsCreate_sRGBProfile();
            if($target===null||\FFI::isNull($target))throw new RuntimeException('Cannot create sRGB profile.');
            // TYPE_RGB_8 = COLORSPACE_SH(PT_RGB=4) | CHANNELS_SH(3) | BYTES_SH(1).
            $transform=$ffi->cmsCreateTransform($source,262169,$target,262169,1,0);
            if($transform===null||\FFI::isNull($transform))throw new InvalidArgumentException('Cannot transform embedded RGB profile.');
            $w=imagesx($image);$input=\FFI::new('unsigned char['.($w*3).']');$output=\FFI::new('unsigned char['.($w*3).']');
            imagealphablending($image,false);imagesavealpha($image,true);
            for($y=0;$y<imagesy($image);$y++){
                $alpha=[];
                for($x=0;$x<$w;$x++){$c=imagecolorat($image,$x,$y);$input[$x*3]=($c>>16)&255;$input[$x*3+1]=($c>>8)&255;$input[$x*3+2]=$c&255;$alpha[$x]=($c>>24)&127;}
                $ffi->cmsDoTransform($transform,$input,$output,$w);
                for($x=0;$x<$w;$x++)imagesetpixel($image,$x,$y,($alpha[$x]<<24)|($output[$x*3]<<16)|($output[$x*3+1]<<8)|$output[$x*3+2]);
            }
            return $image;
        } finally {
            if($transform!==null&&!\FFI::isNull($transform))$ffi->cmsDeleteTransform($transform);
            if($target!==null&&!\FFI::isNull($target))$ffi->cmsCloseProfile($target);
            if($source!==null&&!\FFI::isNull($source))$ffi->cmsCloseProfile($source);
        }
    }
}
