<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/ImageEffects.php';
require_once __DIR__ . '/CompositionTools.php';
require_once __DIR__ . '/CompositionPanel.php';

/** Deterministic local image placement; no generated pixels or external services. */
final class ImageComposition
{
    private static function keys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed)) throw new InvalidArgumentException('Unknown composition setting.');
        if (in_array(null, $value, true)) throw new InvalidArgumentException('Null composition settings are not supported; omit optional keys.');
    }

    private static function integer($value, int $min, int $max): int
    {
        if (!is_int($value) || $value < $min || $value > $max) throw new InvalidArgumentException('Composition integer outside allowed range.');
        return $value;
    }

    private static function size($w, $h): array
    {
        $w = self::integer($w, 1, 24000000); $h = self::integer($h, 1, 24000000);
        if ($w > 24000000 / $h) throw new InvalidArgumentException('Maximum 24 million pixels per image.');
        return [$w, $h];
    }

    private static function path($value, string $root): string
    {
        if (!is_string($value) || $value === '' || str_contains($value, "\0") || str_contains($value, '://')) throw new InvalidArgumentException('Local image path required.');
        $path = realpath(str_starts_with($value, '/') ? $value : $root . '/' . $value);
        if ($path === false || !is_file($path) || !is_readable($path) || filesize($path) > 40 * 1024 * 1024) throw new InvalidArgumentException('Readable local image up to 40MB required.');
        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) throw new InvalidArgumentException('Composition accepts PNG/JPEG only; normalize other formats first.');
        self::size($info[0], $info[1]);
        return $path;
    }

    private static function load(string $path, bool $srgb, bool $convert = false): GdImage
    {
        if ($convert) { require_once __DIR__ . '/ColorProfiles.php'; $image = ColorProfiles::normalize($path); }
        else $image = ImageEffects::prepare($path, '', $srgb);
        imagealphablending($image, false); imagesavealpha($image, true);
        return $image;
    }

    /** Crop coordinates refer to normalized (EXIF-oriented) source pixels. */
    private static function place(GdImage $canvas, GdImage $src, array $layer): void
    {
        [$sx, $sy, $sw, $sh] = [0, 0, imagesx($src), imagesy($src)];
        [$px,$py] = $layer['position'] ?? [.5,.5];
        $dw = $layer['width']; $dh = $layer['height']; $dx = $layer['x']; $dy = $layer['y'];
        if ($layer['fit'] === 'cover') {
            if ($sw / $sh > $dw / $dh) { $n = max(1, (int) round($sh * $dw / $dh)); $sx = isset($layer['focus']) ? (int)round(max(0,min($sw-$n,$layer['focus'][0]*$sw-$n/2))) : (int)round(($sw-$n)*$px); $sw = $n; }
            else { $n = max(1, (int) round($sw * $dh / $dw)); $sy = isset($layer['focus']) ? (int)round(max(0,min($sh-$n,$layer['focus'][1]*$sh-$n/2))) : (int)round(($sh-$n)*$py); $sh = $n; }
        } elseif ($layer['fit'] === 'contain') {
            $scale = min($dw / $sw, $dh / $sh);
            $w = max(1, (int) round($sw * $scale)); $h = max(1, (int) round($sh * $scale));
            $dx += (int)round(($dw-$w)*$px); $dy += (int)round(($dh-$h)*$py); $dw = $w; $dh = $h;
        }
        // Resize on a transparent temporary layer so alpha is preserved before source-over.
        $tile = imagecreatetruecolor($dw, $dh);
        imagealphablending($tile, false); imagesavealpha($tile, true);
        imagefill($tile, 0, 0, imagecolorallocatealpha($tile, 0, 0, 0, 127));
        if (!imagecopyresampled($tile, $src, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh)) throw new RuntimeException('Resize failed.');
        imagealphablending($canvas, true);
        if (!imagecopy($canvas, $tile, $dx, $dy, 0, 0, $dw, $dh)) throw new RuntimeException('Composition failed.');
        imagedestroy($tile);
    }

    public static function sheet(array $config, string $root, string $output, bool $zip = false, bool $srgb = false): array
    {
        self::keys($config,['items','columns','cell_width','cell_height','gap','label_height','font','font_size','background','color','name','quality','min_quality','max_bytes','normalize_srgb']);
        $items=$config['items']??null;
        if(!is_array($items)||!array_is_list($items)||!$items||count($items)>16)throw new InvalidArgumentException('Sheet requires 1..16 items.');
        $columns=self::integer($config['columns']??3,1,10);$cw=self::integer($config['cell_width']??400,1,10000);$ch=self::integer($config['cell_height']??300,1,10000);
        $gap=self::integer($config['gap']??20,0,1000);$lh=self::integer($config['label_height']??50,1,1000);
        $font=CompositionTools::font($config['font']??(dirname(__DIR__).'/pdfmaker/tfpdf/font/unifont/ipaexg.ttf'),$root);
        $layers=[];
        foreach($items as $i=>$item){
            if(!is_array($item))throw new InvalidArgumentException('Sheet item must be an object.');
            self::keys($item,['input','label']);
            if(!is_string($item['label']??null)||$item['label']==='')throw new InvalidArgumentException('Each sheet item requires a label.');
            $x=$gap+($i%$columns)*($cw+$gap);$y=$gap+intdiv($i,$columns)*($ch+$lh+$gap);
            $layers[]=['input'=>$item['input']??null,'x'=>$x,'y'=>$y,'width'=>$cw,'height'=>$ch,'fit'=>'contain'];
            $layers[]=['type'=>'text','text'=>$item['label'],'font'=>$font,'font_size'=>$config['font_size']??20,'color'=>$config['color']??'#202020','align'=>'center','x'=>$x,'y'=>$y+$ch,'width'=>$cw,'height'=>$lh];
        }
        $job=['name'=>$config['name']??'contact-sheet.png','width'=>$columns*$cw+($columns+1)*$gap,
            'height'=>(int)ceil(count($items)/$columns)*($ch+$lh+$gap)+$gap,'background'=>$config['background']??'#FFFFFF','layers'=>$layers];
        foreach(['quality','min_quality','max_bytes','normalize_srgb'] as $key)if(isset($config[$key]))$job[$key]=$config[$key];
        return self::batch(['jobs'=>[$job]],$root,$output,$zip,$srgb);
    }

    public static function batch(array $config, string $root, string $output, bool $zip = false, bool $srgb = false): array
    {
        self::keys($config, ['jobs', 'templates']);
        $templates=$config['templates']??[];
        if(!is_array($templates)||count($templates)>100)throw new InvalidArgumentException('templates must be a map of up to 100 jobs.');
        foreach($templates as $name=>$template)if(!is_string($name)||!is_array($template)||isset($template['template']))throw new InvalidArgumentException('Templates must be named job objects without inheritance.');
        $jobs = $config['jobs'] ?? null;
        if (!is_array($jobs) || !array_is_list($jobs) || count($jobs) < 1 || count($jobs) > 100) throw new InvalidArgumentException('jobs must contain 1..100 entries.');
        if ($zip && !class_exists(\ZipArchive::class)) throw new RuntimeException('PHP zip extension required.');
        if (file_exists($output) || is_link($output) || !is_dir(dirname($output)) || !is_writable(dirname($output))) throw new InvalidArgumentException('New output directory with writable parent required.');
        $names = []; $plan = []; $pixels = 0;
        foreach ($jobs as $job) {
            if (!is_array($job)) throw new InvalidArgumentException('Job must be an object.');
            if(isset($job['template'])){
                if(!is_string($job['template'])||!isset($templates[$job['template']]))throw new InvalidArgumentException('Unknown composition template.');
                $key=$job['template'];unset($job['template']);$job=array_replace($templates[$key],$job);
            }
            self::keys($job, ['name', 'base', 'width', 'height', 'background', 'layers', 'padding', 'quality', 'min_quality', 'max_bytes', 'matte', 'normalize_srgb']);
            $name = $job['name'] ?? '';
            if (!is_string($name) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,79}\.(png|jpe?g|webp)$/D', $name) || isset($names[strtolower($name)])) throw new InvalidArgumentException('Unique plain PNG/JPEG/WebP filename required.');
            $names[strtolower($name)] = true;
            $format=pathinfo($name,PATHINFO_EXTENSION);if($format==='jpg')$format='jpeg';
            if($format==='webp'&&!function_exists('imagewebp'))throw new RuntimeException('GD WebP support required.');
            $quality=self::integer($job['quality']??85,1,100);$minimum=self::integer($job['min_quality']??min(40,$quality),1,$quality);
            $maximum=isset($job['max_bytes'])?self::integer($job['max_bytes'],1,100*1024*1024):null;
            $convert=$job['normalize_srgb']??false;if(!is_bool($convert))throw new InvalidArgumentException('normalize_srgb must be boolean.');
            $padding=$job['padding']??[0,0,0,0];
            if(!is_array($padding)||!array_is_list($padding)||count($padding)!==4)throw new InvalidArgumentException('padding must be [top,right,bottom,left].');
            foreach($padding as $v)self::integer($v,0,24000000);
            $base = isset($job['base']) ? self::path($job['base'], $root) : null;
            if ($base && (array_key_exists('width', $job) || array_key_exists('height', $job) || array_key_exists('background', $job))) throw new InvalidArgumentException('Base dimensions/background are retained; omit width/height/background.');
            if ($base) {
                $im = self::load($base, $srgb, $convert); $w = imagesx($im); $h = imagesy($im); imagedestroy($im);
            } else { [$w, $h] = self::size($job['width'] ?? null, $job['height'] ?? null); }
            [$ow,$oh]=self::size($w+$padding[1]+$padding[3],$h+$padding[0]+$padding[2]);
            $pixels += $ow * $oh;
            if ($pixels > 240000000) throw new InvalidArgumentException('Maximum 240 million output pixels per batch.');
            $bg = $job['background'] ?? '#FFFFFF';
            CompositionTools::color($bg);
            $matte=$job['matte']??'#FFFFFF';CompositionTools::color($matte);
            if($format==='jpeg'&&$matte==='transparent')throw new InvalidArgumentException('JPEG matte must be opaque.');
            $layers = $job['layers'] ?? [];
            if (!is_array($layers) || !array_is_list($layers) || count($layers) > 32 || (!$base && !$layers)) throw new InvalidArgumentException('Up to 32 layers required; blank jobs unsupported.');
            foreach ($layers as &$layer) {
                if (!is_array($layer)) throw new InvalidArgumentException('Layer must be an object.');
                $type=$layer['type']??'image';
                if ($type === 'panel') {
                    $layer = CompositionPanel::validate($layer, $w, $h);
                    continue;
                }
                if (in_array($type, ['arrow','rect'], true)) {
                    self::keys($layer, ['type','x','y','x2','y2','color','stroke_width','head_length','head_angle']);
                    foreach (['x','x2'] as $key) $layer[$key] = self::integer($layer[$key] ?? null, 0, $w-1);
                    foreach (['y','y2'] as $key) $layer[$key] = self::integer($layer[$key] ?? null, 0, $h-1);
                    $layer['stroke_width'] = self::integer($layer['stroke_width'] ?? 4, 1, 64);
                    $layer['color'] = $layer['color'] ?? '#DE3029';
                    CompositionTools::color($layer['color']);
                    if ($layer['color'] === 'transparent') throw new InvalidArgumentException('Shape color must be opaque.');
                    if ($type === 'arrow') {
                        $layer['head_length'] = self::integer($layer['head_length'] ?? 18, 1, 1000);
                        $angle = $layer['head_angle'] ?? 0.5;
                        if ((!is_int($angle) && !is_float($angle)) || !is_finite((float)$angle) || $angle <= 0 || $angle >= M_PI/2) throw new InvalidArgumentException('head_angle must be radians between 0 and pi/2.');
                        $layer['head_angle'] = $angle;
                        if ($layer['x'] === $layer['x2'] && $layer['y'] === $layer['y2']) throw new InvalidArgumentException('Arrow endpoints must differ.');
                    } elseif (isset($layer['head_length']) || isset($layer['head_angle'])) throw new InvalidArgumentException('Arrow head settings require arrow type.');
                    continue;
                }
                if(!in_array($type,['image','text'],true))throw new InvalidArgumentException('Layer type must be image/text.');
                self::keys($layer,$type==='image'?['type','input','x','y','width','height','fit','crop','position','focus','rotate','flip']:['type','text','font','font_size','body_width','color','align','line_height','outlines','x','y','width','height']);
                $layer['x'] = self::integer($layer['x'] ?? 0, 0, $w - 1); $layer['y'] = self::integer($layer['y'] ?? 0, 0, $h - 1);
                [$layer['width'], $layer['height']] = self::size($layer['width'] ?? null, $layer['height'] ?? null);
                if ($layer['x'] + $layer['width'] > $w || $layer['y'] + $layer['height'] > $h) throw new InvalidArgumentException('Layer rectangle exceeds canvas.');
                if($type==='text'){
                    if(!is_string($layer['text']??null)||$layer['text']===''||strlen($layer['text'])>16000||!mb_check_encoding($layer['text'],'UTF-8')||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$layer['text']))throw new InvalidArgumentException('Nonempty UTF-8 text up to 16000 bytes required.');
                    $layer['font']=CompositionTools::font($layer['font']??null,$root);
                    $layer['font_size']=self::integer($layer['font_size']??24,1,1000);
                    $layer['body_width']=self::integer($layer['body_width']??0,0,32);
                    $layer['color']=$layer['color']??'#000000';CompositionTools::color($layer['color']);
                    $layer['outlines']=CompositionTools::outlines($layer['outlines']??[]);
                    $layer['align']=$layer['align']??'left';if(!in_array($layer['align'],['left','center','right'],true))throw new InvalidArgumentException('Text align must be left/center/right.');
                    $layer['line_height']=$layer['line_height']??1.4;
                    if((!is_int($layer['line_height'])&&!is_float($layer['line_height']))||!is_finite((float)$layer['line_height'])||$layer['line_height']<1||$layer['line_height']>5)throw new InvalidArgumentException('line_height must be 1..5.');
                    $probe=CompositionTools::canvas(1,1,'transparent');CompositionTools::text($probe,$layer,false);imagedestroy($probe);
                    continue;
                }
                $layer['input'] = self::path($layer['input'] ?? null, $root);
                $layer['fit'] = $layer['fit'] ?? 'contain';
                if (!in_array($layer['fit'], ['contain', 'cover', 'stretch'], true)) throw new InvalidArgumentException('fit must be contain/cover/stretch.');
                foreach(['position','focus'] as $option)if(isset($layer[$option])){
                    if(!is_array($layer[$option])||!array_is_list($layer[$option])||count($layer[$option])!==2)throw new InvalidArgumentException($option.' must be [x,y] in 0..1.');
                    foreach($layer[$option] as $v)if((!is_int($v)&&!is_float($v))||!is_finite((float)$v)||$v<0||$v>1)throw new InvalidArgumentException($option.' must be numeric in 0..1.');
                }
                if(isset($layer['focus'])&&($layer['fit']!=='cover'||isset($layer['position'])))throw new InvalidArgumentException('focus requires cover and cannot combine with position.');
                $layer['rotate']=$layer['rotate']??0;
                if((!is_int($layer['rotate'])&&!is_float($layer['rotate']))||!is_finite((float)$layer['rotate'])||abs($layer['rotate'])>360)throw new InvalidArgumentException('rotate must be clockwise degrees -360..360.');
                $layer['flip']=$layer['flip']??'none';if(!in_array($layer['flip'],['none','horizontal','vertical','both'],true))throw new InvalidArgumentException('Invalid flip.');
                if (isset($layer['crop'])) {
                    if (!is_array($layer['crop']) || !array_is_list($layer['crop']) || count($layer['crop']) !== 4) throw new InvalidArgumentException('crop must be [x,y,width,height].');
                    foreach ($layer['crop'] as $i => $v) self::integer($v, $i < 2 ? 0 : 1, 24000000);
                }
                // Decode and validate every source before creating any outputs.
                $im = self::load($layer['input'], $srgb, $convert);
                if (isset($layer['crop'])) {
                    [$cx, $cy, $cw, $ch] = $layer['crop'];
                    if ($cx + $cw > imagesx($im) || $cy + $ch > imagesy($im)) throw new InvalidArgumentException('Crop exceeds normalized source.');
                }
                $im=CompositionTools::transform($im,$layer);imagedestroy($im);
            }
            unset($layer);
            $plan[] = ['name' => $name, 'base' => $base, 'width' => $w, 'height' => $h, 'background' => $bg, 'layers' => $layers,
                'padding'=>$padding,'format'=>$format,'quality'=>$quality,'minimum'=>$minimum,'maximum'=>$maximum,'matte'=>$matte,'convert'=>$convert];
        }
        if (!mkdir($output, 0777)) throw new RuntimeException('Cannot create output directory.');
        $created = []; $report = ['operation' => 'compose_batch', 'jobs' => []];
        try {
            foreach ($plan as $job) {
                $canvas = $job['base'] ? self::load($job['base'], $srgb,$job['convert']) : CompositionTools::canvas($job['width'],$job['height'],$job['background']);
                imagesavealpha($canvas, true);
                $inputs = [];
                if ($job['base']) $inputs[] = ['path' => $job['base'], 'sha256' => hash_file('sha256', $job['base'])];
                foreach ($job['layers'] as $layer) {
                    if (($layer['type'] ?? '') === 'panel') {
                        CompositionPanel::draw($canvas, $layer); continue;
                    }
                    if (in_array($layer['type'] ?? '', ['arrow','rect'], true)) {
                        CompositionTools::shape($canvas, $layer); continue;
                    }
                    if(($layer['type']??'image')==='text'){
                        CompositionTools::text($canvas,$layer);$inputs[]=['path'=>$layer['font'],'sha256'=>hash_file('sha256',$layer['font'])];continue;
                    }
                    $src = CompositionTools::transform(self::load($layer['input'], $srgb,$job['convert']),$layer); self::place($canvas, $src, $layer); imagedestroy($src);
                    $inputs[] = ['path' => $layer['input'], 'sha256' => hash_file('sha256', $layer['input'])];
                }
                $path = $output . '/' . $job['name'];
                [$top,$right,$bottom,$left]=$job['padding'];
                if($top||$right||$bottom||$left){
                    $padded=CompositionTools::canvas(imagesx($canvas)+$left+$right,imagesy($canvas)+$top+$bottom,$job['base']?$job['matte']:$job['background']);
                    imagealphablending($padded,true);imagecopy($padded,$canvas,$left,$top,0,0,imagesx($canvas),imagesy($canvas));imagedestroy($canvas);$canvas=$padded;
                }
                $encoded=CompositionTools::encode($canvas,$job['format'],$job['quality'],$job['minimum'],$job['maximum'],$job['matte']);
                $stream=fopen($path,'xb');if(!$stream)throw new RuntimeException('Cannot create output image.');$created[]=$path;
                try { $offset=0;$bytes=$encoded['bytes'];while($offset<strlen($bytes)){$n=fwrite($stream,substr($bytes,$offset));if(!$n)throw new RuntimeException('Cannot write output image.');$offset+=$n;} }
                finally { fclose($stream); }
                $report['jobs'][] = ['file' => $job['name'], 'width' => imagesx($canvas), 'height' => imagesy($canvas),
                    'format'=>$job['format'],'quality'=>$encoded['quality'],'bytes'=>strlen($encoded['bytes']),'normalize_srgb'=>$job['convert'],
                    'padding'=>$job['padding'],'matte'=>$job['matte'],'background'=>$job['background'],
                    'sha256' => hash_file('sha256', $path), 'inputs' => $inputs, 'layers' => $job['layers']];
                imagedestroy($canvas);
            }
            $path = $output . '/report.json';
            $bytes = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            $stream = fopen($path, 'xb');
            if (!$stream) throw new RuntimeException('Cannot create report.');
            $created[] = $path;
            try { if (fwrite($stream, $bytes) !== strlen($bytes)) throw new RuntimeException('Cannot write report.'); }
            finally { fclose($stream); }
            if ($zip) {
                $archive = new \ZipArchive(); $zipPath = $output . '/images.zip';
                if ($archive->open($zipPath, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create ZIP.');
                $created[] = $zipPath;
                try {
                    foreach ($report['jobs'] as $job) if (!$archive->addFile($output . '/' . $job['file'], $job['file'])) throw new RuntimeException('Cannot add ZIP entry.');
                } finally { if (!$archive->close()) throw new RuntimeException('Cannot finalize ZIP.'); }
            }
            return $report;
        } catch (\Throwable $e) {
            foreach (array_reverse($created) as $path) if (is_file($path)) unlink($path);
            rmdir($output);
            throw $e;
        }
    }
}
