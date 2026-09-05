<?php
// Run with the framework runtime directory as the optional first argument.
require ($argv[1] ?? dirname(__DIR__) . '/fbp') . '/lib/pdfmaker/pdfmaker_class.php';
class TextBoxProbe extends CustomizedPDF {
    public array $drawn = [];
    public array $clips = [];
    public function _out($s) {
        if (str_contains($s, "re W n") || $s === "Q") $this->clips[]=$s;
        parent::_out($s);
    }
    public function Text($x, $y, $text) {
        $this->drawn[] = [$x, $y, $text, $this->GetStringWidth($text), $this->FontSizePt];
        parent::Text($x, $y, $text);
    }
}
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$pdf = new TextBoxProbe('P', 'mm', 'A4');
$pdf->AddFont('gothic', '', 'ipag.ttf', true);
$pdf->AddPage();
$pdf->SetFont('gothic', '', 10);
$pdf->SetXY(70, 80);
$base = ['x'=>10, 'y'=>20, 'width'=>40, 'height'=>10, 'fontsize'=>10];
foreach (['L','C','R'] as $align) foreach (['top','middle','bottom'] as $valign) {
    $pdf->drawn=[];
    $pdf->TextBox('日本語 ABC', $base+['align'=>$align,'valign'=>$valign,'overflow'=>'error']);
    [$x,$y,$text,$width] = $pdf->drawn[0];
    check($x>=10 && $x+$width<=50.001 && $y>=20 && $y<=30, 'Alignment outside box');
    $expected = $align==='L' ? 10 : ($align==='R' ? 50-$width : 10+(40-$width)/2);
    check(abs($x-$expected)<.0001, 'Wrong horizontal alignment');
}
$pdf->drawn=[];
$pdf->TextBox(str_repeat('日本語 ABC ',20), $base+['overflow'=>'ellipsis']);
check(str_ends_with(end($pdf->drawn)[2], '…'), 'Missing ellipsis');
foreach($pdf->drawn as [$x,$y,$text,$width]) check($width<=40.001 && $y<=30, 'Ellipsis outside box');
$pdf->drawn=[];
$pdf->TextBox('370370367369', ['x'=>10,'y'=>20,'width'=>15,'height'=>8,'fontsize'=>10,'min_fontsize'=>4,'wrap'=>false,'overflow'=>'shrink']);
check(count($pdf->drawn)===1 && $pdf->drawn[0][3]<=15.001 && $pdf->drawn[0][4]<10, 'Shrink failed');
$pdf->drawn=[];
$pdf->TextBox(str_repeat('長い備考',50), $base+['overflow'=>'shrink','min_fontsize'=>9]);
check(str_ends_with(end($pdf->drawn)[2], '…'), 'Shrink fallback failed');
$pdf->drawn=[];
try { $pdf->TextBox(str_repeat('長い備考',50), $base+['overflow'=>'error']); throw new RuntimeException('Overflow accepted'); }
catch (OverflowException $e) { check(!$pdf->drawn, 'Error mode drew text'); }
$pdf->drawn=[];
$pdf->TextBox("一行目\n二行目", ['x'=>10,'y'=>20,'width'=>40,'height'=>4,'fontsize'=>10,'overflow'=>'clip','complete_lines'=>true]);
check(count($pdf->drawn)===1, 'Incomplete line was drawn');
foreach ([['width'=>0],['padding'=>20],['lineheight'=>1],['overflow'=>'unknown']] as $bad) {
    try { $pdf->TextBox('test', array_replace($base,$bad)); throw new RuntimeException('Invalid option accepted'); }
    catch (InvalidArgumentException $e) {}
}
check(abs($pdf->GetX()-70)<.0001 && abs($pdf->GetY()-80)<.0001 && $pdf->PageNo()===1 && abs($pdf->FontSizePt-10)<.0001, 'Cursor/page/font changed');
$pdf->TextBox('clip', ['x'=>10,'y'=>290,'width'=>3,'height'=>1,'fontsize'=>10,'wrap'=>false]);
check($pdf->PageNo()===1, 'Fixed box triggered page break');
check(count($pdf->clips)>0 && count($pdf->clips)%2===0, 'Unbalanced clipping');
foreach(array_chunk($pdf->clips,2) as [$start,$end]) check(str_contains($start,'re W n') && $end==='Q', 'Clipping leaked');
echo "PASS: alignment, wrapping, ellipsis, shrink/fallback, clip, validation, cursor/page/font preservation\n";
